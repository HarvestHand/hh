<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

/* Note: individual user editing is carried out in web/user.php */

MTrackACL::requireAnyRights('User', 'modify');

mtrack_head("Administration - Users");
mtrack_admin_nav();

?>
<h1>Users</h1>
<?php
  echo "<form method='get' action=\"{$ABSWEB}admin/user.php\">";
  $find = htmlentities(trim($_GET['find']), ENT_QUOTES, 'utf-8');
?>
<p>To find an user, enter their name, userid or email address in the box
below and click search; matches will be shown in the list below.
</p>
<input type="text" name="find" value="<?php echo $find ?>">
<button type="submit">Find User</button>
</form>
<p>
Select a user below to edit them, or click the "Add" button to create
a new user.
</p>

<?php

$limit = 15;
$offset = isset($_GET['off']) ? (int)$_GET['off'] : 0;

if (strlen($find)) {
  $sql =
    "select distinct i.userid, fullname, email, active from userinfo i left join useraliases a on i.userid = a.userid where i.userid like '%$find%' or fullname like '%$find%' or email like '%$find%' or a.alias like '%$find%' order by active desc, i.userid limit $limit offset $offset";
} else {
  $sql = "select userid, fullname, email, active from userinfo order by case active when 1 then 0 else 1 end, userid limit $limit offset $offset";
}

echo "<table>\n";
foreach (MTrackDB::q($sql) as $row) {

  $uid = $row[0];
  $name = htmlentities($row[1], ENT_QUOTES, 'utf-8');
  $email = htmlentities($row[2], ENT_QUOTES, 'utf-8');
  $class = $row[3] == '1' ? 'activeuser' : 'inactiveuser';

  echo "<tr class='$class'>",
    "<td>" . mtrack_username($uid) . "</td>" .
    "<td>$name</td>",
    "<td>$email</td>",
    "</tr>\n";

}
echo "</table><br>";
if ($offset > 0) {
  echo "<a href=\"{$ABSWEB}admin/user.php?off=" . ($offset - $limit) . "\">Previous</a> ";
}
echo "<a href=\"{$ABSWEB}admin/user.php?off=" . ($offset + $limit) . "\">Next</a>";
echo "<br><br>";

echo "<h2>Add User</h2>";
echo "<form method='get' action=\"{$ABSWEB}user.php\">";
?>
<p>
To create a new user, enter the userid (typically the "short" login name) that
you want to use in the box below, and click "Create".
</p>
<input type="text" name="user" value="">
<button type="submit">Create User</button>
</form>
<?php

mtrack_foot();

