<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

if (!file_exists("bin/init.php")) {
  echo "You must run me from the top-level mtrack dir\n";
  exit(1);
}

/* People doing this are not necessarily sane, make sure we have PDO and
 * pdo_sqlite */
if (!extension_loaded('PDO') || !extension_loaded('pdo_sqlite')) {
  echo "Mtrack requires PDO and pdo_sqlite to function\n";
  exit(1);
}

$projects = array();
$projects_prefix = array();
$repos = array();
$tracs = array();
$links = array();
$config_file_name = 'config.ini';

$args = array();
array_shift($argv);
while (count($argv)) {
  $arg = array_shift($argv);

  if ($arg == '--config-file') {
    if (count($argv) < 1) {
      usage("Missing argument to --config-file");
    }
    $config_file_name = array_shift($argv);
    continue;
  }
  if ($arg == '--trac') {
    if (count($argv) < 3) {
      usage("Missing arguments to --trac");
    }
    $pname = array_shift($argv);
    $tracdb = array_shift($argv);

    $tracVersion = array_shift($argv);
    if (!in_array($tracVersion, array('0.11', '0.12')))
      usage("Trac version unknown: ".$tracVersion);

    if (!file_exists($tracdb)) {
      usage("Tracdb path must be a sqlite database");
    }
    if(substr($pname,0,1) === "+") {
      $pname = substr($pname,1);
      $projects_prefix[$pname] = false;
    }
    else {
      $projects_prefix[$pname] = true;
    }
    $tracs[$tracdb] = array('projectName' => $pname, 'version' => $tracVersion);
    $projects[$pname] = $pname;
    continue;
  }
  if ($arg == '--repo') {
    if (count($argv) < 3) {
      usage("Missing arguments to --repo");
    }
    $rname = array_shift($argv);
    $rtype = array_shift($argv);
    $rpath = array_shift($argv);

    switch ($rtype) {
      case 'hg':
        if (!is_dir("$rpath/.hg")) {
          usage("Repo path must be an hg repo dir");
        }
        break;
      case 'svn':
        if (!file_exists("$rpath/format")) {
          usage("Repo path must be a svn repo");
        }
        break;
      default:
        usage("Invalid repo type $rtype");
    }

    $repos[$rname] = array($rname, $rtype, $rpath);
    continue;
  }

  if ($arg == '--link') {
    if (count($argv) < 3) {
      usage("Missing arguments to --link");
    }
    $pname = array_shift($argv);
    $rname = array_shift($argv);
    $rloc = array_shift($argv);

    $links[] = array($pname, $rname, $rloc);
    $projects[$pname] = $pname;
    continue;
  }

  $args[] = $arg;
}

if (count($args)) {
  usage("Unhandled arguments");
}

putenv("MTRACK_CONFIG_FILE=" . $config_file_name);

require_once 'inc/common.php';
include 'bin/import-trac.php';

MTrackSearchDB::setBatchMode();

$db = MTrackDB::get();
MTrackChangeset::$use_txn = false;
$db->beginTransaction();

$CS = MTrackChangeset::begin('~modify~', 'setup modified');

foreach ($projects as $pname) {
  $p = MTrackProject::loadByName($pname);
  if ($p === null) {
    $p = new MTrackProject;
    $p->shortname = $pname;
    $p->name = $pname;
    $p->save($CS);
  }
  $projects[$pname] = $p;
}

foreach ($repos as $repo) {
  $r = new MTrackRepo;
  $r->shortname = $repo[0];
  $r->scmtype = $repo[1];
  $r->repopath = $repo[2];

  foreach ($links as $link) {
    list($pname, $rname, $loc) = $link;
    if ($rname == $r->shortname) {
      $p = $projects[$pname];
      $r->addLink($p, $loc);
    }
  }

  $r->save($CS);
  $repos[$r->shortname] = $r;
}

$CS->commit();

$i = 0;
foreach ($tracs as $tracdb => $tracInfo) {
  import_from_trac($projects[$tracInfo['projectName']], $tracdb, $tracInfo['version'], $projects_prefix[$pname],
                   isset($_ENV['TRAC_AVOID_COMMITS']));
}
echo "Updating ACL tree\n"; flush();
echo "Committing\n"; flush();
$db->commit();
MTrackSearchDB::commit(TRUE);
echo "Done\n";

function usage($msg = '')
{
  require_once 'inc/common.php';
  echo $msg, <<<TXT

Usage: modify
  --repo {name} {type} {repopath}
                           define a repo named {name} of {type} at {repopath}
  --link {project} {repo} {location}
                           link a repo location to a project
  --trac {project} {tracenv} {tracversion}
                           import data from a trac environment at {tracenv}
                           and associate with project {project}.  If {project}
                           begins with a "+", then the "+" is excluded from
                           the project name, but the imported trac data is *not*
                           namespaced under {project}. Currently
                           only the tracversions 0.11 and 0.12 are supported.

  --config-file {filename} Where to find the configuration file.
                           defaults to config.ini in the current directory.


Supported repo types:


TXT;

  foreach (MTrackRepo::getAvailableSCMs() as $t => $r) {
    $d = $r->getSCMMetaData();
    printf("  %10s   %s\n", $t, $d['name']);
  }
  echo "\n\n\n";

  exit(1);
}
