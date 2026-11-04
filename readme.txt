=== WooCommerce Mix and Match Products - Filter ===

Contributors: helgatheviking
Tags: woocommerce, product, container, boxes, kits, configurable, filter
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.0.0
WC requires at least: 8.2.0
WC tested up to: 10.3.0
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Lets shoppers filter a Mix and Match container's products by category, tag or attribute.

== Description ==

Show buttons above a Mix and Match container's child items so shoppers can filter them by category, tag, brand or attribute. Requires WordPress 6.5 and WooCommerce Mix and Match 3.0 or newer.

== Changelog ==

= 3.0.0 XXXX.XX.XX =
* Important: Requires Mix and Match 3.0. The jQuery script is removed.
* Important: Child items are no longer filtered by their product classes. Each item gets a `data-filter_terms` attribute with its term slugs instead, so the filter no longer depends on the theme printing product classes. Themes that styled the old `{taxonomy}-{term}` item classes should use the data attribute.
* Important: When several terms can be selected, items with any selected term are shown. Previously only items with all of them were shown.
* Important: The filter template was rewritten. If your theme overrides `single-product/mnm/options-filter.php`, update it from the plugin's copy.
* New: Only terms that the container's child items have are shown, and terms that every item has are left out because they filter nothing. No filter shows when fewer than 2 terms are left. Terms follow WooCommerce's term order.
* New: Items hidden by the filter keep their quantities and are still added to the cart. A note says how many selected items are hidden, with a "Show all" link.
* New: Accessible buttons with `aria-pressed` in a labelled group, a "Showing %1$d of %2$d products" status for screen readers, and WooCommerce's "No products were found matching your selection." message.
* New: Category sections and their titles hide when none of their items match.
* New: Tabular layout support.
* New: The filter runs on the Interactivity API (script module `@wc-mnm-filter/filter`, store namespace `wc-mnm-filter`) and reads selections from Mix and Match's container store.
* New: On small screens, the buttons stay in one row that scrolls sideways.
* New: "MnM Filter Taxonomy" column in the product CSV importer and exporter. An empty cell leaves the product unchanged, `none` removes the filter.
* Update: The buttons are styled as chips, like the package selection and WooCommerce's attribute chips, with a visible "Filter by {taxonomy}" label. They no longer use the theme's button classes, and the `--wc-product-filter-chips-*` variables restyle them.
* Update: The filter shows after the package selection in Mix and Match 3.0 and before the first category title, and also in the cart and order edit forms.
* Update: The "Filter by" option lists product attributes and public taxonomies with an admin screen, such as categories, tags and brands. Internal taxonomies, such as product visibility or translation plugins' own, are left out. Unknown saved values mean no filter.
* Update: Grid `first` and `last` classes are recalculated from the container's column count.
* Update: Styles moved from inline CSS to a stylesheet. The `wc_mnm_filter_display_inline_styles` filter still turns them off.
* Update: Requires WordPress 6.5, WooCommerce 8.2.0 and PHP 7.4.
* Dev: New `wc_mnm_filter_terms`, `wc_mnm_filter_taxonomies` and `wc_mnm_filter_taxonomy` filters.
* Dev: Git Updater Lite, prefixed into `vendor-prefixed/` with a Strauss phar, handles updates from GitHub releases. The release workflow uses the latest GitHub actions, skips Composer dev packages, builds the .pot with WP-CLI and takes release notes from this changelog.
* Dev: Build CSS with Dart Sass instead of node-sass.

= 2.2.0 2024.11.07 =
* New: Category titles and descriptions are wrapped in a `div`, so they hide and show with their category when categories are used as the container's contents.
* Fix: Fallbacks for undefined CSS variables.

= 2.1.0 2023.12.12 =
* Important: Requires Mix and Match 2.4.
* New: Declare compatibility with HPOS and the Cart and Checkout blocks.
* Update: The term data attributes moved to the form, for easier filtering.
* Update: Buttons use the block theme's button classes, with style tweaks for block themes.
* Fix: The "Filter by" option was added twice in the product settings.

= 2.0.1 2023.11.11 =
* Dev: Release workflow and `.gitignore` updates.

= 2.0.0 2023.02.28 =
* Important: Requires Mix and Match 2.0. Back-compatibility hooks were removed.
* Important: Only one term can be selected at a time by default. Use the `wc_mnm_filters_support_multi_term_filtering` filter to allow several.
* Update: The template and styles use flex.
* Update: Empty items are removed entirely.
* Dev: Script versions follow file changes when debugging.
* Dev: The release zip name includes the version, for Git Updater.
