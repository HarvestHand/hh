<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include MTRACK_INC_DIR . '/search/lucene.php';
include MTRACK_INC_DIR . '/search/solr.php';

class MTrackSearchResult {
  /** object identifier of result */
  public $objectid;
  /** result ranking; higher is more relevant */
  public $score;
  /** excerpt of matching text */
  public $excerpt;

  /* some implementations may need the caller to provide the context
   * text; the default just returns what is there */
  function getExcerpt($text) {
    return $this->excerpt;
  }
}

interface IMTrackSearchEngine {
  public function setBatchMode();
  public function commit($optimize = false);
  public function remove($object);
  public function add($object, $fields, $replace = false);
  /** returns an array of MTrackSearchResult objects corresponding
   * to matches to the supplied query string */
  public function search($query);
  /** returns true if the engine needs the caller to provide context
   * for highlighting */
  public function highlighterNeedsContext();
}

class MTrackSearchDB {
  static $index = null;
  static $engine = null;

  static function getEngine() {
    if (self::$engine === null) {
      $name = MTrackConfig::get('core', 'search_engine');
      if (!$name) $name = 'MTrackSearchEngineLucene';
      self::$engine = new $name;
    }
    return self::$engine;
  }

  public function highlighterNeedsContext() {
    return self::getEngine()->highlighterNeedsContext();
  }

  /* functions that can perform indexing */
  static $funcs = array();

  static function register_indexer($id, $func)
  {
    self::$funcs[$id] = $func;
  }

  static function index_object($id)
  {
    $key = $id;
    while (strlen($key)) {
      if (isset(self::$funcs[$key])) {
        break;
      }
      $new_key = preg_replace('/:[^:]+$/', '', $key);
      if ($key == $new_key) {
        break;
      }
      $key = $new_key;
    }

    if (isset(self::$funcs[$key])) {
      $func = self::$funcs[$key];
      /* some of the indexing code is verbose; if we're updating
       * inline as part of the requests, we need to turn that off
       * to avoid breaking the page output! */
      if (MTrackConfig::get('core', 'update_search_immediate')) {
        ob_start();
      }
      $ret = call_user_func($func, $id);
      if (MTrackConfig::get('core', 'update_search_immediate')) {
        ob_end_clean();
      }
      return $ret;
    }
    return false;
  }

  static function get() {
    return self::getEngine()->getIdx();
  }

  static function setBatchMode() {
    self::getEngine()->setBatchMode();
  }

  static function commit($optimize = false) {
    self::getEngine()->commit($optimize);
  }

  static function remove($object) {
    self::getEngine()->remove($object);
  }

  static function add($object, $fields, $replace = false) {
    self::getEngine()->add($object, $fields, $replace);
  }

  static function search($query) {
    return self::getEngine()->search($query);
  }

  static function expand_quick_link($q) {
    global $ABSWEB;

    if (preg_match("/^help$/i", $q)) {
      return array("Help on $q", $ABSWEB . 'help.php');
    }
    if (preg_match('/^#([a-zA-Z0-9]+)$/', $q, $M)) {
      /* ticket */
      $t = $M[1];
      $url = $ABSWEB . "ticket.php/$t";
      return array("<a href='$url' class='ticketlink'>Ticket #$t</a>", $url);
    }
    if (preg_match('/^([0-9]+)$/', $q, $M)) {
      $t = $M[1];
      $url = $ABSWEB . "ticket.php/$t";
      return array("<a href='$url' class='ticketlink'>Ticket #$t</a>", $url);
    }
    if (preg_match('/^(?:#?[0-9-]+\s*)+$/', $q)) {
      /* tickets; show a custom query for those */
      $tkts = array();
      foreach (preg_split("/\s+/", $q) as $id) {
        if ($id[0] == '#') $id = substr($id, 1);
        $tkts[] = $id;
      }
      return array("Show ticket list: $q",
        $ABSWEB . "query.php?ticket=" . join('|', $tkts));
    }
    if (preg_match('/^r([a-zA-Z]*\d+)$/', $q, $M)) {
      /* changeset */
      $url = mtrack_changeset_url($M[1]);
      return array("Show changeset $q", $url);
    }
    if (preg_match('/^\[([a-zA-Z]*\d+)\]$/', $q, $M)) {
      /* changeset */
      $url = mtrack_changeset_url($M[1]);
      return array("Show changeset $q", $url);
    }
    if (preg_match('/^\{(\d+)\}$/', $q, $M)) {
      /* report */
      return array("Go to report $q",
        $ABSWEB . "report.php/$M[1]");
    }
    return null;
  }

