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
    $content = mergeData(file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/vueelements/$request.html"), $data['results'], $request);

$html = <<<HTML
<!DOCTYPE html><html><head><style>
$css
</style></head><body>
{$config['company']['letterhead']}
<div id="content">

$content

</div>

</body></html>

HTML;
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

function mergeData($html, $data, $request) {
    global $selfClosingTags; global $htmlLoopRegex;
    $html = trim(preg_replace('/\>\s+\</', '><', $html));
    preg_match_all('/<.*?>[^<]{0,}/', $html, $tags);
    $tags = $tags[0];
    $deep = 0;
    $loops = [];
    $childhtml = [];
    foreach($tags as $tag) {
        $loop = false;
        preg_match("/(?<=\<)\/?.*?[^\s|>]{0,}/", $tag, $short);
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
                        foreach ($data as $p => $row) {
                            foreach ($row as $dkey => $field) {
                                if ($l['container'] === $dkey) $html = str_replace(innerHTML($l['html']), mergeData(innerHTML($l['html']), $field, $request), $html);
                            }
                        }
                    }
                    //echo "end $key\n";
                    //echo $html . "\n\n";
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
        //echo "$deep" . str_repeat("    ", $deep) . "$tag\n";
        if (substr($short, 0, 1) === '/' || in_array($short, $selfClosingTags)) $deep -= 1;
    }

    $return = '';
    preg_match_all('/(?<={{\s).{1,}(?=\s}})/U', $html, $replaces);
    $replaces = $replaces[0];
    foreach($data as $row) {
        $tmp = $html;
        foreach ($replaces as $ev) {
            $eval = explode('.', $ev);
            $replace = "{{ $ev }}";
            $tmp = str_replace($replace, $row[$eval[1]], $tmp);
        }
        $return .= $tmp;
    }
    return $return;
}

function innerHTML($html) {
    $t = explode('><', $html);
    unset($t[sizeof($t) - 1]);
    unset($t[0]);
    return '<' . implode('><', $t) . '>';
}
