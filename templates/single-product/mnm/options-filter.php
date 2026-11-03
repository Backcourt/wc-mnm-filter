<?php
/**
 * Mix and Match Options Filter
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/mnm/options-filter.php.
 *
 * HOWEVER, on occasion WooCommerce Mix and Match will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * NB: The script reads the data-filter attributes and the wc-mnm-filter__* class names, keep them when overriding.
 * The filter stays hidden until the script runs. The mnm_filter_button_group, mnm_filters and selected classes
 * are kept from version 1 for existing theme styles. The buttons are styled as chips, like the package selector
 * and WooCommerce's attribute chips, so they don't use the theme's button classes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @author  Kathy Darling
 * @package WooCommerce Mix and Match Filter/Templates
 * @since   1.0.0
 * @version 2.0.0
 *
 * @var WC_Product_Mix_and_Match $container      The container product.
 * @var string                   $taxonomy       Taxonomy name.
 * @var string                   $taxonomy_label Taxonomy singular name.
 * @var WP_Term[]                $terms          Terms used by the child items.
 * @var bool                     $multiple       Whether several terms can be selected at once.
 * @var string                   $layout         The container's layout, grid or tabular.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$label_id = 'wc-mnm-filter-label-' . $container->get_id();

/* translators: %s: Taxonomy name, such as Category. */
$group_label = $taxonomy_label ? sprintf( __( 'Filter by %s', 'wc-mnm-filter' ), $taxonomy_label ) : __( 'Filter products', 'wc-mnm-filter' );
?>
<div class="wc-mnm-filter mnm_filter_button_group" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" data-multiple="<?php echo esc_attr( wc_bool_to_string( $multiple ) ); ?>" hidden>

	<span class="wc-mnm-filter__label" id="<?php echo esc_attr( $label_id ); ?>"><?php echo esc_html( $group_label ); ?></span>

	<div class="wc-mnm-filter__buttons mnm_filters" role="group" aria-labelledby="<?php echo esc_attr( $label_id ); ?>">

		<button type="button" class="wc-mnm-filter__button wc-mnm-filter__button--all selected" data-filter="*" aria-pressed="true"><?php esc_html_e( 'Show all', 'wc-mnm-filter' ); ?></button>

		<?php foreach ( $terms as $filter_term ) : ?>
			<button type="button" class="wc-mnm-filter__button" data-filter="<?php echo esc_attr( $filter_term->slug ); ?>" aria-pressed="false"><?php echo esc_html( $filter_term->name ); ?></button>
		<?php endforeach; ?>

	</div>

	<p class="wc-mnm-filter__status screen-reader-text" role="status" aria-live="polite"></p>

	<p class="wc-mnm-filter__hidden-selections" hidden></p>

	<p class="wc-mnm-filter__no-results woocommerce-info" hidden><?php esc_html_e( 'No products were found matching your selection.', 'wc-mnm-filter' ); ?></p>

</div>
