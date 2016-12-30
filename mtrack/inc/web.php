<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* Simplistic pathinfo parsing - could optionally have additional features such
  as validation added */
function mtrack_parse_pathinfo($vars) {
  $pi = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
  $data = explode('/', $pi);
  $i = 0;
  $return_vars = array();
  array_shift($data);
  foreach($vars as $name => $value) {
    if (isset($data[$i])) {
      $return_vars[$name] = $data[$i];
      $i++;
    } else {
      $return_vars[$name] = $value;
    }
  }
  return $return_vars;
}

/**
 * Pathinfo retrieval minus starting slash
 * @param bool $no_strip Set to TRUE if you want to keep the prepended slash
 * @return null|string
 */
function mtrack_get_pathinfo($no_strip = false) {
  $pi = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : "";

  if (!empty($pi) && $no_strip == false) {
    $pi = substr($pi, 1);
  }

  // If the value is empty (by default or by stripping off the slash) replace it with null.
  if (empty($pi)) {
    $pi = null;
  }

  return $pi;
}

function mtrack_calc_root()
{
  /* ABSWEB: the absolute URL to the base of the web app */
  global $ABSWEB;

  /* if they have one, use the weburl config value for this */
  $ABSWEB = MTrackConfig::get('core', 'weburl');
  if (strlen($ABSWEB)) {
    return;
  }

  /* otherwise, determine the root of the app.
   * This is complicated because the DOCUMENT_ROOT may refer to an area that
   * is completely unrelated to the actual root of the web application, for
   * instance, in the case that the user has a public_html dir where they
   * are running mtrack */

  /* determine the root of the app */
  $sdir = dirname($_SERVER['SCRIPT_FILENAME']);
  $idir = dirname(dirname(__FILE__)) . '/web';
  $diff = substr($sdir, strlen($idir)+1);
  $rel = preg_replace('@[^/]+@', '..', $diff);
  if (strlen($rel)) {
    $rel .= '/';
  }
  /* $rel is now the relative path to the root of the web app, from the current
   * page */

  if (isset($_SERVER['HTTP_HOST'])) {
    $ABSWEB = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ?
              'https' : 'http') . '://' .  $_SERVER['HTTP_HOST'];
  } else {
    $ABSWEB = 'http://localhost';
  }

  $bits = explode('/', $rel);
  $base = $_SERVER['SCRIPT_NAME'];
  foreach ($bits as $b) {
    $base = dirname($base);
  }
  if ($base == '/') {
    $ABSWEB .= '/';
  } else {
    $ABSWEB .= $base . '/';
  }
}
mtrack_calc_root();

