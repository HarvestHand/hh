<?php # vim:ts=2:sw=2:et:
include '../inc/common.php';

$username = $_GET['u'];
$size = $_GET['s'];
$data = MTrackAuth::getUserData($username);

$loc = MTrackConfig::get('core', 'httpcachedir');
if (!$loc) {
  $loc = MTrackConfig::get('core', 'vardir') . '/httpcache';
  if (!is_dir($loc)) {
    mkdir($loc);
  }
}

$cache_duration = 3600; // seconds

$default = MTrackConfig::get('gravatar', 'default');
if (!$default) {
  $default = 'wavatar';
}

$source = null;
if (isset($data['avatar'])) {
  $source = $data['avatar'];
} else if (isset($data['email'])) {
  $source = "http://www.gravatar.com/avatar/" .
    md5(strtolower($data['email'])) . "?s=$size&d=$default";
} else if (preg_match('/^https?:\/\//', $username)) {
  // Let's try a favatar!

  function extract_favatar_link($filename, $relurl)
  {
    $data = file_get_contents($filename);
    // Just get the head
    if (preg_match('@<head[^>]*>(.*)</head>@smi', $data, $M)) {
      $data = "<html><head>" . $M[1] . "</head></html>";
    }
    $doc = new DomDocument;
    if (!@$doc->loadHTML($data)) {
      return null;
    }
    $xpath = new DomXPath($doc);
    $links = $xpath->query(
        '/html/head/link[@rel="shortcut icon" or @rel="icon"]');

    if (substr($relurl, -1) != '/') {
      $relurl .= '/';
    }

    foreach ($links as $link) {
      $url = $link->getAttribute('href');
      if ($url !== null) {
        break;
      }
    }

    if ($url === null) {
      return $relurl . 'favicon.ico';
    }

    if (!preg_match('@^([a-zA-Z]+)://@', $url)) {
      /* fixup relative links */
      if ($url[0] == '/') {
        $url = substr($url, 1);
      }
      foreach ($xpath->query('/html/head/base') as $base) {
        $url = $base->getAttribute('href') . $url;
      }
      if (!preg_match('@^([a-zA-Z]+)://@', $url)) {
        $url = $relurl . $url;
      }
    }
    return $url;
  }

  list($head, $link) = cache_get_url_and_operate(
    $username, 'extract_favatar_link', $username);

  $source = $link;
}

function logit($msg)
{
#  echo "$msg<br>";
//  error_log($msg);
}

/**
 * Fetches the contents of the URL $source using a cache.
 * Optionally runs a callback specified by $funcname on the
 * data while it is under a lock (to ensure a consistent view).
 * $funcname is passed the local cache filename as its first parameter.
 * Any additional parameters passed to this function will be passed
 * to $funcname as parameters after the cache filename.
 *
 * returns an array(
 *  0 => data from the url
 *  1 => return value of optional funcname
 * )
 */
function cache_get_url_and_operate($source, $funcname = null /* args */)
{
  global $loc;
  global $cache_duration;

  $args = func_get_args();
  if (count($args) > 2) {
    array_shift($args);
    array_shift($args);
  } else {
    $args = array();
  }
  $cache = $loc . "/" . md5($source);
  array_unshift($args, $cache);

  // cache file population, avoiding thundering herd and maintaining
  // consistency under concurrency.

  $dat = null;
  $tosend = null;

  $tries = 20;
  while ($tries-- > 0) {
    logit("tries=$tries");
    // Can we open the file for read?
    $fp = @fopen($cache, 'r+b');
    if (!$fp) {
      $fp = @fopen($cache, 'x+');
    }
    if ($fp) {
      // Yes; get a lock for consistency
      flock($fp, LOCK_SH);
      logit("got shared lock");
      // What do we need to do?
      $st = fstat($fp);
      if ($st['size'] == 0) {
        // No data in the file, let's see if we can do something about that
        logit("zero size; getting ex lock");
        flock($fp, LOCK_EX);
        $st = fstat($fp);
        if ($st['size'] == 0) {
          // We get to fix it
          logit("zero sized; we're fixing it, reading from $source");
          $tosend = file_get_contents($source);
          fwrite($fp, $tosend);

          if ($funcname !== null) {
            $dat = call_user_func_array($funcname, $args);
          }
          break;
        }
        // Someone else fixed it
        logit("Someone else fixed it, size is now $st[size]");
      } else if (time() - $st['mtime'] > $cache_duration) {
        // Someone needs to re-fetch the data
        logit("Past cache period, getting ex lock");
        flock($fp, LOCK_EX);
        $st = fstat($fp);
        if (time() - $st['mtime'] > $cache_duration) {
          // We get to fix it
          logit("cache expired; reading from $source, truncating");
          ftruncate($fp, 0);
          rewind($fp);
          $tosend = file_get_contents($source);
          logit("read " . strlen($tosend) . " from $source");
          $x = fwrite($fp, $tosend);
          logit("wrote $x to local cache file");
          if ($funcname !== null) {
            $dat = call_user_func_array($funcname, $args);
          }
          break;
        }
        // Someone else fixed it
        logit("Someone fixed it, mtime now $st[mtime]");
      }
      // Good to read through
      logit("Reading through cache");
      $tosend = stream_get_contents($fp);
      if ($funcname !== null) {
        $dat = call_user_func_array($funcname, $args);
      }
      break;
    }
    logit("Couldn't get data, sleeping and retrying");
    usleep(100);
  }
  if ($fp) {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
  return array($tosend, $dat);
}

$age = 120;
header("Cache-Control: public, max-age=$age, pre-check=$age");
header('Expires: ' . date(DATE_COOKIE, time() + $age));

if ($source) {
  $hint = basename($source);
  list($tosend, $mime) = cache_get_url_and_operate(
    $source, 'mtrack_mime_detect', $hint);
  if ($mime) {
    logit("All is good, sending data");
  } else {
    logit("Unable to get data");
  }
  if ($mime) {
    header("Content-Type: $mime");
    header("Content-Disposition: inline; filename=\"$hint\"");
    echo $tosend;
    exit;
  }
}

$cache = dirname(__FILE__) . "/images/default_avatar.png";
$mime = mtrack_mime_detect($cache, $cache);
header("Content-Type: $mime");
header("Content-Disposition: inline");
readfile($cache);
exit;

