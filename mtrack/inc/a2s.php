<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
/* This renders ASCII art diagrams as SVG
 * See: https://9vx.org/~dho/a2s/ */

$p = get_include_path();
set_include_path("$p:" . MTRACK_INC_DIR . '/lib/a2s');
include 'a2s52.php';
set_include_path($p);

class mtrack_a2s {
  static $objects = false;

  static function loadObjsFromWiki()
  {

    if (self::$objects !== false) {
      return self::$objects;
    }

    $tree = MTrackWikiItem::get_wiki_tree();

    if (!isset($tree->a2s)) {
      $objects = null;
      return null;
    }

    $a2sTree = $tree->a2s;

    $objects = array();
    $i = 0;
    foreach ($a2sTree as $name => $val) {
      /* Ignore subdirectories */
      if (is_object($val)) {
        continue;
      }

      $data = MTrackWikiItem::loadByPageName("a2s/$name")->content;
      if (preg_match_all('/<path(?P<contents>[^>]+)>/',
        $data, $matches) > 0) {
          $objects[$i] = array('name' => $name, 'paths' => array());

          foreach ($matches['contents'] as $j => $content) {
            if (preg_match('/width="(\d+)/', $content, $m)) {
              $objects[$i]['paths'][$j]['width'] = $m[1];
            }

            if (preg_match('/height="(\d+)/', $content, $m)) {
              $objects[$i]['paths'][$j]['height'] = $m[1];
            }

            if (preg_match('/d="([^"]+)"/', $content, $m)) {
              $objects[$i]['paths'][$j]['path'] = $m[1];
            }
          }
          $i++;
        }
    }
    self::$objects = $objects;
    return $objects;
  }

  static function loadObjsFromWikiCached()
  {
    return mtrack_cache(array('mtrack_a2s', 'loadObjsFromWiki'), array(), 10);
  }

  static function storeCache($objects)
  {
  }

  /** Interprets text as ASCIIToSVG ascii art diagrams and translates it to the SVG equivalent */
  static function render($name, $content)
  {
    $dir = getcwd();
    $data = join("\n", $content);
    chdir(MTRACK_INC_DIR . '/lib/a2s');
    $o = new A2S_ASCIIToSVG($data);
    $o->setDimensionScale(9, 16);
    $o->parseGrid();
    chdir($dir);
    return $o->render();
  }
}

/*
A2S_CustomObjects::$loadObjsFn = 
  array('mtrack_a2s', 'loadObjsFromWikiCache');
A2S_CustomObjects::$storCacheFn = 
  array('mtrack_a2s', 'storeCache');
 */

MTrackWiki::register_processor('a2s', array('mtrack_a2s', 'render'));



