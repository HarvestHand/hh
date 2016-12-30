<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */


class WebDriver {
  public $id;
  public $url;

  static $map = array(
    '/timeouts/async_script' => 'POST',
    '/timeouts/implicit_wait' => 'POST',
    '/forward' => 'POST',
    '/back' => 'POST',
    '/refresh' => 'POST',
    '/execute' => 'POST',
    '/execute_async' => 'POST',
    '/frame' => 'POST',
  );

  function request($rel, $payload = null, $method = null) {
    if ($method === null && isset(self::$map[$rel])) {
      $method = self::$map[$rel];
    }
    if ($method === null) {
      $method = 'GET';
    }
    #diag("$method $this->url$rel");
    return rest_func($method, $this->url . $rel, null, null, $payload);
  }

  function boolReq($rel, $payload = null, $method = null) {
    list($st, $h, $body) = $this->request($rel, $payload, $method);
    if ($st >= 200 && $st < 300) {
      return true;
    }
    #diag("$rel -> false.  $st");
    #diag($body);
    return false;
  }

  static function required_for_test() {
    $url = getenv("INCUB_WEBDRIVER");
    if (!strlen($url)) {
      plan(array('skip_all' => "Selenium required for this test, use --selenium to run it"));
    }
  }

  function __construct($browser = 'firefox', $caps = array()) {
    $caps['browserName'] = $browser;
    $this->url = getenv("INCUB_WEBDRIVER");
    if (!strlen($this->url)) {
      $this->url = "http://127.0.0.1:4444/wd/hub";
    }
    list($st, $h, $body) = $this->request('/session', array(
      'desiredCapabilities' => $caps
      ), 'POST');
    if ($st != 302) {
      throw new Exception("failed to start session $st $body");
    }
    $this->url = $h['location'];
    #diag("session URL is $this->url");
  }

  function __destruct() {
    $this->request('', null, 'DELETE');
  }

  function url($url = null) {
    if ($url) {
      return $this->boolReq('/url', array('url' => $url), 'POST');
    }
    list($st, $h, $body) = $this->request('/url');
    if ($st === 200) {
      if (is_object($body)) {
        $body = $body->value;
      }
      return $body;
    }
    throw new Exception("GET /url => $st $body");
  }

  function element($strategy, $value = null) {
    if ($strategy == 'active') {
      list($st, $h, $body) = $this->request('/element/active', null, 'POST');

    } else {
      list($st, $h, $body) = $this->request('/element', array(
        'using' => $strategy,
        'value' => $value
      ), 'POST');
    }
    return new WebDriverElement($this, $body);
  }
  function elements($strategy, $value) {
    list($st, $h, $body) = $this->request('/elements', array(
      'using' => $strategy,
      'value' => $value
    ), 'POST');
    $r = array();
    foreach ($body as $e) {
      $r[] = new WebDriverElement($this, $e);
    }
    return $r;
  }
}

class WebDriverElement {
  public $sess;
  public $id;

  function __construct(WebDriver $sess, $id) {
    $this->sess = $sess;
    if (is_object($id)) {
      $id = $id->value->ELEMENT;
    }
    $this->id = $id;
  }

  function moveto() {
    return $this->sess->boolReq(
      "/moveto", array(
        'element' => $this->id
      ), 'POST');
  }

  function doubleclick() {
    $this->moveto();
    return $this->sess->boolReq(
      "/doubleclick", null, 'POST');
  }
  function click() {
    return $this->sess->boolReq(
      "/element/$this->id/click", null, 'POST');
  }
  function submit() {
    return $this->sess->boolReq(
      "/element/$this->id/submit", null, 'POST');
  }
  
  function text() {
    list($st, $h, $body) = $this->sess->request(
      "/element/$this->id/text", null, 'GET');
    if ($st >= 200 && $st < 300) {
      if (is_object($body)) {
        return $body->value;
      }
      return $body;
    }
    throw new Exception("text: $st $body");
  }

  const BACKSPACE = 0xE003;
  const DEL = 0xE017;
  const TAB = 0xE004;
  const RET = 0xE006;
  const ENTER = 0xE007;
  const ESC = 0xE00C;

  function value($text) {
    $t = array();
    $args = func_get_args();
    foreach ($args as $text) {
      if (is_array($text)) {
        foreach ($text as $v) {
          $t[] = $v;
        }
      } else {
        $t[] = $text;
      }
    }
    foreach ($t as $i => $v) {
      if (is_string($v)) {
        continue;
      }
      $v = sprintf("&#x%x;", $v);
      $t[$i] = html_entity_decode($v, ENT_NOQUOTES, 'utf-8');
    }
    $payload = new stdclass;
    $payload->value = $t;
    return $this->sess->boolReq(
      "/element/$this->id/value", $payload, 'POST');
  }
}

