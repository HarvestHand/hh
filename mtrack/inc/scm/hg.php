<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

/* Mercurial SCM browsing */

class MTrackSCMFileHg extends MTrackSCMFile {
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

  public function _determineFileChangeEvent($repoid, $filename, $rev)
  {
    $repo = MTrackRepo::loadById($repoid);
    if ($filename == '') {
      $ents = $repo->_parse_log(array('-r', $rev));
    } else {
      $ents = $repo->_parse_log(array(
          '-r', "last(::$rev and filelog('$filename'), 1)"
        ));
    }
    if (!count($ents)) {
      throw new Exception("$filename is invalid");
    }
    return $ents[0];
  }

  public function getChangeEvent()
  {
    /* for performance reasons (eg: it can take seconds to execute
     * this for each file), we fake the change event here to match
     * that of the last known revision at the root if we are running
     * the browser view (there can be many files).
     * Otherwise we do the logical thing for other areas; this should
     * give a reasonable balance between accuracy and performance in
     * the right places */
    if (basename($_SERVER['SCRIPT_NAME']) == 'browse.php') {
      return mtrack_cache(
        array('MTrackSCMFileHg', '_determineFileChangeEvent'),
        array($this->repo->repoid, '', $this->rev),
        300);
    }
    return mtrack_cache(
      array('MTrackSCMFileHg', '_determineFileChangeEvent'),
      array($this->repo->repoid, $this->name, $this->rev),
      864000);
  }

  function cat()
  {
    return $this->repo->hg('cat', '-r', $this->rev, $this->name);
  }

  function annotate($include_line_content = false)
  {
    $i = 1;
    $ann = array();
    $fp = $this->repo->hg('annotate', '-r', $this->rev, '-uvc', $this->name);
    while ($line = fgets($fp)) {
      preg_match("/^\s*([^:]*)\s+([0-9a-fA-F]+): (.*)$/", $line, $M);
      $A = new MTrackSCMAnnotation;
      $A->changeby = $M[1];
      $A->rev = $M[2];
      if ($include_line_content) {
        $A->line = $M[3];
      }
      $ann[$i++] = $A;
    }
    return $ann;
  }
}

class MTrackWCHg extends MTrackSCMWorkingCopy {
  private $repo;

  function __construct(MTrackRepo $repo) {
    /* use a temp dir on the same filesystem as the repos dir to improve
     * clone performance */
    $tempdir = dirname(MTrackRepo::get_repos_dir()) . '/temp';
    if (!is_dir($tempdir)) {
      mkdir($tempdir);
    }
    $this->dir = mtrack_make_temp_dir(true, $tempdir);
    $this->repo = $repo;

    stream_get_contents($this->hg('init', $this->dir));
    stream_get_contents($this->hg('pull', $this->repo->repopath));
    stream_get_contents($this->hg('up'));
  }

  function __destruct() {

    $a = array("-y", "--cwd", $this->dir, 'push', $this->repo->repopath);

    list($proc, $pipes) = mtrack_run_tool('hg', 'proc', $a);

    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    $st = proc_close($proc);

    if ($st) {
      throw new Exception("push failed with status $st: $err $out");
    }
    mtrack_rmdir($this->dir);
  }

  function getFile($path)
  {
    return $this->repo->file($path);
  }

  function addFile($path)
  {
    // nothing to do; we use --addremove
  }

  function delFile($path)
  {
    // we use --addremove when we commit for this to take effect
    unlink($this->dir . DIRECTORY_SEPARATOR . $path);
  }

  function commit(MTrackChangeset $CS)
  {
    $hg_date = (int)strtotime($CS->when) . ' 0';
    $reason = trim($CS->reason);
    if (!strlen($reason)) {
      $reason = 'Changed';
    }
    $out = $this->hg('ci', '--addremove',
      '-m', $reason,
      '-d', $hg_date,
      '-u', $CS->who);
    $data = stream_get_contents($out);
    $st = pclose($out);
    if ($st != 0) {
      throw new Exception("commit failed $st $data");
    }
  }

  function hg()
  {
    $args = func_get_args();
    $a = array("-y", "--cwd", $this->dir);
    foreach ($args as $arg) {
      $a[] = $arg;
    }

    return mtrack_run_tool('hg', 'read', $a);
  }
}

class MTrackSCMHg extends MTrackRepo {
  protected $hg = 'hg';
  protected $branches = null;
  protected $tags = null;

  public function getSCMMetaData() {
    return array(
      'name' => 'Mercurial',
      'tools' => array('hg'),
    );
  }

