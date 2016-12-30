<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../../inc/common.php';

MTrackACL::requireAnyRights('Enumerations', 'modify');

$ename = mtrack_get_pathinfo();
$enums = array(
  'Priority' => '/ticket/enums/priority',
  'TicketState' => '/ticket/enums/state',
  'Severity' => '/ticket/enums/severity',
  'Resolution' => '/ticket/enums/resolution',
  'Classification' => '/ticket/enums/classification',
);

if (!isset($enums[$ename])) {
  throw new Exception("Invalid enum type");
}

mtrack_head("Administration - $ename");
mtrack_admin_nav();

$url = $enums[$ename];
$col = json_encode(MTrackAPI::invoke('GET', $url)->result);

echo <<<HTML
<h1>Administer Ticket $ename</h1>
<p>Drag to re-order!</p>
<script>
$(document).ready(function() {
  var MODEL = Backbone.Model.extend({
    url: function() {
      return ABSWEB + "api.php$url/" + this.id;
    }
  });
  var ECOL = Backbone.Collection.extend({
    model: MODEL
  });
  var COL = new ECOL($col);

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
      "<b>" + model.id + "</b>: " + err + '</div>').
      appendTo('#content');
  }

  $('#elist').sortable({
    placeholder: 'ticketDragTarget',
    start: function (evt, ui) {
      ui.placeholder.height(ui.item.height());
      ui.helper.addClass('draggingTicket');
    },
    stop: function (evt, ui) {
      ui.item.removeClass('draggingTicket');
    },
    update: function (evt, ui) {
      /* update the value of each model in the collection */
      $('#elist').children().each(function (idx, elt) {
        var E = $(elt).data('model');
        idx++; // want it to be 1 based
        if (E.get('value') != idx) {
          E.save({value: idx}, {error: capture_error});
        }
      });
    }
  });
  $('#elist').on('click', 'input[type=checkbox]', function () {
    var E = $(this).closest('li').data('model');
    E.set({deleted: $(this).prop('checked')});
    E.save();
  });

  function addOne(E) {
    var li = $('<li/>');
    var span = $('<div class="handle"/>');
    span.text(E.id);
    li.append(span);
    li.data('model', E);
    var cont = $('<div class="mark"/>');
    var cb = $('<input type="checkbox">');
    cb.attr('checked', E.get('deleted'));
    cont.append(cb);
    cont.append(" <span>Mark as deleted</span>");
    li.append(cont);
    li.appendTo('#elist');
  }

  COL.each(function (E) {
    addOne(E);
  });

  $('#newbtn').click(function () {
    var input = $('#newname');
    var name = input.val();
    input.val('');
    if (COL.get(name)) {
      return;
    }
    var E = new MODEL;
    E.save({
      id: name,
      value: COL.length,
      deleted: false
    }, {
      success: function (model, resp) {
        COL.add(model, {at: COL.length});
        addOne(model);
      },
      error: capture_error
    });
  });
});
</script>
<style>
ul.tickets {
padding-top: 1em;
}
ul.tickets li div.mark {
  font-size: smaller;
  float: right;
}
input#newname {
  font-size: 1.4em;
  width: 20em;
}
</style>
<ul id='elist' class='tickets'></ul>

<input type="text" id="newname" placeholder="Add a new $ename">
<button id='newbtn' class='btn btn-primary'><i class='icon-white icon-plus'></i> Add</button>
HTML;


mtrack_foot();

