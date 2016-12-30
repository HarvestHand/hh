<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../inc/common.php';

header('Content-Type: text/plain');
header('Content-Disposition: inline');
ob_start();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
  $res = new stdclass;
  $res->status = 'success';
  $res->code = 0;
  $res->message = 'nothing to do';
  echo json_encode($res);
  exit;
}

$object = $_POST['object'];

try {
  MTrackACL::requireAllRights($object, 'modify');

  if (!preg_match('/^(ticket|wiki):/', $object)) {
    throw new Exception("cannot attach to $object");
  }

  $CS = MTrackChangeset::begin($object, "attaching files");
  $nfiles = 0;

  if (isset($_FILES['attachments']) && is_array($_FILES['attachments'])) {
    foreach ($_FILES['attachments']['name'] as $fileid => $name) {
      $message = null;
      switch ($_FILES['attachments']['error'][$fileid]) {
        case UPLOAD_ERR_OK:
          $do_attach = true;
          break;
        case UPLOAD_ERR_NO_FILE:
          break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
          $message = "Attachment(s) exceed the upload file size limit";
          break;
        case UPLOAD_ERR_PARTIAL:
        case UPLOAD_ERR_CANT_WRITE:
          $message = "Attachment file upload failed";
          break;
        case UPLOAD_ERR_NO_TMP_DIR:
          $message = "Server configuration prevents upload due to missing temporary dir";
          break;
        case UPLOAD_ERR_EXTENSION:
          $message = "An extension prevented an upload from running";
      }
      if ($message !== null) {
        throw new Exception($message);
      }
    }

    foreach ($_FILES['attachments']['name'] as $fileid => $name) {
      if (MTrackAttachment::add($object,
          $_FILES['attachments']['tmp_name'][$fileid],
          $_FILES['attachments']['name'][$fileid],
          $CS)) {
        $nfiles++;
      }
    }
  }

  if ($nfiles) {
    $CS->commit();

    $res = new stdclass;
    $res->status = 'success';
    $res->attachments = MTrackAttachment::getList($object);

    ob_end_clean();
    echo json_encode($res);
    exit;
  }

  throw new Exception("no files processed");

} catch (Exception $e) {
  $res = new stdclass;
  $res->status = 'error';
  $res->code = $e->getCode();
  $res->message = $e->getMessage();
  ob_end_clean();
  echo json_encode($res);
  exit;
}

