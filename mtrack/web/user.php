<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

$user = mtrack_get_pathinfo();
if ($user === null && isset($_GET['user'])) {
  $user = $_GET['user'];
  header("Location: {$ABSWEB}user.php/" . urlencode($user));
  exit;
}
if (!strlen(trim($user))) {
  throw new Exception("No user name provided");
}
$canon_user = mtrack_canon_username($user);
if ($canon_user != $user) {
  header("Location: {$ABSWEB}user.php/" . urlencode($canon_user));
  exit;
}

$me = mtrack_canon_username(MTrackAuth::whoami());
MTrackACL::requireAllRights('User', 'read');
$me = mtrack_canon_username(MTrackAuth::whoami());
mtrack_head("User $user");

try {
  $U = MTrackAPI::invoke('GET', "/user/$user")->result;
  $KEYS = MTrackAPI::invoke('GET', "/user/$user/keys")->result;
  $isNew = 0;
} catch (Exception $e) {
  MTrackACL::requireAllRights('User', 'modify');
  $isNew = 1;
  $U = new stdclass;
  $U->id = $user;
  $KEYS = array();
}

$U = json_encode($U);
$KEYS = json_encode($KEYS);

if (($me != 'anonymous' && $me === $user) ||
    MTrackACL::hasAnyRights('User', 'modify')) {
  $viewclass = 'MTrackUserProfileEdit';
} else {
  $viewclass = 'MTrackUserProfileView';
}

$http_auth = MTrackAuth::getMech('MTrackAuth_HTTP');
$local_auth = MTrackAuth::getMech('MTrackAuth_MTrack');

/* show password change controls? */
if ($me === $user || MTrackACL::hasAnyRights('User', 'modify')) {
  if ($http_auth) {
    $pw_change = ($http_auth && !isset($_SERVER['REMOTE_USER']));
  } else if ($local_auth) {
    $pw_change = true;
  }
} else {
  $pw_change = false;
}

$pw_change = json_encode($pw_change);

$privileged = json_encode(MTrackACL::hasAnyRights('User', 'modify'));

ob_start();
mtrack_render_timeline($user);
$timeline = ob_get_contents();
ob_end_clean();

echo <<<HTML
<script type="text/template" id='timeline-data' style="display:none">$timeline</script>
<script type="text/template" id='user-profile-template'>
<div class='userinfo'>
  <img class='gravatar' src="${ABSWEB}avatar.php?u=<%- id %>&amp;s=96">
  <h1><%- id %><% if (fullname) { %> - <%- fullname %><% } %></h1>
  <a href="mailto:<%- email %>"><%- email %></a><br>
  <% if (aliases.length) { %>
  <h2>Aliases</h2>
  <ul>
  <% _.each(aliases, function (name) { %>
  <li><%- name %></li>
  <% }); %>
  </ul>
  <% } %>
  <div id='timeline'></div>
</div>
</script>
<script type="text/template" id='user-edit-template'>
<h1><%- id %> <span id='user-fullname'></span></h1>

<fieldset id='userinfo-container'>
  <legend>User Information</legend>
  <img class='gravatar' src="${ABSWEB}avatar.php?u=<%- id %>&amp;s=96">

  <div class='userinfofield'>
  <label>Email</label>
  <span id='user-email'></span>
  <em class='tip'>We use this with <a href='http://gravatar.com'>Gravatar</a>
    to obtain your avatar image throughout mtrack</em>
  </div>

  <div class='userinfofield'>
  <label>Timezone</label>
  <span id='user-timezone'></span>
  <em class='tip'>We use this to show times in your preferred timezone</em>
  </div>

  <% if (privileged) { %>
  <div class='userinfofield'>
    <label>Active?</label>
    <input type='checkbox' id='user-active'>
    <em class='tip'>Active users are shown in the Responsible users list when editing tickets</em>
    </div>
  <% } %>

</fieldset>
  <div id='user-tabs'>
    <ul>
      <% if (!$isNew) { %>
      <li><a href='#activity-tab'>Activity</a></li>
      <% } %>
      <li><a href='#aliases-tab'>Aliases</a></li>
      <li><a href='#keys-tab'>SSH Keys</a></li>
      <li><a href='#roles-tab'>Roles &amp; Permissions</a></li>
      <% if (pw_change) { %>
      <li><a href='#auth-tab'>Password</a></li>
      <% } %>
    </ul>
    <% if (!$isNew) { %>
    <div id='activity-tab' class='tab'>
      <div id='timeline'></div>
    </div>
    <% } %>
    <div id='aliases-tab' class='tab'>
      <em>This user is also known by the following identities
      when assessing changes in the various repositories</em><br>
      <% if (privileged) { %>
      <input id='add-alias' placeholder='Enter a new alias'>
      <button id='add-alias-button'>Add Alias</button>
      <% } %>
      <ul id='aliases-list'></ul>
    </div>
    <div id='keys-tab' class='tab'>
      <em>The repositories created and managed by mtrack are served over SSH.
        Access is enabled only based on public SSH keys, not passwords.
        In order to check code in or out, you must provide one or more
        keys.  Paste in the public key(s) you want to use below, one per line.
      </em><br><br>
      <em>
        If your workflow causes you to work on multiple build or
        server machines, we recommend using the "-A" Agent Forwarding option for
        SSH when you login to those machines; that way you only need to maintain
        keys for your primary workstation(s).
      </em>

      <ul id='keys-list'></ul>

      <p>Paste in new ssh key(s) below, one per line</p>
      <textarea id="new-key" rows="5" cols="78"></textarea><br>
      <em>It may take a few minutes for changes to keys to take effect!</em><br>
      <button id="add-key">Add Key</button>
    </div>
    <div id='roles-tab' class='tab'>
      <b>Primary Role</b> <div id='primary-role'></div><br>
      <b>Groups</b>
      <ul id='groups-list'></ul>
    </div>
    <% if (pw_change) { %>
    <div id='auth-tab' class='tab'>

      <label>New Password</label>
      <input type="password" id="passwd1" placeholder="Enter new password">
      <em>Enter new password</em>
      <br>
      <label>Confirm Password</label>
      <input type="password" id="passwd2" placeholder="Confirm password">
      <em>Confirm new password</em>
      <br>
      <button id="save-password">Change Password</button>

    </div>
    <% } %>
</div>
</script>

<div id='user-profile'></div>
<script>
$(document).ready(function () {
  var TheUser = new MTrackUser($U, {
    keys: $KEYS
  });
  new $viewclass({
    model: TheUser,
    el: '#user-profile',
    pw_change: $pw_change,
    privileged: $privileged
  });
  $('#timeline').html($('#timeline-data').html());
});
</script>
HTML;

mtrack_foot();

