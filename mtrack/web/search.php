<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../inc/common.php';

$q = $_GET['q'];

$quick = MTrackSearchDB::expand_quick_link($q);
if ($quick) {
  header("Location: " . $quick[1]);
  exit;
}

mtrack_head("Search");
?>
<h1>Search</h1>

<input type="text" id="searchq" size="50" placeholder="Type here to search">
<div>
  <input type="checkbox" id="only_open_tickets" value="1"
  <?php
    if (isset($_GET['only_open_tickets'])) {
    echo "checked='checked'";
    }
  ?>
  >
    Restrict search to open tickets
</div>
<p>
  Search the full-text index.
  Read more about <a class='wikilink' href="<?php echo $ABSWEB ; ?>help.php/Searching">Searching</a>.
  <button data-toggle='collapse' data-target='#fieldsummary'
    type='button' class='btn'>Show Fields</button>
</p>
<p>
  You may also
  use the <a href="<?php echo $ABSWEB ?>query.php">Custom Ticket Query</a>
  page to create a report on the fly.
</p>

<div id='fieldsummary' class='collapse'>
  <p>The following fields are available for targeted searching:</p>
  <table>
    <tr>
      <th>Item</th>
      <th>Field</th>
      <th>Description</th>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>summary</td>
      <td>The one-line ticket summary</td>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>description</td>
      <td>The ticket description</td>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>changelog</td>
      <td>The changelog field</td>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>keyword</td>
      <td>The keyword field</td>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>date</td>
      <td>The last-changed date</td>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>who</td>
      <td>who last changed the ticket</td>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>creator</td>
      <td>who opened the ticket</td>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>created</td>
      <td>The date that the ticket was created</td>
    </tr>
    <tr>
      <td>Ticket</td>
      <td>owner</td>
      <td>who is responsible for the ticket</td>
    </tr>
    <tr>
      <td>Comment</td>
      <td>description</td>
      <td>The comment text</td>
    </tr>
    <tr>
      <td>Comment</td>
      <td>date</td>
      <td>Date the comment was made</td>
    </tr>
    <tr>
      <td>Comment</td>
      <td>who</td>
      <td>who made that comment</td>
    </tr>
    <tr>
      <td>Wiki</td>
      <td>wiki</td>
      <td>The content from the wiki page</td>
    </tr>
    <tr>
      <td>Wiki</td>
      <td>who</td>
      <td>Who last changed that wiki page</td>
    </tr>
    <tr>
      <td>Wiki</td>
      <td>date</td>
      <td>Date the wiki page was last changed</td>
    </tr>
<?php
$CF = MTrackTicket_CustomFields::getInstance();
foreach ($CF->getFields() as $f) {
  echo "<tr><td>Ticket</td><td>$f->name</td><td>",
    htmlentities($f->label, ENT_QUOTES, 'utf-8'),
    "</td></tr>\n";
}
?>
  </table>

</div>
</form>

<?php

if (strlen($q)) {
  $userq = $q;

  if (isset($_GET['only_open_tickets'])) {
    $q = "+type:ticket -status:closed +($q)";
  }

  $results = MTrackAPI::invoke('GET', '/search/query', null, array(
    'q' => $q
  ))->result;

} else {
  $results = null;
  $userq = "";
}
$results = json_encode($results);
$userq = json_encode($userq);
?>

<ul id="results" class="searchresults"></ul>

<script type="text/template" id='obj-template'>
  <%= link %>
  <% _.each(hits, function (hit) { %>
    <%= hit.excerpt %>
  <% }); %>
</script>

<?php
echo <<<HTML
<script>
$(document).ready(function () {
  var results = $results;
  var t = _.template($('#obj-template').html());

  function show_list(res) {
    var ul = $('#results');
    ul.empty();
    if (res) {
      _.each(res.results, function (obj) {
        var li = $("<li/>");
        li.html(t(obj));
        ul.append(li);
      });
    }
    if (!res || !res.results.length) {
      var li = $("<li/>");
      li.text("No matching results");
      ul.append(li);
    }
  }

  show_list(results);

  var ajax = null;
  function search(q) {
    if (!q.length) {
      show_list({results:[]});
      return;
    }
    if ($('#only_open_tickets').attr('checked')) {
      q = "+type:ticket -status:closed +(" + q + ")";
    }
    ajax = $.ajax({
      url: ABSWEB + 'api.php/search/query',
      dataType: 'json',
      data: {
        q: q
      },
      success: function (data) {
        show_list(data);
      },
      error: function (xhr, text, err) {
        var li = $("<li/>");
        li.text(err);
        $('#results').empty().append(li);
      },
      complete: function (xhr, text) {
        ajax = null;
      }
    });
  }

  var S = $('#searchq');
  S.val($userq);
  S.attr('autocomplete', 'off');
  var timer = null;
  var lastval = $userq;

  function trigger_search() {
    var q = S.val();

    if (ajax) {
      ajax.abort();
      ajax = null;
    }
    clearTimeout(timer);
    timer = setTimeout(function () {
      search(q);
    }, 250);
  }

  S.bind('keyup.mtrack', function () {
    var q = $(this).val();
    if (lastval == q) {
      return;
    }
    lastval = q;
    trigger_search();
  });

  $('#only_open_tickets').click(function () {
    trigger_search();
  });

});
</script>
HTML;

mtrack_foot();
