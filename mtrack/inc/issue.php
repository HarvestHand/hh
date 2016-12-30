<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackEnumeration {
  public $tablename;
  protected $fieldname;
  protected $fieldvalue;

  public $name = null;
  public $value = null;
  public $deleted = null;
  public $new = true;

  function enumerate($all = false) {
    $res = array();
    if ($all) {
      foreach (MTrackDB::q(sprintf("select %s, %s, deleted from %s order by %s",
            $this->fieldname, $this->fieldvalue, $this->tablename,
            $this->fieldvalue))
            ->fetchAll(PDO::FETCH_NUM)
          as $row) {
        $res[$row[0]] = array(
          'name' => $row[0],
          'value' => $row[1],
          'deleted' => $row[2] == '1' ? true : false
        );
      }
    } else {
      foreach (MTrackDB::q(sprintf("select %s from %s where deleted != '1'",
              $this->fieldname, $this->tablename))->fetchAll(PDO::FETCH_NUM)
          as $row) {
        $res[$row[0]] = $row[0];
      }
    }
    return $res;
  }

  function __construct($name = null) {
    if ($name !== null) {
      $rows = MTrackDB::q(sprintf(
          "select %s, deleted from %s where %s = ?",
          $this->fieldvalue, $this->tablename, $this->fieldname),
          $name)
          ->fetchAll();
      if (isset($rows[0])) {
        $this->name = $name;
        $this->value = $rows[0][0];
        $this->deleted = $rows[0][1];
        $this->new = false;
      } else {
        throw new Exception("unable to find $this->tablename with name = $name");
	  }
    } else {
      $this->deleted = false;
	}
  }

  function save(MTrackChangeset $CS) {
    if ($this->new) {
      MTrackDB::q(sprintf('insert into %s (%s, %s, deleted) values (?, ?, ?)',
        $this->tablename, $this->fieldname, $this->fieldvalue),
            $this->name, $this->value, (int)$this->deleted);
      $old = null;
    } else {
      list($row) = MTrackDB::q(
        sprintf('select %s, deleted from %s where %s = ?',
          $this->fieldname, $this->tablename, $this->fieldvalue),
          $this->name)->fetchAll();
      $old = $row[0];
      MTrackDB::q(sprintf('update %s set %s = ?, deleted = ? where %s = ?',
        $this->tablename, $this->fieldvalue, $this->fieldname),
            $this->value, (int)$this->deleted, $this->name);
    }
    $CS->add($this->tablename . ":" . $this->name . ":" . $this->fieldvalue,
      $old, $this->value);

  }

  /* new-or-list */
  function do_rest_list($method, $uri, $captures)
  {
    MTrackAPI::checkAllowed($method, 'GET');
    MTrackACL::requireAllRights('Enumerations', 'read');

    $res = array();
    foreach ($this->enumerate(true) as $item) {
      $o = new stdclass;
      $o->id = $item['name'];
      $o->value = $item['value'];
      $o->deleted = $item['deleted'];
      $res[] = $o;
    }
    return $res;
  }

  function do_rest_item($method, $uri, $captures)
  {
    MTrackAPI::checkAllowed($method, 'GET', 'PUT');

    $id = $captures['s'];
    $cls = get_class($this);

    try {
      $E = new $cls($id);
    } catch (Exception $e) {
      if ($method == 'GET') {
        MTrackAPI::error(404, "no such such item", $id);
      }
      /* we're creating */
      MTrackACL::requireAllRights('Enumerations', 'create');
      $E = new $cls;
    }

    if ($method == 'PUT') {
      MTrackACL::requireAllRights('Enumerations', 'modify');
      $in = MTrackAPI::getPayload();
      if (!is_object($in)) {
        MTrackAPI::error(400, "expected json payload");
      }

      if ($E->name) {
        $CS = MTrackChangeset::begin("enum:$this->tablename:$in->id",
          "Edit $this->tablename $in->name");
      } else {
        $CS = MTrackChangeset::begin("enum:$this->tablename:$in->id",
            "Added $this->tablename $in->name");
      }

      if (isset($in->id)) {
        $newname = trim($in->id);
        if (!strlen($newname)) {
          throw new Exception("invalid or missing id");
        }
        if ($E->name && $newname != $E->name) {
          /* would need to modify all kinds of data in the tables
           * in a way that is not visible to the change audit
           * tables */
          throw new Exception("renaming is not currently supported");
        }
        $E->name = $newname;
      }

      if (isset($in->value)) {
        $E->value = $in->value;
      }
      if (isset($in->deleted)) {
        $E->deleted = $in->deleted;
      }

      $E->save($CS);
      $CS->commit();
    }
    MTrackACL::requireAllRights("Enumerations", 'read');

    $o = new stdclass;
    $o->id = $E->name;
    $o->value = $E->value;
    $o->deleted = $E->deleted;

    return $o;
  }
}

class MTrackTicketState extends MTrackEnumeration {
  public $tablename = 'ticketstates';
  protected $fieldname = 'statename';
  protected $fieldvalue = 'ordinal';

  static function rest_list($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_list($method, $uri, $captures);
  }
  static function rest_item($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_item($method, $uri, $captures);
  }

  static function loadByName($name) {
    return new MTrackTicketState($name);
  }

  /* some older installations don't have any rows populated in ticketstates;
   * synthesize some */
  function enumerate($all = false) {
    $res = parent::enumerate($all);
    if (!count($res)) {
      $defs = array('new', 'open', 'closed', 'reopened');
      foreach ($defs as $v => $name) {
        if ($all) {
          $res[$name] = array(
            'name' => $name,
            'value' => $v,
            'deleted' => false
          );
        } else {
          $res[$name] = $name;
        }
      }
    }
    return $res;
  }
}


class MTrackPriority extends MTrackEnumeration {
  public $tablename = 'priorities';
  protected $fieldname = 'priorityname';
  protected $fieldvalue = 'value';

  static function rest_list($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_list($method, $uri, $captures);
  }
  static function rest_item($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_item($method, $uri, $captures);
  }

  static function loadByName($name) {
    return new MTrackPriority($name);
  }
}

class MTrackSeverity extends MTrackEnumeration {
  public $tablename = 'severities';
  protected $fieldname = 'sevname';
  protected $fieldvalue = 'ordinal';

  static function rest_list($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_list($method, $uri, $captures);
  }
  static function rest_item($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_item($method, $uri, $captures);
  }


  static function loadByName($name) {
    return new MTrackSeverity($name);
  }
}

class MTrackResolution extends MTrackEnumeration {
  public $tablename = 'resolutions';
  protected $fieldname = 'resname';
  protected $fieldvalue = 'ordinal';

  static function rest_list($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_list($method, $uri, $captures);
  }
  static function rest_item($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_item($method, $uri, $captures);
  }


  static function loadByName($name) {
    return new MTrackResolution($name);
  }
}

class MTrackClassification extends MTrackEnumeration {
  public $tablename = 'classifications';
  protected $fieldname = 'classname';
  protected $fieldvalue = 'ordinal';

  static function rest_list($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_list($method, $uri, $captures);
  }
  static function rest_item($method, $uri, $captures) {
    $o = new self;
    return $o->do_rest_item($method, $uri, $captures);
  }


  static function loadByName($name) {
    return new MTrackClassification($name);
  }
}

class MTrackComponent {
  public $compid = null;
  public $name = null;
  public $deleted = null;
  protected $projects = null;
  protected $origprojects = null;

  static function loadById($id) {
    return new MTrackComponent($id);
  }

  static function enumerate($deleted = false) {
    if ($deleted) {
      $query = <<<SQL
select c.compid, c.name, p.name from
components c left join components_by_project cbp on (c.compid = cbp.compid)
left join projects p on (cbp.projid = p.projid)
order by c.name
SQL;
    } else {
      $query = <<<SQL
select c.compid, c.name, p.name from
components c left join components_by_project cbp on (c.compid = cbp.compid)
left join projects p on (cbp.projid = p.projid)
where deleted <> 1 order by c.name
SQL;
    }
    $res = array();
    foreach (MTrackDB::q($query)->fetchAll(PDO::FETCH_NUM) as $row) {
      $C = new MTrackComponent();
      $C->compid = $row[0];
      $C->name = $row[1];
      $C->deleted = $row[2];
      $res[$C->compid] = $C;
    }
    return $res;
  }

  static function loadByName($name) {
    $rows = MTrackDB::q('select compid from components where name = ?',
      $name)->fetchAll(PDO::FETCH_COLUMN, 0);
    if (isset($rows[0])) {
      return self::loadById($rows[0]);
    }
    return null;
  }

  function __construct($id = null) {
    if ($id !== null) {
      list($row) = MTrackDB::q(
                    'select name, deleted from components where compid = ?',
                    $id)->fetchAll();
      if (isset($row[0])) {
        $this->compid = $id;
        $this->name = $row[0];
        $this->deleted = $row[1];
        return;
      }
      throw new Exception("unable to find component with id = $id");
    }
    $this->deleted = false;
  }

  function getProjects() {
    if ($this->origprojects === null) {
      $this->origprojects = array();
      foreach (MTrackDB::q('select projid from components_by_project where compid = ? order by projid', $this->compid) as $row) {
        $this->origprojects[] = $row[0];
      }
      $this->projects = $this->origprojects;
    }
    return $this->projects;
  }

  function setProjects($projlist) {
    $this->projects = $projlist;
  }

  function save(MTrackChangeset $CS) {
    if ($this->compid) {
      list($row) = MTrackDB::q(
                    'select name, deleted from components where compid = ?',
                    $this->compid)->fetchAll();
      $old = $row;
      MTrackDB::q(
          'update components set name = ?, deleted = ? where compid = ?',
          $this->name, (int)$this->deleted, $this->compid);
    } else {
      MTrackDB::q('insert into components (name, deleted) values (?, ?)',
        $this->name, (int)$this->deleted);
      $this->compid = MTrackDB::lastInsertId('components', 'compid');
      $old = null;
    }
    $CS->add("component:" . $this->compid . ":name", $old['name'], $this->name);
    $CS->add("component:" . $this->compid . ":deleted", $old['deleted'], $this->deleted);
    if ($this->projects !== $this->origprojects) {
      $old = is_array($this->origprojects) ?
              join(",", $this->origprojects) : '';
      $new = is_array($this->projects) ?
              join(",", $this->projects) : '';
      MTrackDB::q('delete from components_by_project where compid = ?',
          $this->compid);
      if (is_array($this->projects)) {
        foreach ($this->projects as $pid) {
          MTrackDB::q(
            'insert into components_by_project (compid, projid) values (?, ?)',
            $this->compid, $pid);
        }
      }
      $CS->add("component:$this->compid:projects", $old, $new);
    }
  }

