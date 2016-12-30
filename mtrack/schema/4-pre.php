<?php # vim:ts=2:sw=2:et:
# De-dupe components table

$names = array();
foreach ($db->query('select compid, name from components')->fetchAll() as $row)
{
  $names[$row[1]][] = $row[0];
}

foreach ($names as $name => $ids) {
  if (count($ids) == 1) continue;
  echo "Fixing duplicate component: $name\n";
  sort($ids);
  $id = array_shift($ids);
  $change = join(',', $ids);

  $q = $db->prepare("update ticket_components set compid = ? where compid in ($change)");
  $q->execute(array($id));
  $q = $db->prepare("update components_by_project set compid = ? where compid in ($change)");
  $q->execute(array($id));
  $comps = array();
  foreach ($ids as $i) {
    $comps[] = $db->quote("component:$i");
  }
  $comps = join(',', $comps);
  $db->exec("update changes set object = 'component:$id' where object in ($comps)");
  $db->exec("delete from components where compid in ($change)");
}

