<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* Git SCM browsing */

class MTrackSCMFileGit extends MTrackSCMFile {
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

  public function getChangeEvent()
  {
    list($ent) = $this->repo->history($this->name, 1, 'rev', $this->rev);
    return $ent;
  }

  function cat()
  {
    // There may be a better way...
    // ls-tree to determine the hash of the file from this change:
    $fp = $this->repo->git('ls-tree', $this->rev, $this->name);
    $line = fgets($fp);
    $fp = null;
    list($mode, $type, $hash, $name) = preg_split("/\s+/", $line);

    // now we can cat that blob
    return $this->repo->git('cat-file', 'blob', $hash);
  }

  function annotate($include_line_content = false)
  {
    if ($this->repo->gitdir == $this->repo->repopath) {
      // For bare repos, we can't run annotate, so we need to make a clone
      // with a work tree.  This relies on local clones being a cheap operation
      $wc = new MTrackWCGit($this->repo);
      $wc->push = false;
      $fp = $wc->git('annotate', '-p', $this->name, $this->rev);
    } else {
      $fp = $this->repo->git('annotate', '-p', $this->name, $this->rev);
    }
    $i = 1;
    $ann = array();
    $meta = array();
    while ($line = fgets($fp)) {
//      echo htmlentities($line), "<br>\n";
      if (!strncmp($line, "\t", 1)) {
        $A = new MTrackSCMAnnotation;
        if (isset($meta['author-mail']) &&
            strpos($meta['author-mail'], '@')) {
          $A->changeby = $meta['author'] . ' ' . $meta['author-mail'];
        } else {
          $A->changeby = $meta['author'];
        }
        $A->rev = $meta['rev'];
        if ($include_line_content) {
          $A->line = substr($line, 1);
        }
        $ann[$i++] = $A;
        continue;
      }
      if (preg_match("/^([a-f0-9]+)\s[a-f0-9]+\s[a-f0-9]+\s[a-f0-9]+$/",
          $line, $M)) {
        $meta['rev'] = $M[1];
      } else if (preg_match("/^(\S+)\s*(.*)$/", $line, $M)) {
        $name = $M[1];
        $value = $M[2];
        $meta[$name] = $value;
      }
    }
    return $ann;
  }
}

class MTrackWCGit extends MTrackSCMWorkingCopy {
  private $repo;
  public $push = true;

  function __construct(MTrackRepo $repo) {
    $this->dir = mtrack_make_temp_dir();
    $this->repo = $repo;

    mtrack_run_tool('git', 'string',
        array('clone', $this->repo->repopath, $this->dir)
    );
  }

  function __destruct() {
    if ($this->push) {
      echo stream_get_contents($this->git('push', 'origin', 'master'));
    }
    mtrack_rmdir($this->dir);
  }

  function getFile($path)
  {
    return $this->repo->file($path);
  }

  function addFile($path)
  {
    $this->git('add', $path);
  }

  function delFile($path)
  {
    $this->git('rm', '-f', $path);
  }

  function commit(MTrackChangeset $CS)
  {
    if ($CS->when) {
      $d = strtotime($CS->when);
      putenv("GIT_AUTHOR_DATE=$d -0000");
    } else {
      putenv("GIT_AUTHOR_DATE=");
    }
    $reason = trim($CS->reason);
    if (!strlen($reason)) {
      $reason = 'Changed';
    }
    MTrackSCMGit::setGitEnvironment($CS->who);
    stream_get_contents($this->git('commit', '-a',
      '-m', $reason
      )
    );
  }

  function git()
  {
    $args = func_get_args();
    $a = array("--git-dir=$this->dir/.git", "--work-tree=$this->dir");
    foreach ($args as $arg) {
      $a[] = $arg;
    }

    return mtrack_run_tool('git', 'read', $a);
  }
}

class MTrackSCMGit extends MTrackRepo {
  protected $branches = null;
  protected $tags = null;
  public $gitdir = null;

  public function getSCMMetaData() {
    return array(
      'name' => 'Git',
      'tools' => array('git'),
    );
  }

  function __construct($id = null) {
    parent::__construct($id);
    if ($id !== null) {
      /* transparently handle bare vs. non bare repos */
      $this->gitdir = $this->repopath;
      if (is_dir("$this->repopath/.git")) {
        $this->gitdir .= "/.git";
      }
    }
  }

