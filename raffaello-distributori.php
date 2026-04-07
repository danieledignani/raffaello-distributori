<?php
/**
 * Plugin Name: Raffaello Distributori
 * Plugin URI: https://raffaelloragazzi.it
 * Description: Gestione dei distributori e classi di sconto per Raffaello Ragazzi.
 * Version: 0.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Auto-update via Plugin Update Checker
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://raw.githubusercontent.com/danieledignani/raffaello-distributori/master/.github/update-metadata/raffaello-distributori.json',
    __FILE__,
    'raffaello-distributori'
);

define('DISTRIBUTORI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DISTRIBUTORI_PLUGIN_URL', plugin_dir_url(__FILE__));

class DistributoriPlugin {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->include_files();
        $this->init_hooks();
    }

    private function include_files() {
        include_once DISTRIBUTORI_PLUGIN_DIR . 'includes/post-types.php';
        include_once DISTRIBUTORI_PLUGIN_DIR . 'includes/rest-api.php';
        include_once DISTRIBUTORI_PLUGIN_DIR . 'includes/province-handler.php';
        include_once DISTRIBUTORI_PLUGIN_DIR . 'includes/archive-filter.php';
        include_once DISTRIBUTORI_PLUGIN_DIR . 'includes/acf-fields.php';
        if (is_admin()) {
            include_once DISTRIBUTORI_PLUGIN_DIR . 'includes/admin.php';
        }
    }

    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_rest_api'), 10);
    }

    public function register_rest_api() {
        rd_register_distributori_rest_api();
    }
}

add_action('plugins_loaded', function() {
    DistributoriPlugin::instance();
});
