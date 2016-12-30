<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';
require_once 'Auth/OpenID/Consumer.php';
require_once 'Auth/OpenID/FileStore.php';
require_once 'Auth/OpenID/SReg.php';
require_once 'Auth/OpenID/PAPE.php';

if (!MTrackAuth::getMech('MTrackAuth_OpenID')) {
  header("Location: $ABSWEB");
  exit;
}

$store_location = MTrackConfig::get('openid', 'store_dir');
if (!$store_location) {
  $store_location = MTrackConfig::get('core', 'vardir') . '/openid';
}
if (!is_dir($store_location)) {
  mkdir($store_location);
}
$store = new Auth_OpenID_FileStore($store_location);
$consumer = new Auth_OpenID_Consumer($store);

$message = null;

$pi = mtrack_get_pathinfo();
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $pi != 'register') {

  $req = null;

  if (!isset($_POST['openid_identifier']) ||
      !strlen($_POST['openid_identifier'])) {
    $message = "you must fill in your OpenID";
  } else {
    $id = $_POST['openid_identifier'];
    if (!preg_match('/^https?:\/\//', $id)) {
      $id = "http://$id";
    }
    $req = $consumer->begin($id);
    if (!$req) {
      $message = "not a valid OpenID";
    }
  }
  if ($req) {
    $sreg = Auth_OpenID_SRegRequest::build(
      array('nickname', 'fullname', 'email')
    );
    $req->addExtension($sreg);

    if ($req->shouldSendRedirect()) {
      $rurl = $req->redirectURL(
        $ABSWEB, $ABSWEB . 'auth/openid.php/callback');
      if (Auth_OpenID::isFailure($rurl)) {
        $message = "Unable to redirect to server: " . $rurl->message;
      } else {
        header("Location: $rurl");
        exit;
      }
    } else {
      $html = $req->htmlMarkup($ABSWEB, $ABSWEB . 'auth/openid.php/callback',
        false, array('id' => 'openid_message'));
      if (Auth_OpenID::isFailure($html)) {
        $message = "Unable to redirect to server: " . $html->message;
      } else {
        echo $html;
      }
    }
  }
} else if ($pi == 'callback') {
  $res = $consumer->complete($ABSWEB . 'auth/openid.php/callback');

  if ($res->status == Auth_OpenID_CANCEL) {
    $message = 'Verification cancelled';
  } else if ($res->status == Auth_OpenID_FAILURE) {
    $message = 'OpenID authentication failed: ' . $res->message;
  } else if ($res->status == Auth_OpenID_SUCCESS) {
    $id = $res->getDisplayIdentifier();
    $sreg = Auth_OpenID_SRegResponse::fromSuccessResponse($res)->contents();

    if (!empty($sreg['nickname'])) {
      $name = $sreg['nickname'];
    } else if (!empty($sreg['fullname'])) {
      $name = $sreg['fullname'];
    } else {
      $name = $id;
    }
    $message = 'Authenticated as ' . $name;

    $_SESSION['openid.id'] = $id;
    unset($_SESSION['openid.userid']);
    $_SESSION['openid.name'] = $name;
    if (!empty($sreg['email'])) {
      $_SESSION['openid.email'] = $sreg['email'];
    }
    /* See if we can find a canonical identity for the user */

    $user = MTrackUser::loadUser($id, true);
    if ($user) {
      $_SESSION['openid.userid'] = $user->userid;
      header("Location: " . $ABSWEB);
      exit;
    }

    /* prompt the user to fill out some basic details so that we can create
      * a local identity and associate their OpenID with it */

    $_SESSION['mtrack.auth.register'] = array(
      'mech' => 'MTrackAuth_OpenID',
      'login' => $name,
      'email' => $sreg['email'],
      'name' => $sreg['fullname'],
      'alias' => $id,
      'sreg' => $sreg,
    );

    header("Location: {$ABSWEB}auth/register.php");
    exit;
  } else {
    $message = 'An error occurred while talking to your OpenID provider';
  }
} else if ($pi == 'signout') {
  session_destroy();
  header('Location: ' . $ABSWEB);
  exit;
}

mtrack_head('Authentication Required');
echo "<h1>Please sign in with your <a id='openidlink' href='http://openid.net'><img src='{$ABSWEB}images/logo_openid.png' alt='OpenID' border='0'></a></h1>\n";
echo "<form method='post' action='{$ABSWEB}auth/openid.php'>";
echo "<input type='text' name='openid_identifier' id='openid_identifier'>";
echo " <button type='submit' id='openid-sign-in'>Sign In</button>";

if ($message) {
  $message = htmlentities($message, ENT_QUOTES, 'utf-8');
  echo <<<HTML
<div class='ui-state-highlight ui-corner-all'>
    <span class='ui-icon ui-icon-info'></span>
    $message
</div>
HTML;
}

echo "</form>";


mtrack_foot();

