<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';
MTrackACL::requireAllRights('Browser', 'read');

$pi = mtrack_get_pathinfo(true);

$data = explode('@', $pi);
$pi = $data[0];
if (isset($data[1])) {
  $_GET['rev'] = $data[1];
}

$crumbs = MTrackSCM::makeBreadcrumbs($pi);
if (!strlen($pi) || $pi == '/') {
  $pi = '/';
} else {
  $repo = MTrackSCM::factory($pi);
}

if (!$repo) {
  throw new Exception("invalid path $pi");
}
MTrackACL::requireAllRights("repo:$repo->repoid", 'read');

if (isset($_GET['rev'])) {
  $file = $repo->file($pi, 'rev', $_GET['rev']);
} else {
  $file = $repo->file($pi);
}

$ent = $file->getChangeEvent();

if (isset($_GET['raw']) && $_GET['raw'] == 1) {
  $filename = basename($pi);
  header("Content-Type: application/octet-stream; name=\"$filename\"");
  header("Content-Disposition: attachment; filename=\"$filename\"");
  fpassthru($file->cat());
  exit;
}

mtrack_head("File $pi @ " . $file->rev);

/* Render a bread-crumb enabled location indicator */
echo "<div class='browselocation'>Location: ";
$location = null;
$last = array_pop($crumbs);
if (isset($_GET['jump'])) {
  $jump = '?jump=' . urlencode($_GET['jump']);
} else {
  $jump = '';
}
foreach ($crumbs as $path) {
  if (!strlen($path)) {
    $path = '[root]';
  } else {
    $location .= '/' . urlencode($path);
  }
  $path = htmlentities($path, ENT_QUOTES, 'utf-8');
  echo "<a href='{$ABSWEB}browse.php$location$jump'>$path</a> / ";
}

echo "$last @ " . mtrack_changeset($ent->rev, $repo);
echo "</div><br>";

echo "<div class='revinfo well'>\n";
echo MTrackWiki::format_to_html($ent->changelog);
echo "<div class='changeinfo'>\n";
echo mtrack_username($ent->changeby, array('size' => 32));
echo "<br>\n";
echo mtrack_date($ent->ctime, true) . "<br>\n";
echo "Revision: $repo->shortname $ent->rev";
foreach ($ent->branches as $b) {
  echo " " . mtrack_branch($b);
}
foreach ($ent->tags as $t) {
  echo " " . mtrack_tag($t);
}
echo "</div></div>\n";

echo "<br><a class='btn' href='{$ABSWEB}log.php/" .
  $repo->getBrowseRootName() .
  htmlentities("/$pi$jump", ENT_QUOTES, 'utf-8') .
  "'>Show revision log</a>";

/* Do we want to show the file? */

$finfo = pathinfo($file->name);
$t = tmpfile();

$data = $file->cat();
stream_copy_to_stream($data, $t);
$data = null;

$info = fstat($t);

$location = stream_get_meta_data($t);
$location = $location['uri'];

$mimetype = mtrack_mime_detect($location, $file->name);
list($major) = explode('/', $mimetype, 2);

// Obscure-ish special cases for mime types;
// some .y files look like old image format data
if ($mimetype == 'image/x-3ds') {
  $major = 'text';
} elseif ($mimetype == 'application/xml') {
  $major = 'text';
}


$p = $_GET;
$p['raw'] = 1;
$raw_url = $ABSWEB . 'file.php' . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '') .
  '?' . http_build_query($p);

echo " <a href='$raw_url' class='btn'><i class='icon-download'></i> Download File</a>\n";

if ($major == 'text') {
  fseek($t, 0);
  $ann = $file->annotate();
  if ($ann === 'DELETED') {
    echo "<div>Deleted</div>\n";
  } else {
    $i = 1;

    $data = stream_get_contents($t);
    $data = MTrackSyntaxHighlight::highlightSource($data, null, $file->name);

    echo <<<HTML
<br>
<br>
<button type='button' class='btn toggle-ann'>Blame</button>
<button type='button' class='btn toggle-line'>Line #s</button>
HTML;
    echo MTrackSyntaxHighlight::getSchemeSelect();
    echo <<<HTML
<script>
$(document).ready(function () {
  var ann = false;
  var line = true;
  $('.toggle-ann').click(function () {
    ann = !ann;
    if (ann) {
      $('table.codeann .user').show();
      $('table.codeann .changeset').show();
    } else {
      $('table.codeann .user').hide();
      $('table.codeann .changeset').hide();
    }
  });
  $('.toggle-line').click(function () {
    ann = !ann;
    if (ann) {
      $('table.codeann .line').show();
    } else {
      $('table.codeann .line').hide();
    }
  });
});
</script>
HTML;

    echo "<br><br><table class='codeann'><tr><th class='changeset'>rev</th><th class='user'>who</th><th class='line'>line</th><th class='code'>code</th></tr>\n";

    while (isset($ann[$i])) {
      $a = $ann[$i];
      echo "<tr>" .
        "<td class='changeset'>" . mtrack_changeset($a->rev, $repo) . "</td>" .
        "<td class='user'>" . mtrack_username($a->changeby,
            array('no_image' => true)) . "</td>" .
        "<td class='line'><a name='l$i'></a><a href='#l$i'>$i</a></td>";

      if ($i == 1) {
        $nlines = count($ann);
        echo "<td rowspan='$nlines' width='100%' class='source-code wezterm'>$data</td>";
      }
      echo "</tr>\n";

      $i++;
    }

    echo "</table>\n";
  }
} elseif ($major == 'image') {
  echo "<br><br><img src='$raw_url'>\n";
}

mtrack_foot();

