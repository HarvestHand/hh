<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

/* An API for operating on users defined by the system */

class MTrackUser {
  public $userid;
  public $fullname;
  public $email;
  public $timezone;
  public $active = true;
  public $sshkeys = null;
  public $aliases = null;
  public $prefs = null;
  private $stored = false;

  function __construct() {
    $this->prefs = new stdclass;
  }

  static function loadUser($username, $storedOnly = false) {
    $username = mtrack_canon_username($username);

    $data = MTrackDB::q('select userid, fullname, email, timezone, active, sshkeys, prefs from userinfo where userid = ?', $username)
              ->fetchAll(PDO::FETCH_ASSOC);
    if (!isset($data[0])) {
      if ($storedOnly) {
        return null;
      }
      $udata = MTrackAuth::getUserData($username);
      if (count($udata) == 1 && isset($udata['fullname'])) {
        /* we faked it; not a legitimate entry for our purposes */
        return null;
      }
      $data = array(
        'userid' => $username,
        'active' => true,
        'fullname' => $udata['fullname'],
        'email' => $udata['email'],
        'timezone' => $udata['timezone'],
      );
      $stored = false;
    } else {
      $data = $data[0];
      $stored = true;
    }

    $user = new MTrackUser;
    $user->stored = $stored;
    $user->userid = $data['userid'];
    $user->fullname = $data['fullname'];
    $user->email = $data['email'];
    $user->timezone = $data['timezone'];
    $user->active = $data['active'];
    $user->sshkeys = $data['sshkeys'];
    $user->prefs = $data['prefs'];
    if ($user->prefs) {
      $user->prefs = json_decode($user->prefs);
    } else {
      $user->prefs = new stdclass;
    }

    $user->aliases = MTrackDB::q(<<<SQL
select alias from useraliases where userid = ? order by alias
SQL
      , $user->userid)->fetchAll(PDO::FETCH_COLUMN, 0);

    return $user;
  }

  function save(MTrackChangeset $CS) {
    if ($this->stored) {
      MTrackDB::q('update userinfo set fullname = ?, email = ?, timezone = ?, active = ?, sshkeys = ?, prefs = ? where userid = ?',
        $this->fullname,
        $this->email,
        $this->timezone,
        $this->active ? 1 : 0,
        $this->sshkeys,
        json_encode($this->prefs),
        $this->userid
      );
    } else {
      MTrackDB::q('insert into userinfo (active, fullname, email, timezone, sshkeys, userid, prefs) values (?, ?, ?, ?, ?, ?, ?)',
        $this->active ? 1 : 0,
        $this->fullname,
        $this->email,
        $this->timezone,
        $this->sshkeys,
        $this->userid,
        json_encode($this->prefs)
      );
      $this->stored = true;
    }
    if (MTrackACL::hasAllRights('User', 'modify')) {
      MTrackDB::q('delete from useraliases where userid = ?', $this->userid);
      foreach ($this->aliases as $alias) {
        if (!strlen(trim($alias))) {
          continue;
        }
        MTrackDB::q('insert into useraliases (userid, alias) values (?, ?)',
          $this->userid, $alias);
      }
    }
  }

  function getKeys() {
    $keys = array();
    $lines = preg_split("/\r?\n/", $this->sshkeys);
    foreach ($lines as $line) {
      if (!strlen($line)) continue;
      list($type, $key, $name) = preg_split("/\s+/", $line);
      $keys[$name] = array(
        'id' => $name,
        'key' => "$type $key"
      );
    }
    return $keys;
  }

  function addKey($name, $key) {
    if (!preg_match("/^ssh-\S+\s+(\S+)$/", $key)) {
      throw new Exception("invalid key");
    }

    $keys = $this->getKeys();
    $keys[$name] = array(
      'id' => $name,
      'key' => $key
    );
    $this->updateKeys($keys);
  }

  private function updateKeys($keys) {
    $new = array();
    foreach ($keys as $key) {
      $new[] = "$key[key] $key[id]";
    }
    $this->sshkeys = join("\n", $new);
  }

  function delKey($name) {
    $keys = $this->getKeys();
    unset($keys[$name]);
    $this->updateKeys($keys);
  }

