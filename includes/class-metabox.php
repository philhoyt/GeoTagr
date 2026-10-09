<?php
/**
 * Classic editor metabox.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

namespace GeoTagr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the classic editor metabox; handles save_post.
 */
class Metabox {

	private const NONCE_ACTION = 'geo_tagr_metabox';
	private const NONCE_FIELD  = '_geo_tagr_nonce';

	/**
	 * Register the metabox on all allowed post types.
	 *
	 * Marked __back_compat_meta_box so the block editor, which has its own
	 * sidebar panel, does not also render this box.
	 */
	public function register(): void {
		$post_types = apply_filters( 'geo_tagr_allowed_post_types', array( 'post' ) );

		foreach ( (array) $post_types as $post_type ) {
			add_meta_box(
				'geo-tagr',
				__( 'GeoTagr', 'geotagr' ),
				array( $this, 'render' ),
				$post_type,
				'normal',
				'default',
				array( '__back_compat_meta_box' => true )
			);
		}
	}

	/**
	 * Render the metabox HTML.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$lat     = (string) get_post_meta( $post->ID, '_geo_tagr_lat', true );
		$lng     = (string) get_post_meta( $post->ID, '_geo_tagr_lng', true );
		$place   = (string) get_post_meta( $post->ID, '_geo_tagr_place', true );
		$address = (string) get_post_meta( $post->ID, '_geo_tagr_address', true );
		?>
		<div class="geo-tagr-metabox">
			<p>
				<label for="geo_tagr_address"><?php esc_html_e( 'Full address', 'geotagr' ); ?></label><br>
				<input
					type="text"
					id="geo_tagr_address"
					name="geo_tagr_address"
					value="<?php echo esc_attr( $address ); ?>"
					style="width:100%"
				>
			</p>
			<p>
				<button type="button" id="geo-tagr-use-location" class="button">
					<?php esc_html_e( 'Use my location', 'geotagr' ); ?>
				</button>
				<button type="button" id="geo-tagr-search-address" class="button" style="margin-left:4px">
					<?php esc_html_e( 'Search on Address', 'geotagr' ); ?>
				</button>
				<span id="geo-tagr-location-error" style="color:#d63638;display:none;margin-left:8px;"></span>
			</p>
			<p>
				<label for="geo_tagr_lat"><?php esc_html_e( 'Latitude', 'geotagr' ); ?></label><br>
				<input
					type="number"
					id="geo_tagr_lat"
					name="geo_tagr_lat"
					value="<?php echo esc_attr( $lat ); ?>"
					step="any"
					style="width:100%"
				>
			</p>
			<p>
				<label for="geo_tagr_lng"><?php esc_html_e( 'Longitude', 'geotagr' ); ?></label><br>
				<input
					type="number"
					id="geo_tagr_lng"
					name="geo_tagr_lng"
					value="<?php echo esc_attr( $lng ); ?>"
					step="any"
					style="width:100%"
				>
			</p>
			<p>
				<label for="geo_tagr_place"><?php esc_html_e( 'Place name', 'geotagr' ); ?></label><br>
				<input
					type="text"
					id="geo_tagr_place"
					name="geo_tagr_place"
					value="<?php echo esc_attr( $place ); ?>"
					style="width:100%"
				>
			</p>
		</div>
		<?php
	}

	/**
	 * Save meta when the post is saved.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save( int $post_id ): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? wp_unslash( $_POST[ self::NONCE_FIELD ] ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// save_post also fires for the revision WordPress creates on save.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$allowed_types = (array) apply_filters( 'geo_tagr_allowed_post_types', array( 'post' ) );
		if ( ! in_array( get_post_type( $post_id ), $allowed_types, true ) ) {
			return;
		}

		$fields = array(
			'geo_tagr_lat'     => '_geo_tagr_lat',
			'geo_tagr_lng'     => '_geo_tagr_lng',
			'geo_tagr_place'   => '_geo_tagr_place',
			'geo_tagr_address' => '_geo_tagr_address',
		);

		$numeric_limits = array(
			'geo_tagr_lat' => 90.0,
			'geo_tagr_lng' => 180.0,
		);

		foreach ( $fields as $input => $meta_key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
			if ( ! isset( $_POST[ $input ] ) || ! is_scalar( $_POST[ $input ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
			$raw = sanitize_text_field( wp_unslash( (string) $_POST[ $input ] ) );

			if ( isset( $numeric_limits[ $input ] ) && '' !== $raw ) {
				if ( ! is_numeric( $raw ) || abs( (float) $raw ) > $numeric_limits[ $input ] ) {
					continue; // Out-of-range or non-numeric: leave the stored value alone.
				}
				$raw = (float) $raw;
			}

			if ( '' === $raw ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $raw );
			}
		}

		Meta::fire_saved_action( $post_id );
	}
}