  public function reconcileRepoSettings(MTrackSCM $r = null) {
    if ($r == null) {
      $r = $this;
    }
    $description = substr(preg_replace("/\r?\n/m", ' ', $r->description), 0, 64);
    $description = trim($description);
    if (!is_dir($r->repopath)) {
      if ($r->clonedfrom) {
        $S = MTrackRepo::loadById($r->clonedfrom);
        $stm = mtrack_run_tool('hg', 'read', array(
          'clone', $S->repopath, $r->repopath));
      } else {
        $stm = mtrack_run_tool('hg', 'read', array('init', $r->repopath));
      }
      $out = stream_get_contents($stm);
      $st = pclose($stm);
      if ($st) {
        throw new Exception("hg: failed $out");
      }
    }

    $php = MTrackConfig::get('tools', 'php');
    $conffile = realpath(MTrackConfig::getLocation());

    $install = realpath(dirname(__FILE__) . '/../../');

    /* fixup config */
    $apply = array(
      "hooks" => array(
        "changegroup.mtrack" =>
          "$php $install/bin/hg-commit-hook changegroup $conffile",
        "commit.mtrack" =>
          "$php $install/bin/hg-commit-hook commit $conffile",
        "pretxncommit.mtrack" =>
          "$php $install/bin/hg-commit-hook pretxncommit $conffile",
        "pretxnchangegroup.mtrack" =>
          "$php $install/bin/hg-commit-hook pretxnchangegroup $conffile",
      ),
      "web" => array(
        "description" => $description,
      )
    );

    $cfg = @file_get_contents("$r->repopath/.hg/hgrc");
    $adds = array();

    foreach ($apply as $sect => $opts) {
      foreach ($opts as $name => $value) {
        if (preg_match("/^$name\s*=/m", $cfg)) {
          $cfg = preg_replace("/^$name\s*=.*$/m", "$name = $value", $cfg);
        } else {
          $adds[$sect][$name] = $value;
        }
      }
    }

    foreach ($adds as $sect => $opts) {
      $cfg .= "[$sect]\n";
      foreach ($opts as $name => $value) {
        $cfg .= "$name = $value\n";
      }
    }
    file_put_contents("$r->repopath/.hg/hgrc", $cfg, LOCK_EX);
    system("chmod -R 02777 $r->repopath");
  }

  function canFork() {
    return true;
  }

  function getServerURL() {
    $url = parent::getServerURL();
    if ($url) return $url;
    $url = MTrackConfig::get('repos', 'serverurl');
    if ($url) {
      $pp = MTrackConfig::get('repos', 'serverpathprefix');
      if ($pp) {
        return "ssh://$url/~$pp/" . $this->getBrowseRootName();
      }
      return "ssh://$url/" . $this->getBrowseRootName();
    }
    return null;
  }

