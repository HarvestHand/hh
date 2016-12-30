<?php # vim:ts=2:sw=2:et:ft=php:
require getenv('INCUB_ROOT') . '/inc/Test/init.php';

/* This test simply opens up each of the top-level pages in a fresh
 * installation to show that they function from a very high-level perspective
 */

WebDriver::required_for_test();
plan(8);

$d = new WebDriver();

$top_level = array(
  'browse.php',
  'wiki.php',
  'timeline.php',
  'roadmap.php',
  'reports.php',
  'ticket.php/new',
  'snippet.php',
  'admin'
);

foreach ($top_level as $p) {
  ok($d->url(INCUB_URL . '/' . $p), "navigate to $p");
}


