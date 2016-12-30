<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

MTrackACL::requireAnyRights('Enumerations', 'modify');

$C = MTrackTicket_CustomFields::getInstance();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  $name = $_POST['name'];
  $type = $_POST['type'];
  $group = $_POST['group'];
  $label = $_POST['label'];
  $template = $_POST['template'];
  $options = $_POST['options'];
  $default = $_POST['default'];
  $order = (int)$_POST['order'];

  if (!isset($C->field_types[$type])) {
    throw new Exception("invalid type $type");
  }

  $name = MTrackTicket_CustomField::canonName($name);
  if (!preg_match("/^x_[a-z_]+$/", $name)) {
    throw new Exception("invalid field name $name");
  }

  $field = $C->fieldByName($name, true);

  if (isset($_POST['delete'])) {
    $C->deleteField($field);
  } else {
    $field->group = $group;
    $field->label = $label;
    $field->type = $type;
    $field->order = $order;
    $field->options = $options;
    $field->default = $default;
    $field->template = $template;
  }

  $C->save();
  MTrackConfig::save();

  header("Location: ${ABSWEB}admin/customfield.php");
  exit;
}

mtrack_head("Administration - Custom Fields");
mtrack_admin_nav();
echo "<h1>Custom Fields</h1>";


$field = null;
if (isset($_GET['add'])) {
  $field = new MTrackTicket_CustomField;
  $field->type = 'text';
  $field->name = 'x_fieldname';
  $field->label = 'The Label';
  $field->group = 'Custom Fields';
} else if (isset($_GET['field'])) {
  $field = $C->fieldByName($_GET['field']);
  if ($field === null) {
    throw new Exception("No such field " . $_GET['field']);
  }
}

if ($field) {
  $type = mtrack_select_box('type', $C->getFieldTypes(), $field->type);
  $name = htmlentities($field->name, ENT_QUOTES, 'utf-8');
  $label = htmlentities($field->label, ENT_QUOTES, 'utf-8');
  $group = htmlentities($field->group, ENT_QUOTES, 'utf-8');
  $options = htmlentities($field->options, ENT_QUOTES, 'utf-8');
  $default = htmlentities($field->default, ENT_QUOTES, 'utf-8');
  $template = htmlentities($field->template, ENT_QUOTES, 'utf-8');
  $order = $field->order;
?>
<form method='post' id='editfield'>
  <fieldset>
  <legend>Edit Custom Field</legend>
  <table>
    <tr>
      <td><label for='name'>Name</label></td>
      <td><input type='text' name='name' value='<?php echo $name ?>'><br>
        <em>The field name to use in the database.  Must have a prefix
          of 'x_' and must only contain characters a-z or underscore.
          You cannot rename a field; once it is created, it stays
          in the database.  You can use the label field below if you
          want to change the presentation.</em></td>
    </tr>
    <tr>
      <td><label for='type'>Type</label></td>
      <td><?php echo $type ?></td>
    </tr>
    <tr>
      <td><label for='label'>Label</label></td>
      <td><input type='text' name='label' value='<?php echo $label ?>'><br>
        <em>The label to display on the ticket screen</em></td>
    </tr>
    <tr>
      <td><label for='group'>Group</label></td>
      <td><input type='text' name='group' value='<?php echo $group ?>'><br>
        <em>Fields with the same group are grouped together on the ticket
          editing screen</em></td>
    </tr>
    <tr>
      <td><label for='default'>Default</label></td>
      <td><input type='text' name='default' value='<?php echo $default ?>'><br>
        <em>Enter the default value for this field</em></td>
    </tr>

    <tr>
      <td><label for='options'>Options</label></td>
      <td><input type='text' name='options' value='<?php echo $options ?>'><br>
        <em>For Select and Multi-Select types, enter a list of possible
          choices here, separated by a pipe character |</em></td>
    </tr>
    <tr>
      <td><label for='order'>Sort Order</label></td>
      <td>
        <input type='text' name='order' value='<?php echo $order ?>'><br>
        <em>Lower means show first.  If two or more fields have same 'order',
          then they are ordered by name</em>
      </td>
    </tr>
    <tr>
      <td><label for='template'>Template</label></td>
      <td><textarea name='template' rows='10' cols='50'><?php echo $template ; ?></textarea><br>
        <em>Optional custom <a href="http://documentcloud.github.com/underscore/#template">Underscore.js template</a> to use when presenting this field in a read-only manner.  The value being rendered is made available to the template in the "value" variable.</em>
      </td>
    </tr>
  </table>
  <button type='submit'>Save</button>
  <button type='submit' name='cancel'>Cancel</button>
  <button type='submit' name='delete' id='delete-field'>Delete</button>
  </fieldset>
</form>
<div id="confirmDeleteDialog" style="display:none"
    title="Are you sure?">
  <p>
    Deleting the field will hide it from the user interface; it will
    not remove it from the database.
  </p>
  <p>
    If you add the field back later on, the data previously entered
    will be visible again.
  </p>
</div>

<script>
$(document).ready(function () {
var delete_button = $('#delete-field');
var edit_form = $('#editfield');

$('#confirmDeleteDialog').dialog({
  autoOpen: false,
  bgiframe: true,
  resizable: false,
  modal: true,
  buttons: {
    'Delete': function() {
      $(this).dialog('close');
      delete_ok = true;
      edit_form.append('<input type="hidden" name="delete" value="delete">');
      delete_button.remove();
      edit_form.submit();
    },
    'Keep': function() {
      $(this).dialog('close');
    }
  }
});
$('#delete-field').click(
  function() {
    $('#confirmDeleteDialog').dialog('open');
    return false;
  }
);
});
</script>
<?php
} else {

$grouped = $C->getGroupedFields();

foreach ($grouped as $groupname => $group) {
  $groupname = htmlentities($groupname, ENT_QUOTES, 'utf-8');
  echo "<b>Group: $groupname</b><br>\n<table>\n";
  foreach ($group as $field) {
    $type = $field->type;
    $label = htmlentities($field->label, ENT_QUOTES, 'utf-8');
    $name = $field->name;
    $name = "<a href=\"{$ABSWEB}admin/customfield.php?field=$name\">$name</a>";
    echo "<tr><td>$name</td><td>$type</td><td>$label</td></tr>\n";
  }
  echo "</table>\n";
}

?>
<form method='get'>
  <input type='hidden' name='add' value='1'>
  <button type='submit'>Add New Field</button>
</form>
<?php
}

mtrack_foot();

