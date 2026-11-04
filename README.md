# WooCommerce Mix and Match - Filter by Terms

### Quickstart

This is a developmental repo. Clone this repo and run `npm install && npm run build`   
OR    
[Download latest release](https://github.com/kathyisawesome/wc-mnm-filter/releases/latest/)
|---|

### What's This?

Experimental mini-extension for [WooCommerce Mix and Match](https://woocommerce.com/products/woocommerce-mix-and-match-products/) that allows a customer to filter products by any product taxonomy.

![A group of products shown changing depending on which category button is clicked](https://user-images.githubusercontent.com/507025/53804881-ff6f5080-3f8b-11e9-8d13-3207df6f3a75.gif)

### Multi-term versus Single-term filtering

By default, customers can only search by a single term at a time. Clicking a selected term again shows all products.

To let customers select several terms, add the following snippet to your child theme's `functions.php`. Products with any of the selected terms are shown. For example, Category A + Category B shows the products in either category.

```
add_filter( 'wc_mnm_filters_support_multi_term_filtering', '__return_true' );
```

### Important

1. This is proof of concept and not officially supported in any way.
2. Works with the grid and tabular layouts.
3. Requires Mix and Match 3.0 and WordPress 6.5.

### Developers

* Each child item has a `data-filter_terms` attribute with its term slugs.
* `wc_mnm_filter_terms` filters the terms shown as buttons, `wc_mnm_filter_taxonomies` the taxonomies offered in the admin.
* The `wc-mnm-filters-updated` event fires on the form after filtering, with the container and the selected terms.
* Override the template by copying `templates/single-product/mnm/options-filter.php` to `yourtheme/woocommerce/single-product/mnm/options-filter.php`.

### Hidden items and older Mix and Match versions

Before Mix and Match 3.0, the add to cart script treated any child item hidden with CSS as a fixed quantity item, so "Clear selections" skipped items hidden by the filter. The filter replaces each child item's `get_type()` with the 3.0 version, which checks the input's type, but only when core still uses the old visibility check.

### Automatic plugin updates

Plugin updates can be enabled by installing the [Git Updater](https://git-updater.com/) plugin.
