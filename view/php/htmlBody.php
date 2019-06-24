<body>

<?php

require_once __DIR__ . '/htmlNavMenu.php';

echo '<div id="container">';

$htmlDir = __DIR__ . '/../html';
$htmlIncludes = folderLoader($htmlDir);
foreach($htmlIncludes as $file) {
    include "$htmlDir/$file";
}

echo '</div>';

$jsDir = __DIR__ . '/../js';
$jsIncludes = folderLoader($jsDir);
foreach($jsIncludes as $file) {
    echo "<script>" . file_get_contents("$jsDir/$file") . "</script>\n";
}

echo '<script src="/vuesetup.js"></script>';

?>

</body></html>
