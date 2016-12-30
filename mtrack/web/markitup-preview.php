<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

mtrack_head('preview', false);
echo MTrackWiki::format_to_html($_POST['data']);
mtrack_foot(false);

