<!DOCTYPE html>
<html>
<head>
<title>Money Thing</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php

echo "<link rel='stylesheet' href='//use.fontawesome.com/releases/v{$config['includes']['fontawesome']['version']}/css/all.css' integrity='{$config['includes']['fontawesome']['integrity']}' crossorigin='anonymous'>";
$cssDir = __DIR__ . '/../css';
$cssIncludes = folderLoader($cssDir);
echo "<style>\n\n";
foreach($cssIncludes as $file) {
    echo file_get_contents("$cssDir/$file") . "\n";
}
echo "</style>\n";
echo "<script src='//ajax.googleapis.com/ajax/libs/jquery/{$config['includes']['jquery']['version']}/jquery.min.js'></script>\n";
echo "<script src='//unpkg.com/vue'></script>\n";

?>

</head>
