<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

// Imports data from a trac sqlite database

$name_map = array(
    'description' => 'content',
    'type' => 'classification',
    'estimatedhours' => 'estimated',
    'ec_branches' => 'branches',
    'ec_features' => 'features',
);

$trac_wiki_names = array(
  'TracAccessibility' => true,
  'TracAdmin' => true,
  'TracBackup' => true,
  'TracBrowser' => true,
  'TracCgi' => true,
  'TracChangeset' => true,
  'TracEnvironment' => true,
  'TracFastCgi' => true,
  'TracGuide' => true,
  'TracImport' => true,
  'TracIni' => true,
  'TracInstall' => true,
  'TracInstallPlatforms' => true,
  'TracInterfaceCustomization' => true,
  'TracLinks' => true,
  'TracLogging' => true,
  'TracModPython' => true,
  'TracMultipleProjects' => true,
  'TracNotification' => true,
  'TracPermissions' => true,
  'TracPlugins' => true,
  'TracQuery' => true,
  'TracReports' => true,
  'TracRevisionLog' => true,
  'TracRoadmap' => true,
  'TracRss' => true,
  'TracSearch' => true,
  'TracStandalone' => true,
  'TracSupport' => true,
  'TracSyntaxColoring' => true,
  'TracTickets' => true,
  'TracTicketsCustomFields' => true,
  'TracTimeline' => true,
  'TracUnicode' => true,
  'TracUpgrade' => true,
  'TracWiki' => true,
  'WikiDeletePage' => true,
  'WikiFormatting' => true,
  'WikiHtml' => true,
  'WikiMacros' => true,
  'WikiNewPage' => true,
  'WikiPageNames' => true,
  'WikiProcessors' => true,
  'WikiRestructuredText' => true,
  'WikiRestructuredTextLinks' => true,
  'CamelCase' => true,
  'InterMapTxt' => true,
  'InterTrac' => true,
  'InterWiki' => true,
  'RecentChanges' => true,
  'SandBox' => true,
  'TitleIndex' => true,
);

function trac_date($unix, $trac_version) {
  if ($trac_version === '0.12')
    $unix = substr($unix, 0, count($unix)-7); /* Remove the last 6 characters which are milliseconds in trac 0.12 */
  return $unix;
}

function trac_get_comp($name, $deleted = true)
{
  global $CS;
  global $components_by_name;
  
  if (!strlen($name)) return null;

  if (!isset($components_by_name[$name])) {
    /* no longer exists */
    $comp = new MTrackComponent;
    $comp->name = $name;
    $comp->deleted = $deleted;
    $comp->save($CS);
    $components_by_name[$comp->name] = $comp;
    return $comp;
  }
  return $components_by_name[$name];
}

