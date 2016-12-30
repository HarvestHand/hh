<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include_once MTRACK_INC_DIR . '/auth/mtrack.php';
include_once MTRACK_INC_DIR . '/auth/anon.php';
include_once MTRACK_INC_DIR . '/auth/http.php';
include_once MTRACK_INC_DIR . '/auth/openid.php';
include_once MTRACK_INC_DIR . '/auth/browserid.php';
include_once MTRACK_INC_DIR . '/auth/facebook.php';

interface IMTrackAuth {
  /** Returns the authenticated user, or null if authentication is
   * required */
  function authenticate();

  /** Called if the user is not authenticated as a registered
   * user and if the page requires it.
   * Should initiate whatever is appropriate to begin the authentication
   * process (eg: displaying logon information).
   * You may assume that no output has been sent to the client at
   * the time that this function is called.
   * Returns null if not supported, throw an exception if failed,
   * else return a the authenticated user (if it can be determined
   * by the time the function returns).
   * If an alternate login page is displayed, this function should
   * exit instead of returning.
   */
  function doAuthenticate($force = false);

  /** Returns a list of available groups.
   * Returns null if not supported, throw an exception if failed. */
  function enumGroups();

  /** Returns a list of groups that a given user belongs to.
   * Returns null if not supported, throw an exception if failed. */
  function getGroups($username);

  /** Adds a user to a group.
   * Returns null if not supported, throw an exception if failed,
   * return true if succeeded */
  function addToGroup($username, $groupname);

  /** Removes a user from a group.
   * Returns null if not supported, throw an exception if failed,
   * return true if succeeded */
  function removeFromGroup($username, $groupname);

  /** Returns userdata for a given user id
   * Some authentication mechanisms outsource the storage of user data.
   * This function returns null if no additional information is available,
   * or an array containing the following keys:
   *   email - the email address
   *   fullname - the full name
   *   avatar - URL to an avatar image
   */
  function getUserData($username);

  /** Returns true if this mechanism is one that is capable of signing
   * out under application control */
  function canLogOut();

  /** logs the user out */
  function LogOut();
}

class MTrackAuth
{
  static $stack = array();
  static $mechs = array();
  static $group_assoc = array();

  public static function registerMech(IMTrackAuth $mech) {
    self::$mechs[] = $mech;
  }

  /** switch user */
  public static function su($user) {
    if (!strlen($user)) throw new Exception("invalid user");
    array_unshift(self::$stack, $user);
    putenv("MTRACK_LOGNAME=$user");
  }

  /** returns the instance of an auth mechanism given its class name */
  public static function getMech($name) {
    foreach (self::$mechs as $inst) {
      if ($inst instanceof $name) {
        return $inst;
      }
    }
    return null;
  }

  /** drop identity set by last su */
  public static function drop() {
    if (count(self::$stack) == 0) {
      throw new Exception("no privs to drop");
    }
    $user = array_shift(self::$stack);
    putenv("MTRACK_LOGNAME=$user");
    return $user;
  }

  /** returns the authenticated user, or null if authentication
   * is required */
  public static function authenticate() {

    /* admin party trumps all; we need to bypass all auth mechs
     * while we're configuring the system */
    if (!MTrackConfig::get('core', 'admin_party')) {
      foreach (self::$mechs as $mech) {
        $name = $mech->authenticate();
        if ($name !== null) {
          return $name;
        }
      }
    }

    /* always fall back on the unix username when running from
     * the console */
    if (php_sapi_name() == 'cli') {
      static $envs = array('MTRACK_LOGNAME', 'LOGNAME', 'USER');
      foreach ($envs as $name) {
        if (isset($_ENV[$name])) {
          return $_ENV[$name];
        }
      }
    } elseif (MTrackConfig::get('core', 'admin_party') == 1) {
      $party = MTrackConfig::get('core', 'admin_party_remote_address');
      if (in_array($_SERVER['REMOTE_ADDR'], explode(',', $party))) {
        return 'adminparty';
      }
    }

    return null;
  }

  public static function isAuthConfigured() {
    return count(self::$mechs) ? true : false;
  }

  /** determine the current identity.  If doauth is true (default),
   * then the authentication hook will be invoked */
  public static function whoami($doauth = true) {
    if (count(self::$stack) == 0 && $doauth) {
      try {
        $who = self::authenticate();
        if ($who === null && !MTrackConfig::get('core', 'admin_party')) {
          foreach (self::$mechs as $mech) {
            $who = $mech->doAuthenticate();
            if ($who !== null) {
              break;
            }
          }
        }
        if ($who !== null) {
          self::su($who);
        }
      } catch (Exception $e) {
        if (php_sapi_name() != 'cli') {
          header('HTTP/1.0 401 Unauthorized');
          echo "<h1>Not authorized</h1>";
          echo htmlentities($e->getMessage());
        } else {
          echo " ** Not authorized\n\n";
          echo $e->getMessage() . "\n";
        }
        error_log($e->getMessage());
        exit(1);
      }
    }
    if (!count(self::$stack)) {
      return "anonymous";
    }
    return self::$stack[0];
  }

