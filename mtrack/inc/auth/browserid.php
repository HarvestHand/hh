<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* You can enable this module using this in your config.ini:
 * [plugins]
 * MTrackAuth_BrowserID = ""
 */

class MTrackAuth_BrowserID implements IMTrackAuth, IMTrackNavigationHelper {
  function __construct() {
    MTrackAuth::registerMech($this);
    MTrackNavigation::registerHelper($this);
  }

  function augmentUserInfo(&$content) {
    if (MTrackAuth::whoami() == 'anonymous' && !$this->authenticate()) {
      $content = "<a href='$GLOBALS[ABSWEB]auth/'>Log In</a>";
    }
  }
  function augmentNavigation($id, &$items) {
  }

  function authenticate() {
    if (!strlen(session_id()) && php_sapi_name() != 'cli') {
      session_start();
    }
    if (isset($_SESSION['auth.browserid'])) {
      return $_SESSION['auth.browserid'];
    }
    return null;
  }

  function doAuthenticate($force = false) {
    if ($force) {
      global $ABSWEB;
      header("Location: {$ABSWEB}auth/");
      exit;
    }
    return null;
  }

  function enumGroups() {
    return null;
  }

  function getGroups($username) {
    return null;
  }

  function addToGroup($username, $groupname) {
    return null;
  }

  function removeFromGroup($username, $groupname) {
    return null;
  }

  function getUserData($username) {
    return null;
  }

  function canLogOut() {
    return true;
  }

  function LogOut() {
    if (isset($_COOKIE[session_name()])) {
      if (!session_id()) session_start();
      if (isset($_SESSION['auth.browserid'])) {
        session_destroy();
        header('Location: ' . $GLOBALS['ABSWEB']);
        exit;
      }
    }
  }
}
