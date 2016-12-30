<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../../../inc/common.php';

$plugins = MTrackConfig::getSection('plugins');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['captcha_on']) && $_POST['captcha_on'] == 'on') {
    /* turn it on */
    $pub = $_POST['pub'];
    $priv = $_POST['priv'];
    if ($pub && $priv) {
      MTrackConfig::set('plugins', 'MTrackCaptcha_Recaptcha', "$pub,$priv");
    }
  } else {
    /* turn it off */
    MTrackConfig::remove('plugins', 'MTrackCaptcha_Recaptcha');
  }

  MTrackConfig::save();
  header("Location: {$ABSWEB}admin/auth.php");
  exit;
}

mtrack_head("Administration - reCAPTCHA");
mtrack_admin_nav();

$impl = MTrackCaptcha::$impl;

if ($impl && $impl instanceof MTrackCaptcha_Recaptcha) {
  $on = ' checked ';
  $pub = htmlentities($impl->pub, ENT_QUOTES, 'utf-8');
  $priv = htmlentities($impl->priv, ENT_QUOTES, 'utf-8');
} else {
  $on = '';
  $pub = '';
  $priv = '';
}

$dom = urlencode(htmlentities($_SERVER['SERVER_NAME'], ENT_QUOTES, 'utf-8'));

echo <<<HTML
<h1>reCAPTCHA</h1>
<p>
reCAPTCHA is a free <a href="http://www.captcha.net/">CAPTCHA</a>
service that helps to digitize books, newspapers
and old time radio shows.
</p>
<p>
To use reCAPTCHA, you need to obtain an pair of API keys; you can do so from
<a href="https://www.google.com/recaptcha/admin/create?domains=$dom&amp;app=mtrack"
  >the recaptcha admin site</a>.
</p>
<p>
At this time, CAPTCHA's are only used for the user registration pages;
this is the gate for untrusted and unauthenticated users.  Once a user
has successfully registered, we assume that they are not a bot.
</p>
<br>

<form method='POST'>
  <table>
    <tr>
      <td>Site Domain</td>
      <td><tt>$dom</tt></td>
    </tr>
    <tr>
      <td><label for="pub">Public Key</label></td>
      <td><input type="text" name="pub"
            value="$pub" size="64"
            placeholder="Enter your Public Key"></td>
    </tr>
    <tr>
      <td><label for="priv">Private Key</label></td>
      <td><input type="text" name="priv"
            value="$priv" size="64"
            placeholder="Enter your Private Key"></td>
    </tr>
  <table>
  <input type='checkbox' name='captcha_on' $on> Enable reCAPTCHA
  <br>
  <button class='btn btn-primary' type='submit'>
    Save Settings</button>
</form>
HTML;

mtrack_foot();


