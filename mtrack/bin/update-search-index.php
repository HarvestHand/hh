<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('UTC');
}

include dirname(__FILE__) . '/../inc/common.php';
MTrackSearchDB::setBatchMode();

$vardir = MTrackConfig::get('core', 'vardir');

/* only allow one instance to run concurrently */
$fp = fopen("$vardir/.indexer.lock", 'w');
if (!$fp) {
  exit(1);
}
if (!flock($fp, LOCK_EX|LOCK_NB)) {
  echo "Another instance is already running\n";
  exit(1);
}
/* "leak" $fp, so that the lock is held while we continue to run */

/* log to a file in the var dir */
function log_output($buffer)
{
  global $log_file;
  fwrite($log_file, $buffer);
  fflush($log_file);
}
$log_file = fopen("$vardir/indexer.log", 'w');
if ($log_file) {
  ob_start('log_output');
}
function log_flush() {
  flush();
  ob_flush();
  flush();
}

$start_time = time();
echo "Indexing started at " . date('c') . "\n";
log_flush();

$last = '1990-01-01T00:00:00';
$ALL = true;
foreach (MTrackDB::q('select last_run from search_engine_state')->fetchAll()
    as $row) {
  $last = $row[0];
  $ALL = false;
}
$LATEST = strtotime($last);
$FIRST = $LATEST;
$ITEMS = 0;
$DONE = array();

function index_and_measure($object)
{
  global $DONE;
  if (isset($DONE[$object])) {
    return true;
  }
  $DONE[$object] = true;

  echo "Examine: $object\n";
  log_flush();
  $start = time();
  $res = MTrackSearchDB::index_object($object);
  $elapsed = time() - $start;
  printf("Indexed $object in %f seconds\n", $elapsed);
  log_flush();
  return $res;
}

function index_items($lower)
{
  global $LATEST;
  global $ITEMS;
  global $start_time;
  global $DONE;
  global $FIRST;

  /* do the work here */

  foreach (MTrackDB::q('select object, max(changedate) from changes where changedate > ? group by object order by max(changedate)', $lower)->fetchAll(PDO::FETCH_NUM)
      as $row) {

    if ($LATEST > ($FIRST + 3) && time() - $start_time > 280) {
      // Step back 1 second on the next run, otherwise we may miss out
      // a couple of items from the current second
      $LATEST--;
      break;
    }

    list($object, $when) = $row;

    if (true) {
      $ITEMS++;
      $res = index_and_measure($object);
    } else {
      $res = true;
    }
    if ($res === false) {
      echo "Don't know how to index $object\n";
    } else {
      echo "Processed $object $when > $lower\n";
    }
    $t = strtotime($when);
    if ($t > $LATEST) {
      $LATEST = $t;
    }
  }
}

if ($ALL) {
  // walk all the wiki pages, in case someone checked in against the
  // wiki repo outside of the app
  $repo = null;
  $root = MTrackWikiItem::getRepoAndRoot($repo);
  $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
  function walk_wiki($repo, $dir, $suf)
  {
    global $DONE;

    $items = $repo->readdir($dir);
    foreach ($items as $file) {
      if ($file->is_dir) {
        walk_wiki($repo, $file->name, $suf);
      } else {
        if (!strlen($suf) || substr($file->name, -strlen($suf)) == $suf) {
          //echo "Going to index wiki:$file->name\n";
          $object = "wiki:$file->name";
          index_and_measure($object);
        } else {
          //echo "NO: wiki:$file->name\n";
        }
      }
    }
  }
  walk_wiki($repo, $root, $suf);
}

index_items($last);

$db = MTrackDB::get();
$db->beginTransaction();
$db->exec("delete from search_engine_state");
$insert = $db->prepare("insert into search_engine_state (last_run) values (?)");
$insert->execute(array(MTrackDB::unixtime($LATEST)));
$db->commit();

if ($ITEMS > 0) {
  MTrackSearchDB::commit();
}

$end_time = time();
$elapsed = $end_time - $start_time;
echo "$ITEMS items processed (in $elapsed seconds)\n";

