<?php
/**
 * Font data helpers, Blocksy integration, and @font-face output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Option key
// ---------------------------------------------------------------------------

define( 'BCF_OPTION_KEY', 'bcf_custom_fonts' );

// ---------------------------------------------------------------------------
// Data helpers
// ---------------------------------------------------------------------------

/**
 * Returns all stored font families.
 *
 * @return array[]
 */
function bcf_get_fonts(): array {
	return get_option( BCF_OPTION_KEY, [] );
}

/**
 * Persists the fonts array.
 */
function bcf_save_fonts( array $fonts ): void {
	update_option( BCF_OPTION_KEY, $fonts, false );
}

/**
 * Maps a variation code (e.g. n4, i7) to a CSS font-weight integer.
 */
function bcf_variation_to_weight( string $code ): int {
	return (int) substr( $code, 1 ) * 100;
}

/**
 * Maps a variation code to a CSS font-style string.
 */
function bcf_variation_to_style( string $code ): string {
	return str_starts_with( $code, 'i' ) ? 'italic' : 'normal';
}

// ---------------------------------------------------------------------------
// Blocksy font picker integration
// ---------------------------------------------------------------------------

add_filter( 'blocksy_typography_font_sources', 'bcf_add_font_sources' );

function bcf_add_font_sources( array $sources ): array {
	$fonts = bcf_get_fonts();

	if ( empty( $fonts ) ) {
		return $sources;
	}

	$families = [];

	foreach ( $fonts as $font ) {
		if ( empty( $font['family'] ) ) {
			continue;
		}

		$variation_codes = array_column( $font['variations'] ?? [], 'code' );

		$families[] = [
			'source'         => 'custom',
			'family'         => $font['family'],
			'display'        => $font['family'],
			'variations'     => [],
			'all_variations' => $variation_codes,
		];
	}

	if ( ! empty( $families ) ) {
		// Use a unique key so we never overwrite another plugin/theme that
		// also writes to 'custom' (e.g. the child theme's twr_add_font_sources).
		$sources['bcf_custom'] = [
			'type'     => 'custom',
			'families' => $families,
		];
	}

	return $sources;
}

// ---------------------------------------------------------------------------
// @font-face CSS
// ---------------------------------------------------------------------------

add_action( 'wp_head',    'bcf_output_font_face_css', 5 );
add_action( 'admin_head', 'bcf_output_font_face_css', 5 );
add_action( 'enqueue_block_editor_assets', 'bcf_enqueue_font_face_css_editor' );

function bcf_output_font_face_css(): void {
	$css = bcf_build_font_face_css();

	if ( $css ) {
		echo "<style id=\"bcf-custom-fonts\">\n" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

function bcf_enqueue_font_face_css_editor(): void {
	$css = bcf_build_font_face_css();

	if ( ! $css ) {
		return;
	}

	wp_register_style( 'bcf-custom-fonts-editor', false );
	wp_enqueue_style( 'bcf-custom-fonts-editor' );
	wp_add_inline_style( 'bcf-custom-fonts-editor', $css );
}

function bcf_build_font_face_css(): string {
	$fonts = bcf_get_fonts();
	$css   = '';

	foreach ( $fonts as $font ) {
		if ( empty( $font['family'] ) || empty( $font['variations'] ) ) {
			continue;
		}

		$family = $font['family'];

		foreach ( $font['variations'] as $variation ) {
			if ( empty( $variation['url'] ) || empty( $variation['code'] ) ) {
				continue;
			}

			$url    = esc_url( $variation['url'] );
			$weight = bcf_variation_to_weight( $variation['code'] );
			$style  = bcf_variation_to_style( $variation['code'] );
			$ext    = strtolower( pathinfo( $variation['url'], PATHINFO_EXTENSION ) );
			$format = bcf_ext_to_format( $ext );

			$css .= "@font-face {\n";
			$css .= "\tfont-family: '" . esc_attr( $family ) . "';\n";
			$css .= "\tsrc: url('" . $url . "') format('" . $format . "');\n";
			$css .= "\tfont-weight: {$weight};\n";
			$css .= "\tfont-style: {$style};\n";
			$css .= "\tfont-display: swap;\n";
			$css .= "}\n";
		}
	}

	return $css;
}

function bcf_ext_to_format( string $ext ): string {
	return match ( $ext ) {
		'woff2' => 'woff2',
		'woff'  => 'woff',
		'ttf'   => 'truetype',
		'otf'   => 'opentype',
		'eot'   => 'embedded-opentype',
		'svg'   => 'svg',
		default => $ext,
	};
}

// ---------------------------------------------------------------------------
// Allow font MIME types in the media uploader
// ---------------------------------------------------------------------------

add_filter( 'upload_mimes', 'bcf_allow_font_mimes' );

function bcf_allow_font_mimes( array $mimes ): array {
	$mimes['woff']  = 'font/woff';
	$mimes['woff2'] = 'font/woff2';
	$mimes['ttf']   = 'font/ttf';
	$mimes['otf']   = 'font/otf';
	$mimes['eot']   = 'application/vnd.ms-fontobject';
	return $mimes;
}

// finfo often mis-identifies font binaries (e.g. OTF → font/sfnt or
// application/octet-stream), causing WordPress to set ext/type to false.
// We always override for known font extensions, regardless of what finfo says.
add_filter( 'wp_check_filetype_and_ext', 'bcf_fix_font_filetype', 10, 4 );

function bcf_fix_font_filetype( array $data, string $file, string $filename, array|null $mimes ): array {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

	$font_map = [
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
		'eot'   => 'application/vnd.ms-fontobject',
	];

	if ( isset( $font_map[ $ext ] ) ) {
		$data['ext']  = $ext;
		$data['type'] = $font_map[ $ext ];
	}

	return $data;
}
