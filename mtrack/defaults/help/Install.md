
# Installing mtrack 

## Pre-requisites 

 * A unix style operating system, such as Linux, Solaris, OS/X, FreeBSD etc.
 * PHP 5.2, both the standalone CLI executable and Web Server (such as Apache) versions
   * PHP must have PDO and pdo_sqlite support
   * fileinfo or mime_magic support is recommended
 * The `diff` and `diff3` command line tools
 * Subversion command line tools (`svn` and `svnlook`) for Subversion repo support
 * Mercurial command line tools (`hg`) for Mercurial repo support
   (Minimum version: 1.5.2)
 * Access to the *cron* mechanism or equivalent on your system to schedule background tasks
 * The `sendmail` command line tool for change notification emails

## Quick Install 

It is recommended that you read this guide in full before installing, but if
you're impatient and want to see it running very quickly, you can follow these
steps.  They are intentionally terse; if you want more detail, read this guide
in full!

Note that if you want to import data from Trac, you will need to start over
with the initialization.

You should treat the quick install as a way to make a quick assessment about
mtrack before beginning your migration in earnest.

```
% cd $MTRACK
% php bin/init.php
```

 * configure your webserver so that the $MTRACK/web dir is accessible
 * turn off magic_quotes_gpc

You can now visit mtrack as an anonymous user and continue reading this
document by navigating to help.php/Install.

To do anything interesting, you will need to configure authentication.

## Background 

An mtrack installation is defined in terms of the mtrack configuration file
`config.ini`, which contains system settings, and the application files,
which contain the program logic and that can be shared between between
instances so that multiple mtrack projects don't need to have their own copies
of the application files.

mtrack uses the environment variable `MTRACK_CONFIG_FILE` to locate the
`config.ini`, so sharing the same mtrack codebase across multiple projects
is just a matter of ensuring that the environment is correctly set for each
project.

## Installation 

Decide where you would like the mtrack application files to reside on your
filesystem and put them there.  mtrack itself does not place any restrictions
on location, although the recommendation is that you do not place it in a path
where any of the parent directories have spaces in their names.

The `web` directory of the sources is intended to be the only portion
served via your web server, and it is recommended that you configure your
system such that the other directories are prevented from being served as a
security precaution.

You must also decide where you want to store the state for your mtrack project.
State includes the mtrack database (which holds tickets, wiki pages and more)
as well as attachment files and a Lucene search index.  All of these things are
encapsulated in a `var` directory.

The `var` directory *must not* be served via your web server.

## A note on Wiki 

mtrack stores wiki pages in a repository.  By default, it will create this repo
in the `var` directory.  If you would like to locate the wiki repo
elsewhere, perhaps because you have want to export that and allow wiki edits to
be made via conventional editing tools and checked back in, then you may use
the `--repo` option to inform mtrack where it can find the existing wiki repository (you need to create it and ensure that it is accessible).


## Performing the Installation 

From this point onwards, we use `$MTRACK` to denote the root of the mtrack
source files, and `$VARDIR` to denote the location that you selected to
hold the state for your mtrack project instance.  Each instance *'must*' have
its own distinct `$VARDIR`.

Each of the steps below cause `config.ini` to be created in the
`$MTRACK` directory; you may change this by usin the `--config-file`
option.

### Initializing 

To initialize a fresh environment that is not related to any source
repositories:

```
% cd $MTRACK
% php bin/init.php --vardir $VARDIR
```

However, it is quite likely that you have a source repository or two; if so,
you will probably want to configure mtrack to see them.  You should also define
a *project* and associate it with the repo; this is used later for change
notifications.  You should initialize your instance using the following
invocation instead:

```
% cd $MTRACK
% php bin/init.php --vardir $VARDIR \
   --repo $REPONAME svn /path/to/repo \
   --link $PROJNAME $REPONAME /
```

 * $REPONAME will show up as the top level name in the source browser
 * $PROJNAME will show up in the subject line of notification email
 * The `/` in the `--link` line causes all changes in $REPONAME to be recognized as happening within the $PROJNAME project.  More advanced rules are possible, such as allowing multiple projects to be contained with the same repo, but are not explained here.

If you are migrating from Trac, then you will want to associate your
repository and tell mtrack to import your Trac data:

