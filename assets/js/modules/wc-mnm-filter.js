/**
 * Mix and Match - Filter by Terms, Interactivity API store.
 *
 * Hides the child items that don't match the selected terms. The filter template is server rendered
 * with this namespace's directives and a context per container:
 *
 *     { containerId, multiple, items: { [productId]: [ termSlug ] }, active: [ termSlug ], isReady, status }
 *
 * Selections are read from Mix and Match's container store, so the count of selected items hidden by
 * the filter updates when quantities change. Hidden items keep their quantities, so they're still
 * counted and added to the cart.
 *
 * Child items are core's markup, so they're hidden from a watch callback (a class toggle), not with
 * directives on each item.
 *
 * Native ES module, no build step: @wordpress/interactivity and @wc-mix-and-match/container resolve
 * through the import map.
 *
 * @since   3.0.0
 * @version 3.0.0
 */
import { store, getContext, getElement, getConfig } from '@wordpress/interactivity';
import '@wc-mix-and-match/container';

const NAMESPACE = 'wc-mnm-filter';

const HIDDEN_CLASS = 'wc-mnm-filter-hidden';

const { state: containerState } = store( 'wc-mix-and-match/container' );

/**
 * Replace printf-style placeholders: %d, %1$d, %2$d.
 *
 * @param {string}    template
 * @param {...number} values
 * @return {string} The formatted string.
 */
const format = ( template, ...values ) => {
	let index = 0;
	return String( template || '' ).replace( /%(?:(\d+)\$)?d/g, ( match, position ) => {
		const value = position ? values[ position - 1 ] : values[ index++ ];
		return undefined === value ? match : String( value );
	} );
};

/**
 * Whether an item's terms match the selected terms. Items matching any selected term are shown.
 *
 * @param {string[]} terms  The item's term slugs.
 * @param {string[]} active The selected term slugs.
 * @return {boolean} True when nothing is selected or the item has a selected term.
 */
const matches = ( terms, active ) => 0 === active.length || ( terms || [] ).some( ( term ) => active.includes( term ) );

/**
 * Find the category caption for an item wrapper.
 *
 * Other elements can sit between them, such as core's skip link target before the first wrapper,
 * so look back until the caption, the previous wrapper or the filter.
 *
 * @param {Element} wrapper The .mnm_child_products element.
 * @return {Element|null} The caption wrapper, if any.
 */
const getCaption = ( wrapper ) => {
	let el = wrapper.previousElementSibling;

	while ( el ) {
		if ( el.classList.contains( 'wc-mnm-filter-category-wrapper' ) ) {
			return el;
		}
		if ( el.classList.contains( 'mnm_child_products' ) || el.classList.contains( 'wc-mnm-filter' ) ) {
			return null;
		}
		el = el.previousElementSibling;
	}

	return null;
};

/**
 * Restore the WooCommerce loop's first and last classes for themes with float grids.
 *
 * @param {Element} wrapper The .mnm_child_products element.
 */
const updateGridClasses = ( wrapper ) => {
	const match   = wrapper.className.match( /\bcolumns-(\d+)\b/ );
	const columns = match ? parseInt( match[ 1 ], 10 ) : 0;

	if ( ! columns ) {
		return;
	}

	let index = 0;

	wrapper.querySelectorAll( '.mnm_item' ).forEach( ( el ) => {
		el.classList.remove( 'first', 'last' );

		if ( el.classList.contains( HIDDEN_CLASS ) ) {
			return;
		}

		if ( 0 === index % columns ) {
			el.classList.add( 'first' );
		}
		if ( 0 === ( index + 1 ) % columns ) {
			el.classList.add( 'last' );
		}
		index++;
	} );
};

/**
 * Get a child item element's product ID, from the item wrapper or the hidden .mnm-item-data element.
 *
 * @param {Element} el The .mnm_item element.
 * @return {number} Product ID, or 0.
 */
const getProductId = ( el ) => {
	const data = el.dataset.child_id ? el : el.querySelector( '.mnm-item-data' );
	return data ? parseInt( data.dataset.child_id || data.dataset.mnm_item_id, 10 ) || 0 : 0;
};

