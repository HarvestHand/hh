<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

$rid = mtrack_get_pathinfo();

mtrack_head("Administration - Repositories");
mtrack_admin_nav();
if (!strlen($rid)) {
  MTrackACL::requireAnyRights('Browser', 'modify');
?>
<h1>Repositories</h1>

<p>
Repositories are version controlled folders that remember your files and
folders at various points in time.  Mtrack has support for multiple different
Software Configuration Management systems (also known as Version Control
Systems; SCM and VCS are the common acronyms).
</p>
<p>
Listed below are the repositories that mtrack is configured to use.
The <em>wiki</em> repository is treated specially by mtrack; it stores the
wiki pages.  Click on the repository name to edit it, or click on the "Add"
button to tell mtrack to use another repository.
</p>
<ul id='repolist' class='nav'></ul>
<?php
  $repos = json_encode(MTrackAPI::invoke('GET', '/repo/properties')->result);
  echo <<<HTML
  <script>
$(document).ready(function () {
  var repos = new MTrackRepoList($repos);

  function addOne(R) {
    var ul = $('#repolist');
    var li = $('<li/>');
    var name = $('<a/>');
    name.text(R.get('browsepath'));
    name.attr('href', '#');//ABSWEB + 'admin/repo.php/' + R.id);
    li.append(name);
    li.data('model', R);
    ul.append(li);
  }

  repos.bind('add', function (R) {
    addOne(R);
  });
  repos.bind('remove', function (R) {
    draw_list();
  });

  function show_repo_dlg(R) {
    var V = new MTrackRepoEditView({
      model: R
    });
    V.show(function () {
      repos.add(R, {at: repos.length});
    });
  }

  function draw_list() {
    var ul = $('#repolist');
    ul.empty();

    repos.each(function (R) {
      addOne(R);
    });
  }
  draw_list();

  $('ul#repolist').on('click', 'a', function() {
    var R = $(this).closest('li').data('model');
    var V = new MTrackRepoEditView({
      model: R
    });
    V.show(function (model) {
    });

    return false;
  });

  $('#newrepobtn').click(function () {
    var R = new MTrackRepo;
    show_repo_dlg(R);
  });
});
</script>
HTML;

  if (MTrackACL::hasAnyRights('Browser', 'create')) {
    echo "<button id='newrepobtn' class='btn btn-primary'><i class='icon-white icon-plus'></i> New Repo</button>";
  }
  mtrack_foot();
  exit;
}

$repotypes = array();
foreach (MTrackRepo::getAvailableSCMs() as $t => $r) {
  $d = $r->getSCMMetaData();
  $repotypes[$t] = $d['name'];
}

echo "<form method='post'>";

if ($rid == 'new') {
  MTrackACL::requireAnyRights('Browser', 'create');
?>
<h2>Add new or existing Repository</h2>
<p>
  Use the form below to tell mtrack where to find an existing
  repository and add it to its list.  Leave the "Path" field
  blank to create a new repository.
</p>
<table>
<?php
  echo "<tr><th>Name</th>" .
    "<td><input type='text' name='repo:name' value=''></td>" .
    "</tr>";
  echo "<tr><th>Type</th>" .
    "<td>" .
    mtrack_select_box("repo:type", $repotypes, null, true) .
    "</td></tr>\n";
  echo "<tr><th>Path</th>" .
    "<td><input type='text' name='repo:path' size='50' value=''></td>" .
    "</tr>\n";
  echo "<tr><td colspan='2'>Description<br><em>You may use <a href='{$ABSWEB}help.php/WikiFormatting' target='_blank'>WikiFormatting</a></em><br>\n";
  echo "<textarea name='repo:description' class='wiki shortwiki' rows='5' cols='78'>";
  echo "</textarea></td></tr>\n";
  echo "</table>";
} else {
  $P = MTrackRepo::loadById($rid);
  MTrackACL::requireAnyRights("repo:$P->repoid", 'modify');

  $name = htmlentities($P->shortname, ENT_QUOTES, 'utf-8');
  $type = htmlentities($P->scmtype, ENT_QUOTES, 'utf-8');
  $path = htmlentities($P->repopath, ENT_QUOTES, 'utf-8');
  $desc = htmlentities($P->description, ENT_QUOTES, 'utf-8');

  echo "<h2>Repository: $name</h2>\n";
  echo "<table>\n";

  if (!$P->parent) {
    /* not created/managed by us; some fields are editable */
    $name = "<input type='text' name='repo:name' value='$name'>";
    $type = mtrack_select_box("repo:type", $repotypes, $type);
    $path = "<input type='text' name='repo:path' size='50' value='$path'>";
  } else {
    $name = htmlentities($P->getBrowseRootName(), ENT_QUOTES, 'utf-8');
  }

  echo "<tr><th>Name</th><td>$name</td></tr>";
  echo "<tr><th>Type</th><td>$type</td></tr>\n";
  echo "<tr><th>Path</th><td>$path</td></tr>\n";
  echo "<tr><td colspan='2'>Description<br><em>You may use <a href='{$ABSWEB}help.php/WikiFormatting' target='_blank'>WikiFormatting</a></em><br>\n";
  echo "<textarea name='repo:description' class='wiki shortwiki' rows='5' cols='78'>$desc";
  echo "</textarea></td></tr>\n";

  echo "<tr><td colspan='2'>\n";

  $action_map = array(
    'Web' => array(
      'read'   => 'Browse via web UI',
      'modify' => 'Administer via web UI',
      'delete' => 'Delete repo via web UI',
    ),
    'SSH' => array(
      'checkout' => 'Check-out repo via SSH',
      'commit' => 'Commit changes to repo via SSH',
    ),
  );

  MTrackACL::renderACLForm('perms', "repo:$P->repoid", $action_map);

  echo "</tr>\n";
  echo "</table>";
}

$projects = array();
foreach (MTrackDB::q('select projid, name, shortname from projects
    order by name')->fetchAll() as $row) {
  if ($row[1] != $row[2]) {
    $projects[$row[0]] = $row[1] . " ($row[2])";
  } else {
    $projects[$row[0]] = $row[1];
  }
}

if (count($projects)) {

  echo <<<HTML
<h3>Linked Projects</h3>
<p>
Project links help associate code changes made in a repository with a project,
and this in turn helps mtrack decide who to notify about the change.
</p>
<p>
When assessing a change, mtrack will try each regex listed below and then take
the project that corresponds with the longest match--not the longest pattern;
the longest actual match.
</p>
<p>
The regex should just be the bare regex string--you must not enclose it in
regex delimiters.
</p>
<p>
You can remove a link by setting the regex to the empty string.
</p>
HTML;

  echo "<table>";
  echo "<tr><th>Regex</th><th>Project</th></tr>\n";

  if ($rid != 'new') {
    foreach ($P->getLinks() as $lid => $n) {
      list($pid, $regex) = $n;

      $regex = htmlentities($regex, ENT_QUOTES, 'utf-8');
      echo "<tr><td>" .
        "<input type='text' name='link:$lid:regex' value='$regex'></td>".
        "<td>" . mtrack_select_box("link:$lid:project", $projects, $pid) .
        "</td></tr>\n";
    }
  }

  if ($rid == 'new') {
    $newre = '/';
  } else {
    $newre = '';
  }

  echo "<tr><td>" .
    "<input type='text' name='link:new:regex' value='$newre'></td>".
    "<td>" . mtrack_select_box("link:new:project", $projects) .
    "</td><td>Add new link</td></tr>\n";

  echo "</table>";
}

echo "<button>Save Changes</button></form>";

mtrack_foot();