  static function rest_query_array($method, $uri, $captures) {
    $q = MTrackAPI::getParam('q');
    MTrackAPI::checkAllowed($method, 'GET');

    /* full text.  We hide closed tickets to reduce noise;
     * we're more likely to be searching for active items here */
    $notickets = "$q -status:closed";
    $res = mtrack_cache(array('MTrackSearchDB', '_do_search'),
      array($notickets), 6);

    foreach ($res->results as $idx => $group) {
      if (!MTrackACL::hasAnyRights($group->object, 'read')) {
        unset($res->results[$idx]);
      }
    }

    /* aggregate the ticket search results */
    $res = $res->results;
    $truncated = false;

    if (count($res) > 8) {
      $res = array_slice($res, 0, 8);
      $truncated = true;
    }

    if (preg_match("/^[#0-9 -]+$/", $q)) {
      $t = MTrackAPI::invoke('GET', "/ticket/search/basic", null, array(
        'q' => trim($q)));
      foreach ($t->result as $r) {
        if (count($res) > 8) {
          $truncated = true;
          break;
        }
        $o = new stdclass;
        $o->url = $GLOBALS['ABSWEB'] . "ticket.php/$r->nsident";
        $o->link = "<a class='ticketlink' href='$o->url'>#$r->nsident $r->summary</a>";
        $res[] = $o;
      }
    }

    $quick = self::expand_quick_link($q);
    if ($quick) {
      /* prepend the quick link version */
      $o = new stdclass;
      $o->link = $quick[0];
      $o->url = $quick[1];

      array_unshift($res, $o);
    }


    /* catch all: take them to the main search page.
     * This is here because there are some quick search cases we don't
     * handle here, and they might want to see the help on searching */
    $o = new stdclass;
    $o->link = $truncated ? "<em>More results for $q</em>" :
                "<em>Search for $q</em>";
    $o->url = $GLOBALS['ABSWEB'] . "search.php?q=" . urlencode($q);
    $res[] = $o;
    return $res;
  }

  static function rest_query($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    $q = MTrackAPI::getParam('q');
    $results = mtrack_cache(array('MTrackSearchDB', '_do_search'),
      array($q), 6);

    foreach ($results->results as $idx => $group) {
      if (!MTrackACL::hasAnyRights($group->object, 'read')) {
        unset($results->results[$idx]);
      }
    }

    return $results;
  }

  static function _do_search($q) {
    $start = microtime(true);
    $hits = self::search($q);
    $end = microtime(true);
    $searchTime = $end - $start;

    $start = $end;
    /* aggregate results by canonical object since we index comments
     * separately from the the top level item */
    $by_obj = array();
    $alias = array();
    foreach ($hits as $hit) {
      list($item, $id) = explode(':', $hit->objectid, 3);

      $object = "$item:$id";
      if (isset($alias[$object])) {
        $object = $alias[$object];
      }
      if (!isset($by_obj[$object])) {
        $H = new stdclass;
        $H->maxScore = $hit->score;
        $H->hits = array($hit);
        $H->object = $object;
        $H->aclid = $object;
        $H->type = $item;
        $H->id = $id;

        /* we may have indexed legacy change audit fields, which means
         * that we have to detect aliasing of identifiers (such as
         * milestone:id vs. milestone:name) and return the canonical
         * information via _get_object_info */
        $info = self::_get_object_info($H);
        if (!$info) {
          continue;
        }
        if (!isset($by_obj[$info->object])) {
          /* no entry yet; set it up */
          $H->object = $info->object;
          $H->_info = $info;
          $by_obj[$H->object] = $H;
          continue;
        }
        /* it's an alias */
        $alias[$object] = $info->object;
        $object = $info->object;
      }
      /* associate with existing entry */
      $H = $by_obj[$object];
      $H->hits[] = $hit;
      if ($hit->score > $H->maxScore) {
        $hit->maxScore = $hit->score;
      }
    }
    /* order that in descending score order */
    uasort($by_obj, array('MTrackSearchDB', '_cmp_hit_container'));

    $res = array();
    foreach ($by_obj as $H) {
      $items = array();
      /* we can get runs of hits to the same object with all the same
       * properties; collapse those down to unique hits */
      foreach ($H->hits as $hit) {
        /* if the engine gave us an excerpt, assume it is unique */
        if ($hit->excerpt) {
          $items[] = $hit;
          continue;
        }
        /* otherwise, it is quite likely a duplicate */
        $uniq = "$hit->objectid:$hit->score";
        if (isset($items[$uniq])) {
          continue;
        }
        $items[$uniq] = $hit;
      }
      $H->hits = array();
      $H->object = $H->_info->object;
      $H->url = $H->_info->url;
      $H->link = $H->_info->link;
      foreach ($items as $hit) {
        $H->hits[] = self::_get_hit_info($H, $H->_info->o, $hit);
      }
      unset($H->_info);
      $res[] = $H;
    }
    $end = microtime(true);
    $S = new stdclass;
    $S->searchTime = $searchTime;
    $S->renderTime = $end - $start;
    $S->results = $res;
    $S->query = $q;
    return $S;
  }

