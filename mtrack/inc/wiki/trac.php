<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackWikiParser {

const EMAIL_LOOKALIKE_PATTERN =
"[a-zA-Z0-9.'=+_-]+@(?:[a-zA-Z0-9_-]+\.)+[a-zA-Z](?:[-a-zA-Z\d]*[a-zA-Z\d])?";
const BOLDITALIC_TOKEN = "'''''";
const BOLD_TOKEN = "'''";
const ITALIC_TOKEN = "''";
const UNDERLINE_TOKEN = "__";
const STRIKE_TOKEN = "~~";
const SUBSCRIPT_TOKEN = ",,";
const SUPERSCRIPT_TOKEN = "\^";
const INLINE_TOKEN = "`";
const STARTBLOCK_TOKEN = "\{\{\{";
const STARTBLOCK = "{{{";
const ENDBLOCK_TOKEN = "\}\}\}";
const ENDBLOCK = "}}}";
const LINK_SCHEME = "[\w.+-]+"; # as per RFC 2396
const INTERTRAC_SCHEME = "[a-zA-Z.+-]*?"; # no digits (support for shorthand links)

const QUOTED_STRING = "'[^']+'|\"[^\"]+\"";

const SHREF_TARGET_FIRST = "[\w/?!#@](?<!_)"; # we don't want "_"
const SHREF_TARGET_MIDDLE = "(?:\|(?=[^|\s])|[^|<>\s])";
const SHREF_TARGET_LAST = "[\w/=](?<!_)"; # we don't want "_"

const LHREF_RELATIVE_TARGET = "[/#][^\s\]]*|\.\.?(?:[/#][^\s\]]*)?";

# See http://www.w3.org/TR/REC-xml/#id
const XML_NAME = "[\w:](?<!\d)[\w:.-]*";

const LOWER = '(?<![A-Z0-9_])';
const UPPER = '(?<![a-z0-9_])';

  static $pre_rules = array(
    array("(?P<bolditalic>!?%s)", self::BOLDITALIC_TOKEN),
    array("(?P<bold>!?%s)" , self::BOLD_TOKEN),
    array("(?P<italic>!?%s)" , self::ITALIC_TOKEN),
    array("(?P<underline>!?%s)" , self::UNDERLINE_TOKEN),
    array("(?P<strike>!?%s)" , self::STRIKE_TOKEN),
    array("(?P<subscript>!?%s)" , self::SUBSCRIPT_TOKEN),
    array("(?P<superscript>!?%s)" , self::SUPERSCRIPT_TOKEN),
    array("(?P<inlinecode>!?%s(?P<inline>.*?)%s)" ,
        self::STARTBLOCK_TOKEN, self::ENDBLOCK_TOKEN),
    array("(?P<inlinecode2>!?%s(?P<inline2>.*?)%s)",
        self::INLINE_TOKEN, self::INLINE_TOKEN),
  );
  static $post_rules = array(
    # WikiPageName
    array("(?P<wikipagename>!?(?<!/)\\b\w%s(?:\w%s)+(?:\w%s(?:\w%s)*[\w/]%s)+(?:@\d+)?(?:#%s)?(?=:(?:\Z|\s)|[^:a-zA-Z]|\s|\Z))",
      self::UPPER, self::LOWER, self::UPPER, self::LOWER, self::LOWER, self::XML_NAME),
    # [WikiPageName with label]
    array("(?P<wikipagenamewithlabel>!?\[\w%s(?:\w%s)+(?:\w%s(?:\w%s)*[\w/]%s)+(?:@\d+)?(?:#%s)?(?=:(?:\Z|\s)|[^:a-zA-Z]|\s|\Z)\s+(?:%s|[^\]]+)\])",
      self::UPPER, self::LOWER, self::UPPER, self::LOWER, self::LOWER, self::XML_NAME, self::QUOTED_STRING),

    # [21450] changeset
    "(?P<svnchangeset>!?\[(?:(?:[a-zA-Z]+)?\d+|[a-fA-F0-9]+)\])",
    # #ticket
    "(?P<ticket>!?#(?:(?:[a-zA-Z]+)?\d+|[a-fA-F0-9]+))",
    # {report}
    "(?P<report>!?\{([^}]*)\})",

    # e-mails
    array("(?P<email>!?%s)" , self::EMAIL_LOOKALIKE_PATTERN),
    # > ...
    "(?P<citation>^(?P<cdepth>>(?: *>)*))",
    # &, < and > to &amp;, &lt; and &gt;
    "(?P<htmlspecialcharsape>[&<>])",
    # wiki:TracLinks
    array(
      "(?P<shref>!?((?P<sns>%s):(?P<stgt>%s|%s(?:%s*%s)?)))",
      self::LINK_SCHEME, self::QUOTED_STRING,
      self::SHREF_TARGET_FIRST, self::SHREF_TARGET_MIDDLE,
      self::SHREF_TARGET_LAST),

    # [wiki:TracLinks with optional label] or [/relative label]
    array(
      "(?P<lhref>!?\[(?:(?P<rel>%s)|(?P<lns>%s):(?P<ltgt>%s|[^\]\s]*))(?:\s+(?P<label>%s|[^\]]+))?\])",
      self::LHREF_RELATIVE_TARGET, self::LINK_SCHEME,
      self::QUOTED_STRING, self::QUOTED_STRING),

    # [[macro]] call
    "(?P<macro>!?\[\[(?P<macroname>[\w/+-]+)(\]\]|\((?P<macroargs>.*?)\)\]\]))",
    # == heading == #hanchor
    array(
    "(?P<heading>^\s*(?P<hdepth>=+)\s.*\s(?P=hdepth)\s*(?P<hanchor>#%s)?(?:\s|$))", self::XML_NAME),
    #  * list
    "(?P<list>^(?P<ldepth>\s+)(?:[-*]|\d+\.|[a-zA-Z]\.|[ivxIVX]{1,5}\.) )",
    # definition::
    array(
      "(?P<definition>^\s+((?:%s[^%s]*%s|%s(?:%s{,2}[^%s])*?%s|[^%s%s:]+|:[^:]+)+::)(?:\s+|$))",
      self::INLINE_TOKEN, self::INLINE_TOKEN, self::INLINE_TOKEN,
      self::STARTBLOCK_TOKEN, '}', '}',
      self::ENDBLOCK_TOKEN, self::INLINE_TOKEN, '{'),
    # (leading space)
    "(?P<indent>^(?P<idepth>\s+)(?=\S))",
    # || table ||
    "(?P<last_table_cell>\|\|\s*$)",
    "(?P<table_cell>\|\|)",
  );

    function get_rules() {
      $this->prepare_rules();
      return $this->compiled_rules;
    }

    private function _build_rule(&$rules, $rule_def) {
      foreach ($rule_def as $rule) {
        if (is_array($rule)) {
          $fmt = array_shift($rule);
          $rule = vsprintf($fmt, $rule);
        }
        $rules[] = $rule;
      }
    }

    var $compiled_rules = null;

    function prepare_rules() {
      if ($this->compiled_rules) {
        return $this->compiled_rules;
      }
      $helpers = array();
      $syntax = array();

      $this->_build_rule($syntax, self::$pre_rules);
      $this->_build_rule($syntax, self::$post_rules);

      foreach ($syntax as $rule) {
        if (preg_match_all("/\?P<([a-z\d_]+)>/", $rule, $matches)) {
          $helpers[] = $matches[1][0];
        }
      }
      $this->helper_patterns = $helpers;

      /* now compose it into a big regex */
      $this->compiled_rules = "/" .
        str_replace("/", "\\/", join('|', $syntax)) .
        "/u";
    }
}

