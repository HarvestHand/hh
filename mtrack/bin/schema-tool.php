<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('UTC');
}
ini_set('memory_limit', -1);

include_once dirname(__FILE__) . '/../inc/common.php';

$dsn = 'sqlite::memory:';
#$dsn = 'pgsql:dbname=wez';

$dsn = MTrackConfig::get('core', 'dsn');
if (!$dsn) {
  $dsn = 'sqlite:' . MTrackConfig::get('core', 'dblocation');
}

if (preg_match("/^sqlite:(.*)$/", $dsn, $M)) {
  $dbfile = $M[1];
  if (file_exists($dbfile)) {
    $bak = $dbfile . '.' . uniqid();
    echo "Backing up $dbfile as $bak\n";
    copy($dbfile, $bak);
  }
}

$db = new PDO($dsn);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
$adapter_class = "MTrackDBSchema_$driver";
$adapter = new $adapter_class;

$adapter->setDB($db);
$vers = $adapter->determineVersion();
echo "Version: ";
var_dump($vers);

$db->beginTransaction();
MTrackDB::$db = $db;

$schemata = array();
$latest = null;
$files = glob(dirname(__FILE__) . '/../schema/*.xml');
natsort($files);
foreach ($files as $filename) {
  $latest = new MTrackDBSchema($filename);
  $schemata[$latest->version] = $latest;
}

$install_version = getenv("INCUB_INSTALL_SCHEMA_VERSION");
if (strlen($install_version)) {
  $install_version = (int)$install_version;
  $latest = $schemata[$install_version];
}
$test_upgrade_path = (int)getenv("INCUB_INSTALL_SCHEMA_TEST_UPGRADE");

if ($vers === null) {

  if (!$test_upgrade_path) {
    // Fresh install
    echo "Applying schema version $latest->version\n";

    foreach ($latest->tables as $t) {
      $adapter->createTable($t);
    }
    if (isset($latest->post[$driver])) {
      $db->exec($latest->post[$driver]);
    }

    $vers = $latest->version;

  } else {
    // while developing, make it go through the whole migration
    $initial = $schemata[0];
    echo "Applying schema version $initial->version\n";

    foreach ($initial->tables as $t) {
      $adapter->createTable($t);
    }
    $vers = 0;
  }
}

while ($vers < $latest->version) {
  $current = $schemata[$vers];
  $next = $schemata[$vers+1];

  echo "Applying migration from schema version $current->version to $next->version\n";

  $migration = dirname(__FILE__) . "/../schema/$next->version-pre.php";
  if (file_exists($migration)) {
    echo "Running migration script schema/$next->version-pre.php\n";
    include $migration;
  }

  /* create any new tables */
  foreach ($next->tables as $t) {
    if (isset($current->tables[$t->name])) continue;
    /* doesn't yet exist, so create it! */
    $adapter->createTable($t);
  }

  /* modify existing tables */
  foreach ($current->tables as $t) {
    if (!isset($next->tables[$t->name])) continue;

    $nt = $next->tables[$t->name];
    /* compare; have they changed? */
    if (!$t->sameAs($nt)) {
      $adapter->alterTable($t, $nt);
    }
  }

  /* delete dead tables */
  foreach ($current->tables as $t) {
    if (isset($next->tables[$t->name])) continue;
    $adapter->dropTable($t);
  }

  $vers++;

  if (isset($next->post[$driver])) {
    $db->exec($next->post[$driver]);
  }

  $migration = dirname(__FILE__) . "/../schema/$vers.php";
  if (file_exists($migration)) {
    echo "Running migration script schema/$vers.php\n";
    include $migration;
  }
}

$db->exec('delete from mtrack_schema');
$q = $db->prepare('insert into mtrack_schema (version) values (?)');
$q->execute(array($latest->version));
$db->commit();

mtrack_cache_blow_all();


