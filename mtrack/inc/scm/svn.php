<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* Subversion SVN browsing */

class MTrackSCMFileSVN extends MTrackSCMFile {
  public $name;
  public $rev;
  public $is_dir;
  public $repo;

  function __construct(MTrackSCM $repo, $name, $rev, $is_dir = false)
  {
    $this->repo = $repo;
    $this->name = $name;
    $this->rev = $rev;
    $this->is_dir = $is_dir;
  }

  public function _determineFileChangeEvent($reponame, $filename, $rev)
  {
    $repo = MTrackRepo::loadByName($reponame);
    list($ent) = $repo->history($filename, 1, 'rev', $rev);
    return $ent;
  }

  public function getChangeEvent()
  {
    return mtrack_cache(
      array('MTrackSCMFileSVN', '_determineFileChangeEvent'),
      array($this->repo->getBrowseRootName(), $this->name, $this->rev));
  }

  function cat()
  {
    return $this->repo->svn('cat', '-r', $this->rev,
      'file://' . $this->repo->repopath . '/' . $this->name . "@$this->rev");
  }

  function annotate($include_line_content = false)
  {
    $xml = stream_get_contents($this->repo->svn('annotate', '--xml',
      'file://' . $this->repo->repopath . '/' . $this->name . "@$this->rev"));
    $ann = array();
    $xml = @simplexml_load_string($xml);
    if (!is_object($xml)) {
      return 'DELETED';
    }
    if ($include_line_content) {
      $cat = $this->cat();
    }
    foreach ($xml->target->entry as $ent) {
      $A = new MTrackSCMAnnotation;
      $A->rev = (int)$ent->commit['revision'];
      $A->changeby = (string)$ent->commit->author;
      if ($include_line_content) {
        $A->line = fgets($cat);
      }
      $ann[(int)$ent['line-number']] = $A;
    }
    return $ann;

  }
}

class MTrackWCSVN extends MTrackSCMWorkingCopy {
  public $repo;

  function __construct(MTrackRepo $repo) {
    $this->dir = mtrack_make_temp_dir();
    $this->repo = $repo;

    stream_get_contents($this->repo->svn('checkout',
      'file://' . $this->repo->repopath . '/trunk',
      $this->dir));
  }

  function getFile($path)
  {
    return $this->repo->file('trunk/' . $path);
  }


  function addFile($path)
  {
    stream_get_contents(
      $this->repo->svn('add', $this->dir . DIRECTORY_SEPARATOR . $path));
  }

  function delFile($path)
  {
    stream_get_contents(
      $this->repo->svn('rm', $this->dir . DIRECTORY_SEPARATOR . $path));
  }

  function commit(MTrackChangeset $CS)
  {
    list($proc, $pipes) = mtrack_run_tool('svn', 'proc',
      array('ci', '--non-interactive', '--username', $CS->who,
        '-m', $CS->reason, $this->dir));
/*
    $svn = MTrackConfig::get('tools', 'svn');
    if (!strlen($svn)) $svn = 'svn';
    $proc = proc_open(
      "$svn ci --non-interactive " .
      ' --username ' . escapeshellarg($CS->who) .
      ' -m ' . escapeshellarg($CS->reason) .
      ' ' . $this->dir,
      array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
      ), $pipes, $this->dir);
*/
    $pipes[0] = null;
    $output = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);

    if (strlen($err)) {
      throw new Exception($err);
    }

    if (preg_match("/Committed revision (\d+)/", $output, $M)) {
      $rev = $M[1];
      stream_get_contents(
          $this->repo->svn('propset', 'svn:date',
            '--revprop',
            '-r', $rev, $CS->when, $this->dir
            ));
    }
  }
}

class MTrackSCMSVN extends MTrackRepo {
  protected $svn = 'svn';
  static $debug = false;

  public function getSCMMetaData() {
    return array(
      'name' => 'Subversion',
      'tools' => array('svn', 'svnlook', 'svnadmin'),
    );
  }

  function getServerURL() {
    $url = parent::getServerURL();
    if ($url) return $url;
    $url = MTrackConfig::get('repos', 'serverurl');
    if ($url) {
      return "svn+ssh://$url/" . $this->getBrowseRootName() . '/BRANCHNAME';
    }
    return null;
  }


