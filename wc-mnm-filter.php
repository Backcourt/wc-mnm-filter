<?php
/**
 * Plugin Name: WooCommerce Mix and Match - Filter by Terms
 * Plugin URI: https://woocommerce.com/products/woocommerce-mix-and-match-products
 * Description: Lets shoppers filter a Mix and Match container's products by category, tag or attribute.
 * Version: 3.0.0
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com/
 *
 * Text Domain: wc-mnm-filter
 * Domain Path: /languages/
 *
 * Requires at least: 6.2
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * WC requires at least: 8.2.0
 * WC tested up to: 10.3.0
 *
 * GitHub Plugin URI: https://github.com/kathyisawesome/wc-mnm-filter
 * Primary Branch: trunk
 * Release Asset: true
 *
 * Copyright: © 2023 Kathy Darling
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \Backcourt\MNMFilter\Vendor\Fragen;

/**
 * Add Git Updater Lite
 */
require_once __DIR__ . '/packages/autoload.php';
add_filter( 'gul_update_server', function () {
	return 'https://backcourt.io';
});
( new Fragen\Git_Updater\Lite( __FILE__ ) )->run();

class WC_MNM_Filter {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $version = '3.0.0';

	/**
	 * Required Mix and Match version.
	 *
	 * @var string
	 */
	private static $required_mnm = '2.4.0';

	/**
	 * Product meta key that stores the filter taxonomy.
	 *
	 * @var string
	 */
	const META_KEY = '_mnm_filter';

	/**
	 * Script and style handle.
	 *
	 * @var string
	 */
	const HANDLE = 'wc-mnm-filter';

	/**
	 * Term slugs per child product, per taxonomy.
	 *
	 * @var array
	 */
	private static $item_terms = array();

	/**
	 * Taxonomies that can be used as filters.
	 *
	 * @var array|null
	 */
	private static $taxonomies = null;

