<?php

/**
 * db.php - manage PDO data sources
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-13
 * @package      VsoP
 * @name         db.php
 * @since        2019-06-24
 * @version      0.13
 * @license      MIT
 */

//TODO: db connect only on use - don't connect to a data source unless we have a reason

$db = [];

foreach ($config['db'] as $dbc) {
    if (isset($dbc['catalog'])) $pdoString = "{$dbc['engine']}:{$dbc['catalog']}";
    else $pdoString = "{$dbc['engine']}:host={$dbc['dsn']};dbname={$dbc['dbn']}";
    if (isset($dbc['char'])) $pdoString .= ";charset={$dbc['char']}";
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
    ];

    try {
         $db[] = (isset($pdoString) && isset($dbc['usr']) && isset($dbc['pwd'])) ? new PDO($pdoString, $dbc['usr'], $dbc['pwd'], $opts) : new PDO($pdoString, null, null, $opts);
    } catch (\PDOException $e) {
         throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

    unset($pdoString);
}

unset($config['db']);
