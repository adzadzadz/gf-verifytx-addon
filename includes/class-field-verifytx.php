<?php
/**
 * VerifyTX Custom Field for Gravity Forms
 *
 * @package GF_VerifyTX
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GF_Field' ) ) {
	return;
}

/**
 * Custom VerifyTX verification field.
 */
class GF_Field_VerifyTX extends GF_Field {

	/**
	 * Field type.
	 *
	 * @var string
	 */
	public $type = 'verifytx';

	/**
	 * Field button text.
	 *
	 * @return string
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => __( 'Insurance Verification', 'gf-verifytx' ),
			'icon'  => 'dashicons-shield-alt',
		);
	}

	/**
	 * Field title.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Insurance Verification', 'gf-verifytx' );
	}

	/**
	 * Field settings for the form editor.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'description_setting',
			'rules_setting',
			'error_message_setting',
			'css_class_setting',
			'conditional_logic_field_setting',
			'verifytx_settings',
		);
	}

	/**
	 * Is this field conditional logic supported.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Field markup for form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_markup() {
		return '{FIELD}';
	}

	/**
	 * Get field input markup.
	 *
	 * @param array  $form  The form object.
	 * @param string $value The field value.
	 * @param array  $entry The entry object.
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$value          = esc_attr( $value );
		$size           = $this->size;
		$class_suffix   = $is_form_editor ? '_admin' : '';
		$class          = $size . $class_suffix;
		$css_class      = trim( esc_attr( $class ) . ' gf-verifytx-field' );

		$tabindex              = $this->get_tabindex();
		$logic_event           = $this->get_conditional_logic_event( 'change' );
		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';

		$verification_status = $this->get_verification_status( $value );

		$input = '<div class="ginput_container ginput_container_verifytx">';

		if ( $is_entry_detail ) {
			if ( ! empty( $value ) ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					$input .= $this->display_verification_results( $decoded );
				} else {
					$input .= '<div class="gf-verifytx-no-results">' . esc_html__( 'No verification data available', 'gf-verifytx' ) . '</div>';
				}
			} else {
				$input .= '<div class="gf-verifytx-not-verified">' . esc_html__( 'Not verified', 'gf-verifytx' ) . '</div>';
			}
		} elseif ( $is_form_editor ) {
			$input .= '<div class="gf-verifytx-preview">';
			$input .= '<button type="button" class="button gf-verifytx-verify-btn" disabled>';
			$input .= esc_html__( 'Verify Insurance', 'gf-verifytx' );
			$input .= '</button>';
			$input .= '<div class="gf-verifytx-status-message">';
			$input .= esc_html__( 'Insurance verification will be available on the live form', 'gf-verifytx' );
			$input .= '</div>';
			$input .= '</div>';
		} else {
			$input .= '<input name="input_' . $id . '" id="' . $field_id . '" type="hidden" value="' . $value . '" class="' . $css_class . '" ' . $tabindex . ' ' . $logic_event . ' ' . $placeholder_attribute . ' ' . $required_attribute . ' ' . $invalid_attribute . ' ' . $disabled_text . '/>';

			$input .= '<div class="gf-verifytx-container" data-field-id="' . $id . '" data-form-id="' . $form_id . '">';

			$button_text = $this->verifyButtonText ?? __( 'Verify Insurance', 'gf-verifytx' );
			$input .= '<button type="button" class="button gf-verifytx-verify-btn" data-field-id="' . $id . '">';
			$input .= esc_html( $button_text );
			$input .= '</button>';

			$input .= '<div class="gf-verifytx-results" id="verifytx_results_' . $id . '">';
			if ( ! empty( $verification_status ) ) {
				$input .= $verification_status;
			}
			$input .= '</div>';

			$input .= '<div class="gf-verifytx-coverage-details" id="verifytx_coverage_' . $id . '"></div>';

			$input .= '</div>';
		}

		$input .= '</div>';

		return $input;
	}

	/**
	 * Get verification status display.
	 *
	 * @param string $value Field value.
	 * @return string HTML markup.
	 */
	private function get_verification_status( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$data = json_decode( $value, true );
		if ( ! is_array( $data ) ) {
			return '';
		}

		$status = isset( $data['status'] ) ? $data['status'] : 'unknown';
		$status_class = 'gf-verifytx-status-' . sanitize_html_class( strtolower( $status ) );

		$html = '<div class="gf-verifytx-status ' . $status_class . '">';
		$html .= '<span class="status-label">' . esc_html__( 'Status:', 'gf-verifytx' ) . '</span> ';
		$html .= '<span class="status-value">' . esc_html( ucfirst( $status ) ) . '</span>';

		if ( ! empty( $data['verified_at'] ) ) {
			$html .= '<span class="verified-at"> (' . esc_html( sprintf( __( 'Verified: %s', 'gf-verifytx' ), date_i18n( get_option( 'date_format' ), strtotime( $data['verified_at'] ) ) ) ) . ')</span>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Display verification results.
	 *
	 * @param array $data Verification data.
	 * @return string HTML markup.
	 */
	private function display_verification_results( $data ) {
		$html = '<div class="gf-verifytx-results-detail">';

		if ( isset( $data['status'] ) ) {
			$status_class = 'status-' . sanitize_html_class( strtolower( $data['status'] ) );
			$html .= '<div class="verification-status ' . $status_class . '">';
			$html .= '<strong>' . esc_html__( 'Coverage Status:', 'gf-verifytx' ) . '</strong> ';
			$html .= '<span class="status-badge">' . esc_html( ucfirst( $data['status'] ) ) . '</span>';
			$html .= '</div>';
		}

		if ( isset( $data['payer'] ) ) {
			$html .= '<div class="verification-payer">';
			$html .= '<strong>' . esc_html__( 'Insurance Provider:', 'gf-verifytx' ) . '</strong> ';
			$html .= esc_html( $data['payer']['name'] ?? 'Unknown' );
			$html .= '</div>';
		}

		if ( isset( $data['subscriber'] ) ) {
			$html .= '<div class="verification-subscriber">';
			$html .= '<strong>' . esc_html__( 'Subscriber:', 'gf-verifytx' ) . '</strong> ';
			$html .= esc_html( $data['subscriber']['first_name'] . ' ' . $data['subscriber']['last_name'] );
			if ( ! empty( $data['subscriber']['member_id'] ) ) {
				$html .= ' (ID: ' . esc_html( $data['subscriber']['member_id'] ) . ')';
			}
			$html .= '</div>';
		}

		if ( isset( $data['benefits'] ) && is_array( $data['benefits'] ) ) {
			$html .= '<div class="verification-benefits">';
			$html .= '<strong>' . esc_html__( 'Benefits Summary:', 'gf-verifytx' ) . '</strong>';
			$html .= $this->format_benefits( $data['benefits'] );
			$html .= '</div>';
		}

		if ( isset( $data['verified_at'] ) ) {
			$html .= '<div class="verification-date">';
			$html .= '<strong>' . esc_html__( 'Verified On:', 'gf-verifytx' ) . '</strong> ';
			$html .= esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $data['verified_at'] ) ) );
			$html .= '</div>';
		}

