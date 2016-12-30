# MTrackAuth_OpenID 

When used in a public facing environment, where you desire to have external
users contributing to your project in terms of bug reports or wiki content, you
may want to enable OpenID as an authentication mechanism.

This allows users to access your site without having to request
credentials and remember passwords and such for your site.

The OpenID implementation in mtrack classes users as anonymous until they
either explicitly log in or arrive at a page that throws a privilege exception.
If a privilege exception is raised while the user is anonymous, they will be
redirected to the OpenID login page to authenticate.

mtrack will not automatically create a local user record for OpenID users as
they log in.  This is for security purposes; even though the authentication is
outsourced, the mechanism does not prevent the user from sending erroneous
information as part of the sign in, and this could potentially be used to
hijack a pre-existing local user record.

The impact of this is that an OpenID user can comment and contribute to the
wiki (assuming that permissions are set accordingly; by default the OpenID user
will be classified as an authenticated user class and thus have rights to the
wiki and tickets), and their contributions will be attributed to their OpenID
identity URL.

To establish a local user identity for the OpenID user, an admin user
can edit the OpenID user by clicking on their name in the UI.  When the
edits are saved, the user will become a local user.

If the OpenID user is also a contributor to the code via the SCM, the admin
user can add an alias for that user.  For example, the user "wez" is a code
contributor and also has the OpenID identity URL "http://netevil.org/".  The
recommended approach for configuring mtrack is to edit the "wez" user details
and add an alias for "http://netevil.org/".  Now, when "wez" logs in via OpenID
he will be recognized as "wez" throughout the system, rather then
"http://netevil.org/" because "wez" is the canonical identifier for that user.

Because OpenID is not a guarantee that the user is trustworthy, you may
also want to consider [help:plugin/Recaptcha enabling captcha support].

## configuration 

The plugin is loaded by adding a line like the following to your [help:ConfigIni config.ini]:

```
[plugins]
MTrackAuth_OpenID 
```

You may also assign user_classes to OpenID URLs; for instance, the following
configuration gives Wez Furlong admin rights to your mtrack instance:

```
[user_classes]
http://netevil.org/ = admin
```

Note that the trailing slash character in the URL is significant.

