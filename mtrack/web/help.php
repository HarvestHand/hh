<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

include '../inc/common.php';

$topic = mtrack_get_pathinfo();
$helpdir = dirname(__FILE__) . '/../defaults/help';
if (strpos($topic, '..') !== false) {
  throw new Exception("invalid help topic");
}
$name = $helpdir . '/' . $topic;

function build_help_tree($tree, $dir) {
  foreach (scandir($dir) as $ent) {
    if ($ent[0] == '.') {
      continue;
    }
    $full = $dir . DIRECTORY_SEPARATOR . $ent;
    if (is_dir($full)) {
      $kid = new stdclass;
      build_help_tree($kid, $full);
      $tree->{$ent} = $kid;
    } else {
      $tree->$ent = $ent;
    }
  }
}

function walk_tree(&$col, $base, $tree, $type)
{
  if ($base != '') {
    $base .= '/';
  }
  foreach ($tree as $name => $val) {
    if (is_object($val)) {
      walk_tree($col, $base . $name, $val, $type);
    } else {
      $o = new stdclass;
      $o->type = ucfirst($type);
      $o->name = $name;
      $o->fullname = $base . $name;
      if ($type == 'help') {
        $o->fullname = preg_replace('/\.md$/', '', $o->fullname);
      }
      $o->lower = strtolower($o->fullname);
      $o->url = $type . '.php/' . $o->fullname;
      $col[] = $o;
    }
  }
}

function get_collection() {
  $col = array();

  $htree = mtrack_cache('get_help_tree', array());
  walk_tree($col, '', $htree, 'help');

  if (MTrackACL::hasAnyRights('Wiki', 'read')) {
    $tree = MTrackWikiItem::get_wiki_tree();
    walk_tree($col, '', $tree, 'wiki');
  }

  return $col;
}

function get_help_tree() {
  $htree = new stdclass;
  build_help_tree($htree, dirname(__FILE__) . '/../defaults/help');
  return $htree;
}

if (!strlen($topic)) {
  mtrack_head("Help topics");

  $pages = json_encode(get_collection());
  if (MTrackACL::hasAnyRights('Wiki', 'read')) {
    $recent = MTrackWikiItem::get_recent_changes();
  } else {
    $recent = array();
  }
  $recent = json_encode($recent);

  echo <<<HTML
<style>
#hsearch {
  width: 20em;
  font-size: 2em;
}

#results {
  margin-top: 1em;
  font-size: 1.2em;
}

#results li {
  line-height: 1.5em;
  max-width: 40em;
}

#recentwiki {
  position: fixed;
  right: 1em;
  width: 20em;
  top: 7em;
  bottom: 0px;
  overflow-y: hidden;
}

</style>
<h1>Help &amp; Wiki</h1>
<label>Search Wiki and Help topics</label><br>
<input type="text" id="hsearch" placeholder="Type here to search">
<ul id="results"></ul>
<div id="recentwiki">
  <h2>Recent Wiki Changes</h2>
  <div id="changelist"></div>
</div>

<script type="text/template" id='change-template'>
  <div class='ticketevent'>
    <abbr class='timeinterval' title='<%- when %>'><%- when %></abbr></a> <%- who %>
  </div>
  <div class='ticketchangeinfo'>
    <img class='gravatar' src="${ABSWEB}avatar.php?u=<%- who %>&amp;s=48">
    <% _.each(pages, function(page) { %>
      <a class='wikilink' href='<%= ABSWEB %>wiki.php/<%- page %>?rev=<%- rev %>'><%- page %></a>
    <% }); %>
    <br/>
    <div class='wiki-change-desc'>
    <%= changelog_html %>
    </div>
  </div>
</script>