function mtrack_head($title, $navbar = true)
{
  global $ABSWEB;
  static $mtrack_did_head;

  $whoami = mtrack_username(MTrackAuth::whoami(),
    array(
      'no_image' => true
    )
  );

  if ($mtrack_did_head) {
    return;
  }
  $mtrack_did_head = true;

  $projectname = htmlentities(MTrackConfig::get('core', 'projectname'),
    ENT_QUOTES, 'utf-8');
  $logo = MTrackConfig::get('core', 'projectlogo');
  if (strlen($logo)) {
    $projectname = "<img alt='$projectname' src='$logo'>";
  }
  $fav = MTrackConfig::get('core', 'favicon');
  if (strlen($fav)) {
    $fav = <<<HTML
<link rel="icon" href="$fav" type="image/x-icon" />
<link rel="shortcut icon" href="$fav" type="image/x-icon" />
HTML;
  } else {
    $fav = '';
  }

  $title = htmlentities($title, ENT_QUOTES, 'utf-8');

  $userinfo = $whoami;
  MTrackNavigation::augmentUserInfo($userinfo);

  echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=8">
<title>$title</title>
$fav
<link rel="stylesheet" href="${ABSWEB}css.php?2" type="text/css" />
<script language="javascript" type="text/javascript" src="${ABSWEB}js.php?3"></script>
</head>
<body>
HTML;

  if ($navbar) {
    echo <<<HTML
<div class='navbar navbar-fixed-top' id='mainnav'>
  <div class='navbar-inner'>
    <div class='container'>
      <a class="btn btn-navbar"
          data-toggle="collapse" data-target=".nav-collapse">
        <span class='icon-bar'></span>
        <span class='icon-bar'></span>
        <span class='icon-bar'></span>
      </a>
      <a class='brand' href='#'>$projectname</a>
      <div class='nav-collapse'>
HTML;

  $nav = array();
  if (MTrackAuth::whoami() !== 'anonymous') {
    $nav['/'] = 'Today';
  }
  $navcandidates = array(
    "/browse.php" => array("Browse", 'read', 'Browser'),
    "/wiki.php" => array("Wiki", 'read', 'Wiki'),
    "/timeline.php" => array("Timeline", 'read', 'Timeline'),
    "/roadmap.php" => array("Roadmap", 'read', 'Roadmap'),
    "/reports.php" => array("Reports", 'read', 'Reports'),
    "/ticket.php/new" => array("New Ticket", 'create', 'Tickets'),
    "/snippet.php" => array("Snippets", 'read', 'Snippets'),
    "/admin/" => array("Administration", 'modify', 'Enumerations', 'Components', 'Projects', 'Browser'),
    "/help.php" => array("Help", 'read', 'Wiki'),
  );
  foreach ($navcandidates as $url => $data) {
    $label = array_shift($data);
    $right = array_shift($data);
    $ok = false;
    foreach ($data as $object) {
      if (MTrackACL::hasAllRights($object, $right)) {
        $ok = true;
        break;
      }
    }
    if ($ok) {
      $nav[$url] = $label;
    }
  }

  echo mtrack_nav('mainnav', $nav, $userinfo);

  echo <<<HTML
    <form class='navbar-search pull-right'
        id="mainsearch" action="${ABSWEB}search.php">
      <input type="text" class="search-query" title="Search"
        name="q" accesskey="f">
    </form>
    </div>
    <div id="ajaxspin"></div>

    </div>
  </div>
</div>


HTML;

  }

  $not_configured = false;

  $party_addrs = MTrackConfig::get('core', 'admin_party_remote_address');
  if (MTrackConfig::get('core', 'admin_party') == 1 &&
      MTrackAuth::whoami() == 'adminparty' &&
      in_array($_SERVER['REMOTE_ADDR'], explode(',', $party_addrs))) {

    if ($_SERVER['SCRIPT_FILENAME'] !=
        dirname(dirname(__FILE__)) . '/web/admin/auth.php') {

      $perms = '';
      $path = MTrackConfig::getRuntimeConfigPath();
      if (!is_writable($path)) {
        $perms = "<br><p><b>$path</b> is NOT writable; please ensure that the permissions on your vardir are correct!</p>";
      }

    echo <<<HTML
<div class='alert alert-error'>
  <a class='close' data-dismiss='alert'>&times;</a>
  <h4 class='alert-heading'>Welcome to the admin party!</h4>
  <p>
  Authentication is not yet configured;
  while it is in this state, any user connecting from <b>$party_addrs</b>
  is treated as having admin rights (that includes you, and this
  is why you are seeing this message). All other users are denied
  access.</p>
  $perms
  <br>
  <p><a class='btn btn-danger' href="{$ABSWEB}admin/auth.php">Click here to Configure Authentication</a></p>
</div>
HTML;
    }
  } elseif (MTrackConfig::get('core', 'admin_party') == 1)
  {
    $localaddr = preg_replace('@^(https?://)([^/]+)/(.*)$@',
      "\${1}127.0.0.1/\\3", $ABSWEB);
    $remoteaddr = htmlentities($_SERVER['REMOTE_ADDR']);

    echo <<<HTML
<div class='alert alert-error'>
  <a class='close' data-dismiss='alert'>&times;</a>
  <h4 class='alert-heading'>Authentication is not yet configured</h4>
  If you are the admin,
  you should use the <b><a href="$localaddr">localhost address</a></b>
  to reach the system and configure it, or configure the <b>admin_party_remote_address</b> option to include <b>$remoteaddr</b>.
</div>
HTML;
    $not_configured = true;
  } elseif (!MTrackAuth::isAuthConfigured()) {
    echo <<<HTML
<div class='alert alert-error'>
  <a class='close' data-dismiss='alert'>&times;</a>
  <b>Authentication is not yet configured</b>.  If you are the admin,
  you will need to edit the config.ini or var/runtime.config file to
  configure authentication.
</div>
HTML;
    $not_configured = true;
  }

  if (preg_match("/(on|true|1)/i", ini_get('magic_quotes_gpc'))) {
    echo <<<HTML
<div class='alert alert-error'>
  <b>magic_quotes_gpc</b> is enabled.  This causes mtrack not to work.
  Disable this setting in your server configuration.
</div>
HTML;

    $not_configured = true;
  }

  echo <<<HTML
</div>
<div id="content">
HTML;

  if ($not_configured) {
    mtrack_foot();
    exit;
  }
}

function mtrack_foot($visible_markup = true, $show_footer = false)
{
  /* close the content div */
  echo <<<HTML
</div>
HTML;
  if ($visible_markup) {
    if ($show_footer) {
      echo <<<HTML
<footer id="footer">
<div class="navfoot">
  Powered by <a href="http://bitbucket.org/wez/mtrack/">mtrack</a>
</div>
</footer>
HTML;
    }

    echo <<<HTML
</body>
</html>
HTML;
    if (MTrackConfig::get('core', 'debug.footer')) {
      global $FORKS;

      echo "<!-- " . MTrackDB::$queries . " queries\n";
      var_export(MTrackDB::$query_strings);
      echo "\n\nforks\n\n";
      var_export($FORKS);
      echo "-->";
    }
  }
}

interface IMTrackExtensionPage {
  /** called to dispatch a page render */
  function dispatchRequest();
}

class MTrackExtensionPage {
  static $locations = array();
  static function registerLocation($location, IMTrackExtensionPage $page) {
    self::$locations[$location] = $page;
  }
  static function locationToURL($location) {
    global $ABSWEB;
    return $ABSWEB . 'ext.php/' . $location;
  }
  static function bindToPage($location) {
    while (strlen($location)) {
      if (isset(self::$locations[$location])) {
        return self::$locations[$location];
      }
      if (strpos($location, '/') === false) {
        return null;
      }
      $location = dirname($location);
    }
  }
}

interface IMTrackNavigationHelper {
  /** called by mtrack_nav
   * You may remove items from or add items to the items array by
   * changing the $items array.
   * Should you want to suppress the Wiki from navigation, you may
   * do so like this:
   * if ($id == 'mainnav') {
   *   unset($items['/wiki.php']);
   * }
   * If you want to add an item, the key is the URL and the value
   * is the label.  The label is raw HTML.
   */
  function augmentNavigation($id, &$items);

  /** called by mtrack_head
   * You may augment or override the "Logged in as user" text by
   * changing the $content variable */
  function augmentUserInfo(&$content);
}

class MTrackNavigation {
  static $helpers = array();

  static function registerHelper(IMTrackNavigationHelper $helper)
  {
    self::$helpers[] = $helper;
  }

