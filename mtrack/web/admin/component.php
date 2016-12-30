<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

MTrackACL::requireAnyRights('Components', 'modify');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['newcomponent']) && strlen($_POST['newcomponent'])) {
    $CS = MTrackChangeset::begin("component:X",
        "Added Component $_POST[newcomponent]");
    $comp = new MTrackComponent;
    $comp->name = $_POST['newcomponent'];
    $comp->setProjects($_POST['newcomponentprojects']);
    $comp->save($CS);
    $CS->setObject("component:$comp->compid");
    $CS->commit();
  }
  foreach ($_POST as $name => $value) {
    if (preg_match("/^comp:(\d+):name$/", $name, $M)) {
      $compid = (int)$M[1];
      $C = MTrackComponent::loadById($compid);
      $changed = false;

      if ($C->name != $_POST["comp:$compid:name"]) {
        $C->name = $_POST["comp:$compid:name"];
        $changed = true;
      }
      if (isset($_POST["comp:$compid:deleted"]) &&
          $_POST["comp:$compid:deleted"] == "on") {
        $deleted = '1';
      } else {
        $deleted = '';
      }
      if ($C->deleted != $deleted) {
        $C->deleted = $deleted;
        $changed = true;
      }
      $plist = $_POST["comp:$compid:projects"];
      if (is_array($plist)) {
        asort($plist);
      }
      if ($plist != $C->getProjects()) {
        $C->setProjects($plist);
        $changed = true;
      }
      if ($changed) {
        $CS = MTrackChangeset::begin("component:$compid",
            "Edit Component $C->name");

        $C->save($CS);
        $CS->commit();
      }
    }
  }
  header("Location: ${ABSWEB}admin/");
  exit;
}

mtrack_head("Administration - Components");
mtrack_admin_nav();

echo "<form method='post'>";
echo "<br><b>Components</b><br>\n";
echo "<table><tr><th>Name</th><th>Projects</th><th>Deleted</th></tr>\n";

$projects = array();
foreach (MTrackDB::q('select projid, name, shortname from projects
    order by name')->fetchAll() as $row) {
  if ($row[1] != $row[2]) {
    $projects[$row[0]] = $row[1] . " ($row[2])";
  } else {
    $projects[$row[0]] = $row[1];
  }
}

$p_by_c = array();
foreach (MTrackDB::q('select compid, projid from components_by_project')
    ->fetchAll() as $row) {
  $p_by_c[$row[0]][$row[1]] = $row[1];
}

foreach (MTrackDB::q('select compid, name, deleted from components order by name')->fetchAll() as $row) {
  $compid = (int)$row[0];
  $name = htmlentities($row[1], ENT_QUOTES, 'utf-8');
  $del = $row[2] ? ' checked="checked" ' : '';
  echo "<tr>" .
    "<td><input type='text' name='comp:$compid:name' value='$name'></td>" .
    "<td>" . mtrack_multi_select_box("comp:$compid:projects",
      "(select to add)", $projects, $p_by_c[$compid]) .
      "</td>" .
      "<td><input type='checkbox' name='comp:$compid:deleted' $del></td>" .
      "</tr>\n";
}

echo "<tr><td><input type='text' name='newcomponent' value=''></td>" .
  "<td>" . mtrack_multi_select_box('newcomponentprojects',
    "(select to add)", $projects) .
    "</td><td>Add a new Component</td></tr>\n";

echo "</table>\n";

echo "<button>Save Changes</button></form>";

mtrack_foot();

