<?php if (!defined('APPLICATION')) exit();

// Conversations
$Configuration['Conversations']['Version'] = '2.0.17.8';

// Database
$Configuration['Database']['Name'] = 'farmnik_hh';
$Configuration['Database']['Host'] = 'localhost';
$Configuration['Database']['User'] = 'harvesthand';
$Configuration['Database']['Password'] = 'harvesthand';

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
$Configuration['Garden']['Email']['SmtpHost'] = 'smtp.eastlink.ca';
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
$Configuration['Garden']['InstallationID'] = '856C-3914CBCD-7C73D474';
$Configuration['Garden']['InstallationSecret'] = 'ca0bc11eb22d0bd9429289e0597be61c26e8e612';
$Configuration['Garden']['Analytics']['LastSentDate'] = '20110928';

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

// Routes
$Configuration['Routes']['DefaultController'] = 'a:2:{i:0;s:14:"categories/all";i:1;s:8:"Internal";}';

// Vanilla
$Configuration['Vanilla']['Version'] = '2.0.17.8';

// Last edited by Unknown (127.0.0.1)2011-09-28 14:53:38