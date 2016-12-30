# bin/init.php

This script is used to initialize a new mtrack instance.  If you want to modify
an existing mtrack instance, you should try to use the administration
interface, or use [help:bin/Modify bin/modify.php] instead as ''init.php'' will
exit when you attempt to use it on an already initialized mtrack instance.

## Synopsis

```
% cd $MTRACK
% php bin/init.php ...parameters...
```

## Parameters

### --disable-index ### {#disable-index}

Disables full-text index generation during setup (affects Trac import).

### --repo {name} {type} {repopath} ### {#repo}

Defines a source repository named `{name}` of type `{type}` that can be found on
the local filesystem at `{repopath}`.

Supported repository types are *hg* for Mercurial, *git* for Git and *svn* for Subversion.

You will typically also want to use `--link` to associate the repository to
a project.

### --link {project} {repo} {location} ### {#link}

Defines a link between the project identified by short name `{project}` and the
repository named `{name}` by the source location identified by the regex
`{location}`.

To associate the entire repository with a project you would use a simple `/` as the `{location}` parameter:

```
% php bin/init.php --repo myrepo svn /path/to/repo \
	--link myproject myrepo /
```

To have changes made under "trunk/docs" be associated with the doc project, and all others be associated with myproject:

```
% php bin/init.php --repo myrepo svn /path/to/repo \
	--link myproject myrepo / \
	--link doc myrepo /trunk/docs/
```

### --trac {project} {tracenv} ### {#trac}

Imports data from a the trac environment on the local filesystem at
`{tracenv}`, and associate it with the project named `{project}`.

`{tracenv}` is the same environment path that you would use when running the
trac admin command line utility.

mtrack can only be used to import SQLite based Trac instances at this time.

You may import multiple trac environments; the first one will be imported in
as-is, but subsequent trac environments will be imported with some changes to
avoid the possibility of collisions between ticket numbers and wiki pages.

Subsequent trac imports will prefix ticket numbers with the project name, so
instead of `#123`, if you import it to a project named `mc`, the ticket
will be `#mc123`.  Similarly, the wiki pages will be adjusted to live under
a directory named after the project, so you would end up with
`mc/WikiStart` for the main wiki page from that trac instance.

The import will skip Trac wiki pages that contain Trac specific docs (even if
you modified them in your trac instance); mtrack prefers to keep its own
documentation out of your wiki history so that it doesn't clutter up what is
important to you, and also updates automatically when you update mtrack.

### --vardir {dir} ### {#vardir}

Where to store the database, attachments and search engine state.

If not specified, defaults to a directory named `var` in the mtrack
directory.

This location, whether specified explicitly or not, will be created if it does
not already exist.

### --config-file {filename} ### {#config-file}

Where to create the configuration file.

If not specified, defaults to `config.ini` in the mtrack directory.

### --author-alias {filename} ### {#author-alias}

where to find an authors file that maps usernames.  This is used to initialize
the canonicalizations used by the system.  The format is a file of the form:

```
sourcename=canonicalname
```

The import will replace all instances of sourcename with canonicalname in the
history, and will record the mapping so that future items will respect it.

You do not need to use this option if you have only a single repository, or if
you have never changed usernames for any of your contributors.

### --author-info {filename} ### {#author-info}

Where to find a file that will be used to initialize the userinfo table. The
format is:

```
canonid,fullname,email,active,timezone
```

where canonid is the canonical username.

for example:

```
wez,Wez Furlong,wez.spam@netevil.org,1,EST
```

The **active** flag indicates whether the account is eligible to be assigned as
a responsible user when changing tickets.

