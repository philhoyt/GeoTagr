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
	 * Option flag set when rewrite rules need flushing on the next admin load.
	 */
	public const FLUSH_FLAG = 'geotagr_flush_rewrite';

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
	 * Hook suffix of the settings page, used to scope asset loading.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Register the admin page and settings fields.
	 */
	public function register(): void {
		$this->hook_suffix = (string) add_options_page(
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
			self::SECTION,
			array( 'label_for' => 'geotagr-provider' )
		);

		add_settings_field(
			'geocoding_api_key',
			__( 'API key', 'geotagr' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE,
			self::SECTION,
			array( 'label_for' => 'geotagr-api-key' )
		);
	}

	/**
	 * Enqueue the settings page script and styles on that page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->hook_suffix || $hook !== $this->hook_suffix ) {
			return;
		}

		$asset_file = GEOTAGR_PLUGIN_DIR . 'build/settings.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'geo-tagr-settings',
			GEOTAGR_PLUGIN_URL . 'build/settings.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'geo-tagr-settings',
			GEOTAGR_PLUGIN_URL . 'build/settings.css',
			array(),
			$asset['version']
		);
	}

	/**
	 * Build an external link that announces it opens in a new tab.
	 *
	 * @param string $url  Destination URL.
	 * @param string $text Link text.
	 * @return string Escaped anchor markup.
	 */
	private static function external_link( string $url, string $text ): string {
		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a>',
			esc_url( $url ),
			esc_html( $text ),
			esc_html__( '(opens in a new tab)', 'geotagr' )
		);
	}

	/**
	 * Register suggested privacy policy text (Settings › Privacy › Policy Guide).
	 */
	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'GeoTagr attaches a location to posts. Review this text and adjust it for the geocoding provider you use.', 'geotagr' ) . '</p>';
		$content .= '<p>' . esc_html__( 'For posts that have been tagged with a location, this site stores latitude and longitude coordinates, a place name, and a formatted address with the post. These values may be visible to anyone who can read the post and are available through the site\'s REST API. A location term containing the same coordinates is also created for grouping posts by place.', 'geotagr' ) . '</p>';
		$content .= '<p>' . esc_html__( 'When an editor looks up a location, the address or coordinates they enter are sent to a third-party geocoding service, which also receives the editor\'s IP address when called from the browser. Depending on configuration this is OpenStreetMap Nominatim (openstreetmap.org), Mapbox (mapbox.com), or Google Maps Platform (google.com); the Google service is called from this server rather than the browser. The editor\'s map preview loads map tiles from OpenStreetMap, which receives the editor\'s IP address. No visitor data is sent to these services.', 'geotagr' ) . '</p>';

		wp_add_privacy_policy_content( __( 'GeoTagr', 'geotagr' ), wp_kses_post( $content ) );
	}

	/**
	 * Flag a rewrite flush when the taxonomy visibility changes.
	 *
	 * Hooked to add_option_/update_option_geotagr_settings. The flush itself
	 * runs on the next admin request after the taxonomy has been re-registered.
	 *
	 * @param mixed $old_value Previous option value (or the option name on add).
	 * @param mixed $new_value New option value.
	 */
	public static function schedule_flush_on_change( mixed $old_value, mixed $new_value ): void {
		$was = is_array( $old_value ) && ! empty( $old_value['taxonomy_public'] );
		$now = is_array( $new_value ) && ! empty( $new_value['taxonomy_public'] );

		if ( $was !== $now ) {
			update_option( self::FLUSH_FLAG, 1 );
		}
	}

	/**
	 * Flush rewrite rules once if a change has been flagged.
	 *
	 * Runs on init after the taxonomy is registered, admin requests only.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( ! is_admin() || ! get_option( self::FLUSH_FLAG ) ) {
			return;
		}

		delete_option( self::FLUSH_FLAG );
		flush_rewrite_rules();
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

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Enable GeoTagr on', 'geotagr' ) . '</legend>';

		foreach ( $types as $type ) {
			$checked = in_array( $type->name, (array) $saved, true );
			printf(
				'<label class="geotagr-post-type-option"><input type="checkbox" name="%s[allowed_post_types][]" value="%s"%s> %s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $type->name ),
				checked( $checked, true, false ),
				esc_html( $type->label )
			);
		}

		echo '</fieldset>';
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

		printf( '<select id="geotagr-provider" name="%s[geocoding_provider]">', esc_attr( self::OPTION ) );
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
	 * Render the API key text field with per-provider instructions.
	 */
	public function render_api_key_field(): void {
		$saved = self::get( 'geocoding_api_key', '' );
		$kses  = array(
			'a'    => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'span' => array( 'class' => array() ),
		);
		printf(
			'<input type="text" id="geotagr-api-key" name="%s[geocoding_api_key]" value="%s" class="regular-text" aria-describedby="geotagr-api-key-description">',
			esc_attr( self::OPTION ),
			esc_attr( $saved )
		);
		?>
		<p class="description" id="geotagr-api-key-description">
			<?php esc_html_e( 'Required when using Google or Mapbox. Stored in plain text and output on admin pages — restrict the key by HTTP referrer to your site domain.', 'geotagr' ); ?>
		</p>

		<div id="geotagr-key-instructions-google" class="geotagr-key-instructions" hidden>
			<strong><?php esc_html_e( 'Getting a Google Maps API key:', 'geotagr' ); ?></strong>
			<ol>
				<li>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: link to the Google Cloud Console. */
						__( 'Go to the %s and create or select a project.', 'geotagr' ),
						self::external_link( 'https://console.cloud.google.com/', __( 'Google Cloud Console', 'geotagr' ) )
					),
					$kses
				);
				?>
				</li>
				<li><?php esc_html_e( 'Enable the Places API (New) (for name lookup) and Geocoding API (for address lookup) under APIs & Services › Library.', 'geotagr' ); ?></li>
				<li>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: link to the Google Cloud credentials page. */
						__( 'Create a key under %s.', 'geotagr' ),
						self::external_link( 'https://console.cloud.google.com/apis/credentials', __( 'APIs & Services › Credentials', 'geotagr' ) )
					),
					$kses
				);
				?>
				</li>
				<li><?php esc_html_e( 'Restrict the key to HTTP referrers and add your site\'s domain.', 'geotagr' ); ?></li>
			</ol>
		</div>

		<div id="geotagr-key-instructions-mapbox" class="geotagr-key-instructions" hidden>
			<strong><?php esc_html_e( 'Getting a Mapbox access token:', 'geotagr' ); ?></strong>
			<ol>
				<li>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: link to mapbox.com. */
						__( 'Sign up or log in at %s.', 'geotagr' ),
						self::external_link( 'https://account.mapbox.com/', 'mapbox.com' )
					),
					$kses
				);
				?>
				</li>
				<li>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: link to the Mapbox access tokens page. */
						__( 'Go to %s and click "Create a token".', 'geotagr' ),
						self::external_link( 'https://account.mapbox.com/access-tokens/', __( 'Access Tokens', 'geotagr' ) )
					),
					$kses
				);
				?>
				</li>
				<li><?php esc_html_e( 'Under "Token restrictions", add your site\'s URL to the Allowed URLs list.', 'geotagr' ); ?></li>
				<li><?php esc_html_e( 'The default public token works for geocoding. Mapbox no longer returns place names from the Geocoding API, so only the address is filled in.', 'geotagr' ); ?></li>
			</ol>
		</div>
		<?php
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
