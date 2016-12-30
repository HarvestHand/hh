<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../inc/common.php';
$pi = urldecode(mtrack_get_pathinfo());

if (!strlen($pi)) {
  throw new Exception("no milestone specified");
}

MTrackACL::requireAllRights("Roadmap", 'modify');

$MS = MTrackAPI::invoke('GET', "/milestone/$pi")->result;

if (!$MS) {
  throw new Exception("no such milestone $pi");
}
mtrack_head("$MS->name - Planning");

$MS = json_encode($MS);

$milestones = MTrackAPI::invoke('GET', '/milestones')->result;
$ALL_MS = array();
foreach ($milestones as $M) {
  if ($M->id == $ms->mid) continue;
  $m = new stdclass;
  $m->id = $M->id;
  $m->label = $M->name;
  $ALL_MS[] = $m;
}
$ALL_MS = json_encode($ALL_MS);

echo <<<HTML
<div id='plan-banner'>
  <h1 id='milestone-name'></h1>
  <div id="plan-milestone-other-selector"></div>
  <div id='plan-rsrc-summary'>
    Allocation
    <span id='plan-rsrc-total'></span>
    hours
  </div>
  <br>
  <button id='newtkt' class='btn btn-primary'><i class='icon-white icon-plus'></i>New Ticket</button>
</div>
<div id="plan-milestone-area">
  <div id="plan-ticket-list"></div>
  <div id="plan-ticket-other"></div>
  <div id="plan-rsrc-list"></div>
</div>

