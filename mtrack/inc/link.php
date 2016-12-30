<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* Handles different types of internal links, and allows new
 * types to be registered and expanded */

interface IMTrackLinkType {
  function resolveLinkToURL(MTrackLink $link);
  function renderHTMLLink(MTrackLink $link);
}

class MTrackLink {
  /* link processors */
  static $types = array();

  /** target of this link */
  public $target;

  /** resolved URL */
  public $url;

  /** type of this link */
  public $type;

  /** displayable label */
  public $label;
  public $label_is_html = false;

  /** css class attribute value(s) */
  public $class;

  /** callback must have this prototype:
   * function(MTrackLink $link)
   * See resolveLinkToURL for details */
  static function register($type, $callable) {
    self::$types[$type] = $callable;
  }

  static function getRegisteredHandler($type) {
    if (!isset(self::$types[$type])) {
      return null;
    }
    $func = self::$types[$type];
    if (!is_string($func)) {
      return $func;
    }
    if ($func[0] == '@') {
      /* '@classname' */
      $cls = substr($func, 1);
      $func = new $cls;
      if (!($func instanceof IMTrackLinkType)) {
        throw new Exception("class $cls is not an IMTrackLinkType");
      }
      self::$types[$type] = $func;
      return $func;
    }

    if (preg_match("/^(.*)::(.*)$/", $func, $M)) {
      $func = array($M[1], $M[2]);
      self::$types[$type] = $func;
    }
    return $func;
  }

  /** update the link object so that its URL is a
   * working URL that maps to the target. For instance,
   * a link of "ticket:1" would get expanded to the HTTP(s)
   * URL of the mtrack instance and ticket processing
   * endpoint: http://myhost.com/ticket.php/1
   */
  function resolveLinkToURL() {
    $func = self::getRegisteredHandler($this->type);
    if ($func) {
      if ($func instanceof IMTrackLinkType) {
        $func->resolveLinkToURL($this);
      } else {
        call_user_func($func, $this);
      }
    }
  }

  static function processHTMLLink($url, $label = null, $label_is_html = false) {
    $link = new MTrackLink($url, $label, $label_is_html);
    return $link->toHTMLLink();
  }

  function __construct($url, $label = null, $label_is_html = false) {
    $this->label = strlen($label) ? $label : null;
    $this->label_is_html = $label_is_html;
    $this->url = $url;

    if ($url[0] == '#') {
      /* ticket id */
      $this->type = 'ticket';
      $this->target = substr($url, 1);
    } elseif (preg_match("/^([a-z]+):(.*)$/", $url, $M)) {
      $this->type = $M[1];
      $this->target = $M[2];
    } else {
      $this->type = 'generic';
      $this->target = $url;
    }

    $this->resolveLinkToURL();
  }

  function toHTMLLink() {
    $func = self::getRegisteredHandler($this->type);
    if ($func && ($func instanceof IMTrackLinkType)) {
      return $func->renderHTMLLink($this);
    }

    if ($this->label === null) {
      $label = $this->target;
    } else {
      $label = $this->label;
    }
    if (!$this->label_is_html) {
      $label = htmlspecialchars($label, ENT_QUOTES, 'utf-8');
    }

    $rel = '';
    if ($this->url[0] != '#' &&
        strncmp($this->url, $GLOBALS['ABSWEB'], strlen($GLOBALS['ABSWEB']))) {
      $rel = " rel=\"external\"";
    }
    $class = '';
    if ($this->class) {
      $class = " class=\"$this->class\"";
    }
    return "<a href=\"$this->url\"$rel$class>$label</a>";
  }
}

