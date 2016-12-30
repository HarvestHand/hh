<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackAuth_Anon implements IMTrackAuth {

  function __construct($group = null, $passwd = null) {
    MTrackAuth::registerMech($this);
  }

  /* Leave authentication to the web server configuration */
  function authenticate() {
    /* web server based auth */
    if (isset($_SERVER['REMOTE_USER'])) {
      return $_SERVER['REMOTE_USER'];
    }
    return 'anonymous';
  }

  function doAuthenticate($force = false) {
    if (defined('MTRACK_IS_REST_API')) {
      MTrackAPI::error(401, "Authentication Required");
    }
    /* This is only triggered if the web server isn't configured */
    header('HTTP/1.0 401 Unauthorized');
?>
<h1>Authentication Required</h1>

<p>I need to know who you are to allow you to access to this site.</p>
<?php
    exit;
  }

  function enumGroups() {
    return null;
  }

  function getGroups($username) {
    return null;
  }

  function addToGroup($username, $groupname)
  {
    return null;
  }

  function removeFromGroup($username, $groupname)
  {
    return null;
  }

  function getUserData($username) {
    return null;
  }

  function canLogOut() {
    return false;
  }

  function LogOut() {
  }
}

