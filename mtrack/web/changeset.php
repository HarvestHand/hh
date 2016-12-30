<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

MTrackACL::requireAllRights('Browser', 'read');

$path = mtrack_get_pathinfo(true);
$pi = $path;
$repo = MTrackSCM::factory($pi);

MTrackACL::requireAllRights("repo:$repo->repoid", 'read');

function get_change_data($pi)
{
  $repo = MTrackSCM::factory($pi);
  $ents = $repo->history(null, 1, 'rev', $pi);
  $data = new stdclass;
  if (!count($ents)) {
    $data->ent = null;
  } else {
    $ent = $ents[0];
    $data->ent = $ent;

    // Determine project from the file list
    $the_proj = $repo->projectFromPath($ent->files);
    if ($the_proj > 1) {
      $proj = MTrackProject::loadById($the_proj);
      $changelog = $proj->adjust_links($ent->changelog, true);
    } else {
      $changelog = $ent->changelog;
    }
    $data->changelog = $changelog;

    if (is_array($ent->files)) foreach ($ent->files as $file) {
      $file->diff = mtrack_diff($repo->diff($file, $ent->rev));
    }
  }

  return $data;
}

function get_change_data_relatives($pi, $rev)
{
  $repo = MTrackSCM::factory($pi);
  $data = new stdclass;
  list($data->parents, $data->kids) = $repo->getRelatedChanges($rev);
  return $data;
}

$data = mtrack_cache('get_change_data', array($path), 864000);
$ent = $data->ent;
if ($ent === null) {
  throw new Exception("invalid parameters");
}

$rdata = mtrack_cache('get_change_data_relatives', array($path, $ent->rev));

if (isset($_GET['fmt']) && $_GET['fmt'] == 'diff') {
  $filename = "$repo->shortname.$ent->rev.diff";
  header("Content-Type: text/plain; name=\"$filename\"");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  echo "Changeset: $repo->shortname $ent->rev\n";
  echo "By: $ent->changeby\n";
  echo "When: $ent->ctime\n";
  echo "\n";
  echo $data->changelog . "\n\n";

  if (is_array($ent->files) && count($ent->files)) {
    foreach ($ent->files as $id => $file) {
      echo "$file->status $file->name\n";
    }
    echo "\n";

    foreach ($ent->files as $id => $file) {
      $fpath = $file->name;
      if ($fpath[0] != '/') $fpath = '/' . $fpath;
      $diff = $repo->diff($file, $ent->rev);
      if (is_resource($diff)) {
        echo stream_get_contents($diff);
      } elseif (is_array($diff)) {
        echo join("\n", $diff);
      } else {
        echo $diff;
      }
    }
  }
  exit;
}

mtrack_head("Changeset " . $ent->rev);

echo "<br><div class='revinfo well'>\n";
echo "Revision: $repo->shortname $ent->rev";
foreach ($ent->branches as $b) {
  echo " " . mtrack_branch($b);
}
foreach ($ent->tags as $t) {
  echo " " . mtrack_tag($t);
}
echo "<br>\n";


echo MTrackWiki::format_to_html($data->changelog);

echo "<div class='changeinfo'>\n";
echo mtrack_username($ent->changeby, array('size' => 32)) . "<br>\n";
echo mtrack_date($ent->ctime, true) . "<br>\n";

if (count($rdata->parents)) {
  echo "Prior:";
  foreach ($rdata->parents as $p) {
    echo " " . mtrack_changeset($p, $repo);
  }
  echo " ";
}

if (count($rdata->kids)) {
  echo "Next:";
  foreach ($rdata->kids as $kid) {
    echo " " . mtrack_changeset($kid, $repo);
  }
}

echo "</div>\n";
echo "</div>\n";

if (is_array($ent->files) && count($ent->files)) {
  echo <<<HTML
<a class='btn' href='${ABSWEB}changeset.php$path?fmt=diff'><i class='icon-download'></i> Download diff</a>
HTML;
  echo "<div class='difffiles'>Affected files:<ul>";
  foreach ($ent->files as $id => $file) {
    echo "<li><a href='#d$id'><b>$file->status</b> $file->name</a></li>\n";
  }
  echo "</ul></div>";

  foreach ($ent->files as $id => $file) {
    $fpath = $file->name;
    if ($fpath[0] != '/') $fpath = '/' . $fpath;
    echo "<a name='d$id'></a><a href='{$ABSWEB}file.php/{$repo->getBrowseRootName()}$fpath?rev=$ent->rev'>$file</a><br>\n";
    $diff = $file->diff; // populated in get_change_data
    if ($diff === null) {
      echo "No diff available.  File status is <b>$file->status</b><br><br>";
    } else {
      echo $diff;
    }
  }
}


mtrack_foot();

