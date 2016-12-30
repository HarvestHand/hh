<?php # vim:ts=2:sw=2:et:
# For copyright and licensing terms, see the file named LICENSE

/* turn on code coverage */
if (function_exists('xdebug_start_code_coverage')) {
  $GLOBALS['__INCUB_COV_EXCLUDE'] = array(__FILE__);
  xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
}

