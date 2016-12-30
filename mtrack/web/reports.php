<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../inc/common.php';

mtrack_head("Reports");
?>
<h1>Available Reports</h1>

<p>
  The reports below are constructed using SQL.  You may also
  use the <a href="<?php echo $ABSWEB ?>query.php">Custom Query</a>
  page to create a report on the fly.
</p>

<table>
<tr>
  <th>Report</th>
  <th>Title</th>
</tr>
<?php
foreach (MTrackDB::q("select rid, summary from reports order by rid"
    )->fetchAll(PDO::FETCH_ASSOC) as $row)
{
  $url = "${ABSWEB}report.php/$row[rid]";
  $t = "<a href='$url'>{" . $row['rid'] . "}</a>";
  $s = htmlentities($row['summary'], ENT_COMPAT, 'utf-8');
  $s = "<a href='$url'>$s</a>";

  echo <<<HTML
<tr><td>$t</td><td>$s</td></tr>
HTML;
}
?>
</table>
<?php
if (MTrackACL::hasAllRights('Reports', 'create')) {
?>
<button class='btn btn-primary' type="submit" name="edit"
  onclick="document.location.href = ABSWEB + 'report.php?edit=1'; return false;"
><i class='icon-plus icon-white'></i> Create Report</button>
<?php
}
echo <<<HTML
<button class='btn btn-success' type="submit" name="edit"
  onclick="document.location.href = ABSWEB + 'query.php'; return false;"
><i class='icon-white icon-search'></i> Ticket Search</button>
HTML;

mtrack_foot();

