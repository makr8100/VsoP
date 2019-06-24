<?php

/**
 * db.php - manage PDO data sources
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-06-24
 * @package      VsoP
 * @name         db.php
 * @since        2019-06-24
 * @version      0.11
 */

$db = [];

foreach ($config['db'] as $dbc) {
    $pdoString = "{$dbc['engine']}:host={$dbc['dsn']};dbname={$dbc['dbn']}";
    if (isset($dbc['char'])) $pdoString .= ";charset={$dbc['char']}";
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
    ];

    try {
         $db[] = new PDO($pdoString, $dbc['usr'], $dbc['pwd'], $opts);
    } catch (\PDOException $e) {
         throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

    unset($pdoString);
}
unset($config['db']);