```
% cd $MTRACK
% php bin/init.php --vardir $VARDIR \
   --trac $PROJNAME /path/to/trac/environment/dir \
   --repo $REPONAME svn /path/to/repo \
   --link $PROJNAME $REPONAME /
```

If your Trac instance contains a lot of data, you might want to use the
`--disable-index` option to improve the import speed.  This turns off
incremental index updates during the import and trades import speed now for
indexing speed in the indexing background job that runs later.

```comment
For Windows users - there is a setup.bat file in the /bin directory you may use
Right clik the setup.bat file and choose edit
Change the PHP_BIN location to the absolute path to your PHP directory.
Change the PROJ_NAM, REPO_NAME and REPO_PATH to the desired locations
and values.  You may also need to change REPO_TYPE if you are not setting up an
svn environment.
Save the changes, then exit and double click on setup.bat to initialize your environment

Currently the windows batch file does not support the --trac or --vardir arguments
If you are comfortable in a command line environment, you may open cmd.exe, cd
to the location of setup.bat, and pass the additional arguments you desire.
```

### Set the ownership on $VARDIR 

Ensure that the web server process can access the mtrack state:

```
# chown -R nobody:nobody $VARDIR
```

 * `nobody` must be changed to match the user account under which the web server process runs

```comment
 * In Windows environments, make sure the user your webserver is running as can write to $VARDIR
   This is normally the SYSTEM user for apache2 installations and IUSR_computername for IIS installations
   See php.net installation instructions for your version of IIS for more information
```

For a reference on the init script and its parameters, consult [help:bin/Init].

### Tool configuration 

Once initialized, open `config.ini` in your text editor and fill out the
`[tools]` section so that mtrack knows the full path to the `svn`,
`svnlook` and `php` command line utilities.  These will be guessed by
the initialization script based on what it can find your `$PATH`.

~~~comment
Windows users can find the appropriate diff tools at
http://gnuwin32.sourceforge.net/packages/diffutils.htm Make sure to use
absolute paths to the appropriate tools and use quotes around the values.  Note
that if you have tortoisesvn installed you will not have the command line svn
tools required, you'll need to install the command lines tools as well.

For example, on a 64 bit system your paths will look similiar to this
```
hg = "C:\Program Files\TortoiseHg\hg.exe"
; svn = /opt/msys/3rdParty/bin/svn
; svnlook = /opt/msys/3rdParty/bin/svnlook
php = "C:\Program Files (x86)\PHP\php.exe"
diff3 = "C:\Program Files (x86)\diff\diff3.exe"
diff = "C:\Program Files (x86)\diff\dif.exe"
```
~~~

### Cron configuration 

mtrack defers content indexing and email notifications so that they can be
intelligently handled in batches and not intrude on the web application
performance.

Configure a cron entry to run these batch processes every 10 minutes, using the following as a template:

```
0,10,20,30,40,50 * * * * nice su nobody -c "php $MTRACK/bin/update-search-index.php ; php $MTRACK/bin/send-notifications.php" >/dev/null 2>/dev/null
```

 * `nobody` must be changed to match the user account under which the web server process runs

You are free to change the interval to anything you like (although the system
minimum is 1 minute); longer intervals allow more ticket changes to be
collapsed into an email at the expense of a larger perceived lag between the
time the event happens and the time the email is sent.

If you imported a large trac instance, the initial run of
`update-search-index.php` can take some time to run (and can tax the CPU
while it is running).  You need not worry about this; it is normal.  Both
`update-search-index.php` and `send-notifications.php` are intelligent
enough to only allow 1 instance to run concurrently, so even if there is a
backlog of work for them to process, they won't trip over each other or other
invocations launched from cron.

### Subversion commit-hook configuration 

mtrack works best when integrated with your SCM.  There is a pre-commit hook
that can be used to enforce commit policies (such as proper formatting of
commit messages, or proper syntax in changed source files), and a post-commit
hook that can be used to apply commit messages as comments to related tickets.

Both the pre- and post-commit hooks are implemented by
`bin/svn-commit-hook`.  To enable it, arrange for your pre-commit hook to
invoke it:

If you do not have an existing hook, then create the following shell script in
the `hooks` directory of your subversion repository.  If you have an
existing hook, then adjust it to invoke the mtrack commit hook in addition to
the other actions it takes:

