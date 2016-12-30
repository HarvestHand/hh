<?php # vim:ts=2:sw=2:et:

$root = getenv('INCUB_ROOT');
require $root . '/inc/Test/init.php';

/* Testing our schema upgrade bits */

/* first find out how many schema versions we have */
$files = glob("$root/schema/*.xml");

/* we're testing installing at each possible version,
 * then separately upgrading that to the latest version.
 * However, our code needs schema version 2 and higher.
 */
$min_schema = 2;
plan((count($files) - $min_schema) * 2);

function run_init_and_upgrade($version)
{
  global $root;

  diag("testing with schema version $version");

  putenv("INCUB_INSTALL_SCHEMA_VERSION=$version");

  $vardir = "$root/build/var-up";
  if (is_dir($vardir)) {
    shell_exec("rm -rf $vardir");
  }
  mkdir($vardir);
  putenv("MTRACK_CONFIG_FILE=$vardir/config.ini");
  if (getenv('INCUB_DSN') && getenv('INCUB_PG_PORT')) {
    // we're using postgres. Fake up a distinct database name
    $dsn = getenv('INCUB_DSN');
    $dbname = "mtrack$version";
    $dsn = preg_replace("/dbname=mtrack/", "dbname=$dbname", $dsn);
    $dsn = " --dsn '$dsn'";
    $port = getenv('INCUB_PG_PORT');
    system("createdb -p $port -E=UTF-8 $dbname");
  } else {
    $dsn = '';
  }

  $res = shell_exec(
    "$root/bin/setup $dsn --vardir $vardir --config-file $vardir/config.ini");

  if (!unlike($res, "/error|exception/i", "installation successful")) {
    diag($res);
  }

  putenv("INCUB_INSTALL_SCHEMA_VERSION=");
  $res = shell_exec("php $root/bin/schema-tool.php");
  if (!unlike($res, "/error|exception/i", "upgrade successful")) {
    diag($res);
  }
  shell_exec("rm -rf $vardir");
}

for ($i = $min_schema; $i < count($files); $i++) {
  run_init_and_upgrade($i);
}

