<?php
/**
 * Plugin Name: Dinamik Fiyat Hesaplayıcı
 * Description: WooCommerce için gelişmiş form builder ve PHP tabanlı dinamik fiyat hesaplama sistemi
 * Version: 2.0.0
 * Author: Custom Development
 * Text Domain: dinamik-fiyat
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitleri
define('DFH_VERSION', '2.0.0');
define('DFH_PATH', plugin_dir_path(__FILE__));
define('DFH_URL', plugin_dir_url(__FILE__));

// Sınıfları yükle
require_once DFH_PATH . 'includes/class-dfh-cpt.php';
require_once DFH_PATH . 'includes/class-dfh-admin.php';
require_once DFH_PATH . 'includes/class-dfh-frontend.php';
require_once DFH_PATH . 'includes/class-dfh-calculation.php';

/**
 * Ana plugin sınıfı
 */
class DFH_Core {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function init() {
        // WooCommerce kontrolü
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Dil dosyalarını yükle
        load_plugin_textdomain('dinamik-fiyat', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Sınıfları başlat
        new DFH_CPT();
        new DFH_Admin();
        new DFH_Frontend();
        new DFH_Calculation();
    }

    public function activate() {
        // Aktivasyon işlemleri
        if (!get_option('dfh_version')) {
            update_option('dfh_version', DFH_VERSION);
        }
        flush_rewrite_rules();
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p>
                <strong>Dinamik Fiyat Hesaplayıcı</strong> çalışmak için 
                <strong>WooCommerce</strong> eklentisine ihtiyaç duyar.
            </p>
        </div>
        <?php
    }
}

// Plugin'i başlat
DFH_Core::get_instance();