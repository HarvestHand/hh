<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('UTC');
}

ini_set('memory_limit', 256*1024*1024);
$_GLOBALS['MTRACK_CONFIG_SKIP_BOOT'] = true;
include 'inc/common.php';
include 'bin/import-trac.php';

if (!file_exists("bin/init.php")) {
  echo "You must run me from the top-level mtrack dir\n";
  exit(1);
}

/* People doing this are not necessarily sane, make sure we have PDO and
 * pdo_sqlite.
 * Furthermore, poor folks on FreeBSD have to put up with a PHP that has
 * the core default components stripped out and built shared.  Help
 * them understand what they need. */
$required_extensions = array(
  'PDO', 'pdo_sqlite', 'ctype', 'Reflection', 'json', 'pcre',
  'session', 'dom', 'SimpleXML',
);
$pre_req_not_met = false;
foreach ($required_extensions as $ext) {
  if (!extension_loaded($ext)) {
    echo "The '$ext' extension is required\n";
    $pre_req_not_met = true;
  }
}
if ($pre_req_not_met) {
  exit(1);
}

$projects = array();
$repos = array();
$tracs = array();
$links = array();
$passwords = array();
$config_file_name = 'config.ini';
$vardir = 'var';
$aliasfile = null;
$authorfile = null;
$wiki_repo_type = null;
$DSN = null;

$SCMS = MTrackRepo::getAvailableSCMs();

