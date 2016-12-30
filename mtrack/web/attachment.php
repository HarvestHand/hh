<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

$pi = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$vars = explode('/', $pi);
array_shift($vars);
$filename = array_pop($vars);
$cid = array_pop($vars);
$object = join('/', $vars);

MTrackACL::requireAllRights($object, 'read');

foreach (MTrackDB::q('select hash, size from attachments where
    object = ? and cid = ? and filename = ?', $object, $cid, $filename)
    ->fetchAll() as $row)
{
  $filename = basename($filename);
/*
  header("Pragma: public");
  header('Expires: 0');
  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
  header('Cache-Control: private', false);
*/
  $age = 3600;
  header("Cache-Control: public, max-age=$age, pre-check=$age");
  header('Expires: ' . date(DATE_COOKIE, time() + $age));

  $path = MTrackAttachment::local_path($row['hash']);
  $mimetype = mtrack_mime_detect($path, $filename);
  header("Content-Type: $mimetype");

  list($major) = explode('/', $mimetype, 2);
  if ($major == 'image' || $major == 'text') {
    $disp = 'inline';
  } else {
    $disp = 'attachment';
  }
  header("Content-Disposition: $disp; filename=\"$filename\"");
  header('Content-Transfer-Encoding: binary');
  header("Content-Length: $row[size]");
  readfile($path);
  exit;
}

mtrack_header('Not found');
mtrack_foot();
