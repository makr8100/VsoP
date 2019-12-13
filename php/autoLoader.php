<?php

/**
 * autoLoader.php
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-13
 * @package      VsoP
 * @name         autoLoader.php
 * @since        2019-06-24
 * @version      0.13
 */

$phpDir = __DIR__;
require_once "$phpDir/folderLoader.php";

$jsonDir = $_SERVER['DOCUMENT_ROOT'] . '/../config';
$jsonConfigs = folderLoader($jsonDir);
foreach($jsonConfigs as $jsonConfig) {
    $name = pathinfo($jsonConfig, PATHINFO_FILENAME);
    $config[$name] = json_decode(file_get_contents("$jsonDir/$jsonConfig"), true);
}

$phpIncludes = folderLoader($phpDir);
foreach($phpIncludes as $file) {
    if ($file !== pathinfo(__FILE__, PATHINFO_BASENAME)  && $file !== 'folderLoader.php') {
        require_once "$phpDir/$file";
    }
}
