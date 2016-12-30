<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../inc/common.php';

$age = 3600;

header('Content-Type: text/javascript');
header("Cache-Control: public, max-age=$age, pre-check=$age");
header('Expires: ' . date(DATE_COOKIE, time() + $age));

$scripts = array(
  'excanvas.pack.js',
  'jquery-1.7.2.min.js',
  'jquery-ui-1.8.16.custom.min.js',
  'chosen.jquery.min.js',
  'jquery.flot.min.js',
  'jquery.MultiFile.pack.js',
  'jquery.cookie.js',
  'jquery.tablesorter.js',
  'jquery.tablesorter.pager.js',
  'jquery.metadata.js',
  'jquery.markitup.js',
  'jquery.timeago.js',
  'json2.js',
  'jquery.marcopolo.min.js',
  'jquery.manifest.js',
  'jquery.mjs.nestedSortable.js',
  'underscore-min.js',
  'backbone-min.js',
  'quicksilver.js',
  'models.js',
  'views.js',
  'modernizr.js',
  'bootstrap.js',
  'd3.min.js',
  'd3.layout.min.js',
  'rickshaw.js',

  // always last
  'mtrack.js'
);

echo "var ABSWEB = '$ABSWEB';\n";
$gaq_code = MTrackConfig::get('core', 'google_analytics');
if($gaq_code) {
  echo "var _gaq_code = '$gaq_code';\n";
}
/* defaults for ticket fields */
$tktdefs = new stdclass;
$tktdefs->classification =
    MTrackConfig::get('ticket', 'default.classification');
$tktdefs->severity =
    MTrackConfig::get('ticket', 'default.severity');
$tktdefs->priority =
    MTrackConfig::get('ticket', 'default.priority');
echo "var mtrack_ticket_defaults = " . json_encode($tktdefs) . ";\n";

echo "var mtrack_wiki_syntax = " .
  (MTrackConfig::get('core', 'wikisyntax') == 'markdown' ?
    '"markdown"' : '"trac"') . ';';

$pri_map = array();
foreach (MTrackDB::q('select priorityname, value from priorities')
    ->fetchAll() as $row) {
  $pri_map[$row[0]] = (int)$row[1];
}
echo "var mtrack_priority_map = " . json_encode($pri_map) . ";\n";

$sev_map = array();
foreach (MTrackDB::q('select sevname, ordinal from severities')
    ->fetchAll() as $row) {
  $sev_map[$row[0]] = (int)$row[1];
}
echo "var mtrack_severity_map = " . json_encode($sev_map) . ";\n";

$resolutions = array();
$R = new MTrackResolution;
$s = new stdclass;
$s->id = '';
$s->label = '';
$resolutions[] = $s;
foreach ($R->enumerate() as $id => $label) {
  $s = new stdclass;
  $s->id = $id;
  $s->label = $label;
  $resolutions[] = $s;
}
echo "var mtrack_resolutions = " . json_encode($resolutions) . ";\n";

$states = array();
$R = new MTrackTicketState;
$s = new stdclass;
foreach ($R->enumerate() as $id => $label) {
  $s = new stdclass;
  $s->id = $id;
  $s->label = $label;
  $states[] = $s;
}
echo "var mtrack_ticket_states = " . json_encode($states) . ";\n";

$roles = array();
foreach (MTrackConfig::getSection('user_class_roles') as $role => $rights) {
  $R = new stdclass;
  $R->id = $role;
  $R->label = $role;
  $roles[] = $R;
}
echo "var mtrack_roles = " . json_encode($roles) . ";\n";

/* build up a list of template wiki pages */
$tree = MTrackWikiItem::get_wiki_tree();
if (isset($tree->Templates)) {
  $templates = array_keys(get_object_vars($tree->Templates));
  natsort($templates);
} else {
  $templates = array();
}
echo "var mtrack_wiki_templates = " . json_encode($templates) . ";\n";
echo "var mtrack_wiki_template_cache = {};\n";

/* Available SCMs */
$repotypes = array();
foreach (MTrackRepo::getAvailableSCMs() as $t => $r) {
  $d = $r->getSCMMetaData();
  $repotypes[$t] = $d['name'];
}
echo "var mtrack_repotypes = " . json_encode($repotypes) . ";\n";
echo "var mtrack_repotypes_select = " .
  json_encode(mtrack_select_box('type', $repotypes, null, true)) . ";\n";

/* "Compile" underscore templates into a blob that we always send down
 * with this javascript blob */
$templdir = dirname(__FILE__) . '/js/templates';
$templates = array();
foreach (scandir($templdir) as $name) {
  if ($name[0] == '.') continue;
  $id = preg_replace('/\.html$/', '', $name);
  $id = preg_replace('/[^a-z0-9]+/', '-', $id);
  $templates[$id] = file_get_contents("$templdir/$name");
}
echo "var mtrack_underscore_templates = " . json_encode($templates) . ";\n";

foreach ($scripts as $name) {
  echo "\n// $name\n";
  readfile("js/$name");
  echo "\n;\n";
}
