<?php
defined( 'ABSPATH' ) || exit;

/**
 * Builds the schema fields and attaches them to whichever SEO plugin owns the
 * Organization entity. Falls back to a standalone JSON-LD block when none does,
 * so there is only ever ONE organisation entity on a page.
 */
class Kbs_Schema {

	public static function init() {
		add_filter( 'wpseo_schema_organization', array( __CLASS__, 'yoast_org' ), 20 );
		add_filter( 'wpseo_schema_graph', array( __CLASS__, 'yoast_graph' ), 20 );
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'rankmath' ), 99, 2 );
		add_filter( 'aioseo_schema_output', array( __CLASS__, 'aioseo' ) );
		add_action( 'wp_head', array( __CLASS__, 'standalone' ), 20 );
	}

	public static function seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) ) {
			return 'rankmath';
		}
		if ( function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}
		return 'none';
	}

	/* ---------- building blocks ---------- */

	/** Fields merged onto the organisation node. */
	public static function org_fields( ?array $d = null ) {
		$d = $d ? $d : Kbs_Settings::get();
		$f = array();

		$f['@type'] = Kbs_Types::schema_types( $d['business_type'] );
		if ( $d['legal_name'] ) {
			$f['legalName'] = $d['legal_name'];
		}
		if ( $d['company_number'] ) {
			$f['identifier'] = array(
				'@type'      => 'PropertyValue',
				'propertyID' => 'Company number',
				'value'      => $d['company_number'],
			);
		}
		if ( $d['telephone'] ) {
			$f['telephone'] = $d['telephone'];
		}
		if ( $d['email'] ) {
			$f['email'] = $d['email'];
		}
		if ( $d['price_range'] ) {
			$f['priceRange'] = $d['price_range'];
		}

		$addr = array(
			'@type'           => 'PostalAddress',
			'addressLocality' => $d['locality'],
			'addressRegion'   => $d['region'],
			'addressCountry'  => $d['country'],
		);
		if ( ! $d['service_area_only'] ) {
			$addr['streetAddress'] = $d['street'];
			$addr['postalCode']    = $d['postcode'];
		}
		$addr = array_filter( $addr );
		if ( count( $addr ) > 2 ) { // more than @type + country
			$f['address'] = $addr;
		}

		// Google documents special days as extra openingHoursSpecification entries
		// carrying validFrom/validThrough, so both live in the one list.
		$hours = array_merge( self::hours_specs( $d['hours'] ), self::special_specs( $d['special_hours'] ) );
		if ( $hours ) {
			$f['openingHoursSpecification'] = $hours;
		}

		if ( $d['area_served'] ) {
			$areas = array();
			foreach ( $d['area_served'] as $name ) {
				$areas[] = array( '@type' => $d['area_type'], 'name' => $name );
			}
			$f['areaServed'] = 1 === count( $areas ) ? $areas[0] : $areas;
		}

		if ( $d['member_of'] ) {
			$members = array();
			foreach ( $d['member_of'] as $m ) {
				$members[] = array_filter( array( '@type' => 'Organization', 'name' => $m['name'], 'url' => $m['url'] ) );
			}
			$f['memberOf'] = 1 === count( $members ) ? $members[0] : $members;
		}

		if ( $d['same_as'] ) {
			$f['sameAs'] = $d['same_as'];
		}

		return $f;
	}

	/** Group days that share identical ranges into one specification each. */
	public static function hours_specs( array $hours ) {
		$groups = array();
		foreach ( Kbs_Settings::DAYS as $day ) {
			$ranges = isset( $hours[ $day ] ) ? $hours[ $day ] : array();
			foreach ( $ranges as $i => $r ) {
				$groups[ $i . '|' . $r['opens'] . '|' . $r['closes'] ][] = $day;
			}
		}
		$specs = array();
		foreach ( $groups as $key => $days ) {
			list( , $opens, $closes ) = explode( '|', $key );
			$specs[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $days,
				'opens'     => $opens,
				'closes'    => $closes,
			);
		}
		return $specs;
	}

	/** Upcoming special hours only; past entries are not output. */
	public static function special_specs( array $rows ) {
		$today = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		$specs = array();
		foreach ( $rows as $r ) {
			if ( $r['to'] < $today ) {
				continue;
			}
			$spec = array(
				'@type'        => 'OpeningHoursSpecification',
				'validFrom'    => $r['from'],
				'validThrough' => $r['to'],
				'opens'        => $r['closed'] ? '00:00' : $r['opens'],
				'closes'       => $r['closed'] ? '00:00' : $r['closes'],
			);
			if ( ! empty( $r['label'] ) ) {
				$spec['description'] = $r['label'];
			}
			$specs[] = $spec;
		}
		return $specs;
	}

	public static function person_id( $name ) {
		return home_url( '/#/schema/person/' . sanitize_title( $name ) );
	}

	/** People whose configured page is the one being viewed. */
	public static function people_here( ?array $d = null ) {
		$d    = $d ? $d : Kbs_Settings::get();
		$here = array();
		foreach ( $d['people'] as $p ) {
			if ( '' !== $p['page_slug'] && is_page( $p['page_slug'] ) ) {
				$here[] = $p;
			}
		}
		return $here;
	}

	public static function person_node( array $p, $org_id ) {
		$node = array(
			'@type'    => 'Person',
			'@id'      => self::person_id( $p['name'] ),
			'name'     => $p['name'],
			'worksFor' => array( '@id' => $org_id ),
			'url'      => get_permalink(),
		);
		if ( $p['job_title'] ) {
			$node['jobTitle'] = $p['job_title'];
		}
		if ( $p['same_as'] ) {
			$node['sameAs'] = array( $p['same_as'] );
		}
		return $node;
	}

	/** Apply org fields (and founder refs, only where the Person is in the graph). */
	public static function extend_org( array $org, array $d, array $here ) {
		$org = array_merge( $org, self::org_fields( $d ) );

		if ( empty( $org['image'] ) && ! empty( $org['logo'] ) ) {
			// LocalBusiness subtypes need an image; reuse the logo.
			$org['image'] = is_array( $org['logo'] ) && isset( $org['logo']['@id'] )
				? array( '@id' => $org['logo']['@id'] )
				: $org['logo'];
		}

		$founders = array();
		foreach ( $here as $p ) {
			if ( $p['founder'] ) {
				$founders[] = array( '@id' => self::person_id( $p['name'] ) );
			}
		}
		if ( $founders ) {
			$org['founder'] = 1 === count( $founders ) ? $founders[0] : $founders;
		}
		return $org;
	}

	/* ---------- Yoast ---------- */

	public static function yoast_org( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$d = Kbs_Settings::get();
		return self::extend_org( $data, $d, self::people_here( $d ) );
	}

	public static function yoast_graph( $graph ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}
		$d      = Kbs_Settings::get();
		$org_id = home_url( '/#organization' );
		foreach ( self::people_here( $d ) as $p ) {
			$graph[] = self::person_node( $p, $org_id );
		}
		return $graph;
	}

	/* ---------- Rank Math ---------- */

	public static function rankmath( $data, $jsonld = null ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$d      = Kbs_Settings::get();
		$here   = self::people_here( $d );
		$org_id = home_url( '/#organization' );
		foreach ( $data as $key => $entity ) {
			if ( ! is_array( $entity ) ) {
				continue;
			}
			$types = (array) ( isset( $entity['@type'] ) ? $entity['@type'] : array() );
			if ( 'publisher' === $key || array_intersect( $types, array( 'Organization', 'LocalBusiness' ) ) ) {
				if ( isset( $entity['@id'] ) ) {
					$org_id = $entity['@id'];
				}
				$data[ $key ] = self::extend_org( $entity, $d, $here );
				break;
			}
		}
		foreach ( $here as $i => $p ) {
			$data[ 'kbs_person_' . $i ] = self::person_node( $p, $org_id );
		}
		return $data;
	}

	/* ---------- AIOSEO ---------- */

	public static function aioseo( $graphs ) {
		if ( ! is_array( $graphs ) ) {
			return $graphs;
		}
		$d      = Kbs_Settings::get();
		$here   = self::people_here( $d );
		$org_id = home_url( '/#organization' );
		foreach ( $graphs as $i => $graph ) {
			if ( is_array( $graph ) && in_array( 'Organization', (array) ( isset( $graph['@type'] ) ? $graph['@type'] : array() ), true ) ) {
				if ( isset( $graph['@id'] ) ) {
					$org_id = $graph['@id'];
				}
				$graphs[ $i ] = self::extend_org( $graph, $d, $here );
				break;
			}
		}
		foreach ( $here as $p ) {
			$graphs[] = self::person_node( $p, $org_id );
		}
		return $graphs;
	}

	/* ---------- Standalone (no SEO plugin) ---------- */

	public static function standalone() {
		if ( 'none' !== self::seo_plugin() || is_admin() ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( self::standalone_graph(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function standalone_graph() {
		$d      = Kbs_Settings::get();
		$here   = self::people_here( $d );
		$org_id = home_url( '/#organization' );

		$org = array(
			'@id'  => $org_id,
			'name' => get_bloginfo( 'name' ),
			'url'  => home_url( '/' ),
		);
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		if ( ! $logo ) {
			$logo = get_site_icon_url( 512 );
		}
		if ( $logo ) {
			$org['logo'] = $logo;
		}
		$org = self::extend_org( $org, $d, $here );

		$graph = array( $org );
		foreach ( $here as $p ) {
			$graph[] = self::person_node( $p, $org_id );
		}
		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	/** Preview for the admin page / ability: the org fields as they will be merged. */
	public static function preview() {
		$d = Kbs_Settings::get();
		return array(
			'seo_plugin'    => self::seo_plugin(),
			'organization'  => array_merge(
				array( '@id' => home_url( '/#organization' ) ),
				self::org_fields( $d )
			),
			'people'        => array_map( function ( $p ) {
				return array(
					'name'      => $p['name'],
					'job_title' => $p['job_title'],
					'on_page'   => $p['page_slug'],
					'founder'   => (bool) $p['founder'],
				);
			}, $d['people'] ),
		);
	}
}
