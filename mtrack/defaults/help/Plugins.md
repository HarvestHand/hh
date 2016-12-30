# Plugins 

mtrack has a simple plugin system that allows a plugin class to be loaded a configured using the [help:ConfigIni#plugins configuration file].

mtrack ships with the following plugins:

| Name | Purpose |
| ---- | ------- |
| [help:plugin/AuthHTTP MTrackAuth_HTTP] | Use HTTP authentication |
| [help:plugin/CommitCheckNoEmpty MTrackCommitCheck_NoEmptyLogMessage] | Prevent commits with no log message |
| [help:plugin/CommitCheckTimeRef MTrackCommitCheck_RequiresTimeReference] | Prevent commits that don't include time tracking information |
| [help:plugin/Recaptcha MTrackCaptcha_Recaptcha] | Require recaptcha for submissions |
| [help:plugin/OpenID MTrackAuth_OpenID] | Use OpenID for public authenticated access control |

