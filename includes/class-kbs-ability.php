<?php
defined( 'ABSPATH' ) || exit;

/**
 * WordPress Abilities API (WP 6.9+) registration, so tools and agents can read and
 * set business details through the plugin's own validation instead of raw option writes.
 *
 *   kaydee-business-schema/get-details   read current details
 *   kaydee-business-schema/set-details   patch details (top-level keys replace; hours merge per day)
 *   kaydee-business-schema/preview       the structured data fields as they will be output
 */
class Kbs_Ability {

	const CATEGORY = 'business-details';

	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // WordPress < 6.9
		}
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
	}

	public static function category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => 'Business details',
				'description' => 'Read and update business details used for structured data and the visible site.',
			)
		);
	}

	public static function can() {
		return current_user_can( 'manage_options' );
	}

	public static function register() {
		wp_register_ability(
			'kaydee-business-schema/get-details',
			array(
				'label'               => 'Get business details',
				'description'         => 'Returns the stored business details (type, address, phone, email, hours, special hours, area served, memberships, profiles, people).',
				'category'            => self::CATEGORY,
				'execute_callback'    => function () {
					return array( 'details' => Kbs_Settings::get() );
				},
				'permission_callback' => array( __CLASS__, 'can' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'readonly' => true ),
				),
			)
		);

		wp_register_ability(
			'kaydee-business-schema/preview',
			array(
				'label'               => 'Preview business structured data',
				'description'         => 'Returns the organization fields that will be merged into the site\'s schema output, and which SEO plugin owns the Organization entity.',
				'category'            => self::CATEGORY,
				'execute_callback'    => function () {
					return Kbs_Schema::preview();
				},
				'permission_callback' => array( __CLASS__, 'can' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'readonly' => true ),
				),
			)
		);

		wp_register_ability(
			'kaydee-business-schema/set-details',
			array(
				'label'               => 'Set business details',
				'description'         => 'Updates business details. Send only the keys to change. Hours accept "08:30-17:30", "closed", or a list of {opens, closes}. Values are validated; anything dropped is listed in "notes".',
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'business_type'     => array( 'type' => 'string', 'description' => 'Key from the plugin type list, e.g. AutoRepair, ProfessionalService, AccountingService.' ),
						'legal_name'        => array( 'type' => 'string' ),
						'company_number'    => array( 'type' => 'string' ),
						'street'            => array( 'type' => 'string' ),
						'locality'          => array( 'type' => 'string' ),
						'region'            => array( 'type' => 'string' ),
						'postcode'          => array( 'type' => 'string' ),
						'country'           => array( 'type' => 'string', 'description' => 'ISO 3166-1 alpha-2, e.g. GB' ),
						'service_area_only' => array( 'type' => 'boolean' ),
						'telephone'         => array( 'type' => 'string', 'description' => 'Any format; stored as E.164 and as shown.' ),
						'telephone_display' => array( 'type' => 'string' ),
						'email'             => array( 'type' => 'string' ),
						'price_range'       => array( 'type' => 'string' ),
						'hours'             => array( 'type' => 'object', 'description' => 'Keys Monday..Sunday.' ),
						'special_hours'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Items: {label, from:YYYY-MM-DD, to, closed:boolean, opens, closes}.' ),
						'area_type'         => array( 'type' => 'string', 'enum' => array( 'Country', 'AdministrativeArea', 'City' ) ),
						'area_served'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'member_of'         => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Items: {name, url}.' ),
						'same_as'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'people'            => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Items: {name, job_title, page_slug, same_as, founder:boolean}.' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) {
					list( $saved, $notes ) = Kbs_Settings::save_partial( is_array( $input ) ? $input : array() );
					return array(
						'saved'   => true,
						'notes'   => $notes,
						'details' => $saved,
						'preview' => Kbs_Schema::preview(),
					);
				},
				'permission_callback' => array( __CLASS__, 'can' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);
	}
}
