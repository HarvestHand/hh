<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include MTRACK_INC_DIR . '/wiki/trac.php';
include MTRACK_INC_DIR . '/lib/markdown.php';

class MTrackWiki {
  static $macros = array();
  static $processors = array();
  static $wikiContext = null;

  static function rest_render_html($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'PUT', 'POST');
    $text = MTrackAPI::getPayload(true);
    return array('html' => self::format_to_html($text,
      MTrackAPI::getParam('wikiContext')));
  }
  static function rest_render_oneline($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'PUT', 'POST');
    $text = MTrackAPI::getPayload(true);
    return array('html' => self::format_to_oneliner($text));
  }

  static function format_to_html($text, $wikiContext = null) {
    if (MTrackWikiItem::is_content_conflicted($text)) {
      return "<em>The text is conflicted; resolve the conflict by removing the chevrons and pipes that demark the &quot;mine&quot;, &quot;original&quot; and &quot;theirs&quot; sections</em><br><pre>" . htmlentities($text, ENT_QUOTES, 'UTF-8') . "</pre>";
    }
    $origPage = self::$wikiContext;
    self::$wikiContext = $wikiContext;

    if (MTrackConfig::get('core', 'wikisyntax') == 'markdown') {
      $html = mtrack_markdown($text);
    } else {
      $f = new MTrackWikiHTMLFormatter;
      $f->format($text);
      $html = $f->out;
    }

    self::$wikiContext = $origPage;
    return $html;
  }

    /* despite the name, we actually take up to 3 lines
     * as a summary */
  static function format_to_oneliner($text, $limit = 3) {
    $lines = explode("\n", $text);
    if (count($lines) > $limit + 1) {
      $lines = array_slice($lines, 0, $limit);
      $text = join("\n", $lines);
      if (MTrackConfig::get('core', 'wikisyntax') == 'markdown') {
        $text .= "\n<br> __*truncated*__";
      } else {
        $text .= "[[BR]] ''truncated''";
      }
    }

    return self::format_to_html($text);

    if (MTrackConfig::get('core', 'wikisyntax') == 'markdown') {
      $html = mtrack_markdown($text, true);
    } else {
      $f = new MTrackWikiOneLinerFormatter;
      $f->format($text);
      $html = $f->out;
    }

    return $html;
  }

  static function format_to_multiliner($text) {
    $f = new MTrackWikiMultiLinerFormatter;
    $f->format($text);
    return $f->out;
  }

  static function format_wiki_page($name) {
    $d = MTrackWikiItem::loadByPageName($name);
    if ($d) {
      return self::format_to_html($d->content);
    }
    return null;
  }

  static function has_macro($name) {
    return isset(self::$macros[$name]);
  }

  static function run_macro($name, $args) {
    if (is_string($args)) {
      $args = preg_split('/\s*,\s*/', $args);
    }
    if (!is_array($args)) {
      $args = array();
    }
    return call_user_func_array(self::$macros[$name], $args);
  }

  static function register_macro($name, $callback) {
    self::$macros[$name] = $callback;
  }

  static function register_processor($name, $callback) {
    self::$processors[$name] = $callback;
  }

  static function has_processor($name) {
    return isset(self::$processors[$name]);
  }

  static function process_codeblock($text) {
    if (!is_array($text)) {
      $text = explode("\n", $text);
    }
    if (preg_match("/^#!(\S+)$/", $text[0], $M) &&
        self::has_processor($M[1])) {
      array_shift($text);
      return self::run_processor($M[1], $text);
    }
    return "<pre>" .
      htmlspecialchars(join("\n", $text), ENT_COMPAT, 'utf-8') .
      "</pre>";
  }

  static function run_processor($name, $text_lines) {
    if (!is_array($text_lines)) {
      $text_lines = explode("\n", $text_lines);
    }
    if (!self::has_processor($name)) {
      return "<pre>" .
        htmlentities(
          join("\n", $text_lines),
          ENT_COMPAT, 'utf-8') .
        "</pre>";
    }
    return call_user_func(self::$processors[$name], $name, $text_lines);
  }

  static function _doc_comment_cleanup($comment) {
    $comment = preg_replace('!^/\*{2,}\s*!', '', $comment);
    $comment = preg_replace('!\s*\*+/$!', '', $comment);
    return $comment;
  }

  /** Summarizes available macros */
  static function macro_show_macros() {
    $md = <<<MARKDOWN

| Macro                   | Description |
| ----------------------- | ----------- |

MARKDOWN;

    foreach (self::$macros as $name => $func) {
      if (is_array($func)) {
        list($class, $fname) = $func;
      } else if (preg_match("/^(.*)::(.*)$/", $func, $M)) {
        list(, $class, $fname) = $M;
      } else {
        $class = null;
        $fname = $func;
      }
      if ($class) {
        $R = new ReflectionMethod($class, $fname);
      } else {
        $R = new ReflectionFunction($fname);
      }
      $comment = self::_doc_comment_cleanup($R->getDocComment());
      if (!strlen($comment)) {
        $comment = "No docs for $class::$fname";
      }
      $md .= "| $name | $comment |\n";
    }

    return mtrack_markdown($md);
  }

  /** Summarizes available block processors */
  static function macro_show_processors() {
    $md = <<<MARKDOWN

| Processor | Description |
| --------- | ----------- |

MARKDOWN;

    foreach (self::$processors as $name => $func) {
      if (is_array($func)) {
        list($class, $fname) = $func;
      } else if (preg_match("/^(.*)::(.*)$/", $func, $M)) {
        list(, $class, $fname) = $M;
      } else {
        $class = null;
        $fname = $func;
      }
      if ($class) {
        $R = new ReflectionMethod($class, $fname);
      } else {
        $R = new ReflectionFunction($fname);
      }
      $comment = self::_doc_comment_cleanup($R->getDocComment());
      if (!strlen($comment)) {
        $comment = "No docs for $class::$fname";
      }
      $md .= "| $name | $comment |\n";
    }

    return mtrack_markdown($md);
  }

  /** Includes content of named wiki page and renders it as HTML */
  static function macro_IncludeWiki($pagename) {
    return self::format_wiki_page($pagename);
  }

  /** Includes content of named help page and renders it as HTML */
  static function macro_IncludeHelp($pagename) {
    return mtrack_markdown(file_get_contents(
      dirname(__FILE__) . '/../defaults/help/' . basename($pagename) . '.md'));
  }

  /** Renders an inline image. See [help:WikiFormatting#Images] */
  static function macro_image($name) {
    /* Ugh! Hate these special cases, but we only allow two types
     * of attachment bearing objects right now.
     * This is crying out for an actual API for registering object
     * identifiers with the system and a way to query those for the URL.
     */
    if (preg_match(',^(.*)/(\d+)/([^/]+)$,', $name, $M)) {
      // Fully qualified object id
      // [[Image(ticket:4e8bb46ac7e046d7ab312dac00000000/2221/default.png)]]
      $object = $M[1];
      $name = $M[3];
    } elseif (preg_match(',^([a-z]+):([a-fA-F0-9]{32})/([^/]+)$,', $name, $M)){
      // [[Image(ticket:4e8bb46ac7e046d7ab312dac00000000/default.png)]]
      $object = $M[1] . ':' . $M[2];
      $name = $M[3];
    } elseif (preg_match(',^ticket:#?(\d+):([^/]+)$,', $name, $M)){
      // [[Image(ticket:#3:default.png)]]
      // [[Image(ticket:3:default.png)]]
      $T = MTrackIssue::loadByNSIdent($M[1]);
      $object = "ticket:$T->tid";
      $name = $M[2];
    } elseif (preg_match(',^(wiki:.+):([^/]+)$,', $name, $M)){
      // [[Image(wiki:WikiStartHello:photo.JPG)]]
      $object = $M[1];
      $name = $M[2];
    } else {
      $object = MTrackWiki::$wikiContext;
    }

    $args = func_get_args();

    $A = MTrackAttachment::getAttachment($object, $name);
    if (!$A) {
      // Invalid
      return "[[Image(" .
        htmlentities(join(",", $args), ENT_QUOTES, 'utf-8') . ")]]";
    }

    // shift away the name
    array_shift($args);

    $params = array();
    // Scale it down to a reasonable default
    if ($A->width > 500) {
      $params['width'] = 500;
    }
    $params['alt'] = $A->filename;

    $use_link = true;
    foreach ($args as $arg) {
      if (preg_match("/^(\d+)px$/", $arg, $M)) {
        $params['width'] = $M[1];
        continue;
      }
      if (preg_match("/^(.*)\s*=\s*(.*)$/", $arg, $M)) {
        $params[$M[1]] = $M[2];
        continue;
      }
      if ($arg == 'nolink') {
        $use_link = false;
        continue;
      }
      $params['align'] = $arg;
    }
    if (isset($params['width']) && !isset($params['height'])) {
      // Auto-scale
      $scale = $A->width / $params['width'];
      $params['height'] = ceil($A->height / $scale);
    }
    if (!isset($params['width']) && !isset($params['height'])) {
      $params['width'] = $A->width;
      $params['height'] = $A->height;
    }

    $img = "<img src='" . htmlentities($A->url, ENT_QUOTES, 'utf-8') . "'";
    foreach ($params as $k => $v) {
      $img .= " $k='" . htmlentities($v, ENT_QUOTES, 'utf-8') . "'";
    }
    $img .= ">";

    if ($use_link) {
      $img = "<a href='" . htmlentities($A->url, ENT_QUOTES, 'utf-8')
        . "' title='" .
        htmlentities($params['alt'], ENT_QUOTES, 'utf-8') . "'>$img</a>";
    }

    $params['object'] = $object;
    $params['name'] = $name;

    return $img;// . json_encode($params);
  }
  static function macro_comment() {
    return '';
  }

  /** Ignores text and emits an empty block instead; useful for commenting markup */
  static function processor_comment($name, $content) {
    return '';
  }

  /** Interprets text as raw HTML and passes it through unchanged */
  static function processor_html($name, $content) {
    return join("\n", $content);
  }

  /** Interpets text as tabular data output from SQL utilities and renders it as a table */
  static function processor_dataset($name, $content) {
    $res = '<table class="report wiki dataset">';
    while (count($content)) {
      $row = array_shift($content);
      $next_row = array_shift($content);
      $cols = preg_split("/\s*\|\s*/", $row);
      if ($next_row[0] == '-') {
        // it's a header
        $res .= '<thead><tr>';
        foreach ($cols as $c) {
          $res .= "<th>" . htmlentities($c, ENT_QUOTES, 'utf-8') . "</th>\n";
        }
        $res .= "</tr></thead><tbody>";
      } else {
        if (is_string($next_row)) {
          array_unshift($content, $next_row);
        }
        // regular row
        $res .= "<tr>";
        foreach ($cols as $c) {
          $res .= "<td>" . htmlentities($c, ENT_QUOTES, 'utf-8') . "</td>\n";
        }
        $res .= "</tr>\n";
      }
    }
    $res .= "</tbody></table>\n";
    return $res;
  }

  static function resolve_wiki_link(MTrackLink $link)
  {
    $link->class = 'wikilink';
    $link->url = $GLOBALS['ABSWEB'] . 'wiki.php/' . $link->target;
  }

  static function resolve_help_link(MTrackLink $link)
  {
    $link->class = 'wikilink';
    $link->url = $GLOBALS['ABSWEB'] . 'help.php/' . $link->target;
  }
}

