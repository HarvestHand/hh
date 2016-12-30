<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('UTC');
}

include dirname(__FILE__) . '/../inc/common.php';

# Our purpose is to generate an appropriately formatted authorized_keys2
# file.  We should be run as the user that will own the authorized_keys2
# file.

$codeshell = escapeshellcmd(realpath(dirname(__FILE__) . '/codeshell'));
$config = escapeshellarg(realpath(MTrackConfig::getLocation()));
$mtrack = escapeshellarg(realpath(dirname(__FILE__) . '/..'));

$keyfile = MTrackConfig::get('repos', 'authorized_keys2');
if (!$keyfile) {
  echo "You need to set [repos] authorized_keys2\n";
  exit(1);
}
$fp = fopen($keyfile . ".new", 'w');

$users_with_keys = array();

foreach (MTrackDB::q('select userid, sshkeys from userinfo where active = 1')->fetchAll(PDO::FETCH_OBJ) as $u) {
  $user = escapeshellarg($u->userid);
  $users_with_keys[$u->userid] = $u->userid;
  $lines = preg_split("/\r?\n/", $u->sshkeys);
  if($u->sshkeys == null) continue;
  foreach ($lines as $key) {
    $key = trim($key);
    if (!strlen($key)) continue;
    fwrite($fp, "command=\"$codeshell $config $user $mtrack\",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty $key\n");
  }
}

fclose($fp);
chmod("$keyfile.new", 0755);
rename("$keyfile.new", $keyfile);

# Unfortunately, subversion doesn't allow us to hook authorization requests
# over svnserve, so we need to pre-compute access to each svn repo for each
# user that can access it.  With very large numbers of svn repos or large
# numbers of users, this will be "expensive".
$fp = null;
$ownerfp = array();
$authzname = MTrackConfig::get('core', 'vardir') . '/svn.authz';

foreach (MTrackDB::q("select repoid from repos where scmtype = 'svn'")
    ->fetchAll(PDO::FETCH_COLUMN, 0) as $repoid) {
  $R = MTrackRepo::loadById($repoid);
  if (!$fp) {
    $fp = fopen("$authzname.new", 'w');
    # deny all
    fwrite($fp, "[/]\n* =\n");
  }
  $bits = explode("/", $R->getBrowseRootName());
  $owner = array_shift($bits);
  if(!$ownerfp[$owner]) {
    $ownerfp[$owner] = fopen("$authzname.$owner.new", 'w');
    # deny all
    fwrite($ownerfp[$owner], "[/]\n* =\n");
  }
  fwrite($fp, "[" . $R->getBrowseRootName() . ":/]\n");
  fwrite($ownerfp[$owner], "[" . implode("/", $bits) . ":/]\n");
  foreach ($users_with_keys as $user) {
    MTrackAuth::su($user);
    $level = '';
    if (MTrackACL::hasAllRights("repo:$repoid", 'commit')) {
      $level = 'rw';
    } elseif (MTrackACL::hasAllRights("repo:$repoid", 'checkout')) {
      $level = 'r';
    }
    MTrackAuth::drop();
    if (strlen($level)) {
      fwrite($fp, "$user = $level\n");
      fwrite($ownerfp[$owner], "$user = $level\n");
    }
  }
}
if($fp) {
  fclose($fp);
  rename("$authzname.new", $authzname);
}
foreach ($ownerfp as $owner => $fp) {
  fclose($fp);
  rename("$authzname.$owner.new", "$authzname.$owner");
}