  static function augmentNavigation($id, &$items)
  {
    foreach (self::$helpers as $helper) {
      $helper->augmentNavigation($id, $items);
    }
  }

  static function augmentUserInfo(&$content)
  {
    foreach (self::$helpers as $helper) {
      $helper->augmentUserInfo($content);
    }
  }
}

function mtrack_breadcrumb($path, $base_url = null)
{
  if ($path[0] != '/') {
    $path = '/' . $path;
  }
  if ($path == '/') {
    $crumbs = array('');
  } else {
    $crumbs = explode('/', $path);
  }

  $html = "<ul class='breadcrumb'>";
  $location = $base_url;
  foreach ($crumbs as $i => $ele) {
    if (!strlen($ele)) {
      $path = '<i class="icon-home"></i> ';
    } else {
      $location .= '/' . urlencode($ele);
      $path = htmlentities($ele, ENT_QUOTES, 'utf-8');
    }
    if ($i == count($crumbs) - 1) {
      $html .= "<li class='active'>";
    } else {
      $html .= "<li>";
    }
    $html .= "<a href='$location'>$path</a> ";

    if ($i < count($crumbs) - 1) {
      $html .= "<span class='divider'>/</span>";
    }
    $html .= "</li>";
  }
  $html .= "</ul>";
  return $html;
}

function mtrack_nav($id, $nav, $userinfo) {
  global $ABSWEB;

  // Allow config file to manipulate the navigation bits
  $cnav = MTrackConfig::getSection('nav:' . $id);
  if (is_array($cnav)) {
    foreach ($cnav as $loc => $label) {
      if (!strlen($label)) {
        unset($nav[$loc]);
      } else {
        $nav[$loc] = $label;
      }
    }
  }

  MTrackNavigation::augmentNavigation($id, $nav);

  $elements = array();

  $web = realpath(dirname(__FILE__) . '/../web');
  $where = substr($_SERVER['SCRIPT_FILENAME'], strlen($web));
  if (isset($_SERVER['PATH_INFO'])) {
    $where .= $_SERVER['PATH_INFO'];
  }
  $active = null;
  $tries = 0;
  do {
    foreach ($nav as $loc => $label) {
      $cloc = $loc;
      if (!strncmp($cloc, $ABSWEB, strlen($ABSWEB))) {
        $cloc = substr($cloc, strlen($ABSWEB)-1);
      }
      if ($where == $cloc || $where == rtrim($cloc, '/')) {
        $active = $loc;
        break;
      }
    }
    $where = dirname($where);
  } while ($active === null && $tries++ < 100);

  $drop = array();
  $active_in_drop = false;
  foreach ($nav as $loc => $label) {
    $class = '';
    if ($active == $loc) {
      $class = ' class="active"';
    }
    if ($loc[0] == '/') {
      $url = substr($loc, 1); // trim off leading /
    } else {
      $url = $loc;
    }
    if (!preg_match('/^[a-z-]+:/', $url)) {
      $url = $ABSWEB . $url;
    }
    if (count($elements) > 6) {
      if (strlen($class)) $active_in_drop = true;
      $drop[] = "<li><a href=\"$url\">$label</a></li>";
    } else {
      $elements[] = "<li$class><a href=\"$url\">$label</a></li>";
    }
  }
  if (count($drop)) {
    $class = $active_in_drop ? ' active' : '';
    $elements[] = "<li class='dropdown$class'>
      <a href='#' class='dropdown-toggle' data-toggle='dropdown'
      >More <b class='caret'></b></a><ul class='dropdown-menu'>" .
      join("\n", $drop) . "</ul></li>";
  }
  if ($userinfo) {
    $me = MTrackAuth::whoami();
    if (MTrackAuth::canLogOut() && $me != 'anonymous') {
      $logout = $GLOBALS['ABSWEB'] . 'logout.php';
      $elements[] = "<li class='dropdown'>
        <a href='#' class='dropdown-toggle' data-toggle='dropdown'
        >$me <b class='caret'></b></a><ul class='dropdown-menu'>
        <li>$userinfo</li>
        <li><a href='$logout'>Log Out</a></li>
        </ul></li>";
    } else {
      $elements[] = "<li>" . $userinfo . "</li>";
    }
  }
  return "<ul class='nav'>" .  implode('', $elements) . "</ul>";
}

function mtrack_date($tstring, $show_full = false)
{
  /* database time is always relative to UTC */
  $d = date_create($tstring, new DateTimeZone('UTC'));
  if (!is_object($d)) {
    throw new Exception("could not represent $tstring as a datetime object");
  }
  $iso8601 = $d->format(DateTime::W3C);
  /* but we want to render relative to user prefs */
  date_timezone_set($d, new DateTimeZone(date_default_timezone_get()));
  $full = $d->format('D, M d Y H:i');

  if (!$show_full) {
    return "<abbr title=\"$iso8601\" class='timeinterval'>$full</abbr>";
  }

  return "<abbr title='$iso8601' class='timeinterval'>$full</abbr> <span class='fulldate'>$full</span>";
}

function mtrack_mkdir_p($dir)
{
  if (is_dir($dir)) return true;
  $parent = dirname($dir);
  if (!is_dir($parent)) {
    mtrack_mkdir_p($parent);
  }
  return mkdir($dir);
}

function mtrack_rmdir($dir)
{
  $files = scandir($dir);
  if (!$files)
    return;
  foreach ($files as $ent) {
    if ($ent == '.' || $ent == '..') {
      continue;
    }
    $full = $dir . DIRECTORY_SEPARATOR . $ent;
    if (is_dir($full)) {
      mtrack_rmdir($full);
    } else {
      unlink($full);
    }
  }
  rmdir($dir);
}