class MTrackWikiHTMLFormatter {
  var $parser;
  var $out;
  var $in_table_row;
  var $table_row_count = 0;
  var $open_tags;
  var $list_stack;
  var $quote_stack;
  var $tabstops;
  var $in_code_block;
  var $in_table;
  var $in_def_list;
  var $in_table_cell;
  var $paragraph_open;

  function __construct() {
    $this->parser = new MTrackWikiParser;
  }

  function reset() {
    $this->open_tags = array();
    $this->list_stack = array();
    $this->quote_stack = array();
    $this->tabstops = array();
    $this->in_code_block = 0;
    $this->in_table = false;
    $this->in_def_list = false;
    $this->in_table_cell = false;
    $this->paragraph_open = false;
  }

  function _apply_rules($line) {
    $rules = $this->parser->get_rules();
    /* slightly tricky bit of code here, because preg_replace_callback
     * doesn't seem to support named groups */
    $matches = array();
    if (preg_match_all($rules, $line, $matches, PREG_OFFSET_CAPTURE)) {
      $repl = array();
      foreach ($matches as $key => $info) {
        if (is_string($key)) {
          foreach ($info as $nmatch => $item) {
            if (!is_array($item)) {
              continue;
            }
            $match = $item[0];
            $offset = $item[1];

            if (strlen($match) && $offset >= 0) {
              if ($match[0] == '!') {
                $repl[$offset] = array(null, $match, null);
              } else {
                $func = '_' . $key . '_formatter';
                if (method_exists($this, $func)) {
                  $repl[$offset] = array($func, $match, $nmatch);
                } else {
                  @$this->missing[$func]++;
                }
              }
            }
          }
        }
      }
      if (count($repl)) {
        /* order matches by match offset */
        ksort($repl);
        /* and now we can generate for each fragment */
        $sol = 0;
        foreach ($repl as $offset => $bits) {
          list($func, $match, $nmatch) = $bits;

          if ($offset > $sol) {
            /* emit verbatim */
            //              $this->out .= "Copying from $sol to $offset\n";
            $this->out .= substr($line, $sol, $offset - $sol);
          }

          if ($func === null) {
            $this->out .= htmlspecialchars(substr($match, 1),
                            ENT_COMPAT, 'utf-8');
          } else {
            //              $this->out .= "invoking $func on $match of len " . strlen($match) . "\n";
            //              $this->out .= var_export($matches, true) . "\n";
            $this->$func($match, $matches, $nmatch);
          }

          $sol = $offset + strlen($match);
        }
        $this->out .= substr($line, $sol);
        $result = '';
      } else {
        $result = $line;
      }
    } else {
      $result = $line;
    }
    return $result;
  }

