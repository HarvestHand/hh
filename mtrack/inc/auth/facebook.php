<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* You can enable this module using this in your config.ini:
 * [plugins]
 * MTrackAuth_Facebook = "APPID,APPSECRET"
 */

class MTrackAuth_Facebook implements IMTrackAuth, IMTrackNavigationHelper {
  var $appid;
  var $secret;
  var $fb;
  var $me = null;

  function __construct($appid, $secret) {
    $this->appid = $appid;
    $this->secret = $secret;
    MTrackAuth::registerMech($this);
    MTrackNavigation::registerHelper($this);
  }

  function getFB() {
    if (!$this->fb) {
      set_include_path(MTRACK_INC_DIR . '/lib/facebook:' .
        get_include_path());
      include 'facebook.php';

      $this->fb = new Facebook(array(
        'appId' => $this->appid,
        'secret' => $this->secret
      ));
    }
    return $this->fb;
  }

  function getMe() {
    if ($this->me === null) {
      $this->me = false;

      session_start();

      if (isset($_SESSION['auth.facebook'])) {
        $profile = $_SESSION['auth.facebook'];
        $this->me = $profile;
        return $this->me;
      }

      $fb = $this->getFB();
      $user = $fb->getUser();
      if ($user) {
        try {
          $profile = $fb->api('/me');
          $this->me = $profile;
          $_SESSION['auth.facebook'] = $this->me;
        } catch (Exception $e) {
        }
      }
    }
    return $this->me;
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
    if (isset($_SESSION['auth.facebook.userid'])) {
      return $_SESSION['auth.facebook.userid'];
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
      if (isset($_SESSION['auth.facebook'])) {
        session_destroy();
        $fb = $this->getFB();
        header('Location: ' . $fb->getLogoutUrl(array(
          'next' => $GLOBALS['ABSWEB']
        )));
        exit;
      }
    }
  }
}

