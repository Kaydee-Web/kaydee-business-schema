<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings → Business Details.
 */
class Kbs_Admin {

	const GROUP = 'kbs_group';
	const SLUG  = 'kbs-business-details';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) ); // not admin_init: REST and WP-CLI writes must hit the sanitiser too
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KBS_FILE ), array( __CLASS__, 'links' ) );
	}

	public static function register() {
		register_setting(
			self::GROUP,
			KBS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Kbs_Settings', 'sanitize_option' ),
				'default'           => Kbs_Settings::defaults(),
			)
		);
	}

	public static function menu() {
		add_options_page( 'Business Details', 'Business Details', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">Settings</a>' );
		return $links;
	}

	private static function n( $path ) {
		return esc_attr( KBS_OPTION . $path );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$d       = Kbs_Settings::get();
		$preview = Kbs_Schema::preview();
		?>
		<div class="wrap kbs-wrap">
			<h1>Business Details</h1>
			<p>These details feed the structured data (<?php echo esc_html( $preview['seo_plugin'] ); ?> mode) and the <code>[kbs]</code> shortcodes. Keep them in step with your Google Business Profile.</p>
			<?php settings_errors( 'kbs_details' ); ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2>Business</h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="kbs_type">Business type</label></th><td>
						<select id="kbs_type" name="<?php echo self::n( '[business_type]' ); ?>">
							<?php foreach ( Kbs_Types::all() as $key => $t ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $d['business_type'], $key ); ?>><?php echo esc_html( $t['label'] ); ?></option>
							<?php endforeach; ?>
						</select></td></tr>
					<?php
					self::row( 'legal_name', 'Legal name', $d['legal_name'] );
					self::row( 'company_number', 'Company number', $d['company_number'] );
					self::row( 'price_range', 'Price range', $d['price_range'], 'Optional, e.g. ££' );
					?>
				</table>

				<h2>Contact</h2>
				<table class="form-table" role="presentation">
					<?php
					self::row( 'telephone', 'Phone', $d['telephone_display'] ? $d['telephone_display'] : $d['telephone'], 'Any format; converted to +44… for the structured data' );
					self::row( 'email', 'Email', $d['email'], '', 'email' );
					self::row( 'street', 'Street address', $d['street'] );
					self::row( 'locality', 'Town / city', $d['locality'] );
					self::row( 'region', 'County / region', $d['region'] );
					self::row( 'postcode', 'Postcode', $d['postcode'] );
					self::row( 'country', 'Country (2-letter)', $d['country'] );
					?>
					<tr><th>Service-area business</th><td><label><input type="checkbox" name="<?php echo self::n( '[service_area_only]' ); ?>" value="1" <?php checked( $d['service_area_only'], 1 ); ?> /> Customers aren't served at this address (omit street and postcode from the markup)</label></td></tr>
				</table>

				<h2>Opening hours</h2>
				<p class="description">24-hour times. Leave blank for closed. Use the second range for split days (e.g. lunch break).</p>
				<table class="widefat striped" style="max-width:640px">
					<thead><tr><th>Day</th><th>Opens</th><th>Closes</th><th>Opens (2nd)</th><th>Closes (2nd)</th></tr></thead>
					<tbody>
					<?php foreach ( Kbs_Settings::DAYS as $day ) : ?>
						<tr><td><?php echo esc_html( $day ); ?></td>
						<?php for ( $i = 0; $i < 2; $i++ ) : foreach ( array( 'opens', 'closes' ) as $f ) :
							$val = isset( $d['hours'][ $day ][ $i ][ $f ] ) ? $d['hours'][ $day ][ $i ][ $f ] : '';
							?>
							<td><input type="time" name="<?php echo self::n( "[hours][$day][$i][$f]" ); ?>" value="<?php echo esc_attr( $val ); ?>" /></td>
						<?php endforeach; endfor; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h3>Special hours (bank holidays, closures)</h3>
				<p class="description">Past entries are kept here but not output. Tick "Closed" or give opening times.</p>
				<?php
				self::repeater(
					'special_hours',
					$d['special_hours'],
					array(
						'label'  => array( 'Label', 'text' ),
						'from'   => array( 'From', 'date' ),
						'to'     => array( 'To', 'date' ),
						'closed' => array( 'Closed', 'checkbox' ),
						'opens'  => array( 'Opens', 'time' ),
						'closes' => array( 'Closes', 'time' ),
					),
					'Add date'
				);
				?>

				<h2>Area served</h2>
				<table class="form-table" role="presentation">
					<tr><th>Type</th><td>
						<select name="<?php echo self::n( '[area_type]' ); ?>">
							<?php foreach ( array( 'AdministrativeArea' => 'County / region', 'City' => 'City / town', 'Country' => 'Country' ) as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $d['area_type'], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select></td></tr>
					<tr><th>Places</th><td><textarea rows="3" class="large-text" name="<?php echo self::n( '[area_served]' ); ?>"><?php echo esc_textarea( implode( "\n", $d['area_served'] ) ); ?></textarea><p class="description">One per line.</p></td></tr>
				</table>

				<h2>Memberships and profiles</h2>
				<?php
				self::repeater( 'member_of', $d['member_of'], array(
					'name' => array( 'Body / accreditation', 'text' ),
					'url'  => array( 'URL', 'url' ),
				), 'Add membership' );
				?>
				<table class="form-table" role="presentation">
					<tr><th>Profile URLs</th><td><textarea rows="4" class="large-text code" name="<?php echo self::n( '[same_as]' ); ?>"><?php echo esc_textarea( implode( "\n", $d['same_as'] ) ); ?></textarea><p class="description">Google Business Profile, Facebook, LinkedIn etc. One per line.</p></td></tr>
				</table>

				<h2>People</h2>
				<p class="description">Marked up on the page whose slug you give (e.g. <code>about-us</code>). Tick Founder to link them to the organisation on that page.</p>
				<?php
				self::repeater( 'people', $d['people'], array(
					'name'      => array( 'Name', 'text' ),
					'job_title' => array( 'Job title', 'text' ),
					'page_slug' => array( 'Page slug', 'text' ),
					'same_as'   => array( 'Profile URL', 'url' ),
					'founder'   => array( 'Founder', 'checkbox' ),
				), 'Add person' );
				?>

				<?php submit_button(); ?>
			</form>

			<h2>Structured data preview</h2>
			<p class="description">Organization fields merged into <?php echo esc_html( $preview['seo_plugin'] ); ?>'s output. Validate with Google's Rich Results Test after saving.</p>
			<pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-height:420px;overflow:auto"><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			<p>Shortcodes: <code>[kbs field="phone" link="1"]</code> <code>[kbs field="email" link="1"]</code> <code>[kbs field="address" format="multiline"]</code> <code>[kbs field="hours"]</code> <code>[kbs field="legal_name"]</code></p>
		</div>
		<?php
	}

	private static function row( $key, $label, $value, $help = '', $type = 'text' ) {
		printf(
			'<tr><th><label for="kbs_%1$s">%2$s</label></th><td><input type="%6$s" id="kbs_%1$s" class="regular-text" name="%3$s" value="%4$s" />%5$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			self::n( '[' . $key . ']' ),
			esc_attr( $value ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : '',
			esc_attr( $type )
		);
	}

	/** Minimal add/remove repeater (vanilla JS, no dependencies). */
	private static function repeater( $key, array $rows, array $fields, $add_label ) {
		$id = 'kbs-rep-' . $key;
		echo '<div id="' . esc_attr( $id ) . '" class="kbs-rep" data-next="' . (int) count( $rows ) . '">';
		foreach ( array_values( $rows ) as $i => $row ) {
			self::repeater_row( $key, $i, $fields, $row );
		}
		echo '</div>';
		echo '<template id="' . esc_attr( $id ) . '-tpl">';
		self::repeater_row( $key, '__i__', $fields, array() );
		echo '</template>';
		echo '<p><button type="button" class="button kbs-add" data-target="' . esc_attr( $id ) . '">' . esc_html( $add_label ) . '</button></p>';
		static $js = false;
		if ( ! $js ) {
			$js = true;
			?>
			<script>
			document.addEventListener('click', function (e) {
				var add = e.target.closest('.kbs-add');
				if (add) {
					var box = document.getElementById(add.dataset.target);
					var tpl = document.getElementById(add.dataset.target + '-tpl');
					var n = parseInt(box.dataset.next, 10) || 0;
					var html = tpl.innerHTML.replace(/__i__/g, n);
					box.insertAdjacentHTML('beforeend', html);
					box.dataset.next = n + 1;
				}
				var rm = e.target.closest('.kbs-remove');
				if (rm) { rm.closest('.kbs-row').remove(); }
			});
			</script>
			<style>.kbs-row{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:0 0 8px}.kbs-row label{display:flex;flex-direction:column;font-size:12px}</style>
			<?php
		}
	}

	private static function repeater_row( $key, $i, array $fields, array $row ) {
		echo '<div class="kbs-row">';
		foreach ( $fields as $f => $def ) {
			list( $label, $type ) = $def;
			$name = KBS_OPTION . "[$key][$i][$f]";
			$val  = isset( $row[ $f ] ) ? $row[ $f ] : '';
			echo '<label>' . esc_html( $label );
			if ( 'checkbox' === $type ) {
				printf( '<input type="checkbox" name="%s" value="1" %s />', esc_attr( $name ), checked( ! empty( $val ), true, false ) );
			} else {
				printf( '<input type="%s" name="%s" value="%s" />', esc_attr( $type ), esc_attr( $name ), esc_attr( $val ) );
			}
			echo '</label>';
		}
		echo '<button type="button" class="button-link-delete kbs-remove">Remove</button></div>';
	}
}
