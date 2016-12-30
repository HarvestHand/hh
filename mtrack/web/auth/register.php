<?php # vim:ts=2:sw=2:et:
/* For copyright and licensing terms, see the file named LICENSE */

include '../../inc/common.php';

if (MTrackAuth::whoami() != 'anonymous') {
  header("Location: $ABSWEB");
  exit;
}

function validate_input()
{
  $cap = MTrackCaptcha::check('');
  if (is_array($cap) && $cap[0] === false) {
    return "Captcha validation failed: " . $cap[1];
  }
  if (empty($_POST['id']) || empty($_POST['email'])
    || empty($_POST['fullname']))
  {
    return 'You must complete all of the fields';
  }

  if (isset($_POST['password']) || isset($_POST['password2'])) {
    if ($_POST['password'] != $_POST['password2']) {
      return "Passwords don't match";
    }
    if (!strlen($_POST['password'])) {
      return "Password must not be empty";
    }
  }

  return null;
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
    MTrackConfig::get('core', 'allow_self_registration'))
{
  $message = validate_input();
  if ($message === null) {

    $userid = $_POST['id'];
    $email = $_POST['email'];
    $name = $_POST['fullname'];

    /* is the requested id available? */
    $user = MTrackUser::loadUser($userid);
    if ($user) {
      $message = "Your selected user ID is not available";
    } else {
      $user = new MTrackUser;
      $user->userid = $userid;
      $user->email = $email;
      $user->fullname = $name;
      $user->active = true;

      $reg = $_SESSION['mtrack.auth.register'];
      if (isset($reg['alias'])) {
        // verify that alias doesn't already exist!
        $alias = MTrackUser::loadUser($reg['alias'], true);
        if (!$alias) {
          // We need to do this manually, as the User object save
          // method checks our rights, and we don't have any right now.
          MTrackDB::q('insert into useraliases (userid, alias) values (?, ?)',
            $userid, $reg['alias']);
        }
      }

      $CS = MTrackChangeset::begin("user:$user->userid", "registered");
      $user->save($CS);

      if (isset($_POST['password']) && strlen($_POST['password'])) {
        $user->setPassword($_POST['password']);
      }

      $CS->commit();

      /* now; we are logged in as this user; gate into mtrack auth */
      $_SESSION['auth.mtrack'] = $user->userid;
      unset($_SESSION['mtrack.auth.register']);

      header("Location: {$ABSWEB}user.php?user=$userid&edit=1");
      exit;
    }
  }
}

if (!MTrackConfig::get('core', 'allow_self_registration')) {
  mtrack_head("Registration Denied");

  echo <<<HTML
<h1>Registration Denied</h1>

<p>
  Thanks for visiting, but the settings at this site don't allow
  the public to register for access.  If you believe this result
  to be in error, contact the site administrator.
</p>
HTML;
  mtrack_foot();
  exit;
}

mtrack_head('Register');

if (isset($_POST['id'])) {
  $userid = htmlentities($_POST['id'], ENT_QUOTES, 'utf-8');
} else {
  $userid = null;
}

if (isset($_POST['email'])) {
  $email = htmlentities($_POST['email'], ENT_QUOTES, 'utf-8');
} else {
  $email = null;
}

if (isset($_POST['fullname'])) {
  $fullname = htmlentities($_POST['fullname'], ENT_QUOTES, 'utf-8');
} else {
  $fullname = null;
}

if (isset($_SESSION['mtrack.auth.register'])) {
  $reg = $_SESSION['mtrack.auth.register'];

  if (!$userid) {
    $userid = htmlentities($reg['login'], ENT_QUOTES, 'utf-8');
  }
  if (!$email) {
    $email = htmlentities($reg['email'], ENT_QUOTES, 'utf-8');
  }
  if (!$fullname) {
    $fullname = htmlentities($reg['name'], ENT_QUOTES, 'utf-8');
  }
} else {
  $reg = null;
}

if ($message) {
  $message = htmlentities($message, ENT_QUOTES, 'utf-8');
  echo <<<HTML
<div class='alert alert-danger'>
  <a class='close' data-dismiss='alert'>&times;</a>
    $message
</div>
HTML;
}

echo <<<HTML
<h1>Register your local account</h1>

<p>
  Please fill out this short form so that we can complete your
  login.  The User ID and Full Name you select below will be how your name
  appears on the site, and the email address will be used to
  send you notifications.
</p>

<br>

<form method='post'>
<table>
  <tr>
    <td>User ID</td>
    <td><input type='text' name='id' value='$userid'>
      <em>Once selected, it cannot be changed; choose wisely!</em>
    </td>
  </tr>
  <tr>
    <td>Full Name</td>
    <td><input type='text' name='fullname' value='$fullname'></td>
  </tr>
  <tr>
    <td>Email</td>
    <td><input type='text' name='email' value='$email'></td>
  </tr>
HTML;

// We only strictly need to show the password box for users
// that didn't come in via an external authentication mechanism
if (!$reg) {
  echo <<<HTML
  <tr>
    <td>Password</td>
    <td><input type='password' name='password'
          placeholder="Choose a password"><br>
        <input type='password' name='password2'
          placeholder="Confirm that password">
    </td>
  </tr>
HTML;
}
echo "</table>";

echo MTrackCaptcha::emit('');

echo <<<HTML
<button type='submit' class='btn btn-primary'>Save</button>
</form>


HTML;

mtrack_foot();
