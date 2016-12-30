<?php # vim:ts=2:sw=2:et:
include '../../inc/common.php';

MTrackACL::requireAllRights("Tickets", 'create');
session_start();

$field_aliases = array(
  'state' => 'status',
  'pri' => 'priority',
  'id' => 'ticket',
  'type' => 'classification',
);
$supported_fields = array(
  'classification',
  'ticket',
  'milestone',
  '-milestone',
  '+milestone',
  'summary',
  'status',
  'priority',
  'estimated',
  'owner',
  'type',
  'component',
  '-component',
  '+component',
  'description'
);
foreach ($supported_fields as $i => $f) {
  unset($supported_fields[$i]);
  $supported_fields[$f] = $f;
}

$C = MTrackTicket_CustomFields::getInstance();
foreach ($C->getFields() as $f) {
  $name = substr($f->name, 2);
  $supported_fields[$f->name] = $f->name;
  if (!isset($field_aliases[$name])) {
    $field_aliases[$name] = $f->name;
  }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  if (isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] == 0
      && is_uploaded_file($_FILES['csvfile']['tmp_name'])) {
    ini_set('auto_detect_line_endings', true);
    $fp = fopen($_FILES['csvfile']['tmp_name'], 'r');
    $header = fgetcsv($fp);
    $err = array();
    $output = array();
    foreach ($header as $i => $name) {
      $name = strtolower($name);
      if (isset($field_aliases[$name])) {
        $name = $field_aliases[$name];
      }
      if (!isset($supported_fields[$name])) {
        $err[] = "Unsupported field: $name";
      }
      $header[$i] = $name;
    }
    $db = MTrackDB::get();
    $db->beginTransaction();
    MTrackChangeset::$use_txn = false;
    $todo = array();
    do {
      $line = fgetcsv($fp);
      if ($line === false) break;

      $item = array();
      foreach ($header as $i => $name) {
        $item[$name] = $line[$i];
      }

      if (isset($item['ticket']) && strlen($item['ticket'])) {
        $id = $item['ticket'];
        if ($id[0] == '#') {
          $id = substr($id, 1);
        }
        try {
          $tkt = MTrackIssue::loadByNSIdent($id);
          if ($tkt == null) {
            $err[] = "No such ticket $id";
            continue;
          }
        } catch (Exception $e) {
          $err[] = $e->getMessage();
          continue;
        }
        $output[] = "<b>Updating ticket $tkt->nsident</b><br>\n";
      } else {
        $tkt = new MTrackIssue;
        $tkt->priority = 'normal';
        $tkt->get_next_nsident();
        $output[] = "<b>Creating ticket $tkt->nsident<b><br>\n";
      }
      $CS = MTrackChangeset::begin("ticket:X", $_POST['comment']);
      if (strlen(trim($_POST['comment']))) {
        $tkt->addComment($_POST['comment']);
      }
      foreach ($item as $name => $value) {
        if ($name == 'ticket') {
          continue;
        }
        $output[] = "$name => $value<br>\n";
        try {
          switch ($name) {
            case 'summary':
            case 'description':
            case 'classification':
            case 'priority':
            case 'severity':
            case 'changelog':
            case 'owner':
            case 'cc':
            case 'estimated':
            case 'status':
              $tkt->$name = strlen($value) ? $value : null;
              break;
            case 'milestone':
              if (strlen($value)) {
                foreach ($tkt->getMilestones() as $mid) {
                  $tkt->dissocMilestone($mid);
                }
                $tkt->assocMilestone($value);
              }
              break;
            case '+milestone':
              if (strlen($value)) {
                $tkt->assocMilestone($value);
              }
              break;
            case '-milestone':
              if (strlen($value)) {
                $tkt->dissocMilestone($value);
              }
              break;
            case 'component':
              if (strlen($value)) {
                foreach ($tkt->getComponents() as $mid) {
                  $tkt->dissocComponent($mid);
                }
                $tkt->assocComponent($value);
              }
              break;
            case '+component':
              if (strlen($value)) {
                $tkt->assocComponent($value);
              }
              break;
            case '-component':
              if (strlen($value)) {
                $tkt->dissocComponent($value);
              }
              break;
            default:
              if (!strncmp($name, 'x_', 2)) {
                $tkt->{$name} = $value;
              }
              break;
          }
        } catch (Exception $e) {
          $err[] = $e->getMessage();
        }
      }
      $tkt->save($CS);
      $CS->setObject("ticket:" . $tkt->tid);

    } while (true);
    $_SESSION['admin.import.result'] = array($err, $output);
    if (count($err)) {
      $db->rollback();
    } else {
      $db->commit();
    }
  }
  header("Location: {$ABSWEB}admin/importcsv.php");
  exit;
}

