<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

MTrackACL::requireAllRights('Roadmap', 'read');

mtrack_head("Roadmap");

$completed = isset($_GET['completed']) ? 'checked' : '';
$watched = isset($_GET['watched']) ? 'checked' : '';

echo <<<HTML
<h1>Roadmap</h1>
<div id="roadmapfilter">
<form method="get">
  <input type="checkbox" id='completed' name="completed" $completed>
  <label for='completed'>Show completed milestones</label><br/>
  <input type="checkbox" id='watched' name="watched" $watched>
  <label for='watched'>Show only watched milestones</label><br/>
  <button class='btn' type="submit" name='s'><i class='icon-refresh'></i> Update</button><br>
</form>
<button class='btn' id="addmilestone" onclick="document.location.href='{$ABSWEB}milestone.php?new=1';return false;"><i class='icon-plus'></i> Add Milestone</button>
<script type='text/javascript'>
var showingGraphs = true;
function toggleGraphs()
{
  if (showingGraphs) {
    $('.burndown').hide();
  } else {
    $('.burndown').show();
  }
  showingGraphs = !showingGraphs;
}
</script>
<button class='btn' onclick="toggleGraphs(); return false;">Toggle Graphs</button>
</div>
HTML;
$db = MTrackDB::get();

if (!empty($_GET['completed'])) {
  $comp = "";
} else {
  $comp = " AND completed IS NULL ";
}

if ($watched == 'checked') {
  $me = $db->quote(mtrack_canon_username(MTrackAuth::whoami()));
  if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
    $oid = 'w.oid::integer';
  } else {
    $oid = 'w.oid';
  }
  $sql = <<<SQL
SELECT distinct name, duedate
FROM watches w
LEFT JOIN milestones m on (m.mid = $oid)
  WHERE
    w.userid = $me AND
    w.otype = 'milestone' AND
    deleted != 1
    AND pmid IS NULL
    $comp
ORDER by duedate ASC, name
SQL;

} else {
  $sql = <<<SQL
SELECT name
FROM milestones
  WHERE 
    deleted != 1
    AND pmid IS NULL
  $comp 
ORDER by case when duedate IS NULL then 1 else 0 end, duedate ASC, name
SQL;
}

$i = 0;
foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
  echo MTrackMilestone::macro_MilestoneSummary($row['name']);
  $i++;
}

if ($i == 0) {
  $milestones = $watched == 'checked' ? 'watched milestones' : 'milestones';
  echo <<<HTML
<p><em>No $milestones were found.</em></p>
HTML;
}

mtrack_foot();

