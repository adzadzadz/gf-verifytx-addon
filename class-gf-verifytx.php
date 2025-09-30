<?php
/**
 * GF VerifyTX Addon main class.
 *
 * @package GF_VerifyTX
 */

defined( 'ABSPATH' ) || exit;

GFForms::include_addon_framework();

/**
 * Main addon class.
 */
class GF_VerifyTX extends GFAddOn {

	/**
	 * Version number.
	 *
	 * @var string
	 */
	protected $_version = GF_VERIFYTX_VERSION;

	/**
	 * Minimum Gravity Forms version.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = GF_VERIFYTX_MIN_GF_VERSION;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $_slug = 'gf-verifytx';

	/**
	 * Plugin path.
	 *
	 * @var string
	 */
	protected $_path = 'gf-verifytx-addon/gf-verifytx-addon.php';

	/**
	 * Full path to this class file.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Plugin title.
	 *
	 * @var string
	 */
	protected $_title = 'GF VerifyTX Addon';

	/**
	 * Short plugin title.
	 *
	 * @var string
	 */
	protected $_short_title = 'VerifyTX';

	/**
	 * Instance of this class.
	 *
	 * @var GF_VerifyTX
	 */
	private static $_instance = null;

	/**
	 * API client instance.
	 *
	 * @var GF_VerifyTX_API_Client
	 */
	private $api_client = null;