  static function _get_hit_info($H, $obj, $hit) {
    $item = new stdclass;
    $item->objectid = $hit->objectid;
    $item->score = $hit->score;

    $context = "";

    switch ($H->type) {
      case 'milestone':
        $context = $obj->description;
        break;
      case 'ticket':
        if (preg_match("/comment:(.*)$/", $hit->objectid, $M)) {
          $comment = $M[1];
          if (MTrackSearchDB::highlighterNeedsContext()) {
            $context = $obj->getComment($comment);
          }
        } else {
          $context = $obj->description;
        }
        break;
      case 'wiki':
        if (MTrackSearchDB::highlighterNeedsContext()) {
          $context = $obj->content;
        }
        break;
    }
    $item->excerpt = $hit->getExcerpt($context);

    return $item;
  }

  static function _get_object_info($H) {
    global $ABSWEB;

    switch ($H->type) {
      case 'milestone':
        static $milestone_name_to_id = null;
        static $milestone_cache = array();
        /* some change audit tables contain milestone:name instead
         * of milestone:id */
        if (!preg_match("/^\d+$/", $H->id)) {
          if ($milestone_name_to_id === null) {
            foreach (MTrackMilestone::enumMilestones() as $mid => $name) {
              $milestone_name_to_id[$name] = $mid;
            }
          }
          $mid = $milestone_name_to_id[$H->id];
        } else {
          $mid = $H->id;
        }

        $M = MTrackMilestone::loadById($mid);
        if (!$M) {
          return null;
        }
        $url = "{$ABSWEB}milestone.php/" .
          str_replace('%2F', '/', urlencode($M->name));
        $name = htmlentities($M->name, ENT_QUOTES, 'utf-8');
        $class = 'milestone';
        if ($M->deleted || $M->completed) {
          $class .= ' completed';
        }
        return (object)array(
          'o' => $M,
          'object' => "milestone:$M->mid",
          'url' => $url,
          'link' => "<span class='$class'><a href='$url'>$name</a></span>",
        );
      case 'ticket':
        $tkt = MTrackIssue::loadById($H->id);
        if (!$tkt) {
          return null;
        }
        $url = "{$ABSWEB}ticket.php/$tkt->nsident";
        return (object)array(
          'o' => $tkt,
          'object' => "ticket:$tkt->tid",
          'url' => $url,
          'link' => mtrack_ticket($tkt, array(
            'display' => "#$tkt->nsident $tkt->summary"
          )),
        );

      case 'wiki':
        $wiki = null;
        if (MTrackSearchDB::highlighterNeedsContext()) {
          $wiki = MTrackWikiItem::loadByPageName($H->id);
          if (!$wiki) {
            return null;
          }
        }
        return (object)array(
          'o' => $wiki,
          'object' => "wiki:$H->id",
          'url' => "{$ABSWEB}wiki.php/$H->id",
          'link' => mtrack_wiki($H->id)
        );
    }
    return null;
  }

  static function _cmp_hit_container($A, $B) {
    return $B->maxScore - $A->maxScore;
  }
}

MTrackAPI::register('/search/query', 'MTrackSearchDB::rest_query');
MTrackAPI::register('/search/query/array', 'MTrackSearchDB::rest_query_array');