function mtrack_make_temp_dir($do_make = true, $tempdir = null)
{
  if ($tempdir === null) {
    $tempdir = sys_get_temp_dir();
  }
  $base = $tempdir . DIRECTORY_SEPARATOR . "mtrack." . uniqid();
  for ($i = 0; $i < 1024; $i++) {
    $candidate = $base . sprintf("%04x", $i);
    if ($do_make) {
      if (mkdir($candidate)) {
        return $candidate;
      }
    } else {
      /* racy */
      if (!file_exists($candidate) && !is_dir($candidate)) {
        return $candidate;
      }
    }
  }
  throw new Exception("unable to make temp dir based on path $candidate.  Check permissions and ownership on vardir and ensure that it is writable to the webserver process");
}

function mtrack_diff_strings($before, $now)
{
  $tempdir = sys_get_temp_dir();
  $afile = tempnam($tempdir, "mtrack");
  $bfile = tempnam($tempdir, "mtrack");
  file_put_contents($afile, $before);
  file_put_contents($bfile, $now);
  $diff = MTrackConfig::get('tools', 'diff');
  if (PHP_OS == 'SunOS') {
     // TODO: make an option to allow use of gnu diff on solaris
    $diff = shell_exec("$diff -u $afile $bfile");
    $diff = str_replace($afile, 'before', $diff);
    $diff = str_replace($bfile, 'now', $diff);
  } else {
    $diff = shell_exec("$diff --label before --label now -u $afile $bfile");
  }
  unlink($afile);
  unlink($bfile);
  $diff = htmlentities($diff, ENT_COMPAT, 'utf-8');
  return $diff;
}

function mtrack_last_chance_saloon($e)
{
  if ($e instanceof MTrackAuthorizationException) {
    if (MTrackAuth::whoami() == 'anonymous') {
      MTrackAuth::forceAuthenticate();
    }
    mtrack_head('Insufficient Privilege');
    echo '<h1>Insufficient Privilege</h1>';
    $rights = is_array($e->rights) ? join(', ', $e->rights) : $e->rights;
    echo "You do not have the required set of rights ($rights) to access this page<br>";
    mtrack_foot();
    exit;
  }

  $msg = $e->getMessage();

  try {
    mtrack_head('Whoops: ' . $msg);
  } catch (Exception $doublefault) {
  }

  echo "<h1>An error occurred!</h1>";

  echo htmlentities($msg, ENT_QUOTES, 'utf-8');

  echo "<br>";

  echo nl2br(htmlentities($e->getTraceAsString(), ENT_QUOTES, 'utf-8'));

  try {
    mtrack_foot();
  } catch (Exception $doublefault) {
  }
}

function mtrack_canon_username($username)
{
  static $canon_map = null;

  if ($canon_map === null) {
    $canon_map = array();
    foreach (MTrackDB::q('select alias, userid from useraliases union select email, userid from userinfo where email <> \'\'')->fetchAll()
        as $row) {
      $canon_map[$row[0]] = $row[1];
    }
  }

  $runaway = 25;
  do {
    if (isset($canon_map[$username])) {
      if ($username == $canon_map[$username]) {
        break;
      }
      $username = $canon_map[$username];
    } elseif (preg_match('/<([a-z0-9_.+=-]+@[a-z0-9.-]+)>/', $username, $M)) {
      // look at just the email address
      $username = $M[1];
      if (!isset($canon_map[$username])) {
        break;
      }
    } else {
      break;
    }
  } while ($runaway-- > 0);

  return $username;
}

function mtrack_username($username, $options = array())
{
  $username = mtrack_canon_username($username);
  $userdata = MTrackAuth::getUserData($username);

  if (isset($userdata['fullname']) && strlen($userdata['fullname'])) {
    $title = " title='" .
        htmlentities($userdata['fullname'], ENT_QUOTES, 'utf-8') . "' ";
  } else {
    $title = '';
  }

  global $ABSWEB;

  if (!isset($options['size'])) {
    $options['size'] = 24;
  }
  if (isset($options['class'])) {
    $extraclass = " $options[class]";
  } else {
    $extraclass = '';
  }

  if (!ctype_alnum($username)) {
    $target = "{$ABSWEB}user.php?user=" . urlencode($username);
    if (isset($options['edit'])) {
      $target .= '&edit=1';
    }
  } else {
    $target = "{$ABSWEB}user.php/$username";
    if (isset($options['edit'])) {
      $target .= '?edit=1';
    }
  }
  $open_a = "<a $title href='$target' class='userlink$extraclass'>";

  $ret = '';
  if ((!isset($options['no_image']) || !$options['no_image'])) {
    $ret .= $open_a .
            mtrack_avatar($username, $options['size']) .
            '</a> ';
  }
  if (!isset($options['no_name']) || !$options['no_name']) {
    $dispuser = $username;

    if (strlen($dispuser) > 12) {
      if (preg_match("/^([^+]*)(\+.*)?@(.*)$/", $dispuser, $M)) {
        /* looks like an email address, try to shorten it in a reasonable way */
        $local = $M[1];
        $extra = $M[2];
        $domain = $M[3];

        if (strlen($extra)) {
          $local .= '...';
        }

        $dispuser = "$local@$domain";
      }
    }
    $ret .= "$open_a$dispuser</a>";
  }
  return $ret;
}

