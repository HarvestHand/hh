<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */
/* Evidence Based Scheduling utility functions
 * See http://www.joelonsoftware.com/items/2007/10/26.html */

class MTrackEBS {

  function getVelocityDataForUser($user) {
    $last_six_months = MTrackDB::unixtime(strtotime("-6 months"));
    $vdata = MTrackDB::q(<<<SQL
select max(estimated), sum(expended)
 from changes c
 left join effort e on (c.cid = e.eid)
 left join tickets t on (e.tid = t.tid)
where
   who = ? and
   e.tid is not null and
   estimated > 0
--   and t.status = 'closed'
group by e.tid
SQL
   #   , $last_six_months
      , $user
      )->fetchAll(PDO::FETCH_NUM);
    $v = array();
    foreach ($vdata as $row) {
      list($est, $act) = $row;
      if ($est != 0 && $act != 0) {
        $v[] = (float)$est / (float)$act;
      }
    }
    return $v;
  }

  function MonteCarlo($user, $estimate) {
    $v = self::getVelocityDataForUser($user);
    /* if we don't have enough data, make some up */
    while (count($v) < 6) {
      $v[] = mt_rand(1, 15) / 10;
    }
    $mc = array();
    for ($i = 0; $i < 100; $i++) {
      $x = array_rand($v);
      $P = $estimate / $v[$x];
      $mc[$P]++;
    }
    asort($mc);
    return $mc;
  }
}


