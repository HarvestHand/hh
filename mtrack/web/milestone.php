<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';
$pi = urldecode(mtrack_get_pathinfo());

function parse_date_string($str)
{
  if (!strlen($str)) {
    return null;
  }
  return MTrackDB::unixtime(strtotime($str));
}

if ($_GET['new'] == 1 || $_GET['edit'] == 1) {
  $error = null;

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['cancel'])) {
      header("Location: {$ABSWEB}roadmap.php");
      exit;
    }

    if ($_GET['new'] == 1) {
      MTrackACL::requireAllRights("Roadmap", 'create');
      $ms = new MTrackMilestone;
    } else {
      MTrackACL::requireAllRights("Roadmap", 'modify');
      $ms = MTrackMilestone::loadById($_POST['mid']);
    }

    if (strlen($_POST['name'])) {
      $ms->name = trim($_POST['name']);
      $ms->description = $_POST['desc'];

      $pmid = (int)$_POST['pmid'];
      if ($pmid > 0) {
        $pm = MTrackMilestone::loadById($pmid);
        if (!$pm) {
          $error = "There is no milestone with a parent of $pmid";
        } else {
          $ms->pmid = $pmid;
        }
      } else {
        $ms->pmid = null;
      }

      $ms->duedate = parse_date_string($_POST['duedate']);
      $ms->startdate = parse_date_string($_POST['startdate']);

      $compdate = parse_date_string($_POST['compdate']);
      if ($ms->completed === null && $compdate !== null) {
        $description = "$ms->name completed";
      } else {
        $description = $ms->description;
      }
      $ms->completed = $compdate;

      $other = MTrackMilestone::loadByName($_POST['name']);
      if ($other && ($_GET['new'] == 1 || $ms->mid != $other->mid)) {
        $error = "a milestone named \"$ms->name\" already exists";
      } else if ($error === null) {
        $CS = MTrackChangeset::begin("milestone:$ms->name", $description);
        $ms->save($CS);

        if ($pmid < 1 && $_POST['additers'] == 'on') {
          /* add children for iterations (not allowed for milestones
           * that are themselves a child of another */
          $start = strtotime($ms->startdate);
          $end = strtotime($ms->duedate);
          $days = (int)$_POST['iterduration'];

          $n = 1;
          $link = rawurlencode($ms->name);
          while ($start < $end) {
            $kid = new MTrackMilestone;
            $kid->name = $ms->name . " ($n)";
            $kid->description = "Iteration $n of [milestone:$link]";
            $kid->startdate = MTrackDB::unixtime($start);
            $due = strtotime("+$days day", $start);
            if ($due > $end) {
              $due = $end;
            }
            $kid->duedate = MTrackDB::unixtime($due);
            $kid->pmid = $ms->mid;

            $kid->save($CS);

            $start = strtotime("+1 day", $due);
            $n++;
          }
        }

        if ($ms->completed !== null && $_POST['compmilestone'] != '') {
          if ($_POST['compmilestone'] != 'close') {
            $TM = MTrackMilestone::loadById($_POST['compmilestone']);
          } else {
            $TM = null;
          }
          foreach (MTrackDB::q("select t.tid from ticket_milestones tm left join tickets t on (tm.tid = t.tid) where mid = ? and status != 'closed'", $ms->mid)->fetchAll(PDO::FETCH_COLUMN, 0) as $tid) {
            $T = MTrackIssue::loadById($tid);

            if ($TM) {
              $T->dissocMilestone($ms);
              $T->assocMilestone($TM);
              $T->addComment("$ms->name completed, moving ticket to $TM->name");
            } else {
              $T->resolution = 'fixed';
              $T->close();
              $T->addComment("$ms->name completed, closing ticket");
            }
            $T->save($CS);
          }
        }

        $CS->setObject("milestone:$ms->mid");
        $CS->commit();
        header("Location: {$ABSWEB}milestone.php/$ms->name");
        exit;
      }
    }
    var_export($_POST);
  } else if (strlen($pi)) {
    MTrackACL::requireAllRights("Roadmap", 'modify');
    $ms = MTrackMilestone::loadByName($pi);
  } else {
    MTrackACL::requireAllRights("Roadmap", 'create');
    $ms = new MTrackMilestone;
  }
  mtrack_head($_GET['new'] == 1 ? "New Milestone" : "Edit Milestone");

  if ($error) {
    $error = htmlentities($error, ENT_QUOTES, 'utf-8');
    echo <<<HTML
<div class='ui-state-error ui-corner-all'>
    <span class='ui-icon ui-icon-alert'></span> $error
</div>

HTML;
  }

  $name = htmlentities($ms->name, ENT_COMPAT, 'utf-8');
  $desc = htmlentities($ms->description, ENT_COMPAT, 'utf-8');

  if ($ms->duedate) {
    $duedate = date('m/d/y', strtotime($ms->duedate));
  } else {
    $duedate = '';
  }
  if ($ms->startdate) {
    $startdate = date('m/d/y', strtotime($ms->startdate));
  } else {
    $startdate = '';
  }

  if ($ms->completed != null) {
    $compdate = date('m/d/y', strtotime($ms->completed));
  } else {
    $compdate = null;
  }

  if ($_GET['new'] == 1) {
    echo "<h1>New Milestone</h1>";
    $save = 'Add';
  } else {
    echo "<h1>Edit Milestone</h1>";
    $save = 'Save';
  }

  echo <<<HTML
