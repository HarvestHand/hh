<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../inc/common.php';

$start = MTrackConfig::get('core', 'startpage');
if(!$start) $start = "Today";

if (MTrackAuth::whoami() === 'anonymous' || $start === '/wiki.php') {
  header("Location: {$ABSWEB}wiki.php");
  exit;
}

mtrack_head($start);
echo MTrackWiki::format_wiki_page($start);

mtrack_foot();