$args = array();
array_shift($argv);
while (count($argv)) {
  $arg = array_shift($argv);

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
    $tracs[$tracdb] = array('projectName' => $pname, 'version' => $tracVersion);
    $projects[$pname] = $pname;
    continue;
  }

  if ($arg == '--author-alias') {
    if (count($argv) < 1) {
      usage("Missing argument to --author-alias");
    }
    $aliasfile = array_shift($argv);
    continue;
  }
  if ($arg == '--author-info') {
    if (count($argv) < 1) {
      usage("Missing argument to --author-info");
    }
    $authorfile = array_shift($argv);
    continue;
  }
  if ($arg == '--http-user-pass' || $arg == '--user-pass') {
    if (count($argv) < 2) {
      usage("Missing arguments to $arg");
    }
    $passwords[array_shift($argv)] = array_shift($argv);
    continue;
  }

  if ($arg == '--wiki-type') {
    if (count($argv) < 1) {
      usage("Missing argument to --wiki-type");
    }
    $wiki_repo_type = array_shift($argv);
    if (!isset($SCMS[$wiki_repo_type])) {
      usage("Invalid repo type $wiki_repo_type");
    }
    continue;
  }

  if ($arg == '--repo') {
    if (count($argv) < 3) {
      usage("Missing arguments to --repo");
    }
    $rname = array_shift($argv);
    $rtype = array_shift($argv);
    $rpath = realpath(array_shift($argv));

    if (!isset($SCMS[$rtype])) {
      usage("Invalid repo type $rtype");
    }

    switch ($rtype) {
      case 'hg':
        if (!is_dir("$rpath/.hg")) {
          usage("Repo path must be a local hg repo dir");
        }
        break;
      case 'git':
        if (!is_dir("$rpath/.git")) {
          usage("Repo path must be a local git repo dir");
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

  if ($arg == '--vardir') {
    if (count($argv) < 1) {
      usage("Missing argument to --vardir");
    }
    $vardir = array_shift($argv);
    continue;
  }

  if ($arg == '--config-file') {
    if (count($argv) < 1) {
      usage("Missing argument to --config-file");
    }
    $config_file_name = array_shift($argv);
    continue;
  }

  if ($arg == '--dsn') {
    if (count($argv) < 1) {
      usage("Missing argument to --dsn");
    }
    $DSN = array_shift($argv);
    continue;
  }

  $args[] = $arg;
}

if (count($args)) {
  usage("Unhandled arguments: ".implode(", ", $args));
}

if (file_exists("$vardir/mtrac.db") || file_exists($config_file_name)) {
  echo "Already configured.  To re-install, remove $vardir/mtrac.db and $config_file_name\n";
  exit(1);
}


echo "Setting up mtrack with:\n";

echo "Projects:\n  ";
echo join("\n  ", $projects);
echo "\n\n";

echo "Repos:\n";
foreach ($repos as $repo) {
  echo "  " . join(" ", $repo) . "\n";
  foreach ($links as $link) {
    if ($link[1] == $repo[0]) {
      echo "    $link[2] -> $link[0]\n";
    }
  }
}
echo "\n";

if (count($tracs)) {
  foreach ($tracs as $tname => $tracInfo) {
    echo "Import trac $tname ({$tracInfo['version']}) -> {$tracInfo['projectName']}\n";
  }
}

function usage($msg = '')
{
  echo $msg, <<<TXT


Usage: init

  --wiki-type              specify the repo type to use when initializing wiki
                           Supported repo types are listed below.
                           To use a pre-existing wiki, don't use this option,
                           use --repo wiki instead.

  --repo {name} {type} {repopath}
                           define a repo named {name} of {type} at {repopath}
  --link {project} {repo} {location}
                           link a repo location to a project
  --trac {project} {tracenv} {tracversion}
                           import data from a trac environment at {tracenv}
                           and associate with project {project}. Currently
                           only the tracversions 0.11 and 0.12 are supported.

  --vardir {dir}           where to store database and search engine state.
                           Defaults to "var" in the current directory; will
                           be created if it does not exist.

  --config-file {filename} Where to create the configuration file.
                           defaults to config.ini in the current directory.

  --author-alias {filename}
                           where to find an authors file that maps usernames.
                           This is used to initialize the canonicalizations
                           used by the system.  The format is a file of the
                           form: sourcename=canonicalname
                           The import will replace all instances of sourcename
                           with canonicalname in the history, and will record
                           the mapping so that future items will respect it.

  --author-info {filename}
                           Where to find a file that will be used to initialize
                           the userinfo table. The format is:
                           canonid,fullname,email,active,timezone
                           where canonid is the canonical username.

  --dsn {dsn}
                           If specified, should be a compatible PDO DSN
                           locating the database to store the mtrack state.
                           If you want to use sqlite, simply omit this
                           parameter.  If you want to use PostgreSQL, then
                           you should enter a string like:
                           pgsql:host=dbhostname

                           mtrack only supports SQLite and PostgreSQL in
                           this version.

  --user-pass {user} {pw}  Create a user record for {user} and set their
                           password to {pw}.

Supported repo types:


TXT;

  foreach (MTrackRepo::getAvailableSCMs() as $t => $r) {
    $d = $r->getSCMMetaData();
    printf("  %10s   %s\n", $t, $d['name']);
  }
  echo "\n\n\n";

  exit(1);
}

if (!is_dir($vardir)) {
  mkdir($vardir);
  chmod($vardir, 02777);
}
if (!is_dir("$vardir/attach")) {
  mkdir("$vardir/attach");
  chmod("$vardir/attach", 02777);
}

function check_working_tool(&$tools, $toolname, $path)
{
  if ($toolname == 'hg') {
    // Check for working mercurial version
    $ver = shell_exec(escapeshellarg($path) . " version");
    if (preg_match("/\(version ([^)]+)\)/", $ver, $M)) {
      $version = $M[1];
    } else {
      echo "Could not determine mercurial version number:\n$path\n$ver\n";
      echo "I need at least version 1.5.2\n";
      return false;
    }
    if (version_compare($version, "1.5.2", "<")) {
      echo "Mimimum supported mercurial is version 1.5.2, you have $version\n";
      return false;
    }
  }


  $tools[$toolname] = $path;
  return true;
}

putenv("MTRACK_CONFIG_FILE=" . $config_file_name);
if (!file_exists($config_file_name)) {
  /* create a new config file */
  $CFG = file_get_contents("config.ini.sample");
  $CFG = str_replace("@VARDIR@", realpath($vardir), $CFG);
  if (count($projects)) {
    list($pname) = array_keys($projects);
  } else {
    $pname = "mtrack demo";
  }
  $CFG = str_replace("@PROJECT@", $pname, $CFG);
  if ($DSN == null) {
    $DSN = "sqlite:@{core:dblocation}";
  }
  $CFG = str_replace("@DSN@", "\"$DSN\"", $CFG);

  $tools_to_find = array('diff', 'diff3', 'php', 'svn', 'hg',
    'git', 'svnserve', 'svnlook', 'svnadmin');
  foreach ($SCMS as $S) {
    $m = $S->getSCMMetaData();
    if (isset($m['tools'])) {
      foreach ($m['tools'] as $t) {
        $tools_to_find[] = $t;
      }
    }
  }

  /* find reasonable defaults for tools */
  $tools = array();
  foreach ($tools_to_find as $toolname) {
    $found = false;
    foreach (explode(PATH_SEPARATOR, getenv('PATH')) as $pdir) {
      if (DIRECTORY_SEPARATOR == '\\' &&
          file_exists($pdir . DIRECTORY_SEPARATOR . $toolname . '.exe')) {
        $found = true;
        if (check_working_tool($tools, $toolname,
            $pdir . DIRECTORY_SEPARATOR . $toolname . '.exe')) {
          break;
        }
      } else if (file_exists($pdir . DIRECTORY_SEPARATOR . $toolname)) {
        $found = true;
        if (check_working_tool($tools, $toolname,
            $pdir . DIRECTORY_SEPARATOR . $toolname)) {
          break;
        }
      }
    }
    if (!$found && !isset($tools[$toolname])) {
      // let the system find it in the path at runtime, but only
      // if we didn't find it above and decide that it was broken
      $tools[$toolname] = $toolname;
    }
  }
  $toolscfg = '';
  foreach ($tools as $toolname => $toolpath) {
    $toolscfg .= "$toolname = \"$toolpath\"\n";
  }
  $CFG = str_replace("@TOOLS@", $toolscfg, $CFG);
  file_put_contents($config_file_name, $CFG);
}
unset($_GLOBALS['MTRACK_CONFIG_SKIP_BOOT']);
MTrackConfig::$ini = null;
MTrackDB::$db = null;
MTrackTicket_CustomFields::$me = null;
MTrackConfig::boot();

include dirname(__FILE__) . '/schema-tool.php';

if (file_exists("$vardir/mtrac.db")) {
  chmod("$vardir/mtrac.db", 0666);
}

$db = MTrackDB::get();

# if the config has custom fields, or the runtime config from an earlier
# installation does, let's update the schema, if needed.
MTrackTicket_CustomFields::getInstance()->save();

MTrackChangeset::$use_txn = false;
$db->beginTransaction();

$CANON_USERS = array();
if ($aliasfile) {
  foreach (file($aliasfile) as $line) {
    if (preg_match('/^\s*([^=]+)\s*=\s*(.*)\s*$/', $line, $M)) {
      if (!strlen($M[1])) {
        continue;
      }
      $CANON_USERS[$M[1]] = $M[2];
    }
  }
}

foreach ($CANON_USERS as $src => $dest) {
  MTrackDB::q('insert into useraliases (alias, userid) values (?, ?)',
    $src, strlen($dest) ? $dest : null);
}

if ($authorfile) {
  foreach (file($authorfile) as $line) {
    $author = explode(',', trim($line));
    if (strlen($author[0])) {
      MTrackDB::q('insert into userinfo (
        userid, fullname, email, active, timezone) values
        (?, ?, ?, ?, ?)',
        $author[0],
        $author[1],
        $author[2],
        ((int)$author[3]) ? 1 : 0,
        $author[4]);
    }
  }
}

/* set up initial ACL tree structure */
$rootobjects = array(
  'Reports', 'Browser', 'Wiki', 'Timeline', 'Roadmap', 'Tickets',
  'Enumerations', 'Components', 'Projects', 'User', 'Snippets',
);

foreach ($rootobjects as $rootobj) {
  MTrackACL::addRootObjectAndRoles($rootobj);
}

# Add forking permissions
$ents = MTrackACL::getACL('Browser', false);
$ents[] = array('BrowserCreator', 'fork', true);
$ents[] = array('BrowserForker', 'fork', true);
$ents[] = array('BrowserForker', 'read', true);
MTrackACL::setACL('Browser', false, $ents);

$CS = MTrackChangeset::begin('~setup~', 'initial setup');

if (count($passwords)) {
  foreach ($passwords as $user => $pass) {
    $U = new MTrackUser;
    $U->userid = $user;
    $U->fullname = $user;
    $U->save($CS);
    $U->setPassword($pass);
    MTrackConfig::set('user_classes', $user, 'admin');
  }
}
// Make our auth the default; folks with custom requirements
// can deploy their own config.ini that removes this line
MTrackConfig::set('plugins', 'MTrackAuth_MTrack', '');
MTrackConfig::save();

foreach ($projects as $pname) {
  $p = new MTrackProject;
  $p->shortname = $pname;
  $p->name = $pname;
  $p->save($CS);
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

if (!isset($repos['wiki'])) {
  // Set up the wiki repo (if they don't already have one named wiki)

  if ($wiki_repo_type === null) {
    $wiki_repo_type = MTrackConfig::get('tools', 'hg');
    if (file_exists($wiki_repo_type)) {
      $wiki_repo_type = 'hg';
    } else {
      $wiki_repo_type = 'svn';
    }
  }

  $r = new MTrackRepo;
  $r->shortname = 'wiki';
  $r->scmtype = $wiki_repo_type;
  $r->repopath = realpath($vardir) . DIRECTORY_SEPARATOR . 'repos/default/wiki';
  $r->description = 'The mtrack wiki pages are stored here';
  echo " ** Creating repo 'wiki' of type $r->scmtype to hold Wiki content at $r->repopath\n";
  echo " ** (use --repo option to specify an alternate location)\n";
  echo " ** (use --wiki-type option to specify an alternate type)\n";
  $r->save($CS);
  $repos['wiki'] = $r;

  $r->reconcileRepoSettings();
}

touch("$vardir/.initializing");
foreach (glob("defaults/wiki/*") as $filename) {
  $name = basename($filename);
  echo "wiki: $name\n";

  $w = MTrackWikiItem::loadByPageName($name);
  if ($name == 'WikiStart' && $w !== null) {
    /* skip existing WikiStart, as it may have been customized */
    continue;
  }
  if ($w === null) {
    $w = new MTrackWikiItem($name);
  }

  $w->content = file_get_contents($filename);
  $w->save($CS);
}
MTrackWikiItem::commitNow();

foreach (glob("defaults/reports/*") as $filename) {
  $name = basename($filename);
  echo "report: $name\n";

  $rep = new MTrackReport;
  $rep->summary = $name;

  list($sql, $wiki) = explode("\n\n", file_get_contents($filename), 2);

  $rep->description = $wiki;
  $rep->query = $sql;
  $rep->save($CS);
}
if (count($tracs) == 0) {
  // Default enumerations
  foreach (array('defect', 'enhancement', 'task') as $v => $c) {
    $cl = new MTrackClassification;
    $cl->name = $c;
    $cl->value = $v;
    $cl->save($CS);
  }
  foreach (array('fixed', 'invalid', 'wontfix', 'duplicate', 'worksforme')
      as $v => $c) {
    $cl = new MTrackResolution;
    $cl->name = $c;
    $cl->value = $v;
    $cl->save($CS);
  }
  foreach (array('blocker', 'critical', 'major', 'normal', 'minor', 'trivial')
      as $v => $c) {
    $cl = new MTrackSeverity;
    $cl->name = $c;
    $cl->value = 1 + $v;
    $cl->save($CS);
  }
  foreach (array('highest', 'high', 'normal', 'low', 'lowest')
      as $v => $c) {
    $cl = new MTrackPriority;
    $cl->name = $c;
    $cl->value = 1 + $v;
    $cl->save($CS);
  }
  foreach (array('new', 'open', 'closed', 'reopened')
      as $v => $c) {
    $cl = new MTrackTicketState;
    $cl->name = $c;
    $cl->value = $v;
    $cl->save($CS);
  }
}
$CS->commit();

$i = 0;
foreach ($tracs as $tracdb => $tracInfo) {
  import_from_trac($projects[$tracInfo['projectName']], $tracdb, $tracInfo['version'], $i++);
}
echo "Committing\n"; flush();
$db->commit();
echo "Done\n";
unlink("$vardir/.initializing");

echo <<<TEXT

 ** Important! Make sure that you chown and/or chmod $vardir so that it is
 ** writable to your web server user


TEXT;

