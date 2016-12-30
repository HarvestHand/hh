# MTrackCommitCheck_RequiresTimeReference

When this plugin is enabled, it prevents commits from taking place if the
commit does not reference a ticket and include time tracking information.

An example of the time reference is shown below, which adds 3.5 hours of effort
to ticket #123:

```
Compensate for the foo issue; it was hard to track down.
refs #123 (spent 3.5)
```

If the commit does not reference a ticket, it will be denied.

The log message may reference multiple tickets; this plugin does not require
that every referenced ticket have an effort associated with it, so long as at
least one ticket has effort tracked, the commit will be allowed.

## configuration

```
[plugins]
MTrackCommitCheck_RequiresTimeReference =
```

