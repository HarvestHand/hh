<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

if (!MTrackAuth::getMech('MTrackAuth_BrowserID')) {
  header("Location: $ABSWEB");
  exit;
}

function verify_assertion($assertion)
{
  if (!strlen($assertion)) {
    $res = new stdclass;
    $res->status = 'error';
    $res->reason = 'missing assertion';
    return $res;
  }
  $audience = ($_SERVER['HTTPS'] ? 'https://' : 'http://') .
                $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];
  $postdata = 'assertion=' . urlencode($assertion) .
              '&audience=' . urlencode($audience);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://browserid.org/verify");
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
  $json = curl_exec($ch);
  curl_close($ch);

  return json_decode($json);
}

if (!session_id()) {
  session_start();
}

$assertion = file_get_contents('php://input');
$res = verify_assertion($assertion);
if ($res->status == 'okay') {
  $user = MTrackUser::loadUser($res->email, true);
  if ($user) {
    $_SESSION['auth.browserid'] = $user->userid;
    $res->user = $user->userid;
  } else {
    unset($_SESSION['auth.browserid']);

    $_SESSION['mtrack.auth.register'] = array(
      'mech' => 'MTrackAuth_BrowserID',
      'email' => $res->email,
      'sreg' => $res,
    );
    session_write_close();

    $res->status = 'register';
  }
}

header('Content-Type: application/json');
echo json_encode($res);

