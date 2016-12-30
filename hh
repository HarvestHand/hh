#!/usr/bin/env php -d memory_limit=128M
<?php
    include 'Bootstrap.php';
    try{
        Bootstrap::init();
    } catch(Exception $e) {
        echo $e->__toString();
        return;
    }

    $console = new HH_Tools_Console();

    exit($console->run());
?>