  public function reconcileRepoSettings(MTrackSCM $r = null) {
    if ($r == null) {
      $r = $this;
    }
    if (!is_dir($r->repopath)) {
      $stm = mtrack_run_tool('svnadmin', 'read', array('create', $r->repopath));
      $out = stream_get_contents($stm);
      if (pclose($stm)) {
        throw new Exception("failed to create repo: $out");
      }
      file_put_contents("$r->repopath/hooks/pre-revprop-change",
          "#!/bin/sh\nexit 0\n");
      chmod("$r->repopath/hooks/pre-revprop-change", 0755);

      $me = mtrack_canon_username(MTrackAuth::whoami());
      $stm = mtrack_run_tool('svn', 'read', array('mkdir', '-m', 'init',
        '--username', $me, "file://$r->repopath/trunk"));
      $out = stream_get_contents($stm);
      if (pclose($stm)) {
        throw new Exception("failed to create trunk: $out");
      }
      system("chmod -R 02777 $r->repopath/db $r->repopath/locks");

      $authzname = MTrackConfig::get('core', 'vardir') . '/svn.authz';
      $svnserve = "[general]\nauthz-db = $authzname\n";
      file_put_contents("$r->repopath/conf/svnserve.conf", $svnserve);

      $php = MTrackConfig::get('tools', 'php');
      $conffile = realpath(MTrackConfig::getLocation());
      $hook = realpath(dirname(__FILE__) . '/../../bin/svn-commit-hook');
      foreach (array('pre', 'post') as $step) {
        $script = <<<HOOK
#!/bin/sh
exec $php $hook $step $r->repopath \$2 $conffile

HOOK;
        $target = "$r->repopath/hooks/$step-commit";
        if (file_put_contents("$target.mtrack", $script)) {
          chmod("$target.mtrack", 0755);
          rename("$target.mtrack", $target);
        }
      }
    }
  }

  public function getDefaultRoot() {
    return 'trunk/';
  }

  public function getBranches()
  {
    return null;
  }

  public function getTags()
  {
    return null;
  }

  public function readdir($path, $object = null, $ident = null)
  {
    $res = array();

    if ($object === null) {
      $object = 'rev';
      $ident = 'HEAD';
    }
    $rev = $this->resolveRevision(null, $object, $ident);

    $rpath = $this->repopath;
    if (strlen($path)) {
      $rpath .= "/$path";
    }

    $fp = $this->svn('ls', '--xml', '-r', $rev,
          "file://" . $rpath);

    $ls = stream_get_contents($fp);
    $doc = simplexml_load_string($ls);
    if (!is_object($doc)) {
      echo '<pre>', htmlentities($ls, ENT_QUOTES, 'utf-8'), '</pre>';
    }
    if (isset($doc->list)) foreach ($doc->list->entry as $le) {
      $name = $path;
      $name .= '/';
      $name .= $le->name;
      if ($name[0] == '/') {
        $name = substr($name, 1);
      }
      /* Use the revision passed in to readdir rather than the revision
       * in the entry, as svn can return a revision number that pre-dates
       * that of the containing tag, and this causes the subsequent
       * lookup of commit data to fail */
      $res[] = new MTrackSCMFileSVN($this, $name,
          //$le->commit['revision'],
          $rev,
          $le['kind'] == 'dir');
    }
    return $res;
  }

  public function file($path, $object = null, $ident = null)
  {
    if ($object == null) {
      $object = 'rev';
      $ident = 'HEAD';
    }
    $rev = $this->resolveRevision(null, $object, $ident);
    return new MTrackSCMFileSVN($this, $path, $rev);
  }

