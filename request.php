<?php

/**
 * request.php - AJAX handler
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-13
 * @package      VsoP
 * @name         request.php
 * @since        2019-06-24
 * @version      0.13
 * @license      MIT
 */

$data = [
    'results' => [],
    'status' => 0,
    'messages' => [],
    'sql' => []
];

require_once __DIR__ . '/php/autoLoader.php';
if (isset($config['timezone'])) date_default_timezone_set($config['timezone']);
$sess = new Session();

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

if ($action === 'login') {
    $sess->doLogin($_REQUEST['usr'], $_REQUEST['pwd']);
} else if ($action === 'logout') {
    $sess->doLogout();
}

if (!empty($sess->user)) {
    $data['user'] = $sess->user;
}

if (isset($_REQUEST['data']['view'])) {
    $request = $_REQUEST['data']['view'];
    $data['request'] = &$request;
    if (empty($request)) {
        $data['status'] = 400;
        $data['messages'][] = [ 'type' => 'error', 'message' => 'Empty Request!' ];
    }
    if (!empty($config['mapping'][$request]['proper'])) $data['proper'] = $config['mapping'][$request]['proper'];

    switch ($action) {
        case 'view':    $permission = 'r';  break;
        case 'add':     $permission = 'a';  break;
        case 'delete':  $permission = 'd';  break;
        default:        $permission = 'w';  break;
    }

    if (!$sess->authCheck($request, $permission)) {
        $data['status'] = 403;
        $data['messages'][] = [ 'type' => 'error', 'message' => 'Not Authorized!' ];
    } else {
        if (isset($config['cache'][$request])) {
            $cache = new Cache();
            $data['results'] = $cache->get($request);
            if (!empty($data['results'])) {
                $data['status'] = 200;
            }
        }

        if (isset($config['mapping'][$request]['poll'])) {
            $data['poll'] = $config['mapping'][$request]['poll'];
        }

        $pg = isset($_REQUEST['data']['pg']) ? $_REQUEST['data']['pg'] : 1;
        $pp = null;
        if (isset($_REQUEST['data']['pp'])) $pp = intval($_REQUEST['data']['pp']);
        else if (isset($config['mapping'][$request]['limit'])) $pp = $config['mapping'][$request]['limit'];

        $data['results'] = (empty($data['status'])) ? recurseTables($config['mapping'][$request], $request, null, $pg, $pp) : false;

        if (!empty($data['results'])) {
            if (isset($cache)) {
                $cache->save($data['results'], $request, $config['cache'][$request]['timeout']);
            }
            $data['status'] = 200;
        }
    }

    $data['cfg'] = $config;
}

if ($action === 'export') {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/../php/export.php');
}

unset($data['sql']);
http_response_code($data['status']);
echo json_encode($data);

function recurseTables($map, $prefix, $pfk = null, $pg = null, $pp = null) {
    global $config; global $db; global $data; global $sess; global $request; global $permission;

    if (!$sess->authCheck($request, $permission)) {
        $data['status'] = 403;
        $data['messages'][] = [ 'type' => 'error', 'message' => 'Not Authorized!' ];
        return false;
    }

    if ($map['type'] == 'db') {
        $where = '';
        $whereParts = [];
        if (isset($map['filters']['always'])) $whereParts = ["({$map['filters']['always']})"];
        $parms = [];
        if (!empty($map['fk']) && !empty($pfk)) {
            $whereParts[] = "{$map['fk']['field']} = ?";
            $parms[] = $pfk;
        } else {
            foreach($_REQUEST['data'] as $k => $param) {
                if (isset($map['pk']) && $map['pk']['param'] == $k && !empty($param)) {
                    $whereParts[] = $map['pk']['field'] . ' = ?';
                    $parms[] = $param;
                } else if (isset($map['filters'][$k])) {
                    if (!is_array($map['filters'][$k]) && sizeof(explode('::', $map['filters'][$k])) == 2) {
                        $fk = explode('::', $map['filters'][$k]);
                        if ($fk[0] === 'key') {
                            $whereParts[] = "? IN ($k.{$fk[1]})";
                        } else if ($fk[0] === 'field') {
                            $whereParts[] = "? IN ($prefix.{$fk[1]})";
                        }
                        $parms[] = $param;
                    } else if (!is_array($map['filters'][$k]) && strpos($map['filters'][$k], '?')) {
                        $whereParts[] = $map['filters'][$k];
                        $parms[] = $param;
                    } else if (!empty($map['filters'][$k][$param])) {
                        $whereParts[] = $map['filters'][$k][$param];
                    } else {
                        $whereParts[] = '1 = 1';
                    }
                }
            }
            if (empty($whereParts) && isset($map['filters']['default'])) {
                $whereParts[] = $map['filters'][$map['filters']['default'][0]][$map['filters']['default'][1]];
            }
        }

        if (!empty($whereParts)) $where = "WHERE " . implode(' AND ', $whereParts);

        $rawSQL = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/../sql/{$map['table']}.sql");

        $limit = '';
        if (!empty($pg) && !empty($pp)) {
            $start = ($pg - 1) * $pp;
            $limit = "LIMIT $start, $pp";

            $sql = 'SELECT COUNT(*) FROM (' . str_replace('/*where*/', $where, $rawSQL) . ') AS t';
            $stmt = $db[$map['db']]->prepare($sql);
            $stmt->execute($parms);
            $data['resultCount'] = $stmt->fetchAll(PDO::FETCH_NUM)[0][0];
            $data['pp'] = $pp;
        }

        $sql = str_replace('/*where*/', $where, str_replace('/*limit*/', $limit, $rawSQL));

        $stmt = $db[$map['db']]->prepare($sql);
        $stmt->execute($parms);
        $results = $stmt->fetchAll();
        if (isset($map['children']) && (sizeof($results) === 1) || !empty($map['listWithChildren'])) {
            foreach($results as &$result) {
                foreach($map['children'] as $k => $child) {
                    $result[$k] = recurseTables($child, $k, $result[$map['pk']['alias']]);
                }
            }
        }

        $data['status'] = 200;
        $data['sql'][] = $sql;
    } else if ($map['type'] == 'file') {
        $parser = new FileParser($map['file'], $map['input']);
        $data['results'] = $parser->output($map['output']);
    } else {
        $data['status'] = 404;
        $data['messages'][] = [ 'type' => 'error', 'message' => 'Routing Not Found!' ];
    }

    return $results;
}
