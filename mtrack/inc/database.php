<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

interface IMTrackDBExtension {
  /** allows the extension an opportunity to adjust the environment;
   * register sqlite functions or otherwise tweak parameters */
  function onHandleCreated(PDO $db);
}

class MTrackDBSchema_Table {
  var $name;
  var $fields;
  var $keys;
  var $triggers;

  /* compares two tables; returns true if they are identical,
   * false if the definitions are altered */
  function sameAs(MTrackDBSchema_Table $other) {
    if ($this->name != $other->name) {
      throw new Exception("can only compare tables with the same name!");
    }
    foreach (array('fields', 'keys', 'triggers') as $propname) {
      if (!is_array($this->{$propname})) continue;
      foreach ($this->{$propname} as $f) {
        if (!isset($other->{$propname}[$f->name])) {
#          echo "$propname $f->name is new\n";
          return false;
        }
        $o = clone $other->{$propname}[$f->name];
        $f = clone $f;
        unset($o->comment);
        unset($f->comment);
        if ($f != $o) {
#          echo "$propname $f->name are not equal\n";
#          var_dump($f);
#          var_dump($o);
          return false;
        }
      }
      if (!is_array($other->{$propname})) continue;
      foreach ($other->{$propname} as $f) {
        if (!isset($this->{$propname}[$f->name])) {
#          echo "$propname $f->name was deleted\n";
          return false;
        }
      }
    }

    return true;
  }
}

interface IMTrackDBSchema_Driver {
  function setDB(PDO $db);
  function determineVersion();
  function createTable(MTrackDBSchema_Table $table);
  function alterTable(MTrackDBSchema_Table $from, MTrackDBSchema_Table $to);
  function dropTable(MTrackDBSchema_Table $table);
};

class MTrackDBSchema_Generic implements IMTrackDBSchema_Driver {
  var $db;
  var $typemap = array();

  function setDB(PDO $db) {
    $this->db = $db;
  }

  function determineVersion() {
    try {
      $q = $this->db->query('select version from mtrack_schema');
      if ($q) {
        foreach ($q as $row) {
          return $row[0];
        }
      }
    } catch (Exception $e) {
    }
    return null;
  }

  function computeFieldCreate($f) {
    $str = "\t$f->name ";
    $str .= isset($this->typemap[$f->type]) ? $this->typemap[$f->type] : $f->type;
    if (isset($f->nullable) && $f->nullable == '0') {
      $str .= ' NOT NULL ';
    }
    if (isset($f->default)) {
      if (!strlen($f->default)) {
        $str .= " DEFAULT ''";
      } else {
        $str .= " DEFAULT $f->default";
      }
    }
    return $str;
  }

  function computeIndexCreate($table, $k) {
    switch ($k->type) {
      case 'unique':
        $kt = ' UNIQUE ';
        break;
      case 'multiple':
      default:
        $kt = '';
    }
    return "CREATE $kt INDEX $k->name on $table->name (" . join(', ', $k->fields) . ")";
  }

  function createTable(MTrackDBSchema_Table $table)
  {
    echo "Create $table->name\n";

    $pri_key = null;

    $sql = array();
    foreach ($table->fields as $f) {
      if ($f->type == 'autoinc') {
        $pri_key = $f->name;
      }
      $str = $this->computeFieldCreate($f);
      $sql[] = $str;
    }

    if (is_array($table->keys)) foreach ($table->keys as $k) {
      if ($k->type != 'primary') continue;
      if ($pri_key !== null) continue;
      $sql[] = "\tprimary key (" . join(', ', $k->fields) . ")";
    }

    $sql = "CREATE TABLE $table->name (\n" .
      join(",\n", $sql) .
      ")\n";

#    echo $sql;

    $this->db->exec($sql);

    if (is_array($table->keys)) foreach ($table->keys as $k) {
      if ($k->type == 'primary') continue;
      $this->db->exec($this->computeIndexCreate($table, $k));
    }
  }

  function alterTable(MTrackDBSchema_Table $from, MTrackDBSchema_Table $to)
  {
    /* if keys have changed, we drop the old key definitions before changing the columns */

    echo "Need to alter $from->name\n";
    throw new Exception("bang!");
  }

  function dropTable(MTrackDBSchema_Table $table)
  {
    echo "Drop $table->name\n";
    $this->db->exec("drop table $table->name");
  }
}

class MTrackDBSchema_SQLite extends MTrackDBSchema_Generic {

