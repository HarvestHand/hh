<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

$USE_AJAX = false;

MTrackACL::requireAllRights('Browser', 'read');

$pi = mtrack_get_pathinfo(true);
$crumbs = MTrackSCM::makeBreadcrumbs($pi);
if (!strlen($pi)) {
  $pi = '/';
}
if (count($crumbs) > 2) {
  $repo = MTrackSCM::factory($pi);
} else {
  $repo = null;
}

if (!isset($_GET['_'])) {
  $AJAX = false;
} else {
  $AJAX = true;
}

function one_line_cl($changelog)
{
  list($one) = explode("\n", $changelog);
  return rtrim($one, " \r\n");
}

function get_browse_data($repo, $pi, $object, $ident)
{
  global $ABSWEB;

  $data = new stdclass;
  $data->dirs = array();
  $data->files = array();
  $data->jumps = array();

  if (!$repo) {
    return $data;
  }
  $branches = $repo->getBranches();
  $tags = $repo->getTags();
  if (count($branches) + count($tags)) {
    $jumps = array("" => "- Select Branch / Tag - ");
    if (is_array($branches)) {
      foreach ($branches as $name => $notcare) {
        $jumps["branch:$name"] = "Branch: $name";
      }
    }
    if (is_array($tags)) {
      foreach ($tags as $name => $notcare) {
        $jumps["tag:$name"] = "Tag: $name";
      }
    }
    $data->jumps = $jumps;
  }
  $files = array();
  $dirs = array();

  if ($repo) {
    try {
      $ents = $repo->readdir($pi, $object, $ident);
    } catch (Exception $e) {
      // Typically a freshly created repo
      $ents = array();
      $data->err = $e->getMessage();
    }
    foreach ($ents as $file) {
      $basename = basename($file->name);
      if ($file->is_dir) {
        $dirs[$basename] = $file;
      } else {
        $files[$basename] = $file;
      }
    }
  }
  uksort($files, 'strnatcmp');
  uksort($dirs, 'strnatcmp');

  $data->files = array();
  $data->dirs = array();

  $urlbase = $ABSWEB . 'browse.php';
  $pathbase = '/' . $repo->getBrowseRootName();
  $urlbase .= $pathbase;

  foreach ($dirs as $basename => $file) {
    $ent = $file->getChangeEvent();
    $url = $urlbase . '/' . $file->name;
    $d = new stdclass;
    $d->url = $url;
    $d->basename = $basename;
    $d->rev = $ent->rev;
    $d->ctime = $ent->ctime;
    $d->changeby = $ent->changeby;
    $d->changelog = one_line_cl($ent->changelog);

    $data->dirs[] = $d;
  }
  foreach ($files as $basename => $file) {
    $ent = $file->getChangeEvent();
    $url = $ABSWEB . 'file.php' . $pathbase .
            '/' . $file->name . '?rev=' . $ent->rev;
    $d = new stdclass;
    $d->url = $url;
    $d->basename = $basename;
    $d->rev = $ent->rev;
    $d->ctime = $ent->ctime;
    $d->changeby = $ent->changeby;
    $d->changelog = one_line_cl($ent->changelog);

    $data->files[] = $d;
  }

  return $data;
}

if (isset($_GET['jump']) && strlen($_GET['jump'])) {
  list($object, $ident) = explode(':', $_GET['jump'], 2);
} else {
  $object = null;
  $ident = null;
}