  /** Interprets block as Trac wiki and transforms it to HTML */
  static function trac_processor($name, $lines) {
    $f = new MTrackWikiHTMLFormatter;
    $f->format(join("\n", $lines));
    return $f->out;
  }

  static function format_to_oneliner($text) {
    $f = new MTrackWikiOneLinerFormatter;
    $f->format($text);
    return $f->out;
  }

  function format($text, $escape_newlines = false) {
    $this->out = '';
    $this->reset();
    foreach (preg_split("!\r?\n!", $text) as $line) {
      if ($this->in_code_block || trim($line) == MTrackWikiParser::STARTBLOCK) {
        $this->handle_code_block($line);
        continue;
      }
      if (!strncmp($line, "----", 4)) {
        $this->close_table();
        $this->close_paragraph();
        $this->close_indentation();
        $this->close_list();
        $this->close_def_list();
        $this->out .= "<hr />\n";
        continue;
      }
      if (strlen($line) == 0) {
        $this->close_paragraph();
        $this->close_indentation();
        $this->close_list();
        $this->close_def_list();
        $this->close_table();
        continue;
      }
      if (strncmp($line, "||", 2)) {
        // Doesn't look like a valid table row line, so break any || in the line
        $line = str_replace("||", "|", $line);
      }
      // Tag expansion and clear tabstops if no indent
      $line = str_replace("\t", "        ", $line);
      if ($line[0] != ' ') {
        $this->tabstops = array();
      }

      $this->in_list_item = false;
      $this->in_quote = false;

      $save = $this->out;
      $this->out = '';
      $result = $this->_apply_rules($line);
      $newbit = $this->out;
      $this->out = $save;
      if (!($this->in_list_item || $this->in_def_list
          || $this->in_table || $this->in_quote)) {
        $this->open_paragraph();
      }

      if (!$this->in_list_item) {
        $this->close_list();
      }
      if (!$this->in_quote) {
        $this->close_indentation();
      }
      if ($this->in_def_list && $line[0] != ' ') {
        $this->close_def_list();
      }
      if ($this->in_table && strncmp(ltrim($line), '||', 2)) {
        $this->close_table();
      }
      $this->out .= $newbit;
      $sep = "\n";
      if (!($this->in_list_item || $this->in_def_list || $this->in_table)) {
        if (strlen($result)) {
          $this->open_paragraph();
        }
        if ($escape_newlines && !preg_match(",<br />\s*$,", $line)) {
          $sep = "<br />\n";
        }
      }
      $this->out .= $result . $sep;
      $this->close_table_row();
    }
    $this->close_table();
    $this->close_paragraph();
    $this->close_indentation();
    $this->close_list();
    $this->close_def_list();
    $this->close_code_blocks();
  }

