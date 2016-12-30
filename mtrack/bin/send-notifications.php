<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('UTC');
}

include dirname(__FILE__) . '/../inc/common.php';
include dirname(__FILE__) . '/../inc/mail.php';

// Force this to be the configure value or something that will guide it
// to be set
$ABSWEB = MTrackConfig::get('core', 'weburl');
if (!strlen($ABSWEB)) {
  $ABSWEB = "(configure [core] weburl in config.ini)";
}
$vardir = MTrackConfig::get('core', 'vardir');

$DEBUG = strlen(getenv('DEBUG_NOTIFY')) ? true : false;
$NO_MAIL = strlen(getenv('DEBUG_NOMAIL')) ? true : false;

$MAX_DIFF = 200 * 1024;
$USE_BATCHING = false;

if (!$DEBUG) {
  /* only allow one instance to run concurrently */
  $lockfp = fopen($vardir . '/.notifier.lock', 'w');
  if (!$lockfp) {
    exit(1);
  }
  if (!flock($lockfp, LOCK_EX|LOCK_NB)) {
    echo "Another instance is already running\n";
    exit(1);
  }
  /* "leak" $lockfp, so that the lock is held while we continue to run */
}

$db = MTrackDB::get();

// default to the last 10 minutes, but prefer the last recorded run time
$last = MTrackDB::unixtime(time() - 600);
foreach (MTrackDB::q('select last_run from last_notification')->fetchAll()
    as $row) {
  $last = $row[0];
}
$LATEST = strtotime($last);
if (getenv('DEBUG_TIME')) {
  $dtime = strtotime(getenv('DEBUG_TIME'));
  if ($dtime > 0) {
    $LATEST = $dtime;
    $last = MTrackDB::unixtime($LATEST);
    echo "Using $last as last time (specified via DEBUG_TIME var)\n";
  }
}

$watched = MTrackWatch::getWatchedItemsAndWatchers($last, 'email');
printf("Got %d watchers over $last\n", count($watched));

/* For each watcher, compute the changes.
 * Group changes by ticket, sending one email per ticket.
 * Group tickets into batch updates if the only fields that changed are
 * bulk update style (milestone, assignment etc.)
 *
 * For the wiki repo, group by file so that serial edits within the batch
 * period show up as a single email.
 */

foreach ($watched as $user => $objects) {
  $udata = MTrackAuth::getUserData($user);

  foreach ($objects as $object => $items) {
    list($otype, $oid) = explode(':', $object, 2);

    $fname = "notify_$otype";
    if (!isset($udata['email'])) {
      echo "WARN: have notifications for user $user, but no email address\n";
    } elseif (function_exists($fname)) {
      call_user_func($fname, $object, $oid, $items, $user, $udata);
    } else {
      echo "WARN: no notifier for $otype $oid\n";
    }
    foreach ($items as $o) {
      if ($o instanceof MTrackSCMEvent) {
        $t = strtotime($o->ctime);
      } else {
        $t = strtotime($o->changedate);
      }
      if ($t > $LATEST) {
        $LATEST = $t;
      }
    }
  }
}

function get_change_audit($items)
{
  $cid_list = array();
  $all_cs = array();

  foreach ($items as $obj) {
    if (!($obj instanceof MTrackSCMEvent)) {
      $all_cs[$obj->cid] = $obj;
      if (!isset($obj->audit)) {
        $obj->audit = array();
        $cid_list[] = $obj->cid;
      }
    }
  }

  if (count($cid_list)) {
    $cid_list = join(',', $cid_list);
    foreach (MTrackDB::q("select * from change_audit where cid in ($cid_list)")
        ->fetchAll(PDO::FETCH_OBJ) as $aud) {
      $cid = $aud->cid;
      unset($aud->cid);
      $all_cs[$cid]->audit[] = $aud;
    }
  }

  return $all_cs;
}

function compute_contributor($items)
{
  $contributors = array();
  foreach ($items as $obj) {
    if (isset($obj->who)) {
      $contributors[$obj->who]++;
    } elseif (isset($obj->changeby)) {
      $contributors[$obj->changeby]++;
    }
  }
  $count = 0;
  $major = null;
  foreach ($contributors as $user => $input) {
    if ($input > $count) {
      $major = $user;
      $count = $input;
    }
  }
  unset($contributors[$major]);

  $res = array();
  $res[] = array($major, MTrackAuth::getUserData($major));
  foreach ($contributors as $user => $input) {
    $res[] = array($user, MTrackAuth::getUserData($user));
  }

  return $res;
}

function make_email($uname, $uinfo)
{
  $email = $uinfo['email'];
  $name = $uinfo['fullname'];
  if ($name == $email) {
    return $email;
  }
  return encode_header($name) . " <$email>";
}