if ($USE_AJAX && !$AJAX) {
  mtrack_head("Browse $pi");

  // Since big dirs can take a while to gather the browse data,
  // We want to show *something* to the user while we wait for
  // the data to come in
  $g = $_GET;
  $g['_'] = '_';
  $url = $_SERVER['REQUEST_URI'] . '?' . http_build_query($g);
  echo <<<HTML
<div id='browsediv'>
  <p>Loading browse data, please wait</p>
</div>
<script>
\$(document).ready(function () {
  \$('#browsediv').load('$url');
});
</script>
HTML;
  mtrack_foot();
} else {
  if (!$USE_AJAX) {
    mtrack_head("Browse $pi");
  }

$bdata = mtrack_cache('get_browse_data',
  array($repo, $pi, $object, $ident));

if (isset($bdata->err) && strlen($pi) > 1) {
  throw new Exception($bdata->err);
}

/* Render a bread-crumb enabled location indicator */
echo "<ul class='breadcrumb browselocation'>Location: ";
$location = null;
foreach ($crumbs as $i => $path) {
  if (!strlen($path)) {
    $path = '<i class="icon-home"></i> [root]';
  } else {
    $location .= '/' . urlencode($path);
    $path = htmlentities($path, ENT_QUOTES, 'utf-8');
  }
  if ($i == count($crumbs) - 1) {
    echo "<li class='active'>";
  } else {
    echo "<li>";
  }
  echo "<a href='{$ABSWEB}browse.php$location'>$path</a> ";

  if ($i < count($crumbs) - 1) {
    echo "<span class='divider'>/</span>";
  }
  echo "</li>";
}
echo "</ul>";

if (count($bdata->jumps)) {
  echo "<form>";
  echo mtrack_select_box("jump", $bdata->jumps,
        isset($_GET['jump']) ? $_GET['jump'] : null);
  echo "<button class='btn' type='submit'>Choose</button></form>\n";
}


$me = mtrack_canon_username(MTrackAuth::whoami());
if (MTrackACL::hasAllRights('Browser', 'create')) {
  /* some users may have rights to create repos that belong to projects.
    * Determine that list of projects here, because we need it for both
    * the fork and new repo cases */
  $owners = array("user:$me" => $me);

  foreach (MTrackDB::q(
      'select projid, shortname, name from projects order by ordinal')
      as $row)
  {
    if (MTrackACL::hasAllRights("project:$row[0]", 'modify')) {
      $owners['project:' . $row[1]] = $row[1];
    }
  }
  if (count($owners) > 1) {
    $owners = mtrack_select_box('parent', $owners, null, true);
  } else {
    $owners = "<input type='hidden' name='parent' value='" .
      htmlentities($me, ENT_QUOTES, 'utf-8') .  "'>";
  }
}

if ($repo) {
  MTrackACL::requireAllRights("repo:$repo->repoid", 'read');

  $description = MTrackWiki::format_to_html($repo->description);
  $url = $repo->getCheckoutCommand();

  echo "<div class='repodesc'>$description</div>";
  if (strlen($url)) {
    echo "<div class='checkout'>\n";
    echo "Use the following command to obtain a working copy:<br>";
    echo "<pre>\$ $url</pre>";
    echo "</div>\n";
  }

  echo "<div id='repobuttons'>\n";

  if ($repo->canFork() && MTrackACL::hasAllRights('Browser', 'fork')
      && MTrackConfig::get('repos', 'allow_user_repo_creation')) {
    $forkname = "$me/$repo->shortname";
    if ($forkname == $repo->getBrowseRootName()) {
      /* if this is mine already, make a "more unique" name for my new fork */
      $forkname = $repo->shortname . '2';
    } else {
      $forkname = $repo->shortname;
    }
    $forkname = htmlentities($forkname, ENT_QUOTES, 'utf-8');
    echo <<<HTML
<div id='forkdialog' class='modal hide fade'>
  <div class='modal-header'>
    <a class='close' data-dismiss='modal'>&times;</a>
    <h3>Fork a repo</h3>
  </div>
  <div class='modal-body'>
  <p>
    A fork is your own copy of a repo that is stored and maintained
    on the server.
  </p>
  <p>
    If all you want to do is obtain a working copy so that you can
    collaborate on this repo, you should not create a fork.
  </p>
  <p>
    You may want to fork if you want the server to keep your work backed up,
    or to collaborate with others on work that you want to share
    with this repo later on.
  </p>
  <p>
    Choose a name for your fork:<br>
    $owners <input type='text' name='name' value='$forkname'>
    <br>
    <br>
    <br>
    <br>
  </p>
  </div>
  <div class='modal-footer'>
    <button class='btn' data-dismiss='modal'>Cancel</button>
    <button id='createforkbtn'
      class='btn btn-primary'>Fork</button>
  </div>
</div>
<button id='forkbtn' class='btn' type='button'
  data-toggle='modal' data-target='#forkdialog'><i class="icon-random"></i> Fork</button>
<script>
$(document).ready(function () {
  function capture_error(model, resp) {
    var err;
    if (!_.isObject(resp)) {
      err = resp;
    } else {
      err = resp.statusText;
      try {
        var r = JSON.parse(resp.responseText);
        err = r.message;
      } catch (e) {
      }
    }
    $('<div class="alert alert-danger">' +
      "<a class='close' data-dismiss='alert'>&times;</a>" +
      err + '</div>').
      appendTo('#forkdialog div.modal-body');
  }

  $('#createforkbtn').click(function () {
    var dlg = $('#forkdialog');
    var owner = $('select[name=parent]', dlg).val();
    var name = $('input[name=name]', dlg).val();
    var R = new MTrackRepo;
    R.save({
      parent: owner,
      shortname: name,
      clonedfrom: $repo->repoid,
    }, {
      success: function (model, resp) {
        window.location = ABSWEB + "browse.php/" + model.get('browsepath');
      },
      error: capture_error
    });
  });
});
</script>

HTML
    ;
  }
  $mine = "user:$me";
  if (MTrackACL::hasAllRights("repo:$repo->repoid", "modify")) {
    $repojson = json_encode(MTrackAPI::invoke(
        'GET', "/repo/properties/$repo->repoid")->result);
    echo <<<HTML
<button id='editrepobtn' class='btn'><i class="icon-edit"></i> Edit</button>
<script>
$(document).ready(function () {
  $('#editrepobtn').click(function () {
    var R = new MTrackRepo($repojson);
    var V = new MTrackRepoEditView({
      model: R
    });
    V.show(function (model) {
      if (model) {
        window.location = ABSWEB + 'browse.php/' + model.get('browsepath');
      } else {
        // deleted
        window.location = ABSWEB + 'browse.php';
      }
    });
  });
});
</script>
HTML

    ;
  }
  MTrackWatch::renderWatchUI('repo', $repo->repoid);

  echo "</div>\n"; # end of repobuttons

  echo "<br>\n<a href='{$ABSWEB}log.php/{$repo->getBrowseRootName()}/$pi'>Show History</a><br>\n";
}

if (!$repo && MTrackACL::hasAllRights('Browser', 'fork')
    && MTrackConfig::get('repos', 'allow_user_repo_creation')) {
echo <<<HTML
<button id='newrepobtn' class='btn btn-primary'
  type='button'><i class='icon-plus icon-white'></i> New Repository</button>
<br>
<script>
$(document).ready(function () {
  $('#newrepobtn').click(function () {
    var R = new MTrackRepo;
    var V = new MTrackRepoEditView({
      model: R
    });
    V.show(function () {
      window.location = ABSWEB + 'browse.php/' + R.get('browsepath');
    });
  });
});
</script>
HTML
;
}

echo "<br>\n";

?>
<table class='listing' id='dirlist'>
  <thead>
    <tr>
<?php
if (!$repo) {
?>
      <th class='name' width='1%'>Name</th>
      <th class='desc'>Description</th>
<?php
} else {
?>
      <th class='name' width='1%'>Name</th>
      <th class='rev' width='1%'>Revision</th>
      <th class='age' width='1%'>Age</th>
      <th class='change'>Last Change</th>
<?php
}
?>
    </tr>
  </thead>
  <tbody>
<?php
$even = 1;

if (count($crumbs) > 1) {
  $class = $even++ % 2 ? 'even' : 'odd';
  $url = $ABSWEB . 'browse.php' . dirname(mtrack_get_pathinfo(true));
  if (isset($_GET['jump'])) {
    $url .= '?jump=' . urlencode($_GET['jump']);
  }
  $url = htmlentities($url, ENT_QUOTES, 'utf-8');

  echo "<tr class='$class'>\n";
  echo "<td class='name'><a class='parent' href='$url'>.. [up]</a></td>";
  if ($repo) {
    echo "<td class='rev'></td>\n";
    echo "<td class='age'></td>\n";
    echo "<td class='change'></td>\n";
  } else {
    echo "<td class='desc'></td>\n";
  }
  echo "</tr>\n";
}

foreach ($bdata->dirs as $d) {
  $class = $even++ % 2 ? 'even' : 'odd';
  $url = $d->url;
  if (isset($_GET['jump'])) {
    $url .= '?jump=' . urlencode($_GET['jump']);
  }
  $url = htmlentities($url, ENT_QUOTES, 'utf-8');
  echo "<tr class='$class'>\n";
  echo "<td class='name'><a class='dir' href='$url'>$d->basename</a></td>";
  echo "<td class='rev'>" . mtrack_changeset($d->rev, $repo) . "</td>\n";
  echo "<td class='age'>" . mtrack_date($d->ctime) . "</td>\n";
  echo "<td class='change'>" .
    mtrack_username($d->changeby, array('size' => 16)) . ": " .
    MTrackWiki::format_to_oneliner($d->changelog) . "</td>\n";
  echo "</tr>\n";
}

$README = array();

foreach ($bdata->files as $d) {
  $class = $even++ % 2 ? 'even' : 'odd';
  $url = $d->url;
  if (isset($_GET['jump'])) {
    $url .= '&jump=' . urlencode($_GET['jump']);
  }
  $url = htmlentities($url, ENT_QUOTES, 'utf-8');
  echo "<tr class='$class'>\n";
  echo "<td class='name'><a class='file' href='$url'>$d->basename</a></td>";
  echo "<td class='rev'>" . mtrack_changeset($d->rev, $repo) . "</td>\n";
  echo "<td class='age'>" . mtrack_date($d->ctime) . "</td>\n";
  echo "<td class='change'>" .
    mtrack_username($d->changeby, array('size' => 16)) . ": " .
    MTrackWiki::format_to_oneliner($d->changelog) . "</td>\n";
  echo "</tr>\n";

  if (preg_match("/^README\.(md|mkd|markdown)$/i", $d->basename)) {
    $README['md'] = mtrack_get_pathinfo(true) . '/' . $d->basename;
  } else if (preg_match("/^README/i", $d->basename)) {
    $README['txt'] = mtrack_get_pathinfo(true) . '/' . $d->basename;
  }

}

if (!$repo) {
  $mine = 'user:' . mtrack_canon_username(MTrackAuth::whoami());
  $params = array();
  if (count($crumbs) == 2 && $crumbs[1] != 'default') {
    /* looking for a particular subset */
    $where = "parent like('%:' || ?)";
    $params[] = $crumbs[1];
  } else if (count($crumbs) == 2 && $crumbs[1] == 'default') {
    /* looking at system items */
    $where = "parent = ''";
  } else {
    /* looking for top level items */
    $where = "1 = 1";
  }
  /* have my own repos bubble up */
  $params[] = $mine;
  $sql = <<<SQL
select repoid, parent, shortname, description
from repos
where $where
order by
  case when parent = ? then 0 else 1 end,
  parent,
  shortname
SQL
  ;
  $q = MTrackDB::get()->prepare($sql);
  $q->execute($params);

  foreach ($q->fetchAll(PDO::FETCH_OBJ) as $rep) {
    if (!MTrackACL::hasAnyRights("repo:$rep->repoid", 'read')) {
      continue;
    }

    $class = $even++ % 2 ? 'even' : 'odd';
    $url = $ABSWEB . 'browse.php/';
    $label = MTrackRepo::makeDisplayName($rep);

    $url .= $label;
    echo "<tr class='$class'>\n";
    echo "<td class='name'><a class='dir' href='$url'>$label</a></td>\n";
    $desc = MTrackWiki::format_to_html($rep->description);
    echo "<td class='desc'>$desc</td>\n";
    echo "</tr>\n";
  }
}

echo "</tbody></table>\n";

if ($repo && count($README)) {
  function get_repo_content($name) {
    global $repo;
    $bits = explode('/', $name);
    array_shift($bits); /* empty due to leading slash */
    array_shift($bits); /* owner */
    array_shift($bits); /* repo name */
    $name = join($bits, '/');
    $file = $repo->file($name);
    return stream_get_contents($file->cat());
  }
  /* prefer to display a markdown formatted readme, otherwise just
   * show it as plain text */
  if (isset($README['md'])) {
    $readme = mtrack_markdown(get_repo_content($README['md']));
  } else {
    $readme = "<pre>" .
      htmlentities(get_repo_content($README['txt']), ENT_QUOTES, 'utf-8') .
      "</pre>";
  }

  echo "<br>$readme<br>";
}

  if (!$USE_AJAX) {
    mtrack_foot();
  }

}
