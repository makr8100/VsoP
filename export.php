<?php

if (!$sess->authCheck($config, $data, $request, 'r') || !$sess->authCheck($config, $data, 'export', 'w')) {
    $data['status'] = 403;
    $data['messages'][] = [ 'type' => 'error', 'message' => 'Not Authorized!' ];
} else {
    $map = $config['mapping'][$request];
    $exportMap = $config['exportMapping'][$map['exportMap']];

    if (!empty($map['exportParm']) && sizeof($data['results']) === 1) {
        $parmField = explode('.', $exportMap['optparams'][$map['exportParm']])[1];
        $parmValue = $data['results'][0][$parmField];
        $data['parmValue'] = $parmValue;

        if (!empty($_REQUEST['data']['confirm'])) {
            $data['exports'] = recurseExportTables($db, $parmValue, $data, $map['exportMap'], $map, $exportMap, $map['db'], $map['exportDB'], $_REQUEST['data']['exportList']);
            if (!empty($data['exports'])) $data['messages'][] = [ 'type' => 'success', 'message' => 'Generated PO Number ' . $data['exports'] ];
        } else {
            $data['exports'] = getSiblingExports($config, $db, $request, $parmField, $parmValue, $map, $exportMap, $data['results'][0]);
        }
    }
}

function getSiblingExports($config, $db, $request, $field, $value, $map, $exportMap, $result) {
    $parms = [$value];
    $group = '';
    if (!empty($exportMap['group'])) {
        foreach ($exportMap['group'] as $groupParam) {
            $groupField = explode('.', $groupParam)[1];
            $parms[] = $result[$groupField];
            $parmMarkers[] = "$groupParam = ?";
        }
        $group = ' AND (' . implode(' AND ', $parmMarkers) . ')';
    }

    $rawSQL = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/../sql/{$map['table']}.sql");
    $where = "WHERE {$map['pk']['field']} IN (SELECT {$map['pk']['param']} FROM {$map['table']} $request WHERE $field = ? AND ({$exportMap['criteria']})$group)";
    $sql = str_replace('/*where*/', $where, $rawSQL);
    $stmt = $db[$map['db']]->prepare($sql);
    $stmt->execute($parms);
    $results = $stmt->fetchAll();
    if (isset($map['children'])) {
        $rawSQL = [];
        foreach($results as &$result) {
            foreach($map['children'] as $k => $child) {
                if (!isset($rawSQL[$k])) $rawSQL[$k] = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/../sql/{$child['table']}.sql");
                $where = "WHERE {$child['fk']['field']} = ?";
                $dsql = str_replace('/*where*/', $where, $rawSQL[$k]);
                $dstmt = $db[$child['db']]->prepare($dsql);
                $dstmt->execute([$result[$map['pk']['alias']]]);
                $result[$k] = $dstmt->fetchAll();
            }
        }
    }
    return $results;
}

