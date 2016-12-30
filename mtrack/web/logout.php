<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../inc/common.php';
MTrackAuth::LogOut();
header("Location: $GLOBALS[ABSWEB]");
