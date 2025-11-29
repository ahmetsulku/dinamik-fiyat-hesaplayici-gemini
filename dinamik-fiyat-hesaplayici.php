<?php
/**
 * Plugin Name: Dinamik Fiyat Hesaplayıcı
 * Description: WooCommerce için gelişmiş form ve fiyat hesaplama
 * Version: 4.0.0
 * Author: Custom Dev
 */

if (!defined('ABSPATH')) exit;

define('DFH_VERSION', '4.0.0');
define('DFH_PATH', plugin_dir_path(__FILE__));
define('DFH_URL', plugin_dir_url(__FILE__));

// Sınıfları yükle
require_once DFH_PATH . 'includes/class-dfh-cpt.php';
require_once DFH_PATH . 'includes/class-dfh-admin.php';
require_once DFH_PATH . 'includes/class-dfh-frontend.php';
require_once DFH_PATH . 'includes/class-dfh-cart.php';
require_once DFH_PATH . 'includes/class-dfh-shortcode.php';
require_once DFH_PATH . 'includes/class-dfh-converter.php';

class DFH_Plugin {
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p><strong>Dinamik Fiyat Hesaplayıcı</strong> için WooCommerce gerekli!</p></div>';
            });
            return;
        }

        new DFH_CPT();
        new DFH_Admin();
        new DFH_Frontend();
        new DFH_Cart();
        new DFH_Shortcode();
    }
}

new DFH_Plugin();
