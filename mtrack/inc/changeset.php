<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackChangeset {
  public $cid = null;
  public $who = null;
  public $object = null;
  public $reason = null;
  public $when = null;
  private $count = 0;

  /* used by the import script to allow batching */
  static $use_txn = true;

  /* this is a bit fugly, but allows us to be more intelligent
   * when processing ticket dependency changes :-/ */
  public $tickets = array();
  public $primaryTicket = null;

  static function get($cid) {
    foreach (MTrackDB::q('select * from changes where cid = ?', $cid)
        ->fetchAll() as $row) {
      $CS = new MTrackChangeset;
      $CS->cid = $cid;
      $CS->who = $row['who'];
      $CS->object = $row['object'];
      $CS->reason = $row['reason'];
      $CS->when = $row['changedate'];
      return $CS;
    }
    throw new Exception("invalid changeset id $cid");
  }

  static function begin($object, $reason = '', $when = null) {
    $CS = new MTrackChangeset;

    $db = MTrackDB::get();
    if (self::$use_txn) {
      $db->beginTransaction();
    }

    $CS->who = MTrackAuth::whoami();
    $CS->object = $object;
    $CS->reason = $reason;

    if ($when === null) {
      $CS->when = MTrackDB::unixtime(time());
      $q = MTrackDB::q(
        "INSERT INTO changes (who, object, reason, changedate)
          values (?,?,?,?)",
        $CS->who, $CS->object, $CS->reason, $CS->when);
    } else {
      $CS->when = MTrackDB::unixtime($when);
      $q = MTrackDB::q(
        "INSERT INTO changes (who, object, reason, changedate)
        values (?,?,?,?)",
        $CS->who, $CS->object, $CS->reason, $CS->when);
    }

    $CS->cid = MTrackDB::lastInsertId('changes', 'cid');

    return $CS;
  }

  function commit()
  {
    if ($this->count == 0) {
//      throw new Exception("no changes were made as part of this changeset");
    }
    foreach ($this->tickets as $tkt) {
      $tkt->save($this);
    }
    if (self::$use_txn) {
      $db = MTrackDB::get();
      $db->commit();
    }
    /* if in immediate mode, index the object, but not if it is a wiki page
     * as that is handled in post-commit */
    if (!preg_match("/^wiki:/", $this->object) &&
        MTrackConfig::get('core', 'update_search_immediate')) {
      MTrackSearchDB::index_object($this->object);
    }
  }

  /* loads a ticket and associates it with a changeset.
   * returns true if it was just loaded, false if we already
   * had it associated */
  function loadTicket($tid, &$tkt)
  {
    if ($this->primaryTicket && $this->primaryTicket->tid == $tid) {
      $tkt = $this->primaryTicket;
      return false;
    }
    if (!isset($this->tickets[$tid])) {
      $this->tickets[$tid] = MTrackIssue::loadById($tid);
      $tkt = $this->tickets[$tid];
      return true;
    }
    $tkt = $this->tickets[$tid];
    return false;
  }

  /* explicitly associates a ticket with a changeset */
  function assocTicket(MTrackIssue $tkt) {
    if (!$this->primaryTicket) {
      $this->primaryTicket = $tkt;
    }
  }

  function addentry($fieldname, $action, $old, $value = null)
  {
    MTrackDB::q("INSERT INTO change_audit
      (cid, fieldname, action, oldvalue, value)
      VALUES (?, ?, ?, ?, ?)",
      $this->cid, $fieldname, $action, $old, $value);
    $this->count++;
  }

  function add($fieldname, $old, $new)
  {
    if ($old == $new) {
      return;
    }
    if (!strlen($old)) {
      $this->addentry($fieldname, 'set', $old, $new);
      return;
    }
    if (!strlen($new)) {
      $this->addentry($fieldname, 'deleted', $old, $new);
      return;
    }
    $this->addentry($fieldname, 'changed', $old, $new);
  }

  function setObject($object)
  {
    $this->object = $object;
    MTrackDB::q('update changes set object = ? where cid = ?',
      $this->object, $this->cid);
  }

  function setReason($reason)
  {
    $this->reason = $reason;
    MTrackDB::q('update changes set reason = ? where cid = ?',
      $this->reason, $this->cid);
  }

}