  static function getUserClass($user = null) {
    if ($user === null) {
      $user = self::whoami();
    }
    if (MTrackConfig::get('core', 'admin_party') == 1
        && $user == 'adminparty'
        && in_array($_SERVER['REMOTE_ADDR'], explode(',', MTrackConfig::get('core', 'admin_party_remote_address')))) {
      return 'admin';
    }

    $user_class = MTrackConfig::get('user_classes', $user);
    if ($user_class === null) {
      if ($user == 'anonymous') {
        return 'anonymous';
      }
      return 'authenticated';
    }
    return $user_class;
  }

  static $userdata_cache = array();
  static function getUserData($username) {
    $username = mtrack_canon_username($username);

    if (array_key_exists($username, self::$userdata_cache)) {
      return self::$userdata_cache[$username];
    }
    $data = null;
    foreach (self::$mechs as $mech) {
      $data = $mech->getUserData($username);
      if ($data !== null) {
        break;
      }
    }
    foreach (MTrackDB::q(
          'select fullname, email from userinfo where userid = ?',
          $username)->fetchAll(PDO::FETCH_ASSOC) as $row) {
      if ($data === null) {
        $data = $row;
        break;
      }
      foreach ($row as $k => $v) {
        if (!isset($data[$k]) || empty($data[$k])) {
          $data[$k] = $v;
        }
      }
      break;
    }
    if ($data === null) {
      $data = array(
        'fullname' => $username
      );
    }

    if (!isset($data['email'])) {
      if (preg_match('/<([a-z0-9_.+=-]+@[a-z0-9.-]+)>/', $username, $M)) {
        // username contains an email address
        $data['email'] = $M[1];
      } else if (preg_match('/^([a-z0-9_.+=-]+@[a-z0-9.-]+)$/', $username)) {
        // username is an email address
        $data['email'] = $username;
      } else if (preg_match('/^[a-z0-9_.+=-]+$/', $username)) {
        // valid localpart; assume a domain and construct an email address
        $dom = MTrackConfig::get('core', 'default_email_domain');
        if ($dom !== null) {
          $data['email'] = $username . '@' . $dom;
        }
      }
    }

    self::$userdata_cache[$username] = $data;

    return $data;
  }

  /* enumerates possible groups from the auth plugin layer */
  static function enumGroups() {
    $groups = array();
    foreach (self::$mechs as $mech) {
      $g = $mech->enumGroups();
      if (is_array($g)) {
        foreach ($g as $i => $grp) {
          if (is_integer($i)) {
            $groups[$grp] = $grp;
          } else {
            $groups[$i] = $grp;
          }
        }
      }
    }
    /* merge in our project groups */
    foreach (MTrackDB::q('select project, g.name, p.name from groups g left join projects p on g.project = p.projid')
        as $row) {
      $gid = "project:$row[0]:$row[1]";
      $groups[$gid] = "$row[1] ($row[2])";
    }
    return $groups;
  }

  /* returns groups of which the authenticated user is a member */
  static function getGroups($user = null) {
    if ($user === null) {
      $user = self::whoami();
    }
    $canon = mtrack_canon_username($user);

    if (isset(self::$group_assoc[$user])) {
      return self::$group_assoc[$user];
    }

    $roles = array($canon => $canon);

    $user_class = self::getUserClass($user); // FIXME: $canon?
    $class_roles = MTrackConfig::get('user_class_roles', $user_class);
    foreach (preg_split('/\s*,\s*/', $class_roles) as $role) {
      $roles[$role] = $role;
    }

    foreach (self::$mechs as $mech) {
      $g = $mech->getGroups($user);
      if (is_array($g)) {
        foreach ($g as $i => $grp) {
          if (is_integer($i)) {
            $roles[$grp] = $grp;
          } else {
            $roles[$i] = $grp;
          }
        }
      }
    }
    /* merge in our project group membership */
    foreach (MTrackDB::q('select project, groupname, p.name from group_membership gm left join projects p on gm.project = p.projid where username = ?',
        $canon)->fetchAll() as $row) {
      $gid = "project:$row[0]:$row[1]";
      $roles[$gid] = "$row[1] ($row[2])";
    }

    self::$group_assoc[$user] = $roles;
    return $roles;
  }

  static function forceAuthenticate() {
    try {
      $who = self::authenticate();
      if ($who === null && !MTrackConfig::get('core', 'admin_party')) {
        foreach (self::$mechs as $mech) {
          $who = $mech->doAuthenticate(true);
          if ($who !== null) {
            break;
          }
        }
      }
      if ($who !== null) {
        self::su($who);
      }
    } catch (Exception $e) {
    }
  }

  static function canLogOut() {
    foreach (self::$mechs as $mech) {
      if ($mech->canLogOut()) {
        return true;
      }
    }
    return false;
  }

  static function LogOut() {
    foreach (self::$mechs as $mech) {
      $mech->LogOut();
    }
  }

}

