# config.ini

The configuration file defines an mtrack instance.  mtrack will look for this
file by first inspecting the `$MTRACK_CONFIG_FILE` environmental variable.
If it is not set it will default to looking for `config.ini` in the mtrack
source directory.

`config.ini` is parsed using the following rule:

 * `[name]` indicates that the following values belong to the ''name'' section.  You may switch section multiple times in the file if you wish.
 * Lines beginning with a semicolon `;` character are comments and are ignored by the parser
 * values are specified by lines of the form `name = value`.  The value belongs to the previously indicated section.
 * Unquoted tokens on the right hand size of an equals sign are replaced by the value of a matching PHP constant
 * Values of the form `${name`} are substituted with the value of the corresponding PHP configuration directive, or if none is found, the corresponding environmental variable value
 * Values of the form `@{section:myname`} are substituted with the value of the option defined in this configuration file.  For example, the ''myname'' value in the ''section'' section)

# [core]

The following options are defined for the `core` section.

vardir
:  The location of the `var` directory, which holds all of the mtrack
	runtime state

dblocation
: 	Where the mtrack sqlite database can be found.  This is usually defined
	to be `"@{core:vardir}/mtrac.db"` which means that it lives in the
	`var` directory.

searchdb
:	Where the mtrack full-text search database can be found.  This is usually
	defined to be `"@{core:vardir}/search.db"` which means that it lives in
	the `var` directory.

projectname
: 	The name of the mtrack instance.  This is displayed in the top left of
	the navigation area if the ''projectlogo'' is not defined.

timezone
: 	The default timezone to use when rendering dates.

projectlogo
: 	Specifies an URL that will be used in an image tag displayed in the top
	left of the navigation area.

weburl
: 	Specifies the canonical URL (including trailing slash) for this mtrack
	instance.  This is used when generating links in notification email,
	but will also be used when generating links in the web application.

default.repo
: 	Specifies the shortname of the repo to use when generating changeset
	links that don't otherwise specify one.  You only need this when you
	have multiple repos.  mtrack will default to the first repo.

default_email_domain
: 	Domain name to use when inferring the email address for users that do
	not have an email address configured in the userinfo table.

includes
: 	Comma separated list of files to be included.  The intended use is for
	loading plugins without modifying the mtrack code.

# [ticket]

default.classification
: 	When creating a new ticket, specifies which classification to pre-select

default.severity
: 	When creating a new ticket, specifies which severity to pre-select

default.priority
: 	When creating a new ticket, specifies which priority to pre-select

# [user_class_roles]

This section allows you to define classes of users.  Unauthenticated users are
placed in the ''anonymous'' user class.  Authenticated users are placed in the
''authenticated'' user class.

The names in this section define user classes, and their corresponding values
define a list of rights that are granted to users that are in that class.

The default configuration for this section is reproduced below:

```ini
; Defines some basic, reasonable, permission sets for 3 classes of user.
; These are used in addition to whatever is selected by auth plugins
[user_class_roles]
anonymous = ReportViewer,BrowserViewer,WikiViewer,TimelineViewer,RoadmapViewer,TicketViewer
authenticated = ReportViewer,BrowserViewer,WikiCreator,TimelineViewer,RoadmapViewer,TicketCreator
admin = ReportCreator,BrowserCreator,WikiCreator,TimelineViewer,RoadmapCreator,TicketCreator,EnumerationCreator,ComponentCreator,ProjectCreator
```

This give anonymous users read-only access to the major areas of mtrack.
Authenticated users are given write access to the major areas.

This also defines a class called ''admin'' that has full access to all areas of mtrack.

## [user_classes]

This names in this section correspond to user names.  The value is the user class that is explicitly assigned to that user.

For example:

```ini
[user_classes]
wez = admin
```

places the ''wez'' user in to the ''admin'' user class.  When combined with the
above [help:ConfigIni#user_class_roles user_class_roles] causes ''wez'' to
belong to each of the groups associated with the ''admin'' class and thus have
full access to the system.

Configuring user_classes is not necessary if you are using an authentication
scheme where you control which groups are assigned to the users.

## [tools]

The tools section controls where mtrack finds the various command line tools
that it may need to run.

The names in this section are the tool names and the value is the path to the
tool itself.  [help:bin/Init bin/init.php] will try to populate these
automatically when it runs, so you will not usually need to make changes here
unless you have an alternate version of a given that is not in a standard
location.

## \[nav:mainnav]

If you want to turn off, rename or add navigation links you can do so by
making changes to this section.

The names in this section correspond to the URL of one of the navigation links
and the value is the displayed text.

To remove the wiki link from navigation:

```ini
[nav:mainnav]
/wiki.php =
```

To rename the wiki link:

```ini
[nav:mainnav]
/wiki.php = Awesome Wiki
```

To add a new navigation item:

```ini
[nav:mainnav]
http://bitbucket.org/wez/mtrack/ = mtrack home
```

## [plugins]

mtrack has a simple plugin system.  After a plugin has been installed, it needs
to be configured by adding an entry to this section of the configuration file.

The names in this section correspond to the names of the plugin classes.
The value is interpreted as a comma separated list of strings that will
be passed as arguments to the constructor of that class.

For example:

```ini
[plugins]
MTrackAuth_HTTP = /Users/wez/Sites/svn.htgroup, /Users/wez/Sites/svn.htpasswd
```

this will cause mtrack to run the equivalent of the following php code:

```php
<?php
$obj = new MTrackAuth_HTTP(
	'/Users/wez/Sites/svn.htgroup',
	'/Users/wez/Sites/svn.htpasswd');
```

For more information about plugins, see [help:Plugins].
