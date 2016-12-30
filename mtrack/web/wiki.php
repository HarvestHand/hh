<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

$pi = mtrack_get_pathinfo();
if (empty($pi)) {
  header("Location: {$ABSWEB}wiki.php/WikiStart");
  exit;
}

$rev = isset($_GET['rev']) ? $_GET['rev'] : null;
if (!$rev || !preg_match("/^[a-fA-F0-9]+$/", $rev)) {
  $rev = null;
}

$hide_outline = false;

try {
  if ($rev) {
    $W = MTrackAPI::invoke('GET', '/wiki/page/' . $pi, null, array(
      'rev' => $rev
    ))->result;
  } else {
    $W = MTrackAPI::invoke('GET', '/wiki/page/' . $pi)->result;
  }
  $ATTACH = json_encode(MTrackAPI::invoke(
                'GET', "/wiki/attach/" . $pi)->result);
  $edit = ($rev == null) && MTrackACL::hasAnyRights("wiki:$pi", 'modify');
} catch (MTrackAPI_Exception $e) {
  if ($e->getCode() != 404) {
    throw $e;
  }

  /* if they're looking at a directory, give them a list of candidate
   * pages to look at instead */
  $tree = MTrackWikiItem::get_wiki_tree();
  $path_bits = explode('/', $pi);
  $dir = $tree;
  foreach ($path_bits as $name) {
    if (!isset($tree->{$name})) {
      $tree = null;
      break;
    }
    $tree = $tree->{$name};
  }

  if ($tree) {
    $edit = false;

    /* fake an empty summary page listing out the elements on this one */
    $W = new stdclass;
    $W->id = $pi;
    $W->version = null;
    $W->content = "\n\n";

    foreach ($tree as $k => $name) {
      $W->content .= " * [wiki:$pi/$k $k]\n";
    }
    $W->content .= "\n\n[help: Complete List of Wiki Pages]\n";

    $W->content_html = MTrackWiki::format_to_html($W->content);
    $W->changelog_html = null;
    $W->changelog = null;
    $W->when = null;
    $W->who = null;
    $ATTACH = json_encode(array());
    $hide_outline = true;

  } else {

    /* fake an empty one so that we can give the user the option of creating
     * a new page */
    $W = new stdclass;
    $W->id = $pi;
    $W->version = null;
    $W->content = null;
    $W->content_html = '';
    $W->changelog_html = null;
    $W->changelog = null;
    $W->when = null;
    $W->who = null;
    $ATTACH = json_encode(array());
    $edit = MTrackACL::hasAnyRights("wiki:$pi", 'create');

    if (!$edit) {
      header("HTTP/1.0 404 Not Found");
      mtrack_head($pi);
      echo "<h1>Page Not Found</h1>";
      mtrack_foot();
      exit;
    }
  }
}
mtrack_head($pi);

if ($hide_outline) {
  echo <<<HTML
<script>
$(document).ready(function() {
  $('#outlinetoggle').hide();
  $('#wikiinfo').hide();
});
</script>
HTML;
}


$edit = json_encode($edit);
$latest = json_encode($rev == null);
$J = json_encode($W);
$crumbs = mtrack_breadcrumb($pi, $ABSWEB . 'wiki.php');
echo <<<HTML
<div id='wikiview'>
  $crumbs
  <div id='wiki' class='wikipage'></div>
  <div id='attachments'>
    <h2>Attachments</h2>
    <div id="attachlist"></div>
  </div>
</div>
<button class='btn' id='outlinetoggle'>Toggle Outline</button>
<div id='wikiinfo'>
  <div id='wikiinfotmpl'></div>
  <label>Page Outline</label>
  <ol id='outline'></ul>
</div>
<div id="attachment-form" class="popupForm" style="display:none">
  <form action="${ABSWEB}post-attachment.php" method="POST"
      id="upload-form" enctype="multipart/form-data" target="upload_target">
    <input type="hidden" name="object" value="wiki:$pi">
    <label for='attachments[]'>Select file(s) to be attached</label>
    <input name="attachments[]" class='btn multi' type="file">
    <iframe id="upload_target" name="upload_target" src="${ABSWEB}/mtrack.css">
    </iframe>
    <input type="submit" class="btn btn-primary"
      id="confirm-upload" value="Upload">
    <button class='btn' id="cancel-upload">Cancel</button>
  </form>
