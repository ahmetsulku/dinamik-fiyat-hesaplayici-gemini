<?php
if (!defined('ABSPATH')) exit;

class DFH_Converter {
    
    public static function js_to_php($js) {
        if (empty($js)) return 'return $product_price * $quantity;';

        $php = $js;

        // var/let/const → $
        $php = preg_replace('/\b(var|let|const)\s+/', '$', $php);

        // fields.xxx → $fields['xxx']
        $php = preg_replace('/fields\.([a-zA-Z_][a-zA-Z0-9_]*)/', '\$fields[\'$1\']', $php);

        // quantity, product_price
        $php = preg_replace('/\bquantity\b/', '$quantity', $php);
        $php = preg_replace('/\bproduct_price\b/', '$product_price', $php);

        // || 0 pattern
        $php = preg_replace(
            '/\$fields\[\'([^\']+)\'\]\s*\|\|\s*0/',
            '(isset($fields[\'$1\']) ? floatval($fields[\'$1\']) : 0)',
            $php
        );

        // Math functions
        $php = str_replace(array('Math.round', 'Math.floor', 'Math.ceil', 'Math.abs', 'Math.min', 'Math.max', 'Math.pow', 'Math.sqrt', 'Math.PI'),
                          array('round', 'floor', 'ceil', 'abs', 'min', 'max', 'pow', 'sqrt', 'M_PI'), $php);

        // Son satırı return yap
        $lines = array_filter(array_map('trim', explode("\n", $php)));
        if (!empty($lines)) {
            $last = array_pop($lines);
            if (!preg_match('/^return\s/', $last)) {
                $last = 'return ' . rtrim($last, ';') . ';';
            }
            $lines[] = $last;
        }

        return implode("\n", $lines);
    }

    public static function execute_php($code, $fields, $product_price, $quantity) {
        if (empty($code)) return $product_price * $quantity;

        $fields = is_array($fields) ? $fields : array();
        $product_price = floatval($product_price);
        $quantity = max(1, intval($quantity));

        try {
            ob_start();
            $result = eval($code);
            ob_end_clean();

            return is_numeric($result) && $result >= 0 ? floatval($result) : $product_price * $quantity;
        } catch (Throwable $e) {
            error_log('DFH Error: ' . $e->getMessage());
            return $product_price * $quantity;
        }
    }
}
