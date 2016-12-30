<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

function mtrack_cache_maintain_file($filename, $max_cache_life)
{
  $st = stat($filename);
  if ($st['mtime'] + $max_cache_life < time()) {
    unlink($filename);
  }
}

function mtrack_cache_maintain_dir($cachedir, $max_cache_life)
{
  foreach (scandir($cachedir) as $name) {
    if ($name[0] == '.') continue;
    $filename = "$cachedir/$name";
    if (is_file($filename)) {
      mtrack_cache_maintain_file($filename, $max_cache_life);
    } else {
      mtrack_cache_maintain_dir($filename, $max_cache_life);
    }
  }
}

/* maintain cache */
function mtrack_cache_maintain($max_cache_life = null)
{
  $cachedir = MTrackConfig::get('core', 'vardir') . '/cmdcache';
  if ($max_cache_life === null) {
    $max_cache_life = MTrackConfig::get('core', 'max_cache_life');
    if (!$max_cache_life) {
      $max_cache_life = 14 * 86400;
    }
  }
  mtrack_cache_maintain_dir($cachedir, $max_cache_life);
}

function mtrack_cache_blow_all()
{
  $cachedir = MTrackConfig::get('core', 'vardir') . '/cmdcache';
  foreach (scandir($cachedir) as $name) {
    if ($name[0] == '.') continue;
    $filename = "$cachedir/$name";
    if (is_file($filename)) {
      unlink($filename);
    } else {
      mtrack_rmdir($filename);
    }
  }
}

function mtrack_cache_key($func, $args, $key = null)
{
  if ($key === null) {
    $fkey = json_encode($args);
    $key = $fkey;
  } else {
    $fkey = json_encode($key);
  }
  if (is_string($func)) {
    $fkey = "$func$fkey";
  } else {
    $fkey = json_encode($func) . $fkey;
  }

  $cachedir = MTrackConfig::get('core', 'vardir') . '/cmdcache';
  $hash = sha1($fkey);
  /* make three levels to avoid creating huge directories */
  $a = substr($hash, 0, 2);
  $b = substr($hash, 2, 2);
  $c = substr($hash, 4);
  $cachefile = "$cachedir/$a/$b/$c";

  return array($key, $fkey, $cachefile);
}

function mtrack_cache_blow($func, $args, $key = null)
{
  list($key, $fkey, $cachefile) = mtrack_cache_key($func, $args, $key);

#  error_log("blow: $fkey $cachefile");
  if (file_exists($cachefile)) {
    unlink($cachefile);
  }
}

function mtrack_cache($func, $args, $cache_life = 300, $key = null)
{
  $cachedir = MTrackConfig::get('core', 'vardir') . '/cmdcache';
  if (!is_dir($cachedir)) {
    mkdir($cachedir);
  }

  list($key, $fkey, $cachefile) = mtrack_cache_key($func, $args, $key);

  mtrack_mkdir_p(dirname($cachefile));
#  error_log("cache: $fkey $cachefile");

  $updating = false;
  for ($i = 0; $i < 10; $i++) {
    $fp = @fopen($cachefile, 'r+');
    if ($fp) {
      flock($fp, LOCK_SH);
      /* is it current? */
      $st = fstat($fp);
      if ($st['size'] == 0) {
        /* not valid to have 0 size; we're likely racing with the
         * creator */
        flock($fp, LOCK_UN);
        $fp = null;
        usleep(100);
        continue;
      }
      if ($st['mtime'] + $cache_life < time()) {
        /* no longer current; we'll make it current */
        $updating = true;
        flock($fp, LOCK_EX);
        /* we have exclusive access; someone else may have
         * made it current in the meantime */
        $st = fstat($fp);
        if ($st['mtime'] + $cache_life >= time()) {
          $updating = false;
        }
      }
      break;
    }
    /* we're going to create it */
    $fp = @fopen($cachefile, 'x+');
    if ($fp) {
      flock($fp, LOCK_EX);
      $updating = true;
      break;
    }
  }

  if ($fp) {
    if ($updating) {
      ftruncate($fp, 0);

      $result = call_user_func_array($func, $args);
      $data = new stdclass;
      $data->key = $key;
      $data->res = $result;
      fwrite($fp, serialize($data));
      flock($fp, LOCK_UN);
      return $result;
    }

    $data = unserialize(stream_get_contents($fp));
    flock($fp, LOCK_UN);
    return $data->res;
  }
  /* if we didn't get a file pointer, just run the command */
  return call_user_func_array($func, $args);
}

