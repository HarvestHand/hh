<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackSnippet {
  public $snid = null;
  public $description = null;
  public $lang = null;
  public $snippet = null;
  public $created = null;
  public $updated = null;

  static function loadById($id)
  {
    foreach (MTrackDB::q('select snid from snippets where snid = ?', $id)
        ->fetchAll() as $row) {
      return new self($row[0]);
    }
    return null;
  }

  function __construct($id = null)
  {
    if ($id !== null) {
      $this->snid = $id;

      list($row) = MTrackDB::q('select * from snippets where snid = ?', $id)
        ->fetchAll(PDO::FETCH_ASSOC);
      foreach ($row as $k => $v) {
        $this->$k = $v;
      }
    }
  }

  function save(MTrackChangeset $CS)
  {
    $this->updated = $CS->cid;

    if (!strlen(trim($this->snippet))) {
      throw new Exception("Snippet cannot be empty");
    }

    if ($this->snid === null) {
      $this->created = $CS->cid;

      $this->snid = sha1(
        $CS->who . ':' .
        $CS->when . ':' .
        $this->description . ':' .
        $this->lang . ':' .
        $this->snippet);

      MTrackDB::q('insert into snippets
          (snid, created, updated, description, lang, snippet)
          values (?, ?, ?, ?, ?, ?)',
        $this->snid,
        $this->created,
        $this->updated,
        $this->description,
        $this->lang,
        $this->snippet
        );
    } else {
      MTrackDB::q('update snippets set updated = ?,
          description = ?, lang = ?, snippet = ?
          WHERE snid = ?',
        $this->updated,
        $this->description,
        $this->lang,
        $this->snippet,
        $this->snid
        );
    }
  }
}

MTrackACL::registerAncestry('snippet', 'Snippets');

