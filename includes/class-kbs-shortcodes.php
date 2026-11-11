<?php
defined( 'ABSPATH' ) || exit;

/**
 * [kbs field="phone|email|address|hours|name|legal_name|company_number"]
 *
 * Use in Elementor (Shortcode widget or any text field that runs shortcodes) so the
 * visible Contact page/footer and the structured data come from the same settings.
 *
 * Attributes:
 *   link="1"          phone/email: wrap in tel:/mailto: link
 *   format="multiline" address: one line per part (default: comma separated)
 *   closed="Closed"   hours: label for closed days
 */
class Kbs_Shortcodes {

	public static function init() {
		add_shortcode( 'kbs', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$a = shortcode_atts(
			array(
				'field'  => '',
				'link'   => '',
				'format' => '',
				'closed' => 'Closed',
			),
			$atts,
			'kbs'
		);
		$d = Kbs_Settings::get();

		switch ( $a['field'] ) {
			case 'name':
				return esc_html( get_bloginfo( 'name' ) );
			case 'legal_name':
				return esc_html( $d['legal_name'] );
			case 'company_number':
				return esc_html( $d['company_number'] );
			case 'email':
				if ( ! $d['email'] ) {
					return '';
				}
				return $a['link']
					? sprintf( '<a href="mailto:%s">%s</a>', esc_attr( antispambot( $d['email'] ) ), esc_html( antispambot( $d['email'] ) ) )
					: esc_html( antispambot( $d['email'] ) );
			case 'phone':
				$shown = $d['telephone_display'] ? $d['telephone_display'] : $d['telephone'];
				if ( ! $shown ) {
					return '';
				}
				return ( $a['link'] && $d['telephone'] )
					? sprintf( '<a href="tel:%s">%s</a>', esc_attr( $d['telephone'] ), esc_html( $shown ) )
					: esc_html( $shown );
			case 'address':
				$parts = array_filter( array( $d['street'], $d['locality'], $d['region'], $d['postcode'] ) );
				$parts = array_map( 'esc_html', $parts );
				return 'multiline' === $a['format'] ? implode( '<br />', $parts ) : implode( ', ', $parts );
			case 'hours':
				return self::hours_html( $d['hours'], $a['closed'] );
		}
		return '';
	}

	/** Collapse consecutive days with identical hours: "Monday – Friday 08:30 – 17:30". */
	public static function hours_html( array $hours, $closed_label = 'Closed' ) {
		$rows = array();
		foreach ( Kbs_Settings::DAYS as $day ) {
			$ranges = isset( $hours[ $day ] ) ? $hours[ $day ] : array();
			$label  = $ranges
				? implode( ', ', array_map( function ( $r ) {
					return $r['opens'] . ' – ' . $r['closes'];
				}, $ranges ) )
				: $closed_label;
			$last = count( $rows ) - 1;
			if ( $last >= 0 && $rows[ $last ]['label'] === $label ) {
				$rows[ $last ]['to'] = $day;
			} else {
				$rows[] = array( 'from' => $day, 'to' => $day, 'label' => $label );
			}
		}
		$out = '<ul class="kbs-hours">';
		foreach ( $rows as $r ) {
			$days = $r['from'] === $r['to'] ? $r['from'] : $r['from'] . ' – ' . $r['to'];
			$out .= '<li><strong>' . esc_html( $days ) . '</strong> ' . esc_html( $r['label'] ) . '</li>';
		}
		return $out . '</ul>';
	}
}
