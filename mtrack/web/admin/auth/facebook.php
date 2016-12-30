<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../../../inc/common.php';

$plugins = MTrackConfig::getSection('plugins');

function is_hex_id($key) {
  $val = $_POST[$key];

  if (preg_match("/^[a-fA-F0-9]+$/", $val)) {
    return $val;
  }
  return null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['facebook_on']) && $_POST['facebook_on'] == 'on') {
    /* turn it on */
    $appid = is_hex_id('appid');
    $secret = is_hex_id('secret');
    if ($appid && $secret) {
      MTrackConfig::set('plugins', 'MTrackAuth_Facebook', "$appid,$secret");
    }
  } else {
    /* turn it off */
    MTrackConfig::remove('plugins', 'MTrackAuth_Facebook');
  }

  MTrackConfig::save();
  header("Location: {$ABSWEB}admin/auth.php");
  exit;
}

mtrack_head("Administration - Facebook");
mtrack_admin_nav();

$on = isset($plugins['MTrackAuth_Facebook']) ? ' checked ' : '';

$FB = MTrackAuth::getMech('MTrackAuth_Facebook');
if ($FB) {
  $appid = htmlentities($FB->appid, ENT_QUOTES, 'utf-8');
  $secret = htmlentities($FB->secret, ENT_QUOTES, 'utf-8');
} else {
  $appid = '';
  $secret = '';
}

echo <<<HTML
<h1>Facebook Authentication</h1>
<p>
  The Facebook auth module allows users to login using their Facebook
  login instead of
  managing and maintaining a password within mtrack.  Users still need
  to have an mtrack account name, and enabling Facebook auth does <b>not</b>
  require all users to switch to Facebook credentials.
</p>
<p>
  In order to enable Facebook authentication, you need to register your
  mtrack installation as a App using the <a href="https://developers.facebook.com/apps">Facebook Developers App Site</a>.
</p>
<br>

<form method='POST'>
  <table>
    <tr>
      <td>Site URL</td>
      <td><tt>$ABSWEB</tt></td>
    </tr>
    <tr>
      <td><label for="appid">App ID</label></td>
      <td><input type="text" name="appid"
            value="$appid"
            placeholder="Enter your App ID"></td>
    </tr>
    <tr>
      <td><label for="secret">App Secret</label></td>
      <td><input type="text" name="secret"
            value="$secret"
            placeholder="Enter your App Secret"></td>
    </tr>
  <table>
  <input type='checkbox' name='facebook_on' $on> Enable Facebook Authentication
  <br>
  <button class='btn btn-primary' type='submit'>
    Save Facebook Settings</button>
</form>
HTML;

mtrack_foot();

