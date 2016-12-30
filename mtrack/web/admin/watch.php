<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

$me = mtrack_canon_username(MTrackAuth::whoami());

if ($me == 'anonymous' || MTrackAuth::getUserClass() == 'anonymous') {
  exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $object = $_GET['o'];
  $id = $_GET['i'];
  $v = $_POST['w'];
  $value = json_decode($v);

  $db = MTrackDB::get();
  $db->beginTransaction();
  MTrackDB::q('delete from watches where otype = ? and oid = ? and userid = ?',
    $object, $id, $me);

  foreach ($value as $medium => $events) {
    foreach ($events as $evt => $value) {
      MTrackDB::q('insert into watches (otype, oid, userid, medium, event, active) values (?, ?, ?, ?, ?, 1)',
        $object, $id, $me, $medium, $evt);
    }
  }

  $db->commit();
}