function mtrack_avatar($username, $size = 24)
{
  global $ABSWEB;

  $id = urlencode($username);

  return "<img class='gravatar' width='$size' height='$size' src='{$ABSWEB}avatar.php?u=$id&amp;s=$size'>";
}

function mtrack_gravatar($email, $size = 24)
{
  // d=identicon
  // d=monsterid
  // d=wavatar
  return "<img class='gravatar' width='$size' height='$size' src='http://www.gravatar.com/avatar/" .  md5(strtolower($email)) . "?s=$size&amp;d=wavatar'>";
}

function mtrack_defrepo()
{
  static $defrepo = null;
  if ($defrepo === null) {
    $defrepo = MTrackConfig::get('core', 'default.repo');
    if ($defrepo === null) {
      $defrepo = '';
      foreach (MTrackDB::q(
          'select parent, shortname from repos order by shortname')
          ->fetchAll() as $row) {
        $defrepo = MTrackSCM::makeDisplayName($row);
        break;
      }
    } else if (strpos($defrepo, '/') === false) {
      $defrepo = 'default/' . $defrepo;
    }
  }
  return $defrepo;
}

function mtrack_changeset_url($cs, $repo = null)
{
  global $ABSWEB;
  if ($repo instanceof MTrackRepo) {
    $p = $repo->getBrowseRootName() . '/';
  } elseif ($repo !== null) {
    if (strpos($repo, '/') === false) {
      $repo = "default/$repo";
    }
    $p = $repo . '/';
  } else {
    static $repos = null;
    if ($repos === null) {
      $repos = array();
      foreach (MTrackDB::q('select r.shortname as repo, p.shortname as proj from repos r left join project_repo_link l using (repoid) left join projects p using (projid) where parent is null or length(parent) = 0')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $r = $row['repo'];
        if ($row['proj']) {
          $repos[$row['proj']] = $r;
        }
        $repos[$row['repo']] = $r;
      }
    }
    $p = null;
    foreach ($repos as $a => $b) {
      if (!strncasecmp($cs, $a, strlen($a))) {
        $p = 'default/' . $b;
        $cs = substr($cs, strlen($a));
        break;
      }
    }
    if ($p === null) {
      $p = mtrack_defrepo();
    }
    $p .= '/';
  }
  return $ABSWEB . "changeset.php/$p$cs";
}

function mtrack_changeset($cs, $repo = null)
{
  $display = $cs;
  if (strlen($display) > 12) {
    $display = substr($display, 0, 12);
  }
  $url = mtrack_changeset_url($cs, $repo);
  return "<a class='changesetlink' href='$url'>[$display]</a>";
}

function mtrack_branch($branch, $repo = null)
{
  return "<span class='branchname'>$branch</span>";
}

function mtrack_wiki($pagename, $extras = array())
{
  global $ABSWEB;
  if ($pagename instanceof MTrackWikiItem) {
    $wiki = $pagename;
  } else if (is_string($pagename)) {
    $wiki = null;//MTrackWikiItem::loadByPageName($pagename);
  } else {
    // FIXME: hinted data from reports
    throw new Exception("FIXME: wiki");
  }
  if ($wiki) {
    $pagename = $wiki->pagename;
  }
  $html = "<a class='wikilink'";
  if (isset($extras['#'])) {
    $anchor = '#' . $extras['#'];
  } else {
    $anchor = '';
  }
  $html .= " href=\"{$ABSWEB}wiki.php/$pagename$anchor\">";
  if (isset($extras['display'])) {
    $html .= htmlentities($extras['display'], ENT_QUOTES, 'utf-8');
  } else {
    $html .= htmlentities($pagename, ENT_QUOTES, 'utf-8');
  }
  $html .= "</a>";
  return $html;
}

function mtrack_ticket($no, $extras = array())
{
  global $ABSWEB;

  if ($no instanceof MTrackIssue) {
    $tkt = $no;
  } else if (is_string($no) || is_int($no)) {
    static $cache = array();

    if ($no[0] == '#') {
      $no = substr($no, 1);
    }

    if (!isset($cache[$no])) {
      if (strlen($no) == 32) {
        $tkt = MTrackIssue::loadById($no);
      } else {
        $tkt = MTrackIssue::loadByNSIdent($no);
      }
      $cache[$no] = $tkt;
    } else {
      $tkt = $cache[$no];
    }
  } else {
    // FIXME: hinted data from reports
    $tkt = new stdClass;
    $tkt->tid = $no['ticket'];
    $tkt->summary = $no['summary'];
    if (isset($no['state'])) {
      $tkt->status = $no['state'];
    } elseif (isset($no['status'])) {
      $tkt->status = $no['status'];
    } elseif (isset($no['__status__'])) {
      $tkt->status = $no['__status__'];
    } else {
      $tkt->status = '';
    }
  }
  if ($tkt == NULL) {
    $tkt = new stdClass;
    $tkt->tid = $no;
    $tkt->summary = 'No such ticket';
    $tkt->status = 'No such ticket';
  }
  $html = "<a class='ticketlink";
  if ($tkt->status == 'closed') {
    $html .= ' completed';
  }
  if (!empty($tkt->nsident)) {
    $ident = $tkt->nsident;
  } else {
    $ident = $tkt->tid;
  }
  if (isset($extras['#'])) {
    $anchor = '#' . $extras['#'];
  } else {
    $anchor = '';
  }
  if (isset($extras['display']) && $tkt->tid == $extras['display']) {
    unset($extras['display']);
  }
  $html .= "' href=\"{$ABSWEB}ticket.php/$ident$anchor\">";
  if (isset($extras['display'])) {
    $html .= htmlentities($extras['display'], ENT_QUOTES, 'utf-8');
  } else {
    $html .= '#' . htmlentities($ident, ENT_QUOTES, 'utf-8');
  }
  $html .= "</a>";
  return $html;
}

