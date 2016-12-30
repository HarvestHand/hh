<?php # vim:ts=2:sw=2:et:
# For copyright and licensing terms, see the file named LICENSE

set_include_path(
  getenv("INCUB_ROOT") . DIRECTORY_SEPARATOR . 'inc' . PATH_SEPARATOR .
  PATH_SEPARATOR .
  get_include_path()
  );

require 'common.php';
require 'Test/More.php';
require 'Test/WebDriver.php';
/*
require 'Test/WebDriver/WebDriverBase.php';
require 'Test/WebDriver/WebDriver.php';
require 'Test/WebDriver/WebDriverContainer.php';
require 'Test/WebDriver/WebDriverSession.php';
require 'Test/WebDriver/WebDriverElement.php';
require 'Test/WebDriver/WebDriverEnvironment.php';
require 'Test/WebDriver/WebDriverExceptions.php';
require 'Test/WebDriver/WebDriverSimpleItem.php';
 */

/* pull in coverage helpers */
function __enable_cov() {
	require getenv("INCUB_ROOT") . "/build/prepend.php";
  $GLOBALS['__INCUB_COV_EXCLUDE'][] = __FILE__;
	register_shutdown_function('__finish_cov');
}

function __finish_cov() {
	require getenv("INCUB_ROOT") . "/build/append.php";
}

$GLOBALS['TEST_SESS'] = array();
function WebDriver($browser = 'firefox', $caps = array()) {
  if (!isset($GLOBALS['TEST_SESS'][$browser])) {
    $GLOBALS['TEST_SESS'][$browser] = new WebDriver($browser, $caps);
  }
  return $GLOBALS['TEST_SESS'][$browser];
}

function tear_down_webdriver() {
  foreach ($GLOBALS['TEST_SESS'] as $d) {
    $d->close();
  }
}
//register_shutdown_function('tear_down_webdriver');

function rest_api_func($method, $url, $params = null,
  $headers = null, $payload = null, $opts = null)
{
  return rest_func($method, INCUB_URL . '/api.php' . $url,
    $params, $headers, $payload, $opts);
}

function rest_func($method, $url, $params = null,
  $headers = null, $payload = null, $extra_opts = null)
{
  $curl = curl_init();

  if ($params === null) {
    $params = array();
  }
  if ($headers === null) {
    $headers = array();
  }

  if (count($params)) {
    if (strpos($url, '?') !== false) {
      $url .= '&';
    } else {
      $url .= '?';
    }
    $url .= http_build_query($params);
  }

  $opts = array(
    CURLOPT_URL => $url,
#    CURLOPT_VERBOSE => true,
    CURLOPT_CONNECTTIMEOUT => 60,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPAUTH => CURLAUTH_ANY,
  );
  if (!strncmp($url, INCUB_URL, strlen(INCUB_URL))) {
    $opts[CURLOPT_USERPWD] = 'admin:admin';
    $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
  }

  if (is_object($payload) || is_array($payload)) {
    $payload = json_encode($payload);
    $headers['Content-Type'] = 'application/json';
  }
  if (is_string($payload) && strlen($payload) > 0) {
    $p = fopen('php://temp', 'r+');
    fwrite($p, $payload);
    rewind($p);
    $payload = $p;
  }
  if (is_resource($payload)) {
    $st = fstat($payload);
    $opts[CURLOPT_UPLOAD] = 1;
    $opts[CURLOPT_INFILESIZE] = $st['size'];
  }

  $h = array();
  foreach ($headers as $k => $v) {
    if (is_string($k)) {
      $h[] = "$k: $v";
    } else {
      $h[] = $v;
    }
  }
  $opts[CURLOPT_HTTPHEADER] = $h;

  /* you need PHP 5.3 to run this test harness */
  $headers = fopen('php://memory', 'r+');
  $body = fopen('php://temp', 'r+');

  $opts[CURLOPT_HEADERFUNCTION] = function ($ch, $data) use ($headers) {
    return fwrite($headers, $data);
  };
  $opts[CURLOPT_WRITEFUNCTION] = function ($ch, $data) use ($body) {
    return fwrite($body, $data);
  };
  $opts[CURLOPT_READFUNCTION] = function ($ch, $fh, $len) use ($payload) {
    $data = fread($payload, $len);
    #diag("> $data");
    return $data;
  };

  if (is_array($extra_opts)) {
    foreach ($extra_opts as $k => $v) {
      $opts[$k] = $v;
    }
  }
  curl_setopt_array($curl, $opts);
  curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  rewind($headers);
  $headers = stream_get_contents($headers);
  rewind($body);

  /* parse the headers */
  $h = array();
  foreach (explode("\r\n", preg_replace("/\r\n[\t ]+/sm", ' ', $headers))
    as $line)
  {
    if (!preg_match("/^([^:]+):\s+(.+)/m", $line, $M)) continue;
    $key = strtolower(trim($M[1]));
    $val = $M[2];
    $h[$key] = $val;
  }
  list($type) = explode(';', $h['content-type']);

  $body = stream_get_contents($body);
  if (isset($h['content-md5'])) {
    if (base64_encode(md5($body, true)) != $h['content-md5']) {
      diag("failed to verify content-md5 hash");
      return array(500, $h, $body);
    }
  }

  if ($type == 'application/json') {
    $obj = json_decode(trim($body));
    if (!$obj) {
      static $json_map = array(
        JSON_ERROR_NONE => 'none',
        JSON_ERROR_DEPTH => 'depth',
        JSON_ERROR_STATE_MISMATCH => 'state-mismatch',
        JSON_ERROR_CTRL_CHAR => 'ctrl-char',
        JSON_ERROR_SYNTAX => 'syntax',
        JSON_ERROR_UTF8 => 'utf-8'
      );

      diag("failed to decode json " . $json_map[json_last_error()]);
      file_put_contents("build/bad.json", $body);
    }
    $body = $obj;
  }

  return array($status, $h, $body);
}

define('INCUB_URL', 'http://admin:admin@' .
  getenv("INCUB_HOSTNAME") . ':' . getenv("INCUB_APACHE_PORT"));

__enable_cov();

