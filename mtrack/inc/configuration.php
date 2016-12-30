<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackConfig {
  static $ini = null;
  static $runtime = array();

  static function getLocation() {
    $location = getenv('MTRACK_CONFIG_FILE');
    if (!strlen($location)) {
      $location = dirname(__FILE__) . '/../config.ini';
    }
    return $location;
  }

  static function getRuntimeConfigPath() {
    /* locate the runtime editable config data */
    $filename = self::_get('core', 'runtime.config');
    if (!$filename) {
      $filename = self::_get('core', 'vardir') . '/runtime.config';
    }
    return $filename;
  }

  static function parseIni() {
    if (self::$ini !== null) {
      return self::$ini;
    }
    $location = self::getLocation();
    self::$ini = @parse_ini_file($location, true);
    if (self::$ini === false) {
      self::$ini = array();
    }

    /* locate the runtime editable config data */
    $filename = self::getRuntimeConfigPath();
    if (file_exists($filename)) {
      $fp = fopen($filename, 'r');
      flock($fp, LOCK_SH);
      self::$runtime = @parse_ini_file($filename, true);
      if (self::$runtime === false) {
        self::$runtime = array();
      }
      flock($fp, LOCK_UN);
      $fp = null;
    }
  }

  static function set($section, $option, $value, $b64 = false) {
    if ($b64) {
      $value = base64_encode($value);
    }
    self::$runtime[$section][$option] = $value;
  }

  static function remove($section, $option) {
    unset(self::$runtime[$section][$option]);
  }

  static function save() {
    $filename = self::getRuntimeConfigPath();
    if (file_exists($filename)) {
      $fp = fopen($filename, 'r+');
    } else {
      $fp = fopen($filename, 'w');
    }
    if (!$fp) {
      throw new Exception("unable to open $filename for writing! check permissions and ownership!");
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    foreach (self::$runtime as $section => $opts) {
      fwrite($fp, "[$section]\n");
      foreach ($opts as $k => $v) {
        switch (gettype($v)) {
          case 'boolean':
            $v = $v ? 'true' : 'false';
            break;
          default:
            $v = '"' . addcslashes($v, "\"\r\n\t") . '"';
        }
        fwrite($fp, "$k = $v\n");
      }
      fwrite($fp, "\n");
    }
    flock($fp, LOCK_UN);
    $fp = null;
  }

  static function get($section, $option, $b64 = false) {
    self::parseIni();
    return self::_get($section, $option, $b64);
  }

  static function _get($section, $option, $b64 = false) {
    if (isset(self::$runtime[$section][$option])) {
      $val = self::$runtime[$section][$option];
    } else if (isset(self::$ini[$section][$option])) {
      $val = self::$ini[$section][$option];
    } else {
      return null;
    }

    if ($b64) {
      return base64_decode($val);
    }

    while (preg_match('/@\{([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)\}/', $val, $M)) {
      $rep = self::_get($M[1], $M[2]);
      $val = str_replace($M[0], $rep, $val);
    }

    return $val;
  }

  static function getSection($section) {
    self::parseIni();
    if (isset(self::$ini[$section])) {
      $S = self::$ini[$section];
    } else {
      $S = null;
    }
    if (isset(self::$runtime[$section])) {
      $R = self::$runtime[$section];
    } else {
      $R = null;
    }
    if ($S && $R) {
      return array_merge($S, $R);
    }
    if ($S) {
      return $S;
    }
    if ($R) {
      return $R;
    }
    return array();
  }

  static function append($section, $option, $value) {
    if (!isset(self::$ini[$section][$option]) || self::$ini[$section][$option] != $value) {
      $location = self::getLocation();
      $data = file_get_contents($location);
      $data .= "\n[$section]\n$option = $value\n";
      file_put_contents($location, $data);
      self::$ini[$section][$option] = $value;
    }
  }

  /* loads plugins */
  static function boot() {
    if (isset($_GLOBALS['MTRACK_CONFIG_SKIP_BOOT'])) {
      return;
    }
    $inc = self::get('core', 'includes');
    if ($inc !== null) {
      foreach (preg_split("/\s*,\s*/", $inc) as $filename) {
        require_once $filename;
      }
    }
    $plugins = self::getSection('plugins');
    if (is_array($plugins)) foreach ($plugins as $classpat => $paramline) {
      $params = preg_split("/\s*,\s*/", $paramline);

      $rcls = new ReflectionClass($classpat);
      $obj = $rcls->newInstanceArgs($params);
    }
  }
}

