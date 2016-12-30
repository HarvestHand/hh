<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

/* The mtrack API is built on functions that take the have the following
 * signature:
 *
 * $value = function ($method, $uri, $captures)
 *
 * Where the return value is any JSON representable type.
 * Return the structured data, not the JSON string; the API framework
 * will automatically encode to JSON and capture a checksum to add as a
 * header in the response.
 *
 * Raising errors:
 * Call MTrackAPI::error() to return an error to the client.
 * Alternatively, any exceptions that are not caught are mapped to
 * MTrackAPI::error(), so you may simply throw an exception.
 *
 * Accessing request information:
 * MTrackAPI::getHeader - returns a request header
 * MTrackAPI::setHeader - sets an outgoing header
 * MTrackAPI::setStatus - sets HTTP status code
 * MTrackAPI::getPayload - returns request payload data (transfer-decoded)
 */

interface MTrackAPI_Driver {
  function setStatus($code, $msg = '');
  function error($code, $msg, $extra = null);
  function setHeader($name, $value);
  function getHeader($name);
  function getPayload($stringify = false);
  function getParam($name);
}

class MTrackAPI_Exception extends Exception {
  public $extra;
  public $driver;

  function __construct($message = null, $code = 0, $extra = null)
  {
    parent::__construct($message, $code);
    $this->extra = $extra;
  }

  function getExtra() {
    return $this->extra;
  }

  function setDriver(MTrackAPI_Driver $d) {
    $this->driver = $d;
  }
}

/* NAP: it's a bit like having a REST */
class MTrackAPI_Driver_NAP implements MTrackAPI_Driver {
  public $status;
  public $payload;
  public $result;
  public $headers = array();
  public $params = array();
  public $reqHeaders;

  function __construct($payload, $params, $reqHeaders = null) {
    $this->payload = $payload;
    $this->params = $params;
    $this->reqHeaders = $reqHeaders;
  }

  function getParam($name) {
    if (isset($this->params[$name])) {
      return $this->params[$name];
    }
    return null;
  }

  function setStatus($code, $msg = '') {
    $this->status = $code;
  }

  function error($code, $msg, $extra = null) {
    throw new MTrackAPI_Exception($msg, $code, $extra);
  }

  function getPayload($stringify = false) {
    if ($stringify) {
      return (string)$this->payload;
    }
    return $this->payload;
  }

  function setHeader($name, $value) {
    $this->headers[$name] = $value;
  }

  function getHeader($name) {
    if (isset($this->reqHeaders[$name])) {
      return $this->reqHeaders[$name];
    }
    if ($name == 'Content-Type') {
      return 'application/json';
    }
    return null;
  }
}

class MTrackAPI_Driver_HTTP implements MTrackAPI_Driver {
  function setStatus($code, $msg = '') {
    header("HTTP/1.0 $code $msg");
  }

  function getParam($name) {
    if (isset($_GET[$name])) {
      return $_GET[$name];
    }
    return null;
  }

  function error($code, $msg, $extra = null) {
    header("HTTP/1.0 $code " . preg_replace("/[\r\n]/", '', $msg));
    header('Content-Type: application/json');
    $err = new stdclass;
    $err->code = $code;
    $err->status = "error";
    $err->message = $msg;
    if ($extra !== null) {
      $err->extra = $extra;
    }
    $json = json_encode($err);
    header('Content-MD5: ' . base64_encode(md5($json, true)));
    echo $json;
    exit;
  }

  function getPayload($stringify = false) {
    list($type) = explode(';', $this->getHeader('Content-Type'));
    $data = fopen('php://input', 'rb');
    if ($stringify) {
      return stream_get_contents($data);
    }
    if ($type == 'application/json') {
      return json_decode(stream_get_contents($data));
    }
    return $data;
  }

  function setHeader($name, $value) {
    header("$name: $value");
  }

  function getHeader($name) {
    $name = str_replace('-', '_', strtoupper($name));
    static $noprefix = array(
      'CONTENT_LENGTH' => true,
      'CONTENT_TYPE' => true,
    );
    if (!isset($noprefix[$name])) {
      $name = 'HTTP_' . $name;
    }
    return isset($_SERVER[$name]) ? $_SERVER[$name] : null;
  }
}

class MTrackAPI {
  static $compiled = array();
  static $routes = array();
  static $driver = null;

  static function error($code, $msg, $extra = null) {
    if (!self::$driver) {
      /* we may be invoked before ::run is called to set up the driver */
      self::$driver = new MTrackAPI_Driver_HTTP;
    }
    return self::$driver->error($code, $msg, $extra);
  }