function trac_assoc_comp_and_proj(MTrackComponent $comp, MTrackProject $proj)
{
  static $comp_assoc = array();

  if (isset($comp_assoc[$proj->shortname][$comp->name])) {
    return;
  }

  MTrackDB::q('insert into components_by_project (projid, compid)
    values (?, ?)', $proj->projid, $comp->compid);

  $comp_assoc[$proj->shortname][$comp->name] = true;
}

function cache_mtrack_users()
{
  trac_add_user();
}

function trac_add_user($username = NULL)
{
  static $users = array();
  global $CANON_USERS;

  if(is_null($username)) {
    $q = MTrackDB::q('select userid from userinfo');
    foreach($q->fetchAll(PDO::FETCH_NUM) as $row) {
      $users[$row[0]] = true;
    }
    return null;
  }
  
  $username = trim($username);
  $username = strtolower($username);

  while (isset($CANON_USERS[$username])) {
    $username = strtolower($CANON_USERS[$username]);
  }

  if (preg_match('/[ ,]/', $username)) {
    // invalid: attempted to set multiple people.
    // take the first one
    list($username) = preg_split('/[ ,]+/', $username);

    while (isset($CANON_USERS[$username])) {
      $username = strtolower($CANON_USERS[$username]);
    }
  }

  if (preg_match('/^\d+(\.\d+)?$/', $username)) {
    // invalid (looks like a version number)
    return null;
  }

  if ($username == 'somebody' || $username == '') {
    return null;
  }

  if (isset($users[$username])) {
    return $username;
  }

  $users[$username] = true;
  switch ($username) {
    case 'trac':
      $active = 0;
      break;
    default:
      $active = 1;
  }

  try {
    MTrackDB::q(
    'insert into userinfo (userid, active) values (?, ?)',
    $username, $active);
  } catch (Exception $e) {
  }

  return $username;
}

function trac_get_milestone($name, MTrackProject $proj)
{
  global $CS;
  global $milestone_by_name;
  static $alias = array();

  $lname = strtolower($name);
  if (isset($alias[$proj->shortname][$lname])) {
    $name = $alias[$proj->shortname][$lname];
  } else {
    $alias[$proj->shortname][$lname] = $name;
  }

  if (!isset($milestone_by_name[$lname])) {
    /* first see if there's a milestone with this name in another project */
    $ms = MTrackMilestone::loadByName($name);
    if ($ms) {
      $alias[$proj->shortname][$lname] .= " ($proj->shortname)";
      $name = $alias[$proj->shortname][$lname];
    }
      
    $ms = new MTrackMilestone();
    $ms->name = $name;
    $ms->deleted = true;
    $ms->description = '';
    $ms->save($CS);
    $milestone_by_name[$lname] = $ms;
  }
  return $milestone_by_name[$lname];
}

function trac_get_keyword($word)
{
  static $words = array();

  if (isset($words[$word])) {
    return $words[$word];
  }

  $kw = MTrackKeyword::loadByWord($word);

  if (!$kw) {
    global $CS;
    $kw = new MTrackKeyword;
    $kw->keyword = $word;
    $kw->save($CS);
  }

  $words[$word] = $kw;

  return $kw;
}

function progress($msg)
{
  static $events = 0;
  static $last = 0;
  static $clr_eol = null;
  static $clr_eod = null;

  if ($clr_eol === null) {
    /* el: clr_eol
     * ed: clr_eos
     */
    $clr_eol = shell_exec("tput el");
    $clr_eod = shell_exec("tput ed");
  }

  $events++;

  $now = time();

  if ($events % 10 || $now - $last > 2) {
    echo "\r$clr_eod$msg"; flush();
  }
  $last = $now;
}
  
$components_by_name = array();

function adjust_links($reason, $ticket_prefix, MTrackProject $project)
{
  return $project->adjust_links($reason, $ticket_prefix);
}

function import_from_trac(MTrackProject $project, $import_from_db, $trac_version, $ticket_prefix = false,
                          $skip_trac_commits = false)
{
  global $components_by_name;
  global $milestone_by_name;

  cache_mtrack_users();

  echo "Importing trac database $import_from_db\n"; flush();

  $start_import = time();

  /* reset this list so that we can detect conflicting names
   * across projects */
  $milestone_by_name = array();


  if (!file_exists("$import_from_db/db/trac.db")) {
    echo "No such file $import_from_db/db/trac.db\n";
    exit(1);
  }

  $trac = new PDO('sqlite:' . $import_from_db . "/db/trac.db");
  $trac->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  //date_default_timezone_set('UTC');

  $CS = MTrackChangeset::begin('~import~', "Import trac from $import_from_db");

  foreach ($trac->query(
        "select type, name, value from enum")->fetchAll()
      as $row) {

    if ($row['type'] == 'priority') {
      try {
        $pri = MTrackPriority::loadByName($row['name']);
      } catch (Exception $e) {
        $pri = new MTrackPriority;
        $pri->name = $row['name'];
        $pri->value = $row['value'];
        $pri->save($CS);
      }
    }

    if ($row['type'] == 'severity') {
      try {
        $pri = MTrackSeverity::loadByName($row['name']);
      } catch (Exception $e) {
        $pri = new MTrackSeverity;
        $pri->name = $row['name'];
        $pri->value = $row['value'];
        $pri->save($CS);
      }
    }

    if ($row['type'] == 'resolution') {
      try {
        $pri = MTrackResolution::loadByName($row['name']);
      } catch (Exception $e) {
        $pri = new MTrackResolution;
        $pri->name = $row['name'];
        $pri->value = $row['value'];
        $pri->save($CS);
      }
    }

    if ($row['type'] == 'ticket_type') {
      try {
        $pri = MTrackClassification::loadByName($row['name']);
      } catch (Exception $e) {
        $pri = new MTrackClassification;
        $pri->name = $row['name'];
        $pri->value = $row['value'];
        $pri->save($CS);
      }
    }
  }

  foreach ($trac->query('select name from component')->fetchAll() as $row) {
    $comp = trac_get_comp($row['name'], false);
    trac_assoc_comp_and_proj($comp, $project);
  }

  foreach ($trac->query("SELECT * from milestone order by name")
      ->fetchAll(PDO::FETCH_ASSOC) as $row) {
    /* first see if there's a milestone with this name in another project */
    $name = $row['name'];
    $ms = MTrackMilestone::loadByName($name);
    if ($ms) {
      $name .= ' (' . $project->shortname . ')';
    }
    $ms = new MTrackMilestone();
    $ms->name = $name;
    /* for names of the form: sprint.1 sprint.2, tie them back as
     * children of "sprint" */
    if (preg_match("/^(.*)\.(\d+)$/", $name, $M)) {
      $pms = $milestone_by_name[strtolower($M[1])];
      if ($pms !== null) {
        $ms->pmid = $pms->mid;
      }
    }
    $ms->description = $row['description'];
    $ms->duedate = MTrackDB::unixtime(trac_date($row['due'], $trac_version));
    $ms->completed = MTrackDB::unixtime(trac_date($row['completed'], $trac_version));
    $ms->save($CS);
    $milestone_by_name[strtolower($row['name'])] = $ms;
  }

  $CS->commit();
  $CS = null;

  list($maxtkt) = $trac->query("select max(id) from ticket")->fetchAll(PDO::FETCH_COLUMN, 0);
  MTrackConfig::append('trac_import',
    "max_ticket:$project->shortname", $maxtkt);

  /* first pass is to reserve ticket ids that match the trac db */
  foreach ($trac->query(
        "SELECT * from ticket order by id")
      ->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $row['reporter'] = trac_add_user($row['reporter']);
    progress("issue $row[id] $row[reporter]");

    $fields = array('summary', 'description', 'resolution', 'status',
        'owner', 'summary', 'component', 'priority', 'severity',
        'changelog',
        'version', 'cc', 'keywords', 'milestone', 'reporter', 'type');

    foreach ($trac->query(
          "select name, value from ticket_custom where ticket='$row[id]'")
        ->fetchAll(PDO::FETCH_ASSOC) as $custom) {
      if (strlen($custom['value'])) {
        $field = $custom['name'];
        $row[$field] = $custom['value'];
        $fields[] = $field;
      }
    }

    /* take a peek at the change history on the ticket to see if we can
     * determine the original field values */
    foreach ($fields as $field) {
      foreach ($trac->query(
            "SELECT oldvalue from ticket_change where ticket = '" .
            $row['id'] . "' and field='$field' order by time LIMIT 1")
          ->fetchAll(PDO::FETCH_ASSOC) as $hist) {
        if (!strlen($hist['oldvalue'])) {
          $row[$field] = null;
        } else {
          $row[$field] = $hist['oldvalue'];
        }
      }
    }

    $ctime = trac_date($row['time'], $trac_version);

    MTrackAuth::su($row['reporter']);
    $CS = MTrackChangeset::begin('ticket:X', $row['summary'], $ctime);

    $issue = new MTrackIssue();
    $issue->summary = $row['summary'];
    $issue->description = adjust_links($row['description'], $ticket_prefix, $project);
    $issue->priority = $row['priority'];
    $issue->classification = $row['type'];
    $issue->resolution = $row['resolution'];
    $issue->severity = $row['severity'];
    $issue->changelog = $row['changelog'];
    $issue->cc = $row['cc'];

    $issue->addEffort(0, $row['estimatedhours']);
    $issue->addEffort($row['totalhours']);

    if (strlen($row['component'])) {
      $comp = trac_get_comp($row['component']);
      $issue->assocComponent($comp);
    }
    if (strlen($row['milestone'])) {
      $ms = trac_get_milestone($row['milestone'], $project);
      $issue->assocMilestone($ms);
    }

    foreach (array('keywords', 'features', 'ec_features',
          'version',
          'branches', 'ec_branches') as $field) {
      foreach (preg_split("/\s+/", $row[$field]) as $w) {
        if (strlen($w)) {
          $kw = trac_get_keyword($w);
          $issue->assocKeyword($kw);
        }
      }
    }

    if (strlen($row['owner']) && $row['owner'] != 'somebody') {
      $row['owner'] = trac_add_user($row['owner']);
      $issue->owner = $row['owner'];
    }

    if ($ticket_prefix) {
      $issue->nsident = $project->shortname . $row['id'];
    } else {
      $issue->nsident = $row['id'];
    }

    $issue->save($CS);

#    if ($issue->tid != $row['id']) {
#      throw new Exception(
#          "expected doc to be created with $row[id], got $issue->tid");
#    }
    $CS->setObject("ticket:" . $issue->tid);
    $CS->commit();
    $CS = null;
    $issue = null;
    MTrackAuth::drop();
  }

  /* now make a pass through the history to flesh out the comments and
   * other changes.
   * This can use up a surprising amount of memory, so we stage in
   * the work. */

  echo "\nLooking for changes in $import_from_db\n"; flush();

  $changes = $trac->query(
      "select distinct time, ticket, author from 
      ticket_change order by ticket asc, time, author")
    ->fetchAll(PDO::FETCH_NUM);

  foreach ($changes as $i => $row) {
    // we order by field because we always want "estimatedhours"
    // to apply before "hours"
    $q = $trac->prepare(
        "select * from ticket_change
        where time = ? and ticket = ? and author = ?
        order by field
        ");
    $q->execute($row);
    $batch = $q->fetchAll(PDO::FETCH_ASSOC);
    if (empty($batch)) continue;
    list($first) = $batch;
    global $CS;

    $first['author'] = trac_add_user($first['author']);
    MTrackAuth::su($first['author']);
    try {
      progress("issue $first[ticket] changed by $first[author]");

      if ($ticket_prefix) {
        $issue = MTrackIssue::loadByNSIdent(
                  $project->shortname . $first['ticket']);
      } else {
        $issue = MTrackIssue::loadByNSIdent($first['ticket']);
      }

      $CS = MTrackChangeset::begin("ticket:" . $issue->tid,
          "changed", trac_date($first['time'], $trac_version));


      foreach ($batch as $row) {
        switch ($row['field']) {
          case 'comment':
            // Trac commits start with "(In [<rev>])"
            if($skip_trac_commits && strpos($row['newvalue'], "(In [") === 0) break;
            $row['newvalue'] = adjust_links($row['newvalue'], $ticket_prefix, $project);
            $issue->addComment($row['newvalue']);
            $CS->setReason($row['newvalue']);
            break;

          case 'owner':
            $row['newvalue'] = trac_add_user($row['newvalue']);
            if ($row['newvalue'] == 'somebody') {
              $issue->owner = null;
            } else {
              $issue->owner = $row['newvalue'];
            }
            break;

          case 'status':
            if ($row['newvalue'] == 'closed') {
              $issue->close();
            } else {
              $issue->status = $row['newvalue'];
            }
            break;

          case 'description':
            $issue->description = adjust_links($row['newvalue'],
                                    $ticket_prefix, $project);
            break;

          case 'resolution':
          case 'summary':
          case 'priority':
          case 'severity':
          case 'changelog':
          case 'cc':
            $name = $row['field'];
            $issue->$name = $row['newvalue'];
            break;

          case 'component':
            foreach ($issue->getComponents() as $comp) {
              $comp = trac_get_comp($comp);
              if ($comp) {
                $issue->dissocComponent($comp);
              }
            }
            if (strlen($row['newvalue'])) {
              $comp = trac_get_comp($row['newvalue']);
              $issue->assocComponent($comp);
            }
            break;

          case 'milestone':
            foreach ($issue->getMilestones() as $ms) {
              $ms = trac_get_milestone($ms, $project);
              if ($ms) {
                $issue->dissocMilestone($ms);
              }
            }
            if (strlen($row['newvalue'])) {
              $ms = trac_get_milestone($row['newvalue'], $project);
              $issue->assocMilestone($ms);
            }
            break;

          case 'keywords':
          case 'features':
          case 'ec_features':
          case 'ec_branches':
          case 'branches':
          case 'version':
            foreach ($issue->getKeywords() as $w) {
              $kw = trac_get_keyword($w);
              $issue->dissocKeyword($kw);
            }
            foreach (preg_split("/\s+/", $row['newvalue']) as $w) {
              if (strlen($w)) {
                $kw = trac_get_keyword($w);
                $issue->assocKeyword($kw);
              }
            }
            break;

          case 'type':
            $issue->classification = $row['newvalue'];
            break;

          case 'totalhours':
          case 'reporter':
            /* ignore */
            break;

          case 'hours':
            $issue->addEffort($row['newvalue'] + 0);
            break;

          case 'duration':
          case 'estimatedhours':
            $issue->addEffort(0, $row['newvalue'] + 0);
            break;

          default:
            throw new Exception("cant handle field $row[field]");
        }
      }
      if(is_object($issue)) {
        $issue->save($CS);
      }
      else {
        print "\nWTF? Issue isn't an object now?\n";
      }
      $issue = null;
      $CS->commit();
      $CS = null;

    } catch (Exception $e) {
      MTrackAuth::drop();
      throw $e;
    }
    MTrackAuth::drop();
  }

  /* Find attachments */
  foreach ($trac->query(
      "select id, filename, size, time, description, author
        from attachment where type = 'ticket'")
      ->fetchAll(PDO::FETCH_ASSOC) as $row) {

    MTrackAuth::su($row['author']);
    try {
      $row['author'] = trac_add_user($row['author']);
      $row['filename'] = trac_attachment_name($row['filename']);
      progress("issue $row[id] attachment $row[filename] $row[author]");

      if ($ticket_prefix) {
        $issue = MTrackIssue::loadByNSIdent(
            $project->shortname . $row['id']);
      } else {
        $issue = MTrackIssue::loadByNSIdent($row['id']);
      }

      $CS = MTrackChangeset::begin("ticket:" . $issue->tid,
          $row['description'], trac_date($row['time'], $trac_version));

      $afile = $import_from_db . "/attachments/ticket/$row[id]/";

      // trac uses weird url encoding on the filename on disk.
      // this weird looking code is because I'm too lazy to reverse
      // engineer their encoding
      foreach (glob("$afile/*") as $potential) {
        if (trac_attachment_name(basename($potential)) == $row['filename']) {
          $afile = $potential;
          break;
        }
      }
      MTrackAttachment::add("ticket:$issue->tid",
          $afile, $row['filename'], $CS);
      $CS->commit();

    } catch (Exception $e) {
      MTrackAuth::drop();
      throw $e;
    }
    MTrackAuth::drop();
  }

  /* Make another pass over the tickets to catch changes made to the
   * database by hand that are not journalled in the trac change tables */
  MTrackAuth::su('trac');
  foreach ($trac->query(
        "SELECT * from ticket order by id")
      ->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $fields = array('summary',
        'description',
        'resolution', 'status',
        'owner', 'summary', 'component', 'priority', 'severity',
        'changelog',
        'version', 'cc', 'keywords', 'milestone', 'reporter', 'type');

    foreach ($trac->query(
          "select name, value from ticket_custom where ticket=$row[id]")
        ->fetchAll(PDO::FETCH_ASSOC) as $custom) {
      if (strlen($custom['value'])) {
        $field = $custom['name'];
        if ($field == 'description') {
          $custom['value'] = adjust_links($custom['value'], $ticket_prefix, $project);
        }

        $row[$field] = $custom['value'];
        $fields[] = $field;
      }
    }

    if ($ticket_prefix) {
      $issue = MTrackIssue::loadByNSIdent($project->shortname . $row['id']);
    } else {
      $issue = MTrackIssue::loadByNSIdent($row['id']);
    }
    $needed = false;

    $row['owner'] = trac_add_user($row['owner']);
    $fmap = array(
      'summary',
      'description',
      'priority',
      'status',
      'classification' => 'type',
      'resolution',
      'owner',
      'severity');

    foreach ($fmap as $sname => $fname) {
      if (is_int($sname) || ctype_digit($sname)) {
        $sname = $fname;
      }
      if ($fname == 'description') {
        $row[$fname] = adjust_links($row[$fname], $ticket_prefix, $project);
      }
      if ($issue->$sname != $row[$fname]) {
        $needed = true;
        $issue->$sname = $row[$fname];
      }
    }

    $comp = $issue->getComponents();
    $comp = reset($comp);
    if ($comp != $row['component']) {
      $needed = true;
      $issue->dissocComponent(trac_get_comp($comp));
      if (strlen($row['component'])) {
        $comp = trac_get_comp($row['component']);
        $issue->assocComponent($comp);
      }
    }

    $ms = $issue->getMilestones();
    $ms = reset($ms);
    if ($ms != $row['milestone']) {
      $needed = true;
      $issue->dissocMilestone(trac_get_milestone($ms, $project));
      if (strlen($row['milestone'])) {
        $ms = trac_get_milestone($row['milestone'], $project);
        $issue->assocMilestone($ms);
      }
    }

    if ($needed) {
      progress("$row[id] fixup");
      if ($issue->updated) {
        $last_cs = MTrackChangeset::get($issue->updated);
      } else {
        $last_cs = MTrackChangeset::get($issue->created);
      }
      $issue->addComment(
        "The importer detected manual database changes; " .
        "revising ticket to match");
      $CS = MTrackChangeset::begin("ticket:" . $issue->tid,
            "fixup", 
            strtotime($last_cs->when));
      $issue->save($CS);
      $CS->commit();
    }
  }
  MTrackAuth::drop();

  echo "\nProcessing wiki pages\n"; flush();

  /* wiki, jungle is posse */
  global $trac_wiki_names;
  $wiki = null;

  $wiki_page_remap = array();
  $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
  if (!strlen($suf)) {
    /* Here's a fun problem; trac allows both pages and dirs to exist with the
     * same name (because its dirs aren't really dirs, they're just illusions)
     * We need to notice those that are pages and that collide with dirs and
     * rename them */
    $all_wiki_page_names = array();
    foreach ($trac->query(
          "select distinct name from wiki")->fetchAll(PDO::FETCH_COLUMN, 0)
        as $name) {
      $all_wiki_page_names[$name] = $name;
    }

    foreach ($all_wiki_page_names as $name) {
      $elements = explode('/', $name);
      if (count($elements) > 1) {
        $accum = array();
        while (count($elements) > 1) {
          $accum[] = array_shift($elements);
          $n = join('/', $accum);
          if (isset($all_wiki_page_names[$n])) {
            // Collision; try adding a suffix of "Page"
            if (!isset($all_wiki_page_names[$n . 'Page'])) {
              $wiki_page_remap[$n] = $n . 'Page';
            } else {
              throw new Exception("wiki collision between $n and $name");
            }
          }
        }
      }
    }
    echo "The following pages will be renamed\n";
    print_r($wiki_page_remap);
  }

  foreach ($trac->query(
        "SELECT * from wiki order by time, name, version")
      ->fetchAll(PDO::FETCH_ASSOC) as $row) {

    if (isset($trac_wiki_names[$row['name']])) {
      continue;
    }
    if (isset($wiki_page_remap[$row['name']])) {
      $row['name'] = $wiki_page_remap[$row['name']];
    }

    $author = trac_add_user($row['author']);
    try {
      MTrackAuth::su($author);
      $row['author'] = $author;
    } catch (Exception $e) {
      echo "Error while assuming $author ($row[author])\n";
      MTrackAuth::drop();
      throw $e;
    }
    if ($ticket_prefix) {
      $row['name'] = $project->shortname . '/' . $row['name'];
    }
    $CS = MTrackChangeset::begin('wiki:' . $row['name'],
        $row['comment'], trac_date($row['time'], $trac_version));
    if (!is_object($wiki) || $wiki->pagename != $row['name']) {
      $wiki = MTrackWikiItem::loadByPageName($row['name']);
    }
    if (!$wiki) {
      $wiki = new MTrackWikiItem($row['name']);
    }
    progress("$row[name] $row[version]");
    $wiki->content = adjust_links($row['text'], $ticket_prefix, $project);
    $wiki->save($CS);
    $CS->commit();
    MTrackAuth::drop();
  }
  /* Find attachments */
  foreach ($trac->query(
      "select id, filename, size, time, description, author
        from attachment where type = 'wiki'")
      ->fetchAll(PDO::FETCH_ASSOC) as $row) {

    MTrackAuth::su($row['author']);
    try {
      $row['author'] = trac_add_user($row['author']);
      $row['filename'] = trac_attachment_name($row['filename']);

      progress("wiki $row[id] attachment $row[filename] $row[author]");

      if ($ticket_prefix) {
        $name = $project->shortname . '/' . $row['id'];
      } else {
        $name = $row['id'];
      }

      $wiki = MTrackWikiItem::loadByPageName($name);
      if (!$wiki) {
        MTrackAuth::drop();
        continue;
      }

      $CS = MTrackChangeset::begin('wiki:' . $name,
          $row['description'], trac_date($row['time'], $trac_version));

      $afile = $import_from_db . "/attachments/wiki/$row[id]/";

      // trac uses weird url encoding on the filename on disk.
      // this weird looking code is because I'm too lazy to reverse
      // engineer their encoding
      foreach (glob("$afile/*") as $potential) {
        if (trac_attachment_name(basename($potential)) == $row['filename']) {
          $afile = $potential;
          break;
        }
      }
      if (!is_file($afile)) {
        echo "Looking for attachment $row[filename]\n";
        echo "Didn't find it in $afile\n";
        $g = glob("$afile/*");
        print_r($g);
        foreach ($g as $f) {
          echo trac_attachment_name($f), "\n";
        }
        throw new Exception("fail");
      }
      MTrackAttachment::add("wiki:$name",
          $afile, $row['filename'], $CS);
      $CS->commit();

    } catch (Exception $e) {
      MTrackAuth::drop();
      throw $e;
    }
    MTrackAuth::drop();
  }


  $end_import = time();
  $elapsed = $end_import - $start_import;
  echo "\nDone with $import_from_db (in $elapsed seconds)\n"; flush();
}

function trac_attachment_name($name)
{
  $name = urldecode($name);
  $name = str_replace('+', ' ', $name);
  return $name;
}
