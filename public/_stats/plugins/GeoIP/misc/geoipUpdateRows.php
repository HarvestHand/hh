<?php
ini_set("memory_limit", "512M");
error_reporting(E_ALL|E_NOTICE);

define('PIWIK_DOCUMENT_ROOT', dirname(__FILE__)=='/'?'':dirname(__FILE__) .'/../../..');
if(file_exists(PIWIK_DOCUMENT_ROOT . '/bootstrap.php'))
{
	require_once PIWIK_DOCUMENT_ROOT . '/bootstrap.php';
}
if(!defined('PIWIK_USER_PATH'))
{
	define('PIWIK_USER_PATH', PIWIK_DOCUMENT_ROOT);
}
if(!defined('PIWIK_INCLUDE_PATH'))
{
	define('PIWIK_INCLUDE_PATH', PIWIK_DOCUMENT_ROOT);
}

ignore_user_abort(true);
set_time_limit(0);
@date_default_timezone_set('UTC');

require_once PIWIK_INCLUDE_PATH . '/libs/upgradephp/upgrade.php';
require_once PIWIK_INCLUDE_PATH . '/core/testMinimumPhpVersion.php';
require_once PIWIK_INCLUDE_PATH . '/core/Loader.php';

$GLOBALS['PIWIK_TRACKER_DEBUG'] = false;
define('PIWIK_ENABLE_DISPATCH', false);

Piwik_FrontController::getInstance()->init();

if(!class_exists('Piwik_GeoIP'))
{
	echo 'ERROR: It seems the GeoIP is not enabled. Please enable the plugin in Piwik admin page.';
	exit;
}

// when script run via browser, check for Super User
if(!Piwik_Common::isPhpCliMode()) 
{
    try {
    	Piwik::checkUserIsSuperUser();
    } catch(Exception $e) {
    	echo 'ERROR: You must be logged in as Super User to run this script. Please login in Piwik and refresh this page.';
    	exit;
    }
}
$geoIp = new Piwik_GeoIP();
$geoIp->updateExistingVisitsWithGeoIpData();
