<?php # vim:ts=2:sw=2:et:ft=php:
require getenv('INCUB_ROOT') . '/inc/Test/init.php';

plan(4);

/* Test some aspects of the REST API */

list($st, $h, $body) = rest_api_func('GET', '/user/admin', null, null, null,
  array(CURLOPT_USERPWD => 'bogus:invalid'));

if (!is($st, 401, "bogus credentials -> 401")) {
  var_dump($st, $h, $body);
}

list($st, $h, $body) = rest_api_func('GET', '/invalid/endpoint');
if (!is($st, 404, "invalid endpoint -> 404")) {
  var_dump($st, $h, $body);
}

list($st, $h, $body) = rest_api_func('GET', '/user/admin');
if (!is($st, 200, "get my user object")) {
  var_dump($st, $h, $body);
} else {
  $expect = (object)array(
    'id' => 'admin',
    'fullname' => 'admin',
    'email' => null,
    'timezone' => null,
    'active' => true,
    'aliases' => array(),
    'prefs' => new stdclass,
    'role' => 'admin',
    'groups' => array(
      "BrowserCreator",
      "BrowserForker",
      "ComponentCreator",
      "EnumerationCreator",
      "ProjectCreator",
      "ReportCreator",
      "RoadmapCreator",
      "SnippetCreator",
      "TicketCreator",
      "TimelineViewer",
      "UserCreator",
      "WikiCreator",
      "admin",
    ),
  );
  sort($body->groups);
  if (!is($body, $expect, "got right bits")) {
    diag($body);
  }
}