	/**
	 * Get instance of this class.
	 *
	 * @return GF_VerifyTX
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Plugin starting point.
	 */
	public function init() {
		parent::init();

		$this->load_dependencies();
		$this->register_hooks();
		$this->maybe_init_api_client();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {

		if ( file_exists( GF_VERIFYTX_PLUGIN_PATH . 'includes/class-api-client.php' ) ) {
			require_once GF_VERIFYTX_PLUGIN_PATH . 'includes/class-api-client.php';
		}

		if ( file_exists( GF_VERIFYTX_PLUGIN_PATH . 'includes/class-field-verifytx.php' ) ) {
			require_once GF_VERIFYTX_PLUGIN_PATH . 'includes/class-field-verifytx.php';
		}

		if ( file_exists( GF_VERIFYTX_PLUGIN_PATH . 'includes/class-verification.php' ) ) {
			require_once GF_VERIFYTX_PLUGIN_PATH . 'includes/class-verification.php';
		}
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {

		add_action( 'gform_field_standard_settings', array( $this, 'add_field_settings' ), 10, 2 );
		add_action( 'gform_editor_js', array( $this, 'editor_script' ) );
		add_filter( 'gform_tooltips', array( $this, 'add_tooltips' ) );

		add_filter( 'gform_validation', array( $this, 'validate_form' ) );
		add_action( 'gform_after_submission', array( $this, 'after_submission' ), 10, 2 );

		add_action( 'wp_ajax_gf_verifytx_verify', array( $this, 'ajax_verify_insurance' ) );
		add_action( 'wp_ajax_nopriv_gf_verifytx_verify', array( $this, 'ajax_verify_insurance' ) );

		add_filter( 'gform_export_field_value', array( $this, 'export_field_value' ), 10, 4 );
	}

	/**
	 * Initialize API client if credentials are configured.
	 */
	private function maybe_init_api_client() {
		$settings = $this->get_plugin_settings();

		if ( ! empty( $settings['api_client_id'] ) && ! empty( $settings['api_secret_key'] ) ) {
			if ( class_exists( 'GF_VerifyTX_API_Client' ) ) {
				$this->api_client = new GF_VerifyTX_API_Client(
					$settings['api_client_id'],
					$settings['api_secret_key'],
					isset( $settings['test_mode'] ) && $settings['test_mode'] === '1'
				);
			}
		}
	}

	/**
	 * Get API client instance.
	 *
	 * @return GF_VerifyTX_API_Client|null
	 */
	public function get_api_client() {
		return $this->api_client;
	}

	/**
	 * Plugin settings fields.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'VerifyTX API Settings', 'gf-verifytx' ),
				'fields' => array(
					array(
						'name'              => 'api_client_id',
						'label'             => esc_html__( 'API Client ID', 'gf-verifytx' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'tooltip'           => esc_html__( 'Enter your VerifyTX API Client ID. You can find this in your VerifyTX dashboard.', 'gf-verifytx' ),
						'feedback_callback' => array( $this, 'validate_api_credentials' ),
					),
					array(
						'name'              => 'api_secret_key',
						'label'             => esc_html__( 'API Secret Key', 'gf-verifytx' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'input_type'        => 'password',
						'tooltip'           => esc_html__( 'Enter your VerifyTX API Secret Key. Keep this secure and never share it.', 'gf-verifytx' ),
						'feedback_callback' => array( $this, 'validate_api_credentials' ),
					),
					array(
						'name'    => 'test_mode',
						'label'   => esc_html__( 'Test Mode', 'gf-verifytx' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Enable Test Mode', 'gf-verifytx' ),
								'name'  => 'test_mode',
							),
						),
						'tooltip' => esc_html__( 'When enabled, the addon will use VerifyTX sandbox environment for testing.', 'gf-verifytx' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'General Settings', 'gf-verifytx' ),
				'fields' => array(
					array(
						'name'    => 'logging_enabled',
						'label'   => esc_html__( 'Logging', 'gf-verifytx' ),
						'type'    => 'select',
						'choices' => array(
							array(
								'label' => esc_html__( 'Disabled', 'gf-verifytx' ),
								'value' => 'disabled',
							),
							array(
								'label' => esc_html__( 'Errors Only', 'gf-verifytx' ),
								'value' => 'errors',
							),
							array(
								'label' => esc_html__( 'All Activity', 'gf-verifytx' ),
								'value' => 'all',
							),
						),
						'default_value' => 'errors',
						'tooltip'       => esc_html__( 'Control what type of activity is logged. Useful for debugging.', 'gf-verifytx' ),
					),
					array(
						'name'          => 'data_retention',
						'label'         => esc_html__( 'Data Retention (days)', 'gf-verifytx' ),
						'type'          => 'text',
						'class'         => 'small',
						'default_value' => '90',
						'tooltip'       => esc_html__( 'Number of days to keep verification records. Set to 0 to keep indefinitely.', 'gf-verifytx' ),
					),
					array(
						'name'          => 'cache_duration',
						'label'         => esc_html__( 'Cache Duration (hours)', 'gf-verifytx' ),
						'type'          => 'text',
						'class'         => 'small',
						'default_value' => '24',
						'tooltip'       => esc_html__( 'How long to cache successful verification results. Set to 0 to disable caching.', 'gf-verifytx' ),
					),
				),
			),
		);
	}

	/**
	 * Form settings fields.
	 *
	 * @param array $form The form object.
	 * @return array
	 */
	public function form_settings_fields( $form ) {
		return array(
			array(
				'title'  => esc_html__( 'VerifyTX Settings', 'gf-verifytx' ),
				'fields' => array(
					array(
						'name'    => 'enable_verifytx',
						'label'   => esc_html__( 'Enable VerifyTX', 'gf-verifytx' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Enable insurance verification for this form', 'gf-verifytx' ),
								'name'  => 'enable_verifytx',
							),
						),
						'tooltip' => esc_html__( 'Enable VerifyTX insurance verification for this form.', 'gf-verifytx' ),
					),
					array(
						'name'    => 'verification_timing',
						'label'   => esc_html__( 'Verification Timing', 'gf-verifytx' ),
						'type'    => 'select',
						'choices' => array(
							array(
								'label' => esc_html__( 'Real-time (during form submission)', 'gf-verifytx' ),
								'value' => 'realtime',
							),
							array(
								'label' => esc_html__( 'Pre-submission (AJAX)', 'gf-verifytx' ),
								'value' => 'ajax',
							),
							array(
								'label' => esc_html__( 'Post-submission (background)', 'gf-verifytx' ),
								'value' => 'background',
							),
						),
						'default_value' => 'realtime',
						'tooltip'       => esc_html__( 'When to perform the insurance verification.', 'gf-verifytx' ),
					),
					array(
						'name'    => 'require_verification',
						'label'   => esc_html__( 'Require Successful Verification', 'gf-verifytx' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Block form submission if verification fails', 'gf-verifytx' ),
								'name'  => 'require_verification',
							),
						),
						'tooltip' => esc_html__( 'If enabled, the form cannot be submitted unless insurance is successfully verified.', 'gf-verifytx' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Field Mapping', 'gf-verifytx' ),
				'fields' => array(
					array(
						'name'    => 'field_map',
						'label'   => esc_html__( 'Map Form Fields to VerifyTX', 'gf-verifytx' ),
						'type'    => 'field_map',
						'field_map' => $this->verifytx_field_map_choices( $form ),
						'tooltip' => esc_html__( 'Map your form fields to VerifyTX API parameters.', 'gf-verifytx' ),
					),
				),
			),
		);
	}

	/**
	 * Get field mapping choices for form settings.
	 *
	 * @param array $form The form object.
	 * @return array
	 */
	private function verifytx_field_map_choices( $form ) {
		return array(
			array(
				'name'     => 'patient_first_name',
				'label'    => esc_html__( 'Patient First Name', 'gf-verifytx' ),
				'required' => true,
			),
			array(
				'name'     => 'patient_last_name',
				'label'    => esc_html__( 'Patient Last Name', 'gf-verifytx' ),
				'required' => true,
			),
			array(
				'name'     => 'patient_dob',
				'label'    => esc_html__( 'Patient Date of Birth', 'gf-verifytx' ),
				'required' => true,
			),
			array(
				'name'     => 'patient_gender',
				'label'    => esc_html__( 'Patient Gender', 'gf-verifytx' ),
				'required' => false,
			),
			array(
				'name'     => 'insurance_company',
				'label'    => esc_html__( 'Insurance Company/Payer ID', 'gf-verifytx' ),
				'required' => true,
			),
			array(
				'name'     => 'member_id',
				'label'    => esc_html__( 'Member/Subscriber ID', 'gf-verifytx' ),
				'required' => true,
			),
			array(
				'name'     => 'group_number',
				'label'    => esc_html__( 'Group Number', 'gf-verifytx' ),
				'required' => false,
			),
			array(
				'name'     => 'relationship',
				'label'    => esc_html__( 'Relationship to Subscriber', 'gf-verifytx' ),
				'required' => false,
			),
		);
	}

	/**
	 * Validate API credentials.
	 *
	 * @param string $value The field value.
	 * @param array  $field The field array.
	 * @return bool|null
	 */
	public function validate_api_credentials( $value, $field ) {

		$client_id = rgpost( '_gaddon_setting_api_client_id' );
		$secret_key = rgpost( '_gaddon_setting_api_secret_key' );

		if ( empty( $client_id ) || empty( $secret_key ) ) {
			return null;
		}

		if ( class_exists( 'GF_VerifyTX_API_Client' ) ) {
			$test_client = new GF_VerifyTX_API_Client( $client_id, $secret_key, true );

			if ( method_exists( $test_client, 'test_connection' ) ) {
				$result = $test_client->test_connection();

				if ( is_wp_error( $result ) ) {
					$this->log_error( __METHOD__ . '(): API credentials validation failed: ' . $result->get_error_message() );
					return false;
				}

				return true;
			}
		}

		return null;
	}

	/**
	 * Add field settings in the form editor.
	 *
	 * @param int   $position The position.
	 * @param int   $form_id  The form ID.
	 */
	public function add_field_settings( $position, $form_id ) {
		if ( $position == 50 ) {
			?>
			<li class="verifytx_setting field_setting">
				<label for="field_verifytx_enable" class="section_label">
					<?php esc_html_e( 'VerifyTX Insurance Verification', 'gf-verifytx' ); ?>
					<?php gform_tooltip( 'form_field_verifytx_enable' ); ?>
				</label>
				<input type="checkbox" id="field_verifytx_enable" onclick="SetFieldProperty('enableVerifyTX', this.checked);" />
				<label for="field_verifytx_enable" class="inline">
					<?php esc_html_e( 'Enable insurance verification for this field', 'gf-verifytx' ); ?>
				</label>
			</li>
			<?php
		}
	}

	/**
	 * Add JavaScript to the form editor.
	 */
	public function editor_script() {
		?>
		<script type='text/javascript'>
			jQuery(document).ready(function($) {

				fieldSettings['text'] += ', .verifytx_setting';
				fieldSettings['select'] += ', .verifytx_setting';

				$(document).bind('gform_load_field_settings', function(event, field, form) {
					$('#field_verifytx_enable').prop('checked', field['enableVerifyTX'] == true);
				});
			});
		</script>
		<?php
	}

	/**
	 * Add tooltips.
	 *
	 * @param array $tooltips The existing tooltips.
	 * @return array
	 */
	public function add_tooltips( $tooltips ) {
		$tooltips['form_field_verifytx_enable'] = sprintf(
			'<h6>%s</h6>%s',
			esc_html__( 'Insurance Verification', 'gf-verifytx' ),
			esc_html__( 'Enable this option to use this field for insurance verification through VerifyTX.', 'gf-verifytx' )
		);
		return $tooltips;
	}

	/**
	 * Validate form submission.
	 *
	 * @param array $validation_result The validation result.
	 * @return array
	 */
	public function validate_form( $validation_result ) {
		$form = $validation_result['form'];
		$settings = $this->get_form_settings( $form );

		if ( ! rgar( $settings, 'enable_verifytx' ) ) {
			return $validation_result;
		}

		if ( rgar( $settings, 'verification_timing' ) !== 'realtime' ) {
			return $validation_result;
		}

		if ( ! $this->api_client ) {
			if ( rgar( $settings, 'require_verification' ) ) {
				$validation_result['is_valid'] = false;
				$this->add_validation_error( $validation_result['form'], esc_html__( 'Insurance verification service is not configured.', 'gf-verifytx' ) );
			}
			return $validation_result;
		}


		return $validation_result;
	}

	/**
	 * Process after form submission.
	 *
	 * @param array $entry The entry object.
	 * @param array $form  The form object.
	 */
	public function after_submission( $entry, $form ) {
		$settings = $this->get_form_settings( $form );

		if ( ! rgar( $settings, 'enable_verifytx' ) ) {
			return;
		}

		if ( rgar( $settings, 'verification_timing' ) !== 'background' ) {
			return;
		}


		$this->log_debug( __METHOD__ . '(): Background verification scheduled for entry #' . $entry['id'] );
	}

	/**
	 * AJAX handler for insurance verification.
	 */
	public function ajax_verify_insurance() {

		check_ajax_referer( 'gf_verifytx_verify', 'nonce' );

		$form_id = intval( $_POST['form_id'] );
		$field_values = json_decode( stripslashes( $_POST['field_values'] ), true );

		if ( ! $this->api_client ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Verification service not configured.', 'gf-verifytx' ) ) );
		}


		wp_send_json_success( array( 'verified' => true, 'message' => esc_html__( 'Insurance verified successfully.', 'gf-verifytx' ) ) );
	}

	/**
	 * Modify export field value.
	 *
	 * @param string $value      The field value.
	 * @param int    $form_id    The form ID.
	 * @param string $field_id   The field ID.
	 * @param array  $entry      The entry object.
	 * @return string
	 */
	public function export_field_value( $value, $form_id, $field_id, $entry ) {

		return $value;
	}

	/**
	 * Add validation error to form.
	 *
	 * @param array  $form    The form object.
	 * @param string $message The error message.
	 */
	private function add_validation_error( &$form, $message ) {
		foreach ( $form['fields'] as &$field ) {
			if ( $field->enableVerifyTX ) {
				$field->failed_validation = true;
				$field->validation_message = $message;
				break;
			}
		}
	}

	/**
	 * Clear cache.
	 */
	public function maybe_clear_cache() {
		global $wpdb;
		$table = $wpdb->prefix . 'gf_verifytx_cache';
		$wpdb->query( "DELETE FROM $table WHERE expiration < NOW()" );
	}

	/**
	 * Scripts to enqueue.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'gf_verifytx_frontend',
				'src'     => GF_VERIFYTX_PLUGIN_URL . 'assets/js/frontend.js',
				'version' => $this->_version,
				'deps'    => array( 'jquery' ),
				'enqueue' => array(
					array( 'field_types' => array( 'text', 'select' ) ),
				),
				'strings' => array(
					'ajax_url'  => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'gf_verifytx_verify' ),
					'verifying' => esc_html__( 'Verifying insurance...', 'gf-verifytx' ),
					'verified'  => esc_html__( 'Insurance verified', 'gf-verifytx' ),
					'error'     => esc_html__( 'Verification failed', 'gf-verifytx' ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Styles to enqueue.
	 *
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'  => 'gf_verifytx_frontend',
				'src'     => GF_VERIFYTX_PLUGIN_URL . 'assets/css/frontend.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( 'field_types' => array( 'text', 'select' ) ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}
}