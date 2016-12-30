<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackAttachment {

  static function add($object, $local_filename, $filename,
      MTrackChangeset $CS)
  {
    $size = filesize($local_filename);
    if (!$size) {
      return false;
    }
    $hash = self::import_file($local_filename);
    $fp = fopen($local_filename, 'rb');
    $q = MTrackDB::get()->prepare(
      'insert into attachments (object, hash, filename, size, cid, payload)
      values (?, ?, ?, ?, ?, ?)');
    $q->bindValue(1, $object);
    $q->bindValue(2, $hash);
    $q->bindValue(3, $filename);
    $q->bindValue(4, $size);
    $q->bindValue(5, $CS->cid);
    $q->bindValue(6, $fp, PDO::PARAM_LOB);
    $q->execute();
    $CS->add("$object:@attachment:", '', $filename);
    return true;
  }

  static function process_delete($relobj, MTrackChangeset $CS) {
    if (!isset($_POST['delete_attachment'])) return;
    if (!is_array($_POST['delete_attachment'])) return;
    foreach ($_POST['delete_attachment'] as $name) {
      $vars = explode('/', $name);
      $filename = array_pop($vars);
      $cid = array_pop($vars);
      $object = join('/', $vars);

      if ($object != $relobj) return;
      MTrackDB::q('delete from attachments where object = ? and
          cid = ? and filename = ?', $object, $cid, $filename);
      $CS->add("$object:@attachment:", $filename, '');
    }
  }

  /* this function is registered into sqlite and invoked from
   * a trigger whenever an attachment row is deleted */
  static function attachment_row_deleted($hash, $count)
  {
    if ($count == 0) {
      // unlink the underlying file here
      unlink(self::local_path($hash, false));
    }
    return $count;
  }

  static function hash_file($filename)
  {
    return sha1_file($filename);
  }

  static function local_path($hash, $fetch = true)
  {
    $adir = MTrackConfig::get('core', 'vardir') . '/attach';

    /* 40 hex digits: split into 16, 16, 4, 4 */
    $a = substr($hash, 0, 16);
    $b = substr($hash, 16, 16);
    $c = substr($hash, 32, 4);
    $d = substr($hash, 36, 4);

    $dir = "$adir/$a/$b/$c";
    if (!is_dir($dir)) {
      $mask = umask(0);
      mkdir($dir, 02777, true);
      umask($mask);
    }
    $filename = $dir . "/$d";

    if ($fetch) {
      // Tricky locking bit
      $fp = @fopen($filename, 'c+');
      flock($fp, LOCK_EX);
      $st = fstat($fp);
      if ($st['size'] == 0) {
        /* we get to fill it out */

        $db = MTrackDB::get();
        $q = $db->prepare(
            'select payload from attachments where hash = ?');
        $q->execute(array($hash));
        $q->bindColumn(1, $blob, PDO::PARAM_LOB);
        $q->fetch();
        if (is_string($blob)) {
          fwrite($fp, $blob);
        } else {
          stream_copy_to_stream($blob, $fp);
        }
        rewind($fp);
      }
    }

    return $filename;
  }

  /* calculates the hash of the filename.  If another file with
   * the same hash does not already exist in the attachment area,
   * the file is copied in.
   * Returns the hash */
  static function import_file($filename)
  {
    $h = self::hash_file($filename);
    $dest = self::local_path($h, false);
    if (!file_exists($dest)) {
      if (is_uploaded_file($filename)) {
        move_uploaded_file($filename, $dest);
      } else if (!is_file($filename)) {
        throw new Exception("$filename does not exist");
      } else {
        copy($filename, $dest);
      }
    }
    return $h;
  }

  static function renderDeleteList($object)
  {
    global $ABSWEB;

    $atts = MTrackDB::q('
      select * from attachments
      left join changes on (attachments.cid = changes.cid)
      where attachments.object = ? order by changedate, filename',
        $object)->fetchAll(PDO::FETCH_ASSOC);

    if (count($atts) == 0) return '';

    $max_dim = 150;

    $html = <<<HTML
<em>Select the checkbox to delete an attachment</em>
<table>
  <tr>
    <td>&nbsp;</td>
    <td>Attachment</td>
    <td>Size</td>
    <td>Added</td>
  </tr>
HTML;

    foreach ($atts as $row) {
      $url = "{$ABSWEB}attachment.php/$object/$row[cid]/$row[filename]";
      $html .= <<<HTML
<tr>
  <td><input type='checkbox' name='delete_attachment[]'
      value='$object/$row[cid]/$row[filename]'></td>
  <td><a class='attachment' href='$url'>$row[filename]</a></td>
  <td>$row[size]</td>
  <td>
HTML;
      $html .= mtrack_username($row['who'], array(
          'no_image' => true
        )) .
        " " . mtrack_date($row['changedate']) . "</td></tr>\n";
    }
    $html .= "</table><br>";
    return $html;
  }

  static function getAttachment($object, $name) {
    $atts = MTrackDB::q('
      select a.object, hash, filename, size, c.cid, who, changedate
      from attachments a
      left join changes c on (a.cid = c.cid)
      where a.object = ? and filename = ? order by changedate desc',
        $object, $name)->fetchAll(PDO::FETCH_OBJ);
    foreach ($atts as $A) {
      list($width, $height) = getimagesize(self::local_path($A->hash));
      if ($width + $height) {
        $A->width = $width;
        $A->height = $height;
      }
      $A->id = "$object/$A->cid/$A->filename";
      global $ABSWEB;
      $A->url = $ABSWEB . 'attachment.php/' . $A->id;
      return $A;
    }
    return null;
  }

  static function getList($object)
  {
    $atts = MTrackDB::q('
      select a.object, hash, filename, size, c.cid, who, changedate
      from attachments a
      left join changes c on (a.cid = c.cid)
      where a.object = ? order by changedate, filename',
        $object)->fetchAll(PDO::FETCH_OBJ);

    $res = array();
    foreach ($atts as $A) {
      list($width, $height) = getimagesize(self::local_path($A->hash));
      if ($width + $height) {
        $A->width = $width;
        $A->height = $height;
      }
      $A->id = "$object/$A->cid/$A->filename";
      $res[] = $A;
    }
    return $res;
  }

  /* renders the attachment list for a given object */
  static function renderList($object)
  {
    global $ABSWEB;

    $atts = MTrackDB::q('
      select * from attachments
      left join changes on (attachments.cid = changes.cid)
      where attachments.object = ? order by changedate, filename',
        $object)->fetchAll(PDO::FETCH_ASSOC);

    if (count($atts) == 0) return '';

    $max_dim = 150;

    $html = "<div class='attachment-list'><b>Attachments</b><ul>";
    foreach ($atts as $row) {
      $url = "{$ABSWEB}attachment.php/$object/$row[cid]/$row[filename]";
      $html .= "<li><a class='attachment'" .
        " href='$url'>".
        "$row[filename]</a> ($row[size]) added by " .
        mtrack_username($row['who'], array(
          'no_image' => true
        )) .
        " " . mtrack_date($row['changedate']);

      list($width, $height) = getimagesize(self::local_path($row['hash']));
      if ($width + $height) {
        /* limit maximum size */
        if ($width > $max_dim) {
          $height *= $max_dim / $width;
          $width = $max_dim;
        }
        if ($height > $max_dim) {
          $width *= $max_dim / $height;
          $height = $max_dim;
        }
        $html .= "<br><a href='$url'><img src='$url' width='$width' border='0' height='$height'></a>";
      }

      $html .= "</li>\n";
    }
    $html .= "</ul></div>";
    return $html;
  }

  static function rest_attachment($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'DELETE');
    $object = $captures['object'];
    $cid = $captures['cid'];
    $filename = $captures['filename'];
    MTrackACL::requireAllRights($object, 'modify');

    $CS = MTrackChangeset::begin($object, "delete attachment");
    MTrackDB::q('delete from attachments where object = ? and
        cid = ? and filename = ?', $object, $cid, $filename);
    $CS->add("$object:@attachment:", $filename, '');
    $CS->commit();
  }
}

MTrackAPI::register('/attachment/*object/:cid/:filename',
  'MTrackAttachment::rest_attachment');
