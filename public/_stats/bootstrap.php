<?php
    if (getenv('APPLICATION_ENV') == 'development') {
        define('PIWIK_USER_PATH', realpath('../../data/piwik_dev'));
    } else {
        define('PIWIK_USER_PATH', realpath('../../data/piwik_prod'));
    }
    //define('PIWIK_INCLUDE_PATH', dirname(__FILE__));
    define('PIWIK_ENABLE_SESSION_START', 0);
    define('PIWIK_DISPLAY_ERRORS', 0);