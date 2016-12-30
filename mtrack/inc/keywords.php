<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackKeyword {
  public $kid;
  public $keyword;

  static function loadByWord($word)
  {
    foreach (MTrackDB::q('select kid from keywords where keyword = ?', $word)
        ->fetchAll() as $row) {
      return new MTrackKeyword($row[0]);
    }
    return null;
  }
  static function loadById($id)
  {
    foreach (MTrackDB::q('select kid from keywords where kid = ?', $id)
        ->fetchAll() as $row) {
      return new MTrackKeyword($row[0]);
    }
    return null;
  }

  function __construct($id = null)
  {
    if ($id !== null) {
      list($row) = MTrackDB::q('select keyword from keywords where kid = ?',
          $id)->fetchAll();
      $this->kid = $id;
      $this->keyword = $row[0];
      return;
    }
  }

  function save(MTrackChangeset $CS)
  {
    if ($this->kid === null) {
      MTrackDB::q('insert into keywords (keyword) values (?)', $this->keyword);
      $this->kid = MTrackDB::lastInsertId('keywords', 'kid');
      $CS->add("keywords:keyword", null, $this->keyword);
    } else {
      throw new Exception("not allowed to rename keywords");
    }
  }

  static function enumerate() {
    $res = array();
    foreach (MTrackDB::q('select kid, keyword from keywords')->fetchAll() as
        $row) {
      $res[$row[0]] = $row[1];
    }
    return $res;
  }
  static function rest_keyword_list($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    $q = MTrackAPI::getParam('q');
    $list = self::enumerate();
    $res = array();
    foreach ($list as $id => $kw) {
      if ($q) {
        if (stripos($kw, $q) === false) {
          continue;
        }
      }
      $o = new stdclass;
      $o->id = $id;
      $o->label = $kw;
      $res[] = $o;
    }
    return $res;
  }

  static function resolve_link(MTrackLink $link)
  {
    $link->class = 'keyword';
    $link->url = $GLOBALS['ABSWEB'] . 'search.php?q=keyword%3A' . $link->target;
  }
}

MTrackAPI::register('/keywords', 'MTrackKeyword::rest_keyword_list');
MTrackLink::register('keyword', 'MTrackKeyword::resolve_link');
