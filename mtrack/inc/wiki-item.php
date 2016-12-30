<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackWikiItem {
  public $pagename = null;
  public $filename = null;
  public $version = null;
  public $file = null;
  static $wc = null;

  function __get($name) {
    if ($name == 'content' && $this->file) {
      $this->content = stream_get_contents($this->file->cat());
      return $this->content;
    }
  }

  static function commitNow() {
    /* force any delayed push to invoke right now */
    self::$wc = null;
  }

  static function loadByPageName($name) {
    $w = new MTrackWikiItem($name);
    if ($w->file) {
      return $w;
    }
    return null;
  }

  static function getWC() {
    if (self::$wc === null) {
      self::getRepoAndRoot($repo);
      self::$wc = $repo->getWorkingCopy();
    }
    return self::$wc;
  }

  static function getRepoAndRoot(&$repo) {
    $repo = MTrackRepo::loadByName('default/wiki');
    return $repo->getDefaultRoot();
  }

  function __construct($name, $version = null) {
    $this->pagename = $name;
    $this->filename = self::getRepoAndRoot($repo) . $name;
    $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
    if ($suf) {
      $this->filename .= $suf;
    }

    if ($version !== null) {
      $this->file = $repo->file($this->filename, 'rev', $version);
    } else {
      $this->file = $repo->file($this->filename);
    }
    if ($this->file && $repo->history($this->filename, 1)) {
      $this->version = $this->file->rev;
    } else {
      $this->file = null;
    }
  }

  function save(MTrackChangeset $changeset) {
    $wc = self::getWC();
    $lfilename = $this->pagename;
    $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
    if ($suf) {
      $lfilename .= $suf;
    }

    if (!strlen(trim($this->content))) {
      if ($wc->file_exists($lfilename)) {
        // removing
        $wc->delFile($lfilename);
      }
    } else {
      if (!$wc->file_exists($lfilename)) {
        // handle dirs
        $elements = explode('/', $lfilename);
        $accum = array();
        while (count($elements) > 1) {
          $ent = array_shift($elements);
          $accum[] = $ent;
          $base = join(DIRECTORY_SEPARATOR, $accum);
          if (!$wc->file_exists($base)) {
            if (!mkdir($wc->getDir() . DIRECTORY_SEPARATOR . $base)) {
              throw new Exception(
                  "unable to mkdir(" . $wc->getDir() .
                  DIRECTORY_SEPARATOR . "$base)");
            }
            $wc->addFile($base);
          } else if (!is_dir($wc->getDir() . DIRECTORY_SEPARATOR . $base)) {
            throw new Exception("$base is not a dir; cannot create $lfilename");
          }
        }
        file_put_contents($wc->getDir() . DIRECTORY_SEPARATOR . $lfilename,
            $this->content);
        $wc->addFile($lfilename);
      } else {
        file_put_contents($wc->getDir() . DIRECTORY_SEPARATOR . $lfilename,
            $this->content);
      }
    }
    $wc->commit($changeset);
  }

  static function index_item($object)
  {
    list($ignore, $ident) = explode(':', $object, 2);

    $w = MTrackWikiItem::loadByPageName($ident);
    if ($w && strlen($w->content)) {
      MTrackSearchDB::add("wiki:$w->pagename", array(
        'type' => 'wiki',
        'wiki' => $w->content,
        'name' => $w->pagename,
        'who' => $w->who,
      ), true);
    } else {
      MTrackSearchDB::remove($object);
    }
  }

  static function _get_parent_for_acl($objectid) {
    if (preg_match("/^(wiki:.*)\/([^\/]+)$/", $objectid, $M)) {
      return $M[1];
    }
    if (preg_match("/^wiki:.*$/", $objectid, $M)) {
      return 'Wiki';
    }
    return null;
  }

  static function _build_tree($tree, $repo, $dir, $suf) {
    $items = $repo->readdir($dir);
    foreach ($items as $file) {
      $label = basename($file->name);
      if ($file->is_dir) {
        $kid = new stdclass;
        self::_build_tree($kid, $repo, $file->name, $suf);
        $tree->{$label} = $kid;
      } else {
        if ($suf && substr($label, -strlen($suf)) == $suf) {
          $label = substr($label, 0, strlen($label) - strlen($suf));
        }
        $tree->{$label} = $label;
      }
    }
  }

  static function _build_tree_top() {
    $tree = new stdclass;
    $root = MTrackWikiItem::getRepoAndRoot($repo);
    $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
    self::_build_tree($tree, $repo, '', $suf);
    return $tree;
  }

  static function get_wiki_tree() {
    return mtrack_cache(array('MTrackWikiItem', '_build_tree_top'),
      array(), 864000);
  }

  static function _get_recent($limit) {
    $recent = array();
    $root = MTrackWikiItem::getRepoAndRoot($repo);
    $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
    $sql = <<<SQL
select c.cid as cid, who, object, changedate, reason, value as json
from changes c left
  join change_audit a on (c.cid = a.cid)
where c.object = 'repo:$repo->repoid'
and fieldname like '%:rev:%'
order by c.cid desc limit $limit;
SQL;
    foreach (MTrackDB::q($sql)->fetchAll(PDO::FETCH_OBJ) as $cs) {
      $j = json_decode($cs->json);
      if (!$j) continue;
      $r = new stdclass;
      $r->who = $j->changeby;
      $r->when = MTrackAPI::date8601($j->ctime);
      $r->rev = $j->rev;
      $r->changelog = $j->changelog;
      $r->changelog_html = MTrackWiki::format_to_html($r->changelog);
      $r->pages = array();
      foreach ($j->files as $name) {
        /* if a suffix is defined, only include pages that have the suffix,
         * otherwise include any pages. We remove the suffix from the names of
         * pages that we return */
        if ($suf) {
          if (preg_match("/^(.*)$suf$/", $name, $M)) {
            $r->pages[] = $M[1];
          }
        } else {
          $r->pages[] = $name;
        }
      }
      $recent[] = $r;
    }
    return $recent;
  }

  static function get_recent_changes($limit = 20) {
    return mtrack_cache(array('MTrackWikiItem', '_get_recent'),
      array($limit));
  }

  static function rest_wiki($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET', 'PUT', 'POST');
    $page = $captures['page'];
    $rev = MTrackAPI::getParam('rev');

    $W = new MTrackWikiItem($page, $rev);
    MTrackACL::requireAnyRights("wiki:$W->pagename",
      $method == 'GET' ? 'read' : 'modify');

    $w = MTrackAPI::makeObj($W, 'pagename');
    unset($w->file);
    $w->content = $W->content;
    if (!strlen($w->content) && $method == 'GET') {
      /* this is equivalent to the page not existing */
      MTrackAPI::error(404, "no such page", $page);
    }
    if ($W->file) {
      try {
        $hist = $W->file->getChangeEvent();
      } catch (Exception $e) {
        // Happens with certain older versions of mercurial; map it as
        // a 404
        if ($method == 'GET') {
          MTrackAPI::error(404, "no such page", array($page, $rev, $w));
        }
        // otherwise, pass it on
        throw $e;
      }
      $w->version = $hist->rev;
    }
    if ($method == 'GET' && (($rev && $w->version != $rev) || (!$W->file))) {
      MTrackAPI::error(404, "no such page", array($page, $rev, $w));
    }

    $conflicted = false;
    if ($method == 'PUT' || $method == 'POST') {
      /* we're being asked to create a new version of the wiki page.
       * If version is set in the incoming payload, it identifies the
       * version of the page that the user was basing their changes from.
       * We can use this to perform a 3-way merge with any potential
       * conflicting wiki page that may exist now */
      $in = MTrackAPI::getPayload();
      if (!isset($in->comment) || !strlen(trim($in->comment))) {
        $in->comment = 'Changed';
      }
      if (isset($in->version) && $in->version != $hist->rev) {
        $basis = new MTrackWikiItem($page, $in->version);
        $orig = self::normalize_text($basis->content);

        /* $orig = the basis of the users changes */
        /* $current = content at the tip */
        $current = self::normalize_text($w->content);
        /* $mine = the desired final content */
        $mine = self::normalize_text($in->content);

        $conflicted = self::is_content_conflicted($mine);
        if (!$conflicted) {
          $mine = self::perform_three_way_merge($orig, $current, $mine);
        }
      } else {
        $mine = self::normalize_text($in->content);
      }
      $conflicted = self::is_content_conflicted($mine);

      if ($conflicted) {
        /* we won't save it, but we will return the merged version to
         * allow the user to fix up the edits */
        $w->content = $mine;
      } else {
        $CS = MTrackChangeset::begin("wiki:$page", $in->comment);
        $W->content = $mine;
        $W->save($CS);
        $CS->commit();
        self::commitNow();

        /* reload and re-compute the returned data */
        $W = new MTrackWikiItem($page);
        $hist = $W->file->getChangeEvent();
        $w = MTrackAPI::makeObj($W, 'pagename');
        unset($w->file);
        $w->content = rtrim($mine);
        $w->version = $hist->rev;
      }
    }
    $w->content_html = MTrackWiki::format_to_html($w->content, "wiki:$page");
    $w->changelog = $hist->changelog;
    $w->changelog_html = MTrackWiki::format_to_html($hist->changelog);
    $w->who = mtrack_canon_username($hist->changeby);
    $w->when = MTrackAPI::date8601($hist->ctime);

    if ($conflicted) {
      MTrackAPI::error(409, "conflict detected", $w);
    }
    return $w;
  }

  static function rest_wiki_attachments($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');

    $page = $captures['page'];
    MTrackACL::requireAnyRights("wiki:$page", "read");

    return MTrackAttachment::getList("wiki:$page");
  }

  static function perform_three_way_merge($orig, $current, $mine) {
    $tempdir = sys_get_temp_dir();
    $ofile = tempnam($tempdir, "mtrack");
    $nfile = tempnam($tempdir, "mtrack");
    $tfile = tempnam($tempdir, "mtrack");
    $pfile = tempnam($tempdir, "mtrack");
    $diff3 = MTrackConfig::get('tools', 'diff3');
    if (empty($diff3)) {
      $diff3 = 'diff3';
    }

    file_put_contents($ofile, $orig);
    file_put_contents($nfile, $mine);
    file_put_contents($tfile, $current);

    exec("$diff3 $nfile $ofile $tfile > $pfile",
        $output = array(), $retval = 0);

    if ($retval == 0) {
      /* see if there were merge conflicts */
      $content = self::merge3($nfile, $pfile);
    } else {
      $content = $mine;
    }
    unlink($ofile);
    unlink($nfile);
    unlink($tfile);
    unlink($pfile);

    return $content;
  }

  /* process the output of the diff3 command.
   * Included below is a description of the output format.
   * In our context, filename1 corresponds to the user file (mine),
   * filename2 corresponds to the data forming the basis of my changes,
   * (original) and filename3 corresponds to the changes made by someone
   * else (theirs).
   *
   * We pass in the filename of the "mine" file and the filename of
   * the file containing the diff3 output file.
   *
   * The return value is the merged result, which may contain conflict
   * markers.
   *
   * This function is needed because not all systems have the GNU diff3
   * command (which includes a merge option).

     diff3 compares  three  versions  of  a  file.  It  publishes
     disagreeing ranges of text flagged with the following codes:

     ====            all three files differ

     ====1           filename1 is different

     ====2           filename2 is different

     ====3           filename3 is different

     The type of change suffered in converting a given range of a
     given  file to some other is indicated in one of the follow-
     ing ways:

     The type of change suffered in converting a given range of a
     given  file to some other is indicated in one of the follow-
     ing ways:

     f : n1 a        Text is to be appended after line number  n1
                     in file f, where f = 1, 2, or 3.

     f : n1 , n2 c   Text is to be changed in the range  line  n1
                     to  line  n2.   If n1 = n2, the range can be
                     abbreviated to n1.

     The original contents of the range follows immediately after
     a  c  indication. When the contents of two files are identi-
     cal, the contents of the lower-numbered file is suppressed.
  */
  static function merge3($mfile, $sfile) {
    $instr = file($sfile);
    $mine = file($mfile);

    while (count($instr)) {
      $range = array_shift($instr);

      if (!preg_match("/^====(\d*)$/", $range, $M)) {
        throw new Exception("merge3: Expected file indicator! $range");
      }
      /* which file the change is from */
      $origin = $M[1];

      /* read rules for files */
      $frules = array();
      $data = array();

      while (count($instr)) {
        $rule = array_shift($instr);
        if ($rule[0] == '=') {
          array_unshift($instr, $rule);
          break;
        }

        if (preg_match("/^([123]):(\d+)a$/", $rule, $M)) {
          $file = (int)$M[1];
          $line = (int)$M[2];
          $frules[$file] = array('a', $line);
          continue;
        }

        if (preg_match("/^([123]):(\d+),(\d+)c$/", $rule, $M)) {
          $file = (int)$M[1];
          $start = (int)$M[2];
          $end = (int)$M[3];
        } else if (preg_match("/^([123]):(\d+)c$/", $rule, $M)) {
          $file = (int)$M[1];
          $start = (int)$M[2];
          $end = $start;
        } else {
          throw new Exception("ERROR: unknown rule $rule");
        }

        $frules[$file] = array('c', $start, $end);
        $nlines = ($end - $start) + 1;
        $data[$file] = array();

        /* data follows a 'c' indicator */
        while (count($instr)) {
          $line = array_shift($instr);
          if (strncmp($line, "  ", 2)) {
            array_unshift($instr, $line);
            break;
          }
          $data[$file][] = substr($line, 2);
        }
        $data[$file] = join('', $data[$file]);
      }

      /* we're interested in changes to my file, so we only look at
       * the rules for file 1 */
      if (!isset($frules[1])) {
        throw new Exception("There is no rule for file 1!?");
      }

      /* when the contents of two files are identical, the contents of the
       * lower-numbered file is suppressed */
      for ($i = 1; $i <= 3; $i++) {
        if (!isset($data[$i])) {
          for ($j = $i + 1; $j <= 3; $j++) {
            if (isset($data[$j])) {
              $data[$i] = $data[$j];
              break;
            }
          }
        }
      }
      switch ($origin) {
      case '2':
        if ($data[1] == $data[2]) {
          $diff = $data[2];
        } else {
          $diff =
            "<<<<<<< original\n" .
            $data[2] .
            "=======\n" .
            $data[1] .
            ">>>>>>> current\n";
        }
        break;
      case '1':
        /* from myself */
        $diff = $data[1];
        break;
      case '3':
        if ($data[3] == $data[1]) {
          $diff = $data[1];
        } else {
          $diff =
            "<<<<<<< theirs\n" .
            $data[3] .
            "=======\n" .
            $data[1] .
            ">>>>>>> current\n";
        }
        break;
      case '':
        $diff =
          "<<<<<<< mine\n" .
          $data[1] .
          "||||||| original\n" .
          $data[2] .
          "=======\n" .
          $data[3] .
          ">>>>>>> theirs\n";
        break;
      default:
        error_log("unhandled origin $origin in merge3 " . json_encode($data));
        throw new Exception("Unhandled origin $origin");
      }
      $rule = $frules[1];
      if ($rule[0] == 'a') {
        /* append after line, where 0 means insert at start of file */
        $line = $rule[1];
        array_splice($mine, $line, 0, $diff);
      } else if ($rule[0] == 'c') {
        $line = $rule[1] - 1;
        $end = $rule[2];
        $nlines = $end - $line;
        array_splice($mine, $line, $nlines, $diff);
      } else {
        error_log("unknown rule in merge3 " . json_encode($rule));
        throw new Exception("Unknown rule!?");
      }
    }
    return join('', $mine);
  }

  static function is_content_conflicted($content)
  {
    if (preg_match("/^([<\|>]+)\s+(mine|theirs|original)\s*$/m", $content)) {
      return true;
    }
    return false;
  }

  /* normalize text so that we always have a single trailing newline.
   * If we don't do this, we get inconsistent behavior from the diff3
   * utility */
  static function normalize_text($text) {
    return rtrim($text) . "\n";
  }


}

