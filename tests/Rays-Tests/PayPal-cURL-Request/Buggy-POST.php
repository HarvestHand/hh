<?php
/**
 * Created by PhpStorm.
 * User: ray
 * Date: 5/27/15
 * Time: 11:03 AM
 */

// Mike's example PayPal IPN test.
error_reporting(E_ALL);
ini_set("display_errors", 1);

$content = 'mc_gross=50.00&protection_eligibility=Eligible&address_status=confirmed&payer_id=5PMGYWKBRQZT8&tax=0.00&address_street=228+Greenwood+Avenue&payment_date=03%3A45%3A08+Mar+05%2C+2015+PST&payment_status=Completed&charset=windows-1252&address_zip=B3T+1H8&first_name=Christal+Lee&mc_fee=1.40&address_country_code=CA&address_name=Christal+Lee+Boutilier&notify_version=3.8&custom=&payer_status=verified&business=info%40taprootfarms.ca&address_country=Canada&address_city=Timberlea&quantity=1&verify_sign=AFcDZYj88fqGxr8zEfOTQfUqeSZmAkf1P4XJoR3MnacQZaAq97sKdok.&payer_email=valkyriens%40yahoo.com&memo=This+is+my+first+payment+on+the+2015-2016+veggie+share+(appetizer).+I+know+you+prefer+payment+as+soon+as+possible%2C+so+I+will+pay+%2450+every+two+weeks+(on+payday)+until+my+account+is+paid+up.+Thanks+for+coming+out+to+see+us+at+the+Department+of+Education+%3A)&txn_id=5WT436234S3597011&payment_type=instant&last_name=Boutilier&address_state=Nova+Scotia&receiver_email=info%40taprootfarms.ca&payment_fee=&receiver_id=Q78CDTRDK2MHJ&txn_type=web_accept&item_name=TapRoot+Farms+Payment&mc_currency=CAD&item_number=hhc%3A2074&residence_country=CA&handling_amount=0.00&transaction_subject=&payment_gross=&shipping=0.00&ipn_track_id=b2c712acabe57&cmd=_notify-validate';
$context = stream_context_create(array('http' => array('method' => 'POST', 'content' => $content, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n" . "Content-Length: " . strlen($content))));

$url = 'https://www.paypal.com/cgi-bin/webscr';

echo $content;
echo $url;
print_r($context);

$result = file_get_contents($url, false, $context);

print_r($result);
