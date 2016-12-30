<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* we exist solely to redirect back to the main app; we need this
 * because the oauth redirect back to us passes GET parameters that
 * we don't care about, but that the facebook auth stuff consumes */

include '../../inc/common.php';

MTrackAuth::whoami();

$FB = MTrackAuth::getMech('MTrackAuth_Facebook');
if ($FB) {
  $me = $FB->getMe();
  if (!isset($_SESSION['auth.facebook.userid'])) {
    $user = MTrackUser::loadUser($me['email'], true);
    if (!$user) {
      $_SESSION['mtrack.auth.register'] = array(
        'mech' => 'MTrackAuth_Facebook',
        'login' => $me['username'],
        'email' => $me['email'],
        'name' => $me['name'],
        'sreg' => $me
      );
      session_write_close();
      header("Location: {$ABSWEB}auth/register.php");
      exit;
    }
    $_SESSION['auth.facebook.userid'] = $user->userid;
    session_write_close();
  }
}

header('Location: ' . $ABSWEB);