function mtrack_tag($tag, $repo = null)
{
  return "<span class='tagname'>$tag</span>";
}

function mtrack_component($comp)
{
  global $ABSWEB;

  static $comps = null;
  if ($comps === null && preg_match("/^\d+$/", $comp)) {
    $comps = MTrackComponent::enumerate();
  }
  if (is_array($comps) && isset($comps[$comp])) {
    $comp = $comps[$comp]->name;
  }

  return "<span class='component'>" .
    htmlentities($comp, ENT_QUOTES, 'utf-8') . "</span>";
}

function mtrack_keyword($keyword)
{
  global $ABSWEB;

  static $keywords = null;
  if ($keywords === null && preg_match("/^\d+$/", $keyword)) {
    $keywords = MTrackKeyword::enumerate();
  }
  if (is_array($keywords) && isset($keywords[$keyword])) {
    $keyword = $keywords[$keyword];
  }

  $kw = urlencode($keyword);
  return "<a class='keyword' href='{$ABSWEB}search.php?q=keyword%3A$kw'>$keyword</a>";
}

function mtrack_multi_select_box($name, $title, $items, $values = null)
{
  $title = htmlentities($title, ENT_QUOTES, 'utf-8');
  $html = "<select id='$name' name='{$name}[]' multiple='multiple' title='$title' data-placeholder='$title'>";
  $in_group = null;
  foreach ($items as $k => $v) {
    $group = null;
    $label = null;
    if (is_array($v)) {
      /* option group item */
      list($label, $group) = $v;
    } else {
      $label = $v;
    }

    if ($in_group && $in_group != $group) {
      /* end of a group */
      $html .= "</optgroup>\n";
      $in_group = null;
    }
    if ($group && !$in_group) {
      $html .= "<optgroup label='" .
        htmlspecialchars($group, ENT_QUOTES, 'utf-8') .
        "'>\n";
      $in_group = $group;
    }

    $html .= "<option value='" .
      htmlspecialchars($k, ENT_QUOTES, 'utf-8') .
      "'";
    if (isset($values[$k])) {
      $html .= ' selected';
    }
    $html .= ">" . htmlentities($label, ENT_QUOTES, 'utf-8') . "</option>\n";
  }
  if ($in_group) {
    $html .= "</optgroup>\n";
  }
  return $html . "</select>";
}

function mtrack_select_box($name, $items, $value = null, $keyed = true,
  $placeholder = null)
{
  if ($placeholder) {
    $placeholder = " data-placeholder='" .
      htmlentities($placeholder, ENT_QUOTES, 'utf-8') . "'";
  } else {
    $placeholder = '';
  }
  $html = "<select id='$name' name='$name'$placeholder>";
  foreach ($items as $k => $v) {
    $html .= "<option value='" .
      htmlspecialchars($keyed ? $k : $v, ENT_QUOTES, 'utf-8') .
      "'";
    if (($keyed && $value == $k) || (!$keyed && $value == $v)) {
      $html .= ' selected';
    }
    $html .= ">" . htmlentities($v, ENT_QUOTES, 'utf-8') . "</option>\n";
  }
  return $html . "</select>";
}

function mtrack_radio($name, $value, $curval)
{
  $checked = $curval == $value ? " checked='checked'": '';
  return "<input type='radio' id='$value' name='$name' value='$value'$checked>";
}

function mtrack_diff($diffstr)
{
  $nlines = 0;

  if (is_resource($diffstr)) {
    $lines = array();
    while (($line = fgets($diffstr)) !== false) {
      $lines[] = rtrim($line, "\r\n");
    }
    $diffstr = $lines;
  }

  if (is_string($diffstr)) {
    $abase = md5($diffstr);
    $diffstr = preg_split("/\r?\n/", $diffstr);
  } else {
    $abase = md5(join("\n", $diffstr));
  }

  /* we could use toggle() below, but it is much faster to determine
   * if we are hiding or showing based on a single variable than evaluating
   * that for each possible cell */
  $html = <<<HTML
<button class='togglediffcopy btn' type='button'>Toggle Diff Line Numbers</button>
HTML;
  $html .= "<table class='code diff'>";
  //$html = "<pre class='code diff'>";

  while (true) {
    if (!count($diffstr)) {
      break;
    }
    $line = array_shift($diffstr);
    $nlines++;
    if (!strncmp($line, '@@ ', 3)) {
      /* done with preamble */
      break;
    }
    $line = htmlspecialchars($line, ENT_QUOTES, 'utf-8');
    $line = "<tr class='meta'><td class='lineno'></td><td class='lineno'></td><td class='lineno'></td><td width='100%'>$line</tr>";
    $html .= $line . "\n";
    $line = null;
  }

  $lines = array(0, 0);
  $first = false;
  while ($line !== null) {
    $class = 'unmod';

    if (preg_match("/^@@\s+-(\pN+)(?:,\pN+)?\s+\+(\pN+)(?:,\pN+)?\s*@@/",
        $line, $M)) {
      $lines[0] = (int)$M[1] - 1;
      $lines[1] = (int)$M[2] - 1;
      $class = 'meta';
      $first = true;
    } elseif (strlen($line)) {
      if ($line[0] == '-') {
        $lines[0]++;
        $class = 'removed';
      } elseif ($line[0] == '+') {
        $lines[1]++;
        $class = 'added';
      } else {
        $lines[0]++;
        $lines[1]++;
      }
    } else {
      $lines[0]++;
      $lines[1]++;
    }
    $row = "<tr class='$class";
    if ($first) {
      $row .= ' first';
    }
    if ($class != 'meta' && $first) {
      $first = false;
    }
    $row .= "'>";

    switch ($class) {
      case 'meta':
        $line_info = '';
        $row .= "<td class='lineno'></td><td class='lineno'></td>";
        break;
      case 'added':
        $row .= "<td class='lineno'></td><td class='lineno'>" . $lines[1] . "</td>";
        break;
      case 'removed':
        $row .= "<td class='lineno'>" . $lines[0] . "</td><td class='lineno'></td>";
        break;
      default:
        $row .= "<td class='lineno'>" . $lines[0] . "</td><td class='lineno'>" . $lines[1] . "</td>";
    }
    $anchor = $abase . '.' . $nlines;
    $row .= "<td class='linelink'><a name='$anchor'></a><a href='#$anchor' title='link to this line'>#</a></td>";

    // deliberately don't inform it of the charset; we have no idea and we
    // only really care about the obvious HTML metacharacters, not the entities
    $line = htmlspecialchars($line);
    $row .= "<td class='line' width='100%'>$line</td></tr>\n";
    $html .= $row;

    if (!count($diffstr)) {
      break;
    }
    $line = array_shift($diffstr);
    $nlines++;
  }

  if ($nlines == 0) {
    return null;
  }

  $html .= "</table>";
  return $html;
}

