<?php
defined( 'ABSPATH' ) || exit;

/**
 * Business type map: key => [ label, schema.org types ].
 * Keep in sync with the business-schema skill's types.md.
 */
class Kbs_Types {

	public static function all() {
		$specific = array(
			'AccountingService'    => 'Accountant / bookkeeper',
			'AutoDealer'           => 'Car dealer',
			'AutoRepair'           => 'Garage / car repair',
			'BarOrPub'             => 'Bar or pub',
			'BeautySalon'          => 'Beauty salon',
			'CafeOrCoffeeShop'     => 'Cafe or coffee shop',
			'ChildCare'            => 'Nursery / childcare',
			'Dentist'              => 'Dentist',
			'Electrician'          => 'Electrician',
			'ExerciseGym'          => 'Gym',
			'FinancialService'     => 'Financial adviser',
			'GeneralContractor'    => 'Builder / contractor',
			'HairSalon'            => 'Hair salon',
			'Hotel'                => 'Hotel',
			'HousePainter'         => 'Painter / decorator',
			'HousekeepingService'  => 'Domestic cleaner',
			'HVACBusiness'         => 'Heating / HVAC',
			'InsuranceAgency'      => 'Insurance broker',
			'LegalService'         => 'Solicitor / law firm',
			'Locksmith'            => 'Locksmith',
			'MedicalClinic'        => 'Medical clinic',
			'Plumber'              => 'Plumber',
			'ProfessionalService'  => 'Professional services (general)',
			'RealEstateAgent'      => 'Estate / letting agent',
			'Restaurant'           => 'Restaurant',
			'RoofingContractor'    => 'Roofer',
			'School'               => 'School / training provider',
			'Store'                => 'Shop',
			'VeterinaryCare'       => 'Vet',
		);
		$out = array();
		foreach ( $specific as $key => $label ) {
			$out[ $key ] = array(
				'label' => $label,
				'types' => array( 'Organization', $key ),
			);
		}
		$out['Organization'] = array(
			'label' => 'Organization (no physical presence)',
			'types' => array( 'Organization' ),
		);
		$out['NGO'] = array(
			'label' => 'Charity / NGO',
			'types' => array( 'Organization', 'NGO' ),
		);
		return apply_filters( 'kbs_types', $out );
	}

	public static function exists( $key ) {
		return isset( self::all()[ $key ] );
	}

	public static function schema_types( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ]['types'] : array( 'Organization' );
	}
}
