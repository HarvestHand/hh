<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* mtrack locally maintained authentication */

class MTrackAuth_MTrack implements IMTrackAuth, IMTrackNavigationHelper {
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

  /* If we're running under the REST bits, we may want to use HTTP
   * auth instead of cookies.
   *
   * if we've already got a session, and it is not empty,
   * we assume that the session was started via browser based auth,
   * or by some cookie aware client.
   *
   * Otherwise, we want to use HTTP auth
   */
  function shouldUseHTTPAuth() {
    if (defined('MTRACK_IS_REST_API')) {
      if (isset($_COOKIE[session_name()])) {
        /* client sent us a cookie */
        return false;
      }
      return true;
    }
    return false;
  }

  function authenticate() {
    if ($this->shouldUseHTTPAuth()) {
      if (isset($_SERVER['PHP_AUTH_USER'])) {
        $user = MTrackUser::loadUser($_SERVER['PHP_AUTH_USER'], true);
        if (!$user) {
          return null;
        }
        if ($user->verifyPassword($_SERVER['PHP_AUTH_PW'])) {
          return $user->userid;
        }
      }
      return null;
    } 

    if (!strlen(session_id()) && php_sapi_name() != 'cli') {
      session_start();
    }

    if (isset($_SESSION['auth.mtrack'])) {
      return $_SESSION['auth.mtrack'];
    }
    return null;
  }

  function doAuthenticate($force = false) {
    if (defined('MTRACK_IS_REST_API')) {
      if ($this->shouldUseHTTPAuth()) {
        header("WWW-Authenticate: Basic realm=\"$_SERVER[SERVER_NAME]\"");
        exit;
      }
    }
    if ($force) {
      if ($this->shouldUseHTTPAuth()) {
        header("WWW-Authenticate: Basic realm=\"$_SERVER[SERVER_NAME]\"");
      } else {
        header("Location: $GLOBALS[ABSWEB]auth/");
      }
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
    return true;
  }

  function LogOut() {
    if (isset($_COOKIE[session_name()])) {
      if (!session_id()) session_start();
      if (isset($_SESSION['auth.mtrack'])) {
        session_destroy();
        header('Location: ' . $GLOBALS['ABSWEB']);
        exit;
      }
    }
  }

}