  public function getBranches()
  {
    if ($this->branches !== null) {
      return $this->branches;
    }
    $this->branches = array();
    $fp = $this->hg('branches');
    while ($line = fgets($fp)) {
      list($branch, $revstr) = preg_split('/\s+/', $line);
      list($num, $rev) = explode(':', $revstr, 2);
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
    $fp = $this->hg('tags');
    while ($line = fgets($fp)) {
      list($tag, $revstr) = preg_split('/\s+/', $line);
      list($num, $rev) = explode(':', $revstr, 2);
      $this->tags[$tag] = $rev;
    }
    $fp = null;
    return $this->tags;
  }

  public function readdir($path, $object = null, $ident = null)
  {
    $res = array();

    if ($object === null) {
      $object = 'branch';
      $ident = 'default';
    }
    $rev = $this->resolveRevision(null, $object, $ident);

    $fp = $this->hg('manifest', '-r', $rev);

    if (strlen($path)) {
      $path .= '/';
    }
    $plen = strlen($path);

    $dirs = array();
    $exists = false;

    while ($line = fgets($fp)) {
      $name = trim($line);

      if (!strncmp($name, $path, $plen)) {
        $exists = true;
        $ent = substr($name, $plen);
        if (strpos($ent, '/') === false) {
          $res[] = new MTrackSCMFileHg($this, "$path$ent", $rev);
        } else {
          list($d) = explode('/', $ent, 2);
          if (!isset($dirs[$d])) {
            $dirs[$d] = $d;
            $res[] = new MTrackSCMFileHg($this, "$path$d", $rev, true);
          }
        }
      }
    }

    if (!$exists) {
      throw new Exception("location $path does not exist");
    }
    return $res;
  }

  public function file($path, $object = null, $ident = null)
  {
    if ($object == null) {
      $branches = $this->getBranches();
      if (isset($branches['default'])) {
        $object = 'branch';
        $ident = 'default';
      } else {
        // fresh/empty repo
        $object = 'tag';
        $ident = 'tip';
      }
    }
    $rev = $this->resolveRevision(null, $object, $ident);
    return new MTrackSCMFileHg($this, $path, $rev);
  }

  public function _parse_log($args)
  {
    $res = array();

    $sep = uniqid();
    $fp = $this->hg('log', '--style', 'xml', '-v', $args);
    $xml = stream_get_contents($fp);
    $log = simplexml_load_string($xml);
    /*
<?xml version="1.0"?>
<log>
<logentry revision="9" node="22e795f008e5743d60a1eca2fdf6cf9542d7d837">
<tag>tip</tag>
<author email="wez@wezfurlong.org">Wez Furlong</author>
<date>2011-11-04T00:08:44-04:00</date>
<msg xml:space="preserve">fix syntax, refs #1 (spent 1)</msg>
<paths>
<path action="M">hello.php</path>
</paths>
</logentry>
<logentry revision="8" node="fcb0eaa717a96259678385b8f8d6a461d893a62f">
<author email="wez@wezfurlong.org">Wez Furlong</author>
<date>2011-11-04T00:08:21-04:00</date>
<msg xml:space="preserve">and some more, refs #1 (spent 2)</msg>
<paths>
<path action="M">hello.php</path>
</paths>
</logentry>
<logentry revision="7" node="b57cf18f1373d3156609863730b7f3f7354bef8a">
<author email="wez@wezfurlong.org">Wez Furlong</author>
<date>2011-11-04T00:06:29-04:00</date>
<msg xml:space="preserve">more changes, refs #1 (spent 1)</msg>
<paths>
<path action="M">hello.php</path>
</paths>
</logentry>
<logentry revision="6" node="dd713e9701cbbbecbb2b7bca57e78ee5f82ef874">
<author email="wez@wezfurlong.org">Wez Furlong</author>
<date>2011-11-04T00:05:54-04:00</date>
<msg xml:space="preserve">add a php file (busted)
refs #1 (spent 1)</msg>
<paths>
<path action="A">hello.php</path>
</paths>
</logentry>
</log>
     */
  if (is_object($log) && isset($log->logentry))
  foreach ($log->logentry as $L) {
      $ent = new MTrackSCMEvent;
      $ent->rev = (string)$L['node'];

      $ent->branches = array();
      foreach ($L->branch as $br) {
        $ent->branches[] = (string)$br;
      }
      if (!count($ent->branches)) {
        $ent->branches[] = 'default';
      }

      $ent->tags = array();
      foreach ($L->tag as $tag) {
        $ent->tags[] = (string)$tag;
      }

      $ent->files = array();
      foreach ($L->paths->path as $p) {
        $f = new MTrackSCMFileEvent;
        $f->name = (string)$p;
        $f->status = (string)$p['action'];
        $ent->files[] = $f;
      }

      $ent->changeby = (string)$L->author['email'];
      $ent->ctime = (string)$L->date;
      $ent->changelog = (string)$L->msg;

      $res[] = $ent;
    }
    $fp = null;
    return $res;
  }

  public function history($path, $limit = null, $object = null, $ident = null)
  {
    $args = array();
    if ($limit > 1 && $object == 'branch') {
      $args[] = '-b';
      $args[] = $ident;
    } else if ($limit > 1 && $object == 'rev') {
      $args[] = '-r';
      $args[] = "limit(reverse(::$ident), $limit)";
      $limit = null;
    } else if ($object !== null) {
      $rev = $this->resolveRevision(null, $object, $ident);
      $args[] = '-r';
      $args[] = $rev;
    }
    if ($limit !== null) {
      if (is_int($limit)) {
        $args[] = '-l';
        $args[] = $limit;
      } else {
        $t = strtotime($limit);
        $args[] = '-d';
        $args[] = ">$t 0";
      }
    }
    $args[] = $path;

    return $this->_parse_log($args);
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
      return $this->hg('diff', '-r', $from, '-r', $to,
        '--git', $path);
    }
    return $this->hg('diff', '-c', $from, '--git', $path);
  }

  public function getWorkingCopy()
  {
    return new MTrackWCHg($this);
  }

  public function getRelatedChanges($revision)
  {
    $parents = array();
    $kids = array();

    foreach (preg_split('/\s+/',
          stream_get_contents($this->hg('parents', '-r', $revision,
              '--template', '{node|short}\n'))) as $p) {
      if (strlen($p)) {
        $parents[] = $p;
      }
    }

    foreach (preg_split('/\s+/',
        stream_get_contents($this->hg('--config',
          'extensions.children=',
          'children', '-r', $revision,
          '--template', '{node|short}\n'))) as $p) {
      if (strlen($p)) {
        $kids[] = $p;
      }
    }
    return array($parents, $kids);
  }

  function hg()
  {
    $args = func_get_args();
    $a = array("-y", "-R", $this->repopath, "--cwd", $this->repopath);
    foreach ($args as $arg) {
      $a[] = $arg;
    }

    return mtrack_run_tool('hg', 'read', $a);
  }
}

MTrackRepo::registerSCM('hg', 'MTrackSCMHg');

