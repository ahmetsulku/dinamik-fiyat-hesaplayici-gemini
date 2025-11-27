<?php
/**
 * Admin paneli ve form builder yönetimi
 */

if (!defined('ABSPATH')) {
    exit;
}

class DFH_Admin {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'), 10, 2);
        add_filter('manage_dfh_form_posts_columns', array($this, 'form_columns'));
        add_action('manage_dfh_form_posts_custom_column', array($this, 'form_column_content'), 10, 2);
    }

    public function enqueue_assets($hook) {
        global $post;
        
        if (!$post || !in_array($post->post_type, array('dfh_form', 'dfh_rule'))) {
            return;
        }

        // jQuery UI Sortable
        wp_enqueue_script('jquery-ui-sortable');
        
        // Media uploader
        wp_enqueue_media();
        
        // Admin CSS
        wp_enqueue_style(
            'dfh-admin-css',
            DFH_URL . 'assets/css/admin.css',
            array(),
            DFH_VERSION
        );
        
        // Admin JS
        wp_enqueue_script(
            'dfh-admin-js',
            DFH_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            DFH_VERSION,
            true
        );

        // Code editor için (sadece rule sayfasında)
        if ($post->post_type === 'dfh_rule') {
            // CodeMirror kütüphanelerini yükle
            wp_enqueue_code_editor(array('type' => 'application/x-httpd-php'));
            wp_enqueue_script('wp-theme-plugin-editor');
            wp_enqueue_style('wp-codemirror');
        }

        // Localize script
        wp_localize_script('dfh-admin-js', 'dfhAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dfh_admin_nonce'),
            'i18n' => array(
                'confirm_delete' => __('Bu alanı silmek istediğinize emin misiniz?', 'dinamik-fiyat'),
                'field_required' => __('Bu alan gereklidir', 'dinamik-fiyat'),
            ),
        ));
    }

    public function add_meta_boxes() {
        // Form Builder Meta Box
        add_meta_box(
            'dfh_form_builder',
            __('Form Oluşturucu (Drag & Drop)', 'dinamik-fiyat'),
            array($this, 'render_form_builder'),
            'dfh_form',
            'normal',
            'high'
        );

        // Rule Settings Meta Box
        add_meta_box(
            'dfh_rule_settings',
            __('Hesaplama Ayarları', 'dinamik-fiyat'),
            array($this, 'render_rule_settings'),
            'dfh_rule',
            'normal',
            'high'
        );

        // PHP Code Meta Box
        add_meta_box(
            'dfh_php_code',
            __('PHP Hesaplama Kodu', 'dinamik-fiyat'),
            array($this, 'render_php_code'),
            'dfh_rule',
            'normal',
            'high'
        );
    }

    public function render_form_builder($post) {
        wp_nonce_field('dfh_save_form', 'dfh_form_nonce');
        
        $fields = get_post_meta($post->ID, '_dfh_form_fields', true);
        if (!is_array($fields)) {
            $fields = array();
        }
        ?>
        <div id="dfh-builder-wrapper">
            <div class="dfh-toolbar">
                <h3><?php _e('Alan Ekle:', 'dinamik-fiyat'); ?></h3>
                <div class="dfh-tools">
                    <button type="button" class="button dfh-add-field" data-type="text">
                        <span class="dashicons dashicons-editor-textcolor"></span> Text
                    </button>
                    <button type="button" class="button dfh-add-field" data-type="number">
                        <span class="dashicons dashicons-calculator"></span> Number
                    </button>
                    <button type="button" class="button dfh-add-field" data-type="textarea">
                        <span class="dashicons dashicons-editor-alignleft"></span> Textarea
                    </button>
                    <button type="button" class="button dfh-add-field" data-type="select">
                        <span class="dashicons dashicons-menu"></span> Select
                    </button>
                    <button type="button" class="button dfh-add-field" data-type="radio">
                        <span class="dashicons dashicons-marker"></span> Radio
                    </button>
                    <button type="button" class="button dfh-add-field" data-type="checkbox">
                        <span class="dashicons dashicons-yes"></span> Checkbox
                    </button>
                    <button type="button" class="button dfh-add-field" data-type="image_radio">
                        <span class="dashicons dashicons-format-gallery"></span> Image Radio
                    </button>
                    <button type="button" class="button dfh-add-field" data-type="file">
                        <span class="dashicons dashicons-upload"></span> Dosya
                    </button>
                </div>
            </div>

            <div id="dfh-fields-container">
                <?php
                if (!empty($fields)) {
                    foreach ($fields as $index => $field) {
                        $this->render_single_field_admin($index, $field);
                    }
                }
                ?>
                <div class="dfh-empty-placeholder <?php echo !empty($fields) ? 'hidden' : ''; ?>">
                    <p><?php _e('Henüz alan eklenmedi. Yukarıdaki butonlardan alan ekleyebilirsiniz.', 'dinamik-fiyat'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_single_field_admin($index, $data = array()) {
        $type = isset($data['type']) ? $data['type'] : 'text';
        $label = isset($data['label']) ? $data['label'] : __('Yeni Alan', 'dinamik-fiyat');
        $name = isset($data['name']) ? $data['name'] : 'field_' . uniqid();
        $width = isset($data['width']) ? $data['width'] : '12/12';
        $required = isset($data['required']) ? $data['required'] : 'no';
        $tooltip = isset($data['tooltip']) ? $data['tooltip'] : '';
        $options = isset($data['options']) ? $data['options'] : '';
        $threshold = isset($data['threshold']) ? $data['threshold'] : '';
        $placeholder = isset($data['placeholder']) ? $data['placeholder'] : '';
        $default_value = isset($data['default']) ? $data['default'] : '';
        ?>
        <div class="dfh-field-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="dfh-field-header">
                <span class="dashicons dashicons-move dfh-handle"></span>
                <strong class="dfh-field-title"><?php echo esc_html($label); ?></strong>
                <span class="dfh-type-badge"><?php echo esc_html($type); ?></span>
                <code class="dfh-var-preview">$fields['<span class="var-name"><?php echo esc_html($name); ?></span>']</code>
                
                <div class="dfh-actions">
                    <button type="button" class="button-link dfh-duplicate-field" title="<?php esc_attr_e('Kopyala', 'dinamik-fiyat'); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                    <button type="button" class="button-link dfh-remove-field" style="color: #b32d2e;" title="<?php esc_attr_e('Sil', 'dinamik-fiyat'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                    <button type="button" class="button-link dfh-toggle-field" title="<?php esc_attr_e('Düzenle', 'dinamik-fiyat'); ?>">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                </div>
            </div>

            <div class="dfh-field-body" style="display: none;">
                <input type="hidden" name="dfh_fields[<?php echo $index; ?>][type]" value="<?php echo esc_attr($type); ?>">
                
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label><?php _e('Label (Görünen İsim)', 'dinamik-fiyat'); ?></label>
                        <input type="text" class="widefat dfh-input-label" 
                               name="dfh_fields[<?php echo $index; ?>][label]" 
                               value="<?php echo esc_attr($label); ?>">
                    </div>
                    <div class="dfh-col">
                        <label><?php _e('ID / Name (Değişken Adı)', 'dinamik-fiyat'); ?></label>
                        <input type="text" class="widefat dfh-input-name" 
                               name="dfh_fields[<?php echo $index; ?>][name]" 
                               value="<?php echo esc_attr($name); ?>">
                        <p class="description"><?php _e('PHP kodunda kullanacağınız değişken adı', 'dinamik-fiyat'); ?></p>
                    </div>
                </div>

                <div class="dfh-row">
                    <div class="dfh-col">
                        <label><?php _e('Placeholder', 'dinamik-fiyat'); ?></label>
                        <input type="text" class="widefat" 
                               name="dfh_fields[<?php echo $index; ?>][placeholder]" 
                               value="<?php echo esc_attr($placeholder); ?>">
                    </div>
                    <div class="dfh-col">
                        <label><?php _e('Varsayılan Değer', 'dinamik-fiyat'); ?></label>
                        <input type="text" class="widefat" 
                               name="dfh_fields[<?php echo $index; ?>][default]" 
                               value="<?php echo esc_attr($default_value); ?>">
                    </div>
                </div>

                <div class="dfh-row">
                    <div class="dfh-col">
                        <label><?php _e('Genişlik (Grid Sistemi)', 'dinamik-fiyat'); ?></label>
                        <select class="widefat" name="dfh_fields[<?php echo $index; ?>][width]">
                            <?php
                            $widths = array('12/12' => '12/12 (Tam genişlik)', '6/12' => '6/12 (Yarım)', '4/12' => '4/12 (1/3)', '3/12' => '3/12 (1/4)', '2/12' => '2/12 (1/6)', '1/12' => '1/12 (1/12)');
                            foreach ($widths as $w => $label_w) {
                                echo '<option value="' . esc_attr($w) . '" ' . selected($width, $w, false) . '>' . esc_html($label_w) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="dfh-col">
                        <label><?php _e('Zorunlu Alan?', 'dinamik-fiyat'); ?></label>
                        <select class="widefat" name="dfh_fields[<?php echo $index; ?>][required]">
                            <option value="no" <?php selected($required, 'no'); ?>><?php _e('Hayır', 'dinamik-fiyat'); ?></option>
                            <option value="yes" <?php selected($required, 'yes'); ?>><?php _e('Evet', 'dinamik-fiyat'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="dfh-row">
                    <div class="dfh-col">
                        <label><?php _e('Açıklama / Tooltip İçeriği', 'dinamik-fiyat'); ?></label>
                        <textarea class="widefat" rows="2" 
                                  name="dfh_fields[<?php echo $index; ?>][tooltip]"><?php echo esc_textarea($tooltip); ?></textarea>
                        <p class="description"><?php _e('Bu metin (?) ikonuna tıklandığında gösterilecek', 'dinamik-fiyat'); ?></p>
                    </div>
                </div>

                <?php if (in_array($type, array('select', 'radio', 'image_radio'))): ?>
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label><?php _e('Seçenekler', 'dinamik-fiyat'); ?></label>
                        <textarea class="widefat" rows="5" 
                                  name="dfh_fields[<?php echo $index; ?>][options]"><?php echo esc_textarea($options); ?></textarea>
                        <p class="description">
                            <?php if ($type === 'image_radio'): ?>
                                <?php _e('Format: değer|Label|resim_url (Her satıra bir seçenek)', 'dinamik-fiyat'); ?>
                            <?php else: ?>
                                <?php _e('Format: değer|Label (Her satıra bir seçenek)', 'dinamik-fiyat'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($type === 'file'): ?>
                <div class="dfh-row">
                    <div class="dfh-col">
                        <label><?php _e('İzin Verilen Dosya Uzantıları', 'dinamik-fiyat'); ?></label>
                        <input type="text" class="widefat" 
                               name="dfh_fields[<?php echo $index; ?>][options]" 
                               value="<?php echo esc_attr($options); ?>" 
                               placeholder="jpg, png, pdf">
                        <p class="description"><?php _e('Virgülle ayırarak yazın', 'dinamik-fiyat'); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($type === 'number'): ?>
                <div class="dfh-row" style="background: #fff3cd; padding: 15px; border-radius: 4px; border-left: 4px solid #ffc107;">
                    <div class="dfh-col">
                        <label><strong><?php _e('Teklif Baremi (Opsiyonel)', 'dinamik-fiyat'); ?></strong></label>
                        <input type="number" class="widefat" 
                               name="dfh_fields[<?php echo $index; ?>][threshold]" 
                               value="<?php echo esc_attr($threshold); ?>" 
                               placeholder="<?php esc_attr_e('Örn: 1000', 'dinamik-fiyat'); ?>" 
                               step="1">
                        <p class="description">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Bu değer aşıldığında "Sepete Ekle" butonu gizlenir ve "Teklif Al" butonu görünür.', 'dinamik-fiyat'); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_shortcode_info($post) {
        ?>
        <div style="padding: 10px;">
            <p><?php _e('Bu formu ürün sayfasında göstermek için aşağıdaki shortcode\'u kullanın:', 'dinamik-fiyat'); ?></p>
            <code style="display: block; padding: 10px; background: #f0f0f0; margin: 10px 0;">[dfh_form id="<?php echo $post->ID; ?>"]</code>
            <p class="description"><?php _e('Shortcode\'u ürün açıklamasına veya tema template dosyalarına ekleyebilirsiniz.', 'dinamik-fiyat'); ?></p>
        </div>
        <?php
    }

    public function render_rule_settings($post) {
        wp_nonce_field('dfh_save_rule', 'dfh_rule_nonce');
        
        $selected_form = get_post_meta($post->ID, '_dfh_selected_form', true);
        $product_ids = get_post_meta($post->ID, '_dfh_product_ids', true);
        
        $forms = get_posts(array(
            'post_type' => 'dfh_form',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        ?>
        <table class="form-table">
            <tr>
                <th style="width: 200px;">
                    <label for="dfh_selected_form"><?php _e('Hangi Form Şablonu?', 'dinamik-fiyat'); ?></label>
                </th>
                <td>
                    <select name="dfh_selected_form" id="dfh_selected_form" class="widefat" required>
                        <option value=""><?php _e('Bir form seçin...', 'dinamik-fiyat'); ?></option>
                        <?php foreach ($forms as $form): ?>
                            <option value="<?php echo esc_attr($form->ID); ?>" <?php selected($selected_form, $form->ID); ?>>
                                <?php echo esc_html($form->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Bu kuralla kullanılacak form şablonunu seçin', 'dinamik-fiyat'); ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="dfh_product_ids"><?php _e('Hangi Ürünlerde Geçerli?', 'dinamik-fiyat'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           name="dfh_product_ids" 
                           id="dfh_product_ids" 
                           value="<?php echo esc_attr($product_ids); ?>" 
                           class="widefat" 
                           placeholder="<?php esc_attr_e('Örn: 12, 45, 102', 'dinamik-fiyat'); ?>">
                    <p class="description"><?php _e('Ürün ID\'lerini virgülle ayırarak yazın. Boş bırakırsanız hiçbir üründe görünmez.', 'dinamik-fiyat'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_php_code($post) {
        $php_code = get_post_meta($post->ID, '_dfh_php_code', true);
        
        if (empty($php_code)) {
            $php_code = $this->get_default_php_code();
        }
        ?>
        <div style="margin-bottom: 15px; padding: 15px; background: #e8f5e9; border-left: 4px solid #4caf50;">
            <h4 style="margin-top: 0;"><?php _e('📖 Nasıl Kullanılır?', 'dinamik-fiyat'); ?></h4>
            <ul style="margin: 0;">
                <li><?php _e('<code>$fields[\'alan_adi\']</code> ile form verilerine erişebilirsiniz', 'dinamik-fiyat'); ?></li>
                <li><?php _e('<code>$product_price</code> ürünün mevcut fiyatını içerir', 'dinamik-fiyat'); ?></li>
                <li><?php _e('<code>$quantity</code> sepete eklenecek miktarı içerir', 'dinamik-fiyat'); ?></li>
                <li><?php _e('Fonksiyon hesaplanan fiyatı <strong>return</strong> etmelidir', 'dinamik-fiyat'); ?></li>
            </ul>
        </div>

        <textarea name="dfh_php_code" id="dfh_php_code" rows="20" style="width: 100%; font-family: 'Courier New', Consolas, Monaco, monospace; font-size: 13px; line-height: 1.6;"><?php echo esc_textarea($php_code); ?></textarea>
        
        <p class="description" style="margin-top: 10px;">
            <?php _e('⚠️ Bu alanda PHP kodu yazacaksınız. Hatalı kod sitenizin çalışmamasına neden olabilir.', 'dinamik-fiyat'); ?>
        </p>
        <?php
    }

    private function get_default_php_code() {
        return <<<'PHP'
// Örnek hesaplama fonksiyonu
// Form alanlarındaki değerleri $fields['alan_adi'] ile alabilirsiniz

$base_price = $product_price; // Ürünün mevcut fiyatı

// Örnek: Yükseklik ve genişlik alanları varsa
if (isset($fields['yukseklik']) && isset($fields['genislik'])) {
    $yukseklik = floatval($fields['yukseklik']);
    $genislik = floatval($fields['genislik']);
    
    // Alan hesaplama (m²)
    $alan = $yukseklik * $genislik;
    
    // m² başına fiyat
    $m2_fiyat = 50; // TL
    
    // Toplam fiyat
    $calculated_price = $alan * $m2_fiyat;
    
    return $calculated_price;
}

// Örnek: Malzeme seçimi varsa
if (isset($fields['malzeme'])) {
    $malzeme = $fields['malzeme'];
    
    $malzeme_fiyatlari = array(
        'aluminyum' => 100,
        'paslanmaz' => 150,
        'galvaniz' => 80
    );
    
    if (isset($malzeme_fiyatlari[$malzeme])) {
        return $malzeme_fiyatlari[$malzeme] * $quantity;
    }
}

// Varsayılan: Ürünün kendi fiyatını döndür
return $base_price * $quantity;
PHP;
    }

    public function save_meta_data($post_id, $post) {
        // Otomatik kayıt kontrolü
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Yetki kontrolü
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Form verileri
        if ($post->post_type === 'dfh_form') {
            if (!isset($_POST['dfh_form_nonce']) || !wp_verify_nonce($_POST['dfh_form_nonce'], 'dfh_save_form')) {
                return;
            }

            if (isset($_POST['dfh_fields'])) {
                update_post_meta($post_id, '_dfh_form_fields', $_POST['dfh_fields']);
            }
        }

        // Rule verileri
        if ($post->post_type === 'dfh_rule') {
            if (!isset($_POST['dfh_rule_nonce']) || !wp_verify_nonce($_POST['dfh_rule_nonce'], 'dfh_save_rule')) {
                return;
            }

            if (isset($_POST['dfh_selected_form'])) {
                update_post_meta($post_id, '_dfh_selected_form', sanitize_text_field($_POST['dfh_selected_form']));
            }

            if (isset($_POST['dfh_product_ids'])) {
                update_post_meta($post_id, '_dfh_product_ids', sanitize_text_field($_POST['dfh_product_ids']));
            }

            if (isset($_POST['dfh_php_code']) && current_user_can('manage_options')) {
                update_post_meta($post_id, '_dfh_php_code', $_POST['dfh_php_code']);
            }
        }
    }

    public function form_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['field_count'] = __('Alan Sayısı', 'dinamik-fiyat');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    public function form_column_content($column, $post_id) {
        switch ($column) {
            case 'field_count':
                $fields = get_post_meta($post_id, '_dfh_form_fields', true);
                echo is_array($fields) ? count($fields) : 0;
                break;
        }
    }
}