class MTrackWikiCommitListener implements IMTrackCommitListener {
  function vetoChangeGroup(MTrackRepo $repo, $msg, $actions, $files) {
    return true;
  }

  function vetoCommit(MTrackRepo $repo,
      MTrackCommitHookChangeEvent $change,
      $actions) {
    return true;
  }

  function postCommit(MTrackRepo $repo,
      MTrackCommitHookChangeEvent $change,
      $actions) {
    return true;
  }

  function postChangeGroup(MTrackRepo $repo, $msg, $actions, $files) {
    /* is this affecting the wiki? */
    if ($repo->getBrowseRootName() == 'default/wiki') {
      mtrack_cache_blow(array('MTrackWikiItem', '_build_tree_top'), array());

      /* this is also an ideal time to update the search index for wiki
       * pages, if we are set to immediate updates.  Normal objects that
       * live solely in our database wouldn't need such special treatment,
       * but repo changes are made partially in the context of the commit
       * hook and so won't have the same view consistency as the rest
       * of the system */
      if (MTrackConfig::get('core', 'update_search_immediate')) {
        $suf = MTrackConfig::get('core', 'wikifilenamesuffix');
        $len = strlen($suf);
        foreach ($files as $name) {
          $name = substr($name, strlen($repo->shortname) + 1);
          $is_wiki = $len == 0;
          if ($len && substr($name, -$len) == $suf) {
            $name = substr($name, 0, strlen($name) - $len);
            $is_wiki = true;
          }
          if ($is_wiki) {
            MTrackSearchDB::index_object("wiki:$name");
          }
        }
      }
    }
    return true;
  }

  static function register() {
    $l = new MTrackWikiCommitListener;
    MTrackCommitChecker::registerListener($l);
  }

};

MTrackSearchDB::register_indexer('wiki', array('MTrackWikiItem', 'index_item'));
MTrackWikiCommitListener::register();
MTrackACL::registerAncestry('wiki', array('MTrackWikiItem', '_get_parent_for_acl'));

MTrackAPI::register('/wiki/page/*page', 'MTrackWikiItem::rest_wiki');
MTrackAPI::register('/wiki/attach/*page',
    'MTrackWikiItem::rest_wiki_attachments');

