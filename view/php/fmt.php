<?php

/**
 * fmt.php - output formatter
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2020-01-17
 * @package      VsoP
 * @name         fmt.php
 * @since        2020-01-10
 * @version      0.15
 * @license      MIT
 */

if (in_array($_REQUEST['fmt'], ['html', 'pdf'])) {
    $css = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/../pdf/css/pdf.css');
    $content = mergeData($selfClosingTags, $htmlLoopRegex, file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/vueelements/$request.html"), $data['results'], $request);

$html = "
<!DOCTYPE html><html><head><style>
$css
</style></head><body>
{$config['company']['letterhead']}
<div id=\"content\">

$content

</div>

</body></html>

";

}

if ($_REQUEST['fmt'] === 'xml') {
    echo xmlrpc_encode($data);
} else if ($_REQUEST['fmt'] === 'csv') {
    $headings = [];
    foreach($data['results'][0] as $k => $row) {
        $headings[] = str_replace('_', ' ', $k);
    }

    $fh = fopen('php://output', 'w');
    ob_start();
    fputcsv($fh, $headings);

    if (!empty($data['results'])) {
        foreach ($data['results'] as $row) {
            fputcsv($fh, $row);
        }
    }

    $string = ob_get_clean();
    $filename = "$request - " . date('Ymd') . ' ' . date('His');

    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private",false);
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$filename.csv\";" );
    header("Content-Transfer-Encoding: binary");

    echo $string;
} else if ($_REQUEST['fmt'] === 'pdf') {
    require_once $config['phplibs']['mpdf'] . '/mpdf.php';
    $mpdf = new Mpdf();

    if (isset($config['mapping'][$request]['printCopies'])) {
        $copies = is_array($config['mapping'][$request]['printCopies']) ? $config['mapping'][$request]['printCopies'] : array_fill(0, $config['mapping'][$request]['printCopies'], '');
        //TODO: footer formatting
        $mpdf->defaultfooterfontsize = 48;
        $mpdf->defaultfooterfontstyle = 'B';
        foreach ($copies as $k => $copy) {
            if ($k > 0) $mpdf->addPage();
            if (!empty($copy)) $mpdf->setHTMLFooter($copy);
            else $mpdf->setHTMLFooter('');
            $mpdf->WriteHTML($html);
        }
    } else {
        $mpdf->WriteHTML($html);
    }
    $id = '';
    foreach($_REQUEST['data'] as $k => $param) {
        if (isset($config['mapping'][$request]['pk']) && $config['mapping'][$request]['pk']['param'] == $k && !empty($param)) {
            $id = ' ' . $param;
        }
    }
    $mpdf->Output("{$config['mapping'][$request]['proper']}$id.pdf", 'I');
} else {
    echo $html;
}

function mergeData($selfClosingTags, $htmlLoopRegex, $html, $data, $request) {
    $html = trim(preg_replace('/\>\s+\</', '><', $html));
    $html = preg_replace('/((?<=\<)|(?<=\<\/))select.*?[^\s|>]{0,}/', 'span', $html);
    $html = preg_replace('/\<option.*?\>.*?\<\/option.*?\>/', '', $html);
    preg_match_all('/<.*?>[^<]{0,}/', $html, $tags);
    $tags = $tags[0];
    $deep = 0;
    $loops = [];
    foreach($tags as &$tag) {
        preg_match('/(?<=v-bind:value=")\S*(?=")/', $tag, $bindFields);
        if (!empty($bindFields)) {
            $newTag = "<span>{{ {$bindFields[0]} }}</span>";
            $html = str_replace($tag, $newTag, $html);
            $tag = $newTag;
        }
        $loop = false;
        preg_match('/(?<=\<)\/?.*?[^\s|>]{0,}/', $tag, $short);
        $short = strtolower($short[0]);
        foreach($loops as &$l) {
            $l['html'] .= $tag;
        }
        if (substr($short, 0, 1) === '/') {
            foreach($loops as &$l) {
                if ($deep === $l['depth'] && "/{$l['tag']}" === $short) {
                    $key = "{$l['self']}.";
                    if (!empty($l['parent'])) $key .= "{$l['parent']}.";
                    $key .= "{$l['container']}";
                    if ($data !== null) {
                        $newHTML = [];
                        foreach ($data as $p => &$row) {
                            foreach ($row as $dkey => $field) {
                                if ($l['container'] === $dkey) {
                                    $row[$dkey . 'html'] = mergeData($selfClosingTags, $htmlLoopRegex, innerHTML($l['html']), $field, $request);
                                    $html = str_replace(innerHTML($l['html']), "{{ {$l['parent']}.{$dkey}html }}", $html);
                                }
                            }
                        }
                    }
                    unset($loops[$key]);
                }
            }
        } else {
            $deep += 1;
            $loop = (preg_match($htmlLoopRegex, $tag, $newLoop));
            if ($loop) {
                $eval = explode(' in ', str_replace("'", '', str_replace('"', '', str_replace('v-for=', '', $newLoop[0]))));
                if ($eval[1] === $request) {
                } else if (strpos($eval[1], '.') !== false) {
                    $eval[2] = explode('.', $eval[1])[1];
                    $eval[1] = explode('.', $eval[1])[0];
                }
                //echo json_encode($eval) . "\n";
                $loops[implode('.', $eval)] = [
                    'parent' => isset($eval[2]) ? $eval[1] : null,
                    'self' => $eval[0],
                    'container' => isset($eval[2]) ? $eval[2] : $eval[1],
                    'depth' => $deep,
                    'tag' => $short,
                    'html' => $tag
                ];
                //echo "begin " . implode('.', $eval) . "\n";
            }
        }
        // echo "$deep" . str_repeat("    ", $deep) . "$tag\n";
        if (substr($short, 0, 1) === '/' || in_array($short, $selfClosingTags) || !empty($bindFields)) $deep -= 1;
    }

    $return = '';
    preg_match_all('/(?<={{\s).{1,}(?=\s}})/U', $html, $replaces);
    $replaces = $replaces[0];
    preg_match_all('/(?<={{\s)Number\(.*\)\.toFixed\(\d\)(?=\s}})/U', $html, $calcReplaces);
    $calcReplaces = $calcReplaces[0];
    foreach($data as &$row) {
        $tmp = $html;
        foreach ($replaces as $ev) {
            $eval = [];
            if (in_array($ev, $calcReplaces)) {
                preg_match('/(?<=Number\().*(?=\)\.toFixed\(\d\))/U', $ev, $fMatch);
                preg_match("/(?<=Number\({$fMatch[0]}\)\.toFixed\()\d(?=\))/U", $ev, $precision);
                $precision = (int)$precision[0];
                $eval = explode('.', $fMatch[0]);
                $row[$eval[1]] = round($row[$eval[1]], $precision);
                if ($precision > 0 && strpos($row[$eval[1]], '.') === false) $row[$eval[1]] .= '.';
                while (strpos($row[$eval[1]], '.') !== false && strlen(explode('.', $row[$eval[1]])[1]) < $precision) {
                    $row[$eval[1]] .= '0';
                }
                
            } else {
                $eval = explode('.', $ev);
            }
            if (sizeof($eval) === 2) {
                $replace = "{{ $ev }}";
                $tmp = str_replace($replace, $row[$eval[1]] ?? '', $tmp);
            }
        }
        $return .= $tmp;
    }
    return $return;
}

function innerHTML($html) {
    preg_match_all('/<.*?>/', $html, $tags);
    $tags = $tags[0];
    return substr(substr($html, 0, strlen($html) - (strlen($tags[sizeof($tags) - 1]))), strlen($tags[0]));
}
