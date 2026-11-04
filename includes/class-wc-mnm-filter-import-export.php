<?php
/**
 * Filter by Terms Import/Export
 *
 * Adds the "MnM Filter Taxonomy" column to the WooCommerce product CSV importer and exporter.
 *
 * @package WooCommerce Mix and Match Filter
 * @since   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_MNM_Filter_Import_Export {

	/**
	 * Column key.
	 *
	 * @var string
	 */
	const COLUMN = 'wc_mnm_filter';

	/**
	 * Hooks.
	 */
	public static function init() {
		// Export.
		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'add_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( __CLASS__, 'add_columns' ) );
		add_filter( 'woocommerce_product_export_product_column_' . self::COLUMN, array( __CLASS__, 'export_filter' ), 10, 2 );

		// Import.
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'add_columns' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'add_import_mapping' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( __CLASS__, 'pre_insert_product_object' ), 10, 2 );
	}

	/**
	 * Column label.
	 *
	 * @return string
	 */
	private static function get_label() {
		return __( 'MnM Filter Taxonomy', 'wc-mnm-filter' );
	}

	/**
	 * Add the column to the exporter and the importer's mapping options.
	 *
	 * @param array $columns
	 * @return array
	 */
	public static function add_columns( $columns ) {
		$columns[ self::COLUMN ] = self::get_label();
		return $columns;
	}

	/**
	 * Export the filter taxonomy.
	 *
	 * @param mixed      $value
	 * @param WC_Product $product
	 * @return string
	 */
	public static function export_filter( $value, $product ) {
		return $product->is_type( 'mix-and-match' ) ? WC_MNM_Filter::get_taxonomy( $product ) : '';
	}

	/**
	 * Map the column header to the import key.
	 *
	 * @param array $columns
	 * @return array
	 */
	public static function add_import_mapping( $columns ) {
		$columns[ self::get_label() ] = self::COLUMN;
		// Always add the English header.
		$columns['MnM Filter Taxonomy'] = self::COLUMN;
		return $columns;
	}

	/**
	 * Set the filter taxonomy. An empty cell leaves the product unchanged, "none" removes the filter.
	 *
	 * @param WC_Product $product
	 * @param array      $data
	 * @return WC_Product
	 */
	public static function pre_insert_product_object( $product, $data ) {

		if ( ! isset( $data[ self::COLUMN ] ) || '' === $data[ self::COLUMN ] || ! $product->is_type( 'mix-and-match' ) ) {
			return $product;
		}

		$taxonomy = sanitize_key( $data[ self::COLUMN ] );

		if ( array_key_exists( $taxonomy, WC_MNM_Filter::get_product_taxonomies() ) ) {
			$product->update_meta_data( WC_MNM_Filter::META_KEY, $taxonomy );
		} elseif ( 'none' === $taxonomy ) {
			$product->delete_meta_data( WC_MNM_Filter::META_KEY );
		}

		return $product;
	}
}
