<?php
defined( 'ABSPATH' ) || exit;

/**
 * Option storage and the single sanitiser used by the settings page AND the ability.
 */
class Kbs_Settings {

	const DAYS = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

	public static function defaults() {
		return array(
			'business_type'     => 'ProfessionalService',
			'legal_name'        => '',
			'company_number'    => '',
			'street'            => '',
			'locality'          => '',
			'region'            => '',
			'postcode'          => '',
			'country'           => 'GB',
			'service_area_only' => 0,
			'telephone'         => '', // E.164, used for schema and tel: links
			'telephone_display' => '', // as shown on the site
			'email'             => '',
			'price_range'       => '',
			'hours'             => array_fill_keys( self::DAYS, array() ),
			'special_hours'     => array(),
			'area_type'         => 'AdministrativeArea',
			'area_served'       => array(),
			'member_of'         => array(),
			'same_as'           => array(),
			'people'            => array(),
		);
	}

	public static function get() {
		$saved = get_option( KBS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$out          = array_replace( self::defaults(), $saved );
		$out['hours'] = array_replace( array_fill_keys( self::DAYS, array() ), is_array( $out['hours'] ) ? $out['hours'] : array() );
		return $out;
	}

	/**
	 * Patch semantics: top-level keys in $patch replace stored ones; hours merge per day.
	 *
	 * @return array{0: array, 1: string[]} saved details, notes about anything dropped.
	 */
	public static function save_partial( array $patch ) {
		$current = self::get();
		if ( isset( $patch['hours'] ) && is_array( $patch['hours'] ) ) {
			$patch['hours'] = array_replace( $current['hours'], $patch['hours'] );
		}
		$notes  = array();
		$clean  = self::sanitize( array_replace( $current, $patch ), $notes );
		update_option( KBS_OPTION, $clean );
		return array( $clean, $notes );
	}

	/**
	 * register_setting() callback.
	 */
	public static function sanitize_option( $input ) {
		$notes = array();
		$clean = self::sanitize( is_array( $input ) ? $input : array(), $notes );
		if ( function_exists( 'add_settings_error' ) ) { // wp-admin only; absent on REST/CLI requests
			foreach ( $notes as $note ) {
				add_settings_error( 'kbs_details', 'kbs_note', $note, 'warning' );
			}
		}
		return $clean;
	}

	public static function sanitize( array $in, array &$notes = array() ) {
		$d   = self::defaults();
		$out = $d;

		$type = isset( $in['business_type'] ) ? (string) $in['business_type'] : $d['business_type'];
		if ( Kbs_Types::exists( $type ) ) {
			$out['business_type'] = $type;
		} else {
			$notes[] = sprintf( 'Unknown business type "%s"; kept "%s".', $type, $d['business_type'] );
		}

		foreach ( array( 'legal_name', 'company_number', 'street', 'locality', 'region', 'postcode', 'telephone_display', 'price_range' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? self::text( $in[ $k ] ) : '';
		}
		$out['postcode'] = strtoupper( $out['postcode'] );

		$country        = isset( $in['country'] ) ? strtoupper( self::text( $in['country'] ) ) : 'GB';
		$out['country'] = preg_match( '/^[A-Z]{2}$/', $country ) ? $country : 'GB';

		$out['service_area_only'] = empty( $in['service_area_only'] ) ? 0 : 1;

		if ( ! empty( $in['telephone'] ) ) {
			$tel = self::normalise_phone( (string) $in['telephone'], $out['country'] );
			if ( $tel ) {
				$out['telephone'] = $tel;
			} else {
				$notes[] = sprintf( 'Phone "%s" could not be converted to international format; dropped. Use +44… style.', self::text( $in['telephone'] ) );
			}
		}
		if ( '' === $out['telephone_display'] ) {
			$out['telephone_display'] = isset( $in['telephone'] ) ? self::text( $in['telephone'] ) : '';
		}

		if ( ! empty( $in['email'] ) ) {
			$email = sanitize_email( (string) $in['email'] );
			if ( is_email( $email ) ) {
				$out['email'] = $email;
			} else {
				$notes[] = 'Email address was invalid; dropped.';
			}
		}

		// Hours: day => list of ranges. A range is [opens, closes] pairs or "HH:MM-HH:MM"; empty = closed.
		$hours_in = isset( $in['hours'] ) && is_array( $in['hours'] ) ? $in['hours'] : array();
		foreach ( self::DAYS as $day ) {
			$out['hours'][ $day ] = isset( $hours_in[ $day ] ) ? self::ranges( $hours_in[ $day ], $day, $notes ) : array();
		}

		// Special / exceptional hours (holidays, closures).
		$special = isset( $in['special_hours'] ) && is_array( $in['special_hours'] ) ? $in['special_hours'] : array();
		foreach ( $special as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$from = isset( $row['from'] ) ? self::date( $row['from'] ) : '';
			$to   = isset( $row['to'] ) ? self::date( $row['to'] ) : '';
			if ( '' === $from && '' === $to ) {
				continue; // empty repeater row
			}
			if ( '' === $from ) {
				$from = $to;
			}
			if ( '' === $to ) {
				$to = $from;
			}
			if ( '' === $from || $to < $from ) {
				$notes[] = 'A special-hours row had an invalid date range; dropped.';
				continue;
			}
			$closed = ! empty( $row['closed'] );
			$entry  = array(
				'label'  => isset( $row['label'] ) ? self::text( $row['label'] ) : '',
				'from'   => $from,
				'to'     => $to,
				'closed' => $closed ? 1 : 0,
				'opens'  => '',
				'closes' => '',
			);
			if ( ! $closed ) {
				$opens  = isset( $row['opens'] ) ? self::time( $row['opens'] ) : '';
				$closes = isset( $row['closes'] ) ? self::time( $row['closes'] ) : '';
				if ( '' === $opens || '' === $closes || $closes <= $opens ) {
					$notes[] = sprintf( 'Special hours %s: open/close times invalid; treated as closed.', $from );
					$entry['closed'] = 1;
				} else {
					$entry['opens']  = $opens;
					$entry['closes'] = $closes;
				}
			}
			$out['special_hours'][] = $entry;
		}
		usort( $out['special_hours'], function ( $a, $b ) {
			return strcmp( $a['from'], $b['from'] );
		} );

		$area_type        = isset( $in['area_type'] ) ? (string) $in['area_type'] : 'AdministrativeArea';
		$out['area_type'] = in_array( $area_type, array( 'Country', 'AdministrativeArea', 'City' ), true ) ? $area_type : 'AdministrativeArea';
		$out['area_served'] = self::lines( isset( $in['area_served'] ) ? $in['area_served'] : array() );

		$members = isset( $in['member_of'] ) && is_array( $in['member_of'] ) ? $in['member_of'] : array();
		foreach ( $members as $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$name = isset( $m['name'] ) ? self::text( $m['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$entry = array( 'name' => $name, 'url' => '' );
			if ( ! empty( $m['url'] ) ) {
				$entry['url'] = esc_url_raw( (string) $m['url'] );
			}
			$out['member_of'][] = $entry;
		}

		$out['same_as'] = array();
		foreach ( self::lines( isset( $in['same_as'] ) ? $in['same_as'] : array() ) as $url ) {
			$u = esc_url_raw( $url );
			if ( $u && preg_match( '#^https?://#i', $u ) ) {
				$out['same_as'][] = $u;
			} else {
				$notes[] = sprintf( 'Profile URL "%s" is not a valid URL; dropped.', $url );
			}
		}
		$out['same_as'] = array_values( array_unique( $out['same_as'] ) );

		$people = isset( $in['people'] ) && is_array( $in['people'] ) ? array_slice( $in['people'], 0, 10 ) : array();
		foreach ( $people as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$name = isset( $p['name'] ) ? self::text( $p['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$out['people'][] = array(
				'name'      => $name,
				'job_title' => isset( $p['job_title'] ) ? self::text( $p['job_title'] ) : '',
				'page_slug' => isset( $p['page_slug'] ) ? sanitize_title( (string) $p['page_slug'] ) : '',
				'same_as'   => ! empty( $p['same_as'] ) ? esc_url_raw( (string) $p['same_as'] ) : '',
				'founder'   => empty( $p['founder'] ) ? 0 : 1,
			);
		}

		return $out;
	}

	/* ---------- helpers ---------- */

	public static function text( $v ) {
		return is_scalar( $v ) ? trim( sanitize_text_field( (string) $v ) ) : '';
	}

	public static function time( $v ) {
		$v = is_scalar( $v ) ? trim( (string) $v ) : '';
		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $v, $m ) && (int) $m[1] <= 23 && (int) $m[2] <= 59 ) {
			return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
		}
		return '';
	}

	public static function date( $v ) {
		$v = is_scalar( $v ) ? trim( (string) $v ) : '';
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $v;
		}
		return '';
	}

	/** Accepts array or newline/comma separated string. */
	public static function lines( $v ) {
		if ( is_string( $v ) ) {
			$v = preg_split( '/[\r\n,]+/', $v );
		}
		$out = array();
		foreach ( (array) $v as $item ) {
			$t = self::text( $item );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalise a ranges value into [ ['opens'=>'HH:MM','closes'=>'HH:MM'], ... ] (max 2).
	 * Accepts "08:30-17:30", "closed", "09:00-12:00, 13:00-17:00", or arrays of pairs.
	 */
	public static function ranges( $v, $day, array &$notes ) {
		$pairs = array();
		if ( is_string( $v ) ) {
			$v = trim( $v );
			if ( '' === $v || 0 === strcasecmp( $v, 'closed' ) ) {
				return array();
			}
			foreach ( preg_split( '/\s*,\s*/', $v ) as $chunk ) {
				if ( preg_match( '/^(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})$/u', $chunk, $m ) ) {
					$pairs[] = array( 'opens' => $m[1], 'closes' => $m[2] );
				} else {
					$notes[] = sprintf( '%s hours "%s" not understood; treated as closed.', $day, $chunk );
				}
			}
		} elseif ( is_array( $v ) ) {
			foreach ( $v as $r ) {
				if ( is_array( $r ) && ( ! empty( $r['opens'] ) || ! empty( $r['closes'] ) ) ) {
					$pairs[] = array(
						'opens'  => isset( $r['opens'] ) ? $r['opens'] : '',
						'closes' => isset( $r['closes'] ) ? $r['closes'] : '',
					);
				}
			}
		}
		$out = array();
		foreach ( array_slice( $pairs, 0, 2 ) as $p ) {
			$o = self::time( $p['opens'] );
			$c = self::time( $p['closes'] );
			if ( '' === $o || '' === $c || $c <= $o ) {
				$notes[] = sprintf( '%s hours %s–%s invalid (use 24h HH:MM, closing after opening); dropped.', $day, $p['opens'], $p['closes'] );
				continue;
			}
			$out[] = array( 'opens' => $o, 'closes' => $c );
		}
		return $out;
	}

	/** Convert to E.164. Returns '' when it cannot be done confidently. */
	public static function normalise_phone( $raw, $country = 'GB' ) {
		$raw = trim( $raw );
		$has_plus = ( 0 === strpos( $raw, '+' ) );
		$digits   = preg_replace( '/\D+/', '', $raw );
		if ( '' === $digits ) {
			return '';
		}
		if ( $has_plus ) {
			$e164 = '+' . $digits;
		} elseif ( 0 === strpos( $digits, '00' ) ) {
			$e164 = '+' . substr( $digits, 2 );
		} elseif ( 'GB' === $country && 0 === strpos( $digits, '0' ) ) {
			$e164 = '+44' . substr( $digits, 1 );
		} else {
			return '';
		}
		// Handle "+44 (0)1993…" written with the trunk zero kept.
		if ( 0 === strpos( $e164, '+440' ) ) {
			$e164 = '+44' . substr( $e164, 4 );
		}
		return preg_match( '/^\+[1-9]\d{7,14}$/', $e164 ) ? $e164 : '';
	}
}
