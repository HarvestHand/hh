<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */
ini_set('log_errors', 1);
define('MTRACK_IS_REST_API', 1);

include '../inc/common.php';

$uri = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
$bits = explode('?', $uri, 2);
if (count($bits) > 1) {
  $uri = $bits[0];
}

$driver = new MTrackAPI_Driver_HTTP;
$value = MTrackAPI::run($_SERVER['REQUEST_METHOD'], $uri, $driver);
if ($value !== null) {
  $json = json_encode($value);
} else {
  $json = '';
}
$driver->setHeader('Content-MD5', base64_encode(md5($json, true)));
$driver->setHeader('Content-Type', 'application/json');
echo $json;

exit;

