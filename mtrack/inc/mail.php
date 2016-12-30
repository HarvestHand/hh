<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

class CanonicalLineEndingFilter extends php_user_filter {
  function filter($in, $out, &$consumed, $closing)
  {
    while ($bucket = stream_bucket_make_writeable($in)) {
      $bucket->data = preg_replace("/\r?\n/", "\r\n", $bucket->data);
      $consumed += $bucket->datalen;
      stream_bucket_append($out, $bucket);
    }
    return PSFS_PASS_ON;
  }
}
class UnixLineEndingFilter extends php_user_filter {
  function filter($in, $out, &$consumed, $closing)
  {
    while ($bucket = stream_bucket_make_writeable($in)) {
      $bucket->data = preg_replace("/\r?\n/", "\n", $bucket->data);
      $consumed += $bucket->datalen;
      stream_bucket_append($out, $bucket);
    }
    return PSFS_PASS_ON;
  }
}
stream_filter_register("mtrackcanonical", 'CanonicalLineEndingFilter');
stream_filter_register("mtrackunix", 'UnixLineEndingFilter');

$smtp_cache = array();

function encode_header($string)
{
  $result = array();
  foreach (preg_split("/\s+/", $string) as $portion) {
    if (!preg_match("/[\x80-\xff]/", $portion)) {
      $result[] = $portion;
      continue;
    }

    $result[] = '=?UTF-8?B?' . base64_encode($portion) . '?=';
  }
  return join(' ', $result);
}
function _sort_mx($A, $B)
{
  $diff = $A->weight - $B->weight;
  if ($diff) return $diff;
  return strncmp($A->host, $B->host);
}

function get_weighted_mx($domain)
{
  static $cache = array();

  if (preg_match("/^\d+\.\d+\.\d+\.\d+$/", $domain)) {
    /* IP literal */
    $mx = new stdclass;
    $mx->host = $domain;
    $mx->a = array($domain);
    $cache[$domain] = array($mx);
    return $cache[$domain];
  }

  /* ensure that we don't things as local */
  $domain = rtrim($domain, '.') . '.';

  if (isset($cache[$domain])) {
    return $cache[$domain];
  }

  if (!getmxrr($domain, $hosts, $weight)) {
    // Fallback to A
    $mx = new stdclass;
    $mx->host = $domain;
    $mx->a = gethostbynamel($domain);
    $cache[$domain] = array($mx);
    return $cache[$domain];
  }
  $res = array();
  foreach ($hosts as $i => $host) {
    $mx = new stdclass;
    $mx->host = $host;
    $mx->weight = $weight[$i];
    $mx->a = gethostbynamel("$host.");
    $res[] = $mx;
  }
  usort($res, '_sort_mx');

  $cache[$domain] = $res;
  return $cache[$domain];
}

function smtp_cmd($fp, $cmd, $exp = 250)
{
  global $smtp_cache;
  global $DEBUG;

  $res = array();

  if ($DEBUG) {
    echo "> $cmd";
  }
  fwrite($fp, $cmd);
  do {
    $line = fgets($fp);
    $res[] = $res;
    if ($DEBUG) {
      echo "< $line";
    }
  } while ($line[3] == '-');
  $code = (int)$line;
  if ($code != $exp) {
    foreach ($smtp_cache as $k => $v) {
      if ($v === $fp) {
        unset($smtp_cache[$k]);
      }
    }
    throw new Exception("got $code, expected $exp");
  }
  return $res;
}

function smtp_connect($rcpt)
{
  global $DEBUG;

  list($local, $domain) = explode('@', $rcpt);
  global $smtp_cache;
  if (isset($smtp_cache[$domain])) {
    return $smtp_cache[$domain];
  }

  $smarthost = MTrackConfig::get('notify', 'smtp_relay');
  if ($smarthost) {
    $domain = $smarthost;
  }
  $mxs = get_weighted_mx($domain);

  foreach ($mxs as $ent) {
    foreach ($ent->a as $addr) {
      $fp = stream_socket_client("$addr:25", $e, $s);
      if ($fp) {
        do {
          $banner = fgets($fp);
          if ($DEBUG) {
            echo "< $banner";
          }
        } while ($banner[3] == '-');
        $code = (int)$banner;
        if ($code != 220) {
          fclose($fp);
          continue;
        }
        smtp_cmd($fp, sprintf("EHLO %s\r\n", php_uname('n')));
        $smtp_cache[$domain] = $fp;
        return $fp;
      }
    }
  }
  return false;
}

function send_mail($rcpt, $payload)
{
  global $DEBUG;
  global $NO_MAIL;

  $reciplist = escapeshellarg($rcpt);
  if ($DEBUG) {
    echo "would mail: $reciplist\n\n";
    echo stream_get_contents($payload);
    rewind($payload);
  }
  if ($NO_MAIL) {
    echo "Not sending any mail\n";
    return;
  }

  if (!strlen($rcpt)) {
    return;
  }

  if (function_exists('getmxrr') &&
      MTrackConfig::get('notify', 'use_smtp')) {
    /* let's do some SMTP */
    echo "Using SMTP\n";

    $fp = smtp_connect($rcpt);
    if ($fp) {
      $local = MTrackConfig::get('notify', 'smtp_from');
      if (!$local) {
        $local = php_uname('n');
      }
      smtp_cmd($fp, "MAIL FROM:<$local>\r\n");
      smtp_cmd($fp, "RCPT TO:<$rcpt>\r\n");
      smtp_cmd($fp, "DATA\r\n", 354);

      while ($line = fgets($payload)) {
        // Session transparency
        if ($line[0] == '.') {
          $line = '.' . $line;
        }
        // Canonical line endings
        $line = preg_replace("/\r?\n/", "\r\n", $line);
        if ($DEBUG) {
          echo "> $line";
        }
        fwrite($fp, $line);
      }
      smtp_cmd($fp, ".\r\n");
    }
  } else {
    echo "Using sendmail\n";
    $pipe = popen("/usr/sbin/sendmail $reciplist", 'w');
    stream_filter_append($pipe, 'mtrackunix', STREAM_FILTER_WRITE);
    stream_copy_to_stream($payload, $pipe);
    pclose($pipe);
  }
}

