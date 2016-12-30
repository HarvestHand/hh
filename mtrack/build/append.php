<?php # vim:ts=2:sw=2:et:
# For copyright and licensing terms, see the file named LICENSE

if (function_exists('xdebug_get_code_coverage')) {
  $GLOBALS['__INCUB_COV_EXCLUDE'][] = __FILE__;

  $data = xdebug_get_code_coverage();
  xdebug_stop_code_coverage();

  foreach ($GLOBALS['__INCUB_COV_EXCLUDE'] as $file) {
    unset($data[$file]);
  }
  $root = getenv("INCUB_ROOT");
  // Exclude Zend and OpenID code
  $exclude = array('inc/lib', 'inc/Test', 't');
  $E = array();
  foreach ($exclude as $path) {
    $E[] = realpath("$root/$path");
  }

  foreach ($data as $file => $d) {
    foreach ($E as $exclude) {
      if (!strncmp($exclude, $file, strlen($exclude))) {
        unset($data[$file]);
        continue 2;
      }
    }
  }

  if (!is_dir("$root/build/.covdata")) {
    mkdir("$root/build/.covdata");
  }
  $filename = "$root/build/.covdata/" . md5(uniqid('cov', true));
  file_put_contents($filename, json_encode($data));
}