  function getServerURL() {
    $url = parent::getServerURL();
    if ($url) return $url;
    $url = MTrackConfig::get('repos', 'serverurl');
    if ($url) {
      $pp = MTrackConfig::get('repos', 'serverpathprefix');
      if ($pp) {
        return "$url:~$pp/" . $this->getBrowseRootName();
      }
      return "$url:" . $this->getBrowseRootName();
    }
    return null;
  }

  /* I've had reports that Git becomes unhappy if it can't find something
   * that looks like an email address, so try to normalize towards that */
  static function setGitEnvironment($user) {
    $userdata = MTrackAuth::getUserData($user);
    if (preg_match("/@/", $userdata['email'])) {
      $who = $userdata['email'];
    } else {
      $who = "$user@local";
    }
    putenv("GIT_AUTHOR_NAME=$who");
    putenv("GIT_AUTHOR_EMAIL=$who");
  }

  public function reconcileRepoSettings(MTrackSCM $r = null) {
    if ($r == null) {
      $r = $this;
    }

    if (!is_dir($r->repopath)) {
      self::setGitEnvironment(MTrackAuth::whoami());

      if ($r->clonedfrom) {
        $S = MTrackRepo::loadById($r->clonedfrom);

        $stm = mtrack_run_tool('git', 'read',
            array('clone', '--bare', $S->repopath, $r->repopath));
        $out = stream_get_contents($stm);
        if (pclose($stm)) {
          throw new Exception("git init failed: $out");
        }

      } else {
        $stm = mtrack_run_tool('git', 'read',
            array('init', '--bare', $r->repopath));
        $out = stream_get_contents($stm);
        if (pclose($stm)) {
          throw new Exception("git init failed: $out");
        }
      }

      $php = MTrackConfig::get('tools', 'php');
      $hook = realpath(dirname(__FILE__) . '/../../bin/git-commit-hook');
      $conffile = realpath(MTrackConfig::getLocation());
      foreach (array('pre', 'post') as $step) {
        $script = <<<HOOK
#!/bin/sh
exec $php $hook $step $conffile

HOOK;
        $target = "$r->repopath/hooks/$step-receive";
        if (file_put_contents("$target.mtrack", $script)) {
          chmod("$target.mtrack", 0755);
          rename("$target.mtrack", $target);
        }
      }
    }

    system("chmod -R 02777 $r->repopath");
  }

  function canFork() {
    return true;
  }


  public function getBranches()
  {
    if ($this->branches !== null) {
      return $this->branches;
    }
    $this->branches = array();
    $fp = $this->git('branch', '--no-color', '--verbose');
    while ($line = fgets($fp)) {
      // * master 61e7e7d oneliner
      $line = substr($line, 2);
      list($branch, $rev) = preg_split('/\s+/', $line);
      $this->branches[$branch] = $rev;
    }
    $fp = null;
    return $this->branches;
  }

  public function getTags()
  {
    if ($this->tags !== null) {
      return $this->tags;
    }
    $this->tags = array();
    $fp = $this->git('tag');
    while ($line = fgets($fp)) {
      $line = trim($line);
      $this->tags[$line] = $line;
    }
    $fp = null;
    return $this->tags;
  }

  public function readdir($path, $object = null, $ident = null)
  {
    $res = array();

    if ($object === null) {
      $object = 'branch';
      $ident = 'master';
    }
    $rev = $this->resolveRevision(null, $object, $ident);

    if (strlen($path)) {
      $path = rtrim($path, '/') . '/';
    }

    $fp = $this->git('ls-tree', $rev, $path);

    $dirs = array();

    while ($line = fgets($fp)) {
      list($mode, $type, $hash, $name) = preg_split("/\s+/", $line);

      $res[] = new MTrackSCMFileGit($this, "$name", $rev, $type == 'tree');
    }
    return $res;
  }

  public function file($path, $object = null, $ident = null)
  {
    if ($object == null) {
      $branches = $this->getBranches();
      if (isset($branches['master'])) {
        $object = 'branch';
        $ident = 'master';
      } else {
        // fresh/empty repo
        return null;
      }
    }
    $rev = $this->resolveRevision(null, $object, $ident);
    return new MTrackSCMFileGit($this, $path, $rev);
  }

