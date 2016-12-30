<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

MTrackACL::requireAnyRights('Projects', 'modify');

mtrack_head("Administration - Projects");
mtrack_admin_nav();

$projects = json_encode(MTrackAPI::invoke('GET', '/project/')->result);

echo <<<HTML
<h1>Projects</h1>
<p>
Projects can be created to track development on a per-project or per-product
basis.  Components may be associated with a project, as well as a default
email distribution address.
</p>
<script>
$(document).ready(function() {
  var projects = new MTrackProjectCollection($projects);

//  $('#projlist').sortable();

  function show_edit(P) {
    var V = new MTrackProjectEditView({
      model: P
    });
    var is_new = P.isNew();
    V.show(function (model) {
      if (is_new) {
        projects.add(model, {at: projects.length});
      } else {
        render_list();
      }
    });
  };

  $('#projlist').on('click', 'a', function () {
    var P = $(this.parentElement).data('project');
    show_edit(P);
  });

  function addOne(P) {
    var li = $('<li/>');
    var a = $('<a href="#"/>');
    a.text(P.get('name'));
    li.append(a);
    li.data('project', P);
    li.appendTo('#projlist');
  }

  function render_list() {
    $('#projlist').empty();
    projects.each(function (P) {
      addOne(P);
    });
  }
  render_list();

  projects.bind('add', function (P) {
    addOne(P);
  });

  $('#newrepobtn').click(function () {
    var P = new MTrackProject;
    show_edit(P);
  });

  // Clear any text from the form when it hides
  $('#editdialog').on('hidden', function () {
    $('input', this).val('');
    $('div.alert', this).remove();
    $('#saveproj').off('click.saveproj');
  });
});
</script>
<button id='newrepobtn' class='btn btn-primary'
  type='button'><i class='icon-plus icon-white'></i> New Project</button>

<ul id='projlist' class='nav nav-pills nav-stacked'>
</ul>

HTML;


mtrack_foot();

