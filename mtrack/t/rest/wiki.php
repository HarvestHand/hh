<?php # vim:ts=2:sw=2:et:
require getenv('INCUB_ROOT') . '/inc/Test/init.php';

plan(22);

list($st, $h, $base) = rest_api_func('POST', "/wiki/page/Conflict", null, null,
  array(
    'content' => " = My Wiki Page = "
  ));

is($st, 200, "created a wiki page");
is($base->content, " = My Wiki Page =", "content stored");
is($base->who, "admin", "created by admin");
is($base->id, "Conflict", "name is Conflict");

/* now to test conflict/merge handling */

$v1 = clone $base;
$v1->content .= "\nbottom";

list($st, $h, $obj) = rest_api_func('POST', "/wiki/page/Conflict",
  null, null, $v1);
is($st, 200, "saved v1");
isnt($obj->version, $base->version, "version is different");
is($obj->content, $v1->content, "content matches what we set");

/* and make a conflicting change from the base */

$v2 = clone $base;
$v2->content = "top\n" . $v2->content;

list($st, $h, $obj) = rest_api_func('POST', "/wiki/page/Conflict",
  null, null, $v2);
is($st, 200, "saved v2");
isnt($obj->version, $base->version, "version is different");
is($obj->content, "top\n = My Wiki Page =\nbottom",
  "auto-merged v1 and v2");

/* now make a change that has a non-mergable conflict */

$v1 = clone $obj;
$v2 = clone $obj;

$v1->content = "Bang\n" . $v1->content;
$v2->content = "Boom\n" . $v2->content;

list($st, $h, $obj) = rest_api_func('POST', "/wiki/page/Conflict",
  null, null, $v1);
is($st, 200, "saved v1");
is($obj->content, $v1->content, "stored Bang");

list($st, $h, $obj) = rest_api_func('POST', "/wiki/page/Conflict",
  null, null, $v2);

is($st, 409, "Conflict detected");
is($obj->code, 409, "smells like a 409 JSON response");
is($obj->status, "error", "status = error");
is($obj->message, "conflict detected", "conflict detected");

$c = $obj->extra;

is($c->id, "Conflict", "conflict payload smells like a wiki object");
is($c->content, <<<JSON
<<<<<<< mine
Boom
||||||| original
Bang
=======
Bang
>>>>>>> theirs
top
 = My Wiki Page =
bottom

JSON
  , "got correct conflict payload");

/* validate that we cannot save a payload with conflict markers */
list($st, $h, $obj) = rest_api_func('POST', "/wiki/page/Conflict",
  null, null, $c);
is($st, 409, "Conflict detected");

/* and now show that we can resolve the conflict */
$c->content = <<<JSON
Boom & Bang
top
 = My Wiki Page =
bottom

JSON;
list($st, $h, $obj) = rest_api_func('POST', "/wiki/page/Conflict",
  null, null, $c);
is($st, 200, "Resolved conflict");

list($st, $h, $sanity) = rest_api_func('GET', "/wiki/page/Conflict");
is($st, 200, "Got page");

is($sanity->content, $c->content, "Content was taken in");

