<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../../../inc/common.php';

$plugins = MTrackConfig::getSection('plugins');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['openid_on']) && $_POST['openid_on'] == 'on') {
    /* turn it on: no parameters */
    MTrackConfig::set('plugins', 'MTrackAuth_OpenID', '');
  } else {
    /* turn it off */
    MTrackConfig::remove('plugins', 'MTrackAuth_OpenID');
  }

  MTrackConfig::save();
  header("Location: {$ABSWEB}admin/auth.php");
  exit;
}

mtrack_head("Administration - OpenID");
mtrack_admin_nav();

$on = isset($plugins['MTrackAuth_OpenID']) ? ' checked ' : '';

echo <<<HTML
<h1>OpenID</h1>
<p>
  The OpenID module allows users to login using an OpenID instead of
  managing and maintaining a password within mtrack.  Users still need
  to have an mtrack account name, and enabling OpenID does <b>not</b>
  require all users to switch to OpenID.
</p>
<br>

<form method='POST'>
  <input type='checkbox' name='openid_on' $on> Enable OpenID
  <br>
  <button class='btn btn-primary' type='submit'>
    Save OpenID Settings</button>
</form>
HTML;

mtrack_foot();
