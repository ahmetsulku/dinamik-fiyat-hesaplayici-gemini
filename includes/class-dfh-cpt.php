<?php
if (!defined('ABSPATH')) exit;

class DFH_CPT {
    public function __construct() {
        add_action('init', array($this, 'register'));
    }

    public function register() {
        register_post_type('dfh_form', array(
            'labels' => array(
                'name' => 'Form Şablonları',
                'singular_name' => 'Form',
                'add_new' => 'Yeni Form',
                'add_new_item' => 'Yeni Form Ekle',
                'edit_item' => 'Formu Düzenle',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 56,
            'menu_icon' => 'dashicons-calculator',
            'supports' => array('title'),
        ));

        register_post_type('dfh_rule', array(
            'labels' => array(
                'name' => 'Hesaplama Kuralları',
                'singular_name' => 'Kural',
                'add_new' => 'Yeni Kural',
                'add_new_item' => 'Yeni Kural Ekle',
                'edit_item' => 'Kuralı Düzenle',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=dfh_form',
            'supports' => array('title'),
        ));
    }
}
