```trac

'''AT THIS TIME I SUGGEST THAT YOU ONLY ENABLE THIS FOR SITES USING CONTROLLED HTTP AUTHENTICATION, NOT OPENID'''

 = Notes on setting up SSH with mtrack. =

$MTRACK is the path to your mtrack installation.

Create a user account using your system adduser or useradd tool.
If you're on OSX, you have to perform the creation manually (see below).

The username you pick will be included in the repo URLs that your
contributors will use, so pick something appropriate.

Make sure that the primary group of the user matches that of your webserver,
so that both the mtrack web application and the server side SCM tools can
both access the repositories.

I've picked ''code'' as the username, and have set the home directory
to be in my mtrack instance vardir, $MTRACK/var/codehome.

 == OSX ==

On OSX:  manually reate a user.  Make sure that PrimaryGroupID matches your
webserver.

{{{
sudo -s
dscl . -create /Users/code
dscl . -create /Users/code UserShell $MTRACK/bin/codeshell
dscl . -create /Users/code RealName "SSH wrapper for mtrack"
dscl . -create /Users/code UniqueID 600
dscl . -create /Users/code PrimaryGroupID 70
dscl . -create /Users/code NFSHomeDirectory $MTRACK/var/codehome
dscl . -create /Users/code Password '*'
}}}

 == Other Unix ==

Make sure that you set the password to '*' so that regular password based
logins are not allowed for this user.  Also make sure that you set the shell to
$MTRACK/bin/codeshell so that the possible set of commands is restricted to
just the configured SCM tools (hg, git, svn).

 == Next step ==

Depending on your system, you may need to create the home directory.
You will also need to create the .ssh directory.

{{{
mkdir -p $MTRACK/var/codehome/.ssh
chown code:staff $MTRACK/var/codehome
}}}

 == Mercurial Trust ==

The commit hooks won't operate for repos created by the web server when pushed
to over SSH, unless you tell Mercurial to trust the web server user.

You can do this by creating an .hgrc in the home directory of your "code" user.
Here, "_www" is the username of my web server (OS/X).

These are the contents of {{{$MTRACK/var/codehome/.hgrc}}}:

{{{
[trusted]
users = _www
}}}

 == Config File ==

There are two setting that need to be placed in your config.ini file.  Both are
required.  The first is the serverurl, which is the user@host which your users
will use to access your server.  This should be the public name or IP of the
system.  The second is the location of the authorized_keys2 file for your
"code" user.  This must be the full path to the file.

{{{
[repos]
serverurl = "code@example.com"
authorized_keys2 = "/Users/wez/Sites/mtrack/var/codehome/.ssh/authorized_keys2"
}}}

The mtrack repo browser will use the serverurl to display the command that will
be used to check out the code.  For example, the following commands are used to
access the "wez/merc", "wez/git" and "wez/svn" repos, which were created in the
code browser as mercurial, git and subversion repositories, respectively.

{{{
$ hg clone ssh://code@example.com/wez/merc
$ git clone code@example.com:wez/git
$ svn checkout svn+ssh://code@example.com/wez/svn/BRANCHNAME
}}}

 == SSH Keys ==

Each user can supply their own SSH keys by clicking on their username and
then the "Edit my details" button.

With SSH key(s) in the system, the next step is to configure the "code" user to see them.

In your crontab, set up a job to run as the "code" user.  This can run as frequently as you like--the longer the interval between runs, the longer it will take for modified SSH keys to take effect.

{{{
0,15,30,45 * * * * su code -c "php $MTRACK/bin/make-authorized-keys.php" >/dev/null 2>/dev/null
}}}

This script will pull out the key information from the user data in the mtrack
database and generate an authorized_keys2 file that routes access via the
"codeshell" script.

The effect of this is that your users will now be able to access your system
over SSH and will be able to run hg, git or svn in a mode that only allows them
to operate on repositories contained in var/repos.

 == On Security ==

How secure is this?  At the time of writing, this configuration has the
following implications:

 * It creates a new user that accepts public-key authentication only over ssh
 * Any authenticated mtrack user can add their ssh keys to the allowed set
 * Any repos created by mtrack are thus accessible (read/write) to any authenticated mtrack user

'''IMPORTANT''': if you have enabled OpenID login, this means that ANY entity
with an OpenID can add ssh keys and gain read/write access to all of the repos
created by mtrack, but not gain full shell access.

```
