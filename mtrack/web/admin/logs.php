<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

MTrackACL::requireAnyRights('Browser', 'modify');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['reset'])) {
    MTrackDB::q('delete from search_engine_state');
  }
  header("Location: {$ABSWEB}admin/logs.php");
  exit;
}

mtrack_head("Logs");
mtrack_admin_nav();

$vardir = MTrackConfig::get('core', 'vardir');
$filename = "$vardir/indexer.log";


echo "<h1>Indexer Log</h1>\n";
echo "<tt>$filename</tt><br>\n";
$mtime = filemtime($filename);
if ($mtime) {
  echo "Modified: " . mtrack_date("@$mtime", true) . "<br>";
}

$last = null;
foreach (MTrackDB::q('select last_run from search_engine_state')->fetchAll()
    as $row) {
  $last = $row[0];
}
if ($last === null) {
  echo "No objects have been indexed yet\n";
} else {
  echo "Last Indexed Object: " . mtrack_date($last, true) . "<br>\n";
}

if ($mtime) {
  $fp = fopen($filename, 'r');
  $lines = array();
  while (($line = fgets($fp)) !== false) {
    $lines[] = htmlentities($line, ENT_QUOTES, 'utf-8');
    if (count($lines) > 100) {
      array_shift($lines);
    }
  }
  echo "<pre>";
  foreach ($lines as $line) {
    echo $line;
  }
  echo "</pre>";
}
?>
<form method='post'>
  <button type='submit' name='reset' class='btn btn-danger'
    >Rebuild Index from scratch on next run</button>
</form>
<?php

mtrack_foot();