function recurseExportTables($db, $parmValue, &$data, $dst, $map, $exportMap, $dbSrc, $dbExp, $exportList = null, $key = null, $master = null) {
    $fields = []; $parms = [];
    if (!empty($exportMap['group']) && !empty($exportMap['pk'])) $fields[] = "GROUP_CONCAT(DISTINCT {$exportMap['abbreviation']}.{$exportMap['pk']} ORDER BY {$exportMap['abbreviation']}.{$exportMap['pk']}) AS pk";
    else if (!empty($exportMap['pk'])) $fields[] = "{$exportMap['abbreviation']}.{$exportMap['pk']} AS pk";

    // build SQL for source SELECT
    foreach($exportMap['fieldTranslations'] as $field => $translation) {
        if (!empty($translation)) {
            $value = explode('::', $translation);
            switch ($value[0]) {
                case 'field':
                    $fields[] = "{$value[1]} AS $field";
                    break;
                case 'sqlp':
                    $fields[] = "{$value[2]} AS $field";
                    break;
                case 'val':
                case 'sql':
                case 'date':
                case 'idate':
                case 'fk':
                    break;
                default:
                    $data['status'] = 500;
                    $data['messages'][] = [ 'type' => 'error', 'message' => "Field $field error in value: $translation" ];
                    return false;
                    break;
            }
        }
    }

    $joins = '';
    foreach($exportMap['joins'] as $abbreviation => $table) {
        if (isset($table['type'])) $joins .= "{$table['type']} ";
        $joinParts = [];
        foreach($table['matches'] as $search => $match) {
            if (strpos($search, '.') !== false) $joinParts[] = "$search = $abbreviation.$match";
            else $joinParts[] = "{$exportMap['abbreviation']}.{$search} = $abbreviation.$match";
        }
        $joins .= "\nJOIN {$table['name']} $abbreviation ON " . implode(' AND ', $joinParts) . ' ';
    }

    $whereParts = [];

    $fkstr = '';
    if (!empty($key)) $fkstr = "{$exportMap['abbreviation']}.{$exportMap['fk']} IN($key)";
    if (!empty($fkstr)) $whereParts[] = $fkstr;

    $filters = [];

    if (!empty($exportList)) {
        $markers = [];
        foreach($exportList as $export) {
            $markers[] = '?';
            $parms[] = $export;
        }
        $markers = implode(', ', $markers);
        $filters[] = "{$exportMap['abbreviation']}.{$exportMap['pk']} IN($markers)";
    }

    if (!empty($map['exportMap']) && !empty($exportMap['optparams'][$map['exportParm']]) && !empty($parmValue)) {
        $filters[] = "{$exportMap['optparams'][$map['exportParm']]} = ?";
        $parms[] = $parmValue;
    }

    $filter = implode(' AND ', $filters);
    if (!empty($filter)) $whereParts[] = $filter;

    if (!empty($exportMap['criteria'])) $whereParts[] = $exportMap['criteria'];

    $where = implode(' AND ', $whereParts);
    $srcSQL = "SELECT " . implode(',', $fields) . "\nFROM {$exportMap['relation']} {$exportMap['abbreviation']} $joins\nWHERE $where";
    if (!empty($exportMap['group'])) {
        $group = implode(', ', $exportMap['group']);
        $srcSQL .= "\nGROUP BY $group";
    }
    if (!empty($key)) $srcSQL .= "\nORDER BY {$exportMap['abbreviation']}.{$exportMap['fk']}, {$exportMap['abbreviation']}.{$exportMap['pk']}";
    else $srcSQL .= "\nORDER BY {$exportMap['abbreviation']}.{$exportMap['pk']}";
// file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/export.sql', $srcSQL);
// die();
    // build parameters and markers for source SELECT
    $fields = []; $values = []; $presets = [];
    if (!empty($exportMap['primary'])) {
        $fields[] = $exportMap['primary'];
        $values[] = "(SELECT MAX({$exportMap['primary']}) + 1 FROM $dst)";
    }
    foreach($exportMap['fieldTranslations'] as $field => $translation) {
        if (!empty($translation)) {
            $fields[] = $field;
            $value = explode('::', $translation);
            if (($value[0] == 'sqlp' && sizeof($value) !== 3) || ($value[0] != 'sqlp' && sizeof($value) !== 2)) {
                $data['status'] = 500;
                $data['messages'][] = [ 'type' => 'error', 'message' => "Field $field error in value: $translation" ];
                return false;
            }
            switch ($value[0]) {
                case 'val':
                    $values[] = '?';
                    $presets[$field] = $value[1];
                    break;
                case 'field':
                    $values[] = '?';
                    break;
                case 'sql':
                    $values[] = $value[1];
                    break;
                case 'sqlp':
                    $values[] = $value[1];
                    break;
                case 'date':
                case 'idate':
                    $values[] = '?';
                    break;
                case 'fk':
                    $values[] = '?';
                    break;
                default:
                    break;
            }
        }
    }

    $results = [];
    if (sizeof($fields) > 0) {
        $db[$dbSrc]->exec('SET @row_number = 0');
        $stmt = $db[$dbSrc]->prepare($srcSQL);
        $stmt->execute($parms);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($results)) {
        $data['status'] = 404;
        $data['messages'][] = [ 'type' => 'error', 'message' => 'Request Not Found!' ];
        $results = false;
        return false;
    }

    $dstSQL = "INSERT INTO $dst\n\t(" . implode(',', $fields) . ")\n\tVALUES (" . implode(',', $values) . ")";

    // build parameters for destination INSERT
    foreach($results as $k => $result) {
        $dparms = []; $fields = [];
        foreach($exportMap['fieldTranslations'] as $field => $translation) {
            if (!empty($translation)) {
                $value = explode('::', $translation);
                switch ($value[0]) {
                    case 'val':
                        $dparms[] = $presets[$field];
                        break;
                    case 'field':
                        $dparms[] = $result[$field];
                        break;
                    case 'sqlp':
                        $dparms[] = $result[$field];
                        break;
                    case 'date':
                        if (strpos($value[1], '+')) {
                            $d = explode('+', $value[1]);
                            $dte = intval(date($d[0])) + $d[1];
                        } elseif (strpos($value[1], '-')) {
                            $d = explode('-', $value[1]);
                            $dte = intval(date($d[0])) - $d[1];
                        } else {
                            $dte = intval(date($value[1]));
                        }
                        $dparms[] = date($dte);
                        break;
                    case 'idate':
                    // requires php7-intl
                        if (strpos($value[1], '+')) {
                            $d = explode('+', $value[1]);
                            $df = new IntlDateFormatter('en_US', null, null, null, null, $d[0]);
                            $dte = intval($df->format(new DateTime)) + $d[1];
                        } elseif (strpos($value[1], '-')) {
                            $d = explode('-', $value[1]);
                            $df = new IntlDateFormatter('en_US', null, null, null, null, $d[0]);
                            $dte = intval($df->format(new DateTime)) - $d[1];
                        } else {
                            $df = new IntlDateFormatter('en_US', null, null, null, null, $value[1]);
                            $dte = intval($df->format(new DateTime));
                        }
                        $dparms[] = date($dte);
                        break;
                    case 'fk':
                        $dparms[] = $master[$value[1]];
                        break;
                    default:
                        break;
                }
            }
        }

        if (!empty($exportMap['charConversion'])) {
            foreach($dparms as &$parm) {
                $parm = mb_convert_encoding($parm, $exportMap['charConversion']);
            }
        }

        if (!empty($exportMap['primary'])) $dstmt = $db[$dbExp]->prepare("SELECT {$exportMap['primary']} FROM FINAL TABLE ({$dstSQL})");
        else $dstmt = $db[$dbExp]->prepare($dstSQL);

        if (empty($dstmt)) {
            $data['status'] = 500;
            $data['messages'][] = [ 'type' => 'error', 'message' => json_encode($db[$dbExp]->errorInfo()) ];
            return false;
        }
        if ($dstmt->execute($dparms)) {
            $mst = null;
            if (!empty($exportMap['primary'])) {
                $dresults = $dstmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($dresults[0])) $mst = $dresults[0];
            }

            if (!empty($exportMap['children'])) {
                foreach($results as &$result) {
                    foreach($exportMap['children'] as $ch => $child) {
                        $result['children'][$ch] = recurseExportTables($db, $parmValue, $data, $ch, $child, $exportMap['children'][$ch], $dbSrc, $dbExp, null, $result['pk'], $mst);
                    }
                }
            }

            if (!empty($exportMap['postProcessing']) && !empty($result['pk'])) {
                $sql = $exportMap['postProcessing'] . " IN({$result['pk']})";
                $pstmt = $db[$dbSrc]->prepare($sql);
                $pstmt->execute([$mst[$exportMap['primary']]]);
                return $mst[$exportMap['primary']];
            }
        } else {
            $data['status'] = 500;
            $data['messages'][] = [ 'type' => 'error', 'message' => json_encode($dstmt->errorInfo()) ];
            return false;
        }
    }
}