  function determineVersion() {
    /* older versions did not have a schema version table, so we dance
     * around a little bit, but only for sqlite, as those older versions
     * didn't support other databases */
    try {
      $q = $this->db->query('select version from mtrack_schema');
      if ($q) {
        foreach ($q as $row) {
          return $row[0];
        }
      }
    } catch (Exception $e) {
    }

    /* do we have any tables at all? if we do, we treat that as schema
     * version 0 */
    foreach ($this->db->query('select count(*) from sqlite_master') as $row) {
      if ($row[0] > 0) {
        $this->db->exec(
          'create table mtrack_schema (version integer not null)');
        return 0;
      }
    }
    return null;
  }

  var $typemap = array(
    'autoinc' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
  );

  function createTable(MTrackDBSchema_Table $table)
  {
    parent::createTable($table);
  }

  function alterTable(MTrackDBSchema_Table $from, MTrackDBSchema_Table $to)
  {
    $tname = $from->name . '_' . uniqid();

    $sql = array();
    foreach ($to->fields as $f) {
      if ($f->type == 'autoinc') {
        $pri_key = $f->name;
      }
      $str = $this->computeFieldCreate($f);
      $sql[] = $str;
    }

    $sql = "CREATE TEMPORARY TABLE $tname (\n" .
      join(",\n", $sql) .
      ")\n";

    $this->db->exec($sql);

    /* copy old data into this table */
    $sql = "INSERT INTO $tname (";
    $names = array();
    foreach ($from->fields as $f) {
      if (!isset($to->fields[$f->name])) continue;
      $names[] = $f->name;
    }
    $sql .= join(', ', $names);
    $sql .= ") SELECT " . join(', ', $names) . " from $from->name";

    #echo "$sql\n";
    $this->db->exec($sql);

    $this->db->exec("DROP TABLE $from->name");
    $this->createTable($to);
    $sql = "INSERT INTO $from->name (";
    $names = array();
    foreach ($from->fields as $f) {
      if (!isset($to->fields[$f->name])) continue;
      $names[] = $f->name;
    }
    $sql .= join(', ', $names);
    $sql .= ") SELECT " . join(', ', $names) . " from $tname";
    #echo "$sql\n";
    $this->db->exec($sql);
    $this->db->exec("DROP TABLE $tname");
  }


}

class MTrackDBSchema_pgsql extends MTrackDBSchema_Generic {
  var $typemap = array(
    'autoinc' => 'SERIAL UNIQUE',
    'timestamp' => 'timestamp with time zone',
    'blob' => 'bytea',
  );

  function alterTable(MTrackDBSchema_Table $from, MTrackDBSchema_Table $to)
  {
    $sql = array();
    $actions = array();

    /* if keys have changed, we drop the old key definitions before changing the columns */
    if (is_array($from->keys)) foreach ($from->keys as $k) {
      if (!isset($to->keys[$k->name]) || $to->keys[$k->name] != $k) {
        if ($k->type == 'primary') {
          $actions[] = "DROP CONSTRAINT {$from->name}_pkey";
        } else {
          $sql[] = "DROP INDEX $k->name";
        }
      }
    }

    foreach ($from->fields as $f) {
      if (!isset($to->fields[$f->name])) {
        $actions[] = "DROP COLUMN $f->name";
        continue;
      }
    }
    foreach ($to->fields as $f) {
      if (isset($from->fields[$f->name])) continue;
      $actions[] = "ADD COLUMN " . $this->computeFieldCreate($f);
    }

    /* changed and new keys */
    if (is_array($from->keys)) foreach ($from->keys as $k) {
      if (isset($to->keys[$k->name]) && $to->keys[$k->name] != $k) {
        if ($k->type == 'primary') {
          $actions[] = "ADD primary key (" . join(', ', $k->fields) . ")";
        } else {
          $sql[] = $this->computeIndexCreate($to, $k);
        }
      }
    }
    if (is_array($to->keys)) foreach ($to->keys as $k) {
      if (isset($from->keys[$k->name])) continue;
      if ($k->type == 'primary') {
        $actions[] = "ADD primary key (" . join(', ', $k->fields) . ")";
      } else {
        $sql[] = $this->computeIndexCreate($to, $k);
      }
    }

    if (count($actions)) {
      $sql[] = "ALTER TABLE $from->name " . join(",\n", $actions);
    }
    echo "Need to alter $from->name\n";
    echo "SQL:\n";
    var_dump($sql);
    foreach ($sql as $s) {
      $this->db->exec($s);
    }
  }
}

class MTrackDBSchema {
  var $tables;
  var $version;
  var $post;

