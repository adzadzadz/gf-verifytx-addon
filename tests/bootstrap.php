<?php

// Load composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants
define('ABSPATH', dirname(__DIR__) . '/');

// Define plugin constants
define('GF_VERIFYTX_PLUGIN_PATH', dirname(__DIR__) . '/');
define('GF_VERIFYTX_VERSION', '0.1.0');

// Mock WordPress functions
if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = '') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = '') {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        // Mock function
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

// Mock global $wpdb
global $wpdb;
$wpdb = new stdClass();
$wpdb->prefix = 'wp_';
$wpdb->prepare = function($query, ...$args) {
    return vsprintf(str_replace('%s', "'%s'", $query), $args);
};
$wpdb->get_var = function($query) {
    return null;
};
$wpdb->get_row = function($query) {
    return null;
};
$wpdb->get_results = function($query) {
    return [];
};
$wpdb->insert = function($table, $data) {
    return 1;
};
$wpdb->update = function($table, $data, $where) {
    return 1;
};
$wpdb->query = function($query) {
    return 0;
};

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        private $error_data = [];

        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys($this->errors);
            return !empty($codes) ? $codes[0] : '';
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
        }
    }
}

// Load plugin classes
require_once dirname(__DIR__) . '/includes/class-api-client.php';
require_once dirname(__DIR__) . '/includes/class-verification.php';