<form method='post' class='milestoneedit'>
<input type='hidden' name='mid' value='{$ms->mid}'>
<div class='field'>
  <label>Name of the milestone:</label><br>
  <input type='text' id='name' name='name' size='32' value="$name">
</div>
HTML;

  $kids = MTrackDB::q('select name from milestones where pmid = ? and deleted != 1', $ms->mid)->fetchAll(PDO::FETCH_COLUMN, 0);
  if (count($kids)) {

    echo <<<HTML
<div class='field'>
  <label>Children:</label> <em>Effort expended against the following milestones is also counted towards the burndown of this milestone</em><br>
HTML;

    foreach ($kids as $name) {
      echo "<span class='milestone'><a href='{$ABSWEB}milestone.php/$name'>$name</a></span><br>\n";
    }

    echo "</div>\n";

  } else {

    $parents = array();
    foreach (MTrackDB::q('select mid, name from milestones where
        pmid is null and ((deleted != 1 and mid != ? and completed is null)
        or (mid = ?))
        order by name',
        $ms->mid, $ms->pmid)->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $parents[$row['mid']] = $row['name'];
    }
    $parents[''] = '(none)';
    $parent = mtrack_select_box('pmid', $parents, $ms->pmid);


    echo <<<HTML
<div class='field'>
  <label>Parent:</label> <em>Effort expended against a milestone is also counted towards the burndown of its parent</em><br>
  $parent
</div>
HTML;
  }

  $open_milestones = MTrackMilestone::enumMilestones();
  $open_milestones[''] = ' - none: Leave ticket associated with milestone';
  $open_milestones['close'] = ' - none: close open tickets';

  $compmilestone = mtrack_select_box('compmilestone', $open_milestones);

  echo <<<HTML
<fieldset>
  <legend>Schedule</legend>
  <div class='field'>
    <label>Start:<br>
      <input type='text' id='startdate' name='startdate' size='0'
        value='$startdate' class='dateinput'>
      <em>Format: MM/DD/YY</em>
    </label>
  </div>
  <div class='field'>
    <label>Due:<br>
      <input type='text' id='duedate' name='duedate' size='0'
        value='$duedate' class='dateinput'>
      <em>Format: MM/DD/YY</em>
    </label>
  </div>
  <br>
  <div class='field'>
    <label>
      Completed:<br>
      <input type='text' id='compdate' name='compdate'
        size='0' value='$compdate' class='dateinput'>
      <em>Format: MM/DD/YY</em>
    </label><br>
    <em>Re-target open tickets to milestone:</em> $compmilestone
  </div>
HTML;

  if (count($kids) == 0 && !$ms->pmid) {
    echo <<<HTML
  <br>
  <div class='field'>
    <label>
      <input type='checkbox' id='additers' name='additers'>
      Add child milestones for iteration tracking<br>
      <em>Iteration duration of
      <input type='text' id='iterduration' name='iterduration'
        size='3' value='7'>
      days</em>
    </label>
  </div>
HTML;
  }

  echo <<<HTML
</fieldset>
<div class='field'>
  <fieldset>
    <label for='desc'>Description</label><br/>
    <em>By default, the milestone summary will display a burndown chart
      as though you had added <tt>[[BurnDown(milestone=name,width=50%,height=150)]]</tt> into the description field below.<br>
      If you wish to change the size and position of the chart, explicitly
      enter the burndown macro in the description field.<br>
      To turn off the burndown for this milestone, enter <tt>[[BurnDown()]]</tt> in the description field.
    </em>
    <textarea id='desc' name='desc' class='code wiki' rows='10' cols='78'>$desc</textarea>
  </fieldset>
</div>
<div class='modal-footer'>
  <button class='btn' type='submit' name='cancel'>Cancel</button>
  <button class='btn btn-primary' id="savemilestone" type='submit' name='save'>$save Milestone</button>
</div>
</form>
<script type='text/javascript'>
$(document).ready(function() {
  $('#name').focus();
  $('.dateinput').datepicker({
    // minDate: 0,
    dateFormat: 'mm/dd/yy'
  });
});
</script>
HTML;
} else if (strlen($pi)) {

  mtrack_head($pi);

  echo MTrackMilestone::macro_MilestoneSummary($pi);

  $kids = MTrackDB::q('select name from milestones where pmid =
    (select mid from milestones where name = ?) and deleted != 1 order by (case when duedate is null then 1 else 0 end), duedate, name', $pi)
    ->fetchAll(PDO::FETCH_ASSOC);
  if (count($kids)) {
    echo "<h2>Related milestones:</h2>";
    foreach ($kids as $row) {
      echo MTrackMilestone::macro_MilestoneSummary($row['name']);
    }
  }

}  else {
  throw new Exception("no such milestone $pi");
}

mtrack_foot();
