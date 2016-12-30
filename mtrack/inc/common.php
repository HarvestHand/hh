<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

define('MTRACK_INC_DIR', dirname(__FILE__));

set_include_path(
  MTRACK_INC_DIR . DIRECTORY_SEPARATOR . 'lib' .
  PATH_SEPARATOR .
  get_include_path()
  );

$MTRACK_INIT_LIST = array();
function mtrack_init($func)
{
  global $MTRACK_INIT_LIST;
  $MTRACK_INIT_LIST[] = $func;
}

include MTRACK_INC_DIR . '/configuration.php';
include MTRACK_INC_DIR . '/link.php';
include MTRACK_INC_DIR . '/rest.php';
include MTRACK_INC_DIR . '/watch.php';
include MTRACK_INC_DIR . '/cache.php';
include MTRACK_INC_DIR . '/UUID.php';
include MTRACK_INC_DIR . '/attachment.php';
include MTRACK_INC_DIR . '/database.php';
include MTRACK_INC_DIR . '/search.php';
include MTRACK_INC_DIR . '/keywords.php';
include MTRACK_INC_DIR . '/wiki.php';
include MTRACK_INC_DIR . '/changeset.php';
include MTRACK_INC_DIR . '/commit-hook.php';
include MTRACK_INC_DIR . '/captcha.php';
include MTRACK_INC_DIR . '/web.php';
include MTRACK_INC_DIR . '/auth.php';
include MTRACK_INC_DIR . '/user.php';
include MTRACK_INC_DIR . '/acl.php';
include MTRACK_INC_DIR . '/ebs.php';
include MTRACK_INC_DIR . '/issue.php';
include MTRACK_INC_DIR . '/report.php';
include MTRACK_INC_DIR . '/milestone.php';
include MTRACK_INC_DIR . '/wiki-item.php';
include MTRACK_INC_DIR . '/scm.php';
include MTRACK_INC_DIR . '/scm/hg.php';
include MTRACK_INC_DIR . '/scm/git.php';
include MTRACK_INC_DIR . '/scm/svn.php';
include MTRACK_INC_DIR . '/timeline.php';
include MTRACK_INC_DIR . '/customfield.php';
include MTRACK_INC_DIR . '/syntax.php';
include MTRACK_INC_DIR . '/snippet.php';
include MTRACK_INC_DIR . '/a2s.php';

foreach ($MTRACK_INIT_LIST as $func) {
  call_user_func($func);
}

MTrackConfig::boot();

if (php_sapi_name() != 'cli') {
  $timezone = null;
  if (MTrackAuth::whoami() != 'anonymous') {
    foreach (MTrackDB::q('select timezone from userinfo where userid = ?',
      MTrackAuth::whoami())->fetchAll() as $row) {
        $timezone = $row[0];
      }
  }
  if (empty($timezone)) {
    $timezone = MTrackConfig::get('core', 'timezone');
  }
  if (!empty($timezone)) {
    $timezone_crutch = array(
      'PST' => 'America/Los_Angeles',
      'PDT' => 'America/Los_Angeles',
      'EDT' => 'America/New_York',
      'EST' => 'America/New_York',
    );
    if (isset($timezone_crutch[$timezone])) {
      $timezone = $timezone_crutch[$timezone];
    }
    date_default_timezone_set($timezone);
  }
}

