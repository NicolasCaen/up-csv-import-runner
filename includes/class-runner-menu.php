<?php
if (!defined('ABSPATH')) exit;

class UP_CSV_Runner_Menu {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_menu_page('Imports CSV', 'Imports CSV', 'manage_options', 'up-csv-imports', function() {
            $active_dir = $this->resolve_active_dir();
            // Save settings
            if (!empty($_POST['up_runner_dir_nonce']) && wp_verify_nonce($_POST['up_runner_dir_nonce'], 'up_runner_dir_save')) {
                $dir = isset($_POST['up_runner_dir']) ? wp_unslash($_POST['up_runner_dir']) : '';
                $dir = is_string($dir) ? trim($dir) : '';
                if ($dir) {
                    $dir = wp_normalize_path($dir);
                    update_option('up_csv_runner_config_dir', $dir);
                } else {
                    delete_option('up_csv_runner_config_dir');
                }
                echo '<div class="updated"><p>Réglages enregistrés.</p></div>';
                $active_dir = $this->resolve_active_dir(true);
            }
            $files = $this->get_files_for_active_dir($active_dir);
            echo '<div class="wrap">';
            echo '<h1>Imports CSV</h1>';
            echo '<p>Dossier prioritaire local: <code>' . esc_html(up_csv_runner_get_local_dir()) . '</code></p>';
            echo '<p>Dossier personnalisé (option): <code>' . esc_html(up_csv_runner_get_custom_dir() ?: 'non défini') . '</code></p>';
            echo '<p>Dossier Importer: <code>' . esc_html(defined('UP_CSV_IMPORTER_CONFIG_DIR') ? UP_CSV_IMPORTER_CONFIG_DIR : '') . '</code></p>';
            echo '<p><strong>Répertoire utilisé actuellement:</strong> <code>' . esc_html($active_dir ?: '—') . '</code> (' . count($files) . ' fichiers)</p>';
            echo '<h2>Réglages du dossier personnalisé</h2>';
            echo '<form method="post" style="margin-bottom:16px;">';
            wp_nonce_field('up_runner_dir_save', 'up_runner_dir_nonce');
            $wp_content = trailingslashit(WP_CONTENT_DIR);
            echo '<p><label for="up_runner_dir">Chemin RELATIF à <code>wp-content/</code></label><br><input type="text" id="up_runner_dir" name="up_runner_dir" class="regular-text" value="" placeholder="mes-configs/" />';
            echo '<br><small>Ex: tapez <code>mes-configs/</code> pour cibler <code>' . esc_html($wp_content) . '</code>mes-configs/</small></p>';
            echo '<p class="submit"><button type="submit" class="button">Enregistrer le dossier</button></p>';
            echo '</form>';
            echo '<p>Sélectionnez une configuration dans le menu pour lancer un import.</p>';
            echo '</div>';
        }, 'dashicons-database-import', 57);

        $files = $this->get_files_for_active_dir($this->resolve_active_dir());
        foreach ($files as $file) {
            $name = basename($file, '.xml');
            add_submenu_page('up-csv-imports', ucfirst($name), ucfirst($name), 'manage_options', 'up-csv-imports-' . $name, function() use ($file, $name) {
                $this->render_import_page($file, $name);
            });
        }
    }

    private function resolve_active_dir($force_refresh = false) {
        $dirs = [
            up_csv_runner_get_local_dir(),
            up_csv_runner_get_custom_dir(),
            defined('UP_CSV_IMPORTER_CONFIG_DIR') ? UP_CSV_IMPORTER_CONFIG_DIR : ''
        ];
        foreach ($dirs as $dir) {
            if (!$dir) continue;
            if (!file_exists($dir)) continue;
            $files = glob(trailingslashit($dir) . '*.xml') ?: [];
            if (!empty($files)) return trailingslashit($dir);
        }
        // Si aucun fichier trouvé, retourner le local par défaut
        return trailingslashit(up_csv_runner_get_local_dir());
    }

    private function get_files_for_active_dir($dir) {
        if (!$dir) return [];
        if (!file_exists($dir)) return [];
        return glob(trailingslashit($dir) . '*.xml') ?: [];
    }

    private function render_import_page($file, $name) {
        $xml = file_exists($file) ? simplexml_load_file($file) : false;
        if (!empty($_POST['up_run_nonce']) && wp_verify_nonce($_POST['up_run_nonce'], 'up_run_import') && !empty($_FILES['csv_file']['tmp_name'])) {
            $runner = new UP_CSV_Runner_Import();
            $result = $runner->run($file, $_FILES['csv_file']['tmp_name']);
            echo '<div class="updated"><p>Import exécuté.</p></div>';
        }
        echo '<div class="wrap">';
        echo '<h1>Import: ' . esc_html(ucfirst($name)) . '</h1>';
        if ($xml) {
            echo '<p><strong>Post type:</strong> ' . esc_html((string)$xml->post_type) . '</p>';
            echo '<ul>';
            if (!empty($xml->fields->field)) {
                foreach ($xml->fields->field as $f) {
                    $csv = isset($f['csv']) ? (string)$f['csv'] : '';
                    $data_type = isset($f['data_type']) ? (string)$f['data_type'] : '';
                    $field_type = isset($f['field_type']) ? (string)$f['field_type'] : '';
                    $meta_key = isset($f['meta_key']) ? (string)$f['meta_key'] : '';
                    $target = $field_type === 'meta' ? ('meta:' . $meta_key) : $field_type;
                    echo '<li>CSV ' . esc_html($csv) . ' → ' . esc_html($target) . ($data_type ? ' <em>(' . esc_html($data_type) . ')</em>' : '') . '</li>';
                }
            }
            echo '</ul>';
        }
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('up_run_import', 'up_run_nonce');
        echo '<input type="file" name="csv_file" accept=".csv" required> ';
        submit_button('Importer');
        echo '</form>';
        echo '</div>';
    }
}

