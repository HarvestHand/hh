<?php # vim:ts=2:sw=2:et:
# Convert attachments so that a copy of blob lives in the db

echo "Migrating attachments\n";
$q = $db->prepare('update attachments set payload = ? where hash = ?');

foreach ($db->query('select hash from attachments')->fetchAll() as $row) {
  $path = MTrackAttachment::local_path($row['hash']);
  $fp = fopen($path, 'rb');
  $q->bindValue(1, $fp, PDO::PARAM_LOB);
  $q->bindValue(2, $row['hash']);
  $q->execute();
  fclose($fp);
  $fp = null;
}

