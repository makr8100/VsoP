<?php

/**
 * folderLoader.php - load entire contents of folder into array
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2020-01-17
 * @package      VsoP
 * @name         folderLoader.php
 * @since        2019-06-24
 * @version      0.15
 *
 * @source { // SAMPLE USAGE:
 *     $jsonDir = $_SERVER['DOCUMENT_ROOT'] . '/../config';
 *     $jsonConfigs = folderLoader($jsonDir);
 * }
 */

function folderLoader($dir) {
    $results = (is_dir($dir)) ? array_diff(scandir($dir), ['.','..']) : false;
    if (is_array($results)) {
        foreach ($results as $k => &$result) {
            if (!is_array($result)) {
                if (is_dir("$dir/$result")) {
                    $results[$result] = folderLoader("$dir/$result");
                    unset($results[$k]);
                }
            }
        }
    }
    return $results;
}
