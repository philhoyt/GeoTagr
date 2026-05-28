<?php
/**
 * Admin settings page — post type selection and taxonomy visibility.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

namespace GeoTagr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Settings › GeoTagr admin page and the geotagr_settings option.
 */
class Settings {

	private const OPTION  = 'geotagr_settings';
	private const PAGE    = 'geotagr-settings';
	private const SECTION = 'geotagr_main';

	/**
	 * Geocoding providers that accept an API key.
	 *
	 * @var string[]
	 */
	private const KEYED_PROVIDERS = array( 'google', 'mapbox' );

	/**
	 * Defaults used when the option has never been saved.
	 *
	 * @var array{allowed_post_types: string[], taxonomy_public: bool, geocoding_provider: string, geocoding_api_key: string}
	 */
	private const DEFAULTS = array(
		'allowed_post_types' => array( 'post' ),
		'taxonomy_public'    => false,
		'geocoding_provider' => 'nominatim',
		'geocoding_api_key'  => '',
	);

	/**
	 * Register the admin page and settings fields.
	 */
	public function register(): void {
		add_options_page(
			__( 'GeoTagr Settings', 'geotagr' ),
			__( 'GeoTagr', 'geotagr' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);

		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::DEFAULTS,
			)
		);

		add_settings_section(
			self::SECTION,
			'',
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			'allowed_post_types',
			__( 'Enable GeoTagr on', 'geotagr' ),
			array( $this, 'render_post_types_field' ),
			self::PAGE,
			self::SECTION
		);

		add_settings_field(
			'taxonomy_public',
			__( 'Location taxonomy', 'geotagr' ),
			array( $this, 'render_taxonomy_public_field' ),
			self::PAGE,
			self::SECTION
		);

		add_settings_field(
			'geocoding_provider',
			__( 'Geocoding provider', 'geotagr' ),
			array( $this, 'render_provider_field' ),
			self::PAGE,
			self::SECTION
		);

		add_settings_field(
			'geocoding_api_key',
			__( 'API key', 'geotagr' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE,
			self::SECTION
		);
	}

	/**
	 * Read a single value from the saved option, falling back to the default.
	 *
	 * @param string $key      Option key (`allowed_post_types` or `taxonomy_public`).
	 * @param mixed  $fallback Value to return when the key is absent.
	 * @return mixed
	 */
	public static function get( string $key, mixed $fallback = null ): mixed {
		$option = get_option( self::OPTION, array() );

		if ( isset( $option[ $key ] ) ) {
			return $option[ $key ];
		}

		return array_key_exists( $key, self::DEFAULTS ) ? self::DEFAULTS[ $key ] : $fallback;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the post types checkboxes field.
	 */
	public function render_post_types_field(): void {
		$saved = self::get( 'allowed_post_types', array( 'post' ) );
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );

		foreach ( $types as $type ) {
			$checked = in_array( $type->name, (array) $saved, true );
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="%s[allowed_post_types][]" value="%s"%s> %s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $type->name ),
				checked( $checked, true, false ),
				esc_html( $type->label )
			);
		}

		echo '<p class="description">' . esc_html__( 'GeoTagr metabox and block editor panel will appear on the selected post types.', 'geotagr' ) . '</p>';
	}

	/**
	 * Render the taxonomy visibility checkbox field.
	 */
	public function render_taxonomy_public_field(): void {
		$checked = self::get( 'taxonomy_public', false );
		printf(
			'<label><input type="checkbox" name="%s[taxonomy_public]" value="1"%s> %s</label>',
			esc_attr( self::OPTION ),
			checked( $checked, true, false ),
			esc_html__( 'Make the location taxonomy public', 'geotagr' )
		);
		echo '<p class="description">' . esc_html__( 'Exposes geo_tagr_location in the admin UI, nav menus, and front-end queries. Useful if you want to build location archives or use the taxonomy in your theme.', 'geotagr' ) . '</p>';
	}

	/**
	 * Render the geocoding provider select field.
	 */
	public function render_provider_field(): void {
		$saved   = self::get( 'geocoding_provider', 'nominatim' );
		$options = array(
			'nominatim' => __( 'Nominatim (OpenStreetMap, no key required)', 'geotagr' ),
			'google'    => __( 'Google Maps Geocoding API', 'geotagr' ),
			'mapbox'    => __( 'Mapbox Geocoding API', 'geotagr' ),
		);

		printf( '<select name="%s[geocoding_provider]">', esc_attr( self::OPTION ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $saved, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Google and Mapbox provide better coverage and POI name lookup but require an API key.', 'geotagr' ) . '</p>';
	}

	/**
	 * Render the API key text field.
	 */
	public function render_api_key_field(): void {
		$saved = self::get( 'geocoding_api_key', '' );
		printf(
			'<input type="text" name="%s[geocoding_api_key]" value="%s" class="regular-text">',
			esc_attr( self::OPTION ),
			esc_attr( $saved )
		);
		echo '<p class="description">' . esc_html__( 'Required when using Google or Mapbox. Restrict this key by HTTP referrer to your site domain. Stored in plain text — keep it out of version control.', 'geotagr' ) . '</p>';
	}

	/**
	 * Sanitize and validate the incoming settings array.
	 *
	 * @param mixed $input Raw POST input.
	 * @return array{allowed_post_types: string[], taxonomy_public: bool, geocoding_provider: string, geocoding_api_key: string}
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		// Validate post types against those actually registered.
		$valid_types   = array_keys( get_post_types( array( 'public' => true ) ) );
		$submitted     = isset( $input['allowed_post_types'] ) ? (array) $input['allowed_post_types'] : array();
		$allowed_types = array_values( array_intersect( $submitted, $valid_types ) );

		// An unchecked checkbox sends nothing — treat absence as false.
		$taxonomy_public = ! empty( $input['taxonomy_public'] );

		// Validate provider against known list; fall back to nominatim.
		$valid_providers    = array_merge( array( 'nominatim' ), self::KEYED_PROVIDERS );
		$submitted_prov     = isset( $input['geocoding_provider'] ) ? (string) $input['geocoding_provider'] : 'nominatim';
		$geocoding_provider = in_array( $submitted_prov, $valid_providers, true ) ? $submitted_prov : 'nominatim';

		$geocoding_api_key = sanitize_text_field( $input['geocoding_api_key'] ?? '' );

		return array(
			'allowed_post_types' => $allowed_types,
			'taxonomy_public'    => $taxonomy_public,
			'geocoding_provider' => $geocoding_provider,
			'geocoding_api_key'  => $geocoding_api_key,
		);
	}
}