  static function rest_perm_check($method, $user) {
    $me = mtrack_canon_username(MTrackAuth::whoami());
    $user = mtrack_canon_username($user);

    if ($user == $me) {
      /* I can read my data */
      return;
    }

    MTrackACL::requireAllRights('User', $method == 'GET' ? 'read' : 'modify');
  }

  function rest_return_user() {
    $u = MTrackAPI::makeObj($this, 'userid');
    $u->active = (bool)$u->active;
    unset($u->sshkeys);
    $u->role = MTrackAuth::getUserClass($u->id);
    $u->groups = array_values(MTrackAuth::getGroups($this->userid));

    /* ensure that we don't ever return the hash */
    unset($u->pwhash);

    return $u;
  }

  static function rest_user($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET', 'PUT');
    self::rest_perm_check($method, $captures['user']);

    $user = self::loadUser($captures['user']);
    if ($user === null && $method == 'GET') {
      MTrackAPI::error(404, "no such user", $captures);
    }
    if ($user === null) {
      $user = new MTrackUser();
      $user->userid = $captures['user'];
    }
    if ($method == 'PUT') {
      $in = MTrackAPI::getPayload();
      foreach (array('fullname', 'email', 'timezone', 'timezone',
          'active', 'prefs') as $prop) {
        if (!isset($in->$prop)) continue;
        $user->$prop = $in->$prop;
      }
      if (MTrackACL::hasAllRights('User', 'modify')) {
        $user->aliases = $in->aliases;
      }
      $CS = MTrackChangeset::begin("user:$user->userid", "update");
      $user->save($CS);
      $CS->commit();

      if (MTrackACL::hasAllRights('User', 'modify')) {
        $user_class = MTrackAuth::getUserClass($user->userid);
        if (isset($in->role) && $in->role != $user_class) {
          MTrackConfig::set('user_classes', $user->userid, $in->role);
          MTrackConfig::save();
        }
      }
    }
    return $user->rest_return_user();
  }

  /** updates the stored password associated with the user.
   * We use SSHA512 for this */
  function setPassword($password) {
    /* make a salt */
    $salt = '';
    if (function_exists('openssl_random_psuedo_bytes')) {
      $salt = openssl_random_psuedo_bytes(4);
    } else {
      for ($i = 0; $i < 4; $i++) {
        $salt .= chr(mt_rand(0, 255));
      }
    }
    $digest = hash('sha512', $password . $salt, true);
    $pwhash = '{SSHA512}' . base64_encode($digest . $salt);

    MTrackDB::q('update userinfo set pwhash = ? where userid = ?',
      $pwhash, $this->userid);
  }

  /** verifies the provided password against the stored credential
   * information, returning true if the password matches */
  function verifyPassword($password)
  {
    foreach (MTrackDB::q('select pwhash from userinfo where userid = ?',
      $this->userid)->fetchAll(PDO::FETCH_COLUMN, 0) as $pwhash)
    {
      if (preg_match('/^\{([A-Z0-9]+)\}(.*)$/', $pwhash, $M)) {
        $mech = $M[1];
        $hash = $M[2];

        error_log("Loaded mech=$mech hash=$hash");

        switch ($mech) {
          case 'SSHA512':
            $d = base64_decode($hash);
            $salt = substr($d, 64);
            $hash = substr($d, 0, 64);
            return hash('sha512', $password . $salt, true) == $hash;
        }
      }
    }
    error_log("no entry for $this->userid ??");
    return false;
  }

  /** computes a new MD5 hash for the user based on the provided password */
  static function rest_password($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'POST');
    self::rest_perm_check($method, $captures['user']);

    $in = MTrackAPI::getPayload();

    $user = self::loadUser($captures['user']);
    if ($user === null) {
      MTrackACL::requireAllRights('User', 'create');
      $user = new MTrackUser();
      $user->userid = $captures['user'];
      $CS = MTrackChangeset::begin("user:$user->userid",
        "create and set password");
      $user->save($CS);
      $CS->commit();
    }

    $local_auth = MTrackAuth::getMech('MTrackAuth_MTrack');
    if ($local_auth) {
      $user->setPassword($in->password);
      return;
    }

