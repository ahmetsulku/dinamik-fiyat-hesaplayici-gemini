<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DFH_Admin {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_data' ) );
    }

    public function enqueue_assets( $hook ) {
        global $post;
        if ( ! $post || ! in_array( $post->post_type, array( 'dfh_form', 'dfh_rule' ) ) ) { return; }
        
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_media();
        wp_enqueue_style( 'dfh-admin-css', DFH_URL . 'assets/css/admin.css', array(), '1.0' );
        wp_enqueue_script( 'dfh-admin-js', DFH_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), '1.0', true );

        if ( $post && $post->post_type === 'dfh_rule' ) {
            $settings = wp_enqueue_code_editor( array( 'type' => 'text/x-php' ) );
            if ( $settings ) {
                wp_enqueue_script( 'wp-theme-plugin-editor' );
                wp_enqueue_style( 'wp-codemirror' );
                wp_add_inline_script(
                    'wp-theme-plugin-editor',
                    'jQuery(function(){if(window.wp&&wp.codeEditor){wp.codeEditor.initialize("dfh_php_code",' . wp_json_encode( $settings ) . ');} });'
                );
            }
        }
    }

    public function add_meta_boxes() {
        add_meta_box('dfh_form_builder', 'Form Oluşturucu (Drag & Drop)', array( $this, 'render_form_builder' ), 'dfh_form', 'normal', 'high');
        add_meta_box('dfh_rule_settings', 'Hesaplama Ayarları & PHP Kodu', array( $this, 'render_rule_settings' ), 'dfh_rule', 'normal', 'high');
    }

    public function render_form_builder( $post ) {
        $fields = get_post_meta( $post->ID, '_dfh_form_fields', true );
        if ( ! is_array( $fields ) ) { $fields = array(); }
        ?>
        <div id="dfh-builder-wrapper">
            <div class="dfh-toolbar">
                <h3>Alan Ekle:</h3>
                <div class="dfh-tools">
                    <button type="button" class="button dfh-add-field" data-type="text">Text</button>
                    <button type="button" class="button dfh-add-field" data-type="number">Number</button>
                    <button type="button" class="button dfh-add-field" data-type="textarea">Textarea</button>
                    <button type="button" class="button dfh-add-field" data-type="select">Select</button>
                    <button type="button" class="button dfh-add-field" data-type="radio">Radio</button>
                    <button type="button" class="button dfh-add-field" data-type="checkbox">Checkbox</button>
                    <button type="button" class="button dfh-add-field" data-type="image_radio">Image Radio</button>
                    <button type="button" class="button dfh-add-field" data-type="file">Dosya Yükleme</button>
                </div>
            </div>
            <div id="dfh-fields-container">
                <?php 
                if ( ! empty( $fields ) ) {
                    foreach ( $fields as $index => $field ) {
                        $this->render_single_field_admin( $index, $field );
                    }
                }
                ?>
                <div class="dfh-empty-placeholder <?php echo !empty($fields) ? 'hidden' : ''; ?>">
                    Alan eklemek için yukarıdaki butonlara tıklayın.
                </div>
            </div>
        </div>
        <?php
    }

    public function render_single_field_admin( $index, $data = array() ) {
        $type = isset($data['type']) ? $data['type'] : 'text';
        $label = isset($data['label']) ? $data['label'] : 'Yeni Alan';
        $name = isset($data['name']) ? $data['name'] : 'field_' . uniqid();
        $width = isset($data['width']) ? $data['width'] : '12/12';
        $required = isset($data['required']) ? $data['required'] : 'no';
        $tooltip = isset($data['tooltip']) ? $data['tooltip'] : '';
        $options = isset($data['options']) ? $data['options'] : '';
        // YENİ: Teklif baremi verisi
        $threshold = isset($data['threshold']) ? $data['threshold'] : ''; 
        
        ?>
        <div class="dfh-field-item" data-index="<?php echo $index; ?>">
            <div class="dfh-field-header">
                <span class="dashicons dashicons-move dfh-handle"></span>
                <strong class="dfh-field-title"><?php echo esc_html($label); ?></strong> 
                <span class="dfh-type-badge"><?php echo esc_html($type); ?></span>
                <code class="dfh-var-preview">$fields['<span class="var-name"><?php echo esc_html($name); ?></span>']</code>
                <div class="dfh-actions">
                    <button type="button" class="button-link dfh-duplicate-field" title="Kopyala"><span class="dashicons dashicons-admin-page"></span></button>
                    <button type="button" class="button-link dfh-remove-field" style="color: #b32d2d;" title="Sil"><span class="dashicons dashicons-trash"></span></button>
                    <button type="button" class="button-link dfh-toggle-field" title="Düzenle"><span class="dashicons dashicons-edit"></span></button>
                </div>
            </div>
            <div class="dfh-field-body" style="display: none;">
                <input type="hidden" name="dfh_fields[<?php echo $index; ?>][type]" value="<?php echo $type; ?>">
                
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>Label</label>
                        <input type="text" class="widefat dfh-input-label" name="dfh_fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr($label); ?>">
                    </div>
                    <div class="dfh-col">
                        <label>ID / Name</label>
                        <input type="text" class="widefat dfh-input-name" name="dfh_fields[<?php echo $index; ?>][name]" value="<?php echo esc_attr($name); ?>">
                    </div>
                </div>

                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>Genişlik</label>
                        <select class="widefat" name="dfh_fields[<?php echo $index; ?>][width]">
                            <?php foreach(['12/12','6/12','4/12','3/12','2/12','1/12'] as $w) echo '<option value="'.$w.'" '.selected($width, $w, false).'>'.$w.'</option>'; ?>
                        </select>
                    </div>
                    <div class="dfh-col">
                        <label>Zorunlu?</label>
                        <select class="widefat" name="dfh_fields[<?php echo $index; ?>][required]">
                            <option value="no" <?php selected($required, 'no'); ?>>Hayır</option>
                            <option value="yes" <?php selected($required, 'yes'); ?>>Evet</option>
                        </select>
                    </div>
                </div>

                <div class="dfh-row">
                    <div class="dfh-col">
                         <label>Açıklama / Tooltip</label>
                         <textarea class="widefat" rows="2" name="dfh_fields[<?php echo $index; ?>][tooltip]"><?php echo esc_textarea($tooltip); ?></textarea>
                    </div>
                </div>

                <?php if ( in_array( $type, array( 'select', 'radio', 'image_radio' ) ) ) : ?>
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>Seçenekler</label>
                        <textarea class="widefat" rows="4" name="dfh_fields[<?php echo $index; ?>][options]"><?php echo esc_textarea($options); ?></textarea>
                    </div>
                </div>
                <?php elseif ( $type === 'file' ) : ?>
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label>İzin Verilen Uzantılar (Örn: jpg, png, pdf)</label>
                        <input type="text" class="widefat" name="dfh_fields[<?php echo $index; ?>][options]" value="<?php echo esc_attr($options); ?>">
                    </div>
                </div>
                <?php elseif ( $type === 'number' ) : ?>
                <div class="dfh-row" style="background: #eef5fa; padding: 10px; border-radius: 4px;">
                    <div class="dfh-col">
                        <label>Teklif Baremi (Opsiyonel)</label>
                        <input type="number" class="widefat" name="dfh_fields[<?php echo $index; ?>][threshold]" value="<?php echo esc_attr($threshold); ?>" placeholder="Örn: 1000">
                        <p class="description">Bu değer aşılırsa 'Sepete Ekle' gizlenir, 'Teklif Al' butonu çıkar.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_rule_settings( $post ) {
        $selected_form = get_post_meta( $post->ID, '_dfh_selected_form', true );
        $php_code = get_post_meta( $post->ID, '_dfh_php_code', true );
        $product_ids = get_post_meta( $post->ID, '_dfh_product_ids', true );
        $forms = get_posts(array('post_type' => 'dfh_form', 'numberposts' => -1));
        ?>
        <table class="form-table">
            <tr>
                <th><label>Hangi Form Şablonu?</label></th>
                <td>
                    <select name="dfh_selected_form" class="widefat">
                        <option value="">Bir Form Seçin...</option>
                        <?php foreach($forms as $form): ?>
                            <option value="<?php echo $form->ID; ?>" <?php selected($selected_form, $form->ID); ?>><?php echo $form->post_title; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Hangi Ürünlerde Geçerli?</label></th>
                <td><input type="text" name="dfh_product_ids" value="<?php echo esc_attr($product_ids); ?>" class="widefat" placeholder="Örn: 12, 45, 102"></td>
            </tr>
            <tr>
                <th><label>PHP Hesaplama Kodu</label></th>
                <td><textarea name="dfh_php_code" style="width:100%; height:300px;"><?php echo esc_textarea($php_code); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_data( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( isset( $_POST['dfh_fields'] ) ) { update_post_meta( $post_id, '_dfh_form_fields', $_POST['dfh_fields'] ); }
        if ( isset( $_POST['dfh_selected_form'] ) ) { update_post_meta( $post_id, '_dfh_selected_form', sanitize_text_field( $_POST['dfh_selected_form'] ) ); }
        if ( isset( $_POST['dfh_product_ids'] ) ) { update_post_meta( $post_id, '_dfh_product_ids', sanitize_text_field( $_POST['dfh_product_ids'] ) ); }
        if ( isset( $_POST['dfh_php_code'] ) && current_user_can( 'manage_options' ) ) { update_post_meta( $post_id, '_dfh_php_code', $_POST['dfh_php_code'] ); }
    }
}