  static function resolve_link(MTrackLink $link) {
    /* not actually a real link, just a way to style
     * "component:name". :-/
     * FIXME: fix CSS for span bits, similar to milestone, or just remove?? */
    $link->class = 'component';
    $link->url = '#';
  }
}

class MTrackProject {
  public $projid = null;
  public $ordinal = 5;
  public $name = null;
  public $shortname = null;
  public $notifyemail = null;

  static function loadById($id) {
    return new MTrackProject($id);
  }

  static function loadByName($name) {
    list($row) = MTrackDB::q('select projid from projects where shortname = ?',
      $name)->fetchAll();
    if (isset($row[0])) {
      return self::loadById($row[0]);
    }
    return null;
  }

  function __construct($id = null) {
    if ($id !== null) {
      list($row) = MTrackDB::q(
                    'select * from projects where projid = ?',
                    $id)->fetchAll();
      if (isset($row[0])) {
        $this->projid = $row['projid'];
        $this->ordinal = $row['ordinal'];
        $this->name = $row['name'];
        $this->shortname = $row['shortname'];
        $this->notifyemail = $row['notifyemail'];
        return;
      }
      throw new Exception("unable to find project with id = $id");
    }
  }

  function save(MTrackChangeset $CS) {
    if ($this->projid) {
      list($row) = MTrackDB::q(
                    'select * from projects where projid = ?',
                    $this->projid)->fetchAll();
      $old = $row;
      MTrackDB::q(
          'update projects set ordinal = ?, name = ?, shortname = ?,
            notifyemail = ? where projid = ?',
          $this->ordinal, $this->name, $this->shortname,
          $this->notifyemail, $this->projid);
    } else {
      MTrackDB::q('insert into projects (ordinal, name,
          shortname, notifyemail) values (?, ?, ?, ?)',
        $this->ordinal, $this->name, $this->shortname,
        $this->notifyemail);
      $this->projid = MTrackDB::lastInsertId('projects', 'projid');
      $old = null;
    }
    $CS->add("project:" . $this->projid . ":name", $old['name'], $this->name);
    $CS->add("project:" . $this->projid . ":ordinal", $old['ordinal'], $this->ordinal);
    $CS->add("project:" . $this->projid . ":shortname", $old['shortname'], $this->shortname);
    $CS->add("project:" . $this->projid . ":notifyemail", $old['notifyemail'], $this->notifyemail);
  }

  function _adjust_ticket_link($M) {
    $tktlimit = MTrackConfig::get('trac_import', "max_ticket:$this->shortname");
    if ($M[1] <= $tktlimit) {
      return "#$this->shortname$M[1]";
    }
    return $M[0];
  }

  function adjust_links($reason, $use_ticket_prefix)
  {
    if (!$use_ticket_prefix) {
      return $reason;
    }

    $tktlimit = MTrackConfig::get('trac_import', "max_ticket:$this->shortname");
    if ($tktlimit !== null) {
      $reason = preg_replace_callback('/#(\d+)/',
        array($this, '_adjust_ticket_link'), $reason);
    } else {
//      don't do this if the number is outside the valid ranges
//      may need to be clever about this during trac imports
//      $reason = preg_replace('/#(\d+)/', "#$this->shortname\$1", $reason);
    }
// FIXME: this and the above need to be more intelligent
    $reason = preg_replace('/\[(\d+)\]/', "[$this->shortname\$1]", $reason);
    return $reason;
  }

  function getGroups() {
    $res = new stdclass;
    foreach (MTrackDB::q(<<<SQL
select g.name as groupname, m.username as user
from groups g
  left join group_membership m on
  (g.name = m.groupname AND g.project = m.project)
where g.project = ?
SQL
        , $this->projid)->fetchAll(PDO::FETCH_OBJ) as $ent) {
      if (!isset($res->{$ent->groupname})) {
        $res->{$ent->groupname} = array();
      }
      if ($ent->user) {
        $res->{$ent->groupname}[] = $ent->user;
      }
    }
    return $res;
  }

  static function rest_project_apply($in, MTrackProject $P, MTrackChangeset $CS)
  {
    error_log(json_encode($in));
    if (isset($in->name)) {
      $P->name = trim($in->name);
    }
    if (isset($in->shortname)) {
      $P->shortname = trim($in->shortname);
    }
    if (isset($in->ordinal)) {
      $P->ordinal = (int)$in->ordinal;
    }
    if (isset($in->notifyemail)) {
      $P->notifyemail = $in->notifyemail;
    }
    if (isset($in->perms) && isset($in->perms->acl)) {
      MTrackACL::setACL("project:$P->projid", 0, $in->perms->acl);
    }
    if (isset($in->groups) && $P->projid) {
      MTrackDB::q('delete from group_membership where project = ?', $P->projid);
      MTrackDB::q('delete from groups where project = ?', $P->projid);

      foreach ($in->groups as $grpname => $users) {
        MTrackDB::q('insert into groups (name, project) values (?, ?)',
          $grpname, $P->projid);
        if (is_array($users)) {
          foreach ($users as $user) {
            MTrackDB::q('insert into group_membership (groupname, project, username) values (?, ?, ?)',
              $grpname, $P->projid, $user);
          }
        }
      }

    }

    // TODO: component association
  }

  static function rest_project_return(MTrackProject $P)
  {
    $p = MTrackAPI::makeObj($P, 'projid');

    // TODO: component association

    if (MTrackACL::hasAnyRights("project:$P->projid", 'modify')) {
      $p->perms = MTrackACL::computeACLObject("project:$P->projid");
    }

    $p->groups = $P->getGroups();

    return $p;
  }

  static function rest_project_new($method, $uri, $captures)
  {
    MTrackAPI::checkAllowed($method, 'GET', 'POST');
    if ($method == 'GET') {
      MTrackACL::requireAllRights('Projects', 'read');

      $res = array();
      foreach (MTrackDB::q(
          'select projid as id from projects order by ordinal')
          ->fetchAll(PDO::FETCH_COLUMN, 0) as $pid) {
        $P = self::loadById($pid);
        $res[] = self::rest_project_return($P);
      }
      return $res;
    }
    MTrackACL::requireAllRights('Projects', 'create');

    $in = MTrackAPI::getPayload();

    $CS = MTrackChangeset::begin("project:X", "Added project $in->name");
    $P = new MTrackProject;
    self::rest_project_apply($in, $P, $CS);

    $P->save($CS);
    $CS->setObject("project:$P->projid");
    $CS->commit();

    return self::rest_project_return($P);
  }

  static function rest_project($method, $uri, $captures)
  {
    MTrackAPI::checkAllowed($method, 'GET', 'PUT');

    $pid = $captures['p'];
    if (preg_match('/^\d+$/', $pid)) {
      $P = self::loadById($pid);
    } else {
      $P = self::loadByName($pid);
    }
    if (!$P) {
      MTrackAPI::error(404, "no such project", $pid);
    }
    if ($method == 'PUT') {
      MTrackACL::requireAllRights("project:$P->projid", 'modify');
      $in = MTrackAPI::getPayload();
      if (!is_object($in)) {
        MTrackAPI::error(400, "expected json payload");
      }
      if (isset($in->shortname) && $in->shortname != $P->shortname) {
        MTrackAPI::error(400, "cannot change shortname");
      }
      $CS = MTrackChangeset::begin("project:$P->projid",
            "Edit project $in->name");
      self::rest_project_apply($in, $P, $CS);
      $P->save($CS);
      $CS->commit();
    }
    MTrackACL::requireAllRights("project:$P->projid", 'read');
    return self::rest_project_return($P);
  }
}

/* The listener protocol is to return true if all is good,
 * or to return either a string or an array of strings that
 * detail why a change is not allowed to proceed */
interface IMTrackIssueListener {
  function vetoMilestone(MTrackIssue $issue,
            MTrackMilestone $ms, $assoc = true);
  function vetoKeyword(MTrackIssue $issue,
            MTrackKeyword $kw, $assoc = true);
  function vetoComponent(MTrackIssue $issue,
            MTrackComponent $comp, $assoc = true);
  function vetoProject(MTrackIssue $issue,
            MTrackProject $proj, $assoc = true);
  function vetoComment(MTrackIssue $issue, $comment);
  function vetoSave(MTrackIssue $issue, $oldFields);

  function augmentFormFields(MTrackIssue $issue, &$fieldset);
  /* return the number of fields that changed */
  function applyPOSTData(MTrackIssue $issue, $data);
  function augmentSaveParams(MTrackIssue $issue, &$params);
  function augmentIndexerFields(MTrackIssue $issue, &$idx);
}
interface IMTrackIssueListener2 extends IMTrackIssueListener {
  function vetoDependency(MTrackIssue $issue,
    MTrackIssue $dep, $assoc = true);
}
interface IMTrackIssueListener3 extends IMTrackIssueListener {
  function augmentLoadedIssue(MTrackIssue $issue);
}

class MTrackVetoException extends Exception {
  public $reasons;

  function __construct($reasons) {
    $this->reasons = $reasons;
    parent::__construct(join("\n", $reasons));
  }
}

class MTrackIssue {
  public $tid = null;
  public $nsident = null;
  public $summary = null;
  public $description = null;
  public $created = null;
  public $updated = null;
  public $owner = null;
  public $priority = null;
  public $severity = null;
  public $classification = null;
  public $resolution = null;
  public $status = null;
  public $estimated = null;
  public $spent = null;
  public $changelog = null;
  public $cc = null;
  public $ptid = null;
  protected $components = null;
  protected $origcomponents = null;
  protected $milestones = null;
  protected $origmilestones = null;
  protected $comments_to_add = array();
  protected $keywords = null;
  protected $origkeywords = null;
  protected $effort = array();
  protected $dependencies = null;
  protected $origdependencies = null;
  protected $blocks = null;
  protected $origblocks = null;
  protected $children = null;
  protected $origchildren = null;
  public $hasChildren = false;

  static $_listeners = array();

  static function loadById($id) {
    try {
      return new MTrackIssue($id);
    } catch (Exception $e) {
    }
    return null;
  }

