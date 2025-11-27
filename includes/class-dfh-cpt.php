<?php
/**
 * Özel post tiplerini kayıt eder
 */

if (!defined('ABSPATH')) {
    exit;
}

class DFH_CPT {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
    }

    public function register_post_types() {
        // Form Şablonları Post Type
        register_post_type('dfh_form', array(
            'labels' => array(
                'name' => __('Form Şablonları', 'dinamik-fiyat'),
                'singular_name' => __('Form Şablonu', 'dinamik-fiyat'),
                'add_new' => __('Yeni Form Ekle', 'dinamik-fiyat'),
                'add_new_item' => __('Yeni Form Şablonu Ekle', 'dinamik-fiyat'),
                'edit_item' => __('Formu Düzenle', 'dinamik-fiyat'),
                'view_item' => __('Formu Görüntüle', 'dinamik-fiyat'),
                'search_items' => __('Form Ara', 'dinamik-fiyat'),
                'not_found' => __('Form bulunamadı', 'dinamik-fiyat'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 56,
            'menu_icon' => 'dashicons-list-view',
            'supports' => array('title'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));

        // Hesaplama Kuralları Post Type
        register_post_type('dfh_rule', array(
            'labels' => array(
                'name' => __('Hesaplama Kuralları', 'dinamik-fiyat'),
                'singular_name' => __('Hesaplama Kuralı', 'dinamik-fiyat'),
                'add_new' => __('Yeni Kural Ekle', 'dinamik-fiyat'),
                'add_new_item' => __('Yeni Hesaplama Kuralı Ekle', 'dinamik-fiyat'),
                'edit_item' => __('Kuralı Düzenle', 'dinamik-fiyat'),
                'search_items' => __('Kural Ara', 'dinamik-fiyat'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=dfh_form',
            'supports' => array('title'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
    }
}