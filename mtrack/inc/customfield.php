<?php # vim:ts=2:sw=2:et:

class MTrackTicket_CustomField {
  var $name;
  var $type;
  var $label;
  var $group;
  var $order = 0;
  var $default;
  var $options;
  /** underscore compatible template to use when presenting the
   * custom field value in a read-only fashion */
  var $template;

  function getTypeLabel() {
    static $field_types = array(
      'text' => 'Text (single line)',
      'multi' => 'Text (multi-line)',
      'wiki' => 'Wiki',
      'shortwiki' => 'Wiki (shorter height)',
      'select' => 'Select box (choice of one)',
      'user' => 'User',
      'multiselect' => 'Multiple select',
    );
    return $field_types[$this->type];
  }

  static function canonName($name) {
    if (!preg_match("/^x_/", $name)) {
      $name = "x_$name";
    }
    return $name;
  }

  /** load the field definition from the configuration file */
  static function load($name) {
    if (!preg_match("/^x_[a-z_]+$/", $name)) {
      throw new Exception("invalid field name $name");
    }
    $type  = MTrackConfig::get('ticket.custom', "$name.type");

    $C = MTrackTicket_CustomFields::getInstance();
    $field = $C->fieldByName($name, $type);
    $field->type  = MTrackConfig::get('ticket.custom', "$name.type");
    $field->label = MTrackConfig::get('ticket.custom', "$name.label");
    $field->group = MTrackConfig::get('ticket.custom', "$name.group");
    $field->order   = (int)MTrackConfig::get('ticket.custom', "$name.order");
    $field->default = MTrackConfig::get('ticket.custom', "$name.default");
    $field->options = MTrackConfig::get('ticket.custom', "$name.options");
    $field->template = json_decode(MTrackConfig::get('ticket.custom', "$name.template", true));

    return $field;
  }

  function save() {
    if (!preg_match("/^x_[a-z_]+$/", $this->name)) {
      throw new Exception("invalid field name $this->name");
    }
    $name = $this->name;
    MTrackConfig::set('ticket.custom', "$name.type", $this->type);
    MTrackConfig::set('ticket.custom', "$name.label", $this->label);
    MTrackConfig::set('ticket.custom', "$name.group", $this->group);
    MTrackConfig::set('ticket.custom', "$name.order", (int)$this->order);
    MTrackConfig::set('ticket.custom', "$name.default", $this->default);
    MTrackConfig::set('ticket.custom', "$name.options", $this->options);
    if ($this->template) {
      MTrackConfig::set('ticket.custom', "$name.template",
        json_encode($this->template), true);
    } else {
      MTrackConfig::set('ticket.custom', "$name.template", '');
    }
  }

  function ticketData(MTrackIssue $issue = null) {
    /* compatible with the $FIELDSET data used in web/ticket.php */
    $data = array(
      'label' => $this->label,
      'type' => $this->type,
      'customfieldtype' => $this->type,
    );
    if (strlen($this->template)) {
      $data['customtemplate'] = $this->template;
    }

    if (strlen($this->default)) {
      $data['default'] = $this->default;
    }

    switch ($this->type) {
      case 'multi':
      case 'wiki':
      case 'shortwiki':
        $data['ownrow'] = true;
        $data['rows'] = 5;
        $data['cols'] = 78;
        break;
      case 'select':
      case 'multiselect':
        $options = array();
        $ent = new stdclass;
        $ent->id = '';
        $ent->label = '';
        $options[] = $ent;
        $seen = array();
        foreach (explode('|', $this->options) as $opt) {
          $ent = new stdclass;
          $ent->id = $opt;
          $ent->label = $opt;
          $seen[$opt] = $ent;
          $options[] = $ent;
        }
        if ($issue && isset($issue->{$this->name})) {
          $val = $issue->{$this->name};
          if (!isset($seen[$val])) {
            $ent = new stdclass;
            $ent->id = $val;
            $ent->label = $val;
            $options[] = $ent;
          }
        }
        $data['options'] = $options;
        break;
      case 'user':
        $options = array();
        $ent = new stdclass;
        if (strlen($this->default)) {
          $ent->id = $this->default;
          $ent->label = $this->default;
        } else {
          $ent->id = '';
          $ent->label = '';
        }
        $options[] = $ent;
        $seen = array();
        foreach (MTrackDB::q('select userid, fullname from userinfo where active = 1 order by userid')->fetchAll() as $row) {
          $ent = new stdclass;
          $ent->id = $row['userid'];
          $ent->label = "$row[userid] - $row[fullname]";
          $options[] = $ent;
          $seen[$ent->id] = $ent;
        }
        if ($issue && isset($issue->{$this->name})) {
          $val = $issue->{$this->name};
          if (!isset($seen[$val])) {
            $ent = new stdclass;
            $ent->id = $val;
            $ent->label = $val;
            $options[] = $ent;
          }
        }
        $data['options'] = $options;
        $data['type'] = 'select';
        break;
    }
    return $data;
  }


