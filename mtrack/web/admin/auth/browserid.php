<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../../../inc/common.php';

$plugins = MTrackConfig::getSection('plugins');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['browserid_on']) && $_POST['browserid_on'] == 'on') {
    /* turn it on: no parameters */
    MTrackConfig::set('plugins', 'MTrackAuth_BrowserID', '');
  } else {
    /* turn it off */
    MTrackConfig::remove('plugins', 'MTrackAuth_BrowserID');
  }

  MTrackConfig::save();
  header("Location: {$ABSWEB}admin/auth.php");
  exit;
}

mtrack_head("Administration - BrowserID");
mtrack_admin_nav();

$on = isset($plugins['MTrackAuth_BrowserID']) ? ' checked ' : '';

echo <<<HTML
<h1>Mozilla Persona / BrowserID</h1>
<p>
  The BrowserID module allows users to login using the Mozilla Persona
  (aka BrowserID) de-centralized login facility instead of
  managing and maintaining a password within mtrack.  Users still need
  to have an mtrack account name, and enabling BrowserID does <b>not</b>
  require all users to switch to BrowserID.
</p>
<br>

<form method='POST'>
  <input type='checkbox' name='browserid_on' $on> Enable BrowserID
  <br>
  <button class='btn btn-primary' type='submit'>
    Save BrowserID Settings</button>
</form>
HTML;

mtrack_foot();