const { state, actions } = store( NAMESPACE, {
	state: {
		/**
		 * Whether a button's term is selected. "*" is pressed when nothing is selected.
		 *
		 * @return {boolean}
		 */
		get isPressed() {
			const { term, active } = getContext();
			return '*' === term ? 0 === active.length : active.includes( term );
		},

		/**
		 * The number of child items shown by the filter.
		 *
		 * @return {number}
		 */
		get shownCount() {
			const { items, active } = getContext();
			return Object.values( items ).filter( ( terms ) => matches( terms, active ) ).length;
		},

		/**
		 * Whether the filter hides every child item.
		 *
		 * @return {boolean}
		 */
		get hasNoResults() {
			return 0 === state.shownCount;
		},

		/**
		 * The quantity of selected items that the filter hides, from the container store.
		 *
		 * @return {number}
		 */
		get hiddenQuantity() {
			const { containerId, items, active } = getContext();
			const container = containerState.containers[ containerId ];

			if ( ! container || ! active.length ) {
				return 0;
			}

			return Object.keys( items ).reduce( ( total, productId ) => {
				const item = container.items[ productId ];

				if ( ! item || ! item.isSelected || matches( items[ productId ], active ) ) {
					return total;
				}

				return total + item.quantity;
			}, 0 );
		},

		/**
		 * Whether selected items are hidden by the filter.
		 *
		 * @return {boolean}
		 */
		get hasHiddenSelections() {
			return state.hiddenQuantity > 0;
		},

		/**
		 * Note how many selected items the filter hides, so shoppers aren't surprised by the total.
		 *
		 * @return {string}
		 */
		get hiddenSelectionsText() {
			const quantity = state.hiddenQuantity;
			const { i18n } = getConfig();

			return quantity ? format( 1 === quantity ? i18n.hiddenSingle : i18n.hiddenPlural, quantity ) : '';
		},
	},
	actions: {
		/**
		 * Select a button's term, or "*" for all. Multi-select toggles each term, single-select toggles the one term.
		 */
		select() {
			const context = getContext();
			const { term, active, multiple } = context;

			let next;

			if ( '*' === term ) {
				next = [];
			} else if ( active.includes( term ) ) {
				next = multiple ? active.filter( ( slug ) => slug !== term ) : [];
			} else {
				next = multiple ? [ ...active, term ] : [ term ];
			}

			context.active = next;

			actions.announce();
		},

		/**
		 * Clear the selected terms.
		 */
		showAll() {
			getContext().active = [];
			actions.announce();
		},

		/**
		 * Update the screen reader status.
		 */
		announce() {
			const context  = getContext();
			const { i18n } = getConfig();
			const total    = Object.keys( context.items ).length;

			context.status = 0 === context.active.length ?
				format( i18n.statusAll, total ) :
				format( i18n.status, state.shownCount, total );
		},
	},
	callbacks: {
		/**
		 * Show the filter once the script has loaded, it does nothing without it.
		 */
		init() {
			getContext().isReady = true;
		},

		/**
		 * Hide the child items, item wrappers and category captions that don't match.
		 *
		 * Runs again whenever the selected terms change.
		 */
		apply() {
			const { items, active } = getContext();
			const { ref }           = getElement();
			const form              = ref.closest( '.mnm_form' ) || ref.parentElement;
			const selected          = [ ...active ]; // Read every term, so the callback tracks the whole array.

			form.querySelectorAll( '.mnm_child_products .mnm_item' ).forEach( ( el ) => {
				const productId = getProductId( el );
				el.classList.toggle( HIDDEN_CLASS, productId in items && ! matches( items[ productId ], selected ) );
			} );

			// Hide item wrappers, and their category captions, without any matches.
			form.querySelectorAll( '.mnm_child_products' ).forEach( ( wrapper ) => {
				const hasMatches = !! wrapper.querySelector( `.mnm_item:not(.${ HIDDEN_CLASS })` );
				const caption    = getCaption( wrapper );

				wrapper.classList.toggle( HIDDEN_CLASS, ! hasMatches );

				if ( caption ) {
					caption.classList.toggle( HIDDEN_CLASS, ! hasMatches );
				}

				if ( form.classList.contains( 'layout_grid' ) ) {
					updateGridClasses( wrapper );
				}
			} );
		},
	},
} );
