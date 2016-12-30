<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */
include '../inc/common.php';

if ($pi = mtrack_get_pathinfo()) {
  $id = $pi;
} else {
  $id = $_GET['id'];
}

if ($id == 'new') {
  $issue = new MTrackIssue;
  $issue->priority = 'normal';
} else {
  if (strlen($id) == 32) {
    $issue = MTrackIssue::loadById($id);
  } else {
    $issue = MTrackIssue::loadByNSIdent($id);
  }
  if (!$issue) {
    throw new Exception("Invalid ticket $id");
  }
}

$field_data = MTrackAPI::invoke('GET', '/ticket/meta/fields', null,
  array('tid' => $issue->tid))->result;

$FIELDSET = json_encode($field_data);

if ($id == 'new') {
  MTrackACL::requireAllRights("Tickets", 'create');
  $editable = 'true';
  mtrack_head("New ticket");
  $TICKET = json_encode(MTrackIssue::rest_return_ticket($issue));
  $CHANGES = json_encode(array());
  $ATTACH = json_encode(array());
} else {
  MTrackACL::requireAllRights("ticket:" . $issue->tid, 'read');
  $editable = json_encode(
    MTrackACL::hasAllRights("ticket:" . $issue->tid, 'modify'));
  if ($issue->nsident) {
    mtrack_head("#$issue->nsident " . $issue->summary);
  } else {
    mtrack_head("#$id " . $issue->summary);
  }
  $TICKET = json_encode(MTrackAPI::invoke('GET', "/ticket/$id")->result);
  $CHANGES = json_encode(MTrackAPI::invoke(
                'GET', "/ticket/$id/changes")->result);
  $ATTACH = json_encode(MTrackAPI::invoke(
                'GET', "/ticket/$id/attach")->result);
}

echo <<<HTML
<div id="ticket"></div>
<script type='text/javascript'>

$(document).ready(function() {
  var TheTicket = null;
  var base_ticket = $TICKET;
  var FIELDSET = $FIELDSET;
  var editable = $editable;
  var editor = null;
  var changes = $CHANGES;
  var attachments = $ATTACH;
  var comment_editor = null;

  TheTicket = new MTrackTicket(base_ticket);
  TheTicket.getAttachments().reset(attachments);
  TheTicket.getChanges().reset(changes);

  TheTicket.bind('change:summary', function() {
    $('html head title').text('#' + TheTicket.get('nsident') + ' ' +
      TheTicket.get('summary'));
  });

  var V = new MTrackTicketViewer({
    model: TheTicket,
    fields: FIELDSET,
    el: $('#ticket')
  });
  V.render();

  setInterval(function () {
    V.refresh()
  }, 60000);

});
</script>
HTML;

mtrack_foot();