  public function history($path, $limit = null, $object = null, $ident = null)
  {
    $res = array();
    $args = array();
    $limit_date = null;

    if ($limit !== null) {
      if (!is_int($limit) && !preg_match("/^\d+$/", $limit)) {
        $limit_date = strtotime($limit);
        $limit = null;
        $limit_date = date('c', $limit_date);
      }
    }

    $use_at_rev = false;
    if ($object !== null) {
      $rev = $this->resolveRevision(null, $object, $ident);
      if ($limit_date != null) {
        $args[] = '-r';
        $args[] = $rev . ':{' . $limit_date . '}';
      } else if ($rev == 'HEAD') {
        $args[] = '-r';
        $args[] = "$rev:1";
      } else {
        $use_at_rev = true;
      }
    }
    if ($limit !== null) {
      $args[] = '--limit';
      $args[] = $limit;
    } else if ($limit_date !== null) {
      $args[] = '-r';
      $args[] = '{' . $limit_date . '}:head';
    }

    $rpath = $this->repopath;
    if (strlen($path)) {
      if ($path[0] != '/') {
        $rpath .= '/';
      }
      $rpath .= $path;
    }
    $spath = $rpath;

    if ($use_at_rev) {
      $spath .= "@$rev";
    }

    $fp = $this->svn('log', '--xml', '-v', $args, "file://$spath");

    $xml = stream_get_contents($fp);
    $doc = @simplexml_load_string($xml);
    if (!is_object($doc)) {
      /* try looking at the parent */
      $spath = dirname($spath);
      if ($use_at_rev) {
        $spath .= "@$rev";
      }
      $fp = $this->svn('log', '--xml', '-v', $args, "file://$spath");
      $xml = stream_get_contents($fp);
      $doc = @simplexml_load_string($xml);
    }

    if (!is_object($doc)) {
//      echo '<pre>', htmlentities($xml, ENT_QUOTES, 'utf-8'), '</pre>';
      return null;
    }
    if (self::$debug) {
      if (php_sapi_name() == 'cli') {
        echo $xml, "\n";
      } else {
        echo htmlentities(var_export($xml, true)) . "<br>";
      }
    }
    $origpath = $path;
    if ($origpath[0] != '/') {
      $origpath = '/' . $origpath;
    }
    if ($doc->logentry) foreach ($doc->logentry as $le) {
      $matched = false;
      $ent = new MTrackSCMEvent;
      $ent->rev = (int)$le['revision'];
      $ent->branches = array();
      $ent->tags = array();

      $ent->files = array();
      foreach ($le->paths->path as $path) {
        if (strncmp($path, $origpath, strlen($origpath))) {
          if (count($le->paths) == 1 &&
              $path['action'] == 'A' && isset($path['copyfrom-path'])) {
            /* branch/tag creation event.
             * When this happens, we get a record like this:

<paths>
<path
   copyfrom-path="/mobility/branches/ecelerity-3.1"
   copyfrom-rev="40075"
   action="A">/branches/3.2-dev</path>
</paths>

             * This indicates that the file/dir we are interrogating
             * was created by creating a tag/branch, so we recognize
             * this situation, where we have a single path entry and
             * it is an svn copy, as matching the file in question */
          } else {
            continue;
          }
        }
        $matched = true;
        $f = new MTrackSCMFileEvent;
        $f->name = (string)$path;
        $f->status = (string)$path['action'];
        $ent->files[] = $f;
      }

      if ($matched) {
        $ent->changeby = (string)$le->author;
        $ent->ctime = MTrackDB::unixtime(strtotime($le->date));
        $ent->changelog = (string)$le->msg;

        $res[] = $ent;
      }
    }
    $fp = null;
    if (count($res) == 0) {
      return null;
    }
    return $res;
  }

  function getCheckoutCommand() {
    $url = $this->getServerURL();
    if (strlen($url)) {
      return $this->scmtype . ' checkout ' . $this->getServerURL();
    }
    return null;
  }

  public function diff($path, $from = null, $to = null)
  {
    $is_file = null;

    if ($path instanceof MTrackSCMFile) {
      $is_file = !$path->is_dir;
      if ($from === null) {
        $from = $path->rev;
      }
      $path = $path->name;
    } elseif ($path instanceof MTrackSCMFileEvent) {
      $is_file = true;
    } else {
      // http://subversion.tigris.org/issues/show_bug.cgi?id=2873
      // Essentially, if there are files added in a changeset, you cannot use
      // diff to show the diff of those newly added files if you explicitly
      // request the file itself.  So we need to assess whether $path represents
      // a file and dance around by diffing the parent path.

      $is_file = false;
      $info = $this->svn('info', "file://$this->repopath$path", '-r', $from);
      $lines = 0;
      while (($line = fgets($info)) !== false) {
        $lines++;
        if (preg_match("/^Node Kind:\s+file/", $line)) {
          $is_file = true;
          break;
        }
      }
      if ($lines == 0) {
        // no data returned; path doesn't exist at that revision
        if ($to === null) {
          $to = $from;
          $from--;
        }
      }
    }
    if ($is_file) {
      $diffpath = dirname($path);
    } else {
      $diffpath = $path;
    }

    if ($to !== null) {
      $diff = $this->svn('diff', '-r', $from, '-r', $to,
        "file://$this->repopath$diffpath");
    } else {
      $diff = $this->svn('diff', '-c', $from,
        "file://$this->repopath$diffpath@$from");
    }

    if ($is_file) {
      $dir = $diff;
      $diff = tmpfile();
      $wanted = basename($path);
      $in_wanted = false;
      // search in the diffstream for the file that was originally requested
      // and copy that through to the tmpfile we're using for the diff we're
      // returning to the caller
      while (($line = fgets($dir)) !== false) {
        if (preg_match("/^Index: $wanted$/", $line)) {
          $in_wanted = true;
          fwrite($diff, $line);
          continue;
        } else if (preg_match("/^Index: /", $line)) {
          if ($in_wanted) {
            break;
          }
        }
        if ($in_wanted) {
          fwrite($diff, $line);
        }
      }
      fseek($diff, 0);
    }
    return $diff;
  }

  public function getWorkingCopy()
  {
    return new MTrackWCSVN($this);
  }

  public function getRelatedChanges($revision)
  {
    return null;
  }

  function svn()
  {
    $args = func_get_args();
    return mtrack_run_tool('svn', 'read', $args);
  }
}

MTrackRepo::registerSCM('svn', 'MTrackSCMSVN');
