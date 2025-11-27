<?php
/**
 * Plugin Name: Dinamik Fiyat Hesaplayıcı - Gemini
 * Description: Drag & Drop form oluşturucu ve özel PHP fiyat hesaplama motoru.
 * Version: 1.0.0
 * Author: Gemini AI
 * Text Domain: din-fiyat-gemini
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Güvenlik
}

// Sabitler
define( 'DFH_PATH', plugin_dir_path( __FILE__ ) );
define( 'DFH_URL', plugin_dir_url( __FILE__ ) );

// Sınıfları Dahil Et
require_once DFH_PATH . 'includes/class-dfh-cpt.php';
require_once DFH_PATH . 'includes/class-dfh-admin.php';
require_once DFH_PATH . 'includes/class-dfh-frontend.php';
require_once DFH_PATH . 'includes/class-dfh-calculation.php';

// Başlatıcı Sınıf
class DFH_Core {
    public function __construct() {
        // WooCommerce aktif mi kontrol et
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="error"><p><strong>Dinamik Fiyat Hesaplayıcı - Gemini</strong> çalışmak için WooCommerce eklentisine ihtiyaç duyar.</p></div>';
            });
            return;
        }

        // Alt sınıfları başlat
        new DFH_CPT();        // Kayıt Türleri
        new DFH_Admin();      // Admin Paneli & Form Builder
        new DFH_Frontend();   // Ön Yüz İşlemleri
        new DFH_Calculation();// Hesaplama Mantığı
    }
}

new DFH_Core();