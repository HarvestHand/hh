<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../../../inc/common.php';

$plugins = MTrackConfig::getSection('plugins');

function get_admins()
{
  $admins = array();
  foreach (MTrackConfig::getSection('user_classes') as $id => $role) {
    if ($role == 'admin' && !preg_match('@^https?://@', $id)) {
      $admins[] = $id;
    }
  }
  return $admins;
}

function get_admins_with_pw_count()
{
  $have_pw = 0;

  $admins = get_admins();
  if (count($admins)) {
    $http = MTrackAuth::getMech('MTrackAuth_HTTP');
    foreach ($admins as $id) {
      if ($http && $http->readPWFile($id)) {
        $have_pw++;
      }
    }
  }
  return $have_pw;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['http_on']) && $_POST['http_on'] == 'on') {
    $vardir = MTrackConfig::get('core', 'vardir');
    $pfile = "$vardir/http.user";

    if (!isset($plugins['MTrackAuth_HTTP'])) {
      MTrackConfig::set('plugins', 'MTrackAuth_HTTP',
        "$vardir/http.group, digest:$pfile");
    }
  } else {
    // Turn it off
    MTrackConfig::remove('plugins', 'MTrackAuth_HTTP');
  }
  MTrackConfig::save();
  header("Location: {$ABSWEB}admin/auth.php");
  exit;
}

mtrack_head("Defer Authentication");
mtrack_admin_nav();

echo <<<HTML
<h1>Defer Authentication</h1>
<p>
  This page configures the legacy HTTP Authentication plugin.  This plugin
  uses a PHP feature that allows the web server to be configured to handle
  authentication and to pass the authentication information down to the
  application.
</p>

<p>
  This module is not intended to be used in
  conjunction with other authentication modules.
</p>
HTML;


if (isset($_SERVER['REMOTE_USER'])) {
  $remote = htmlentities($_SERVER['REMOTE_USER'], ENT_QUOTES, 'utf-8');
  echo <<<HTML
<p>
  It looks like your web server is configured to use HTTP authentication
  (you're authenticated as $remote)
  mtrack will defer to your web server configuration for authentication.
  Contact your system administrator to add or remove users, or to change
  their passwords.  You may still use the mtrack user management screens
  to change rights assignments for the users.
</p>
HTML;

} else {
  echo <<<HTML
<br>
<div class='alert alert-danger'>
  mtrack will use its own HTTP authentication and store the password and group
  files in the <em>vardir</em>.
<p>
  <b>This is a legacy configuration</b> and it is recommended that
  you consider moving to use the main mtrack authentication system instead.
</p>
</div>
HTML;
}
$need_pw = get_admins_with_pw_count() == 0;
if ($need_pw) {
  if (isset($_SERVER['REMOTE_USER'])) {
    echo <<<HTML
<div class='alert alert-danger'>
mtrack was unable to find any admin level users based on your current
configuration.  Make sure that you have at least one user configured
with admin privileges.  You may need to manually configure the plugin
to find the group file it is using.
</div>
HTML;

  } else {
    echo <<<HTML
<div class='alert alert-danger'>
You <em>MUST</em> add at least one user as an administrator (and give
them a password!),
otherwise no one will be able to administer the system without editing
the config.ini file.
<p>
Use the
<a href="{$ABSWEB}admin/user.php">Users</a> admin section to manage
users; note that you need to save this screen before the password
tab will be enabled.
</p>
</div>
HTML;
  }
}

$http_on = isset($plugins['MTrackAuth_HTTP']) ? ' checked ' : '';

echo <<<HTML
<form method='POST'>
  <input type='checkbox' name='http_on' $http_on> Enable Deferred Authentication
  <br>
  <button class='btn btn-primary' type='submit'>
    Apply Authentication Settings</button>
</form>
HTML;

mtrack_foot();
