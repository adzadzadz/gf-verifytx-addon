<?php
/**
 * PHPUnit bootstrap file for GF VerifyTX Addon
 *
 * @package GF_VerifyTX\Tests
 */

// Define test environment constants.
define( 'GF_VERIFYTX_TESTS', true );
define( 'GF_VERIFYTX_PLUGIN_FILE', dirname( __DIR__ ) . '/gf-verifytx-addon.php' );
define( 'GF_VERIFYTX_PLUGIN_PATH', dirname( __DIR__ ) . '/' );

// Load Composer autoloader.
$composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! file_exists( $composer_autoload ) ) {
	die( 'Please run `composer install` to install dependencies.' . PHP_EOL );
}
require_once $composer_autoload;

// Bootstrap Brain Monkey for WordPress function mocking.
require_once dirname( __DIR__ ) . '/vendor/brain/monkey/inc/patchwork-loader.php';
Brain\Monkey::setUp();

// Define WordPress constants and functions that plugins expect.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

// Mock WordPress functions commonly used in plugins.
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

// Mock Gravity Forms classes if not available.
if ( ! class_exists( 'GFAddOn' ) ) {
	abstract class GFAddOn {
		protected $_version;
		protected $_min_gravityforms_version;
		protected $_slug;
		protected $_path;
		protected $_full_path;
		protected $_title;
		protected $_short_title;

		public function __construct() {}
		public function init() {}
		public function init_admin() {}
		public function init_frontend() {}
		public function init_ajax() {}
		public function get_plugin_settings() {
			return array();
		}
		public function get_form_settings( $form ) {
			return array();
		}
		public function plugin_settings_fields() {
			return array();
		}
		public function form_settings_fields( $form ) {
			return array();
		}
		public function scripts() {
			return array();
		}
		public function styles() {
			return array();
		}
		public function log_debug( $message ) {}
		public function log_error( $message ) {}
		public static function register( $class ) {}
		public static function get_instance() {}
	}
}

if ( ! class_exists( 'GF_Field' ) ) {
	abstract class GF_Field {
		public $type;
		public $id;
		public $formId;
		public $label;
		public $adminLabel;
		public $isRequired;
		public $size;
		public $errorMessage;
		public $inputs;
		public $failed_validation;
		public $validation_message;

		public function __construct( $data = array() ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}

		public function get_form_editor_field_title() {
			return '';
		}

		public function get_field_input( $form, $value = '', $entry = null ) {
			return '';
		}

		public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
			return $value;
		}

		public function is_entry_detail() {
			return false;
		}

		public function is_form_editor() {
			return false;
		}

		public function get_tabindex() {
			return '';
		}

		public function get_field_placeholder_attribute() {
			return '';
		}

		public function get_conditional_logic_event( $event ) {
			return '';
		}

		public function validate( $value, $form ) {}
		public function sanitize_settings() {}
	}
}

if ( ! class_exists( 'GF_Fields' ) ) {
	class GF_Fields {
		private static $fields = array();

		public static function register( $field ) {
			if ( is_object( $field ) && is_subclass_of( $field, 'GF_Field' ) ) {
				self::$fields[ $field->type ] = $field;
			}
		}

		public static function get_all() {
			return self::$fields;
		}

		public static function get( $type ) {
			return isset( self::$fields[ $type ] ) ? self::$fields[ $type ] : false;
		}
	}
}

if ( ! class_exists( 'GFForms' ) ) {
	class GFForms {
		public static $version = '2.7.0';

		public static function include_addon_framework() {}
	}
}

// Load plugin files for testing.
require_once GF_VERIFYTX_PLUGIN_PATH . 'includes/class-api-client.php';
require_once GF_VERIFYTX_PLUGIN_PATH . 'includes/class-verification.php';
require_once GF_VERIFYTX_PLUGIN_PATH . 'includes/class-field-verifytx.php';
require_once GF_VERIFYTX_PLUGIN_PATH . 'class-gf-verifytx.php';

echo "GF VerifyTX Addon Test Suite\n";
echo "=============================\n\n";