<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../../inc/common.php';

$fail = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (MTrackAuth::getMech('MTrackAuth_MTrack')) {
    /* authenticate against our password database */
    if (isset($_POST['userid']) && isset($_POST['password'])) {
      $user = MTrackUser::loadUser($_POST['userid']);
      if ($user) {
        if ($user->verifyPassword($_POST['password'])) {
          if (session_id()) {
            session_regenerate_id(true);
          } else {
            session_start();
          }
          $_SESSION['auth.mtrack'] = $user->userid;
          header("Location: $ABSWEB");
          exit;
        }
      }
    }
    $fail = "Invalid username or password";
  }
}

mtrack_head('Login');

echo <<<HTML
<h1>Log In</h1>
HTML;

// MTrack local passwords
if (MTrackAuth::getMech('MTrackAuth_MTrack')) {
  echo <<<HTML
<p>Log-in using your mtrack username and password</p>
<form method="POST">

<table>
  <tr>
    <td>
      <label for="userid">User Id</label>
    </td>
    <td>
      <input type="text" name="userid" placeholder="Enter your user id">
    </td>
  </tr>

  <tr>
    <td>
      <label for="password">Password</label>
    </td>
    <td>
      <input type="password" name="password" placeholder="Enter your password">
    </td>
  </tr>
</table>
HTML;

  if ($fail) {
    $fail = htmlentities($fail, ENT_QUOTES, 'utf-8');

    echo <<<HTML
<div class="alert alert-danger">
  <a class="close" data-dismiss="alert">&times;</a>
  $fail
</div>
HTML;
  }

  echo <<<HTML
  <button type='submit' class='btn btn-primary'>Log In</button>
HTML;

  if (MTrackConfig::get('core', 'allow_self_registration')) {
    echo <<<HTML
  <a class='btn' href="{$ABSWEB}auth/register.php">Sign Up</a>
HTML;
  }

  echo "</form>";
}

if (MTrackAuth::getMech('MTrackAuth_OpenID')) {
  echo <<<HTML
<script type="text/javascript" src="{$ABSWEB}js/openid-jquery.js"></script>
<script type="text/javascript" src="{$ABSWEB}js/openid-en.js"></script>
<script>
$(document).ready(function() {
  openid.img_path = ABSWEB + 'images/';
  openid.init('openid_identifier');
});
</script>
<style>
#openid_form {
    width: 580px;
}

#openid_form legend {
    font-weight: bold;
}

#openid_choice {
    display: none;
}

#openid_input_area {
    clear: both;
    padding: 10px;
}

#openid_btns, #openid_btns br {
    clear: both;
}

#openid_highlight {
    padding: 3px;
    background-color: #FFFCC9;
    float: left;
}
.openid_large_btn {
    width: 100px;
    height: 60px;
/* fix for IE 6 only: http://en.wikipedia.org/wiki/CSS_filter#Underscore_hack */
    _width: 102px;
    _height: 62px;

    border: 1px solid #DDD;
    margin: 3px;
    float: left;
}

.openid_small_btn {
    width: 24px;
    height: 24px;
/* fix for IE 6 only: http://en.wikipedia.org/wiki/CSS_filter#Underscore_hack */
    _width: 26px;
    _height: 26px;

    border: 1px solid #DDD;
    margin: 3px;
    float: left;
}

a.openid_large_btn:focus {
    outline: none;
}

a.openid_large_btn:focus {
    -moz-outline-style: none;
}

.openid_selected {
    border: 4px solid #DDD;
}

#openid_identifier {
  width: 20em;
}
</style>
<br>
<form method="POST" action="{$ABSWEB}auth/openid.php" id="openid_form">
    <div id="openid_choice">
      <p>Please click your account provider:</p>
      <div id="openid_btns"></div>
    </div>
    <div id="openid_input_area">
      <input type="text" name="openid_identifier" id="openid_identifier">
      <button type="submit" class="btn btn-primary">
        Sign-In</button>
    </div>
</form>
HTML;
}

// BrowserID
if (MTrackAuth::getMech('MTrackAuth_BrowserID')) {
  echo <<<HTML

<script src='https://browserid.org/include.js'></script>
<script>
function browserid_got_assertion(assertion) {
  if (assertion) {
    $.ajax({
      url: ABSWEB + 'auth/browserid.php',
      contentType: 'application/octet-stream',
      type: 'POST',
      data: assertion,
      success: function(data) {
        if (data.status == 'okay') {
          // Success; reload page (ajax call set the cookie we need)
          window.location = ABSWEB;
        } else if (data.status == 'register') {
          // Success, but user needs to register
          window.location = ABSWEB + 'auth/register.php';
        } else {
          window.alert("BrowserID authentication failed: " +
            data.reason);
        }
      },
      error: function (xhr, status, err) {
        // the alert is a bit nasty, but browserid tends to
        // deal with most errors by not even invoking this
        // assertion callback
        window.alert("BrowserID authentication failed: " +
            status + " " + err);
      }
    });
  }
}

function browserid_login() {
  navigator.id.get(browserid_got_assertion, {allowPersistent: true});
  return false;
}
$(function () {
  //navigator.id.get(browserid_got_assertion, {silent:true});
});
</script>
<br>
<a href='javascript:browserid_login()' class='btn'>Log In using BrowserID</a>
<br>
HTML;

}

// Facebook
$FBA = MTrackAuth::getMech('MTrackAuth_Facebook');
if ($FBA) {
  $url = $FBA->getFB()->getLoginUrl(array(
    'redirect_uri' => $GLOBALS['ABSWEB'] . 'auth/facebook.php'
  ));

  echo <<<HTML
<br>
<a href="$url" class="btn">Log In using Facebook</a>
<br>
HTML;
}

mtrack_foot();

