<?php if (!defined('APPLICATION')) exit();

// Conversations
$Configuration['Conversations']['Version'] = '2.0.17.8';

// Database
$Configuration['Database']['Name'] = 'farmnik_hh';
$Configuration['Database']['Host'] = 'localhost';
$Configuration['Database']['User'] = 'farmnik_farmnik';
$Configuration['Database']['Password'] = '00sm00sh00';

// EnabledApplications
$Configuration['EnabledApplications']['Vanilla'] = 'vanilla';
$Configuration['EnabledApplications']['Conversations'] = 'conversations';

// EnabledPlugins
$Configuration['EnabledPlugins']['GettingStarted'] = 'GettingStarted';
$Configuration['EnabledPlugins']['HtmLawed'] = 'HtmLawed';
$Configuration['EnabledPlugins']['Emotify'] = 'Emotify';
$Configuration['EnabledPlugins']['cleditor'] = 'cleditor';
$Configuration['EnabledPlugins']['Gravatar'] = 'Gravatar';
$Configuration['EnabledPlugins']['VanillaInThisDiscussion'] = 'VanillaInThisDiscussion';
$Configuration['EnabledPlugins']['Tagging'] = 'Tagging';
$Configuration['EnabledPlugins']['embedvanilla'] = 'embedvanilla';
$Configuration['EnabledPlugins']['AllViewed'] = 'AllViewed';
$Configuration['EnabledPlugins']['ProxyConnect'] = 'ProxyConnect';
$Configuration['EnabledPlugins']['ProxyConnectManual'] = 'ProxyConnectManualPlugin';
$Configuration['EnabledPlugins']['ProxyConnectWordpress'] = 'ProxyConnectWordpressPlugin';

// Garden
$Configuration['Garden']['Title'] = 'HarvestHand Community Forum';
$Configuration['Garden']['Cookie']['Salt'] = 'APNC8D6VP5';
$Configuration['Garden']['Cookie']['Domain'] = '';
$Configuration['Garden']['Version'] = '2.0.17.8';
$Configuration['Garden']['RewriteUrls'] = FALSE;
$Configuration['Garden']['CanProcessImages'] = TRUE;
$Configuration['Garden']['Installed'] = TRUE;
$Configuration['Garden']['Errors']['MasterView'] = 'error.master.php';
$Configuration['Garden']['Registration']['Method'] = 'Captcha';
$Configuration['Garden']['Registration']['CaptchaPrivateKey'] = '';
$Configuration['Garden']['Registration']['CaptchaPublicKey'] = '';
$Configuration['Garden']['Registration']['InviteExpiration'] = '-1 week';
$Configuration['Garden']['Registration']['InviteRoles'] = 'a:3:{i:8;s:1:"0";i:32;s:1:"0";i:16;s:1:"0";}';
$Configuration['Garden']['Email']['SupportName'] = 'HarvestHand';
$Configuration['Garden']['Email']['SupportAddress'] = 'farmnik@farmnik.com';
$Configuration['Garden']['Email']['UseSmtp'] = '1';
$Configuration['Garden']['Email']['SmtpHost'] = 'localhost';
$Configuration['Garden']['Email']['SmtpUser'] = '';
$Configuration['Garden']['Email']['SmtpPassword'] = '';
$Configuration['Garden']['Email']['SmtpPort'] = '25';
$Configuration['Garden']['Email']['SmtpSecurity'] = '';
$Configuration['Garden']['Theme'] = 'EmbedFriendly';
$Configuration['Garden']['Authenticators']['proxy']['Name'] = 'ProxyConnect';
$Configuration['Garden']['Authenticators']['proxy']['CookieName'] = 'VanillaProxy';
$Configuration['Garden']['Authenticator']['EnabledSchemes'] = 'a:2:{i:0;s:8:"password";i:1;s:5:"proxy";}';
$Configuration['Garden']['Authenticator']['DefaultScheme'] = 'proxy';
$Configuration['Garden']['SignIn']['Popup'] = FALSE;
$Configuration['Garden']['InstallationID'] = '338b7608a7b14f05207b7997255d75551e6aa5b3';
$Configuration['Garden']['InstallationSecret'] = 'e870b51a43bd952fbceb90e7392134afc14a2350';
$Configuration['Garden']['Analytics']['LastSentDate'] = '20110312';

// Modules
$Configuration['Modules']['Vanilla']['Content'] = 'a:6:{i:0;s:13:"MessageModule";i:1;s:7:"Notices";i:2;s:21:"NewConversationModule";i:3;s:19:"NewDiscussionModule";i:4;s:7:"Content";i:5;s:3:"Ads";}';
$Configuration['Modules']['Conversations']['Content'] = 'a:6:{i:0;s:13:"MessageModule";i:1;s:7:"Notices";i:2;s:21:"NewConversationModule";i:3;s:19:"NewDiscussionModule";i:4;s:7:"Content";i:5;s:3:"Ads";}';

// Plugin
$Configuration['Plugin']['ProxyConnect']['IntegrationManager'] = 'proxyconnectmanual';

// Plugins
$Configuration['Plugins']['GettingStarted']['Dashboard'] = '1';
$Configuration['Plugins']['GettingStarted']['Registration'] = '1';
$Configuration['Plugins']['GettingStarted']['Categories'] = '1';
$Configuration['Plugins']['GettingStarted']['Plugins'] = '1';
$Configuration['Plugins']['GettingStarted']['Discussion'] = '1';
$Configuration['Plugins']['AllViewed']['Enabled'] = TRUE;
$Configuration['Plugins']['ProxyConnect']['Enabled'] = TRUE;
$Configuration['Plugins']['EmbedVanilla']['RemoteUrl'] = 'http://www.hhint.com/forum';
$Configuration['Plugins']['Tagging']['Enabled'] = TRUE;

// Routes
$Configuration['Routes']['DefaultController'] = 'a:2:{i:0;s:14:"categories/all";i:1;s:8:"Internal";}';

// Vanilla
$Configuration['Vanilla']['Version'] = '2.0.17.8';

// Last edited by Unknown (76.11.69.83)2011-03-14 16:07:13