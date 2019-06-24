<?php

/**
 * request.php - AJAX handler
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-06-24
 * @package      VsoP
 * @name         request.php
 * @since        2019-06-24
 * @version      0.11
 */

require_once __DIR__ . '/php/autoLoader.php';

$q = explode('/', $_GET['q']);
$request = isset($q[0]) ? $q[0] : $_REQUEST['request'];
if (empty($request)) die(json_encode(['status' => 400]));

$data = [
    'request' => $request,
    'results' => [],
    'status' => 0
];

if (isset($config['cache'][$request])) {
    $cache = new Cache();
    $data['results'] = $cache->get($request);
    if (!empty($data['results'])) {
        $data['status'] = 200;
        echo json_encode($data);
        exit;
    }
}

$limit = '';

if (isset($_REQUEST['data']['pg'])) {
    $start = ($_REQUEST['data']['pg'] - 1) * 100;
    $limit = "LIMIT $start, 100";
}

//TODO: check type of routing, die if not exists

$sql = str_replace('/*limit*/', $limit, file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/../sql/{$config['mapping'][$request]['table']}.sql"));
$parms = [];

$stmt = $db[0]->prepare($sql); //TODO: get db by key in routing
$stmt->execute($parms);
$data['results'] = $stmt->fetchAll();

if (!empty($data['results'])) {
    if (isset($cache)) {
        $cache->save($data['results'], $request, 300 /*seconds*/);
    }
    $data['status'] = 200;
}

// $data['sql'] = $sql;
$data['cfg'] = $config;

echo json_encode($data);
