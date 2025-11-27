<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DFH_CPT {
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_types' ) );
    }

    public function register_post_types() {
        // 1. Form Şablonları (Örn: Tabela Formu)
        register_post_type( 'dfh_form', array(
            'labels' => array(
                'name' => 'Form Şablonları',
                'singular_name' => 'Form Şablonu',
                'add_new' => 'Yeni Form Ekle',
                'add_new_item' => 'Yeni Form Şablonu Ekle',
                'edit_item' => 'Formu Düzenle',
            ),
            'public' => false,
            'show_ui' => true, // Admin menüde görünsün
            'show_in_menu' => true,
            'supports' => array( 'title' ),
            'menu_icon' => 'dashicons-layout',
        ));

        // 2. Hesaplama Kuralları (Örn: Tabela Fiyat Kuralı v1)
        register_post_type( 'dfh_rule', array(
            'labels' => array(
                'name' => 'Hesaplama Kuralları',
                'singular_name' => 'Hesaplama Kuralı',
                'add_new' => 'Yeni Kural Ekle',
                'edit_item' => 'Kuralı Düzenle',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=dfh_form', // Form menüsünün altına ekle
            'supports' => array( 'title' ),
        ));
    }
}