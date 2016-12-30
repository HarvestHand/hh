<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../inc/common.php';

$pi = mtrack_get_pathinfo();

$p = MTrackExtensionPage::bindToPage($pi);

if ($p) {
  $p->dispatchRequest();
} else {

  mtrack_head("Not found");

  echo htmlentities($pi, ENT_QUOTES, 'utf-8');
  echo " is not a registered mtrack application endpoint";

  mtrack_foot();
}