```
#!/bin/sh
php $MTRACK/bin/svn-commit-hook pre $1 $2 $MTRACK/config.ini
```

Then make sure it is executable:

```
# chmod a+rx hooks/pre-commit
```

The post-commit hook is similar:

```
#!/bin/sh
php $MTRACK/bin/svn-commit-hook post $1 $2 $MTRACK/config.ini
```

Then make sure it is executable:

```
# chmod a+rx hooks/post-commit
```

### Mercurial Commit Hook 

Add this to the .hg/hgrc in the Mercurial repos:

```
[hooks]
changegroup.mtrack = php $MTRACK/bin/hg-commit-hook changegroup $MTRACK/config.ini
commit.mtrack = php $MTRACK/bin/hg-commit-hook commit $MTRACK/config.ini
pretxncommit.mtrack = php $MTRACK/bin/hg-commit-hook pretxncommit $MTRACK/config.ini
pretxnchangegroup.mtrack = php $MTRACK/bin/hg-commit-hook pretxnchangegroup $MTRACK/config.ini
```

### Notification Email Configuration 

mtrack notifies users of changes based on the project associated with the
source code that was changed.  During initialization, we used the `--link`
argument to define a relationship between a location within a repo and a
project.

To enable email notification, we now need to associate an email address with a
project.  This is done via the Administration section; you can edit the email
address associated with the project from there.

Edit `config.ini` and set the *'weburl*' to match the URL you are going
to use for the web application.  It is important to include the trailing slash
in the URL that you put into the configuration file.  This value is used to
construct clickable links in notification emails.

### Authentication Considerations 

mtrack uses plugins to control authentication and authorization.  By default,
it will respect the user identity of the command line user, but all web
accesses will be mapped to an *anonymous* user account that has read-only
access rights.

The recommended authentication approach is to configure your web server to
apply HTTP authentication to the mtrack application to secure it.

mtrack ships with an `MTrackAuth_HTTP` plugin that will recognize when the
web server has authenticated the user, and if not, will initiate Basic or
Digest authentication itself.

The default `config.ini` file leaves the HTTP auth module commented out;
you should uncomment it and inform it where it can find apache style group and
password files.  If the password file contains digest authentication
credentials, the filename must be prefixed with `digest:`.

```
[plugins]
MTrackAuth_HTTP = /path/to/htgroup, /path/to/htpasswd
; for digest:
;MTrackAuth_HTTP = /path/to/htgroup, digest:/path/to/htpasswd
```

 * At this time, mtrack does not ship with a mechanism to allow both unauthenticated and authenticated access (but it could be implemented pretty easily)

More information on authentication can be found in [help:plugin/AuthHTTP].

## Web server Configuration 

 * Configure your web server such that your preferred URL maps to the `$MTRACK/web` directory
 * Ensure that `magic_quotes_gpc` is set to `Off` in your PHP configuration.

A snippet from my httpd.conf:

```
# mtrack prototype
<Location /mtrack/eng>
	AuthType Basic
	AuthName "Access for mtrack"
	AuthUserFile "/path/to/htpasswd"
	AuthGroupFile "/path/to/htgroup"
	require group developers
</Location>
<Directory /home/wez/mtrack/web>
	Options Indexes FollowSymLinks
	AllowOverride None
	Order allow,deny
	Allow from all

	DirectoryIndex index.php
	php_value magic_quotes_gpc Off
</Directory>
Alias /mtrack/eng /home/wez/mtrack/web
```

You may want to consider something like this for multi-instance hosting, if your "foo" project has its vardir at `/data/foo` and your "bar" project has its vardir at `/data/bar`:

```
Alias /mtrack/foo /home/wez/mtrack/web
SetEnvIf Request_URI "^/mtrack/foo(/|$)" "MTRACK_CONFIG_FILE=/data/foo/config.ini"
Alias /mtrack/bar /home/wez/mtrack/web
SetEnvIf Request_URI "^/mtrack/bar(/|$)" "MTRACK_CONFIG_FILE=/data/bar/config.ini"
```

## Done 

Your basic configuration is now complete.  There are a number of other settings
in `config.ini` that can be adjusted (See [help:ConfigIni] for details),
but following the steps above should be sufficient to get you up and running.