  function _parse_heading($match, $info, $nmatch, $shorten) {
    $match = trim($match);
    $depth = min(strlen($info['hdepth'][$nmatch][0]), 5);
    if (isset($info['hanchor']) && is_array($info['hanchor'])
        && is_array($info['hanchor'][$nmatch])
        && strlen($info['hanchor'][$nmatch][0])) {
      $anchor = $info['hanchor'][$nmatch][0];
    } else {
      $anchor = '';
    }
    $heading_text = substr($match, $depth+1, - $depth - 1 - strlen($anchor));
    $heading = self::format_to_oneliner($heading_text);
    if ($anchor) {
      $anchor = substr($anchor, 1);
    } else {
      $anchor = preg_replace("/[^\w:.-]+/", "", $heading_text);
      if (ctype_digit($anchor[0])) {
        $anchor = 'a' . $anchor;
      }
    }
    return array($depth, $heading, $anchor);
  }

  function _heading_formatter($match, $info, $nmatch) {
    $this->close_table();
    $this->close_paragraph();
    $this->close_indentation();
    $this->close_list();
    $this->close_def_list();
    list($depth, $heading, $anchor) =
      $this->_parse_heading($match, $info, $nmatch, false);

    $this->out .= sprintf('<h%d>%s<a class="wiki" name="%s">&nbsp;</a></h%d>',
      $depth, $heading, $anchor, $depth);
  }

  function tag_open_p($tag) {
    /* do we currently have any open tag with $tag as end-tag? */
    return in_array($tag, $this->open_tags);
  }

  function open_tag($open_tag, $close_tag) {
    $this->open_tags[] = array($open_tag, $close_tag);
  }

  function simple_tag_handler($match, $open_tag, $close_tag) {
    if ($this->tag_open_p(array($open_tag, $close_tag))) {
      $this->out .= $this->close_tag($close_tag);
      return;
    }
    $this->open_tag($open_tag, $close_tag);
    $this->out .= $open_tag;
  }

  function close_tag($tag) {
    $tmp = '';
    /* walk backwards until we find the tag, closing out
     * as we go */
    $keys = array_reverse(array_keys($this->open_tags));
    foreach ($keys as $k) {
      $pair = $this->open_tags[$k];
      $tmp .= $pair[1];
      if ($pair[1] == $tag) {
        unset($this->open_tags[$k]);
        foreach ($this->open_tags as $k2 => $pair) {
          if ($k2 == $k) {
            break;
          }
          $tmp .= $pair[0];
        }
        break;
      }
    }
    return $tmp;
  }

  function _bolditalic_formatter($match, $info) {
    $italic = array('<i>', '</i>');
    $open = $this->tag_open_p($italic);
    $tmp = '';
    if ($open) {
      $this->out .= $italic[1];
      $this->close_tag($italic[1]);
    }
    $this->_bold_formatter($match, $info);
    if (!$open) {
      $this->out .= $italic[0];
      $this->open_tag($italic[0], $italic[1]);
    }
  }

  function _bold_formatter($match, $info) {
    $this->simple_tag_handler($match, '<strong>', '</strong>');
  }
  function _italic_formatter($match, $info) {
    $this->simple_tag_handler($match, '<i>', '</i>');
  }
  function _underline_formatter($match, $info) {
    $this->simple_tag_handler($match,
      '<span class="underline">', '</span>');
  }
  function _strike_formatter($match, $info) {
    $this->simple_tag_handler($match, '<del>', '</del>');
  }
  function _subscript_formatter($match, $info) {
    $this->simple_tag_handler($match, '<sub>', '</sub>');
  }
  function _superscript_formatter($match, $info) {
    $this->simple_tag_handler($match, '<sup>', '</sup>');
  }