function find_related_projects($items)
{
  $projects = array();
  foreach ($items as $obj) {
    if (!isset($obj->_related)) continue;
    foreach ($obj->_related as $rel) {
      if ($rel[0] == 'project') {
        $p = get_project($rel[1]);
        $projects[$p->projid] = $p->shortname;
      }
    }
  }
  natsort($projects);
  return $projects;
}

/* Composes project related email headers, returns a subject line
 * prefix string */
function add_project_headers(&$headers, $projects)
{
  if (count($projects)) {
    $headers['X-mtrack-project-list'] = join(' ', $projects);
    foreach ($projects as $pname) {
      $headers["X-mtrack-project-$pname"] = $pname;
      $headers['X-mtrack-project'][] = $pname;
    }
    return "[" . implode(',', $projects) . "] ";
  }
  return '';
}

function notify_repo($object, $tid, $items, $user, $udata)
{
  global $ABSWEB;

  $revlist = array();
  $repo = null;

  $code_by_repo = array();
  foreach ($items as $obj) {
    if (!($obj instanceof MTrackSCMEvent) && !isset($obj->repo)) {
      if (!isset($obj->ent)) {
        continue;
      }
      $obj = $obj->ent;
    }

    $code_by_repo[$obj->repo->getBrowseRootName()][] = $obj;
    $revlist[] = $obj->rev;
    if ($repo === null) {
      $repo = $obj->repo;
    }
  }
  if (!count($code_by_repo)) {
    return;
  }

  $reponame = $repo->getBrowseRootName();

  $from = compute_contributor($items);

  $headers = array(
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset="UTF-8"',
    'Content-Transfer-Encoding' => 'quoted-printable',
  );

  $headers['To'] = make_email($user, $udata);
  $headers['From'] = make_email($from[0][0], $from[0][1]);
  if (count($from) > 1) {
    $rep = array();
    array_shift($from);
    foreach ($from as $email) {
      $rep[] = make_email($email[0], $email[1]);
    }
    $headers['Reply-To'] = join(', ', $rep);
  }
  $mid = sha1($reponame . join(':', $revlist)) . '@' . php_uname('n');
  $headers['Message-ID'] = "<$mid>";

  /* find related project(s) */
  $projects = find_related_projects($items);
  $subj = add_project_headers($headers, $projects);
  $subj = sprintf("%scommit %s ", $subj, $reponame);
  foreach ($revlist as $rev) {
    if (strlen($subj) > 72) break;
    $subj .= " [$rev]";
  }
  $headers['Subject'] = $subj;

  global $ABSWEB;

  $plain = tmpfile();
  stream_filter_append($plain, 'mtrackcanonical', STREAM_FILTER_WRITE);
  foreach ($headers as $name => $value) {
    if (is_array($value)) {
      foreach ($value as $v) {
        fprintf($plain, "%s: %s\n", $name, encode_header($v));
      }
    } else {
      fprintf($plain, "%s: %s\n", $name, encode_header($value));
    }
  }

  fprintf($plain, "\n");
  fflush($plain);
  add_qp_filter($plain);

  generate_repo_changes($plain, $code_by_repo, true);

  rewind($plain);

  send_mail($udata['email'], $plain);
}

function add_qp_filter($stream)
{
  stream_filter_append($stream, 'convert.quoted-printable-encode',
    STREAM_FILTER_WRITE, array(
      'line-length' => 74,
      'line-break-chars' => "\r\n",
    )
  );
}

