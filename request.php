<?php

/**
 * request.php - AJAX handler
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2020-05-28
 * @package      VsoP
 * @name         request.php
 * @since        2019-06-24
 * @version      0.17
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
$sess = new Session($config, $db, $data);

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

if ($action === 'login') {
    $sess->doLogin($db, $config, $data, $_REQUEST['usr'], $_REQUEST['pwd']);
} else if ($action === 'logout') {
    $sess->doLogout($config, $data);
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

    if ($sess->authCheck($config, $data, $config['mapping'][$request]['auth'], $permission)) {
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
        if (isset($_REQUEST['fmt']) && in_array($_REQUEST['fmt'], ['csv'])) $pp = null;
        else if (isset($_REQUEST['data']['pp'])) $pp = intval($_REQUEST['data']['pp']);
        else if (isset($config['mapping'][$request]['limit'])) $pp = $config['mapping'][$request]['limit'];

        $data['results'] = (empty($data['status'])) ? recurseTables($config, $db, $sess, $request, $permission, $data, $config['mapping'][$request], $request, null, $pg, $pp) : false;

        if (!empty($data['results'])) {
            if (isset($cache)) {
                $cache->save($data['results'], $request, $config['cache'][$request]['timeout']);
            }
            $data['status'] = 200;
        }
    }

//     $data['cfg'] = $config;
} else if ($action === 'view') {
    $data['status'] = 400;
    $data['messages'][] = [ 'type' => 'error', 'message' => 'Empty Request!' ];
}

if ($action === 'export') {
    require_once(__DIR__ . '/export.php');
}

unset($data['sql']);

http_response_code($data['status']);

if (isset($_REQUEST['fmt']) && in_array($_REQUEST['fmt'], ['html', 'pdf', 'xml', 'csv']) && $data['status'] === 200) {
    require_once __DIR__ . '/view/php/fmt.php';
} else if (isset($_REQUEST['fmt']) && $data['status'] === 403) {
    //TODO: echo login form instead of link
    echo "Please log in at <a href='//{$_SERVER['SERVER_NAME']}'>{$_SERVER['SERVER_NAME']}</a>, then reload this page.  If you are already logged in and stil cannot view please check with IT for permissions to view.";
} else if (isset($_REQUEST['fmt'])) {
    echo "ERROR {$data['status']}: {$data['messages'][0]['message']}";
} else {
    echo json_encode($data, !empty($_REQUEST['tidy']) ? JSON_PRETTY_PRINT : null);
}

function recurseTables($config, $db, $sess, $request, $permission, &$data, $map, $prefix, $pfk = null, $pg = null, $pp = null, $isExport = false, $where = '', $parms = []) {
    if (!$sess->authCheck($config, $data, $config['mapping'][$request]['auth'], $permission)) {
        $data['status'] = 403;
        $data['messages'][] = [ 'type' => 'error', 'message' => 'Not Authorized!' ];
        return false;
    }

    if ($map['type'] == 'db') {
        $rawSQL = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/../sql/{$map['table']}.sql");

        if (empty($where) || empty($parms)) {
            $whereParts = [];
            if (isset($map['filters']['always'])) $whereParts = ["({$map['filters']['always']})"];
            $getKey = false;
            if (!empty($map['fk']) && !empty($pfk)) {
                $whereParts[] = "{$map['fk']['field']} = ?";
                $parms[] = $pfk;
            } else {
                foreach($_REQUEST['data'] as $k => &$parm) {
                    if (isset($map['charConversion'])) {
                        $parm = mb_convert_encoding($parm, $map['charConversion']);
                    }
                    if (isset($map['pk']) && $map['pk']['param'] == $k && !empty($parm)) {
                        $whereParts[] = $map['pk']['field'] . ' = ?';
                        $parms[] = $parm;
                        $getKey = true;
                    } else if (isset($map['filters'][$k])) {
                        if (!is_array($map['filters'][$k]) && sizeof(explode('::', $map['filters'][$k])) == 2) {
                            $fk = explode('::', $map['filters'][$k]);
                            if ($fk[0] === 'key') {
                                $whereParts[] = "? IN ($k.{$fk[1]})";
                            } else if ($fk[0] === 'nqkey') {
                                $whereParts[] = "? IN ({$fk[1]})";
                            } else if ($fk[0] === 'field') {
                                $whereParts[] = "? IN ($prefix.{$fk[1]})";
                            } else if ($fk[0] === 'date') {
                                $dte = DateTime::createFromFormat('Y-m-d', $parm);
                                $whereParts[] = $fk[2];
                                $parm = $dte->format($fk[1]);
                            } else if ($fk[0] === 'dateint') {
                                $dte = DateTime::createFromFormat('Y-m-d', $parm);
                                $whereParts[] = $fk[2];
                                $parm = intval($dte->format($fk[1]));
                            }
                            $parms[] = $parm;
                        } else if (!is_array($map['filters'][$k]) && strpos($map['filters'][$k], '?')) {
                            $whereParts[] = $map['filters'][$k];
                            $parms[] = $parm;
                        } else if (!empty($map['filters'][$k][$parm])) {
                            $whereParts[] = $map['filters'][$k][$parm];
                        } else {
                            //TODO: necessary?
                            $whereParts[] = '1 = 1';
                        }
                    }
                }

                //TODO: make default an object, foreach it, and set each filter conditionally
                if (empty($whereParts) && isset($map['filters']['default'])) {
                    $whereParts[] = $map['filters'][$map['filters']['default'][0]][$map['filters']['default'][1]];
                }
            }

            if (!empty($whereParts)) $where = "WHERE " . implode(' AND ', $whereParts);

            if (isset($map['charConversion'])) {
                foreach ($parms as &$parm) {
                    $parm = mb_convert_encoding($parm, $map['charConversion']);
                }
            }

            $limit = '';
            if (!$getKey && !empty($pg) && !empty($pp)) {
                $start = ($pg - 1) * $pp;
                if (!isset($map['pageSyntax']) || $map['pageSyntax'] !== 'rownumber') $limit = "LIMIT $start, $pp";

                $sql = 'SELECT COUNT(*) FROM (' . str_replace('/*where*/', $where, $rawSQL) . ') AS t';
    // echo "$sql\n\n";
                $stmt = $db[$map['db']]->prepare($sql);
                $stmt->execute($parms);
                $data['resultCount'] = $stmt->fetchAll(PDO::FETCH_NUM)[0][0];
                $data['pp'] = $pp;
            }

            if (!$getKey && !empty($pg) && !empty($pp) && isset($map['pageSyntax']) && $map['pageSyntax'] === 'rownumber') {
                $end = $start + $pp;
                $start ++;
                $order = '';
                if (isset($map['order'])) $order = $map['order'];
                $sql = str_replace('/*order*/', $order, str_replace('/*where*/', $where, "SELECT * FROM (SELECT ROW_NUMBER() OVER(/*order*/) AS RNUM, r.* FROM ($rawSQL) AS r) AS t WHERE RNUM BETWEEN $start AND $end"));
            } else {
                $sql = str_replace('/*where*/', $where, str_replace('/*limit*/', $limit, $rawSQL));
            }
        } else {
            $sql = str_replace('/*where*/', $where, str_replace('/*limit*/', $limit, $rawSQL));
        }
