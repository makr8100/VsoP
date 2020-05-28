<?php

/**
 * htmlHeader.php - begin page body
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2020-05-28
 * @package      VsoP
 * @name         htmlHeader.php
 * @since        2019-06-24
 * @version      0.17
 * @license      MIT
 */

?><!DOCTYPE html>
<html>
<head>
<title><?php echo $config['title']; ?></title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php

echo "<link rel='stylesheet' href='//use.fontawesome.com/releases/v{$config['includes']['fontawesome']['version']}/css/all.css' integrity='{$config['includes']['fontawesome']['integrity']}' crossorigin='anonymous'>";

/* TODO: obsoleted default CSS - remove? use as fallback?  CSS is outdated
$cssDir = __DIR__ . '/../css';
$cssIncludes = folderLoader($cssDir);
echo "<style>\n\n";
foreach($cssIncludes as $file) {
    echo file_get_contents("$cssDir/$file") . "\n";
}
echo "</style>\n";
*/

$cssCustomDir = $_SERVER['DOCUMENT_ROOT'] . '/css';
if (is_dir($cssCustomDir)) {
    $cssCustomIncludes = folderLoader($cssCustomDir);
    foreach($cssCustomIncludes as $file) {
        echo "<link rel='stylesheet' href='/css/$file'>\n";
    }
}

echo "<script src='//ajax.googleapis.com/ajax/libs/jquery/{$config['includes']['jquery']['version']}/jquery.min.js'></script>\n";
echo "<script src='//unpkg.com/vue'></script>\n";

?>

</head>