  public function history($path, $limit = null, $object = null, $ident = null)
  {
    $res = array();

    $args = array();
    if ($object == 'rev' && $limit > 1) {
      $args[] = $ident;
    } else if ($object !== null) {
      $rev = $this->resolveRevision(null, $object, $ident);
      $args[] = "$rev";
    } else {
      $args[] = "master";
    }
    if ($limit !== null) {
      if (is_int($limit) || preg_match("/^\d+$/", $limit)) {
        $args[] = "--max-count=$limit";
      } else {
        $args[] = "--since=$limit";
      }
    }
    $args[] = "--no-color";
    $args[] = "--name-status";
    $args[] = "--date=rfc";

    $path = ltrim($path, '/');

    $fp = $this->git('log', $args, '--', $path);

    $commits = array();
    $commit = null;
    while (true) {
      $line = fgets($fp);
      if ($line === false) {
        if ($commit !== null) {
          $commits[] = $commit;
        }
        break;
      }
      if (preg_match("/^commit/", $line)) {
        if ($commit !== null) {
          $commits[] = $commit;
        }
        $commit = $line;
        continue;
      }
      $commit .= $line;
    }

    foreach ($commits as $commit) {
      $ent = new MTrackSCMEvent;
      $lines = explode("\n", $commit);
      $line = array_shift($lines);

      if (!preg_match("/^commit\s+(\S+)$/", $line, $M)) {
        break;
      }
      $ent->rev = $M[1];

      $ent->branches = array(); // FIXME
      $ent->tags = array(); // FIXME
      $ent->files = array();

      while (count($lines)) {
        $line = array_shift($lines);
        if (!strlen($line)) {
          break;
        }
        if (preg_match("/^(\S+):\s+(.*)\s*$/", $line, $M)) {
          $k = $M[1];
          $v = $M[2];

          switch ($k) {
            case 'Author':
              $ent->changeby = $v;
              break;
            case 'Date':
              $ts = strtotime($v);
              $ent->ctime = MTrackDB::unixtime($ts);
              break;
          }
        }
      }

      $ent->changelog = "";

      if ($lines[0] == '') {
        array_shift($lines);
      }

      while (count($lines)) {
        $line = array_shift($lines);
        if (strncmp($line, '    ', 4)) {
          array_unshift($lines, $line);
          break;
        }
        $line = substr($line, 4);
        $ent->changelog .= $line . "\n";
      }

      if ($lines[0] == '') {
        array_shift($lines);
      }
      foreach ($lines as $line) {
        if (preg_match("/^(.+)\s+(\S+)\s*$/", $line, $M)) {
          $f = new MTrackSCMFileEvent;
          $f->name = $M[2];
          $f->status = $M[1];
          $ent->files[] = $f;
        }
      }

      if (!count($ent->branches)) {
        $ent->branches[] = 'master';
      }

      $res[] = $ent;
    }
    $fp = null;
    return $res;
  }

  public function diff($path, $from = null, $to = null)
  {
    if ($path instanceof MTrackSCMFile) {
      if ($from === null) {
        $from = $path->rev;
      }
      $path = $path->name;
    }
    if ($to !== null) {
      return $this->git('diff', "$from..$to", '--', $path);
    }
    return $this->git('diff', "$from^..$from", '--', $path);
  }

  public function getWorkingCopy()
  {
    return new MTrackWCGit($this);
  }

  public function getRelatedChanges($revision)
  {
    $parents = array();
    $kids = array();

    $fp = $this->git('rev-parse', "$revision^");
    while (($line = fgets($fp)) !== false) {
      $parents[] = trim($line);
    }

    // Ugh!: http://stackoverflow.com/questions/1761825/referencing-the-child-of-a-commit-in-git
    $fp = $this->git('rev-list', '--all', '--parents');
    while (($line = fgets($fp)) !== false) {
      $hashes = preg_split("/\s+/", $line);
      $kid = array_shift($hashes);
      if (in_array($revision, $hashes)) {
        $kids[] = $kid;
      }
    }

    return array($parents, $kids);
  }

  function git()
  {
    $args = func_get_args();
    $a = array(
      "--git-dir=$this->gitdir"
    );

    if ($this->gitdir != $this->repopath) {
      $a[] = "--work-tree=$this->repopath";
    }
    foreach ($args as $arg) {
      $a[] = $arg;
    }

    return mtrack_run_tool('git', 'read', $a);
  }
}

MTrackRepo::registerSCM('git', 'MTrackSCMGit');