  static function loadByNSIdent($id) {
    static $cache = array();
    if ($id[0] == '#') {
      $id = substr($id, 1);
    }
    if (!isset($cache[$id])) {
      $ids = MTrackDB::q('select tid from tickets where nsident = ?', $id)
            ->fetchAll(PDO::FETCH_COLUMN, 0);
      if (count($ids) == 1) {
        $cache[$id] = $ids[0];
      } else {
        return null;
      }
    }
    return new MTrackIssue($cache[$id]);
  }

  static function registerListener(IMTrackIssueListener $l)
  {
    self::$_listeners[] = $l;
  }

  function __construct($tid = null) {
    if ($tid === null) {
      $this->components = array();
      $this->origcomponents = array();
      $this->milestones = array();
      $this->origmilestones = array();
      $this->keywords = array();
      $this->origkeywords = array();
      $this->dependencies = array();
      $this->origdependencies = array();
      $this->blocks = array();
      $this->origblocks = array();
      $this->status = 'new';
      $this->origchildren = array();
      $this->children = array();
      $this->hasChildren = false;

      foreach (array('classification', 'severity', 'priority') as $f) {
        $this->$f = MTrackConfig::get('ticket', "default.$f");
      }
      $me = MTrackAuth::whoami();
      if ($me != 'anonymous' && MTrackAuth::getUserClass() != 'anonymous') {
        // Add me (the reporter) to the Cc list
        $this->cc = $me;
      }
    } else {
      $data =  MTrackDB::q('select *, (select count(tid) from tickets where ptid = ?) as hasChildren from tickets where tid = ?',
                        $tid, $tid)->fetchAll(PDO::FETCH_ASSOC);
      if (isset($data[0])) {
        $row = $data[0];
      } else {
        $row = null;
      }
      if (!is_array($row)) {
        throw new Exception("no such issue $tid");
      }
      foreach ($row as $k => $v) {
        $this->$k = $v;
      }
      foreach (self::$_listeners as $l) {
        if ($l instanceof IMTrackIssueListener3) {
          $l->augmentLoadedIssue($this);
        }
      }
    }
  }

  function applyPOSTData($data) {
    $changes = 0;
    foreach (self::$_listeners as $l) {
      $changes += $l->applyPOSTData($this, $data);
    }
    return $changes;
  }

  function augmentFormFields(&$FIELDSET) {
    foreach (self::$_listeners as $l) {
      $l->augmentFormFields($this, $FIELDSET);
    }
  }
  function augmentIndexerFields(&$idx) {
    foreach (self::$_listeners as $l) {
      $l->augmentIndexerFields($this, $idx);
    }
  }
  function augmentSaveParams(&$params) {
    foreach (self::$_listeners as $l) {
      $l->augmentSaveParams($this, $params);
    }
  }

  function checkVeto()
  {
    $args = func_get_args();
    $method = array_shift($args);
    $veto = array();

    foreach (self::$_listeners as $l) {
      if ($method == 'vetoDependency' &&
          !($l instanceof IMTrackIssueListener2)) {
        continue;
      }
      $v = call_user_func_array(array($l, $method), $args);
      if ($v !== true && $v !== null) {
        $veto[] = $v;
      }
    }
    if (count($veto)) {
      $reasons = array();
      foreach ($veto as $r) {
        if (is_array($r)) {
          foreach ($r as $m) {
            $reasons[] = $m;
          }
        } else {
          $reasons[] = $r;
        }
      }
      throw new MTrackVetoException($reasons);
    }
  }

  function save(MTrackChangeset $CS)
  {
    if (!strlen($this->summary)) {
      throw new Exception("must have a summary");
    }
    $db = MTrackDB::get();

    $parent = null;
    if (strlen($this->ptid)) {
      $parent = $this->resolveTicketDep($this->ptid);
      $this->ptid = $parent->tid;
    } else {
      $this->ptid = null;
    }

    if ($this->tid === null) {
      $this->created = $CS->cid;
      $oldrow = array();
    } else {
      list($oldrow) = MTrackDB::q('select * from tickets where tid = ?',
                        $this->tid)->fetchAll();
    }

    $this->checkVeto('vetoSave', $this, $oldrow);

    $this->updated = $CS->cid;

    $params = array(
      'summary' => $this->summary,
      'description' => $this->description,
      'created' => $this->created,
      'updated' => $this->updated,
      'owner' => $this->owner,
      'changelog' => $this->changelog,
      'priority' => $this->priority,
      'severity' => $this->severity,
      'classification' => $this->classification,
      'resolution' => $this->resolution,
      'status' => $this->status,
      'estimated' => (float)$this->estimated,
      'spent' => (float)$this->spent,
      'nsident' => $this->nsident,
      'cc' => $this->cc,
      'ptid' => $this->ptid,
    );

    $this->augmentSaveParams($params);

    if ($this->tid === null) {
      $sql = 'insert into tickets ';
      $keys = array();
      $values = array();

      $new_tid = new OmniTI_Util_UUID;
      $new_tid = $new_tid->toRFC4122String(false);

      $keys[] = "tid";
      $values[] = "'$new_tid'";

      foreach ($params as $key => $value) {
        $keys[] = $key;
        $values[] = ":$key";
      }

      $sql .= "(" . join(', ', $keys) . ") values (" .
              join(', ', $values) . ")";
    } else {
      $sql = 'update tickets set ';
      $values = array();
      foreach ($params as $key => $value) {
        $values[] = "$key = :$key";
      }
      $sql .= join(', ', $values) . " where tid = :tid";

      $params['tid'] = $this->tid;
    }

    $q = $db->prepare($sql);
    //error_log(json_encode($params));
    $q->execute($params);

    if ($this->tid === null) {
      $this->tid = $new_tid;
    }

    $old_parent = isset($oldrow['ptid']) ? $oldrow['ptid'] : null;
    if ($this->ptid !== $old_parent) {
      if ($old_parent) {
        $parent = null;
        $CS->loadTicket($old_parent, $parent);
        $parent->addComment(
          "related ticket #$this->nsident is no longer a child");
      }
      if ($this->ptid) {
        $parent = null;
        error_log("this->ptid is :$this->ptid:");
        $CS->loadTicket($this->ptid, $parent);
        $parent->addComment(
          "related ticket #$this->nsident is now a child");
      }
    }

    if ($this->ptid && count($this->getChildren())) {
      throw new Exception("tickets can only have parent:child relationships, you're adding children to a child");
    }
    $this->applyChildChanges($CS);

    foreach ($params as $key => $value) {
      if ($key == 'created' || $key == 'updated' || $key == 'tid') {
        continue;
      }
      if (!isset($oldrow[$key])) {
        $oldrow[$key] = null;
      }
      $CS->add("ticket:$this->tid:$key", $oldrow[$key], $value);
    }

    $this->compute_diff($CS, 'components', 'ticket_components', 'compid',
        $this->components, $this->origcomponents);
    $this->compute_diff($CS, 'keywords', 'ticket_keywords', 'kid',
        $this->keywords, $this->origkeywords);

    $CS->assocTicket($this);
    $this->compute_diff($CS, 'dependencies', 'ticket_deps', 'depends_on',
        $this->dependencies, $this->origdependencies);
    $this->compute_diff($CS, 'blocks', 'ticket_deps', 'depends_on',
        $this->blocks, $this->origblocks, true);
    $this->compute_diff($CS, 'milestones', 'ticket_milestones', 'mid',
        $this->milestones, $this->origmilestones);

    foreach ($this->comments_to_add as $text) {
      $CS->add("ticket:$this->tid:@comment", null, $text);
    }

    foreach ($this->effort as $effort) {
      MTrackDB::q('insert into effort (tid, cid, expended, remaining)
        values (?, ?, ?, ?)',
        $this->tid, $CS->cid, $effort[0], $effort[1]);
    }
    $this->effort = array();
  }

  static function index_issue($object)
  {
    list($ignore, $ident) = explode(':', $object, 2);
    $i = MTrackIssue::loadById($ident);
    if (!$i) return;
    echo "Ticket #$i->nsident\n";

    $CS = MTrackChangeset::get($i->updated);
    $CSC = MTrackChangeset::get($i->created);

    $kw = join(' ', array_values($i->getKeywords()));
    $idx = array(
            'type' => 'ticket',
            'summary' => $i->summary,
            'description' => $i->description,
            'changelog' => $i->changelog,
            'keyword' => $kw,
            'stored:date' => $CS->when,
            'who' => $CS->who,
            'creator' => $CSC->who,
            'stored:created' => $CSC->when,
            'status' => $i->status,
            'owner' => $i->owner
            );
    $i->augmentIndexerFields($idx);
    MTrackSearchDB::add("ticket:$i->tid", $idx, true);

    foreach (MTrackDB::q('select value, changedate, who from
        change_audit left join changes using (cid) where fieldname = ?',
        "ticket:$ident:@comment") as $row) {
      list($text, $when, $who) = $row;
      $start = time();
      $id = sha1($text);
      $elapsed = time() - $start;
      if ($elapsed > 4) {
        echo "  - comment $who $when took $elapsed to hash\n";
      }
      $start = time();
      if (strlen($text) > 8192) {
        // A huge paste into a ticket
        $text = substr($text, 0, 8192);
      }
      MTrackSearchDB::add("ticket:$ident:comment:$id", array(
        'description' => $text,
        'stored:date' => $when,
        /* need the status in here so that we can cheaply exclude
         * closed tickets.  Otherwise they keep popping up in the
         * results */
        'status' => $i->status,
        'who' => $who,
      ), true);

      $elapsed = time() - $start;
      if ($elapsed > 4) {
        echo "  - comment $who $when took $elapsed to index\n";
      }
    }
  }

  private function applyChildChanges(MTrackChangeset $CS) {
    $this->getChildren();
    $added = array_keys(array_diff_key($this->children, $this->origchildren));
    $removed = array_keys(array_diff_key($this->origchildren, $this->children));

    foreach ($added as $key) {
      if ($CS->loadTicket($key, $other)) {
        $other->addComment(
          "related ticket #$this->nsident is now my parent");
      }
      $other->ptid = $this->nsident;
    }
    foreach ($removed as $key) {
      if ($CS->loadTicket($key, $other)) {
        $other->addComment(
          "related ticket #$this->nsident is no longer my parent");
      }
      $other->ptid = null;
    }
    if (count($added) + count($removed)) {
      $old = join(',', array_keys($this->origchildren));
      $new = join(',', array_keys($this->children));
      $CS->add(
        "ticket:$this->tid:@children", $old, $new);
    }
  }