<script type='text/javascript'>
$(document).ready(function(){
  var recent = $recent;
  var t = _.template($('#change-template').html());
  var rlist = $('#changelist');
  _.each(recent, function (ent) {
    rlist.append(t(ent));
  });
  $('abbr', rlist).timeago();

  var pages = $pages;
  var wiki_by_name = {};
  _.each(pages, function (p) {
    if (p.type == 'Wiki') {
      wiki_by_name[p.fullname] = p;
    }
  });
  var S = $('#hsearch');

  var lastval = window.location.hash.substr(1);
  S.val(lastval);

  var timer = null;
  var ajax = null;

  S.attr('autocomplete', 'off');

  function make_entry(ul, page) {
    var li = $('<li/>');
    li.attr('mtrack-fullname', page.fullname);
    li.append('<span>' + page.type + ':</span> ');
    var a = $('<a/>');
    a.text(page.fullname);
    a.attr('href', ABSWEB + page.url);
    if (page.type == 'Wiki') {
      a.addClass('wikilink');
    } else {
      a.addClass('helplink');
    }
    li.append(a);
    ul.append(li);

    return li;
  }

  function show_list(res) {
    var ul = $('#results');
    ul.empty();
    _.each(res, function (page) {
      make_entry(ul, page);
    });
  }

  function annotate_list(res) {
    var ul = $('#results');
    _.each(res.results, function (r) {
      var page = wiki_by_name[r.id];
      if (!page) {
        /* it's been deleted but is still in the index */
        return;
      }
      /* See if we already have an entry for this guy in the list */
      var li = null;
      $('li', ul).each(function () {
        if ($(this).attr('mtrack-fullname') == r.id) {
          li = $(this);
        }
      });

      /* if not, then we need to create one */
      if (!li) {
        li = make_entry(ul, page);
      }
      _.each(r.hits, function (hit) {
        $(hit.excerpt).appendTo(li);
      });

    });
  }

  function search(q) {
    // Generate list of matching items
    // Compute the score for this item
    var biggest = 0;
    var res = [];
    if (q.length <= 2) {
      show_list(pages);
      return;
    }
    _.each(pages, function (page) {
      if ('cache' in page) {
        if (q in page.cache) {
          page.s = page.cache[q];
          res.push(page);
          return;
        }
      } else {
        page.cache = {};
      }
      // compute
      page.s = page.lower.QuickSilverScore(q);
      page.cache[q] = page.s;
      if (page.s > 0) {
        res.push(page);
      }
    });
    res.sort(function (a, b) { return b.s - a.s; });
    show_list(res);
    ajax = $.ajax({
      url: ABSWEB + "api.php/search/query",
      dataType: 'json',
      data: {
        q: "+type:wiki +\"" + q + "\""
      },
      success: function (data) {
        annotate_list(data);
      },
      complete: function (xhr, text) {
        ajax = null;
      }
    });
  }

  search(S.val().toLowerCase());

  S.bind('keyup.mtrack', function () {
    var q = $(this).val();
    if (q == lastval) {
      return;
    }
    lastval = q;
    q = q.toLowerCase();
    if (ajax) {
      ajax.abort();
      ajax = null;
    }
    clearTimeout(timer);
    timer = setTimeout(function () {
      search(q.toLowerCase());
    }, 250);
  });
});
</script>
HTML;

} elseif (!file_exists("$name.md")) {
  header("HTTP/1.0 404 Not Found");
  mtrack_head("no help topic $topic");

  echo "<h1>No Help topic ", htmlentities($topic), "</h1>";
} else {
  mtrack_head("Help: $topic");
  $wiki = mtrack_markdown(file_get_contents("$name.md"));
  echo <<<HTML
<div id='wikiview'>
  <div id='wiki' class='wikipage'>$wiki</div>
</div>
<div id='wikiinfo'>
  <label>Page Outline</label>
  <ol id='outline'></ul>
</div>
<script>
$(document).ready(function () {
  mtrack_wiki_outline($('#wiki'), $('#outline'));
});
</script>
HTML;
}

mtrack_foot();
