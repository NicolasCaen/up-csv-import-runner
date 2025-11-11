<?php
if (!defined('ABSPATH')) exit;

class UP_CSV_Runner_Import {
    public function run($xml_file, $csv_tmp_path) {
        if (class_exists('UP_CSV_Config')) {
            $xml = UP_CSV_Config::load($xml_file);
        } else {
            $xml = simplexml_load_file($xml_file);
        }
        if (class_exists('UP_CSV_Importer')) {
            $importer = new UP_CSV_Importer();
            return $importer->import_from_config($xml, $csv_tmp_path);
        }
        return ['imported' => 0, 'errors' => []];
    }
}