function _mtrack_admin_nav_add_cat(&$by_cat, $url) {
  $cats = func_get_args();
  array_shift($cats);
  foreach ($cats as $cat) {
    $by_cat[$cat][] = $url;
  }
}


function mtrack_admin_nav()
{
  global $ABSWEB;

  $cat_titles = array(
    'user' => 'Users &amp; Groups',
    'repo' => 'Configure Repositories',
    'projects' => 'Projects &amp; Notifications',
    'tickets' => 'Configure Tickets',
    'logs' => 'Initial Setup &amp; Logs',
    'import' => 'Import',
  );

  $by_cat = array();
  $add_cat = '_mtrack_admin_nav_add_cat';

  if (MTrackACL::hasAnyRights('Projects', 'modify')) {
    $add_cat($by_cat, "<a href='{$ABSWEB}admin/project.php'><i class='icon-envelope'></i> Projects and Groups</a>", 'user');
  }

  if (MTrackACL::hasAnyRights('Enumerations', 'modify')) {
    $eurl = $ABSWEB . 'admin/enum.php';
    $add_cat($by_cat, "<a href='$eurl/Priority'><i class='icon-list'></i> Priority</a>", 'tickets');
    $add_cat($by_cat, "<a href='$eurl/TicketState'><i class='icon-list'></i> States</a>", 'tickets');
    $add_cat($by_cat, "<a href='$eurl/Severity'><i class='icon-list'></i> Severity</a>", 'tickets');
    $add_cat($by_cat, "<a href='$eurl/Resolution'><i class='icon-list'></i> Resolution</a>", 'tickets');
    $add_cat($by_cat, "<a href='$eurl/Classification'><i class='icon-list'></i> Classification</a>", 'tickets');
    $add_cat($by_cat, "<a href='{$ABSWEB}admin/customfield.php'><i class='icon-list'></i> Custom Fields</a>", 'tickets');
  }

  if (MTrackACL::hasAnyRights('Components', 'modify')) {
    $add_cat($by_cat, "<a href='{$ABSWEB}admin/component.php'><i class='icon-list'></i> Components</a>", 'projects');
  }

  if (MTrackACL::hasAnyRights('Browser', 'modify')) {
    $add_cat($by_cat, "<a href='{$ABSWEB}admin/repo.php'><i class='icon-file'></i> Repositories</a>", 'repo');
  }

  if (MTrackACL::hasAllRights('User', 'modify')) {
    $add_cat($by_cat, "<a href='{$ABSWEB}admin/auth.php'><i class='icon-lock'></i> Authentication</a>", 'logs');
    $add_cat($by_cat, "<a href='{$ABSWEB}admin/user.php'><i class='icon-user'></i> Users</a>", 'user');
  }

  if (MTrackACL::hasAnyRights('Tickets', 'create')) {
    $add_cat($by_cat, "<a class='btn' href='{$ABSWEB}admin/importcsv.php'><i class='icon-upload'></i> Import CSV</a>", 'import');
  }

  if (MTrackACL::hasAllRights('Browser', 'modify')) {
    $add_cat($by_cat, "<a href='{$ABSWEB}admin/logs.php'><i class='icon-cog'></i> Indexer logs</a>", 'logs');
  }

  /* there should be an easier way to figure this out, but there's
   * no guaranteed way with PHP */
  $here = preg_replace('{^.*/admin/}', '', $_SERVER['REQUEST_URI']);

  echo "<div class='well' id='adminnav'>";
  echo "<ul class='nav nav-list'>\n";
  foreach ($cat_titles as $cat => $title) {
    $links = $by_cat[$cat];
    if (count($links) == 0) {
      continue;
    }
    echo "<li class='nav-header'>$title</li>";
    foreach ($links as $link) {
      $class = '';
      if (preg_match("{href='.*/admin/(.*?)'}", $link, $M)) {
        $there = $M[1];
        if ($here == $there) {
          $class = " class='active'";
        }
      }
      echo "<li$class>$link</li>\n";
    }
  }
  echo "</ul>";
  echo "</div>";

}