function notify_ticket($object, $tid, $items, $user, $udata)
{
  global $MAX_DIFF;
  $T = MTrackIssue::loadById($tid);
  if (!is_object($T)) {
    echo "Failed to load ticket by id: $tid\n";
    return;
  }

  $from = compute_contributor($items);
  $audit = get_change_audit($items);

  $comments = array();
  $fields = array();
  $field_changers = array();
  $old_values = array();
  $is_initial = false;

  foreach ($audit as $CS) {
    if ($CS->cid == $T->created) {
      // We use this to set a Message-ID header
      $is_initial = true;
    }
    foreach ($CS->audit as $aud) {
      // fieldname is of the form: "ticket:id:fieldname"
      $field = substr($aud->fieldname, strlen($object)+1);

      if ($field == '@comment') {
        $comments[] = "Comment by " .
            $CS->who . ":\n" . $aud->value;
      } elseif ($field != 'spent') {
        $field_changers[$field] = $CS->who;
        if (!isset($old_values[$field])) {
          $old_values[$field] = $aud->oldvalue;
        }
      }
    }
  }


  $headers = array(
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset="UTF-8"',
    'Content-Transfer-Encoding' => 'quoted-printable',
  );

  $headers['To'] = make_email($user, $udata);
  $headers['From'] = make_email($from[0][0], $from[0][1]);
  if (count($from) > 1) {
    $rep = array();
    array_shift($from);
    foreach ($from as $email) {
      $rep[] = make_email($email[0], $email[1]);
    }
    $headers['Reply-To'] = join(', ', $rep);
  }
  $mid = $T->tid . '@' . php_uname('n');
  if ($is_initial) {
    $headers['Message-ID'] = "<$mid>";
  } else {
    $headers['Message-ID'] = "<$T->updated.$mid>";
    $headers['In-Reply-To'] = "<$mid>";
    $headers['References'] = "<$mid>";
  }
  /* find related project(s) */
  $projects = find_related_projects($items);
  $subj = add_project_headers($headers, $projects);

  $headers['Subject'] = sprintf("%s#%s %s (%s %s)",
    $subj, $T->nsident, $T->summary, $T->status, $T->classification);

  global $ABSWEB;

  $plain = tmpfile();
  stream_filter_append($plain, 'mtrackcanonical', STREAM_FILTER_WRITE);
  foreach ($headers as $name => $value) {
    if (is_array($value)) {
      foreach ($value as $v) {
        fprintf($plain, "%s: %s\n", $name, encode_header($v));
      }
    } else {
      fprintf($plain, "%s: %s\n", $name, encode_header($value));
    }
  }
  fprintf($plain, "\n");
  fflush($plain);
  add_qp_filter($plain);

  fprintf($plain, "%sticket.php/%s\n\n", $ABSWEB, $T->nsident);

  fprintf($plain, "#%s: %s (%s %s)\n",
    $T->nsident, $T->summary, $T->status, $T->classification);

  $owner = strlen($T->owner) ? $T->owner : 'nobody';
  fprintf($plain, "Responsible: %s (%s / %s)\n",
    $owner, $T->priority, $T->severity);

  fprintf($plain, "Milestone: %s\n", join(', ', $T->getMilestones()));
  fprintf($plain, "Component: %s\n", join(', ', $T->getComponents()));

  fprintf($plain, "\n");

  // Display changed fields grouped by the person that last changed them
  $who_changed = array();
  foreach ($field_changers as $field => $who) {
    $who_changed[$who][] = $field;
  }
  foreach ($who_changed as $who => $fieldlist) {
    fprintf($plain, "Changes by %s:\n", $who);

    foreach ($fieldlist as $field) {
      $old = $old_values[$field];

      if (!strlen($old) && $field == 'nsident') {
        continue;
      }

      $value = null;
      switch ($field) {
        case '@components':
          $old = array();
          foreach (preg_split("/\s*,\s*/", $old_values[$field]) as $id) {
            if (!strlen($id)) continue;
            $c = get_component($id);
            $old[$id] = $c->name;
          }
          $value = $T->getComponents();
          $field = 'Component';
          break;
        case '@milestones':
          $old = array();
          foreach (preg_split("/\s*,\s*/", $old_values[$field]) as $id) {
            if (!strlen($id)) continue;
            $m = get_milestone($id);
            $old[$id] = $m->name;
          }
          $value = array();
          $value = $T->getMilestones();
          $field = 'Milestone';
          break;
        case '@keywords':
          $old = array();
          $field = 'Keywords';
          $value = $T->getKeywords();
          break;
        default:
          $old = null;
          $value = $T->{$field};
      }
      if (is_array($value)) {
        $value = join(', ', $value);
      }
      if (is_array($old)) {
        $old = join(', ', $old);
      }
      if ($value == $old) {
        continue;
      }
      if ($field == 'description') {
        $lines = count(explode("\n", $old));
        $diff = mtrack_diff_strings($old, $value);
        $diff_add = 0;
        $diff_rem = 0;
        foreach (explode("\n", $diff) as $line) {
          if ($line[0] == '-') {
            $diff_rem++;
          } else if ($line[0] == '+') {
            $diff_add++;
          }
        }
        if (abs($diff_add - $diff_rem) > $lines / 2) {
          fprintf($plain, "Description changed to:\n%s\n\n", $value);
        } else {
          fprintf($plain, "Description changed:\n%s\n\n", $diff);
        }
      } else {
        $f = MTrackTicket_CustomFields::getInstance()
          ->fieldByName($field);
        if ($f) {
          $field = $f->label;
          $old = $f->flattenValue($old);
          $value = $f->flattenValue($value);
        } else {
          $field = ucfirst($field);
        }
        fprintf($plain, "%s %s -> %s\n", $field, $old, $value);
      }
    }
  }
  foreach ($comments as $comment) {
    fprintf($plain, "\n%s\n", $comment);
  }

  $code_by_repo = array();
  foreach ($items as $obj) {
    if (!($obj instanceof MTrackSCMEvent)) {
      if (!isset($obj->ent)) {
        continue;
      }
      $obj = $obj->ent;
    }
    $code_by_repo[$obj->repo->getBrowseRootName()][] = $obj;
  }
  generate_repo_changes($plain, $code_by_repo);

  fprintf($plain, "\n%sticket.php/%s\n\n", $ABSWEB, $T->nsident);
  rewind($plain);

  send_mail($udata['email'], $plain);
}

