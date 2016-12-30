<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

MTrackACL::requireAllRights('Snippets', 'read');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
  MTrackACL::requireAllRights('Snippets', 'create');

  $snip = new MTrackSnippet;
  $snip->snippet = $_POST['code'];
  $snip->description = $_POST['description'];
  $snip->lang = $_POST['lang'];
  $cs = MTrackChangeset::begin("snippet:?", $snip->description);
  $snip->save($cs);
  $cs->setObject("snippet:$snip->snid");
  $cs->commit();
  header("Location: {$ABSWEB}snippet.php/$snip->snid");
  exit;
}

$pi = mtrack_get_pathinfo();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $snip = new MTrackSnippet;
  $snip->description = $_POST['description'];
  $snip->lang = $_POST['lang'];
  $snip->snippet = $_POST['code'];
} elseif (strlen($pi)) {
  $snip = MTrackSnippet::loadById($pi);
  if (!$snip) {
    throw new Exception("Invalid snippet ID");
  }
} else {
  $snip = null;
}

if ($snip) {
  $lang = $snip->lang;
  $code = $snip->snippet;
  $desc = $snip->description;
  mtrack_head("Snippet $pi");
} else {
  $lang = '';
  $code = '';
  $desc = '';
  mtrack_head("New Snippet");
}

echo "<table><tr>";


/* collect recent snippets */
$recent = MTrackDB::q('select snid, description, who, changedate
from snippets
  left join changes on snippets.updated = changes.cid
  order by changes.changedate desc
  limit 10')->fetchAll(PDO::FETCH_OBJ);

echo <<<HTML
<td id='recentsnippets'>
<em>Snippets are a way to share text or code fragments</em><br><br>
HTML;

if (MTrackACL::hasAllRights('Snippets', 'create')) {
  echo <<<HTML
  <button id='newsnippet'>New Snippet</button><br>
<script>
\$(document).ready(function () {
  \$('#newsnippet').click(function () {
    document.location.href = "{$ABSWEB}snippet.php";
  });
});
</script>
HTML;
}

echo <<<HTML
  <b>Recent Snippets</b>
HTML;

foreach ($recent as $s) {
  $url = "{$ABSWEB}snippet.php/$s->snid";
  $sum = MTrackWiki::format_to_oneliner($s->description);
  $who = mtrack_username($s->who, array('no_image' => true));
  $when = mtrack_date($s->changedate);
  echo <<<HTML
  <div class='snippetsummary'>
    $sum<br>
    $when by $who<br>
    <a href='$url'>view snippet</a>
  </div>
HTML;
}
echo "</td><td>";

if (MTrackACL::hasAllRights('Snippets', 'create') &&
    (!$snip || $_SERVER['REQUEST_METHOD'] == 'POST')) {
  echo "<form method='post' class='snippetform' action='{$ABSWEB}snippet.php'>";
  echo "<textarea name='description' class='wiki shortwiki' placeholder='Enter a descriptive message here'>$desc</textarea>\n";
  echo MTrackSyntaxHighlight::getLangSelect('lang', $lang);
  echo "<br><textarea id='code' name='code' class='code' placeholder='Place your snippet here!' rows='20' cols='78'>";
  echo htmlentities($code, ENT_QUOTES, 'utf-8');
  echo "</textarea><br>";
  echo "<button type='submit' name='preview'>Preview</button>\n";
  echo "<button type='submit' name='submit'>Submit</button>\n";
  echo "</form>";
}

if ($snip) {
  echo "<div class='snippetview'>";
  echo "<h1>Snippet</h1>";
  if ($snip->created) {
    $created = MTrackChangeset::get($snip->created);
  } else {
    $created = new stdclass;
  }

  echo "<span id='snippetmug'>",
    mtrack_username($created->who, array('no_name' => true, 'size' => 48)),
    "</span>";
  echo "<b>Created</b>: ",
       mtrack_date($created->when),
       " by ",
       mtrack_username($created->who, array('no_image' => true)),
       "<br>\n";
  echo "<a href='{$ABSWEB}snippet.php/$snip->snid'>Link to this snippet</a><br>";

  echo MTrackWiki::format_to_html($snip->description);
  echo "<br><br>";
  echo MTrackSyntaxHighlight::getSchemeSelect();
  echo MTrackSyntaxHighlight::highlightSource($code, $lang, null, true);
  echo "</div>";
} else if (!MTrackACL::hasAllRights('Snippets', 'create')) {
  echo "<p>You do not have rights to create snippets</p>";
}

echo "</td></tr></table>";

mtrack_foot();
