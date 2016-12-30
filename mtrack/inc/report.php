<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackReport {
  public $rid = null;
  public $summary = null;
  public $description = null;
  public $query = null;
  public $changed = null;

  static function loadByID($id) {
    return new MTrackReport($id);
  }

  static function loadBySummary($summary) {
    list($row) = MTrackDB::q('select rid from reports where summary = ?',
      $summary)->fetchAll();
    if (isset($row[0])) {
      return new MTrackReport($row[0]);
    }
    return null;
  }

  function __construct($id = null) {
    $this->rid = $id;
    if ($this->rid) {
      $q = MTrackDB::q('select * from reports where rid = ?', $this->rid);
      foreach ($q->fetchAll() as $row) {
        $this->summary = $row['summary'];
        $this->description = $row['description'];
        $this->query = $row['query'];
        $this->changed = (int)$row['changed'];
        return;
      }
      throw new Exception("report $id not found");
    }
  }

  function save(MTrackChangeset $changeset) {
    if ($this->rid) {

      /* figure what we actually changed */
      $q = MTrackDB::q('select * from reports where rid = ?', $this->rid);
      list($row) = $q->fetchAll();

      $changeset->add("report:" . $this->rid . ":summary",
        $row['summary'], $this->summary);
      $changeset->add("report:" . $this->rid . ":description",
        $row['description'], $this->description);
      $changeset->add("report:" . $this->rid . ":query",
        $row['query'], $this->query);

      $q = MTrackDB::q('update reports set summary = ?, description = ?, query = ?, changed = ? where rid = ?',
            $this->summary, $this->description, $this->query,
            $changeset->cid, $this->rid);
    } else {
      $q = MTrackDB::q('insert into reports (summary, description, query, changed) values (?, ?, ?, ?)',
            $this->summary, $this->description, $this->query,
            $changeset->cid);
      $this->rid = MTrackDB::lastInsertId('reports', 'rid');
      $changeset->add("report:" . $this->rid . ":summary",
        null, $this->summary);
      $changeset->add("report:" . $this->rid . ":description",
        null, $this->description);
      $changeset->add("report:" . $this->rid . ":query",
        null, $this->query);

    }
  }

  static $reportFormats = array(
    'html' => array(
        'downloadable' => false,
        'render' => 'MTrackReport::renderReportHTML'
      ),
    'tab' => array(
        'downloadable' => 'Tab-delimited Text',
        'render' => 'MTrackReport::renderReportTab',
        'mimetype' => 'text/plain',
      ),
    'csv' => array(
        'downloadable' => 'CSV',
        'render' => 'MTrackReport::renderReportCSV',
        'mimetype' => 'text/csv',
      ),
    );

  static function emitReportDownloadHeaders($name, $format) {
    header("Content-Disposition: attachment;filename=\"$name.$format\"");
    if (isset(self::$reportFormats[$format]['mimetype'])) {
      $mime = self::$reportFormats[$format]['mimetype'];
      header("Content-Type: $mime;charset=UTF-8");
    } else {
      header("Content-Type: text/plain;charset=UTF-8");
    }
  }

  /* runs the report and returns the raw rowset */
  static function executeReportQuery($repstring, $passed_params = null) {
    $db = MTrackDB::get();

    /* process the report string; any $PARAM in there is recognized
     * as a parameter and the query munged accordingly to pass in the data */

    $params = array();
    $n = preg_match_all("/\\$([A-Z]+)/m", $repstring, $matches);
    for ($i = 0; $i < $n; $i++) {
      $pname = $matches[1][$i];
      /* default the parameter to no value */
      $params[$pname] = '';
      /* replace with query placeholder */
      $repstring = str_replace('$' . $pname, ':' . $pname,
        $repstring);
    }

    /* now to summon parameters */
    if (isset($params['USER'])) {
      $params['USER'] = MTrackAuth::whoami();
    }
    foreach ($params as $p => $v) {
      if (isset($_GET[$p])) {
        $params[$p] = $_GET[$p];
      }
    }
    if (is_array($passed_params)) {
      foreach ($params as $p => $v) {
        if (isset($passed_params[$p])) {
          $params[$p] = $passed_params[$p];
        }
      }
    }

    $q = $db->prepare($repstring);
    $q->execute($params);

    return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  static function renderReport($repstring, $passed_params = null,
      $format = 'html') {
    global $ABSWEB;

    try {
      $results = self::executeReportQuery($repstring, $passed_params);
    } catch (Exception $e) {
      return "<div class='error'>" . $e->getMessage() . "<br>" .
        htmlentities($repstring, ENT_QUOTES, 'utf-8') . "</div>";
    }

    if (count($results) == 0) {
      return "No records matched";
    }

    $CF = MTrackTicket_CustomFields::getInstance();
    /* figure out the table headings */
    $captions = array();
    foreach ($results[0] as $name => $value) {
      if (preg_match("/^__.*__$/", $name)) {
        if ($format == 'html') {
          /* special meaning, not a column */
          continue;
        }
      }
      $caption = preg_replace("/^_(.*)_$/", "\\1", $name);
      if (!strncmp($caption, "x_", 2)) {
        $CFI = $CF->fieldByName($caption);
        if ($CFI) {
          $caption = $CFI->label;
        }
      }
      $captions[$name] = $caption;
    }

    $render = self::$reportFormats[$format]['render'];
    if (is_string($render) &&
          preg_match("/^(.*)::(.*)$/", $render, $M)) {
      $render = array($M[1], $M[2]);
    }
    if (!is_callable($render)) {
      return "Cannot render reports in " .
        htmlentities($format, ENT_QUOTES, 'utf-8');
    }
    return call_user_func($render, $captions, $results);
  }

  static function renderReportCSV($captions, $results) {
    $out = '';

    $t = fopen("php://temp", 'r+');
    $c = array();
    foreach ($captions as $name => $caption) {
      $caption = ucfirst($caption);
      if ($name[0] == '_' && substr($name,-1) == '_') {
        $c[] = $caption;
      } elseif ($name[0] == '_') {
        $c[] = substr($caption, 1);
      } else {
        $c[] = $caption;
      }
    }
    fputcsv($t, $c);

    foreach ($results as $nrow => $row) {
      $c = array();
      foreach ($captions as $name => $caption) {
        $c[] = trim(preg_replace("/[\t\n\r]+/sm", " ", $row[$name]));
      }
      fputcsv($t, $c);
    }
    fseek($t, 0);
    return stream_get_contents($t);
  }

  static function renderReportTab($captions, $results) {
    $out = '';

    foreach ($captions as $name => $caption) {
      $caption = ucfirst($caption);
      if ($name[0] == '_' && substr($name,-1) == '_') {
        $out .= "$caption\t";
      } elseif ($name[0] == '_') {
        $out .= substr($caption, 1) . "\t";
      } else {
        $out .= "$caption\t";
      }
    }
    $out .= "\n";

    foreach ($results as $nrow => $row) {
      foreach ($captions as $name => $caption) {
        $v = trim(preg_replace("/[\t\n\r]+/sm", " ", $row[$name]));
        $out .= "$v\t";
      }
      $out .= "\n";
    }
    $out = str_replace("\t\n", "\n", $out);

    return $out;
  }

  static function renderReportHTML($captions, $results) {
    global $ABSWEB;
    $out = '';

    /* for spanning purposes, calculate the longest row */
    $max_width = 0;
    $width = 0;
    foreach ($captions as $name => $caption) {
      if ($name[0] == '_' && substr($name, -1) == '_') {
        $width = 1;
      } else {
        $width++;
      }
      if ($width > $max_width) {
        $max_width = $width;
      }
      if (substr($name, -1) == '_') {
        $width = 1;
      }
    }

    $group = null;
    foreach ($results as $nrow => $row) {
      $starting_new_group = false;

      if ($nrow == 0) {
        $starting_new_group = true;
      } else if (
          (isset($row['__group__']) && $group !== $row['__group__'])) {
        $starting_new_group = true;
      }

      if ($starting_new_group) {
        /* starting a new group */
        if ($nrow) {
          /* close the old one */
          $out .= "</tbody></table>\n";
        }
        if (isset($row['__group__'])) {
          $out .= "<h2 class='reportgroup'>" .
            htmlentities($row['__group__'], ENT_COMPAT, 'utf-8') .
            "</h2>\n";
          $group = $row['__group__'];
        }

        $out .= "<table class='report'><thead><tr>";

        foreach ($captions as $name => $caption) {

          /* figure out sort info for javascript bits */
          $sort = null;
          switch (strtolower($caption)) {
            case 'priority':
            case 'ticket':
            case 'severity':
            case 'ord':
              $sort = strtolower($caption);
              break;
            case 'created':
            case 'modified':
            case 'date':
            case 'due':
              $sort = 'mtrackdate';
              break;
            case 'remaining':
            case 'estimated':
              $sort = 'digit';
              break;
            case 'is_child':
              continue 2;
            case 'updated':
            case 'time':
            case 'content':
            case 'summary':
            default:
              break;
          }

          $caption = ucfirst($caption);
          if ($name[0] == '_' && substr($name,-1) == '_') {
            $out .= "</tr><tr><th colspan='$max_width'>$caption</th></tr><tr>";
          } elseif ($name[0] == '_') {
            continue;
          } else {
            $out .= "<th";
            if ($sort !== null) {
              $out .= " class=\"{sorter: '$sort'}\"";
            }
            $out .= ">$caption</th>";
            if (substr($name, -1) == '_') {
              $out .= "</tr><tr>";
            }
          }
        }
        $out .= "</tr></thead><tbody>\n";
      }

      /* and now the column data itself */
      if (isset($row['__style__'])) {
        $style = " style=\"$row[__style__]\"";
      } else {
        $style = "";
      }
      $class = $nrow % 2 ? "even" : "odd";
      if (isset($row['__color__'])) {
        $class .= " color$row[__color__]";
      }
      if (isset($row['__status__'])) {
        $class .= " status$row[__status__]";
      }
      if (isset($row['is_child']) && (int)$row['is_child']) {
        $class .= " is_child";
      }

      $begin_row = "<tr class=\"$class\"$style>";
      $out .= $begin_row;
      $href = null;

      /* determine if we should link to something for this row */
      if (isset($row['ticket'])) {
        $href = $ABSWEB . "ticket.php/$row[ticket]";
      }

      foreach ($captions as $name => $caption) {
        $v = $row[$name];

        /* apply special formatting rules */
        switch (strtolower($caption)) {
          case 'created':
          case 'modified':
          case 'date':
          case 'due':
          case 'updated':
          case 'time':
            if ($v !== null) {
              $v = mtrack_date($v);
            }
            break;
          case 'content':
            $v = MTrackWiki::format_to_html($v);
            break;
          case 'owner':
            $v = mtrack_username($v, array('no_image' => true));
            break;
          case 'is_child':
            continue 2;
          case 'docid':
          case 'ticket':
            if (isset($row['is_child']) && (int)$row['is_child']) {
              $caption .= " is_child";
            }
            $v = mtrack_ticket($row);
            break;
          case 'summary':
            if ($href) {
              $v = htmlentities($v, ENT_QUOTES, 'utf-8');
              $v = "<a href=\"$href\">$v</a>";
            } else {
              $v = htmlentities($v, ENT_QUOTES, 'utf-8');
            }
            break;
          case 'milestone':
            $oldv = $v;
            $v = '';
            foreach (preg_split("/\s*,\s*/", $oldv) as $m) {
              if (!strlen($m)) continue;
              $v .= "<span class='milestone'>" .
                    "<a href=\"{$ABSWEB}milestone.php/" .
                    urlencode($m) . "\">" .
                    htmlentities($m, ENT_QUOTES, 'utf-8') .
                    "</a></span> ";
            }
            break;
          case 'keyword':
            $oldv = $v;
            $v = '';
            foreach (preg_split("/\s*,\s*/", $oldv) as $m) {
              if (!strlen($m)) continue;
              $v .= mtrack_keyword($m) . ' ';
            }
            break;
          default:
            $v = htmlentities($v, ENT_QUOTES, 'utf-8');
        }

        if ($name[0] == '_' && substr($name, -1) == '_') {
          $out .= "</tr>$begin_row<td class='$caption' colspan='$max_width'>$v</td></tr>$begin_row";
        } elseif ($name[0] == '_') {
          continue;
        } else {
          $out .= "<td class='$caption'>$v</td>";
          if (substr($name, -1) == '_') {
            $out .= "</tr>$begin_row";
          }
        }
      }
      $out .= "</tr>\n";
    }
    $out .= "</tbody></table>";

    return $out;
  }

  /** Run a saved report and render it as HTML */
  static function macro_RunReport($name, $url_style_params = null) {
    $params = array();
    parse_str($url_style_params, $params);
    $rep = self::loadBySummary($name);
    if ($rep) {
      if (MTrackACL::hasAllRights("report:" . $rep->rid, 'read')) {
        return $rep->renderReport($rep->query, $params);
      } else {
        return "Not authorized to run report $name";
      }
    } else {
      return "Unable to find report $name";
    }
  }

  static function parseQuery()
  {
    $macro_params = array(
      'group' => true,
      'col' => true,
      'order' => true,
      'desc' => true,
      'format' => true,
      'compact' => true,
      'count' => true,
      'max' => true
    );

    $mparams = array(
      'col' => array('ticket', 'summary', 'state',
                'priority',
                'owner', 'type', 'component',
                'remaining'),
      'desc' => array('0'),
    );
    $params = array();
    $have_milestone = false;

    $args = func_get_args();
    foreach ($args as $arg) {
      if ($arg === null) continue;
      $p = explode('&', $arg);

      foreach ($p as $a) {
        $a = urldecode($a);
        preg_match('/^([a-zA-Z_]+)(!?(?:=|~=|\^=|\$=))(.*)$/', $a, $M);

        $k = $M[1];
        $op = $M[2];
        $pat = explode('|', $M[3]);

        if (isset($macro_params[$k])) {
          $mparams[$k] = $pat;
        } else if (isset($params[$k])) {
          if ($params[$k][0] == $op) {
            // compatible operator; add $pat to possible set
            $params[$k][1] = array_merge($pat, $params[$k][1]);
          } else {
            // ignore
          }
        } else {
          if ($k == 'milestone') {
            $have_milestone = true;
          }
          $params[$k] = array($op, $pat);
        }
      }
    }
    if (!isset($mparams['order'])) {
      /* if they specified a milestone, we can use the established
       * stack rank order from the planning screen, else the priority
       * property */
      if ($have_milestone) {
        $mparams['order'] = array('pri_ord', 'pri.value');
        $col = $mparams['col'];
        if (!in_array('pri_ord', $col)) {
          array_unshift($col, 'pri_ord');
          $mparams['col'] = $col;
        }
      } else {
        $mparams['order'] = array('pri.value');
      }
    }
    if (!count($params)) {
      $me = MTrackAuth::whoami();
      $params['status'] = array('!=', array('closed'));
      if ($me != 'anonymous') {
        $params['owner'] = array('=', array($me));
      }
    }
    return array($params, $mparams);
  }

  /** Run a ticket query and render it as HTML */
  static function macro_TicketQuery()
  {
    $args = func_get_args();
    $sql = call_user_func_array(array('MTrackReport', 'TicketQueryToSQL'),
      $args);
#    return htmlentities($sql) . "<br>" . self::renderReport($sql);
#    return var_export($sql, true);

    return self::renderReport($sql);
  }

  static function TicketQueryToSQL()
  {
    $args = func_get_args();
    list($params, $mparams) = call_user_func_array(array(
      'MTrackReport', 'parseQuery'), $args);
    return self::composeParsedQuery($params, $mparams);
  }

  static function composeParsedQuery($params, $mparams)
  {
    /* compose that info into a query */
    $sql = 'select t.ptid is not null as is_child, ';

    $colmap = array(
      'ticket' => '(case when t.nsident is null then t.tid else t.nsident end) as ticket',
      'component' => '(select mtrack_group_concat(name) from ticket_components
            tcm left join components c on (tcm.compid = c.compid)
            where tcm.tid = t.tid) as component',
      'keyword' => '(select mtrack_group_concat(keyword) from ticket_keywords
            tk left join keywords k on (tk.kid = k.kid)
            where tk.tid = t.tid) as keyword',
      'type' => 'classification as type',
      'remaining' =>
      // This is much more complex than I'd like it to be :-/
      // This logic MUST be equivalent to that of MTrackIssue::getRemaining
      // Logic is: if we have any non-zero effort entries, we sum them to
      // get the remaining time, otherwise we use the estimated value.
      // Except when the ticket is closed: show 0 then.
            <<<SQL
(
  case when
    t.status = 'closed' then
      0
  else (
    select
      greatest(
        round(
            cast(t.estimated as numeric) +
            cast(coalesce(sum(remaining), 0) as numeric
        ), 2),
        0
      )
    from effort where effort.tid = t.tid and remaining != 0
  )
  end

) as remaining
SQL
      ,
      'state' => "(case when t.status = 'closed' then coalesce(t.resolution, 'closed') else t.status end) as state",
      'milestone' => '(select mtrack_group_concat(name) from ticket_milestones
            tmm left join milestones tmmm on (tmm.mid = tmmm.mid)
            where tmm.tid = t.tid) as milestone',
      'depends' => '(select mtrack_group_concat(ot.nsident) from
            ticket_deps tdep left join tickets ot on (tdep.depends_on =
            ot.tid) where tdep.tid = t.tid) as depends',
      'blocks' => '(select mtrack_group_concat(ot.nsident) from
            ticket_deps tdep left join tickets ot on (tdep.tid =
            ot.tid) where tdep.depends_on = t.tid) as blocks',
      'pri_ord' => 'pri_ord as ord',
    );

    $cols = array(
     ' pri.value as __color__ ',
     ' (case when t.nsident is null then t.tid else t.nsident end) as ticket ',
     " t.status as __status__ ",
    );

    foreach ($mparams['col'] as $colname) {
      if ($colname == 'ticket') {
        continue;
      }
      if ($colname == 'pri_ord' && !isset($params['milestone'])) {
        continue;
      }
      if (isset($colmap[$colname])) {
        $cols[$colname] = $colmap[$colname];
      } else {
        if (!preg_match("/^[a-zA-Z_]+$/", $colname)) {
          throw new Exception("column name $colname is invalid");
        }
        $cols[$colname] = $colname;
      }
    }

    $sql .= join(', ', $cols);

    if (!isset($params['milestone'])) {
      $sql .= <<<SQL

FROM
tickets t
left join priorities pri on (t.priority = pri.priorityname)
left join severities sev on (t.severity = sev.sevname)
WHERE
 1 = 1

SQL;
    } else {
      $sql .= <<<SQL

FROM milestones m
left join ticket_milestones tm on (m.mid = tm.mid)
left join tickets t on (tm.tid = t.tid)
left join priorities pri on (t.priority = pri.priorityname)
left join severities sev on (t.severity = sev.sevname)
WHERE
 1 = 1

SQL;
    }

    $critmap = array(
      'milestone' => 'm.name',
      'tid' => 't.nsident',
      'id' => 't.nsident',
      'ticket' => 't.nsident',
      'type' => 't.classification',
    );

    foreach ($params as $k => $v) {
      list($op, $values) = $v;

      if (isset($critmap[$k])) {
        $k = $critmap[$k];
      }

      $sql .= " AND ";

      if ($op[0] == '!') {
        $sql .= " NOT ";
        $op = substr($op, 1);
      }
      $sql .= "(";

      if ($op == '=') {
        /* Allow "100,200" to pick out 100 and 200.
         * Allow "100-110" to pick out the range 100-110 inclusive.
         * Allow "100-110,200" to pick out the range 100-110 and 200
         *
         * Made more interesting by namespace prefixes and text/integer
         * conversions in the database (postgres is more pedantic than
         * sqlite!), so we handle the range expansion
         * in the query that we generate and build a set that we query
         * using the "IN" clause
         */
        if (count($values) == 1 && $k == 't.nsident' &&
              preg_match('/[,-]/', $values[0])) {
          $crit = array();
          foreach (explode(',', $values[0]) as $range) {
            list($rfrom, $rto) = explode('-', $range, 2);

            if (!$rto) {
              $crit[] = " $k = " . MTrackDB::esc($rfrom) . " ";
              continue;
            }

            $critset = array();
            /* if it's a range, we look for the numeric portion of it
             * (recall that it may have a prefix and be something like
             * "mc123" */
            $rfromint = (int)$rfrom;
            if (preg_match("/(\d+)/", $rfrom, $M)) {
              $rfromint = (int)$M[1];
            }
            $rtoint = (int)$rto;
            /* note that if the the namespace prefixes don't match between
             * the from and the to, you'll get undefined behavior; we use
             * the rfrom value as the template for the range that we generate.
             */
            if (preg_match("/(\d+)/", $rto, $M)) {
              $rtoint = (int)$M[1];
            }
            for ($i = $rfromint; $i <= $rtoint; $i++) {
              $critset[] = MTrackDB::esc(preg_replace("/(\d+)/", $i, $rfrom));
            }
            $crit[] = "$k in (" . join($critset, ",") . ")";
          }
          $sql .= join(' OR ', $crit);
        } else if (count($values) == 1) {
          $sql .= " $k = " . MTrackDB::esc($values[0]) . " ";
        } else {

          $sql .= " $k in (";
          foreach ($values as $i => $val) {
            $values[$i] = MTrackDB::esc($val);
          }
          $sql .= join(', ', $values) . ") ";
        }
      } else {
        /* variations on like */
        if ($op == '~=') {
          $start = '%';
          $end = '%';
        } else if ($op == '^=') {
          $start = '';
          $end = '%';
        } else {
          $start = '%';
          $end = '';
        }

        $crit = array();

        foreach ($values as $val) {
          $crit[] = "($k LIKE " . MTrackDB::esc("$start$val$end") . ")";
        }
        $sql .= join(" OR ", $crit);
      }

      $sql .= ") ";

    }
    if (isset($mparams['group'])) {
      $g = $mparams['group'][0];
      if (!ctype_alpha($g)) {
        throw new Exception("group $g is not alpha");
      }
      $sql .= ' GROUP BY ' . $g;
    }

    if (isset($mparams['order'])) {
      $k = $mparams['order'][0];
      if ($k == 'tid') {
        $k = 't.tid';
      }

      $sql .= ' ORDER BY ' . $k;
      if (isset($mparams['desc']) && $mparams['desc'][0]) {
        $sql .= ' DESC';
      }
    }

    if (isset($mparams['max'])) {
      $sql .= ' LIMIT ' . (int)$mparams['max'][0];
    }
    return $sql;
  }

  static function resolve_report_link(MTrackLink $link)
  {
    $link->url = $GLOBALS['ABSWEB'] . 'report.php/' .
      $link->target;
  }

  static function resolve_query_link(MTrackLink $link)
  {
    $link->url = $GLOBALS['ABSWEB'] . 'query.php?' .
      $link->target;
  }
};

MTrackWiki::register_macro('RunReport',
  array('MTrackReport', 'macro_RunReport'));

MTrackWiki::register_macro('TicketQuery',
  array('MTrackReport', 'macro_TicketQuery'));

MTrackACL::registerAncestry('report', 'Reports');
MTrackLink::register('query', 'MTrackReport::resolve_query_link');
MTrackLink::register('report', 'MTrackReport::resolve_report_link');