if (isset($_SESSION['admin.import.result'])) {
  list($err, $info) = $_SESSION['admin.import.result'];
  unset($_SESSION['admin.import.result']);

  mtrack_head(count($err) ? 'Import Failed' : 'Import Complete');

  foreach ($info as $line) {
    echo $line;
  }

  if (count($err)) {
    echo "The following errors were encountered:<br>\n";
    foreach ($err as $msg) {
      echo htmlentities($msg) . "<br>\n";
    }
    echo "<br><b>No changes were committed</b><br>\n";
  } else {
    echo "<br><b>Done!</b>\n";
  }

  mtrack_foot();
  exit;
}

mtrack_head('Import');

?>
<h1>Import/Update via CSV</h1>

<p>
You may use this facility to change ticket properties en-masse by uploading
a CSV file.
</p>

<ul>
  <li>If a ticket column is present and non-empty,
    that ticket will be updated</li>
  <li>If there is no ticket column, or the ticket column is empty,
    then a ticket will be created</li>
  <li>If any errors are detected, none of the changes from the CSV file
    will be applied</li>
</ul>

<p>
The input file must be a CSV file with the field names on the first line.
</p>

<p>
The following fields are supported:
</p>

<dl>
  <dt>ticket</dt>
  <dd>The ticket number</dd>

  <dt>milestone</dt>
  <dd>The value to use for the milestone.  If updating an existing ticket,
   this field will remove any other milestones in the ticket and set it to
   only this value.
  </dd>

  <dt>-milestone</dt>
  <dd>Removes a milestone; if the ticket is associated with the named milestone,
   it will be removed from that milestone.
  </dd>

  <dt>+milestone</dt>
  <dd>Associates the ticket with the named milestone, preserving any other
  milestones currently associated with the ticket.
  </dd>

  <dt>summary</dt>
  <dd>Sets the summary for the ticket</dd>

  <dt>status or state</dt>
  <dd>Sets the state of the ticket; can be one of the configured ticket states
  </dd>

  <dt>priority</dt>
  <dd>Sets the priority; can be one of the configured priorities</dd>

  <dt>owner</dt>
  <dd>Sets the owner</dd>

  <dt>type</dt>
  <dd>Sets the ticket type</dd>

  <dt>estimated</dt>
  <dd>Estimated time (hours)</dd>

  <dt>component</dt>
  <dd>Sets the component, replacing all other component associations</dd>

  <dt>-component</dt>
  <dd>Removes association with the named component</dd>

  <dt>+component</dt>
  <dd>Associates with the named component, preserving existing associations</dd>

  <dt>description</dt>
  <dd>Sets the description of the ticket</dd>

<?php

foreach ($C->getFields() as $f) {
  $name = substr($f->name, 2);
  if (!isset($field_aliases[$name]) || $field_aliases[$name] != $f->name) {
    $name = $f->name;
    echo "<dt>$name</dt>\n";
  } else {
    echo "<dt>$name</dt>\n";
    echo "<dt>$f->name</dt>\n";
  }
  echo "<dd>" . htmlentities($f->label, ENT_QUOTES, 'utf-8') . "\n";

  if ($f->type == 'select') {
    echo "<br>Value may be one of:<br>";
    $data = $f->ticketData();
    foreach ($data['options'] as $opt) {
      echo " <tt>" . htmlentities($opt, ENT_QUOTES, 'utf-8') . "</tt><br>";
    }
  }

  echo "</dd>\n";
}

?>

</dl>

<h2>Import</h2>

<p>Enter a comment in the box below; it will be added as a comment to
all affected tickets</p>

<form method='post' enctype='multipart/form-data'>
  <textarea name='comment' id='comment'
    class='code wiki' rows='4' cols='78'></textarea>
  <input type='file' name='csvfile'>
  <input type='submit' value='Import'>
</form>

<?php
mtrack_foot();