	/**
	 * Fire in the hole!
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_plugin' ), 20 );
	}

	/**
	 * Hooks.
	 */
	public static function load_plugin() {

		// Load translation files.
		add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ) );

		if ( ! function_exists( 'WC_Mix_and_Match' ) || version_compare( WC_Mix_and_Match()->version, self::$required_mnm, '<' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
			return;
		}

		/*
		 * Admin.
		 */
		add_action( 'wc_mnm_admin_product_options', array( __CLASS__, 'additional_container_option' ), 25, 2 );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'process_meta' ), 20 );

		/*
		 * Import/Export.
		 */
		require_once self::plugin_path() . '/includes/class-wc-mnm-filter-import-export.php';
		WC_MNM_Filter_Import_Export::init();

		// Declare Features compatibility.
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_features_compatibility' ) );

		/*
		 * Display.
		 */

		// Filter buttons, after core's package selection (-20) and before the first category caption (-10). Also runs in edit forms.
		add_action( 'wc_mnm_before_child_items', array( __CLASS__, 'add_filter_navigation' ), -15 );

		// Tag each child item with its terms.
		add_filter( 'wc_mnm_child_item_data_attributes', array( __CLASS__, 'child_item_data_attributes' ), 10, 2 );

		// Wrap category captions so the script can hide categories without matches.
		add_action( 'wc_mnm_category_caption', array( __CLASS__, 'add_open_category_wrapper' ), 0 );
		add_action( 'wc_mnm_category_caption', array( __CLASS__, 'add_close_category_wrapper' ), 9999 );

		// Register Scripts.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );

		// Forms loaded with ajax.
		add_action( 'wc_quick_view_enqueue_scripts', array( __CLASS__, 'load_scripts' ) );
		add_action( 'wc_mnm_subscription_editing_enqueue_scripts', array( __CLASS__, 'load_scripts' ) );
	}

	/**
	 * Warn when Mix and Match is missing or too old.
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// Translators: %1$s = Plugin name, %2$s = Plugin version, %3$s = Required Mix and Match Plugin version.
		$message = sprintf( esc_html__( 'To function properly, %1$s %2$s requires WooCommerce Mix and Match version %3$s', 'wc-mnm-filter' ), '<strong>' . esc_html__( 'WooCommerce Mix and Match - Filter by Terms', 'wc-mnm-filter' ) . '</strong>', self::$version, self::$required_mnm );
		echo '<div class="notice notice-error"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/*-----------------------------------------------------------------------------------*/
	/* Localization */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Make the plugin translation ready
	 *
	 * @return void
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'wc-mnm-filter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/*-----------------------------------------------------------------------------------*/
	/* Admin */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Add the "Filter by" select to the Mix and Match product options.
	 *
	 * @param int                      $post_id
	 * @param WC_Product_Mix_and_Match $mnm_product_object
	 */
	public static function additional_container_option( $post_id, $mnm_product_object ) {

		woocommerce_wp_select(
			array(
				'id'          => '_mnm_filter',
				'label'       => esc_html__( 'Filter by', 'wc-mnm-filter' ),
				'options'     => array( '' => esc_html__( 'No filter', 'wc-mnm-filter' ) ) + self::get_product_taxonomies(),
				'value'       => self::get_taxonomy( $mnm_product_object ),
				'desc_tip'    => true,
				'description' => esc_html__( 'Show buttons above the child items so shoppers can filter them by category, tag or attribute.', 'wc-mnm-filter' ),
			)
		);
	}

	/**
	 * Saves the new meta field.
	 *
	 * @param WC_Product $product
	 */
	public static function process_meta( $product ) {

		if ( ! $product->is_type( 'mix-and-match' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the nonce before saving products.
		$taxonomy = isset( $_POST['_mnm_filter'] ) ? sanitize_key( wp_unslash( $_POST['_mnm_filter'] ) ) : '';

		if ( $taxonomy && array_key_exists( $taxonomy, self::get_product_taxonomies() ) ) {
			$product->update_meta_data( self::META_KEY, $taxonomy );
		} else {
			$product->delete_meta_data( self::META_KEY );
		}
	}

	/*-----------------------------------------------------------------------------------*/
	/* Front End Display */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Print the filter buttons.
	 *
	 * @param WC_Product_Mix_and_Match $product The container product.
	 */
	public static function add_filter_navigation( $product ) {

		$taxonomy = self::get_taxonomy( $product );

		if ( ! $taxonomy || ! $product->has_child_items() ) {
			return;
		}

		$terms = self::get_terms( $product, $taxonomy );

		// A single button filters nothing.
		if ( count( $terms ) < 2 ) {
			return;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );

		self::load_scripts();

		wc_get_template(
			'single-product/mnm/options-filter.php',
			array(
				'container'      => $product,
				'taxonomy'       => $taxonomy,
				'taxonomy_label' => $taxonomy_object ? $taxonomy_object->labels->singular_name : '',
				'terms'          => $terms,
				'multiple'       => self::supports_multiple_terms( $product ),
				'layout'         => $product->get_layout(),
			),
			'',
			self::plugin_path() . '/templates/'
		);
	}

	/**
	 * Get the terms that the container's child items use.
	 *
	 * Terms that every child item has are left out, as they'd show the same items as "Show all".
	 *
	 * @param WC_Product_Mix_and_Match $product  The container product.
	 * @param string                   $taxonomy Taxonomy name.
	 * @return WP_Term[]
	 */
	public static function get_terms( $product, $taxonomy ) {

		$counts = array();
		$total  = 0;

		foreach ( $product->get_child_items() as $child_item ) {
			$child_product = $child_item->get_product();

			if ( $child_product ) {
				++$total;

				foreach ( self::get_item_term_slugs( $child_product, $taxonomy ) as $slug ) {
					$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
				}
			}
		}

		$slugs = array_keys(
			array_filter(
				$counts,
				function ( $count ) use ( $total ) {
					return $count < $total;
				}
			)
		);

		$terms = array();

		if ( $slugs ) {
			// Let WooCommerce apply its term order for categories and attributes.
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'slug'       => array_map( 'strval', $slugs ),
					'hide_empty' => false,
				)
			);

			$terms = is_wp_error( $terms ) ? array() : $terms;
		}

		/**
		 * Filter the terms shown as filter buttons.
		 *
		 * @since 2.0.0
		 *
		 * @param WP_Term[]                $terms    Terms used by some, but not all, child items.
		 * @param WC_Product_Mix_and_Match $product  The container product.
		 * @param string                   $taxonomy Taxonomy name.
		 */
		return (array) apply_filters( 'wc_mnm_filter_terms', $terms, $product, $taxonomy );
	}

	/**
	 * Get a child product's term slugs.
	 *
	 * A variation uses its own value when the taxonomy is one of its attributes, otherwise its parent's terms.
	 *
	 * @param WC_Product $product  The child product.
	 * @param string     $taxonomy Taxonomy name.
	 * @return string[]
	 */
	public static function get_item_term_slugs( $product, $taxonomy ) {

		$key = $product->get_id() . '|' . $taxonomy;

		if ( isset( self::$item_terms[ $key ] ) ) {
			return self::$item_terms[ $key ];
		}

		$slugs   = array();
		$term_id = $product->get_id();

		if ( $product->is_type( 'variation' ) ) {
			$attributes = $product->get_attributes();

			if ( ! empty( $attributes[ $taxonomy ] ) ) {
				$slugs = array( (string) $attributes[ $taxonomy ] );
			}

			$term_id = $product->get_parent_id();
		}

		if ( ! $slugs ) {
			$terms = get_the_terms( $term_id, $taxonomy );
			$slugs = $terms && ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array();
		}

		self::$item_terms[ $key ] = array_values( array_map( 'strval', $slugs ) );

		return self::$item_terms[ $key ];
	}

	/**
	 * Add the child item's term slugs to its data attributes, printed as data-filter_terms="slug slug".
	 *
	 * @param array             $attributes Data attributes.
	 * @param WC_MNM_Child_Item $child_item The child item.
	 * @return array
	 */
	public static function child_item_data_attributes( $attributes, $child_item ) {

		$taxonomy = self::get_taxonomy( $child_item->get_container() );
		$product  = $child_item->get_product();

		if ( $taxonomy && $product ) {
			$attributes['filter_terms'] = implode( ' ', self::get_item_term_slugs( $product, $taxonomy ) );
		}

		return $attributes;
	}

	/**
	 * Open wrapping div.
	 * We need to wrap the category title and description in a div so they can be hidden/shown when filtering.
	 *
	 * @since 2.2.0
	 *
	 * @param WP_Term $category The category.
	 */
	public static function add_open_category_wrapper( $category ) {
		echo '<div class="wc-mnm-filter-category-wrapper" data-product_cat="' . esc_attr( $category->slug ) . '">';
	}

	/**
	 * Close wrapping div.
	 *
	 * @since 2.2.0
	 */
	public static function add_close_category_wrapper() {
		echo '</div>';
	}

	/*-----------------------------------------------------------------------------------*/
	/* Core Compat */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Declare Features compatibility.
	 */
	public static function declare_features_compatibility() {

		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		// HPOS (Custom Order tables) compatibility.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( __FILE__ ), true );

		// Cart and Checkout Blocks.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', plugin_basename( __FILE__ ), true );
	}

	/*-----------------------------------------------------------------------------------*/
	/* Scripts and Styles */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Register scripts and styles.
	 *
	 * @return void
	 */
	public static function register_scripts() {
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		$script_path    = 'assets/js/frontend/wc-mnm-filter' . $suffix . '.js';
		$script_version = WC_Mix_and_Match()->get_file_version( self::plugin_path() . '/' . $script_path, self::$version );

		wp_register_script( self::HANDLE, plugins_url( $script_path, __FILE__ ), array( 'jquery', 'wc-add-to-cart-mnm' ), $script_version, true );

		wp_localize_script(
			self::HANDLE,
			'WC_MNM_FILTER_PARAMS',
			array(
				/* translators: %1$d: Number of products shown. %2$d: Total number of products. */
				'i18n_status'        => __( 'Showing %1$d of %2$d products', 'wc-mnm-filter' ),
				/* translators: %d: Total number of products. */
				'i18n_status_all'    => __( 'Showing all %d products', 'wc-mnm-filter' ),
				/* translators: %d: Number of selected items hidden by the filter. */
				'i18n_hidden_single' => __( '%d selected item is hidden by the filter.', 'wc-mnm-filter' ),
				/* translators: %d: Number of selected items hidden by the filter. */
				'i18n_hidden_plural' => __( '%d selected items are hidden by the filter.', 'wc-mnm-filter' ),
				'i18n_show_all'      => __( 'Show all', 'wc-mnm-filter' ),
			)
		);

		/**
		 * Whether to load the plugin's stylesheet. When false, only the rule that hides filtered items is printed.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $load_styles
		 */
		if ( apply_filters( 'wc_mnm_filter_display_inline_styles', true ) ) {
			$style_path    = 'assets/css/frontend/wc-mnm-filter' . $suffix . '.css';
			$style_version = WC_Mix_and_Match()->get_file_version( self::plugin_path() . '/' . $style_path, self::$version );

			wp_register_style( self::HANDLE, plugins_url( $style_path, __FILE__ ), array(), $style_version );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

			if ( $suffix ) {
				wp_style_add_data( self::HANDLE, 'suffix', $suffix );
			}
		} else {
			wp_register_style( self::HANDLE, false, array(), self::$version );
			wp_add_inline_style( self::HANDLE, '.wc-mnm-filter[hidden], .wc-mnm-filter [hidden], .mnm_form .wc-mnm-filter-hidden { display: none !important; }' );
		}
	}

	/**
	 * Enqueue the script and stylesheet.
	 *
	 * @return void
	 */
	public static function load_scripts() {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			self::register_scripts();
		}
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/*-----------------------------------------------------------------------------------*/
	/* Helper Functions                                                                  */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin path.
	 *
	 * @return string
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get the product taxonomies that can be used as filters.
	 *
	 * Product attributes, plus public taxonomies with an admin UI such as categories, tags and brands.
	 * Internal taxonomies (product type, visibility, shipping class, translation plugins' own) are left out.
	 *
	 * @return array Taxonomy name => label.
	 */
	public static function get_product_taxonomies() {

		if ( null === self::$taxonomies ) {

			$options = array();

			foreach ( get_object_taxonomies( 'product', 'objects' ) as $taxonomy ) {
				if ( taxonomy_is_product_attribute( $taxonomy->name ) || ( $taxonomy->public && $taxonomy->show_ui ) ) {
					$options[ $taxonomy->name ] = $taxonomy->labels->singular_name ? $taxonomy->labels->singular_name : $taxonomy->label;
				}
			}

			/**
			 * Filter the taxonomies that can be used to filter child items.
			 *
			 * @since 2.0.0
			 *
			 * @param array $options Taxonomy name => label.
			 */
			self::$taxonomies = (array) apply_filters( 'wc_mnm_filter_taxonomies', $options );
		}

		return self::$taxonomies;
	}

	/**
	 * Get the taxonomy a container's child items are filtered by.
	 *
	 * @param WC_Product $product The container product.
	 * @return string Taxonomy name, or an empty string when the container isn't filtered.
	 */
	public static function get_taxonomy( $product ) {

		$taxonomy = '';

		if ( $product instanceof WC_Product && $product->is_type( 'mix-and-match' ) ) {
			$taxonomy = (string) $product->get_meta( self::META_KEY, true );

			// The first versions only filtered by category.
			if ( 'yes' === $taxonomy ) {
				$taxonomy = 'product_cat';
			}

			if ( $taxonomy && ! array_key_exists( $taxonomy, self::get_product_taxonomies() ) ) {
				$taxonomy = '';
			}
		}

		/**
		 * Filter the taxonomy a container's child items are filtered by.
		 *
		 * @since 2.0.0
		 *
		 * @param string     $taxonomy Taxonomy name, or an empty string for no filter.
		 * @param WC_Product $product  The container product.
		 */
		return (string) apply_filters( 'wc_mnm_filter_taxonomy', $taxonomy, $product );
	}

	/**
	 * Whether shoppers can select several terms at once. Items matching any selected term are shown.
	 *
	 * @param WC_Product $product The container product.
	 * @return bool
	 */
	public static function supports_multiple_terms( $product ) {
		return (bool) apply_filters( 'wc_mnm_filters_support_multi_term_filtering', false, $product );
	}
}
WC_MNM_Filter::init();
