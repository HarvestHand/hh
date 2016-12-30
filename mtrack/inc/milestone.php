<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackMilestone {
  public $mid = null;
  public $pmid = null;
  public $name = null;
  public $description = null;
  public $duedate = null;
  public $startdate = null;
  public $deleted = null;
  public $completed = null;
  public $created = null;

  static function loadByName($name)
  {
    foreach (MTrackDB::q('select mid from milestones where lower(name) = lower(?)', $name)
        ->fetchAll() as $row) {
      return new self($row[0]);
    }
    return null;
  }

  static function loadByID($id)
  {
    foreach (MTrackDB::q('select mid from milestones where mid = ?', $id)
        ->fetchAll() as $row) {
      return new self($row[0]);
    }
    return null;
  }

  static function enumMilestones($all = false)
  {
    if ($all === 'closed') {
      $q = MTrackDB::q('select mid, name from milestones where completed is not null and deleted != 1');
    } elseif ($all) {
      $q = MTrackDB::q('select mid, name from milestones where deleted != 1');
    } else {
      $q = MTrackDB::q('select mid, name from milestones where completed is null and deleted != 1');
    }
    $res = array();
    foreach ($q->fetchAll(PDO::FETCH_NUM) as $row) {
      $res[$row[0]] = $row[1];
    }
    return $res;
  }

  function __construct($id = null)
  {
    if ($id !== null) {
      $this->mid = $id;

      list($row) = MTrackDB::q('select * from milestones where mid = ?', $id)
        ->fetchAll(PDO::FETCH_ASSOC);
      foreach ($row as $k => $v) {
        $this->$k = $v;
      }
    }
    $this->deleted = false;
  }

  function save(MTrackChangeset $CS)
  {
    $this->updated = $CS->cid;

    if ($this->mid === null) {
      $this->created = $CS->cid;

      MTrackDB::q('insert into milestones
          (name, description, startdate, duedate, completed, created,
            pmid, updated, deleted)
          values (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $this->name,
        $this->description,
        $this->startdate,
        $this->duedate,
        $this->completed,
        $this->created,
        $this->pmid,
        $this->updated,
        (int)$this->deleted);

      $this->mid = MTrackDB::lastInsertId('milestones', 'mid');
    } else {
      list($old) = MTrackDB::q(
          'select * from milestones where mid = ?', $this->mid)
          ->fetchAll(PDO::FETCH_ASSOC);
      foreach ($old as $k => $v) {
        if ($k == 'mid' || $k == 'created' || $k == 'updated') {
          continue;
        }
        $CS->add("milestone:$this->mid:$k", $v, $this->$k);
      }
      MTrackDB::q('update milestones set name = ?,
          description = ?, startdate = ?, duedate = ?, completed = ?,
          updated = ?, deleted = ?, pmid = ?
          WHERE mid = ?',
        $this->name,
        $this->description,
        $this->startdate,
        $this->duedate,
        $this->completed,
        $this->updated,
        (int)$this->deleted,
        $this->pmid,
        $this->mid);
    }
  }

  static function index_item($object)
  {
    list($ignore, $mid) = explode(':', $object, 2);
    if (preg_match("/^\d+$/", $mid)) {
      $M = self::loadByID($mid);
    } else {
      $M = self::loadByName($mid);
    }

    MTrackSearchDB::add("milestone:$M->mid", array(
      'type' => 'milestone',
      'milestone' => $M->name,
      'description' => $M->description,
    ), true);
  }

  /** Renders a burndown chart for the named milestone */
  static function macro_BurnDown() {
    global $ABSWEB;

    $args = func_get_args();

    if (!count($args) || (count($args) == 1 && $args[0] == '')) {
      # Special case for allowing burndown to NOP in the milestone summary
      return '';
    }

    $params = array(
      'width' => '75%',
      'height' => '250px',
    );

    foreach ($args as $arg) {
      list($name, $value) = explode('=', $arg, 2);
      $params[$name] = $value;
    }

    $m = MTrackMilestone::loadByName($params['milestone']);
    if (!$m) {
      return "BurnDown: milestone $params[milestone] is invalid<br>\n";
    }
    if (!MTrackACL::hasAllRights("milestone:" . $m->mid, 'read')) {
      return "Not authorized to view milestone $name<br>\n";
    }

    /* compute total "initial estimate" value */
    $last_estimate = 0;
    foreach (MTrackDB::q(<<<SQL
select sum(estimated)
 from ticket_milestones tm
 left join tickets t on (tm.tid = t.tid)
where (mid = ?
  or (mid in (select mid from milestones where pmid = ?))
)
SQL
      , $m->mid, $m->mid)->fetchAll(PDO::FETCH_NUM) as $row) {
      $last_estimate = round($row[0]);
    }

    /* step 1: find all changes on this milestone and its children */
    $effort = MTrackDB::q("
      select expended, remaining, changedate
      from
        ticket_milestones tm
      left join
        effort e on (tm.tid = e.tid)
      left join
        changes c on (e.cid = c.cid)
      where (mid = ?
        or (mid in (select mid from milestones where pmid = ?))
      )
         and c.changedate is not null
      order by c.changedate",
      $m->mid, $m->mid)->fetchAll(PDO::FETCH_NUM);
    /* accumulated work spent by day */
    $accum_spent_by_day = array();
    /* accumulated remaining hours by day */
    $accum_remain_by_day = array();
    $last_remain = $last_estimate;

    $current_estimate = null;
    $min_day = null;
    $total_exp = 0;
    $max_y = 0;

    if ($m->startdate) {
      $min_day = strtotime($m->startdate);
    }

    $maxday = $min_day;
    $granularity = 1500;

    foreach ($effort as $info) {
      list($exp, $rem, $date) = $info;
      $ts = strtotime($date);
      $ts -= $ts % $granularity;
      $day = $ts;

      /* previous accumulation carries over */
      if (!isset($accum_spent_by_day[$day])) {
        $accum_spent_by_day[$day] = $total_exp;
      }
      if (!isset($accum_remain_by_day[$day])) {
        $accum_remain_by_day[$day] = $last_remain;
      }

      if ($rem !== null) {
        if ($min_day === null) {
          $min_day = $ts;
        }
        $accum_remain_by_day[$day] += $rem;
        $last_remain = $accum_remain_by_day[$day];
        $max_y = max($last_remain, $max_y);
        if ($ts > $maxday && $rem != 0) $maxday = $ts;
      }

      if ($exp !== null) {
        if ($exp != 0 && $min_day === null) {
          $min_day = $ts;
        }
        $accum_spent_by_day[$day] += $exp;
        $total_exp += $exp;
        $max_y = max($total_exp, $max_y);

        if ($ts > $maxday && $exp != 0) $maxday = $ts;
      }
    }

    /* limit the view to the past 3 weeks */
    $earliest = strtotime('-3 week');
    if ($min_day < $earliest) {
//      $min_day = $earliest;
    }
    $min_day *= 1000;

    if ($m->duedate) {
      $ts = strtotime($m->duedate);
      $maxday = max($maxday, $ts);
    } elseif (!$maxday) {
      $maxday = time();
    }
    $maxday = strtotime('1 week', $maxday);
    $maxday *= 1000;

    /* step 3: compute the day by day remaining value,
     * and produce data series for remaining and expended time */

    $js_remain = array();
    $js_estimate = array();
    $js_spent = array();
    $rick_remain = array();
    $rick_spent = array();

    $trend = array();
    foreach ($accum_remain_by_day as $day => $remaining) {
      /* compute javascript timestamp */
      $ts = $day * 1000;
      if ($ts < $min_day) continue;
      $js_remain[] = array($ts, $remaining);
      $rick_remain[] = array('x' => $ts/1000, 'y' => $remaining);
    }

    foreach ($accum_spent_by_day as $day => $spent) {
      if ($spent == 0 && !count($js_spent)) continue;
      /* compute javascript timestamp */
      $ts = $day * 1000;
      if ($ts < $min_day) continue;
      $js_spent[] = array($ts, $spent);
      $rick_spent[] = array('x' => $ts/1000, 'y' => $spent);
    }

    $js_remain = json_encode($js_remain);
    $js_spent = json_encode($js_spent);

    $flot = "bd_graph_" . sha1(join(':', $args) . time());

    $height = (int)$params['height'];
    $max_y = max($max_y, $last_estimate);

    // avoid weird height computation problem for webkit based browsers;
    // they need to see the px unit in the height property
    if (!strcmp($height, $params['height'])) {
      $params['height'] .= 'px';
    }

    $flot_html = <<<HTML
<div id='$flot' class='flotgraph'
  style='width: $params[width]; height: $params[height];'></div>
<div id='legend$flot' class='flotlegend'></div>
<script id='source_$flot' language='javascript' type='text/javascript'>
\$(function () {
  var p = \$('#$flot');
  var series = [
    { label: "spent", data: $js_spent, color: '#77a' },
    { label: "remaining", data: $js_remain, color: '#a77' }
  ];
  var plot = \$.plot(p, series, {
     xaxis: {
       mode: "time",
       timeformat: '%b %d',
       min: $min_day,
       max: $maxday
     },
     yaxis: {
//       max: $max_y
     },
    series: {
      lines: { show: true, fill: true },
      points: { show: true }
    },
     legend: {
      position: 'sw',
      container: '#legend$flot'
     },
     grid: {
      hoverable: true,
      backgroundColor: { colors: ['#fff', '#eee'] }
     }
    }
  );
});
</script>
HTML;
    $rick_remain = json_encode($rick_remain);
    $rick_spent = json_encode($rick_spent);

    $html = <<<HTML
<style>
div.bdgraph {
  position: relative;
  display: inline-block;
  margin-top: 1em;
}
div.bdgraph div.yaxis {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 40px;
}
div.bdgraph div.chart {
  position: relative;
  top: 0px;
  left: 40px;
}
div.bdlegend {
  display: inline-block;
  vertical-align: top;
  margin: 0 0 0 10px;
}
</style>
<div id='$flot' class='bdgraph'
    style="height:$params[height]; width: $params[width];">
  <div class="yaxis"></div>
  <div class="chart"></div>
</div>
<script>
\$(function () {
  var series = [];
  var spent = $rick_spent;
  var remain = $rick_remain;

  if (remain.length) {
    series.push({
      data: remain,
      color: '#a77',
      name: 'Remaining'
    });
  }
  if (spent.length) {
    series.push({
      data: spent,
      color: '#77a',
      name: 'Spent'
    });
  }

  var elem = $('#$flot');

  if (!series.length) {
    elem.remove();
    return;
  }

  var yaxis = $('div.yaxis', elem);
  var chart = $('div.chart', elem);
  Rickshaw.Series.zeroFill(series);
  var graph = new Rickshaw.Graph({
    element: chart.get(0),
    renderer: 'stack',
    stroke: true,
    strokeWidth: 4,
    width: elem.width() - 40,
    height: elem.height(),
    series: series
  });
  var time = new Rickshaw.Fixtures.Time();
  var x_axis = new Rickshaw.Graph.Axis.Time({
    graph: graph,
    ticksTreatment: 'glow'
  });
  var hover = new Rickshaw.Graph.HoverDetail({
    graph: graph
  });
  var y_axis = new Rickshaw.Graph.Axis.Y({
    graph: graph,
    orientation: 'left',
    element: yaxis.get(0)
  });

  var leg = $('<div/>', {class: 'bdlegend'});
  leg.insertAfter(elem);
  var legend = new Rickshaw.Graph.Legend({
    element: leg.get(0),
    graph: graph
  });
  var but = $('<button/>', {class: 'btn btn-inverse', style: 'float:right'});
  but.text('line');
  but.appendTo(leg);
  but.click(function () {
    if (but.text() == 'line') {
      graph.setRenderer('line');
      but.text('stack');
    } else {
      graph.setRenderer('stack');
      but.text('line');
    }
    graph.render();
  });
  var shelving = new Rickshaw.Graph.Behavior.Series.Toggle({
    graph: graph,
    legend: legend
  });
  graph.render();
});
</script>
HTML;

    // to use flot instead of rickshaw, uncomment this line
    //  $html = $flot_html;

    $total_exp = round($total_exp);


    return
      "<div class='burndown'>Initial Estimate: $last_estimate, Work expended: $total_exp<br>\n"
      . $html . "</div>";
  }

  /** Displays a summary of the named milestone */
  static function macro_MilestoneSummary($name) {
    global $ABSWEB;

    $m = self::loadByName($name);
    if (!$m) {
      return "milestone: " . htmlentities($name) . " not found<br>\n";
    }

    if (!MTrackACL::hasAllRights("milestone:" . $m->mid, 'read')) {
      return "Not authorized to view milestone $name<br>\n";
    }

    $completed = mtrack_date($m->completed);
    $description = $m->description;
    if (strpos($description, "[[BurnDown(") === false) {
      $description = "[[BurnDown(milestone=$name,width=50%,height=150)]]\n" .
        $description;
    }
    $desc = MTrackWiki::format_to_html($description);
    $pname = $name;
    if ($m->completed !== NULL) {
      $pname = "<del>$name</del>";
      $due = "Completed";
    } elseif ($m->duedate) {
      $due = "Due " . mtrack_date($m->duedate);
    } else {
      $due = null;
    }

    $watch = MTrackWatch::getWatchUI('milestone', $m->mid);

    if (MTrackACL::hasAllRights('Roadmap', 'modify')) {
      $qname = htmlentities($name, ENT_QUOTES, 'utf-8');
      $qname = urlencode($name);
      $qname = str_replace("%2F", '/', $qname);
      $edit = <<<HTML
<button class='btn' onclick="document.location.href='{$ABSWEB}milestone.php/$qname?edit=1';return false;"><i class='icon-edit'></i> Edit Milestone</button>
<button class='btn' onclick="document.location.href='{$ABSWEB}plan.php/$m->mid';return false;"><i class='icon-list'></i> Planning</button>
HTML;
    } else {
      $edit = '';
    }
    $html = <<<HTML
<div class="milestone">
<h2><a href="{$ABSWEB}milestone.php/$name">$pname</a></h2>
$watch $edit
<div class="due">$due</div>
$desc<br/>
HTML;

    $estimated = 0;
    $remaining = 0;
    $open = 0;
    $total = 0;

    foreach (MTrackDB::q('select status from ticket_milestones tm left join tickets t on (tm.tid = t.tid) where mid = ?',
        $m->mid)->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $total++;
      if ($row['status'] != 'closed') {
        $open++;
      }
    }

    $closed = $total - $open;
    if ($total) {
      $apct = (int)($open / $total * 100);
    } else {
      $apct = 0;
    }
    $cpct = 100 - $apct;
    $html .= <<<HTML
<div class='progress progress-success progress-striped' style='width: 50%'>
  <div class='bar' style='width:$cpct%;'></div>
</div>
HTML;


    $ms = urlencode($name);

    $html .= <<<HTML
<a href='{$ABSWEB}query.php?milestone=$ms&status!=closed'>$open open</a>,
<a href='{$ABSWEB}query.php?milestone=$ms&status=closed'>$closed closed</a>,
<a href='{$ABSWEB}query.php?milestone=$ms'>$total total</a> ($cpct % complete)
</div>
HTML;
    return $html;
  }

  static function rest_load_ms($captures) {
    $mid = $captures['mid'];
    if (ctype_digit($mid)) {
      $me = self::loadByID($mid);
    } else {
      $me = self::loadByName($mid);
    }
    return $me;
  }

  static function rest_milestone($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET', 'PUT');

    $ms = self::rest_load_ms($captures);
    if (!$ms) {
      MTrackAPI::error(404, "no such milestone", $captures['mid']);
    }
    if ($method == 'PUT') {
      MTrackACL::requireAllRights("Roadmap", 'modify');
      $in = MTrackAPI::getPayload();
      $CS = MTrackChangeset::begin("milestone:$ms->name");

      foreach (array('duedate', 'startdate', 'compdate') as $name) {
        if (isset($in->$name)) {
          if (strlen($in->$name)) {
            $ms->$name = MTrackDB::unixtime(strtotime($in->$name));
          } else {
            $ms->$name = null;
          }
        }
      }
      foreach (array('name', 'description', 'pmid') as $name) {
        if (isset($in->$name)) {
          $ms->$name = $in->$name;
        }
      }
      $ms->save($CS);
      $CS->commit();
    }

    $obj = MTrackAPI::makeObj($ms, 'mid');
    $obj->description_html = MTrackWiki::format_to_html($obj->description);
    return $obj;
  }

  /** Summarizes the remaining time across tickets associated with
   * this milestone along with a breakdown of time remaining per user */
  static function rest_time_remaining($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    $ms = self::rest_load_ms($captures);
    if (!$ms) {
      MTrackAPI::error(404, "no such milestone", $captures['mid']);
    }
    MTrackACL::requireAllRights("milestone:" . $ms->mid, 'read');

    $data = MTrackDB::q(<<<SQL
SELECT owner, sum(
  (case when t.status = 'closed' then 0 else
    (select coalesce(
      case when sum(remaining) < 0 then 0 else
        round(cast(sum(remaining) as numeric), 2)
      end, t.estimated)
      from effort where effort.tid = t.tid
    )
  end)) as remaining
FROM
  ticket_milestones tm
LEFT JOIN
  tickets t on (tm.tid = t.tid)
WHERE
  mid = ?
GROUP BY owner
SQL
      , $ms->mid)->fetchAll(PDO::FETCH_OBJ);

    $obj = new stdclass;
    $obj->total = 0;
    $obj->users = array();
    $obj->unassigned = 0;
    foreach ($data as $item) {
      $name = $item->owner;
      $obj->total += $item->remaining;
      if (!strlen($name)) {
        $obj->unassigned += $item->remaining;
      } else {
        $obj->users[$name] = $item->remaining;
      }
    }
    return $obj;
  }

  /* set the pri_ord field of the ticket_milestones table to reflect
   * the chosen order */
  static function rest_prioritize($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'POST');
    $ms = self::rest_load_ms($captures);
    if (!$ms) {
      MTrackAPI::error(404, "no such milestone", $captures['mid']);
    }

    $tkts = MTrackAPI::getPayload();

    $CS = MTrackChangeset::begin("milestone:$ms->name", "re-prioritize");
    $db = MTrackDB::get();
    $tweak = $db->prepare(
      "update ticket_milestones set pri_ord = ? where tid = ? and mid = ?");

    /* parent<->child stuff makes this more interesting.
     * Look through the input ticket list.
     * Each item that has children, we're going to put next in the run */
    $tkts_by_tid = array();
    $tkts_by_parent = array();

    foreach ($tkts->tickets as $id) {
      $tkt = MTrackIssue::loadById($id);
      if (!$tkt) {
        MTrackAPI::error(404, "no such ticket", $id);
      }
      MTrackACL::requireAllRights("ticket:" . $tkt->tid, 'modify');
      $tkts_by_tid[$id] = $tkt;

      $tkts_by_parent[$id] = MTrackDB::q(
        'select tid from tickets where ptid = ?', $id)
          ->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    $pri = 0;
    foreach ($tkts_by_tid as $tkt) {
      $tweak->execute(array($pri++, $tkt->tid, $ms->mid));
      foreach ($tkts_by_parent[$tkt->tid] as $kid) {
        $tweak->execute(array($pri++, $kid, $ms->mid));
      }
    }
    $CS->commit();
  }

  static function rest_tickets($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');

    $me = self::rest_load_ms($captures);
    if (!$me) {
      MTrackAPI::error(404, "no such milestone", $captures['mid']);
    }

    $result = array();
    $tickets = MTrackDB::q(
      "select tm.tid as tid from ticket_milestones tm left join tickets t
      on (tm.tid = t.tid)
      where tm.mid = ? and (t.ptid is null or t.ptid = '') order by pri_ord",
      $me->mid)
      ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tickets as $row) {
      $t = MTrackAPI::invoke('GET', "/ticket/$row[tid]");
      $obj = $t->result;
      $result[] = $obj;
    }
    return $result;

  }

  static function rest_milestone_list($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');

    $list = self::enumMilestones($uri == '/milestones/all');
    $result = array();
    foreach ($list as $mid => $name) {
      $m = MTrackAPI::invoke('GET', "/milestone/$mid");
      $result[] = $m->result;
    }
    return $result;
  }

  static function resolve_milestone_link(MTrackLink $link) {
    // FIXME: CSS only works if this is enclosed in a span; fix CSS and all places that generate milestone links
    if (preg_match("/^\d+$/", $link->target)) {
      $ms = MTrackMilestone::loadById($link->target);
    } else {
      $ms = MTrackMilestone::loadByName($link->target);
    }
    if (!$link->label) {
      $link->label = htmlspecialchars(urldecode($ms->name), ENT_QUOTES, 'utf-8');
    }
    $link->url = $GLOBALS['ABSWEB'] . "milestone.php/" . urlencode($ms->name);
    $link->class = 'milestone';

    if ($ms->deleted || $ms->completed) {
      $link->class .= ' completed';
    }
  }
}

MTrackSearchDB::register_indexer('milestone',
  array('MTrackMilestone', 'index_item'));

MTrackWiki::register_macro('MilestoneSummary',
  array('MTrackMilestone', 'macro_MilestoneSummary'));

MTrackWiki::register_macro('BurnDown',
  array('MTrackMilestone', 'macro_BurnDown'));

MTrackACL::registerAncestry('milestone', 'Roadmap');
MTrackWatch::registerEventTypes('milestone', array(
  'ticket' => 'Tickets',
  'changeset' => 'Code changes'
));

MTrackAPI::register('/milestones/all', 'MTrackMilestone::rest_milestone_list');
MTrackAPI::register('/milestones', 'MTrackMilestone::rest_milestone_list');
MTrackAPI::register('/milestone/:mid', 'MTrackMilestone::rest_milestone');
MTrackAPI::register('/milestone/:mid/tickets', 'MTrackMilestone::rest_tickets');
MTrackAPI::register('/milestone/:mid/prioritize',
  'MTrackMilestone::rest_prioritize');
MTrackAPI::register('/milestone/:mid/time/remaining',
  'MTrackMilestone::rest_time_remaining');
MTrackLink::register('milestone', 'MTrackMilestone::resolve_milestone_link');