function generate_repo_changes($plain, $code_by_repo, $changelog = false)
{
  global $MAX_DIFF;
  global $ABSWEB;

  foreach ($code_by_repo as $reponame => $ents) {
    fprintf($plain, "\nChanges in %s:\n", $reponame);

    /* Gather up affected files */
    $files = array();
    foreach ($ents as $obj) {
      foreach ($obj->files as $file) {
        $files[$file->name][$file->status]++;
      }
    }
    ksort($files);
    $n = 0;
    fprintf($plain, "  Affected files:\n");
    foreach ($files as $filename => $status) {
      if ($n++ > 20) {
        fprintf($plain, "  ** More than 20 files were changed\n");
        break;
      }
      fprintf($plain, "%5s %s\n", join('', array_keys($status)), $filename);
    }

    $too_big = false;
    $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
    foreach ($ents as $obj) {
      fprintf($plain, "\n[%s] by %s\n", $obj->rev, $obj->changeby);
      fprintf($plain, "%schangeset.php/%s/%s\n",
        $ABSWEB, $reponame, $obj->rev);
      if (isset($obj->branches) && is_array($obj->branches)
            && count($obj->branches)) {
        fprintf($plain, "Branch: %s\n", $obj->branches[0]);
      }
      fprintf($plain, "\n");

      if ($changelog) {
        fprintf($plain, "%s\n\n", $obj->changelog);
      }

      $email_size = get_stream_size($plain);
      if ($email_size >= $MAX_DIFF) {
        $too_big = true;
        continue;
      }
      foreach ($obj->files as $file) {
        $diff = get_diff($obj, $file);

        $email_size = get_stream_size($plain);
        $diff_size = get_stream_size($diff);

        if ($email_size + $diff_size < $MAX_DIFF) {
          if ($reponame == 'default/wiki') {
            $page = null;
            if ($suf) {
              if (preg_match("/^(.*)$suf$/", $file->name, $M)) {
                $page = $M[1];
              }
            } else {
              $page = $file->name;
            }
            if ($page) {
              fprintf($plain, "Wiki: %swiki.php/%s\n",
                $ABSWEB, $page);
            }
          }

          stream_copy_to_stream($diff, $plain);
          fwrite($plain, "\n");
        } else {
          $too_big = true;
        }
      }

    }
    if ($too_big) {
      fprintf($plain, "  * Diff exceeds configured limit\n");
    }
  }
}

function get_stream_size($stm)
{
  $st = fstat($stm);
  return $st['size'];
}

function get_diff(MTrackSCMEvent $ent, $file)
{
  $fname = $file->name;
  if (isset($ent->__diff[$fname])) {
    $diff = $ent->__diff[$fname];
    rewind($diff);
    return $diff;
  }
  $tmp = tmpfile();
  $diff = $ent->repo->diff($file, $ent->rev);
  stream_copy_to_stream($diff, $tmp);
  $ent->__diff[$fname] = $tmp;
  rewind($tmp);
  return $tmp;
}

function get_project($pid) {
  static $projects = array();
  if (isset($projects[$pid])) {
    return $projects[$pid];
  }
  $projects[$pid] = MTrackProject::loadById($pid);
  return $projects[$pid];
}

function get_component($cid) {
  static $comps = array();
  if (isset($comps[$cid])) {
    return $comps[$cid];
  }
  $comps[$cid] = MTrackComponent::loadById($cid);
  return $comps[$cid];
}

function get_milestone($mid) {
  static $comps = array();
  if (isset($comps[$mid])) {
    return $comps[$mid];
  }
  $comps[$mid] = MTrackMilestone::loadById($mid);
  return $comps[$mid];
}

if (!$DEBUG) {
  // Now we are done, update the last run time
  $db->beginTransaction();
  $db->exec("delete from last_notification");
  $t = MTrackDB::unixtime($LATEST);
  echo "updating last run to $t $LATEST\n";
  $db->exec("insert into last_notification (last_run) values ('$t')");
  $db->commit();
}

mtrack_cache_maintain();