    $http_auth = MTrackAuth::getMech('MTrackAuth_HTTP');
    if ($http_auth && !isset($_SERVER['REMOTE_USER'])) {
      $http_auth->setUserPassword($captures['user'], $in->password);
      return;
    }
    MTrackAPI::error(404, "HTTP authentication not configured");
  }

  static function rest_keys($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    self::rest_perm_check($method, $captures['user']);

    $user = self::loadUser($captures['user']);
    if ($user === null) {
      MTrackAPI::error(404, "no such user", $captures);
    }
    $keys = array();
    foreach ($user->getKeys() as $k) {
      $keys[] = $k;
    }
    return $keys;
  }

  static function rest_velocity($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    self::rest_perm_check($method, $captures['user']);

    return MTrackEBS::getVelocityDataForUser($captures['user']);
  }

  static function rest_predict($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    self::rest_perm_check($method, $captures['user']);

    $mc = MTrackEBS::MonteCarlo($captures['user'], $captures['estimate']);
    $o = new stdclass;
    $o->montecarlo = array();
    $o->best_estimate = 0;
    $o->best_prob = 0;
    foreach ($mc as $est => $prob) {
      $o->best_prob = $prob;
      $o->best_estimate = $est;
      $o->montecarlo[] = array($est, $prob);
    }

    return $o;
  }

  static function rest_key($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET', 'PUT', 'POST', 'DELETE');
    self::rest_perm_check($method, $captures['user']);

    $user = self::loadUser($captures['user']);
    if ($user === null) {
      MTrackAPI::error(404, "no such user", $captures);
    }
    $name = $captures['key'];
    if ($method == 'GET') {
      $keys = $user->getKeys();
      if (isset($keys[$name])) {
        return $keys[$name];
      }
      MTrackAPI::error(404, "no such key", $captures);
    }
    if ($method == 'DELETE') {
      $keys = $user->getKeys();
      if (isset($keys[$name])) {
        $user->delKey($name);
        $CS = MTrackChangeset::begin(
          "user:$user->userid", "delete key $name");
        $user->save($CS);
        $CS->commit();
        return;
      }
      MTrackAPI::error(404, "no such key", $captures);
    }

    $key = MTrackAPI::getPayload();
    if (!is_object($key) || !isset($key->id) || !isset($key->key)) {
      MTrackAPI::error(400, "invalid key", $key);
    }
    $user->addKey($key->id, $key->key);
    $CS = MTrackChangeset::begin("user:$user->userid", "adding key $key->name");
    $user->save($CS);
    $CS->commit();
  }
}

class MTrackUserLink implements IMTrackLinkType {

  function resolveLinkToURL(MTrackLink $link)
  {
    $username = mtrack_canon_username($link->target);
    $link->class = 'userlink';
    $link->url = $GLOBALS['ABSWEB'] . 'user.php/' . $username;
    if ($link->label === null) {
      $link->label = $username;
    }
  }

  function renderHTMLLink(MTrackLink $link)
  {
    $username = mtrack_canon_username($link->target);

    if ($link->label === null) {
      $label = $username;
    } else {
      $label = $link->label;
    }

    if (!$link->label_is_html) {
      $label = htmlspecialchars($label, ENT_QUOTES, 'utf-8');
    }
    $class = '';
    if ($link->class) {
      $class = " class=\"$link->class\"";
    }

    $avatar = mtrack_avatar($username, 24);
    return "<a href=\"$link->url\"$class>$avatar $label</a>";
  }
}

MTrackAPI::register('/user/:user', 'MTrackUser::rest_user');
MTrackAPI::register('/user/:user/keys', 'MTrackUser::rest_keys');
MTrackAPI::register('/user/:user/password', 'MTrackUser::rest_password');
MTrackAPI::register('/user/:user/keys/:key', 'MTrackUser::rest_key');
MTrackAPI::register('/user/:user/ebs/velocity', 'MTrackUser::rest_velocity');
MTrackAPI::register('/user/:user/ebs/predict/:estimate',
  'MTrackUser::rest_predict');
MTrackLink::register('user', '@MTrackUserLink');
