# MTrackAuth_HTTP 

By default, mtrack considers every user accessing the application via the web
server as an anonymous user.  Enabling this plugin will cause mtrack to either
recognize the HTTP authentication employed by your web server, or in the case
where the web server does not have authentication enabled, causes mtrack to
initiate HTTP authentication for itself.

## configuration 

The plugin is loaded by adding a line like the following to your [help:ConfigIni config.ini]:

```
[plugins]
MTrackAuth_HTTP = /var/tmp/repos/svn.htgroup, /var/tmp/repos/svn.htpasswd
```

The first parameter is the path to an Apache style group file and the second is
the path to an Apache style password file.

## Basic vs Digest authentication 

If your web server is not configured to perform authentication, mtrack will
initiate it for itself.  You have the option is implementing Basic or Digest
authentication.  Basic is more widely supported but should be used in
conjunction with SSL or other network level security so that the password
cannot be snooped.  Digest authentication does not transmit the password in
clear text so there is no risk of the password being snooped in the same way as
Basic auth.

If you choose to use Basic authentication, it should be noted that mtrack
supports only crypt based password encoding at this time.

### Enabling Digest authentication 

By default, mtrack uses Basic authentication.  To use Digest authentication you
need to create a digest password file instead of the regular password file and
then tell mtrack to use digest: by prefixing the password file path with
`digest:`

```
[plugins]
MTrackAuth_HTTP = /var/tmp/repos/svn.htgroup, digest:/var/tmp/repos/svn.htpasswd
```

# Groups 

On successful authentication, the groups file is read and the groups of the
authenticated user are used to determine what rights the user has.

