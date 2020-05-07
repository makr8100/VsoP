<body>

<?php

/**
 * htmlBody.php - recurses navigation tree structure and creates HTML for nav bar
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-13
 * @package      VsoP
 * @name         htmlBody.php
 * @since        2019-06-24
 * @version      0.13
 * @license      MIT
 */

require_once __DIR__ . '/htmlNavMenu.php';

// echo '<div id="loadingScreen" class="pageCenter"></div><div id="emptyRequest" class="fullCenter"><i class="fas fa-question-circle"></i><div>Welcome!  Please make a selection.</div></div><div id="container">';
echo '<div id="loadingScreen" class="pageCenter"></div><div id="emptyRequest"><div>' . recurseMenu($config, $sess, $config['navigation'], true) . '</div></div><div id="container">';

$htmlDir = $_SERVER['DOCUMENT_ROOT'] . '/../vueelements';
$htmlIncludes = folderLoader($htmlDir);

if (!empty($htmlIncludes)) {
    foreach($htmlIncludes as $file) {
        include "$htmlDir/$file";
    }
}
echo '</div>';

$jsDir = __DIR__ . '/../js';
$jsIncludes = folderLoader($jsDir);
foreach($jsIncludes as $file) {
    echo "<script>" . file_get_contents("$jsDir/$file") . "</script>\n";
}

echo '<script src="/vuesetup.js"></script>';

?>

<div id="notificationContainer">
    <div class="notification" v-for="(m, idx) in messages" v-bind:class="m.type">
        <button class="close" v-on:click="closeMessage(idx)"><i class="far fa-window-close"></i></button>
        <div v-if="m.proper && m.proper !== ''" class="title">{{ m.proper }}</div>
        <div v-else-if="m.request && m.request !== ''" class="title">{{ m.request }}</div>
        <div>{{ m.message }}</div>
        <div class="timestamp">{{ m.timestamp }}</div>
    </div>
    <div v-if="messages.length" id="messageCount" v-on:click="toggleMessages()">{{ messages.length }} Message<span v-if="messages.length > 1">s</span></div>
</div>

</body></html>
