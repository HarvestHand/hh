<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

MTrackACL::requireAnyRights('User', 'modify');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  function is_cb_set($key) {
    return isset($_POST[$key]) && $_POST[$key] == 'on';
  }
  function set_cb($name, $key) {
    if (is_cb_set($key)) {
      MTrackConfig::set('core', $name, true);
    } else {
      MTrackConfig::set('core', $name, false);
    }
  }

  set_cb('admin_party', 'party');
  set_cb('allow_self_registration', 'register');

  if (is_cb_set('local')) {
    /* turn it on: no parameters */
    MTrackConfig::set('plugins', 'MTrackAuth_MTrack', '');
  } else {
    MTrackConfig::remove('plugins', 'MTrackAuth_MTrack');
  }

  // This bit is likely made obsolete by planned class/role editor
  if (is_cb_set('restricted')) {
    // Force anonymous users to have no rights
    MTrackConfig::set('user_class_roles', 'anonymous', '');
  } else {
    // Reset anonymous users to default rights from config.ini
    MTrackConfig::remove('user_class_roles', 'anonymous');
  }

  MTrackConfig::save();
  header("Location: $GLOBALS[ABSWEB]admin/auth.php");
  exit;
}

$plugins = MTrackConfig::getSection('plugins');

mtrack_head("Administration - Authentication");
mtrack_admin_nav();

$party_addrs = MTrackConfig::get('core', 'admin_party_remote_address');

$party_on /* dude */ =
  MTrackConfig::get('core', 'admin_party') == '1' ? ' checked ' : '';

$reg_on =
  MTrackConfig::get('core', 'allow_self_registration') ? ' checked ' : '';

$restricted_on =
  MTrackConfig::get('user_class_roles', 'anonymous') == '' ? ' checked ' : '';

$local_pw = MTrackAuth::getMech('MTrackAuth_MTrack');
$local_on = '';
if ($local_pw) {
  $local_on = ' checked ';
}

echo <<<HTML
<h1>Authentication</h1>

<form method='POST'>
<input type="checkbox" $party_on name="party"> Enable admin party<br>
Anyone accessing the system from <b>$party_addrs</b> will be treated as an administrator.<br>
Everyone else will have read-only access and no self-enrolment will be allowed.
<br>
<br>
<input type="checkbox" $reg_on name="register"> Allow users to register themselves and sign up with an account.<br>
You may also want to enable <a href="{$ABSWEB}admin/auth/captcha.php">CAPTCHA</a>.
<br>
<br>
<input type="checkbox" $local_on name="local"> Use mtrack's own password storage and cookie based authentication.<br>
Uses salted SHA-512 passwords and enables HTTP Basic authentication for REST API access.
<br>
<br>
<input type="checkbox" $restricted_on name="restricted"> Restrict all access to authenticated users.<br>
Anonymous users have their permissions revoked.
<br>
<br>

<button type='submit' class='btn btn-primary'>Apply Auth Settings</button>
</form>

<ul>
  <li><a href="{$ABSWEB}admin/auth/captcha.php">Configure CAPTCHA</a><br>
      Add bot protection to the registration form.
  </li>
  <li><a href="{$ABSWEB}admin/auth/openid.php">Configure OpenID</a><br>
      Enable OpenID authentication; allow users to associate OpenID
      identifiers with their user accounts.
  </li>
  <li><a href="{$ABSWEB}admin/auth/facebook.php">Configure Facebook Auth</a><br>
      Allow users to associate Facebook logins with their user accounts.
  </li>

  <li><a href="{$ABSWEB}admin/auth/browserid.php">Configure Mozilla Persona, aka BrowserID</a><br>
      Allow users to associate Persona/BrowserID logins with
      their user accounts.
  </li>
  <li><a href="{$ABSWEB}admin/auth/http.php">Defer Auth to the Web Server</a><br>
      Configure mtrack to defer to the web server for its authentication.
  </li>

</ul>


HTML;
mtrack_foot();

