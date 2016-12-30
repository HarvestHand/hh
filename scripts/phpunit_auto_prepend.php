<?php
    include dirname(__FILE__) . '/../Bootstrap.php';
    Bootstrap::$runTests = true;
    putenv('APPLICATION_ENV=development');
    Bootstrap::init();

    PHP_CodeCoverage_Filter::getInstance()->addDirectoryToBlacklist(
        realpath(dirname(__FILE__) . '/../library/Zend')
    );