<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('UTC');
}

include dirname(__FILE__) . '/../inc/common.php';

/* user object right... */

array_shift($argv);
$user = array_shift($argv);
MTrackAuth::su($user);
$objectid = array_shift($argv);

/* A bit ugly to have this special case */
if ($objectid == '--repo') {
  $reponame = array_shift($argv);
  $obj = MTrackRepo::loadByName($reponame);
  $objectid = "repo:$obj->repoid";
}

$res = MTrackACL::hasAnyRights($objectid, $argv);

if ($res) {
  exit(0);
}
exit(1);

