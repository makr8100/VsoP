<?php

/**
 * folderLoader.php - load entire contents of folder into array
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-13
 * @package      VsoP
 * @name         folderLoader.php
 * @since        2019-06-24
 * @version      0.13
 *
 * @source { // SAMPLE USAGE:
 *     $jsonDir = $_SERVER['DOCUMENT_ROOT'] . '/../config';
 *     $jsonConfigs = folderLoader($jsonDir);
 * }
 */

function folderLoader($dir) {
    return (is_dir($dir)) ? array_diff(scandir($dir), ['.','..']) : false;
}
