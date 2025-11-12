<?php
/**
 * Plugin Name: UP CSV Import Runner
 * Description: Lecture et exécution des configurations XML d'import CSV.
 * Version: 0.1.2.1
 * Author: GEHIN Nicolas
 */
if (!defined('ABSPATH')) exit;

if (!defined('UP_CSV_RUNNER_VERSION')) define('UP_CSV_RUNNER_VERSION', '0.1.2.1');
if (!defined('UP_CSV_RUNNER_PATH')) define('UP_CSV_RUNNER_PATH', plugin_dir_path(__FILE__));
if (!defined('UP_CSV_IMPORTER_CONFIG_DIR')) define('UP_CSV_IMPORTER_CONFIG_DIR', WP_PLUGIN_DIR . '/up-csv-importer/config-settings/');

if (!function_exists('up_csv_runner_get_local_dir')) {
    function up_csv_runner_get_local_dir() {
        $dir = UP_CSV_RUNNER_PATH . 'config/';
        return wp_normalize_path($dir);
    }
}

if (!function_exists('up_csv_runner_get_custom_dir')) {
    function up_csv_runner_get_custom_dir() {
        $rel = get_option('up_csv_runner_config_dir');
        $rel = is_string($rel) ? trim($rel) : '';
        if ($rel) {
            // Interpréter comme chemin RELATIF à WP_CONTENT_DIR
            $rel = ltrim($rel, '/');
            $abs = trailingslashit(WP_CONTENT_DIR) . $rel;
            $abs = wp_normalize_path($abs);
            if (substr($abs, -1) !== '/') $abs .= '/';
            return $abs;
        }
        return '';
    }
}

require_once UP_CSV_RUNNER_PATH . 'includes/class-runner-menu.php';
require_once UP_CSV_RUNNER_PATH . 'includes/class-runner-import.php';

add_action('plugins_loaded', function() {
    new UP_CSV_Runner_Menu();
});
