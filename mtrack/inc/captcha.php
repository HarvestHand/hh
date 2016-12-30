<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

interface IMTrackCaptchImplementation {
  /** return the captcha content */
  function emit($form);
  /** check that the captcha is good
   * Returns true/false */
  function check($form);
}

class MTrackCaptcha {
  static $impl = null;

  static function register(IMTrackCaptchImplementation $impl)
  {
    self::$impl = $impl;
  }

  static function emit($form)
  {
    if (self::$impl !== null) {
      return self::$impl->emit($form);
    }
    return '';
  }

  static function check($form)
  {
    if (self::$impl !== null) {
      return self::$impl->check($form);
    }
    return true;
  }
}

class MTrackCaptcha_Recaptcha implements IMTrackCaptchImplementation {
  public $errcode = null;
  public $pub;
  public $priv;
  public $userclass;

  function __construct($pub, $priv, $userclass = 'anonymous|authenticated') {
    $this->pub = $pub;
    $this->priv = $priv;
    $this->userclass = explode("|", $userclass);
    MTrackCaptcha::register($this);
  }

  function emit($form)
  {
    $class = MTrackAuth::getUserClass();
    if (!in_array($class, $this->userclass)) {
      return '';
    }
    $pub = $this->pub;
    $err = $this->errcode === null ? '' : "&error=$this->errcode";
    $http = $_SERVER['HTTPS'] ? 'https' : 'http';
    return <<<HTML
<script type='text/javascript'
  src="$http://www.google.com/recaptcha/api/challenge?k=$pub$err">
</script>
<noscript>
  <iframe src="$http://www.google.com/recaptcha/api/noscript?k=$pub$err"
    height="300" width="500" frameborder="0"></iframe>
  <br/>
  <textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
  <input type="hidden" name="recaptcha_response_field"
    value="manual_challenge"/>
</noscript>
HTML;
  }

  function check($form)
  {
    $class = MTrackAuth::getUserClass();
    if (!in_array($class, $this->userclass)) {
      return true;
    }
    if (empty($_POST['recaptcha_challenge_field']) or
        empty($_POST['recaptcha_response_field'])) {
      return array('false', 'incorrect-captcha-sol');
    }

    $data = http_build_query(array(
          'privatekey' => $this->priv,
          'remoteip' => $_SERVER['REMOTE_ADDR'],
          'challenge' => $_POST['recaptcha_challenge_field'],
          'response' => $_POST['recaptcha_response_field'],
          ));
    $params = array(
        'http' => array(
          'method' => 'POST',
          'content' => $data,
          ),
        );
    $ctx = stream_context_create($params);

    /* first line: true/false
     * second line: error code
     */
    $res = array();
    foreach (file('http://www.google.com/recaptcha/api/verify', 0, $ctx) as $line) {
      $res[] = trim($line);
    }
    if ($res[0] == 'true') {
      return true;
    }
    $this->errcode = $res[1];
    return array(false, $this->errcode);
  }

}

