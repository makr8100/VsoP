<?php

/**
 * fileParser.php - text file parser
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-13
 * @package      VsoP
 * @name         fileParser.php
 * @since        2019-07-15
 * @version      0.13
 */

class FileParser {
    public $data;

    public function __construct($file, $inputType) {
        $path = $_SERVER['DOCUMENT_ROOT'] . "/../files/$file";
        if ($inputType == 'text-lines') $this->data = file($path, FILE_IGNORE_NEW_LINES);
        else die("FILE READ ERROR!");
    }

    public function output($outputType) {
        if ($outputType == 'array') $formattedData = $this->data;
        return $formattedData;
    }
}