  static function date8601($tstring) {
    $d = date_create($tstring, new DateTimeZone('UTC'));
    if (!is_object($d)) {
      throw new Exception("could not represent $tstring as a datetime object");
    }
    return $d->format(DateTime::W3C);
  }

  static function checkAllowed($method, $allowed) {
    if (!is_array($allowed)) {
      $allowed = func_get_args();
      array_shift($allowed);
    }
    if (!in_array($method, $allowed)) {
      self::error(405, "method not allowed");
    }
  }

  static function invoke($method, $uri, $payload = null, $params = array()) {
    $driver = new MTrackAPI_Driver_NAP($payload, $params);
    try {
      $driver->result = self::run($method, $uri, $driver);
    } catch (MTrackAPI_Exception $e) {
      $e->setDriver($driver);
      throw $e;
    } catch (Exception $e) {
      $de = new MTrackAPI_Exception($e->getMessage(), $e->getCode(), $e);
      $de->setDriver($driver);
      throw $e;
    }
    return $driver;
  }

  static function run($method, $uri, $driver = null) {
    $old_driver = self::$driver;
    self::$driver = $driver;
    try {
      self::compileRoutes();

      $res = self::resolveTarget($uri);
      if (!$res) {
        self::error(404, "not found", array('uri' => $uri));
      }
      list($controller, $captures) = $res;
      if (is_string($controller) &&
          preg_match("/^(.*)::(.*)$/", $controller, $M)) {
        $controller = array($M[1], $M[2]);
      }
      if (!is_callable($controller)) {
        self::error(500, "controller not callable", $controller);
      }

      try {
        $value = call_user_func($controller, $method, $uri, $captures);
      } catch (Exception $e) {
        $code = $e->getCode();
        if (!$code) $code = 500;
        self::error($code, $e->getMessage());
      }
    } catch (Exception $e) {
      self::$driver = $old_driver;
      throw $e;
    }
    self::$driver = $old_driver;
    return $value;
  }

  static function setHeader($name, $value) {
    return self::$driver->setHeader($name, $value);
  }

  static function getParam($name) {
    return self::$driver->getParam($name);
  }

  static function getPayload($stringify = false) {
    return self::$driver->getPayload($stringify);
  }

  static function getHeader($name) {
    return self::$driver->getHeader($name);
  }

  static function resolveTarget($path) {
    /* remove leading and trailing slash */
    $path = trim($path, '/');
    /* collapse repeated slashes into one */
    $path = preg_replace('@/{2,}@', '/', $path);

    foreach (self::$compiled as $route) {
      $c = $route[0];
      $captures = array();
      if (preg_match($route[0], $path, $captures)) {
        foreach ($captures as $k => $v) {
          if (is_int($k)) {
            /* eliminate integer keys */
            unset($captures[$k]);
          } else {
            /* decode encoding after we've done matching (as it is theoretically
             * possible to have a component with an encoded slash) */
            $captures[$k] = urldecode($v);
          }
        }
        return array($route[1], $captures);
      }
    }
    /* no match */
    return null;
  }

  static function compileRoutes() {
    if (count(self::$compiled)) return;

    foreach (self::$routes as $route) {
      list($url, $controller) = $route;
      $path = trim($url, '/');
      $path = preg_replace("/\*([a-zA-Z]+)/", "(?P<\\1>.+)", $path);
      $path = preg_replace("/:([a-zA-Z]+)/", "(?P<\\1>[^/]+)", $path);
      $path = str_replace("/", "\\/", $path);

      self::$compiled[] = array("/^$path$/", $controller);
    }
  }

  /* register an API endpoint.
   * These are relative to the API driver endpoint.
   *
   * "/foo" -> "classname::func"
   *     if the URL matches literal /foo, then use the static function
   *     "func" from class "classname".
   *
   * "/foo/:user" -> "foo"
   *     if the URL matches /foo/name then capture "user" => "name"
   *     and invoke the global "foo" function.
   */
  static function register($url, $controller) {
    self::$routes[] = array($url, $controller);
  }

  /* make a stdclass object from source, copying the public property
   * names and values */
  static function makeObj($src, $idname = null) {
    $res = new stdclass;
    $r = new ReflectionClass($src);
    foreach ($r->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
      if ($prop->isStatic()) continue;
      $name = $prop->getName();
      $lname = $name;
      if ($idname == $name) {
        $lname = 'id';
      }
      $res->$lname = $src->$name;
    }
    return $res;
  }
}

