<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

MTrackACL::requireAllRights('Timeline', 'read');
mtrack_head("Timeline");

mtrack_render_timeline();

mtrack_foot();