  function _email_formatter($match, $info) {
    $this->out .= "<a href=\"mailto:" .
      htmlspecialchars($match, ENT_QUOTES, 'utf-8') .
      "\">" . htmlspecialchars($match, ENT_COMPAT, 'utf-8') . "</a>";
  }

  function _htmlspecialcharsape_formatter($match, $info) {
    $this->out .= htmlspecialchars($match, ENT_QUOTES, 'utf-8');
  }

  function _make_link($ns, $target, $match, $label) {
    global $ABSWEB;
    $is_closed = false;

    if ($label[0] == '"' || $label[0] == "'") {
      $label = substr($label, 1, -1);
    }

    $link = new MTrackLink(strlen($ns) ?
        "$ns:$target" : $target, $label);
    $this->out .= $link->toHTMLLink();
  }

  function _ticket_formatter($match, $info, $nmatch) {
    $ticket = substr($match, 1);
    $this->_make_link('ticket', $ticket, $ticket, $match);
  }

  function _report_formatter($match, $info, $nmatch) {
    $ticket = substr($match, 1, -1);
    $this->_make_link('report', $ticket, $ticket, $match);
  }

  function _svnchangeset_formatter($match, $info, $nmatch) {
    $rev = substr($match, 1, -1);
    $this->_make_link('changeset', $rev, $rev, $match);
  }

  function _wikipagename_formatter($match, $info, $nmatch) {
    $this->_make_link('wiki', $match, $match, $match);
  }

  function _wikipagenamewithlabel_formatter($match, $info, $nmatch) {
    $match = substr($match, 1, -1);
    list($page, $label) = explode(" ", $match, 2);
    $label = $this->_unquote(trim($label));
    $this->_make_link('wiki', $page, $match, $label);
  }

  function _shref_formatter($match, $info, $nmatch) {
    $ns = $info['sns'][$nmatch][0];
    $target = $this->_unquote($info['stgt'][$nmatch][0]);
    $shref = $info['shref'][$nmatch][0];
    $this->_make_link($ns, $target, $match, $match);
  }

  function _lhref_formatter($match, $info, $nmatch) {
    $rel = $info['rel'][$nmatch][0];
    $ns = $info['lns'][$nmatch][0];
    $target = $info['ltgt'][$nmatch][0];
    $label = isset($info['label'][$nmatch][0]) ? $info['label'][$nmatch][0] : '';

//    var_dump($rel, $ns, $target, $label);

    if (!strlen($label)) {
      /* [http://target] or [wiki:target] */
      if (strlen($target)) {
        if (!strncmp($target, "//", 2)) {
          /* for [http://target], label is http://target */
          $label = "$ns:$target";
        } else {
          /* for [wiki:target], label is target */
          $label = $target;
        }
      } else {
        /* [search:] */
        $label = $ns;
      }
    } else {
      $label = $this->_unquote($label);
    }
    if (strlen($rel)) {
      list($path, $query, $frag) = $this->split_link($rel);
      if (!strncmp($path, '//', 2)) {
        $path = '/' . ltrim($path, '/');
      } elseif (!strncmp($path, "/", 1)) {
        $path = $GLOBALS['ABSWEB'] . substr($path, 1);
      }
      $rel = '';
      if (strlen($path) && $path[0] != '#' &&
          strncmp($path, $GLOBALS['ABSWEB'], strlen($GLOBALS['ABSWEB']))) {
        $rel = " rel=\"external\"";
      }
      $target = $path;
      if (strlen($query)) {
        $target .= "?$query";
      }
      if (strlen($frag)) {
        $target .= "#$frag";
      }
      $this->out .= "<a href=\"$target\"$rel>$label</a>";
    } else {
      $this->_make_link($ns, $target, $match, $label);
    }
  }

  function _inlinecode_formatter($match, $info, $nmatch) {
    $this->out .= "<tt>" .
      nl2br(htmlspecialchars($info['inline'][$nmatch][0],
        ENT_COMPAT, 'utf-8')) .
        "</tt>";
  }
  function _inlinecode2_formatter($match, $info, $nmatch) {
    $this->out .= "<tt>" .
      nl2br(htmlspecialchars($info['inline2'][$nmatch][0],
        ENT_COMPAT, 'utf-8')) .
        "</tt>";
  }