  private function compute_diff(MTrackChangeset $CS, $label,
        $tablename, $keyname, $current, $orig, $flip = false) {
    if (!is_array($current)) {
      $current = array();
    }
    if (!is_array($orig)) {
      $orig = array();
    }
    $added = array_keys(array_diff_key($current, $orig));
    $removed = array_keys(array_diff_key($orig, $current));

    $db = MTrackDB::get();
    $ADD = $db->prepare(
      "insert into $tablename (tid, $keyname) values (?, ?)");
    $DEL = $db->prepare(
      "delete from $tablename where tid = ? AND $keyname = ?");
    foreach ($added as $key) {
      if (!strlen(trim($key))) continue;
      if ($tablename == 'ticket_deps') {
        if ($CS->loadTicket($key, $other)) {
          $other->addComment(
            "related ticket #" . $this->nsident . " changed $label");
        }
        if ($label == 'blocks') {
          $other->addDependency($this->tid);
        } else {
          $other->addBlock($this->tid);
        }
      }
      $DEL->execute(
        $flip ?
          array($key, $this->tid) :
          array($this->tid, $key)
      );
      $ADD->execute(
        $flip ?
          array($key, $this->tid) :
          array($this->tid, $key)
      );

    }
    foreach ($removed as $key) {
      if ($tablename == 'ticket_deps') {
        if ($CS->loadTicket($key, $other)) {
          $other->addComment(
            "related ticket #" . $this->nsident . " changed $label");
        }
        if ($label == 'blocks') {
          $other->removeDependency($this->tid);
        } else {
          $other->removeBlock($this->tid);
        }
      }
      $DEL->execute(
        $flip ?
          array($key, $this->tid) :
          array($this->tid, $key)
      );
    }
    if (count($added) + count($removed)) {
      $old = join(',', array_keys($orig));
      $new = join(',', array_keys($current));
      $CS->add(
        "ticket:$this->tid:@$label", $old, $new);
    }
  }

  function getComponents()
  {
    if ($this->components === null) {
      $comps = MTrackDB::q('select tc.compid, name from ticket_components tc left join components using (compid) where tid = ?', $this->tid)->fetchAll();
      $this->origcomponents = array();
      foreach ($comps as $row) {
        $this->origcomponents[$row[0]] = $row[1];
      }
      $this->components = $this->origcomponents;
    }
    return $this->components;
  }

  private function resolveComponent($comp)
  {
    if ($comp instanceof MTrackComponent) {
      return $comp;
    }
    if (ctype_digit($comp) || is_int($comp)) {
      return MTrackComponent::loadById($comp);
    }
    return MTrackComponent::loadByName($comp);
  }

  function assocComponent($comp)
  {
    $comp = $this->resolveComponent($comp);
    $this->getComponents();
    $this->checkVeto('vetoComponent', $this, $comp, true);
    $this->components[$comp->compid] = $comp->name;
  }

  function dissocComponent($comp)
  {
    $comp = $this->resolveComponent($comp);
    $this->getComponents();
    $this->checkVeto('vetoComponent', $this, $comp, false);
    unset($this->components[$comp->compid]);
  }

  function getMilestones()
  {
    if ($this->milestones === null) {
      $comps = MTrackDB::q('select tc.mid, name from ticket_milestones tc left join milestones using (mid) where tid = ? order by duedate, name', $this->tid)->fetchAll();
      $this->origmilestones = array();
      foreach ($comps as $row) {
        $this->origmilestones[$row[0]] = $row[1];
      }
      $this->milestones = $this->origmilestones;
    }
    return $this->milestones;
  }

  private function resolveMilestone($ms)
  {
    if ($ms instanceof MTrackMilestone) {
      return $ms;
    }
    if (ctype_digit($ms) || is_int($ms)) {
      return MTrackMilestone::loadById($ms);
    }
    return MTrackMilestone::loadByName($ms);
  }

  function assocMilestone($M)
  {
    $ms = $this->resolveMilestone($M);
    if ($ms === null) {
      throw new Exception("unable to resolve milestone $M");
    }
    $this->getMilestones();
    $this->checkVeto('vetoMilestone', $this, $ms, true);
    $this->milestones[$ms->mid] = $ms->name;
  }

  function dissocMilestone($M)
  {
    $ms = $this->resolveMilestone($M);
    if ($ms === null) {
      throw new Exception("unable to resolve milestone $M");
    }
    $this->getMilestones();
    $this->checkVeto('vetoMilestone', $this, $ms, false);
    unset($this->milestones[$ms->mid]);
  }

  /* look up a comment by its sha1 hash id (as is stored by the search
   * index).  This is a relatively expensive operation as it has to load
   * all of the comments and sha1 hash them :-/ */
  private $_comments_by_id = array();
  private $_comments_for_id = null;
  function getComment($commentId) {
    if (isset($this->_comments_by_id[$commentId])) {
      return $this->_comments_by_id[$commentId];
    }
    if ($this->_comments_for_id === null) {
      $this->_comments_for_id = MTrackDB::q('select value from change_audit
        where fieldname = ?', "ticket:$this->tid:@comment")
        ->fetchAll(PDO::FETCH_OBJ);
    }
    foreach ($this->_comments_for_id as $obj) {
      if (isset($obj->hash)) continue;
      $obj->hash = sha1($obj->value);
      $this->_comments_by_id[$obj->hash] = $obj->value;
      if ($obj->hash == $commentId) {
        return $obj->value;
      }
    }
    return null;
  }

  function addComment($comment)
  {
    $comment = trim($comment);
    if (strlen($comment)) {
      $this->checkVeto('vetoComment', $this, $comment);
      $this->comments_to_add[] = $comment;
    }
  }

  private function resolveTicketDep($tkt) {
    if ($tkt instanceof MTrackIssue) {
      return $tkt;
    }
    if (strlen($tkt) == 32) {
      return self::loadById($tkt);
    }
    return self::loadByNSIdent($tkt);
  }

  function addChild($id) {
    $tkt = $this->resolveTicketDep($id);
    if (!$tkt) {
      throw new Exception("$id is not a valid ticket!");
      return;
    }
    $this->getChildren();
//    $this->checkVeto('vetoDependency', $tkt, $this, true); FIXME
    $this->children[$tkt->tid] = $tkt->nsident;
    $this->hasChildren = true;
  }

  function removeChild($tkt) {
    $tkt = $this->resolveTicketDep($tkt);
    if (!$tkt) return;
    $this->getChildren();
//    $this->checkVeto('vetoDependency', $tkt, $this, false); FIXME
    unset($this->children[$tkt->tid]);
    if (!count($this->children)) {
      $this->hasChildren = false;
    }
  }

  function getChildren() {
    if ($this->children === null) {
      $deps = MTrackDB::q("select tid, nsident from tickets where ptid = ?",
        $this->tid)->fetchAll(PDO::FETCH_OBJ);

      $this->origchildren = array();
      foreach ($deps as $d) {
        if (!strlen(trim($d->nsident))) continue;
        $this->origchildren[$d->tid] = $d->nsident;
      }
      $this->children = $this->origchildren;
      $this->hasChildren = count($this->children) > 0;
    }
    return $this->children;
  }

  function addBlock($id) {
    $tkt = $this->resolveTicketDep($id);
    if (!$tkt) {
      throw new Exception("$id is not a valid ticket!");
      return;
    }
    $this->getBlocks();
    $this->checkVeto('vetoDependency', $tkt, $this, true);
    $this->blocks[$tkt->tid] = $tkt->nsident;
  }

  function removeBlock($tkt) {
    $tkt = $this->resolveTicketDep($tkt);
    if (!$tkt) return;
    $this->getBlocks();
    $this->checkVeto('vetoDependency', $tkt, $this, false);
    unset($this->blocks[$tkt->tid]);
  }

  function getBlocks() {
    if ($this->blocks === null) {
      $deps = MTrackDB::q("select d.tid, t.nsident as nsident from ticket_deps d left join tickets t on (d.tid = t.tid) where d.depends_on = ? and rel = 'depends'", $this->tid)->fetchAll(PDO::FETCH_OBJ);

      $this->origblocks = array();
      foreach ($deps as $d) {
        if (!strlen(trim($d->nsident))) continue;
        $this->origblocks[$d->tid] = $d->nsident;
      }
      $this->blocks = $this->origblocks;
    }
    return $this->blocks;
  }

  function addDependency($id) {
    $tkt = $this->resolveTicketDep($id);
    if (!$tkt) {
      throw new Exception("$id is not a valid ticket!");
      return;
    }
    $this->getDependencies();
    $this->checkVeto('vetoDependency', $this, $tkt, true);
    $this->dependencies[$tkt->tid] = $tkt->nsident;
  }

  function removeDependency($tkt) {
    $tkt = $this->resolveTicketDep($tkt);
    if (!$tkt) return;
    $this->getDependencies();
    $this->checkVeto('vetoDependency', $this, $tkt, false);
    unset($this->dependencies[$tkt->tid]);
  }

  function getDependencies() {
    if ($this->dependencies === null) {
      $deps = MTrackDB::q("select depends_on, t.nsident as nsident from ticket_deps d left join tickets t on (d.depends_on = t.tid) where d.tid = ? and rel = 'depends'", $this->tid)->fetchAll(PDO::FETCH_OBJ);

      $this->origdependencies = array();
      foreach ($deps as $d) {
        if (!strlen(trim($d->nsident))) continue;
        $this->origdependencies[$d->depends_on] = $d->nsident;
      }
      $this->dependencies = $this->origdependencies;
    }
    return $this->dependencies;
  }

  private function resolveKeyword($kw, $CS = null)
  {
    if ($kw instanceof MTrackKeyword) {
      return $kw;
    }
    $kw = trim($kw);
    $k = MTrackKeyword::loadByWord($kw);
    if ($k === null) {
      if (ctype_digit($kw) || is_int($kw)) {
        return MTrackKeyword::loadById($kw);
      }
      $k = new MTrackKeyword;
      $k->keyword = $kw;
      $k->save($CS);
      return $k;
    }
    return $k;
  }

  function assocKeyword($kw, $CS = null)
  {
    $kw = $this->resolveKeyword($kw, $CS);
    $this->getKeywords();
    $this->checkVeto('vetoKeyword', $this, $kw, true);
    $this->keywords[$kw->kid] = $kw->keyword;
  }

  function dissocKeyword($kw)
  {
    $kw = $this->resolveKeyword($kw);
    $this->getKeywords();
    $this->checkVeto('vetoKeyword', $this, $kw, false);
    unset($this->keywords[$kw->kid]);
  }

  function getKeywords()
  {
    if ($this->keywords === null) {
      $comps = MTrackDB::q('select tc.kid, keyword from ticket_keywords tc left join keywords using (kid) where tid = ?', $this->tid)->fetchAll();
      $this->origkeywords = array();
      foreach ($comps as $row) {
        $this->origkeywords[$row[0]] = $row[1];
      }
      $this->keywords = $this->origkeywords;
    }
    return $this->keywords;
  }

  /* the effort table is differential, so convert the revised estimate
   * into a delta over the prior calculated estimate */
  function addEffort($amount, $revised = null)
  {
    if ($revised === null) {
      $delta = -$amount;
    } else {
      /* we used to use getRemaining() here, but that makes negative
       * remaining time round to 0; we need to compensate for the true
       * values in the table in case they have gone negative, so we
       * do a true sum */
      list($rem) = MTrackDB::q(
          'select sum(remaining) from effort where tid = ?',
          $this->tid)->fetchAll(PDO::FETCH_COLUMN, 0);
      $delta = $revised - ($rem + $this->estimated);
    }
    $this->effort[] = array($amount, $delta);
    $this->spent += $amount;
  }

  /* determine if the ticket can be closed */
  function canClose()
  {
    $reasons = array();

    /* don't allow it to be closed if the dependencies are open */
    $deps = $this->getDependencies();
    $d = array();
    foreach ($deps as $id => $t) {
      $T = self::loadById($id);
      if ($T->isOpen()) {
        $d[] = "#$t";
      }
    }
    if (count($d)) {
      $reasons[] = "cannot close #$this->nsident: blocked by " . join(" ", $d);
    }

    return $reasons;
  }

  function close()
  {
    $reasons = $this->canClose();
    if (count($reasons)) {
      throw new Exception(join("\n", $reasons));
    }
    $this->status = 'closed';
    $this->addEffort(0, 0);
  }

  function isOpen()
  {
    switch ($this->status) {
      case 'closed':
        return false;
      default:
        return true;
    }
  }

  function reOpen()
  {
    $this->status = 'reopened';
    $this->resolution = null;
  }

  /* returns a structured representation of the Cc field.
   * In other words, it returns an object keyed by canonical username
   * that maps to the email address and display name (based on user data)
   * that goes with it */
  function getCc() {
    return self::computeCc($this->cc);
  }

  function setCc($cc) {
    $this->cc = self::flattenCc($cc);
  }

  static function computeCc($cc) {
    $res = array();
    foreach (preg_split("/[,; \t]+/", $cc) as $user) {
      $user = trim($user);
      if (!strlen($user)) continue;
      $who = mtrack_canon_username($user);
      $data = MTrackAuth::getUserData($who);
      $o = new stdclass;
      $o->id = $who;
      $o->email = $data['email'];
      $o->label = isset($data['fullname']) ? $data['fullname'] : $who;
      $res[$who] = $o;
    }
    return $res;
  }

  static function flattenCc($cc) {
    $norm = array();
    foreach ($cc as $k => $v) {
      if (preg_match("/^\d+$/", $k)) {
        if (is_string($v)) {
          $user = $v;
        } else if (is_array($v) && isset($v['email'])) {
          $user = $v['email'];
        } else if (is_object($v) && isset($v->email)) {
          $user = $v->email;
        } else {
          continue;
        }
      } else {
        /* string key is assumed to be the identity */
        $user = $k;
      }
      $norm[] = mtrack_canon_username($user);
    }
    sort($norm);
    return join(', ', $norm);
  }

  function get_next_nsident() {
    // compute next id number.
    // We don't use auto-number, because we allow for importing multiple
    // projects with their own ticket sequence.
    // During "normal" user-driven operation, we do want plain old id numbers
    // so we compute it here, under a transaction
    $db = MTrackDB::get();
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
        // Some versions of postgres don't like that we have "abc123" for
        // identifiers, so match on the bigest number nsident fields only
      $max = "select max(cast(nsident as integer)) + 1 from tickets where nsident ~ E'^\\\\d+$'";
    } else {
      $max = 'select max(cast(nsident as integer)) + 1 from tickets';
    }
    list($this->nsident) = MTrackDB::q($max)->fetchAll(PDO::FETCH_COLUMN, 0);
    if ($this->nsident === null) {
      $this->nsident = 1;
    }
    return $this->nsident;
  }

