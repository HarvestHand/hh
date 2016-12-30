<?php
define('APPLICATION', 'Vanilla');
define('APPLICATION_VERSION', '2.0.17.8');
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Report and track all errors.
if(defined('DEBUG'))
   error_reporting(E_ALL);
else
   error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

ob_start();

// 1. Define the constants we need to get going.
define('DS', '/');
define('PATH_ROOT', dirname(__FILE__));

$root = realpath('../..');

if (getenv('APPLICATION_ENV') == 'development') {
    $dataDir = $root.DS.'data/vanilla_dev/';
} else {
    $dataDir = $root.DS.'data/vanilla_prod/';
}

define('PATH_CONF', $dataDir.'conf');
define('PATH_LOCAL_CONF', $dataDir.'conf');
define('PATH_APPLICATIONS', PATH_ROOT.DS.'applications');
define('PATH_CACHE', $dataDir.'cache');
define('PATH_LIBRARY', $root.DS.'library/Vanilla');
define('PATH_LIBRARY_CORE', PATH_LIBRARY.DS.'core');
define('PATH_PLUGINS', PATH_ROOT.DS.'plugins');
define('PATH_THEMES', PATH_ROOT.DS.'themes');
define('PATH_UPLOADS', $dataDir.'uploads');

// Delivery type enumerators:
define('DELIVERY_TYPE_ALL', 'ALL'); // Deliver an entire page
define('DELIVERY_TYPE_ASSET', 'ASSET'); // Deliver all content for the requested asset
define('DELIVERY_TYPE_VIEW', 'VIEW'); // Deliver only the view
define('DELIVERY_TYPE_BOOL', 'BOOL'); // Deliver only the success status (or error) of the request
define('DELIVERY_TYPE_NONE', 'NONE'); // Deliver nothing
define('DELIVERY_TYPE_MESSAGE', 'MESSAGE'); // Just deliver messages.
define('DELIVERY_TYPE_DATA', 'DATA'); // Just deliver the data.

// Delivery method enumerators
define('DELIVERY_METHOD_XHTML', 'XHTML');
define('DELIVERY_METHOD_JSON', 'JSON');
define('DELIVERY_METHOD_XML', 'XML');

// Handler enumerators:
define('HANDLER_TYPE_NORMAL', 'NORMAL'); // Standard call to a method on the object.
define('HANDLER_TYPE_EVENT', 'EVENT'); // Call to an event handler.
define('HANDLER_TYPE_OVERRIDE', 'OVERRIDE'); // Call to a method override.
define('HANDLER_TYPE_NEW', 'NEW'); // Call to a new object method.

// Dataset type enumerators:
define('DATASET_TYPE_ARRAY', 'array');
define('DATASET_TYPE_OBJECT', 'object');

// Syndication enumerators:
define('SYNDICATION_NONE', 'NONE');
define('SYNDICATION_RSS', 'RSS');
define('SYNDICATION_ATOM', 'ATOM');

// Environment
define('ENVIRONMENT_PHP_VERSION','5.2.0');

if (!defined('E_USER_DEPRECATED'))
   define('E_USER_DEPRECATED', E_USER_WARNING);
   
define('VANILLA_CONSTANTS', TRUE);

// 2. Include the header.
require_once(PATH_ROOT.DS.'bootstrap.php');

$Dispatcher = Gdn::Dispatcher();

$EnabledApplications = Gdn::Config('EnabledApplications');
$Dispatcher->EnabledApplicationFolders($EnabledApplications);

$Dispatcher->PassProperty('EnabledApplications', $EnabledApplications);

// Process the request.
$Dispatcher->Dispatch();
$Dispatcher->Cleanup();