  function _macro_formatter($match, $info, $nmatch) {
    $name = $info['macroname'][$nmatch][0];
    if (!strcasecmp($name, 'br')) {
      $this->out .= "<br />";
      return;
    }
    if (MTrackWiki::has_macro($name)) {
      $args = isset($info['macroargs'][$nmatch][0]) ?
          $info['macroargs'][$nmatch][0] : null;
      try {
        $this->out .= MTrackWiki::run_macro($name, $args);
      } catch (Exception $e) {
        error_log($e->getMessage());
        $this->out .= "<tt>Error running: " .
          htmlspecialchars($match, ENT_QUOTES, 'utf-8') . "</tt>";
      }
    } else {
      $this->out .= "<tt>" .
        htmlspecialchars($match, ENT_QUOTES, 'utf-8') . "</tt>";
    }
  }


  function split_link($target) {
    @list($query, $frag) = explode('#', $target, 2);
    @list($target, $query) = explode('?', $query, 2);
    return array($target, $query, $frag);
  }

  function _unquote($text) {
    return preg_replace("/^(['\"])(.*)(\\1)$/", "\\2", $text);
  }

  function close_list() {
    $this->_set_list_depth(0, null, null, null);
  }

  private function _get_list_depth() {
    // Return the space offset associated to the deepest opened list
    if (count($this->list_stack)) {
      $e = end($this->list_stack);
      return $e[1];
    }
    return 0;
  }

  private function _open_list($depth, $new_type, $list_class, $start) {
    $this->close_table();
    $this->close_paragraph();
    $this->close_indentation();
    $this->list_stack[] = array($new_type, $depth);
    $this->_set_tab($depth);
    if ($list_class) {
      $list_class = "wikilist $list_class";
    } else {
      $list_class = "wikilist";
    }
    $class_attr = $list_class ? sprintf(' class="%s"', $list_class) : '';
    $start_attr = $start ? sprintf(' start="%s"', $start) : '';
    $this->out .= "<$new_type$class_attr$start_attr><li>";
  }
  private function _close_list($type) {
    array_pop($this->list_stack);
    $this->out .= "</li></$type>";
  }

  private function _set_list_depth($depth, $new_type, $list_class, $start) {
    if ($depth > $this->_get_list_depth()) {
      $this->_open_list($depth, $new_type, $list_class, $start);
      return;
    }
    while (count($this->list_stack)) {
      list($deepest_type, $deepest_offset) = end($this->list_stack);
      if ($depth >= $deepest_offset) {
        break;
      }
      $this->_close_list($deepest_type);
    }
    if ($depth > 0) {
      if (count($this->list_stack)) {
        list($old_type, $old_offset) = end($this->list_stack);
        if ($new_type && $new_type != $old_type) {
          $this->_close_list($old_type);
          $this->_open_list($depth, $new_type, $list_class, $start);
        } else {
          if ($old_offset != $depth) {
            array_pop($this->list_stack);
            $this->list_stack[] = array($old_type, $depth);
          }
          $this->out .= "</li><li>";
        }
      } else {
        $this->_open_list($depth, $new_type, $list_class, $start);
      }
    }
  }

  function close_indentation() {
    $this->_set_quote_depth(0);
  }

  private function _get_quote_depth() {
    // Return the space offset associated to the deepest opened quote
    if (count($this->quote_stack)) {
      $e = end($this->quote_stack);
      return $e;
    }
    return 0;
  }

  private function _open_one_quote($d, $citation) {
    $this->quote_stack[] = $d;
    $this->_set_tab($d);
    $class_attr = $citation ? ' class="citation"' : '';
    $this->out .= "<blockquote$class_attr>\n";
  }

  private function _open_quote($quote_depth, $depth, $citation) {
    $this->close_table();
    $this->close_paragraph();
    $this->close_list();

    if ($citation) {
      for ($d = $quote_depth + 1; $d < $depth+1; $d++) {
        $this->_open_one_quote($d, $citation);
      }
    } else {
      $this->_open_one_quote($depth, $citation);
    }
  }

  private function _close_quote() {
    $this->close_table();
    $this->close_paragraph();
    array_pop($this->quote_stack);
    $this->out .= "</blockquote>\n";
  }