  static function rest_ticket_new($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'POST');
    MTrackACL::requireAllRights("Tickets", 'create');
    $in = MTrackAPI::getPayload();

    $tkt = new MTrackIssue;
    $comment = isset($in->comment) ? $in->comment : $in->description;
    $CS = MTrackChangeset::begin("ticket:X", $in->description);

    $tkt->get_next_nsident();
    self::rest_apply_to_ticket($in, $tkt, $CS);
    return self::rest_return_ticket($tkt);
  }

  static function rest_apply_to_ticket($in, $tkt, MTrackChangeset $CS) {
    $fields = array(
      'summary',
      'description',
      'owner',
      'changelog',
      'priority',
      'severity',
      'classification',
      'spent',
      'nsident',
      'ptid'
      /* 'estimated' is (and must be!) handled separately below */
    );
    $changes = 0;

    $changes += $tkt->applyPOSTData((array)$in);

    foreach ($fields as $field) {
      if (isset($in->$field) && $in->$field != $tkt->$field) {
        $tkt->$field = $in->$field;
        $changes++;
      }
    }

    if (isset($in->cc)) {
      $cc = self::flattenCc($in->cc);
      if ($cc != $tkt->cc) {
        $tkt->cc = $cc;
        $changes++;
      }
    }

    $arr = array(
      array('keywords', 'getKeywords', 'dissocKeyword',
            'assocKeyword', 'resolveKeyword'),
      array('milestones', 'getMilestones', 'dissocMilestone',
            'assocMilestone', 'resolveMilestone'),
      array('components', 'getComponents', 'dissocComponent',
            'assocComponent', 'resolveComponent'),
      array('dependencies', 'getDependencies',
            'removeDependency', 'addDependency', 'resolveTicketDep'),
      array('blocks', 'getBlocks',
            'removeBlock', 'addBlock', 'resolveTicketDep'),
      array('children', 'getChildren',
            'removeChild', 'addChild', 'resolveTicketDep'),
    );

    foreach ($arr as $arrinfo) {
      list($field, $getter, $remover, $adder, $resolver) = $arrinfo;

      if (!isset($in->$field)) {
        continue;
      }

      $old = array_keys($tkt->$getter());
      $final_set = array();
      $orig_set = array();

      foreach ($old as $id) {
        $orig_set[] = $tkt->$resolver($id, $CS);
      }

      if (is_array($in->$field)) {
        /* an array of item names. */
        foreach ($in->$field as $new_name) {
          $final_set[] = $tkt->$resolver($new_name, $CS);
        }
      } else if (is_object($in->$field)) {
        /* we have the definitive ids */
        foreach ($in->$field as $new_id => $new_name) {
          $final_set[] = $tkt->$resolver($new_id, $CS);
        }
      } else {
        $final_set[] = $tkt->$resolver($in->$field, $CS);
      }

      foreach ($final_set as $item) {
        if (!in_array($item, $orig_set)) {
          $tkt->$adder($item, $CS);
          $changes++;
        }
      }
      foreach ($orig_set as $item) {
        if (!in_array($item, $final_set)) {
          $tkt->$remover($item);
          $changes++;
        }
      }
    }

    if (isset($in->resolution) && $in->resolution != $tkt->resolution) {
      if ($in->resolution) {
        /* implicit close */
        $tkt->resolution = $in->resolution;
        $tkt->close();
      } else {
        /* implicit re-open */
        $tkt->status = 'reopened';
        $tkt->resolution = null;
      }
      $changes++;
    } else if (isset($in->status) && $in->status != $tkt->status) {
      /* handle status transition */
      switch ($in->status) {
        case 'closed':
          $tkt->resolution = 'fixed';
          $tkt->close();
          unset($in->revisedEstimate);
          break;
        case 'reopened':
        case 'open':
        default:
          $tkt->resolution = '';
          $tkt->status = $in->status;
      }
      $changes++;
    } else if ($tkt->status == 'new' && isset($in->owner)) {
      $tkt->status = 'open';
      $changes++;
    }

    $revised = null;
    $spent = 0;
    if (isset($in->revisedEstimate) && strlen($in->revisedEstimate)) {
      $revised = (float)$in->revisedEstimate;
      $changes++;
    } else if (isset($in->estimated) && strlen($in->estimated) &&
        $in->estimated != $tkt->estimated) {
      $revised = (float)$in->estimated;
      $tkt->estimated = $in->estimated;
      $changes++;
    }
    if (isset($in->effortSpent) && strlen($in->effortSpent)) {
      $spent = $in->effortSpent;
    }
    if ($spent || $revised !== null) {
      $tkt->addEffort($spent, $revised);
      $changes++;
    }

    if (isset($in->comment) && strlen($in->comment)) {
      $tkt->addComment($in->comment);
      $changes++;
    }

    if ($changes) {
      $tkt->save($CS);
      $CS->setObject("ticket:$tkt->tid");
      $CS->commit();
    }
  }

  // This is much more complex than I'd like it to be :-/
  // This logic MUST be equivalent to that of the 'remaining' field
  // in inc/report.php.
  function getRemaining() {
    if ($this->status == 'closed') {
      return 0;
    }
    foreach (MTrackDB::q(<<<SQL
SELECT
  count(remaining),
  round(cast(? + coalesce(sum(remaining), 0) as numeric), 2)
FROM effort where tid = ? and remaining != 0
SQL
        , $this->estimated, $this->tid
        )->fetchAll() as $row) {
      if ($row[0]) {
        /* normalize floating point zero to precisely zero */
        return $row[1] == 0 ? 0 : max($row[1], 0);
      }
    }
    return $this->estimated;
  }

  static function rest_return_ticket($tkt) {
    $j = MTrackAPI::makeObj($tkt, 'tid');
    /* custom fields */
    foreach (get_object_vars($tkt) as $k => $v) {
      if (strncmp($k, "x_", 2)) continue;
      $j->$k = $v;
    }
    if ($tkt->ptid) {
      $p = self::loadById($tkt->ptid);
      $j->parent = $p->nsident;
    }
    $j->cc = $tkt->getCc();
    $j->keywords = $tkt->getKeywords();
    $j->components = $tkt->getComponents();
    $j->milestones = $tkt->getMilestones();
    $j->dependencies = $tkt->getDependencies();
    $j->blocks = $tkt->getBlocks();
    $j->children = $tkt->getChildren();
    $j->hasChildren = $tkt->hasChildren ? true : false;
    $j->description_html = MTrackWiki::format_to_html($j->description,
      "ticket:$tkt->tid");

    /* make fake fields go away */
    $j->effortSpent = '';
    $j->comment = '';
    $j->revisedEstimate = '';

    if (isset($tkt->updated)) {
      $CS = MTrackChangeset::get($tkt->updated);
      $j->updated = new stdclass;
      $j->updated->who = $CS->who;
      $j->updated->when = MTrackAPI::date8601($CS->when);
      $j->updated->cid = $tkt->updated;
    }

    if (isset($tkt->created)) {
      $CS = MTrackChangeset::get($tkt->created);
      $j->created = new stdclass;
      $j->created->who = $CS->who;
      $j->created->when = MTrackAPI::date8601($CS->when);
      $j->created->cid = $tkt->created;
    }

    $j->remaining = $tkt->getRemaining();
    /* the prediction can change due to randomness, and this makes it
     * seem like the data always changes when backbone loads and examines it
    if ($j->owner && $j->remaining > 0) {
      $j->prediction = MTrackAPI::invoke('GET', "/user/" .
        $j->owner . "/ebs/predict/" . $j->remaining)->result;
    }
    */

    return $j;
  }

  function _determineAssigneeList($fallback, $multi_select = false) {
    // FIXME: workflow should be able to influence this list of users
    $users = array();
    if (!$multi_select) {
      $users[''] = '';
    }
    $inactiveusers = array();
    foreach (MTrackDB::q(
        'select userid, fullname, active from userinfo order by userid'
        )->fetchAll() as $row) {
      if (strlen($row[1])) {
        $disp = "$row[0] - $row[1]";
      } else {
        $disp = $row[0];
      }
      if ($row[2]) {
        $users[$row[0]] = $disp;
      } else {
        $inactiveusers[$row[0]] = $disp;
      }
    }

    if (!is_array($fallback)) {
      $fallback = array($fallback);
    }
    foreach ($fallback as $u) {
      // allow for inactive users to show up if they're currently responsible
      if (!isset($users[$u])) {
        if (!isset($inactiveusers[$u])) {
          $users[$u] = $u . ' (inactive)';
        } else {
          $users[$u] = $inactiveusers[$u] . ' (inactive)';
        }
      }
      // last ditch to have it show the right info
      if (!isset($users[$u])) {
        $users[$u] = $u;
      }
    }
    $res = array();
    foreach ($users as $id => $label) {
      $u = new stdclass;
      $u->id = $id;
      $u->label = $label;
      $res[] = $u;
    }

    return $res;
  }

  /** this is used by the "Children" ticket field to match
   * suitable child candidates */
  static function rest_ticket_search_child_candidates($method, $uri, $captures)
  {
    MTrackAPI::checkAllowed($method, 'GET');
    $q = MTrackAPI::getParam('q');
    $res = array();

    MTrackACL::requireAnyRights('Tickets', 'read');

    $id = null;
    if ($q[0] == '#') {
      $id = substr($q, 1);
    } else {
      $id = $q;
    }
    /* we only want to return matches that have no children of their own */

    if (preg_match("/^[a-z0-9A-Z]+$/", $id)) {
      foreach (MTrackDB::q("select tid, nsident, summary, (select count(k.tid) from tickets k where k.ptid = t.tid) as kids from tickets t where nsident like '$id%' and status != 'closed'")->fetchAll(PDO::FETCH_OBJ)
          as $obj) {
        if ($obj->kids == 0) {
          $res[$obj->tid] = $obj;
        }
      }
    }
    if (!preg_match("/\d/", $q)) {
      $hits = MTrackSearchDB::search(
            "(+summary:\"$q\" +type:ticket) NOT status:closed");
      $extras = array();
      $tids = array();
      foreach ($hits as $hit) {
        list($item, $id) = explode(':', $hit->objectid, 3);
        if ($item != 'ticket') continue;
        if (isset($res[$id])) continue;
        if (isset($extras[$id])) continue;
        $extras[$id] = $hit;
        $tids[] = MTrackDB::esc($id);
      }

      /* anything in extras needs the nsident and summary fetched */
      if (count($tids)) {
        $tids = join(',', $tids);
        foreach (MTrackDB::q("select tid, nsident, summary, (select count(k.tid) from tickets k where k.ptid = t.tid) as kids from tickets t where t.tid in ($tids) and kids = 0")->fetchAll(PDO::FETCH_OBJ) as $obj) {
          $res[$obj->tid] = $obj;
        }
      }
    }

    return array_values($res);
  }

  static function rest_ticket_search($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    $q = MTrackAPI::getParam('q');
    $res = array();

    MTrackACL::requireAnyRights('Tickets', 'read');

    $id = null;
    if ($q[0] == '#') {
      $id = substr($q, 1);
    } else {
      $id = $q;
    }
    if (preg_match("/^[a-z0-9A-Z]+$/", $id)) {
      foreach (MTrackDB::q("select tid, nsident, summary from tickets
          where nsident like '$id%' and status != 'closed'")
          ->fetchAll(PDO::FETCH_OBJ)
          as $obj) {
        $res[$obj->tid] = $obj;
      }
    }
    if (!preg_match("/\d/", $q)) {
      $hits = MTrackSearchDB::search(
            "(+summary:\"$q\" +type:ticket) NOT status:closed");
      $extras = array();
      $tids = array();
      foreach ($hits as $hit) {
        list($item, $id) = explode(':', $hit->objectid, 3);
        if ($item != 'ticket') continue;
        if (isset($res[$id])) continue;
        if (isset($extras[$id])) continue;
        $extras[$id] = $hit;
        $tids[] = MTrackDB::esc($id);
      }

      /* anything in extras needs the nsident and summary fetched */
      if (count($tids)) {
        $tids = join(',', $tids);
        foreach (MTrackDB::q("select tid, nsident, summary from tickets where tid in ($tids)")->fetchAll(PDO::FETCH_OBJ) as $obj) {
          $res[$obj->tid] = $obj;
        }
      }
    }

    return array_values($res);
  }

  static function _find_matching_cc($q) {
    $matches = array();
    $sanitized = preg_replace("/[^a-zA-Z0-9@._=+-]+/", '', $q);
    foreach (MTrackDB::q("select distinct cc from tickets where cc is not null and cc <> '' and cc like '%$sanitized%'")->fetchAll(PDO::FETCH_COLUMN, 0) as $cc) {
      foreach (self::computeCc($cc) as $o) {
        if (stripos($o->id, $q) !== false ||
            stripos($o->email, $q) !== false ||
            stripos($o->label, $q) !== false) {
          $matches[$o->id] = $o;
        }
      }
    }
    return $matches;
  }

  static function rest_active_users($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    /* avoid leaking information to anonymous users */
    $me = MTrackAuth::whoami();
    if ($me == 'anonymous' || MTrackAuth::getUserClass() == 'anonymous') {
      return array();
    }
    $tkt = new MTrackIssue;
    $users = $tkt->_determineAssigneeList(array(), true);

    return $users;
  }

  static function rest_ticket_cclist($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');
    /* avoid leaking information to anonymous users */
    $me = MTrackAuth::whoami();
    if ($me == 'anonymous' || MTrackAuth::getUserClass() == 'anonymous') {
      return array();
    }
    $q = MTrackAPI::getParam('q');
    $tkt = new MTrackIssue;
    $users = $tkt->_determineAssigneeList(array(), true);

    $matches = array();
    foreach ($users as $u) {
      if (stripos($u->id, $q) !== false || stripos($u->label, $q) !== false) {
        $o = new stdclass;
        $o->id = $u->id;
        $o->email = $u->id;
        $o->label = $u->label;
        $matches[$o->id] = $o;
      }
    }
    foreach (mtrack_cache(array('MTrackIssue', '_find_matching_cc'),
        array($q), 60) as $o) {
      $matches[$o->id] = $o;
    }

    return array_values($matches);
  }

  /* Returns information about the ticket fields such that it could be
   * used to procedurally generate a rendering of the ticket data */
  static function rest_ticket_fields($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');

    $tid = MTrackAPI::getParam('tid');
    if (strlen($tid)) {
      $tkt = self::loadById($tid);
      if (!$tkt) {
        MTrackAPI::error(400, "invalid tid", $tid);
      }
      MTrackACL::requireAllRights("ticket:" . $tkt->tid, 'read');
    } else {
      $tkt = new MTrackIssue;
      MTrackACL::requireAllRights("Tickets", "create");
    }

    $enums = array();
    foreach (array(
        'classification' => 'MTrackClassification',
        'priority' => 'MTrackPriority',
        'severity' => 'MTrackSeverity',
        'resolution' => 'MTrackResolution') as $k => $cls) {
      $E = new $cls;
      $r = array();
      foreach ($E->enumerate() as $id => $label) {
        $o = new stdclass;
        $o->id = $id;
        $o->label = $label;
        $r[] = $o;
      }
      $enums[$k] = $r;
    }
    $components = array();
    foreach (MTrackDB::q('select c.compid, c.name from components c where deleted <> 1 order by c.name')
        ->fetchAll(PDO::FETCH_NUM) as $row) {
      $o = new stdclass;
      $o->id = $row[0];
      $o->label = $row[1];
      $components[] = $o;
    }

    /* first pass to determine the milestones */
    $m_by_id = array();
    $km_by_id = array();
    foreach (MTrackDB::q(
            'select m.mid, m.name, k.mid, k.name
            from
              milestones m
            left join
              milestones k on (k.pmid = m.mid)
            where m.deleted <> 1
                and m.completed is null and m.pmid is null
                and ((k.deleted is null) or (k.deleted <> 1))
            order by
                (case when m.duedate is null then 1 else 0 end),
                m.duedate, m.name'
          )->fetchAll(PDO::FETCH_NUM) as $row) {
      list($mid, $mname, $kmid, $kname) = $row;

      if (!isset($m_by_id[$mid])) {
        $m = new stdclass;
        $m->id = $mid;
        $m->label = $mname;
        $m_by_id[$m->id] = $m;
      }
      if ($kmid) {
        $m = new stdclass;
        $m->id = $kmid;
        $m->label = $kname;
        $p = $m_by_id[$mid];
        if (!isset($p->items)) {
          $p->items = array();
        }
        $p->items[] = $m;
        $km_by_id[$m->id] = $m;
      }
    }

    /* in some cases, the value we have in the ticket is not present
     * in the list of options; this happens for example when we close
     * a milestone; we won't allow it to be used for tickets going forward,
     * but it we're viewing a ticket that is already on that milestone,
     * we do want the option to be available, otherwise it looks like the
     * ticket is no longer on a milestone */

    $closed_ms = array();
    foreach ($tkt->getMilestones() as $id => $name) {
      if (isset($m_by_id[$id])) continue;
      if (isset($km_by_id[$id])) continue;

      $m = new stdclass;
      $m->id = $id;
      $m->label = $name;
      $closed_ms[] = $m;
    }

    /* second pass to make the items smell more like a select option group */
    $milestones = array();
    foreach ($m_by_id as $m) {
      $o = clone $m;
      unset($o->items);
      $milestones[] = $o;

      if (isset($m->items)) {
        /* synthesize a group */
        $g = new stdclass;
        $g->group = "Iterations of \"$m->label\"";
        $g->items = $m->items;
        $milestones[] = $g;
      }
    }

    /* and a third pass to put the closed milestones in a group of their own */
    if (count($closed_ms)) {
      $g = new stdclass;
      $g->group = "Closed Milestones";
      $g->items = $closed_ms;
      $milestones[] = $g;
    }

    $FIELDSET = array(
      array(
        "description" => array(
          "label" => "Full description",
          "placeholder" => "Describe it here",
          "ownrow" => true,
          "type" => "wiki",
          "rows" => 10,
          "cols" => 78,
          "editonly" => true,
        ),
      ),
      "Properties" => array(
        "milestones" => array(
          "label" => "Milestone",
          "type" => "multiselect",
          "options" => $milestones,
          "placeholder" => "Select some Milestone(s)",
        ),
        "components" => array(
          "label" => "Component",
          "type" => "multiselect",
          "options" => $components,
          "placeholder" => "Select some Component(s)",
        ),
        "classification" => array(
          "label" => "Classification",
          "type" => "select",
          "options" => $enums['classification']
        ),
        "priority" => array(
          "label" => "Priority",
          "type" => "select",
          "options" => $enums['priority']
        ),
        "severity" => array(
          "label" => "Severity",
          "type" => "select",
          "options" => $enums['severity']
        ),
        "keywords" => array(
          "label" => "Keywords",
          "type" => "tags",
        ),
        "changelog" => array(
          "label" => "ChangeLog",
          "placeholder" => "customer visible; choose your words wisely!",
          "type" => "multi",
          "ownrow" => true,
          "rows" => 5,
          "cols" => 78,
          #   "condition" => $issue->status == 'closed'
        ),
      ),
      "Resources" => array(
        "owner" => array(
          "label" => "Responsible",
          "placeholder" => "Nobody",
          "type" => "select",
          "options" => $tkt->_determineAssigneeList($tkt->owner)
        ),
        "cc" => array(
          "label" => "Cc",
          "type" => "cc",
        ),
        "estimated" => array(
          "label" => "Initial Estimate",
          "type" => "text",
          "placeholder" => "Enter time in hours"
        ),
        "spent" => array(
          "label" => "Expended Effort",
          "type" => "readonly",
        ),
        "effortSpent" => array(
          "label" => "Log time spent",
          "placeholder" => "Enter time in hours",
          "type" => "text"
        ),
        "revisedEstimate" => array(
          "label" => "Time Remaining",
          "type" => "text",
          "placeholder" => "after logged time"
        ),
      ),
      "Dependencies" => array(
        "children" => array(
          "label" => "Children",
          "type" => "ticketdeps",
        ),
        "dependencies" => array(
          "label" => "Depends On",
          "type" => "ticketdeps",
        ),
        "blocks" => array(
          "label" => "Blocks",
          "type" => "ticketdeps",
        ),
      ),
    );
    $tkt->augmentFormFields($FIELDSET);

    /* workaround a bug in "chosen" that causes it to crap out when
     * we have no options; this happens in a fresh install because there
     * are no components in the initial setup */
    if (count($milestones) == 0) {
      unset($FIELDSET['Properties']['milestones']);
    }
    if (count($components) == 0) {
      unset($FIELDSET['Properties']['components']);
    }
    if ($tkt && strlen($tkt->ptid)) {
      unset($FIELDSET['Properties']['children']);
    }

    $res = array();
    foreach ($FIELDSET as $group => $fields) {
      $G = new stdclass;
      $G->name = $group;
      $G->fields = array();
      foreach ($fields as $fieldname => $info) {
        $F = new stdclass;
        $F->name = $fieldname;
        foreach ($info as $k => $v) {
          $F->$k = $v;
        }
        $G->fields[] = $F;
      }
      $res[] = $G;
    }

    return $res;
  }

  static function rest_ticket($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET', 'PUT');

    $tid = $captures['tid'];
    $tkt = null;

    if (strlen($tid) == 32) {
      /* probably a tid */
      $tkt = self::loadById($tid);
    }
    if (!$tkt) {
      $tkt = self::loadByNSIdent($tid);
    }
    if (!$tkt) {
      MTrackAPI::error(404, "no such ticket", $tid);
    }
    if ($method == 'PUT') {
      MTrackACL::requireAllRights("ticket:" . $tkt->tid, 'modify');
      $in = MTrackAPI::getPayload();
      if (!is_object($in)) {
        MTrackAPI::error(400, "expected json payload");
      }
      if (isset($in->updated) && $in->updated->cid != $tkt->updated) {
        /* get a JSON version to make it easier to compare */
        /* nuance: the keys of some of our objects are "integers", but
         * may be the string representation or a native integer depending
         * on the circumstance.  We do an obj->json->obj conversion to
         * normalize this */
        $curr = get_object_vars(json_decode(json_encode(
                  self::rest_return_ticket($tkt))));
        $updated = $curr['updated'];
        $old = get_object_vars(json_decode(json_encode($in)));
        unset($curr['updated']);
        unset($old['updated']);
        $old['comment'] = '';
        $old['effortSpent'] = '';
        $diff = array();
        foreach ($old as $k => $v) {
          if (!isset($curr[$k])) {
            if ($v !== null) {
              $diff[$k] = array($v, null);
            }
          } else if ($curr[$k] != $v) {
            $diff[$k] = array($v, $curr[$k]);
          }
        }
        foreach ($curr as $k => $v) {
          if (isset($diff[$k])) continue;
          if (!isset($old[$k])) {
            if ($v !== null) {
              $diff[$k] = array(null, $v);
            }
          } else if ($old[$k] != $v) {
            $diff[$k] = array($old[$k], $v);
          }
        }
        if (count($diff)) {
          $diff['updated'] = $updated;
          MTrackAPI::error(409, "conflict detected", $diff);
        }
      }
      $CS = MTrackChangeset::begin("ticket:$tkt->tid",
        isset($in->comment) ? $in->comment : '');
      self::rest_apply_to_ticket($in, $tkt, $CS);

      /* and continue on to return the updated version */
    }
    MTrackACL::requireAllRights("ticket:" . $tkt->tid, 'read');
    return self::rest_return_ticket($tkt);
  }

  static function _component_value_fixup($str) {
    static $components = null;
    if ($components === null) {
      $components = MTrackComponent::enumerate(true);
    }
    $V = new stdclass;
    foreach (preg_split("/\s*,\s*/", $str) as $id) {
      if (!strlen(trim($id))) continue;
      $C = $components[$id];
      $V->$id = $C->name;
    }
    return $V;
  }

  static function _milestone_value_fixup($str) {
    static $milestones = null;
    if ($milestones === null) {
      $milestones = MTrackMilestone::enumMilestones(true);
    }
    $V = new stdclass;
    foreach (preg_split("/\s*,\s*/", $str) as $id) {
      if (!strlen(trim($id))) continue;
      $V->$id = $milestones[$id];
    }
    return $V;
  }

  static function _keyword_value_fixup($str) {
    static $keywords = null;
    if ($keywords === null) {
      $keywords = MTrackKeyword::enumerate();
    }
    $V = new stdclass;
    foreach (preg_split("/\s*,\s*/", $str) as $id) {
      if (!strlen(trim($id))) continue;
      $V->$id = $keywords[$id];
    }
    return $V;
  }

  static function _dep_value_fixup($str) {
    static $map = array();
    $V = new stdclass;
    foreach (preg_split("/\s*,\s*/", $str) as $id) {
      if (!strlen(trim($id))) continue;
      if (isset($map[$id])) {
        $V->$id = $map[$id];
      } else {
        $T = self::loadById($id);
        $map[$id] = $T->nsident;
        $V->$id = $T->nsident;
      }
    }
    return $V;
  }

  static function _process_ticket_audit_entry($A, MTrackIssue $tkt) {
    $bits = explode(':', $A->fieldname, 3);
    if (count($bits) != 3) {
      return false;
    }
    list($tbl,$targettkt,$field) = $bits;


    if ($tbl != 'ticket') {
      /* can get here if we created a new keyword etc. */
      return false;
    }
    if ($targettkt != $tkt->tid) {
      /* referencing another ticket; perhaps because the blocks/depends
       * status between the two changed */
      return false;
    }

    switch ($field) {
      case 'ptid':
        if ($A->value) {
          $T = self::loadById($A->value);
          $A->value = array($T->tid => $T->nsident);
        }
        if ($A->oldvalue) {
          $T = self::loadById($A->oldvalue);
          $A->oldvalue = array($T->tid => $T->nsident);
        }
        $field = 'parent';
        break;

      case '@components':
        $A->value = self::_component_value_fixup($A->value);
        $A->oldvalue = self::_component_value_fixup($A->oldvalue);
        break;

      case '@milestones':
        $A->value = self::_milestone_value_fixup($A->value);
        $A->oldvalue = self::_milestone_value_fixup($A->oldvalue);
        break;

      case '@keywords':
        $A->value = self::_keyword_value_fixup($A->value);
        $A->oldvalue = self::_keyword_value_fixup($A->oldvalue);
        break;

      case '@dependencies':
      case '@blocks':
      case '@children':
        $A->value = self::_dep_value_fixup($A->value);
        $A->oldvalue = self::_dep_value_fixup($A->oldvalue);
        break;

      case '@comment':
        $field = 'comment';
        if (strlen($A->value)) {
          $A->value_html = MTrackWiki::format_to_html($A->value,
            "ticket:$tkt->tid");
        }
        break;
    }

    if ($field[0] == '@') {
      /* remove preceeding @ sign */
      if ($field == '@dependencies' || $field == '@blocks' ||
          $field == '@children') {
        $field = substr($field, 1);
      } else {
        /* de-pluralize */
        $field = substr($field, 1, -1);
      }
    }
    $f = MTrackTicket_CustomFields::getInstance()->fieldByName($field);
    if ($f) {
      $A->label = $f->label;
    } else {
      $A->label = ucfirst($field);
    }

    return true;
  }

  static function rest_ticket_children($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');

    $tid = $captures['tid'];
    $tkt = null;

    if (strlen($tid) == 32) {
      /* probably a tid */
      $tkt = self::loadById($tid);
    }
    if (!$tkt) {
      $tkt = self::loadByNSIdent($tid);
    }
    if (!$tkt) {
      MTrackAPI::error(404, "no such ticket", $tid);
    }
    MTrackACL::requireAllRights("ticket:" . $tkt->tid, 'read');

    $tickets = MTrackDB::q('select tid from tickets where ptid = ?',
      $tkt->tid)->fetchAll(PDO::FETCH_ASSOC);
    $result = array();
    foreach ($tickets as $row) {
      $t = MTrackAPI::invoke('GET', "/ticket/$row[tid]");
      $obj = $t->result;
      $result[] = $obj;
    }
    return $result;
  }

  static function rest_ticket_attachments($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');

    $tid = $captures['tid'];
    $tkt = null;

    if (strlen($tid) == 32) {
      /* probably a tid */
      $tkt = self::loadById($tid);
    }
    if (!$tkt) {
      $tkt = self::loadByNSIdent($tid);
    }
    if (!$tkt) {
      MTrackAPI::error(404, "no such ticket", $tid);
    }
    MTrackACL::requireAllRights("ticket:" . $tkt->tid, 'read');

    return MTrackAttachment::getList("ticket:$tkt->tid");
  }

  static function rest_ticket_changes($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET', 'PUT');

    $last = (int)MTrackAPI::getParam('last');
    $tid = $captures['tid'];
    $tkt = null;

    if (strlen($tid) == 32) {
      /* probably a tid */
      $tkt = self::loadById($tid);
    }
    if (!$tkt) {
      $tkt = self::loadByNSIdent($tid);
    }
    if (!$tkt) {
      MTrackAPI::error(404, "no such ticket", $tid);
    }
    MTrackACL::requireAllRights("ticket:" . $tkt->tid, 'read');

    $changes = array();
    $cids = array();
    foreach (MTrackDB::q(
        'select * from changes where
        cid > ? and
        object = ?
        order by changedate asc',
        $last, "ticket:$tkt->tid")->fetchAll(PDO::FETCH_OBJ) as $CS) {
      $CS->audit = array();
      $CS->id = (int)$CS->cid;
      $CS->changedate = MTrackAPI::date8601($CS->changedate);
      unset($CS->cid);
      $changes[$CS->id] = $CS;
      $cids[] = $CS->id;
    }

    if (count($cids)) {
      $cidlist = join(',', $cids);
      foreach (MTrackDB::q("select * from change_audit where cid in ($cidlist)")
          ->fetchAll(PDO::FETCH_OBJ) as $citem) {
        $CS = $changes[$citem->cid];
        if (self::_process_ticket_audit_entry($citem, $tkt)) {
          $CS->audit[] = $citem;
        }
      }

      /* also need to include cases where the ticket was modified as a
       * side-effect of other manipulations (such as milestones being closed
       * and tickets being re-targeted.  Such manipulations do not directly
       * reference this ticket, and so do not need to be included in the
       * effort_audit array that is populated below. */
      $tid = $tkt->tid;
      foreach (MTrackDB::q(
            "select c.cid as cid, c.who as who, c.object as object, c.changedate as changedate, c.reason as reason, ca.fieldname as fieldname, ca.action as action, ca.oldvalue as oldvalue, ca.value as value from change_audit ca left join changes c on (ca.cid = c.cid) where ca.cid > ? and ca.cid not in ($cidlist) and ca.fieldname like 'ticket:$tid:%'", $last)
          ->fetchAll(PDO::FETCH_OBJ) as $audit) {
        if (!isset($changes[$audit->cid])) {
          $CS = new stdclass;
          $CS->id = (int)$audit->cid;
          $CS->who = $audit->who;
          $CS->object = $audit->object;
          $CS->changedate = MTrackAPI::date8601($audit->changedate);
          $CS->reason = $audit->reason;
          $changes[$CS->id] = $CS;
        } else {
          $CS = $changes[$audit->cid];
        }

        $A = new stdclass;
        $A->cid = $audit->cid;
        $A->fieldname = $audit->fieldname;
        $A->action = $audit->action;
        $A->oldvalue = $audit->oldvalue;
        $A->value = $audit->value;
        if (self::_process_ticket_audit_entry($A, $tkt)) {
          $CS->audit[] = $A;
        }
      }
    }

    $r = array();
    krsort($changes);
    foreach ($changes as $CS) {
      if (!count($CS->audit)) {
        continue;
      }
      $r[] = $CS;
    }
    return $r;
  }

  static function resolve_link(MTrackLink $link)
  {
    /* ticket ranges map to queries */
    if (strpos($link->target, '-') !== false ||
        strpos($link->target, ',') !== false) {
      $link->type = 'query';
      $link->target = 'id=' . $link->target;
      $link->resolveLinkToURL();
      $link->class = 'ticketlink';
      return;
    }

    $link->class = 'ticketlink';
    if (preg_match('/^(.*)(#.*)$/', $link->target, $M)) {
      $tkt = $M[1];
      $anchor = $M[2]; // note: includes '#'
    } else {
      $tkt = $link->target;
      $anchor = '';
    }

    if (strlen($tkt) == 32) {
      /* tid; resolve to nsident */

      if ($link->label == $tkt) {
        $link->label = null;
      }
      static $tickets = array();
      if (!isset($tickets[$tkt])) {
        $t = MTrackIssue::loadById($tkt);
        $tickets[$tkt] = $t;
        $tkt = $t->nsident;
      } else {
        $tkt = $tickets[$tkt]->nsident;
      }
    }

    $link->url = $GLOBALS['ABSWEB'] . 'ticket.php/' . $tkt . $anchor;
    if ($link->label === null) {
      $link->label = '#' . $tkt;
    }
  }

  static function resolve_comment_link(MTrackLink $link)
  {
    if (preg_match('/^(\d+):ticket:(.*)$/', $link->target, $M)) {
      /* re-frame as a ticket link with an anchor */
      $ticket = $M[1];
      $comment = $M[2];

      $link->type = 'ticket';
      $link->target = "$ticket#$comment";
      $link->resolveLinkToURL();
      return;
    }

    /* otherwise, not really anything we know about; just make
     * a local page relative link */
    $link->url = '#' . $link->target;
  }
}