</div>
<script type="text/template" id='attach-template'>
  <a class='attachment' href='<%= ABSWEB %>attachment.php/<%- object %>/<%- cid %>/<%- filename %>'><%- filename %></a> (<%- size %>) added by <%- who %>
  <abbr class='timeinterval' title='<%- changedate %>'><%- changedate %></abbr>
  <button class='btn'>x</button>
  <% if (image && 0) { %>
  <br><a href='<%= ABSWEB %>attachment.php/<%- object %>/<%- cid %>/<%- filename %>'><img src='<%= ABSWEB %>attachment.php/<%- object %>/<%- cid %>/<%- filename %>' width='<%- width %>' height='<%- height %>' border='0'></a>
  <% } %>
</script>


<script type='text/template' id='wiki-info-template'>
<label><%- id %></label><br>
<% if (!latest) { %>
<br>
<div class='ui-state-highlight ui-corner-all'>
  <span class='ui-icon ui-icon-info'></span>
  This is not the most recent version of this page!<br>
  <a href="${ABSWEB}wiki.php/<%- id %>"><b>Show latest version</b></a>
</div>
<br>
<% } %>
<div id="wiki-buttons">
<button id="save-wiki" class="btn btn-success hide-until-change"><%
if (version) {
%>Save<% } else { %>Create this page<% } %></button>
  <button class='btn btn-primary' id="edit-wiki"
      ><i class="icon-edit icon-white"></i> Edit this page</button>
  <button id="cancel-wiki"
      class="btn hide-until-change">Cancel</button>
  <button class="btn" id="attach-wiki"><i class="icon-upload"></i> Add Attachment</button>
  <button class='btn'
    onclick="document.location.href = '${ABSWEB}help.php'; return false;"
    ><i class='icon-book'></i> Help &amp; Page List</button>
  <div class='alert alert-danger' id='wiki-error' style="display:none">
    <a class='close' data-dismiss='alert'>&times;</a>
    <span id="wiki-error-text">Whoops</span>
  </div>
  <br>