  function __construct($filename) {
    $s = simplexml_load_file($filename);

    $this->version = (int)$s['version'];

    /* fabricate a table to hold the schema info */
    $table = new MTrackDBSchema_Table;
    $table->name = 'mtrack_schema';
    $f = new stdclass;
    $f->name = 'version';
    $f->type = 'integer';
    $f->nullable = '0';
    $table->fields[$f->name] = $f;
    $this->tables[$table->name] = $table;

    foreach ($s->table as $t) {
      $table = new MTrackDBSchema_Table;
      $table->name = (string)$t['name'];

      foreach ($t->field as $f) {
        $F = new stdclass;
        foreach ($f->attributes() as $k => $v) {
          $F->{(string)$k} = (string)$v;
        }
        if (isset($f->comment)) {
          $F->comment = (string)$f->comment;
        }
        $table->fields[$F->name] = $F;
      }
      foreach ($t->key as $k) {
        $K = new stdclass;
        $K->fields = array();
        if (isset($k['type'])) {
          $K->type = (string)$k['type'];
        } else {
          $K->type = 'primary';
        }
        foreach ($k->field as $f) {
          $K->fields[] = (string)$f;
        }
        if (isset($k['name'])) {
          $K->name = (string)$k['name'];
        } else {
          $K->name = sprintf("idx_%s_%s", $table->name, join('_', $K->fields));
        }
        $table->keys[$K->name] = $K;
      }

      $this->tables[$table->name] = $table;
    }
    foreach ($s->post as $p) {
      $this->post[(string)$p['driver']] = (string)$p;
    }

    /* apply custom ticket fields */
    if (isset($this->tables['tickets'])) {
      $table = $this->tables['tickets'];
      $custom = MTrackTicket_CustomFields::getInstance();
      foreach ($custom->getFields() as $field) {
        $f = new stdclass;
        $f->name = $field->name;
        $f->type = 'text';
        $table->fields[$f->name] = $f;
      }
    }
  }
}

class MTrackDB {
  static $db = null;
  static $extensions = array();
  static $queries = 0;
  static $query_strings = array();

  static function registerExtension(IMTrackDBExtension $ext) {
    self::$extensions[] = $ext;
  }

  // given a unix timestamp, return a value timestamp string
  // suitable for use with the database
  static function unixtime($unix) {
    list($unix) = explode('.', $unix, 2);
    if ($unix == 0) {
      return null;
    }
    if ($unix < 10) {
      throw new Exception("unix time $unix is too small\n");
    }
    $d = date_create("@$unix", new DateTimeZone('UTC'));
    // 2008-12-22T05:42:42.285445Z
    if (!is_object($d)) {
      throw new Exception("failed to create date for time $unix");
    }
    return $d->format('Y-m-d\TH:i:s.u\Z');
  }

  static function get() {
    if (self::$db == null) {
      $dsn = MTrackConfig::get('core', 'dsn');
      if ($dsn === null) {
        $dsn = 'sqlite:' . MTrackConfig::get('core', 'dblocation');
      }
      $db = new PDO($dsn);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$db = $db;

      if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'sqlite') {
        $db->sqliteCreateAggregate('mtrack_group_concat',
            array('MTrackDB', 'group_concat_step'),
            array('MTrackDB', 'group_concat_final'));

        $db->sqliteCreateFunction('mtrack_cleanup_attachments',
            array('MTrackAttachment', 'attachment_row_deleted'));

        $db->sqliteCreateFunction('greatest', 'max');
      }

      foreach (self::$extensions as $ext) {
        $ext->onHandleCreated($db);
      }
    }
    return self::$db;
  }

  static function lastInsertId($tablename, $keyfield) {
    if (!strlen($tablename) || !strlen($keyfield)) {
      throw new Exception("missing tablename or keyfield");
    }
    if (self::$db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
      return self::$db->lastInsertId($tablename . '_' . $keyfield . '_seq');
    } else {
      return self::$db->lastInsertId();
    }
  }

  static function group_concat_step($context, $rownum, $value)
  {
    if (!is_array($context)) {
      $context = array();
    }
    $context[] = $value;
    return $context;
  }

  static function group_concat_final($context, $rownum)
  {
    if ($context === null) {
      return null;
    }
    asort($context);
    return join(", ", $context);
  }

  static function esc($str) {
    return "'" . str_replace("'", "''", $str) . "'";
  }

  /* issue a query, passing optional parameters */
  static function q($sql) {
    self::$queries++;
    if (isset(self::$query_strings[$sql])) {
      self::$query_strings[$sql]++;
    } else {
      self::$query_strings[$sql] = 1;
    }
    $params = func_get_args();
    array_shift($params);
    $db = self::get();
#      echo "<br>SQL: $sql\n";
#      var_dump($params);
#echo "<br>";
    try {
      if (count($params)) {
        $q = $db->prepare($sql);
        $q->execute($params);
      } else {
        $q = $db->query($sql);
      }
    } catch (Exception $e) {
      error_log($e->getMessage() . "  ->  $sql");
      ob_start();
      debug_print_backtrace();
      $bt = ob_get_contents();
      ob_end_clean();
      error_log($bt);
      throw $e;
    }
    return $q;
  }
}