MTrackSearchDB::register_indexer('ticket', array('MTrackIssue', 'index_issue'));
MTrackACL::registerAncestry('enum', 'Enumerations');
MTrackACL::registerAncestry("component", 'Components');
MTrackACL::registerAncestry("project", 'Projects');
MTrackACL::registerAncestry("ticket", "Tickets");
MTrackWatch::registerEventTypes('ticket', array(
  'ticket' => 'Tickets'
));
MTrackAPI::register('/ticket/:tid', 'MTrackIssue::rest_ticket');
MTrackAPI::register('/ticket/:tid/changes', 'MTrackIssue::rest_ticket_changes');
MTrackAPI::register('/ticket/:tid/attach',
   'MTrackIssue::rest_ticket_attachments');
MTrackAPI::register('/ticket/:tid/children',
  'MTrackIssue::rest_ticket_children');
MTrackAPI::register('/ticket/:tid/children/candidates',
  'MTrackIssue::rest_ticket_search_child_candidates');
MTrackAPI::register('/ticket', 'MTrackIssue::rest_ticket_new');
MTrackAPI::register('/ticket/meta/fields', 'MTrackIssue::rest_ticket_fields');
MTrackAPI::register('/ticket/meta/cc', 'MTrackIssue::rest_ticket_cclist');
MTrackAPI::register('/ticket/meta/users', 'MTrackIssue::rest_active_users');
MTrackAPI::register('/ticket/search/basic', 'MTrackIssue::rest_ticket_search');
MTrackLink::register('ticket', 'MTrackIssue::resolve_link');
MTrackLink::register('comment', 'MTrackIssue::resolve_comment_link');
MTrackLink::register('component', 'MTrackComponent::resolve_link');


MTrackAPI::register('/project', 'MTrackProject::rest_project_new');
MTrackAPI::register('/project/:p', 'MTrackProject::rest_project');

MTrackAPI::register('/ticket/enums/state', 'MTrackTicketState::rest_list');
MTrackAPI::register('/ticket/enums/state/:s', 'MTrackTicketState::rest_item');

MTrackAPI::register('/ticket/enums/priority', 'MTrackPriority::rest_list');
MTrackAPI::register('/ticket/enums/priority/:s', 'MTrackPriority::rest_item');

MTrackAPI::register('/ticket/enums/severity', 'MTrackSeverity::rest_list');
MTrackAPI::register('/ticket/enums/severity/:s', 'MTrackSeverity::rest_item');

MTrackAPI::register('/ticket/enums/resolution', 'MTrackResolution::rest_list');
MTrackAPI::register('/ticket/enums/resolution/:s', 'MTrackResolution::rest_item');

MTrackAPI::register('/ticket/enums/classification', 'MTrackClassification::rest_list');
MTrackAPI::register('/ticket/enums/classification/:s', 'MTrackClassification::rest_item');

