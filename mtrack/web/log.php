<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';
MTrackACL::requireAllRights('Browser', 'read');

$pi = mtrack_get_pathinfo(true);
$crumbs = MTrackSCM::makeBreadcrumbs($pi);
if (!strlen($pi)) {
  $pi = '/';
}

$params = array();
if (isset($_GET['jump']) && strlen($_GET['jump'])) {
  $jump = '?jump=' . urlencode($_GET['jump']);
  list($object, $ident) = explode(':', $_GET['jump'], 2);
  $params[$object] = $ident;
} else {
  $_GET['jump'] = '';
  $jump = '';
}

$hist = MTrackAPI::invoke('GET', '/repo/history' . $pi, null, $params)->result;

mtrack_head("Log $pi");

/* Render a bread-crumb enabled location indicator */
echo "<div class='browselocation'>Location: ";
$location = null;
$last = array_pop($crumbs);
foreach ($crumbs as $i => $path) {
  if ($i == 0) {
    $path = '[root]';
    echo "<a href='{$ABSWEB}browse.php$jump'>$path</a> / ";
  } else if ($i == 1) {
    $location .= '/' . htmlentities(urlencode($path), ENT_QUOTES, 'utf-8');
    echo "<a href='{$ABSWEB}browse.php$location$jump'>$path</a> / ";
  } else {
    $location .= '/' . htmlentities(urlencode($path), ENT_QUOTES, 'utf-8');
    echo "<a href='{$ABSWEB}log.php$location$jump'>$path</a> / ";
  }
}
echo "$last";


$branches = $hist->branches;
$tags = $hist->tags;
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
  echo "<form>";
  echo mtrack_select_box("jump", $jumps, $_GET['jump']);
  echo "<button type='submit'>Choose</button></form>\n";
}
echo "</div>";

$hist = json_encode($hist);
echo <<<HTML
<script type="text/template" id='hist-template'>
  <div class='histevent'>
    <a class='pmark' href='#<%- rev %>'>#</a> <a name='<%- rev %>'>&nbsp;</a><abbr class='timeinterval' title='<%- when %>'><%- when %></abbr> <%- who %>
  </div>
  <div class='histinfo'>
    <img class='gravatar' src="${ABSWEB}avatar.php?u=<%- who %>&amp;s=36">
    <a class='changesetlink' href='<%- url %>'>[<%- shortrev %>]</a>
    <% if (branch) { %>
    <span class='branchname'><%- branch %></span>
    <% } %>
    <% _.each(tags, function (t) { %>
    <span class='tagname'><%- t %></span>
    <% }); %>
    <div class='changelog'><%= changelog_html %></div>
  </div>
</script>
<div id="history">
  <em>No history for the requested path</em>
</div>
<script>
$(document).ready(function () {
  var hist = $hist;
  var templ = _.template($('#hist-template').html());

  function get_more() {
    /* find earliest revision */
    var rev = hist.entries[hist.entries.length-1].rev;
    var params = {
      rev: rev,
      // ask for 1 more; we're basing off the last one we saw and this
      // will be included in the results
      limit: hist.limit + 1
    };

    $.ajax({
      url: ABSWEB + 'api.php/repo/history/' +
              hist.repo + '/' + hist.path,
      data: params,
      success: function(data) {
        if (data.entries.length > 1) {
          data.entries.shift(); // this is a duplicate; remove it
          data.prev = hist;
          hist = data;
          add_entries(data);
        }
      },
      complete: function() {
        $('#history button').removeAttr('disabled');
      }
    });
  }

  function add_entries(hist) {
    var H = $('#history');
    $('button', H).remove();
    _.each(hist.entries, function (ent) {
      var d = $('<div class="histentry"/>');

      ent.url = ABSWEB + 'changeset.php/' + hist.repo + '/' + ent.rev;
      var r = ent.rev + '';
      if (r.length > 12) {
        r = r.substr(0, 12);
      }
      ent.shortrev = r;

      d.html(templ(ent));
      H.append(d);
    });
    var b = $('<button>More</button>');
    H.append(b);
    b.click(function () {
      $(this).attr('disabled', 'disabled');
      get_more();
    });
    $('abbr.timeinterval', H).timeago();
  }

  if (hist.entries.length) {
    var H = $('#history');
    H.empty();
    add_entries(hist);
  }

});
</script>
HTML;

mtrack_foot();