		if ( isset( $data['vob_id'] ) ) {
			$html .= '<div class="verification-id">';
			$html .= '<strong>' . esc_html__( 'Verification ID:', 'gf-verifytx' ) . '</strong> ';
			$html .= '<code>' . esc_html( $data['vob_id'] ) . '</code>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Format benefits display.
	 *
	 * @param array $benefits Benefits array.
	 * @return string HTML markup.
	 */
	private function format_benefits( $benefits ) {
		$html = '<ul class="benefits-list">';

		foreach ( $benefits as $benefit ) {
			$html .= '<li class="benefit-item">';
			$html .= '<span class="benefit-type">' . esc_html( ucfirst( $benefit['type'] ?? 'General' ) ) . ':</span>';

			if ( isset( $benefit['in_network'] ) ) {
				$network = $benefit['in_network'];
				$items = array();

				if ( isset( $network['copay'] ) ) {
					$items[] = sprintf( __( 'Copay: $%s', 'gf-verifytx' ), number_format( $network['copay'], 2 ) );
				}

				if ( isset( $network['deductible'] ) ) {
					$items[] = sprintf(
						__( 'Deductible: $%s (Met: $%s)', 'gf-verifytx' ),
						number_format( $network['deductible']['amount'] ?? 0, 2 ),
						number_format( $network['deductible']['met'] ?? 0, 2 )
					);
				}

				if ( isset( $network['out_of_pocket'] ) ) {
					$items[] = sprintf(
						__( 'Out of Pocket Max: $%s (Met: $%s)', 'gf-verifytx' ),
						number_format( $network['out_of_pocket']['max'] ?? 0, 2 ),
						number_format( $network['out_of_pocket']['met'] ?? 0, 2 )
					);
				}

				if ( ! empty( $items ) ) {
					$html .= ' ' . implode( ', ', $items );
				}
			}

			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Validate field value.
	 *
	 * @param string|array $value The field value.
	 * @param array        $form  The form object.
	 */
	public function validate( $value, $form ) {
		if ( $this->isRequired && empty( $value ) ) {
			$this->failed_validation = true;
			$this->validation_message = empty( $this->errorMessage )
				? __( 'Insurance verification is required.', 'gf-verifytx' )
				: $this->errorMessage;
			return;
		}

		if ( ! empty( $value ) ) {
			$data = json_decode( $value, true );

			if ( ! is_array( $data ) ) {
				$this->failed_validation = true;
				$this->validation_message = __( 'Invalid verification data.', 'gf-verifytx' );
				return;
			}

			if ( $this->requireActiveStatus && isset( $data['status'] ) && strtolower( $data['status'] ) !== 'active' ) {
				$this->failed_validation = true;
				$this->validation_message = __( 'Insurance coverage must be active.', 'gf-verifytx' );
				return;
			}

			if ( ! empty( $data['error'] ) ) {
				$this->failed_validation = true;
				$this->validation_message = sprintf(
					__( 'Verification failed: %s', 'gf-verifytx' ),
					$data['error']
				);
				return;
			}
		}
	}

	/**
	 * Get the field value to save to the entry.
	 *
	 * @param string|array $value                The field value.
	 * @param array        $form                 The form object.
	 * @param string       $input_name           The input name.
	 * @param int          $lead_id              The entry ID.
	 * @param array        $lead                 The entry object.
	 * @return string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( is_array( $value ) ) {
			return wp_json_encode( $value );
		}

		$decoded = json_decode( $value, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $value;
		}

		return '';
	}

	/**
	 * Format the entry value for display.
	 *
	 * @param string|array $value    The field value.
	 * @param string       $currency The currency.
	 * @param bool         $use_text Whether to use text.
	 * @param string       $format   The format.
	 * @param string       $media    The media.
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( empty( $value ) ) {
			return __( 'Not verified', 'gf-verifytx' );
		}

		$data = json_decode( $value, true );
		if ( ! is_array( $data ) ) {
			return $value;
		}

		if ( $format === 'text' ) {
			$output = array();

			if ( isset( $data['status'] ) ) {
				$output[] = 'Status: ' . $data['status'];
			}

			if ( isset( $data['payer']['name'] ) ) {
				$output[] = 'Payer: ' . $data['payer']['name'];
			}

			if ( isset( $data['subscriber'] ) ) {
				$output[] = 'Subscriber: ' . $data['subscriber']['first_name'] . ' ' . $data['subscriber']['last_name'];
			}

			return implode( "\n", $output );
		}

		return $this->display_verification_results( $data );
	}

	/**
	 * Get value for merge tag.
	 *
	 * @param string|array $value      The field value.
	 * @param string       $input_id   The input ID.
	 * @param array        $entry      The entry object.
	 * @param array        $form       The form object.
	 * @param string       $modifier   The merge tag modifier.
	 * @param string|array $raw_value  The raw field value.
	 * @param bool         $url_encode Whether to URL encode.
	 * @param bool         $esc_html   Whether to escape HTML.
	 * @param string       $format     The format.
	 * @param bool         $nl2br      Whether to convert newlines to breaks.
	 * @return string
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		if ( empty( $value ) ) {
			return '';
		}

		$data = json_decode( $value, true );
		if ( ! is_array( $data ) ) {
			return $value;
		}

		switch ( $modifier ) {
			case 'status':
				return isset( $data['status'] ) ? $data['status'] : '';

			case 'payer':
				return isset( $data['payer']['name'] ) ? $data['payer']['name'] : '';

			case 'member_id':
				return isset( $data['subscriber']['member_id'] ) ? $data['subscriber']['member_id'] : '';

			case 'vob_id':
				return isset( $data['vob_id'] ) ? $data['vob_id'] : '';

			case 'json':
				return wp_json_encode( $data );

			default:
				$output = isset( $data['status'] ) ? 'Status: ' . $data['status'] : 'Not verified';
				if ( isset( $data['payer']['name'] ) ) {
					$output .= ' | Payer: ' . $data['payer']['name'];
				}
				return $output;
		}
	}

	/**
	 * Sanitize settings.
	 *
	 * @return void
	 */
	public function sanitize_settings() {
		parent::sanitize_settings();

		if ( isset( $this->verifyButtonText ) ) {
			$this->verifyButtonText = sanitize_text_field( $this->verifyButtonText );
		}

		if ( isset( $this->requireActiveStatus ) ) {
			$this->requireActiveStatus = (bool) $this->requireActiveStatus;
		}

		if ( isset( $this->autoVerify ) ) {
			$this->autoVerify = (bool) $this->autoVerify;
		}
	}

	/**
	 * Get field settings fields.
	 *
	 * @return array
	 */
	public function get_form_editor_inline_script() {
		$script = sprintf( "
			function SetDefaultValues_%s(field) {
				field.label = '%s';
				field.verifyButtonText = '%s';
				field.requireActiveStatus = false;
				field.autoVerify = false;
			}",
			$this->type,
			esc_js( __( 'Insurance Verification', 'gf-verifytx' ) ),
			esc_js( __( 'Verify Insurance', 'gf-verifytx' ) )
		);

		return $script;
	}
}

GF_Fields::register( new GF_Field_VerifyTX() );