<?php # vim:ts=2:sw=2:et:
require getenv('INCUB_ROOT') . '/inc/Test/init.php';

plan(17);

function update_index()
{
  putenv("MTRACK_CONFIG_FILE=" . MTrackConfig::getLocation());
  system("php " . getenv("INCUB_ROOT") . "/bin/update-search-index.php");
  if (getenv("INCUB_SOLR_PORT")) {
    // Ugh; need to allow a moment for results to become available
    sleep(1);
  }
}

list($st, $h, $body) = rest_api_func('POST', '/ticket', null, null, array(
  'summary' => 'my test ticket',
  'description' => 'this is my description',
));

is($st, 200, "created ticket");

// remember its id
$tkt1 = $body;

update_index();

list($st, $h, $body) = rest_api_func('GET', '/search/query', array(
  'q' => 'this'
));

is($st, 200, "search ok");
is($body->query, "this", "query matched up");
is(count($body->results), 1, "one result");
is(count($body->results[0]->hits), 1, "one hit in that result");
is($body->results[0]->hits[0]->excerpt,
  "<div class='excerpt'><span class='hl'>this</span> is my description</div>",
  "picked up description text");
is($body->results[0]->id, $tkt1->id, "matched our ticket");

// Verify that we can round-trip UTF-8 properly
$utf8 = html_entity_decode("&pound;", ENT_QUOTES, 'utf-8');
diag($utf8);
$tkt1->comment = "British $utf8";

list($st, $h, $body) = rest_api_func('PUT', "/ticket/$tkt1->id",
  null, null, $tkt1);
if (!is($st, 200, "updated ticket")) {
  var_dump($body);
}
update_index();

list($st, $h, $body) = rest_api_func('GET', '/search/query', array(
  'q' => 'British'
));
is($st, 200, "query for British");
$excerpt = $body->results[0]->hits[0]->excerpt;
is($excerpt, "<div class='excerpt'><span class='hl'>British</span> $utf8</div>", "utf8");

// Validate searching in wiki pages
list($st, $h, $base) = rest_api_func('POST', "/wiki/page/SearchTest",
  null, null,
  array(
    'content' => "This has some stuff in it that I'd like to match"
  ));

is($st, 200, "created a wiki page");
update_index();

list($st, $h, $body) = rest_api_func('GET', '/search/query', array(
  'q' => 'stuff'
));
is($st, 200, "query for stuff");
if (!is($body->results[0]->hits[0]->objectid,
    "wiki:SearchTest", "found wiki page")) {
  var_dump($body);
}
$excerpt = $body->results[0]->hits[0]->excerpt;
like($excerpt, ",<span class='hl'>stuff</span>,", "stuff");

// Validate that deleting a wiki page also makes it go away from index
list($st, $h, $base) = rest_api_func('POST', "/wiki/page/SearchTest",
  null, null,
  array(
    'content' => "" # deleted
  ));

is($st, 200, "delete a wiki page");
update_index();
mtrack_cache_blow_all();

list($st, $h, $body) = rest_api_func('GET', '/search/query', array(
  'q' => 'stuff'
));
is($st, 200, "query for stuff");
if (!is(count($body->results), 0, "no hits")) {
  var_dump($body);
}