function mtrack_markdown($text, $oneline = false) {
  $text = Markdown($text, array('nl2br' => !$oneline));

  if ($oneline) {
    // This is likely to need some improvement!
    $text = preg_replace('{</?[pP]>}', '', $text);
  }

  return $text;
}

/** Interprets text as Markdown and transforms it to HTML */
function mtrack_markdown_processor($name, $content) {
    return mtrack_markdown(join("\n", $content));
}

MTrackWiki::register_processor('markdown', 'mtrack_markdown_processor');


MTrackLink::register('wiki', 'MTrackWiki::resolve_wiki_link');
MTrackLink::register('help', 'MTrackWiki::resolve_help_link');

MTrackAPI::register('/wiki/render/html', 'MTrackWiki::rest_render_html');
MTrackAPI::register('/wiki/render/oneline', 'MTrackWiki::rest_render_oneline');
MTrackWiki::register_macro('ListRegisteredBlockProcessors',
  array('MTrackWiki', 'macro_show_processors'));
MTrackWiki::register_macro('ListRegisteredMacros',
  array('MTrackWiki', 'macro_show_macros'));
MTrackWiki::register_macro('IncludeWikiPage',
  array('MTrackWiki', 'macro_IncludeWiki'));
MTrackWiki::register_macro('IncludeHelpPage',
  array('MTrackWiki', 'macro_IncludeHelp'));
MTrackWiki::register_macro('Comment',
  array('MTrackWiki', 'macro_comment'));
MTrackWiki::register_macro('Image',
  array('MTrackWiki', 'macro_image'));
MTrackWiki::register_processor('comment',
  array('MTrackWiki', 'processor_comment'));
MTrackWiki::register_processor('html',
  array('MTrackWiki', 'processor_html'));
MTrackWiki::register_processor('dataset',
  array('MTrackWiki', 'processor_dataset'));

/*
#error_reporting(E_NOTICE);
$f = new MTrackWikiHTMLFormatter;
$f->format(file_get_contents("WikiFormatting"));
#$f->format("* '''wooot'''\noh '''yeah'''\n\n");
#$f->format(" < wez@php.net http://foo.com/bar [https://baz.com/flib Flib] [/foo Shoe]\n");
/*
$f->format(<<<WIKI
>> foo
> bar

all done
WIKI
);
*/
/*
echo $f->out, "\n";
print_r($f->missing);
echo "\ndone\n";
*/
