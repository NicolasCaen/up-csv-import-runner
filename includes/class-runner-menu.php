<?php
if (!defined('ABSPATH')) exit;

class UP_CSV_Runner_Menu {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_menu_page('Imports CSV', 'Imports CSV', 'manage_options', 'up-csv-imports', function() {
            // Save settings
            if (!empty($_POST['up_runner_dir_nonce']) && wp_verify_nonce($_POST['up_runner_dir_nonce'], 'up_runner_dir_save')) {
                $dir_rel = isset($_POST['up_runner_dir']) ? wp_unslash($_POST['up_runner_dir']) : '';
                $dir_rel = is_string($dir_rel) ? trim($dir_rel) : '';
                // Enregistrer RELATIF à wp-content
                if ($dir_rel) {
                    $dir_rel = ltrim(wp_normalize_path($dir_rel), '/');
                    update_option('up_csv_runner_config_dir', $dir_rel);
                } else {
                    delete_option('up_csv_runner_config_dir');
                }
                wp_safe_redirect(admin_url('admin.php?page=up-csv-imports&saved=1'));
                exit;
            }
            $dirs = $this->get_dirs();
            $files = $this->get_all_files($dirs);
            echo '<div class="wrap">';
            echo '<h1>Imports CSV</h1>';
            if (isset($_GET['saved'])) echo '<div class="updated"><p>Réglages enregistrés.</p></div>';
            echo '<p>Dossier local: <code>' . esc_html(up_csv_runner_get_local_dir()) . '</code></p>';
            echo '<p>Dossier personnalisé (relatif wp-content): <code>' . esc_html(up_csv_runner_get_custom_dir() ?: 'non défini') . '</code></p>';
            echo '<p>Dossier Importer: <code>' . esc_html(defined('UP_CSV_IMPORTER_CONFIG_DIR') ? UP_CSV_IMPORTER_CONFIG_DIR : '') . '</code></p>';
            echo '<p><strong>Total fichiers détectés (toutes sources):</strong> ' . count($files) . '</p>';
            echo '<h2>Réglages du dossier personnalisé</h2>';
            echo '<form method="post" style="margin-bottom:16px;">';
            wp_nonce_field('up_runner_dir_save', 'up_runner_dir_nonce');
            $wp_content = trailingslashit(WP_CONTENT_DIR);
            $current_rel = get_option('up_csv_runner_config_dir', '');
            echo '<p><label for="up_runner_dir">Chemin RELATIF à <code>wp-content/</code></label><br><input type="text" id="up_runner_dir" name="up_runner_dir" class="regular-text" value="' . esc_attr($current_rel) . '" placeholder="mes-configs/" />';
            echo '<br><small>Ex: tapez <code>mes-configs/</code> pour cibler <code>' . esc_html($wp_content) . '</code>mes-configs/</small></p>';
            echo '<p class="submit"><button type="submit" class="button">Enregistrer le dossier</button></p>';
            echo '</form>';
            echo '<h2>Fichiers détectés (toutes sources)</h2>';
            if (!empty($files)) {
                echo '<table class="widefat striped"><thead><tr><th>Fichier</th><th>Dossier</th></tr></thead><tbody>';
                foreach ($files as $f) {
                    echo '<tr><td>' . esc_html(basename($f)) . '</td><td><code>' . esc_html(trailingslashit(dirname($f))) . '</code></td></tr>';
                }
                echo '</tbody></table>';
                echo '<p>Sélectionnez une configuration dans le menu à gauche pour lancer un import.</p>';
            } else {
                echo '<p>Aucun fichier .xml détecté dans ce répertoire.</p>';
            }
            echo '</div>';
        }, 'dashicons-database-import', 57);

        $files = $this->get_all_files($this->get_dirs());
        foreach ($files as $file) {
            $name = basename($file, '.xml');
            add_submenu_page('up-csv-imports', ucfirst($name), ucfirst($name), 'manage_options', 'up-csv-imports-' . $name, function() use ($file, $name) {
                $this->render_import_page($file, $name);
            });
        }
    }

    private function get_dirs() {
        return [
            up_csv_runner_get_local_dir(),
            up_csv_runner_get_custom_dir(),
            defined('UP_CSV_IMPORTER_CONFIG_DIR') ? UP_CSV_IMPORTER_CONFIG_DIR : ''
        ];
    }

    private function get_all_files($dirs) {
        $out = [];
        foreach ($dirs as $dir) {
            if (!$dir) continue;
            if (!file_exists($dir)) continue;
            foreach (glob(trailingslashit($dir) . '*.xml') ?: [] as $f) {
                $out[$f] = $f; // unique par chemin complet
            }
        }
        ksort($out);
        return array_values($out);
    }

    private function render_import_page($file, $name) {
        $xml = file_exists($file) ? simplexml_load_file($file) : false;
        if (!empty($_POST['up_run_nonce']) && wp_verify_nonce($_POST['up_run_nonce'], 'up_run_import') && !empty($_FILES['csv_file']['tmp_name'])) {
            $runner = new UP_CSV_Runner_Import();
            $result = $runner->run($file, $_FILES['csv_file']['tmp_name']);
            $imported = isset($result['imported']) ? intval($result['imported']) : 0;
            echo '<div class="updated"><p>Import terminé. Lignes importées: <strong>' . esc_html($imported) . '</strong>.</p>'; 
            if (!empty($result['errors'])) {
                echo '<p><strong>Erreurs:</strong></p><ul>'; 
                foreach ($result['errors'] as $err) { echo '<li>' . esc_html($err) . '</li>'; }
                echo '</ul>';
            }
            echo '</div>';
        }
        echo '<div class="wrap">';
        echo '<h1>Import: ' . esc_html(ucfirst($name)) . '</h1>';
        echo '<p><small>Chemin XML: <code>' . esc_html($file) . '</code></small></p>';
        if ($xml) {
            echo '<p><strong>Post type:</strong> ' . esc_html((string)$xml->post_type) . '</p>';
            echo '<ul>';
            $nodes = method_exists($xml, 'xpath') ? $xml->xpath('/config/fields/field') : [];
            if (empty($nodes) && isset($xml->fields)) { $nodes = $xml->fields->children(); }
            $countShown = 0;
            foreach ($nodes as $f) {
                if ($f->getName() !== 'field') continue;
                $csv = isset($f['csv']) ? (string)$f['csv'] : '';
                $data_type = isset($f['data_type']) ? (string)$f['data_type'] : '';
                $field_type = isset($f['field_type']) ? (string)$f['field_type'] : '';
                $meta_key = isset($f['meta_key']) ? (string)$f['meta_key'] : '';
                $target = $field_type === 'meta' ? ('meta:' . $meta_key) : $field_type;
                echo '<li>CSV ' . esc_html($csv) . ' → ' . esc_html($target) . ($data_type ? ' <em>(' . esc_html($data_type) . ')</em>' : '') . '</li>';
                $countShown++;
            }
            if ($countShown === 0) { echo '<li><em>Aucun champ détecté dans ce XML.</em></li>'; }
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

