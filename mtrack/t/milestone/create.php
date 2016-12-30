<?php # vim:ts=2:sw=2:et:ft=php:
require getenv('INCUB_ROOT') . '/inc/Test/init.php';

WebDriver::required_for_test();
plan(29);

$d = WebDriver();
ok($d->url(INCUB_URL . '/roadmap.php'), 'click on Roadmap');
$add = $d->element('id', 'addmilestone');
ok($add->click(), 'click add milestone');
like($d->url(), '/milestone\.php\?new=1$/', "landed on right page");

$name = $d->element('id', 'name');
ok($name->value('milestone1'), "set name");

$save = $d->element('id', 'savemilestone');
ok($save->submit(), 'submitted');

like($d->url(), '/milestone\.php\/milestone1$/', "landed on milestone page");

ok($d->url(INCUB_URL . '/ticket.php/new'), 'click on new ticket');

$summary = $d->element('css selector', '#tkt-summary-text span');
ok($summary, "got summary field");
ok($summary->doubleclick(), "activated editor for summary");
$summary = $d->element('css selector', '#tkt-summary-text input');
ok($summary, "got summary editor");
ok($summary->value("first ticket"), "entered summary text");
ok($summary->value(WebDriverElement::RET), "press enter");

$ms = $d->element('css selector', '#tkt-edit-milestones input');
ok($ms, 'got milestone field');
ok($ms->value("milestone1"), "input milestone1");
ok($ms->value(WebDriverElement::RET), "accept milestone1");

$txt = $d->element('css selector', '#tkt-edit-estimated span');
ok($txt, "got estimate field");
ok($txt->doubleclick(), "activated editor for estimated");
$txt = $d->element('css selector', '#tkt-edit-estimated input');
ok($txt, "got estimate editor");
ok($txt->value("10"), "entered estimate");
ok($txt->value(WebDriverElement::RET), "press enter");

$txt = $d->element('css selector', '#tkt-edit-effortSpent span');
ok($txt, "got effortSpent field");
ok($txt->doubleclick(), "activated editor for effortSpent");
$txt = $d->element('css selector', '#tkt-edit-effortSpent input');
ok($txt, "got effortSpent editor");
ok($txt->value("5"), "entered time");
ok($txt->value(WebDriverElement::RET), "press enter");

$save = $d->element('id', 'save-issue');
ok($save, "got save button");
ok($save->click(), "clicked save");

/* allow it time to save and redirect */
for ($i = 0; $i < 10; $i++) {
  if (preg_match("/\d+$/", $d->url())) {
    break;
  }
  sleep(1);
}
like($d->url(), '/ticket\.php\/\d+$/', "landed on ticket page");

ok($d->url(INCUB_URL . '/roadmap.php'), 'click on Roadmap');

