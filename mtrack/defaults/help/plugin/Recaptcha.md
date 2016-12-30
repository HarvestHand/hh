# MTrackCaptcha_Recaptcha 

When used in a public facing environment, in order to reduce automated spam,
you may want to enable a CAPTCHA.  Mtrack has an API that allows different
captcha implementations to be used, and ships with support for the reCaptcha
service.

## configuration 

The plugin is loaded by adding a line like the following to your [help:ConfigIni config.ini]:

```
[plugins]
MTrackCaptcha_Recaptcha = publickey, privatekey, userclass
```

The first parameter is your publickey key and the second is your privatekey.
You can obtain keys from [https://www.google.com/recaptcha/admin/create recaptcha.net].

The userclass parameter indicates which classes (separated by a pipe character)
of user should have the captcha applied.  The default value is
`anonymous|authenticated` which means that everyone except for admin users
will be presented with a captcha.