  private function _set_quote_depth($depth, $citation = false) {
    $quote_depth = $this->_get_quote_depth();
    if ($depth > $quote_depth) {
      $this->_set_tab($depth);
      $tabstops = $this->tabstops;

      while (count($tabstops)) {
        $tab = array_pop($tabstops);
        if ($tab > $quote_depth) {
          $this->_open_quote($quote_depth, $tab, $citation);
        }
      }
    } else {
      while ($this->quote_stack) {
        $deepest_offset = end($this->quote_stack);
        if ($depth >= $deepest_offset) {
          break;
        }
        $this->_close_quote();
      }
      if (!$citation && $depth > 0) {
        if (count($this->quote_stack)) {
          $old_offset = end($this->quote_stack);
          if ($old_offset != $depth) {
            array_pop($this->quote_stack);
            $this->quote_stack[] = $depth;
          }
        } else {
          $this->_open_quote($quote_depth, $depth, $citation);
        }
      }
    }
    if ($depth > 0) {
      $this->in_quote = true;
    }
  }

  function open_paragraph() {
    if (!$this->paragraph_open) {
      $this->out .= "<p>\n";
      $this->paragraph_open = true;
    }
  }

  function close_paragraph() {
    if ($this->paragraph_open) {
      while (count($this->open_tags)) {
        $t = array_pop($this->open_tags);
        $this->out .= $t[1];
      }
      $this->out .= "</p>\n";
      $this->paragraph_open = false;
    }
  }

  function _last_table_cell_formatter($match, $info, $nmatch) {
    return;
  }

  function _table_cell_formatter($match, $info, $nmatch) {
    $this->open_table();
    $this->open_table_row();
    $tag = $this->table_row_count == 1 ? 'th' : 'td';
    if ($this->in_table_cell) {
      $this->out .= "</$tag><$tag>";
      return;
    }
    $this->in_table_cell = 1;
    $this->out .= "<$tag>";
  }


  function open_table() {
    if (!$this->in_table) {
      $this->close_paragraph();
      $this->close_list();
      $this->close_def_list();
      $this->in_table = 1;
      $this->table_row_count = 0;
      $this->out .= "<table class='report wiki'>\n";
    }
  }

  function open_table_row() {
    if (!$this->in_table_row) {
      $this->open_table();
      if ($this->table_row_count == 0) {
        $this->out .= "<thead><tr>";
      } else if ($this->table_row_count == 1) {
        $this->out .= "<tbody><tr>";
      } else {
        $this->out .= "<tr>";
      }
      $this->in_table_row = 1;
      $this->table_row_count++;
    }
  }

  function close_table_row() {
    if ($this->in_table_row) {
      $tag = $this->table_row_count == 1 ? 'th' : 'td';
      $this->in_table_row = 0;
      if ($this->in_table_cell) {
        $this->in_table_cell = 0;
        $this->out .= "</$tag>";
      }
      if ($this->table_row_count == 1) {
        $this->out .= "</tr></thead>";
      } else {
        $this->out .= "</tr>";
      }
    }
  }

  function close_table() {
    if ($this->in_table) {
      $this->close_table_row();
      if ($this->table_row_count == 1) {
        $this->out .= "</thead></table>\n";
      } else {
        $this->out .= "</tbody></table>\n";
      }
      $this->in_table = 0;
    }
  }

  function close_def_list() {
    if ($this->in_def_list) {
      $this->out .= "</dd></dl>\n";
    }
    $this->in_def_list = false;
  }

  function handle_code_block($line) {
    if (trim($line) == MTrackWikiParser::STARTBLOCK) {
      $this->in_code_block++;
      if ($this->in_code_block == 1) {
        $this->code_buf = array();
      } else {
        $this->code_buf[] = $line;
      }
    } elseif (trim($line) == MTrackWikiParser::ENDBLOCK) {
      $this->in_code_block--;
      if ($this->in_code_block == 0) {
        if (preg_match("/^#!(\S+)$/", $this->code_buf[0], $M) &&
            MTrackWiki::has_processor($M[1])) {
          array_shift($this->code_buf);
          $this->out .= MTrackWiki::run_processor($M[1], $this->code_buf);
        } else {
          $this->out .= "<pre>" .
            htmlspecialchars(join("\n", $this->code_buf), ENT_COMPAT, 'utf-8') .
            "</pre>";
        }
      } else {
        $this->code_buf[] = $line;
      }
    } else {
      $this->code_buf[] = $line;
    }
  }

