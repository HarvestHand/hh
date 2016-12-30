<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackAuth_HTTP implements IMTrackAuth {
  public $htgroup = null;
  public $htpasswd = null;
  public $use_digest = false;
  public $realm = 'mtrack';

  function __construct($group = null, $passwd = null) {
    $this->htgroup = $group;
    if ($passwd !== null) {
      if (!strncmp('digest:', $passwd, 7)) {
        $this->use_digest = true;
        $passwd = substr($passwd, 7);
      }
      $this->htpasswd = $passwd;
    }
    MTrackAuth::registerMech($this);
  }

  function parseDigest($string)
  {
    $resp = trim($string);
    $DIG = array();
    while (strlen($resp)) {
      if (!preg_match('/^([a-z-]+)\s*=\s*(.*)$/', $resp, $M)) {
#        error_log("unable to parse $string [$resp]");
        return null;
      }
      $name = $M[1];
      $param = null;

      $rest = $M[2];

      if ($rest[0] == '"' || $rest[0] == "'") {
        $delim = $rest[0];
        $delim_offset = 1;
      } else {
        $delim = ',';
        $delim_offset = 0;
      }
      $len = strlen($rest);
      $i = $delim_offset;
      while ($i < $len) {
        if ($delim != ',' && $rest[$i] == '\\') {
          $i += 2;
          if ($i >= $len) {
#            error_log("unable to parse $string (unterminated quotes)");
            return null;
          }
          continue;
        }
        if ($rest[$i] == $delim) {
          $param = substr($rest, $delim_offset, $i - $delim_offset);
          $resp = substr($rest, $i + 1);
          break;
        }
        $i++;
      }
      if ($param === null && $delim != ',') {
#        error_log("unable to parse $string, unterminated delim $delim");
        return null;
      }
      if ($param === null) {
        $param = $rest;
        $resp = '';
      }
      $DIG[$name] = $param;

      if (preg_match('/^,\s*(.*)$/', $resp, $M)) {
        $resp = $M[1];
      }
      $resp = trim($resp);
    }
    return $DIG;
  }

  /* Leave authentication to the web server configuration */
  function authenticate() {
    /* web server based auth */
    if (isset($_SERVER['REMOTE_USER'])) {
      return $_SERVER['REMOTE_USER'];
    }

    /* PHP based auth */
    if (($this->use_digest && isset($_SERVER['PHP_AUTH_DIGEST'])) ||
        (!$this->use_digest && isset($_SERVER['PHP_AUTH_USER'])))
    {
      /* validate the password */
      if ($this->use_digest) {
        /* parse the digest response */

        $DIG = $this->parseDigest($_SERVER['PHP_AUTH_DIGEST']);

        if ($DIG['realm'] != $this->realm) {
          return null;
        }
        $secret = $this->getSecret();
        global $ABSWEB;
        $domain = $ABSWEB;
        $opaque = sha1($domain . $secret);

        if ($DIG['opaque'] != $opaque) {
          // secret expired
          // Seeing this often? Perhaps you need to set [core]weburl to
          // the canonical root of your mtrack instance?
          // Without that, the $domain used to calculate opaque can vary
          // across resources loaded by a given page load
          error_log("secret expired");
          return null;
        }

        $user = $DIG['username'];

      } else {
        $user = $_SERVER['PHP_AUTH_USER'];
      }

      if (!strlen($user)) {
        return null;
      }

      if ($this->htpasswd === null) {
        error_log("no password file defined, unable to validate $user");
        return null;
      }

      $userline = $this->readPWFile($user);
      if ($userline === null) {
        error_log("no user found in readPWFile");
        return null;
      }
      if ($this->use_digest) {
        // $userline[2] is: md5($user . ":" . $realm . ":" . $pw)
        $expect = $userline[2];
        $uri = md5($_SERVER['REQUEST_METHOD'] . ':' . $DIG['uri']);
        $rawresp = "$expect:$DIG[nonce]:$DIG[nc]:$DIG[cnonce]:$DIG[qop]:$uri";
        $resp = md5($rawresp);
        if ($resp != $DIG['response']) {
          /* invalid */
          return null;
        }
      } else {
        $secret = $userline[1];
        if (crypt($_SERVER['PHP_AUTH_PW'], $secret) != $secret) {
          /* invalid */
          return null;
        }
      }

      return $user;
    }

    return null;
  }

  function readPWFile($user) {
    $fp = fopen($this->htpasswd, 'r');
    if (!$fp) {
      error_log("unable to open password file to validate user $user");
      return null;
    }

    if (!flock($fp, LOCK_SH)) {
      error_log("unable to lock password file to validate user $user");
      return null;
    }

    $puser = preg_quote($user);

    while (true) {
      $line = fgets($fp);
      if ($line === false) {
        $user = false;
        break;
      }

      if ($this->use_digest) {
        if (preg_match("/^$puser:(.*):(.*)$/", $line, $M)) {
          if ($M[1] != $this->realm) {
            continue;
          }
          flock($fp, LOCK_UN);
          $fp = null;
          return array($user, $this->realm, $M[2]);
        }
        continue;
      }

      if (preg_match("/^$puser\s*:\s*(\S+)/", $line, $M)) {
        flock($fp, LOCK_UN);
        $fp = null;
        return array($user, $M[1]);
      }
    }
    flock($fp, LOCK_UN);
    $fp = null;
    return null;
  }

  function getSecret() {
    $secret_file = MTrackConfig::get('core', 'vardir') . '/.digest.secret';
    $duration = MTrackConfig::get('core', 'digest_auth_duration');
    if (!$duration) $duration = 86400;
    if (file_exists($secret_file)) {
      if (filemtime($secret_file) + $duration > time()) {
        $res = file_get_contents($secret_file);
        if ($res === false || !strlen($res)) {
          error_log(
            "Unable to read HTTP secret for mtrack; logins will likely fail");
        }
        return $res;
      }
      unlink($secret_file);
    }
    $secret = uniqid();
    if (!file_put_contents($secret_file, $secret)) {
      error_log(
        "Unable to write HTTP secret for mtrack; logins will likely fail");
    }
    return $secret;
  }

  function doAuthenticate($force = false) {
    /* This is only triggered if the web server isn't configured
     * to handle auth itself */

    $realm = $this->realm;

    if ($this->use_digest) {
      $secret = $this->getSecret();
      $nonce = sha1(uniqid() . $secret);
      global $ABSWEB;
      $domain = $ABSWEB;
      $opaque = sha1($domain . $secret);
      header("WWW-Authenticate: Digest realm=\"$realm\",qop=\"auth\",nonce=\"$nonce\",opaque=\"$opaque\"");
    } else {
      header("WWW-Authenticate: Basic realm=\"$realm\"");
    }
    header('HTTP/1.0 401 Unauthorized');

    if (defined('MTRACK_IS_REST_API')) {
      MTrackAPI::error(401, "Authentication Required");
    }
?>
<h1>Authentication Required</h1>

<p>I need to know who you are to allow you to access to this site.</p>
<?php
    exit;
  }

  protected function readGroupFile($filename) {
    if (!file_exists($filename)) return null;
    $fp = fopen($filename, 'r');
    if (!$fp) return null;
    if (!flock($fp, LOCK_SH)) return null;

    /* an apache style group file */
    $groups = array();
    $users = array();

    while (true) {
      $line = fgets($fp);
      if ($line === false) {
        break;
      }
      $line = trim($line);
      if ($line[0] == '#') {
        continue;
      }
      if (preg_match('/^([a-zA-Z][a-zA-Z0-9_]+)\s*:\s*(.*)$/', $line,
            $M)) {
        $groupname = $M[1];
        $members = $M[2];
        foreach (preg_split('/\s+/', $members) as $user) {
          $users[$user][] = $groupname;
          $groups[$groupname][] = $user;
        }
      }
    }

    flock($fp, LOCK_UN);
    $fp = null;
    return array($groups, $users);
  }

  function enumGroups() {
    if (strlen($this->htgroup)) {
      list($groups, $users) = $this->readGroupFile($this->htgroup);
      if (is_array($groups)) {
        return array_keys($groups);
      }
    }
    return null;
  }

  function getGroups($username) {
    if (strlen($this->htgroup)) {
      list($groups, $users) = $this->readGroupFile($this->htgroup);
      return $users[$username];
    }
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
    $line = $this->readPWFile($username);
    if ($line) {
      return array(
        'email' => null,
        'fullname' => $username
      );
    }
    return null;
  }

  /** a bit of a hack; this helper enables the HTTP password to be set
   * by the user admin screen */
  function setUserPassword($username, $password) {
    if (!$this->use_digest) {
      throw new Exception("not supported");
    }
    $pwline = "mtrack:" .
      md5("$username:mtrack:" . $password);
    $fp = @fopen($this->htpasswd, 'r+');
    if (!$fp && !file_exists($this->htpasswd)) {
      $fp = fopen($this->htpasswd, 'w');
    }
    if (!$fp) {
      throw new Exception("failed to write to $this->htpasswd");
    }
    flock($fp, LOCK_EX);
    $lines = array();
    while (($line = fgets($fp)) !== false) {
      $bits = explode(':', $line, 2);
      if (count($bits) >= 2) {
        $lines[$bits[0]] = $bits[1];
      }
    }
    $lines[$username] = $pwline;
    fseek($fp, 0);
    ftruncate($fp, 0);
    foreach ($lines as $user => $rest) {
      fwrite($fp, "$user:$rest\n");
    }
    flock($fp, LOCK_UN);
    $fp = null;
  }

  function canLogOut() {
    return false;
  }

  function LogOut() {
  }
}

