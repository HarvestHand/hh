# MTrackCommitCheck_NoEmptyLogMessage

When this plugin is enabled, it prevents commits from taking place if the
commit has an empty log message.

This restriction will only apply to repositories that have been configured to
use the mtrack pre-commit hook.

## configuration

```
[plugins]
MTrackCommitCheck_NoEmptyLogMessage =
```