  function close_code_blocks() {
    while ($this->in_code_block) {
      $this->handle_code_block(MTrackWikiParser::ENDBLOCK);
    }
  }

  function _set_tab($depth) {
    /* Append a new tab if needed and truncate tabs deeper than `depth`
      given:       -*-----*--*---*--
      setting:              *
      results in:  -*-----*-*-------
    */
    $tabstops = array();
    foreach ($this->tabstops as $ts) {
      if ($ts >= $depth) {
        break;
      }
      $tabstops[] = $ts;
    }
    $tabstops[] = $depth;
    $this->tabstops = $tabstops;
  }

  function _list_formatter($match, $info, $nmatch) {
    $ldepth = strlen($info['ldepth'][$nmatch][0]);
    $listid = $match[$ldepth];
    $this->in_list_item = true;
    $class = '';
    $start = '';
    if ($listid == '-' || $listid == '*') {
      $type = 'ul';
    } else {
      $type = 'ol';
      switch ($listid) {
        case '1': break;
        case '0': $class = 'arabiczero'; break;
        case 'i': $class = 'lowerroman'; break;
        case 'I': $class = 'upperroman'; break;
        default:
          if (preg_match("/(\d+)\./", substr($match, $ldepth), $d)) {
            $start = (int)$d[1];
          } elseif (ctype_lower($listid)) {
            $class = 'loweralpha';
          } elseif (ctype_upper($listid)) {
            $class = 'upperalpha';
          }
      }
    }
    $this->_set_list_depth($ldepth, $type, $class, $start);
  }

  function _definition_formatter($match, $info, $nmatch) {
    $tmp = $this->in_def_list ? '</dd>' : '<dl class="wikidl">';
    list($def) = explode('::', $match, 2);
    $tmp .= sprintf("<dt>%s</dt><dd>",
      self::format_to_oneliner(trim($def)));
    $this->in_def_list = true;
    $this->out .= $tmp;
  }

  function _indent_formatter($match, $info, $nmatch) {
    $idepth = strlen($info['idepth'][$nmatch][0]);
    if (count($this->list_stack)) {
      list($ltype, $ldepth) = end($this->list_stack);
      if ($idepth < $ldepth) {
        foreach ($this->list_stack as $pair) {
          $ldepth = $pair[1];
          if ($idepth > $ldepth) {
            $this->in_list_item = true;
            $this->_set_list_depth($idepth, null, null, null);
            return;
          }
        }
      } elseif ($idepth <= $ldepth + ($ltype == 'ol' ? 3 : 2)) {
        $this->in_list_item = true;
        return;
      }
    }
    if (!$this->in_def_list) {
      $this->_set_quote_depth($idepth);
    }
  }

  function _citation_formatter($match, $info, $nmatch) {
    $cdepth = strlen(str_replace(' ', '', $info['cdepth'][$nmatch][0]));
    $this->_set_quote_depth($cdepth, true);
  }


}

class MTrackWikiOneLinerFormatter extends MTrackWikiHTMLFormatter {
  function format($text, $escape_newlines = false) {
    if (!strlen($text)) return;
    $this->reset();
    $in_code_block = 0;
    $num = 0;
    foreach (preg_split("!\r?\n!", $text) as $line) {
      if ($num++) $this->out .= ' ';
      $result = '';
      if ($this->in_code_block || trim($line) == MTrackWikiParser::STARTBLOCK) {
        $in_code_block++;
      } elseif (trim($line) == MTrackWikiParser::ENDBLOCK) {
        if ($in_code_block) {
          $in_code_block--;
          if ($in_code_block == 0) {
            $result .= " [...]\n";
          }
        }
      } elseif (!$in_code_block) {
        $result .= "$line\n";
      }

      $result = $this->_apply_rules(rtrim($result, "\r\n"));
      $this->out .= $result;
      $this->close_tag(null);
    }
  }
}

MTrackWiki::register_processor('trac', array(
  'MTrackWikiHTMLFormatter', 'trac_processor'));

