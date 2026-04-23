<?php
/**
 * Admin UI: menu, page render, and AJAX handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Variation labels shown in the UI
// ---------------------------------------------------------------------------

function bcf_variation_labels(): array {
	return [
		'n1' => __( '100 Thin',              'blocksy-custom-fonts' ),
		'i1' => __( '100 Thin Italic',       'blocksy-custom-fonts' ),
		'n2' => __( '200 Extra Light',       'blocksy-custom-fonts' ),
		'i2' => __( '200 Extra Light Italic','blocksy-custom-fonts' ),
		'n3' => __( '300 Light',             'blocksy-custom-fonts' ),
		'i3' => __( '300 Light Italic',      'blocksy-custom-fonts' ),
		'n4' => __( '400 Regular',           'blocksy-custom-fonts' ),
		'i4' => __( '400 Italic',            'blocksy-custom-fonts' ),
		'n5' => __( '500 Medium',            'blocksy-custom-fonts' ),
		'i5' => __( '500 Medium Italic',     'blocksy-custom-fonts' ),
		'n6' => __( '600 SemiBold',          'blocksy-custom-fonts' ),
		'i6' => __( '600 SemiBold Italic',   'blocksy-custom-fonts' ),
		'n7' => __( '700 Bold',              'blocksy-custom-fonts' ),
		'i7' => __( '700 Bold Italic',       'blocksy-custom-fonts' ),
		'n8' => __( '800 Extra Bold',        'blocksy-custom-fonts' ),
		'i8' => __( '800 Extra Bold Italic', 'blocksy-custom-fonts' ),
		'n9' => __( '900 Black',             'blocksy-custom-fonts' ),
		'i9' => __( '900 Black Italic',      'blocksy-custom-fonts' ),
	];
}

// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------

add_action( 'admin_menu', 'bcf_add_menu' );

function bcf_add_menu(): void {
	add_theme_page(
		__( 'Custom Fonts', 'blocksy-custom-fonts' ),
		__( 'Custom Fonts', 'blocksy-custom-fonts' ),
		'manage_options',
		'bcf-custom-fonts',
		'bcf_render_page'
	);
}

// ---------------------------------------------------------------------------
// Enqueue admin assets (only on our page)
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', 'bcf_enqueue_admin_assets' );

function bcf_enqueue_admin_assets( string $hook ): void {
	if ( $hook !== 'appearance_page_bcf-custom-fonts' ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'bcf-admin',
		plugin_dir_url( BCF_DIR . 'blocksy-custom-fonts.php' ) . 'assets/admin.js',
		[],
		'1.0.0',
		true
	);

	wp_localize_script(
		'bcf-admin',
		'bcfData',
		[
			'nonce'        => wp_create_nonce( 'bcf_ajax' ),
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'mediaTitle'   => __( 'Select Font File', 'blocksy-custom-fonts' ),
			'mediaButton'  => __( 'Use this font', 'blocksy-custom-fonts' ),
		]
	);
}

// ---------------------------------------------------------------------------
// Admin page render
// ---------------------------------------------------------------------------

function bcf_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$fonts  = bcf_get_fonts();
	$labels = bcf_variation_labels();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Custom Fonts', 'blocksy-custom-fonts' ); ?></h1>

		<p><?php esc_html_e( 'Register custom font families so they appear in the Blocksy Customizer typography picker.', 'blocksy-custom-fonts' ); ?></p>

		<?php // ---- Add new family form ---- ?>
		<h2><?php esc_html_e( 'Add New Font Family', 'blocksy-custom-fonts' ); ?></h2>
		<form id="bcf-add-family-form">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="bcf-family-name"><?php esc_html_e( 'Family Name', 'blocksy-custom-fonts' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="bcf-family-name"
							name="family"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. My Brand Font', 'blocksy-custom-fonts' ); ?>"
							required
						/>
						<p class="description"><?php esc_html_e( 'This becomes the CSS font-family name — use the exact name from the font files.', 'blocksy-custom-fonts' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Add Font Family', 'blocksy-custom-fonts' ); ?>
				</button>
				<span class="bcf-spinner spinner" style="float:none;margin-top:0;"></span>
				<span class="bcf-msg"></span>
			</p>
		</form>

		<hr />

		<?php // ---- Existing families ---- ?>
		<h2><?php esc_html_e( 'Registered Fonts', 'blocksy-custom-fonts' ); ?></h2>

		<?php if ( empty( $fonts ) ) : ?>
			<p id="bcf-no-fonts"><?php esc_html_e( 'No custom fonts registered yet.', 'blocksy-custom-fonts' ); ?></p>
		<?php else : ?>
			<p id="bcf-no-fonts" style="display:none;"><?php esc_html_e( 'No custom fonts registered yet.', 'blocksy-custom-fonts' ); ?></p>
		<?php endif; ?>

		<div id="bcf-families-list">
		<?php foreach ( $fonts as $family_index => $font ) :
			$family = $font['family'];
			?>
			<div class="bcf-family-card postbox" data-index="<?php echo esc_attr( $family_index ); ?>">
				<div class="postbox-header">
					<h2 class="hndle"><?php echo esc_html( $family ); ?></h2>
					<div class="handle-actions">
						<button
							type="button"
							class="button button-link-delete bcf-delete-family"
							data-index="<?php echo esc_attr( $family_index ); ?>"
							data-family="<?php echo esc_attr( $family ); ?>"
						><?php esc_html_e( 'Delete Family', 'blocksy-custom-fonts' ); ?></button>
					</div>
				</div>
				<div class="inside">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Variation', 'blocksy-custom-fonts' ); ?></th>
								<th><?php esc_html_e( 'File', 'blocksy-custom-fonts' ); ?></th>
								<th style="width:80px;"><?php esc_html_e( 'Remove', 'blocksy-custom-fonts' ); ?></th>
							</tr>
						</thead>
						<tbody class="bcf-variations-tbody">
						<?php foreach ( $font['variations'] as $var_index => $v ) : ?>
							<tr data-var-index="<?php echo esc_attr( $var_index ); ?>">
								<td><?php echo esc_html( $labels[ $v['code'] ] ?? $v['code'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( $v['url'] ); ?>" target="_blank">
										<?php echo esc_html( basename( $v['url'] ) ); ?>
									</a>
								</td>
								<td>
									<button
										type="button"
										class="button button-small bcf-delete-variation"
										data-family-index="<?php echo esc_attr( $family_index ); ?>"
										data-var-index="<?php echo esc_attr( $var_index ); ?>"
									><?php esc_html_e( 'Remove', 'blocksy-custom-fonts' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php // ---- Add variation row ---- ?>
					<div class="bcf-add-variation" style="margin-top:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
						<select class="bcf-var-code">
							<?php foreach ( $labels as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button bcf-choose-file">
							<?php esc_html_e( 'Choose Font File…', 'blocksy-custom-fonts' ); ?>
						</button>
						<span class="bcf-chosen-filename" style="font-style:italic;"></span>
						<input type="hidden" class="bcf-chosen-attachment-id" value="" />
						<input type="hidden" class="bcf-chosen-url" value="" />
						<button
							type="button"
							class="button button-primary bcf-save-variation"
							data-family-index="<?php echo esc_attr( $family_index ); ?>"
						><?php esc_html_e( 'Add Variation', 'blocksy-custom-fonts' ); ?></button>
						<span class="bcf-spinner spinner" style="float:none;margin-top:0;"></span>
						<span class="bcf-msg"></span>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		</div>
	</div>

	<?php
	// Template for a new family card injected by JS after an add-family action.
	$labels_json = wp_json_encode( $labels );
	?>
	<script>
	window.bcfVariationLabels = <?php echo $labels_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	</script>
	<?php
}

// ---------------------------------------------------------------------------
// AJAX: add font family
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_bcf_add_family', 'bcf_ajax_add_family' );

function bcf_ajax_add_family(): void {
	check_ajax_referer( 'bcf_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'blocksy-custom-fonts' ) ], 403 );
	}

	$family = sanitize_text_field( wp_unslash( $_POST['family'] ?? '' ) );

	if ( '' === $family ) {
		wp_send_json_error( [ 'message' => __( 'Font family name is required.', 'blocksy-custom-fonts' ) ] );
	}

	$fonts = bcf_get_fonts();

	// Prevent duplicates (case-insensitive).
	foreach ( $fonts as $f ) {
		if ( strtolower( $f['family'] ) === strtolower( $family ) ) {
			wp_send_json_error( [ 'message' => __( 'A font family with that name already exists.', 'blocksy-custom-fonts' ) ] );
		}
	}

	$fonts[] = [
		'family'     => $family,
		'variations' => [],
	];

	bcf_save_fonts( $fonts );

	wp_send_json_success( [ 'family' => $family, 'index' => array_key_last( $fonts ) ] );
}

// ---------------------------------------------------------------------------
// AJAX: add variation to a family
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_bcf_add_variation', 'bcf_ajax_add_variation' );

function bcf_ajax_add_variation(): void {
	check_ajax_referer( 'bcf_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'blocksy-custom-fonts' ) ], 403 );
	}

	$family_index   = (int) ( $_POST['family_index'] ?? -1 );
	$code           = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
	$attachment_id  = (int) ( $_POST['attachment_id'] ?? 0 );

	$allowed_codes = array_keys( bcf_variation_labels() );

	if ( ! in_array( $code, $allowed_codes, true ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid variation code.', 'blocksy-custom-fonts' ) ] );
	}

	if ( $attachment_id < 1 ) {
		wp_send_json_error( [ 'message' => __( 'No file selected.', 'blocksy-custom-fonts' ) ] );
	}

	$url = wp_get_attachment_url( $attachment_id );

	if ( ! $url ) {
		wp_send_json_error( [ 'message' => __( 'Attachment not found.', 'blocksy-custom-fonts' ) ] );
	}

	$fonts = bcf_get_fonts();

	if ( ! isset( $fonts[ $family_index ] ) ) {
		wp_send_json_error( [ 'message' => __( 'Font family not found.', 'blocksy-custom-fonts' ) ] );
	}

	// Replace if the same variation code already exists.
	$variations = &$fonts[ $family_index ]['variations'];
	foreach ( $variations as &$v ) {
		if ( $v['code'] === $code ) {
			$v['attachment_id'] = $attachment_id;
			$v['url']           = $url;
			bcf_save_fonts( $fonts );
			wp_send_json_success( [
				'code'          => $code,
				'attachment_id' => $attachment_id,
				'url'           => $url,
				'var_index'     => array_search( $v, $variations, true ),
				'replaced'      => true,
			] );
		}
	}
	unset( $v );

	$variations[] = [
		'code'          => $code,
		'attachment_id' => $attachment_id,
		'url'           => $url,
	];

	bcf_save_fonts( $fonts );

	wp_send_json_success( [
		'code'          => $code,
		'attachment_id' => $attachment_id,
		'url'           => $url,
		'var_index'     => array_key_last( $variations ),
		'replaced'      => false,
	] );
}

// ---------------------------------------------------------------------------
// AJAX: delete a single variation
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_bcf_delete_variation', 'bcf_ajax_delete_variation' );

function bcf_ajax_delete_variation(): void {
	check_ajax_referer( 'bcf_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'blocksy-custom-fonts' ) ], 403 );
	}

	$family_index = (int) ( $_POST['family_index'] ?? -1 );
	$var_index    = (int) ( $_POST['var_index'] ?? -1 );

	$fonts = bcf_get_fonts();

	if ( ! isset( $fonts[ $family_index ]['variations'][ $var_index ] ) ) {
		wp_send_json_error( [ 'message' => __( 'Variation not found.', 'blocksy-custom-fonts' ) ] );
	}

	array_splice( $fonts[ $family_index ]['variations'], $var_index, 1 );
	bcf_save_fonts( $fonts );

	wp_send_json_success();
}

// ---------------------------------------------------------------------------
// AJAX: delete an entire family
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_bcf_delete_family', 'bcf_ajax_delete_family' );

function bcf_ajax_delete_family(): void {
	check_ajax_referer( 'bcf_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'blocksy-custom-fonts' ) ], 403 );
	}

	$family_index = (int) ( $_POST['family_index'] ?? -1 );

	$fonts = bcf_get_fonts();

	if ( ! isset( $fonts[ $family_index ] ) ) {
		wp_send_json_error( [ 'message' => __( 'Font family not found.', 'blocksy-custom-fonts' ) ] );
	}

	array_splice( $fonts, $family_index, 1 );
	bcf_save_fonts( $fonts );

	wp_send_json_success();
}