</div>
<% if (who) { %>
<div id="wiki-change-info">
<img class='gravatar' src="${ABSWEB}avatar.php?u=<%- who %>&amp;s=24">
<div class="wiki-change-desc">
<%= changelog_html %>
</div>
<abbr class='timeinterval' title='<%- when %>'><%- when %></abbr>
by <%- who %><br>
<a href="${ABSWEB}log.php/default/wiki/<%- filename %>">Page History</a>
</div>
<% } %>
</script>
<script>
$(document).ready(function() {
  var WIKIPAGE = $J;
  // Fake up a property so we can tell if we are looking at a historical
  // version of the wiki page
  WIKIPAGE.latest = $latest;

  var attachments = $ATTACH;
  WIKI = new MTrackWiki(WIKIPAGE);
  WIKI.getAttachments().reset(attachments);
  var editable = $edit;
  var editor = null;
  var attach_view = null;
  var wasNew = WIKI.isNew();

  $('#outlinetoggle').click(function () {
    $('#wikiinfo').toggle();
  });

  function update_attachments() {
    if (WIKI.getAttachments().length) {
      $('#attachments').show();
    } else {
      $('#attachments').hide();
    }
  }

  function save() {
    var overlay = $('<div class="overlay"><div class="progress progress-striped progress-success active"><div class="bar" style="width: 100%"></div></div></div>');
    overlay.appendTo('body').fadeIn('fast', function () {
      WIKI.save(WIKI.toJSON(), {
        success: function(model, response) {
          $('button.hide-until-change').fadeOut('fast');
          overlay.fadeOut('fast', function () {
            WIKIPAGE = WIKI.toJSON();
            WIKI.unset('comment', {silent: true});
            if (wasNew || !WIKI.get('content') ||
                WIKI.get('content').length == 0) {
              // created/deleted, easier just to reload
              window.onbeforeunload = null;
              window.location.href = window.location.href;
              return;
            }
            editor.render();
            overlay.remove();
            WIKI.changed = false;
            attach_view.editable = true;
            attach_view.render();
            mtrack_wiki_outline($('#wiki'), $('#outline'));
          });
        },
        error: function(model, response) {
          var err;
          var conflict = null;
          if (!_.isObject(response)) {
            err = response;
          } else {
            err = response.statusText;
            try {
              var r = JSON.parse(response.responseText);
              err = r.message;
              if (r.code == 409) {
                conflict = r.extra;
              }
            } catch (e) {
              err = response.statusText;
            }
          }

          overlay.fadeOut('fast', function () {
            overlay.remove();
            if (conflict) {
              WIKI.set(conflict);
            }
            $('#wiki-error-text').text(err);
            $('#wiki-error').fadeIn('fast')
          });
        }
      });
    });
  }

  var comment_explanation =
    "\\n\\n> Please enter a reason for this change. Your changes will not be applied until you click Save. (this line will be automatically removed)\\n";
  WIKI.bind('change:comment', function () {
    var c = WIKI.get('comment').replace(comment_explanation, "");
    WIKI.set({comment: c},{silent: true});
    save();
  });

  function update_info() {
    update_attachments();

    if (WIKI.hasChanged()) {
      WIKI.changed = true;
    }
    $('#wikiinfotmpl').html(_.template(
      $('#wiki-info-template').html(), WIKI.toJSON()
    ));
    if (!editable) {
      $('#wiki-buttons').hide();
    }
    if (WIKI.changed) {
      $('button.hide-until-change').fadeIn('fast');
    }
    $('#wiki-error').click(function () {
      $(this).fadeOut('fast');
      return false;
    });
    $('#wikiinfotmpl .timeinterval').timeago();

    $('#edit-wiki').click(function () {
      editor.edit();
      return false;
    });
    $('#cancel-wiki').click(function () {
      $('#wiki-error').fadeOut('fast');
      WIKI.set(WIKIPAGE);
      WIKI.changed = false;
      $('button.hide-until-change').fadeOut('fast');
    });

    $('#save-wiki').click(function () {
      $('#wiki-error').fadeOut('fast');
      if (!WIKI.get('comment')) {
        WIKI.set({comment: comment_explanation}, {silent:true});
      }
      var reason = new MTrackWikiTextAreaView({
        model: WIKI,
        srcattr: 'comment',
        use_overlay: true,
        Caption: "Comment on this wiki edit",
        OKLabel: "Save Wiki Page",
        CancelLabel: "Return to Wiki Editor"
      });
      reason.edit();
    });
    mtrack_wiki_outline($('#wiki'), $('#outline'));
  }

  WIKI.bind('change', update_info);
  WIKI.bind('error', function (model, err) {
    $('#wiki-error-text').text(err);
    $('#wiki-error').fadeIn('fast');
  });
  WIKI.getAttachments().bind('all', update_attachments);

  editor = new MTrackWikiTextAreaView({
    model: WIKI,
    wikiContext: "wiki:",
    use_overlay: true,
    srcattr: "content",
    readonly: !editable,
    renderedattr: "content_html",
    doubleclick: false,
    placeholder:
      WIKI.isNew() ?
        "This page doesn't exist; click the \"Edit this page\" button to start editing!"
        : "Saving this page now (with no content) will delete it",
    el: "#wiki"
  });
  /* hide the outline while editing, in case the user wants to search
   * for text in the page */
  editor.bind('editstart', function () {
    $('#wikiinfo').hide();
  });
  editor.bind('editend', function () {
    $('#wikiinfo').show();
  });

  update_info();

  attach_view = new MTrackTicketAttachmentsView({
    model: WIKI,
    collection: WIKI.getAttachments(),
    type: 'wiki',
    button: '#attach-wiki',
    editable: editable && !WIKI.isNew(),
    el: '#attachlist'
  });

  window.onbeforeunload = function() {
    if (WIKI.changed) {
      return "You haven't saved your changes!";
    }
  };

});
</script>
HTML;

mtrack_foot();
