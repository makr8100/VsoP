<?php

/**
 * request.php - AJAX handler
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-07-15
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
    $limit = "LIMIT $start, 100"; //TODO: test, delete this
}

if (isset($config['mapping'][$request]['poll'])) {
    $data['poll'] = $config['mapping'][$request]['poll'];
}

//TODO: $config['mapping'][$request][$config['mapping'][$request]['type']] -- yes/no?
if ($config['mapping'][$request]['type'] == 'db') {
    $sql = str_replace('/*limit*/', $limit, file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/../sql/{$config['mapping'][$request]['table']}.sql"));
    $parms = [];

    $stmt = $db[$config['mapping'][$request]['db']]->prepare($sql);
    $stmt->execute($parms);
    $data['results'] = $stmt->fetchAll();
    // $data['sql'] = $sql;
} elseif ($config['mapping'][$request]['type'] == 'file') {
    $parser = new FileParser($config['mapping'][$request]['file'], $config['mapping'][$request]['input']);
    $data['results'] = $parser->output($config['mapping'][$request]['output']);
} else {
    die("ROUTING NOT FOUND!");
}



if (!empty($data['results'])) {
    if (isset($cache)) {
        $cache->save($data['results'], $request, 300 /*seconds*/);
    }
    $data['status'] = 200;
}

$data['cfg'] = $config;

echo json_encode($data);