<script type="text/template" id='ticket-template'>
<div class='handle'>
  <div class='summary'>
  <% if (status == 'closed') { %><del><% } %>
  <% if (nsident) { %>#<%- nsident %><% } else { %>[NEW]<% } %> <%- summary %>
  <% if (status == 'closed') { %></del><% } %>
  </div>
  <% if (remaining) { %>
    <span class='loe'><%- remaining %></span>
  <% } %>
    <button class='btn btn-mini'><i class='icon-plus'></i></button>
  <% if (owner) { %>
    <img class='gravatar' src="${ABSWEB}avatar.php?u=<%- owner %>&amp;s=32">
  <% } %>
</div>
</script>

<script type="text/template" id="rsrc-template">
<div class='allocated-resource'>
  <img class='gravatar' src="${ABSWEB}avatar.php?u=<%- owner %>&amp;s=32">
  <%- owner %>
  <span class='loe'><%- total %></span>
</div>
</script>

<div id='tktdialog' class='modal hide'>
  <div class='modal-header'>
    <a class='close' data-dismiss='modal'>&times;</a>
    <h3><span id='tkttitle'></span>
      <span id='tktsummary'></span>
    </h3>
  </div>
  <div class='modal-body'>
  <div id='tktdesc'></div>
  </div>
  <div class='modal-footer'>
    <button class='btn' id='edittktdesc'
      ><i class='icon-pencil'></i> Edit Description</button>
    <button class='btn' data-dismiss='modal'>Cancel</button>
    <button type='submit' class='btn btn-primary'>Save</button>
  </div>
  </form>
</div>

<script>
$(document).ready(function () {
  var TheMilestone = new MTrackMilestone($MS);
  var ALL_MS = $ALL_MS;
  var OtherTicketList = null;

  var sel = $('<select/>', {
    "data-placeholder": "Select other milestone"
  });
  $('#plan-milestone-other-selector').append(sel);
  /* popover would be nice, but it craps out when used on a
   * position:fixed element */
  /*
  $('#plan-milestone-other-selector').popover({
    title: 'Source Milestone',
    placement: 'bottom',
    trigger: 'hover',
    content: 'Choose a second milestone to have its tickets display below.  You may then drag tickets between the milestones to move them'
});
   */
  sel.append("<option value=''></option>");
  _.each(ALL_MS, function (ms) {
    if (ms.id == TheMilestone.id) {
      return;
    }
    var opt = $("<option/>");
    opt.attr('value', ms.id);
    opt.text(ms.label);
    sel.append(opt);
  });
  sel.chosen({
    allow_single_deselect: true
  }).change(function() {
    var msid = sel.val();
    if (OtherTicketList) {
      delete OtherTicketList;
      $('#plan-ticket-other').empty();
      OtherTicketList = null;
    }

    if (msid) {
      var m = new MTrackMilestone({id: msid});
      m.fetch({success: function(model, response) {
        OtherTicketList = new MTrackPlanningTicketListView({
          el: '#plan-ticket-other',
          model: model
        });
      }});
    }
    $(document.body).animate({scrollTop: 0}, {
      duration: 300,
      easing: "easeOutQuint"
    });
  });

  /*
  new MTrackClickToEditTextField({
    model: TheMilestone,
    srcattr: "name",
    el: "#milestone-name",
    readonly: true,
    saveAfterEdit: true
  });
  TheMilestone.bind('change', function () {
    $('html head title').text(TheMilestone.get('name') + " - Planning");
});
  */
  $('#milestone-name').text('Planning: ' + TheMilestone.get('name'));
  var plv = new MTrackPlanningTicketListView({
    model: TheMilestone,
    el: '#plan-ticket-list'
  });

  var alloc_timer = null;

  function update_allocation() {
    if (alloc_timer) {
      return;
    }
    alloc_timer = setTimeout(function () {
      $.ajax({
        url: ABSWEB + "api.php/milestone/" + TheMilestone.id + "/time/remaining",
        success: function(data) {
          var r = $('#plan-rsrc-total');
          r.text(data.total);

          r = $('#plan-rsrc-list');
          r.empty();
          var template = _.template($('#rsrc-template').html());
          _.each(_.keys(data.users).sort(), function(u) {
            var t = Math.round(100 * data.users[u]) / 100;
            var d = $(template({owner: u, total: t}));
            r.append(d);
          });

          r.append($(template({
            owner: 'Unassigned',
            total: data.unassigned
          })));

          r.popover({
            title: 'Resource Allocation',
            trigger: 'hover',
            placement: 'left',
            content: 'This area shows resource allocation broken out by user.  It only includes tickets that have time remaining.  The sum of remaining time for tickets that are not assigned is shown at the bottom of this list.'
          });
        },
        complete: function() {
          alloc_timer = null;
        }
      });
    }, 200);
  }

  TheMilestone.tickets.bind('all', function () {
    update_allocation();
  });

  var fake_ticket = new MTrackTicket;
  var wiki = null;
  var summary_editor = null;
  var on_ticket_save = null;

  /* set stuff up for ticket editing */
  function setup_ticket_editor() {
    var M = $('#tktdialog');
    summary_editor = new MTrackClickToEditTextField({
        model: fake_ticket,
        srcattr: "summary",
        el: "#tktsummary",
        placeholder: "Enter ticket summary"
    });
    //$('#tktdesc').html(T.get('description_html'));
    wiki = new MTrackWikiTextAreaView({
        model: fake_ticket,
        wikiContext: "ticket:",
        use_overlay: true,
        Caption: "Edit Description",
        OKLabel: "Accept Description",
        CancelLabel: "Abandon changes to description",
        srcattr: "description",
        renderedattr: "description_html",
        el: "#tktdesc"
    });
    wiki.bind('editstart', function () {
      setTimeout(function () {
        M.modal('hide');
      }, 1000);
    });
    wiki.bind('editend', function () {
      M.modal('show');
    });
    $('#edittktdesc').click(function () {
      wiki.edit();
    });
    $('#tktdialog button[type=submit]').click(function () {
      on_ticket_save();
    });

  };
  setup_ticket_editor();

  function show_ticket_editor(T, after) {
    var M = $('#tktdialog');

    fake_ticket.set({
      summary: T.get('summary'),
      description_html: T.get('description_html'),
      description: T.get('description')
    });//, {silent: true});
    //wiki.change();

    $('#tkttitle').text(
      T.id ?
        ( 'Edit Ticket #' + T.get('nsident') ) :
        'New Ticket'
    );
    on_ticket_save = function() {
      // Apply changes from the clone
      T.save({
        summary: fake_ticket.get('summary'),
        description: fake_ticket.get('description'),
        description_html: fake_ticket.get('description_html')
      }, {
        success: function() {
          after.success();
        }
      });
      M.modal('hide');
    };

    M.modal();
  };

  $('#plan-ticket-list, #plan-ticket-other')
      .on('dblclick', 'div.handle', function() {
    var T = $(this.parentElement).data('plan-tkt');
    show_ticket_editor(T, {
      success: function () {
      }
    });
  });
  /* show nice data augments via popovers */
  $('#plan-ticket-list, #plan-ticket-other').popover({
    selector: 'div.handle',
    title: function() {
      var T = $(this.parentElement).data('plan-tkt');
      return T.get('summary');
    },
    placement: function() {
      var ele = this.\$element;
      return ele.parents().filter('#plan-ticket-list').length > 0 ?
        'right' : 'left';
    },
    content: function() {
      var T = $(this.parentElement).data('plan-tkt');
      var html = '';
      if (T.get('owner')) {
        html += "<b>Owner:</b> " + T.get("owner") + "<br>";
      }
      html += "<b>Type:</b> " + T.get("classification") + "<br>";
      html += "<b>Status:</b> " + T.get("status");

      return html;
    }
  });

  $('#newtkt').click(function() {
    var T = new MTrackTicket;
    var m = {};
    m[TheMilestone.id] = TheMilestone.get('name');
    T.set({milestones: m, classification: 'enhancement'}, {silent: true});
    show_ticket_editor(T, {
      success: function () {
        // TODO: add to collection
        TheMilestone.tickets.add(T, {at: TheMilestone.tickets.length});
      }
    });
  });


});
</script>
HTML;

mtrack_foot();
