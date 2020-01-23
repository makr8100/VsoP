<?php

/**
 * index.php - render page
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-13
 * @package      VsoP
 * @name         index.php
 * @since        2019-06-24
 * @version      0.13
 * @license      MIT
 */

require_once __DIR__ . '/php/autoLoader.php';
if (isset($config['timezone'])) date_default_timezone_set($config['timezone']);
$sess = new Session($config, $db, $data);

require_once __DIR__ . '/view/php/htmlHeader.php';
require_once __DIR__ . '/view/php/htmlBody.php';