// echo "$sql\n\n";
        $stmt = $db[$map['db']]->prepare($sql);
        $stmt->execute($parms);
        $results = $stmt->fetchAll();
        foreach($results as &$result) {
            if (!empty($map['requiresTrim']) || isset($map['charConversion'])) {
                foreach($result as $k => &$column) {
                    // if (isset($map['charConversion'])) $column = mb_convert_encoding($column, 'utf-8', $map['charConversion']);
                    if (isset($map['charConversion'])) $column = iconv($map['charConversion'], 'utf-8', $column);
                    if (!empty($map['requiresTrim'])) $column = trim($column);
                    if (!empty($map['formatFields']) && !empty($map['formatFields'][$k])) {
                        $fmt = explode('::', $map['formatFields'][$k]);
                        if ($fmt[0] === 'date') {
                            $dte = DateTime::createFromFormat($map['dateFormat'], $column);
                            $result[$k . '_fmt'] = date($fmt[1], intval($dte->format('U')));
                        }
                    }
                }
            }
            if (isset($map['children']) && ((sizeof($results) === 1) || !empty($map['listWithChildren'] || $isExport))) {
                foreach($map['children'] as $k => $child) {
                    $result[$k] = recurseTables($config, $db, $sess, $request, $permission, $data, $child, $k, $result[$map['pk']['alias']]);
                }
            }
            if (isset($map['extInfo'])) {
                foreach($map['extInfo'] as $k => $ext) {
                    if ($ext['type'] === 'sum') {
                        $result[$k] = 0;
                        foreach ($result[$ext['key']] as $row) {
                            $result[$k] += $row[$ext['col']];
                        }
                    } else if ($ext['type'] === 'nempty') {
                        $result[$k] = 1;
                        foreach ($result[$ext['key']] as $row) {
                            if (empty($row[$ext['col']])) $result[$k] = 0;
                        }
                    } else {
                        $extInfo = recurseTables($config, $db, $sess, $request, $permission, $data, $ext, $k, $result[$map['pk']['alias']]);
                    }
                    if (!empty($extInfo)) {
                        foreach($extInfo[0] as $key => $col) {
                            $result[$key] = $col;
                        }
                    }
                }
            }
            $result['editing'] = 0;
        }

        $data['status'] = 200;
        $data['sql'][] = $sql;
    } else if ($map['type'] == 'file') {
        $parser = new FileParser($map['file'], $map['input']);
        $results = $parser->output($map['output']);
    } else if ($map['type'] == 'json') {
        $results = $map['data'];
    } else {
        $data['status'] = 404;
        $data['messages'][] = [ 'type' => 'error', 'message' => 'Routing Not Found!' ];
    }

    return $results;
}