  function flattenMultiSelectValue($value) {
    //error_log("flattenValue: " . json_encode($value));
    $r = array_keys((array)$value);
    return join('|', $r);
  }

  function loadMultiSelectValue($value) {
    $ret = new stdclass;
    $data = $this->ticketData();
    $opts = array();
    foreach ($data['options'] as $ent) {
      $opts[$ent->id] = $ent;
    }
    if (strlen($value)) {
      foreach (explode('|', $value) as $id) {
        $ret->{$id} = $opts[$id];
      }
    }
    return $ret;
  }

  function flattenValue($value) {
    if ($this->type == 'multiselect') {
      return $this->flattenMultiSelectValue($value);
    }
    return $value;
  }

  function loadValue($value) {
    if ($this->type == 'multiselect') {
      return $this->loadMultiSelectValue($value);
    }
    return $value;
  }

}

class MTrackTicket_CustomFields
  implements IMTrackIssueListener, IMTrackIssueListener3
{
  private $fields = null;

  var $field_types = array(
    'text' => 'MTrackTicket_CustomField',
    'multi' => 'MTrackTicket_CustomField',
    'wiki' => 'MTrackTicket_CustomField',
    'shortwiki' => 'MTrackTicket_CustomField',
    'select' => 'MTrackTicket_CustomField',
    'user' => 'MTrackTicket_CustomField',
    'multiselect' => 'MTrackTicket_CustomField',
  );

  function getFieldTypes() {
    $ret = array();
    foreach ($this->field_types as $tname => $cls) {
      $f = new $cls;
      $f->type = $tname;
      $ret[$tname] = $f->getTypeLabel();
    }
    return $ret;
  }

  function registerFieldType($type, $className) {
    $this->field_types[$type] = $className;
  }

  function save() {
    $this->alterSchema();

    $fieldlist = join(',', array_keys($this->fields));
    MTrackConfig::set('ticket', 'customfields', $fieldlist);

    foreach ($this->fields as $field) {
      $field->save();
    }
  }

  function fieldByName($name, $createAsType = false) {
    $this->init();
    $name = MTrackTicket_CustomField::canonName($name);
    if (!isset($this->fields[$name]) && $createAsType !== false) {
      if ($createAsType === true) {
        $cls = 'MTrackTicket_CustomField';
      } else {
        if (!isset($this->field_types[$createAsType])) {
          $cls = 'MTrackTicket_CustomField';
        } else {
          $cls = $this->field_types[$createAsType];
        }
      }
      $field = new $cls;
      $field->name = $name;
      $this->fields[$name] = $field;
    } else if (!isset($this->fields[$name])) {
      return null;
    }
    return $this->fields[$name];
  }

  function deleteField($field) {
    $this->init();
    if (!($field instanceof MTrackTicket_CustomField)) {
      $field = $this->fieldByName($field);
    }
    if (!($field instanceof MTrackTicket_CustomField)) {
      throw new Exception("can't delete an unknown field");
    }
    unset($this->fields[$field->name]);
  }

  function vetoMilestone(MTrackIssue $issue,
      MTrackMilestone $ms, $assoc = true) {
    return true;
  }
  function vetoKeyword(MTrackIssue $issue,
      MTrackKeyword $kw, $assoc = true) {
    return true;
  }
  function vetoComponent(MTrackIssue $issue,
      MTrackComponent $comp, $assoc = true) {
    return true;
  }
  function vetoProject(MTrackIssue $issue,
      MTrackProject $proj, $assoc = true) {
    return true;
  }
  function vetoComment(MTrackIssue $issue, $comment) {
    return true;
  }
  function vetoSave(MTrackIssue $issue, $oldFields) {
    return true;
  }

  function _orderField($a, $b) {
    $diff = $a->order - $b->order;
    if ($diff == 0) {
      return strnatcasecmp($a->label, $b->label);
    }
    return $diff;
  }

  function getFields() {
    $this->init();
    return $this->fields;
  }

  function getGroupedFields() {
    $this->init();
    $grouped = array();
    foreach ($this->fields as $field) {
      $grouped[$field->group][$field->name] = $field;
    }
    $result = array();
    $names = array_keys($grouped);
    asort($grouped);
    foreach ($grouped as $name => $group) {
      uasort($group, array($this, '_orderField'));
      $result[$name] = $group;
    }
    return $result;
  }

  function augmentLoadedIssue(MTrackIssue $issue) {
    $this->init();
    foreach ($this->fields as $field) {
      if (!isset($issue->{$field->name})) continue;
      $issue->{$field->name} = $field->loadValue($issue->{$field->name});
    }
  }

  function augmentFormFields(MTrackIssue $issue, &$fieldset) {
    $grouped = $this->getGroupedFields();
    foreach ($grouped as $group) {
      foreach ($group as $field) {
        $fieldset[$field->group][$field->name] = $field->ticketData($issue);
      }
    }
  }

  function augmentSaveParams(MTrackIssue $issue, &$params) {
    $this->init();
    foreach ($this->fields as $field) {
      if (strlen($field->default) && !isset($issue->{$field->name})) {
        $issue->{$field->name} = $field->default;
      }
      if (isset($issue->{$field->name})) {
        $params[$field->name] = $field->flattenValue($issue->{$field->name});
      }
    }
  }
  function augmentIndexerFields(MTrackIssue $issue, &$idx) {
    $this->init();
    foreach ($this->fields as $field) {
      $idx[$field->name] = $field->flattenValue($issue->{$field->name});
    }
  }

  function applyPOSTData(MTrackIssue $issue, $post) {
    $changes = 0;
    $this->init();
    foreach ($this->fields as $field) {
      if (!isset($post[$field->name])) {
        continue;
      }
      $issue->{$field->name} = $post[$field->name];
      $changes++;
    }
    return $changes;
  }

  function alterSchema() {
    $this->init();
    $names = array();
    foreach ($this->fields as $field) {
      $names[] = $field->name;
    }
    $db = MTrackDB::get();
    try {
      $db->exec("select " . join(', ', $names) . ' from tickets limit 1');
    } catch (Exception $e) {
      foreach ($names as $name) {
        try {
          $db->exec("ALTER TABLE tickets add column $name text");
        } catch (Exception $e) {
        }
      }
    }
  }

  function __construct() {
    MTrackIssue::registerListener($this);
  }

  function init() {
    if (is_array($this->fields)) return;
    $this->fields = array();

    /* read in custom fields from ini */
    $fieldlist = MTrackConfig::get('ticket', 'customfields');
    if ($fieldlist) {
      $fieldlist = preg_split("/\s*,\s*/", $fieldlist);
      foreach ($fieldlist as $fieldname) {
        $field = MTrackTicket_CustomField::load($fieldname);
        $this->fields[$field->name] = $field;
      }
    }
  }

  static $me = null;
  static function getInstance() {
    if (self::$me !== null) {
      return self::$me;
    }
    self::$me = new MTrackTicket_CustomFields;
    return self::$me;
  }
}

MTrackTicket_CustomFields::getInstance();