function mtrack_mime_detect($filename, $namehint = null)
{
  /* does config tell us how to decide mimetype */
  $detector = MTrackConfig::get('core', 'mimetype_detect');

  /* if detector is blank, we'll try to figure out which one to use */
  if (empty($detector)) {
    if (function_exists('finfo_open')) {
      $detector = 'fileinfo';
    } elseif (function_exists('mime_content_type')) {
      $detector = 'mime_magic';
    } else {
      $detector = 'file';
    }
  }

  /* use detector or all mimetypes will be blank */
  if ($detector === 'fileinfo') {
    if (defined('FILEINFO_MIME_TYPE')) {
      $fileinfo = finfo_open(FILEINFO_MIME_TYPE);
    } else {
      $magic = MTrackConfig::get('core', 'mime.magic');
      if (strlen($magic)) {
        $fileinfo = finfo_open(FILEINFO_MIME, $magic);
      } else {
        $fileinfo = finfo_open(FILEINFO_MIME);
      }
    }
    $mimetype = finfo_file($fileinfo, $filename);
    finfo_close($fileinfo);
  } elseif ($detector === 'mime_magic') {
    $mimetype = mime_content_type($filename);
  } elseif (PHP_OS != 'SunOS') {
    $mimetype = shell_exec("file -b --mime " . escapeshellarg($filename));
  } else {
    $mimetype = 'application/octet-stream';
  }
  $mimetype = trim(preg_replace("/\s*;.*$/", '', $mimetype));
  if (empty($mimetype)) {
    $mimetype = 'application/octet-stream';
  }
  if ($mimetype == 'application/octet-stream') {
    if ($namehint === null) {
      $namehint = $filename;
    }
    $pi = pathinfo($namehint);
    switch (strtolower($pi['extension'])) {
      case 'bin': return 'application/octet-stream';
      case 'exe': return 'application/octet-stream';
      case 'dll': return 'application/octet-stream';
      case 'iso': return 'application/octet-stream';
      case 'so': return 'application/octet-stream';
      case 'a': return 'application/octet-stream';
      case 'lib': return 'application/octet-stream';
      case 'pdf': return 'application/pdf';
      case 'ps': return 'application/postscript';
      case 'ai': return 'application/postscript';
      case 'eps': return 'application/postscript';
      case 'ppt': return 'application/vnd.ms-powerpoint';
      case 'xls': return 'application/vnd.ms-excel';
      case 'tiff': return 'image/tiff';
      case 'tif': return 'image/tiff';
      case 'wbmp': return 'image/vnd.wap.wbmp';
      case 'png': return 'image/png';
      case 'gif': return 'image/gif';
      case 'jpg': return 'image/jpeg';
      case 'jpeg': return 'image/jpeg';
      case 'ico': return 'image/x-icon';
      case 'bmp': return 'image/bmp';
      case 'css': return 'text/css';
      case 'htm': return 'text/html';
      case 'html': return 'text/html';
      case 'txt': return 'text/plain';
      case 'xml': return 'text/xml';
      case 'eml': return 'message/rfc822';
      case 'asc': return 'text/plain';
      case 'rtf': return 'application/rtf';
      case 'wml': return 'text/vnd.wap.wml';
      case 'wmls': return 'text/vnd.wap.wmlscript';
      case 'gtar': return 'application/x-gtar';
      case 'gz': return 'application/x-gzip';
      case 'tgz': return 'application/x-gzip';
      case 'tar': return 'application/x-tar';
      case 'zip': return 'application/zip';
      case 'sql': return 'text/plain';
    }
    // if the file is ascii, then treat it as text/plain
    $fp = fopen($filename, 'rb');
    $mimetype = 'text/plain';
    do {
      $x = fread($fp, 8192);
      if (!strlen($x)) break;
      if (preg_match('/([\x80-\xff])/', $x, $M)) {
        $mimetype = 'application/octet-stream';
        break;
      }
    } while (true);
    $fp = null;
  }
  return $mimetype;
}

function mtrack_run_tool($toolname, $mode, $args = null)
{
  global $FORKS;

  $tool = MTrackConfig::get('tools', $toolname);
  if (!strlen($tool)) {
    $tool = $toolname;
  }
  if (PHP_OS == 'Windows' && strpos($tool, ' ') !== false) {
    $tool = '"' . $tool . '"';
  }
  $cmd = $tool;
  if (is_array($args)) {
    foreach ($args as $arg) {
      if (is_array($arg)) {
        foreach ($arg as $a) {
          $cmd .= ' ' . escapeshellarg($a);
        }
      } else {
        $cmd .= ' ' . escapeshellarg($arg);
      }
    }
  }
  if (!isset($FORKS[$cmd])) {
    $FORKS[$cmd] = 0;
  }
  $FORKS[$cmd]++;
  if (false) {
    if (php_sapi_name() == 'cli') {
      echo $cmd, "\n";
    } else {
      error_log($cmd);
      echo htmlentities($cmd) . "<br>\n";
    }
  }
#  $log = fopen('/var/tmp/mtrack.popen.log', 'a');
#  fwrite($log, date('Y-m-d H:i:s') . " $cmd\n");
#  fclose($log);
  switch ($mode) {
    case 'read':   return popen($cmd, 'r');
    case 'write':  return popen($cmd, 'w');
    case 'string': return stream_get_contents(popen($cmd, 'r'));
    case 'proc':
      $pipedef = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
      );
      $proc = proc_open($cmd, $pipedef, $pipes);
      return array($proc, $pipes);
  }
}

if (php_sapi_name() != 'cli') {
  set_exception_handler('mtrack_last_chance_saloon');
  error_reporting(E_ALL);
  ini_set('display_errors', false);
  set_time_limit(300);
}


