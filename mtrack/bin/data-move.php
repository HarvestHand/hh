<?php # vim:ts=2:sw=2:et:

/* For licensing and copyright terms, see the file named LICENSE */

if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('UTC');
}
ini_set('memory_limit', -1);

include_once dirname(__FILE__) . '/../inc/common.php';

if (count($argv) != 2) {
  echo "Usage: bin/data-move.php 'pgsql:dbname=foo;user=bar'\n";
  echo <<<TXT
Reads your existing mtrack database (uses DSN information in config.ini).
Connects to the specified DSN and creates the mtrack schema, then populates
it from your existing mtrack database.

TXT;

  exit(1);
}
/* destination DSN */
$ddsn = $argv[1];

$sdsn = MTrackConfig::get('core', 'dsn');
if (!$sdsn) {
  $sdsn = 'sqlite:' . MTrackConfig::get('core', 'dblocation');
}
$sdb = new PDO($sdsn);
$sdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ddb = new PDO($ddsn);
$ddb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ddb->exec("set client_encoding='utf-8'");

$driver = $ddb->getAttribute(PDO::ATTR_DRIVER_NAME);
$adapter_class = "MTrackDBSchema_$driver";
$adapter = new $adapter_class;

$adapter->setDB($ddb);
$vers = $adapter->determineVersion();
echo "Version: ";
var_dump($vers);

$ddb->beginTransaction();

$schemata = array();
$latest = null;
foreach (glob(dirname(__FILE__) . '/../schema/*.xml') as $filename) {
  $latest = new MTrackDBSchema($filename);
  $schemata[$latest->version] = $latest;
}

echo "Applying schema version $latest->version\n";
foreach ($latest->tables as $t) {
  $adapter->createTable($t);

  $names = array();

  foreach ($t->fields as $f) {
    if ($f->type == 'autoinc') {
      // Omit: we want the database to set this for us, otherwise
      // sequence numbers won't get populated!
      continue;
    }
    $names[] = $f->name;
  }
  $pull = 'select ' . join(',', $names) . ' from ' . $t->name;

  $push = 'insert into ' . $t->name . '(' . join(',', $names) . ') values (' .
    str_repeat('?,', count($names) - 1) . '?)';

  $sq = $sdb->query($pull, PDO::FETCH_NUM);

  $dq = $ddb->prepare($push);

  foreach ($sq as $row) {
    /* postgres has stronger data validation requirements;
    * fixup the data */
    $send = array();
    foreach ($names as $i => $fname) {
      $f = $t->fields[$fname];
      switch ($f->type) {
        case 'integer':
        case 'autoinc':
          if ($row[$i] == '') {
            if (isset($f->nullable) && $f->nullable == '0') {
              $row[$i] = 0;
            } else {
              $row[$i] = null;
            }
          }
          $dq->bindValue(1+$i, $row[$i]);
          break;
        case 'real':
          if ($row[$i] == '') {
            if (isset($f->nullable) && $f->nullable == '0') {
              $dq->bindValue(1+$i, 0.0);
            } else {
              $dq->bindValue(1+$i, null);
            }
          } else {
            /* avoid converting to double here, for sake of precision.
             * Also, somehow we have commas in our data... fix that */
            $dq->bindValue(1+$i, str_replace(",", ".", $row[$i]));
          }
          break;
        case 'blob':
          if (is_null($row[$i])) {
            $dq->bindValue(1+$i, null);
          } else {
            $stm = fopen('php://memory', 'r+');
            fwrite($stm, $row[$i]);
            rewind($stm);
            $dq->bindValue(1+$i, $stm, PDO::PARAM_LOB);
          }
          break;
        case 'text':
        default:
          /* CSV import could have injected non-UTF-8 data */
          if (is_null($row[$i])) {
            $dq->bindValue(1+$i, null);
          } else {
            $enc = mb_detect_encoding($row[$i], 'UTF-8,ISO-8859-1');
            if ($enc != 'UTF-8') {
              $dq->bindValue(1+$i,
                mb_convert_encoding($row[$i], 'UTF-8', $enc));
            } else {
              $dq->bindValue(1+$i, $row[$i]);
            }
          }
      }
    }
    try {
      $dq->execute();
    } catch (Exception $e) {
      echo "$push\n";
      var_dump($names);
      var_dump($row);
      var_dump($send);
      foreach ($send as $d) {
        echo bin2hex($d) . "\n";
      }
      throw $e;
    }
  }
}
if (isset($latest->post[$driver])) {
  $ddb->exec($latest->post[$driver]);
}
$vers = $latest->version;




$ddb->exec('delete from mtrack_schema');
$q = $ddb->prepare('insert into mtrack_schema (version) values (?)');
$q->execute(array($latest->version));
$ddb->commit();




