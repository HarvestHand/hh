<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

function mtrack_timeline_order_events_newest_first($a, $b)
{
  return strcmp($b['changedate'], $a['changedate']);
}

function mtrack_get_timeline($start_time = '-2 weeks',
  $only_users = null, $limit = 50)
{
  if (is_string($start_time)) {
    $date_limit = strtotime($start_time);
  } else {
    $date_limit = $start_time; // assume that it's a timestamp
  }
  /* round back to earlier minute (aids caching) */
  $date_limit -= $date_limit % 60;
  $db_date_limit = MTrackDB::unixtime($date_limit);
  $last_date = null;

  $filter_users = null;
  if (is_string($only_users)) {
    $filter_users = array(mtrack_canon_username($only_users));
  } else if (is_array($only_users)) {
    $filter_users = array();
    foreach ($only_users as $user) {
      $filter_users[] = mtrack_canon_username($user);
    }
  }

  $proj_by_id = array();
  foreach (MTrackDB::q('select projid from projects')->fetchAll() as $r) {
    $proj_by_id[$r[0]] = MTrackProject::loadById($r[0]);
  }
  $events = array();

  $cids = array();
  $min_cid = null;
  $max_cid = null;

  foreach (MTrackDB::q("select
      cid, changedate, who, object, reason from changes
      where changedate > ?
      order by changedate desc
      limit $limit
      ", $db_date_limit)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (is_array($filter_users)) {
      $wanted_user = false;
      foreach ($filter_users as $fuser) {
        if (mtrack_canon_username($row['who']) === $fuser) {
          $wanted_user = true;
          break;
        }
      }
      if (!$wanted_user) {
        continue;
      }
    }
    $events[$row['cid']] = $row;
    if (preg_match("/^(ticket|repo):/", $row['object'])) {
      $row['audit'] = array();
      $cid = $row['cid'];
      $cids[] = $cid;
      if ($cid > $max_cid) {
        $max_cid = $cid;
      }
      if ($min_cid === null || $cid < $min_cid) {
        $min_cid = $cid;
      }
    }
  }

  if (count($cids)) {
    $cids = join(',', $cids);
    foreach (MTrackDB::q("select * from change_audit where cid BETWEEN
            $min_cid AND $max_cid AND cid in ($cids)")
        ->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $cid = $row['cid'];
      $events[$cid]['audit'][] = $row;
    }
  }

  usort($events, 'mtrack_timeline_order_events_newest_first');
  return $events;
}

function _mtrack_timeline_is_repo_visible($reponame)
{
  static $cache = array();
  $me = MTrackAuth::whoami();
  if (isset($cache[$me][$reponame])) {
    return $cache[$me][$reponame];
  }

  if (ctype_digit($reponame)) {
    $oid = "repo:$reponame";
  } else {
    $repo = MTrackRepo::loadByName($reponame);
    if ($repo) {
      $oid = "repo:$repo->repoid";
    } else {
      $oid = null;
    }
  }
  if ($oid) {
    $ok = MTrackACL::hasAnyRights($oid, array(
    'read', 'checkout'));
  } else {
    $ok = false;
  }
  $cache[$me][$reponame] = $ok;
  return $ok;
}

function mtrack_object_id_link($object, $id)
{
  global $ABSWEB;

  switch ($object) {
    case 'ticket':
      return "Ticket " . mtrack_ticket($id);
    case 'wiki':
      return "Wiki " . mtrack_wiki($id);
    case 'milestone':
      static $milestones = null;
      if ($milestones === null) {
        $milestones = MTrackMilestone::enumMilestones();
      }
      if (preg_match("/^\d+$/", $id)) {
        $name = $milestones[$id];
      } else {
        $name = $id;
      }
      $qname = str_replace('%2F', '/', urlencode($name));
      return "Milestone <span class='milestone'><a href='{$ABSWEB}milestone.php/$qname'>$name</a></span>";
    case 'repo':
      static $repos = null;
      if ($repos === null) {
        $repos = array();
        foreach (MTrackDB::q(
          'select repoid, shortname, parent from repos')->fetchAll()
          as $r) {
            $repos[$r[0]] = $r;
          }
      }
      if (isset($repos[$id])) {
        $name = MTrackRepo::makeDisplayName($repos[$id]);
        return "<a href='{$ABSWEB}browse.php/$name'>$name</a>";
      }
  }
}

function mtrack_render_timeline_item($d, $row)
{
  global $ABSWEB;

  $time = $d->format('H:i');
  $day = $d->format('D, M d Y');

  // figure out an event type based on the object and the reason
  if (strpos($row['object'], ':') !== false) {
    list($object, $id) = explode(':', $row['object'], 3);
  } else {
    $id = 0;
    $object = $row['object'];
  }
  $eventclass = '';
  $item = $row['object'];
  switch ($object) {
  case 'ticket':
    if (!strncmp($row['reason'], 'created ', 8)) {
      $eventclass = ' newticket';
    } elseif (!strncmp($row['reason'], 'closed ', 7)) {
      $eventclass = ' closedticket';
    } else {
      $eventclass = ' editticket';
    }
    $item = "Ticket " . mtrack_ticket($id);
    if (MTrackConfig::get('core', 'wikisyntax') == 'markdown') {
      /* need a blank line to successfully start a list */
      $row['reason'] .= "\n";
    }
    foreach ($row['audit'] as $audit) {
      if (!preg_match("/^ticket:$id:@?(.*)$/", $audit['fieldname'], $M)) {
        continue;
      }
      $fieldname = $M[1];
      if ($fieldname == 'comment' || $fieldname == 'nsident') {
        continue;
      }
      $value = $audit['value'];
      switch ($fieldname) {
      case 'ptid':
        $value = strlen($value) ? "[ticket:$value]" : "deleted";
        $fieldname = "Parent";
        break;
      case 'dependencies':
      case 'blocks':
      case 'children':
        $value = array();
        foreach (explode(',', $audit['value']) as $t) {
          $value[] = "[ticket:$t]";
        }
        $value = join(" ", $value);
        break;
      case 'milestones':
        $value = array();
        foreach (explode(',', $audit['value']) as $t) {
          $value[] = "[milestone:$t]";
        }
        $value = join(" ", $value);
        break;
      case 'keywords':
        $value = array();
        foreach (explode(',', $audit['value']) as $t) {
          $value[] = "[keyword:$t]";
        }
        $value = join(" ", $value);
        break;
      case 'components':
        $value = array();
        foreach (explode(',', $audit['value']) as $t) {
          $value[] = "[component:$t]";
        }
        $value = join(" ", $value);
        break;
      }
      $f = MTrackTicket_CustomFields::getInstance()
        ->fieldByName($fieldname);
      if ($f) {
        $fieldname = $f->label;
      } else {
        $fieldname = ucfirst($fieldname);
      }
      $row['reason'] .= "\n * $fieldname -> $value";
    }
    $row['reason'] .= "\n";
    break;

  case 'wiki':
    /* we ignore these; they're were created by the wiki UI,
     * but have been superseded by the repo entry instead */
    return null;

  case 'milestone':
    $eventclass = ' editmilestone';
    $item = mtrack_object_id_link('milestone', $id);
    break;
  case 'changeset':
    /* these are only present in installations that were migrated
     * from trac */
    $eventclass = ' newchangeset';
    preg_match("/^changeset:(.*):([^:]+)$/", $row['object'], $M);
    $repo = $M[1];
    if (!_mtrack_timeline_is_repo_visible($repo)) {
      return null;
    }
    $id = $M[2];
    $item = "<a href='{$ABSWEB}browse.php/$repo'>$repo</a> change " .
      mtrack_changeset($id, $repo);
    break;
  case 'snippet':
    $item = "<a href='{$ABSWEB}snippet.php/$id'>View Snippet</a>";
    break;
  case 'repo':
    static $repos = array();
    if (!_mtrack_timeline_is_repo_visible($id)) {
      return null;
    }
    if (!isset($repos[$id])) {
      $repos[$id] = MTrackRepo::loadById($id);
    }
    if (is_object($repos[$id])) {
      $R = $repos[$id];
      $name = MTrackRepo::makeDisplayName($R);
      $item = "<a href='{$ABSWEB}browse.php/$name'>$name</a>";
      /* pre-existing installations may not have any audit entries */
      if (!isset($row['audit'])) {
        $row['audit'] = array();
      }
      if ($name == 'default/wiki') {
        /* pull out the list of modified files from the change audit */
        $pages = array();
        $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
        foreach ($row['audit'] as $audit) {
          if (!preg_match("/^repo:\d+:rev:/", $audit['fieldname'])) {
            continue;
          }
          $ent = json_decode($audit['value']);
          /* if a suffix is defined, only include pages that have the
           * suffix, otherwise include any pages. We remove the suffix from
           * the names of pages that we return */
          foreach ($ent->files as $page) {
            if ($suf) {
              if (substr($page, -strlen($suf)) == $suf) {
                $pages[$page] =
                  substr($page, 0, strlen($page) - strlen($suf));
              }
            } else {
              $pages[$page] = $page;
            }
          }
        }
        if (count($pages)) {
          $item = '';
          foreach ($pages as $page) {
            $item .= ' ' . mtrack_wiki($page);
          }
        }
      } else {
        /* not wiki.  This is a placeholder for changeset(s) in a
         * changegroup.  If so, those may reference a ticket.  If not, then
         * we want to emit them now */
        $checker = new MTrackCommitChecker($R);
        foreach ($row['audit'] as $audit) {
          if (!preg_match("/^repo:\d+:rev:/", $audit['fieldname'])) {
            continue;
          }
          $ent = json_decode($audit['value']);
          $a = $checker->parseCommitMessage($ent->changelog);
          if (!count($a)) {
            /* doesn't ref a ticket; ensure that we see which
             * changeset it came from if it doesn't already
             * mention it */

            if ($ent->rev === null) {
              // Ugh, workaround a bug that recorded the rev
              // as null in the audit.
              continue;
            }

            $cslink =
              "[changeset:" . $R->getBrowseRootName() . ',' .
              $ent->rev . ']';
            if (strpos($row['reason'], $cslink) === false) {
              $row['reason'] .= " (In $cslink)";
            }
            if (strpos($row['reason'], trim($ent->changelog)) === false) {
              $row['reason'] .= ' ' . $ent->changelog;
            }
          }
        }
        if (!strlen(trim($row['reason']))) {
          /* if there's nothing to say about the change, don't show it
           * in the timeline */
          return null;
        }
      }
    } else {
      $item = "&lt;item has been deleted&gt;";
    }
    break;
  }

  $reason = MTrackWiki::format_to_oneliner($row['reason'], 12);

  $html = "<div class='timelineevent'>" .
    mtrack_username($row['who'], array(
      'no_name' => true,
      'size' => 48,
      'class' => 'timelineface'
    )) .
    "<div class='timelinetext'>" .
    "<div class='timelinereason'>" .
    "$reason</div>\n" .
    "<span class='time'>$time</span> $item by " .
    mtrack_username($row['who'], array('no_image' => true)) .
    "</div>\n" .
    "</div>\n";

  return array($day, $row, $html);
}

function mtrack_render_timeline($user = null)
{
  global $ABSWEB;

  $limit = 500;

  /* get the newest items first with a short ttl */
  $newest = mtrack_cache('mtrack_get_timeline',
    array('-2 minutes', $user, $limit), 5);

  $older = mtrack_cache('mtrack_get_timeline',
    array('-6 weeks', $user, $limit), 60);

  $events = array_merge($newest, $older);

  echo "<div class='timeline'>";
  $items = array();
  $last_row = null;

  foreach ($events as $event_index => $row) {
    if (count($items) >= $limit) {
      break;
    }

    if (isset($items[$row['cid']])) {
      /* already have this one; overlap from the caches */
      continue;
    }

    /* avoid spam */
    if ($last_row && $row['reason'] == $last_row['reason'] &&
        $last_row['who'] == $row['who'] &&
        $last_row['object'] == $row['object'])
    {
      continue;
    }

    $d = date_create($row['changedate'], new DateTimeZone('UTC'));
    date_timezone_set($d, new DateTimeZone(date_default_timezone_get()));

    $item = mtrack_cache('mtrack_render_timeline_item',
      array($d, $row), 86400);

    if (!$item) {
      continue;
    }

    $items[$row['cid']] = $item;
    $last_row = $row;
  }
  $last_date = null;
  foreach ($items as $item) {
    list($day, $row, $html) = $item;
    if ($last_date != $day) {
      echo "<h1 class='timelineday'>$day</h1>\n";
      $last_date = $day;
    }
    echo $html;
  }

  echo "</div>\n";
}

