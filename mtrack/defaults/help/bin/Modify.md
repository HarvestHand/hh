# bin/modify.php

This script can be used to modify an existing mtrack instance.  You should try
to use the administration interface where possible.

## Synopsis

```bash
% cd $MTRACK
% php bin/modify.php ...parameters...
```

## Parameters

### --repo {name} {type} {repopath}

Adds a source repository.  This works in the same way as the
[help:bin/Init#repo repo option for bin/init.php].

### --link {project} {repo} {location}

Defines a link between the project identified by short name {project} and the
repository named {name} by the source location identified by the regex
{location}.

This works in the same way as the [help:bin/Init#link link option for bin/init.php].

### --trac {project} {tracenv}

Imports data from a the trac environment on the local filesystem at {tracenv}, and associate it with the project named {project}.

This works in the same way as the
[help:bin/Init#trac trac option for bin/init.php],
''except that the trac imports will always be treated as
secondary instances''.  This means that the tickets and wiki pages will all be
prefixed with the project name.

### --config-file {filename}

Where to find the pre-existing configuration file.

If not specified, defaults to `config.ini` in the mtrack directory.

