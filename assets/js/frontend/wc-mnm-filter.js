/**
 * Mix and Match - Filter by Terms
 *
 * Hides the child items that don't match the selected terms. The fallback for the Interactivity API
 * module (assets/js/modules/wc-mnm-filter.js), used before WordPress 6.5 or Mix and Match 3.0 and in
 * forms loaded with ajax. Starts with each container on the jQuery form's wc-mnm-initializing event.
 * Hidden items keep their quantities, so they're still counted and added to the cart.
 *
 * @since   1.0.0
 * @version 3.0.0
 */
/* global jQuery */
( function ( $ ) {
	'use strict';

	const params = window.WC_MNM_FILTER_PARAMS || {};

	const HIDDEN_CLASS = 'wc-mnm-filter-hidden';

	/**
	 * Replace printf-style placeholders: %d, %1$d, %2$d.
	 *
	 * @param {string}    template
	 * @param {...number} values
	 * @return {string} The formatted string.
	 */
	function format( template, ...values ) {
		let index = 0;
		return String( template || '' ).replace( /%(?:(\d+)\$)?d/g, ( match, position ) => {
			const value = position ? values[ position - 1 ] : values[ index++ ];
			return undefined === value ? match : String( value );
		} );
	}

	/**
	 * Work around core's get_type() before Mix and Match 3.0.
	 *
	 * Older versions detect fixed quantity items with `:hidden`, so an item hidden by the filter
	 * (display: none) would count as fixed and "Clear selections" would skip it. Swap in the
	 * 3.0 check, which looks at the input's type instead. Newer core is left alone.
	 *
	 * @param {Object} childItem The WC_MNM_Child_Item from add-to-cart-mnm.js.
	 */
	function patchGetType( childItem ) {
		if ( 'function' !== typeof childItem.get_type || -1 === String( childItem.get_type ).indexOf( ':hidden' ) ) {
			return;
		}

		childItem.get_type = function () {
			const $qty = this.$mnm_item_qty;

			if ( $qty.is( ':checkbox' ) ) {
				return 'checkbox';
			}
			if ( $qty.is( 'select' ) ) {
				return 'select';
			}
			if ( $qty.is( '[type="hidden"]' ) ) {
				return 'hidden';
			}
			return 'input';
		};
	}

	/**
	 * Read a child item's term slugs from the item wrapper, or the hidden .mnm-item-data element some templates use.
	 *
	 * @param {jQuery} $item
	 * @return {string[]} Term slugs.
	 */
	function getItemTerms( $item ) {
		let terms = $item.attr( 'data-filter_terms' );

		if ( undefined === terms ) {
			terms = $item.find( '.mnm-item-data' ).attr( 'data-filter_terms' );
		}

		return String( terms || '' ).split( ' ' ).filter( Boolean );
	}

	/**
	 * Find the category caption for an item wrapper.
	 *
	 * Other elements can sit between them, such as core's skip link target before the first wrapper,
	 * so look back until the caption, the previous wrapper or the filter.
	 *
	 * @param {Element} wrapper The .mnm_child_products element.
	 * @return {Element|null} The caption wrapper, if any.
	 */
	function getCaption( wrapper ) {
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
	}

	/**
	 * Filter for one container.
	 *
	 * @param {Object} container The WC_MNM_Container from add-to-cart-mnm.js.
	 */
	function WC_MNM_Filter( container ) {
		const $form = container.$mnm_form;
		const form = $form.get( 0 );
		const root = form ? form.querySelector( '.wc-mnm-filter' ) : null;

		// Filters printed with Interactivity API directives belong to the wc-mnm-filter script module.
		if ( ! root || root.dataset.initialized || root.hasAttribute( 'data-wp-interactive' ) ) {
			return;
		}

		root.dataset.initialized = 'yes';

		// $.map works whether child_items is an array or an object keyed by index.
		const items = $.map( container.child_items || [], ( childItem ) => {
			patchGetType( childItem );

			return {
				childItem,
				el: childItem.$self.get( 0 ),
				terms: getItemTerms( childItem.$self ),
			};
		} );

		// Remove buttons that wouldn't show anything, eg terms added with the wc_mnm_filter_terms filter.
		root.querySelectorAll( '.wc-mnm-filter__button:not([data-filter="*"])' ).forEach( ( button ) => {
			if ( ! items.some( ( item ) => item.terms.includes( button.dataset.filter ) ) ) {
				button.remove();
			}
		} );

		const buttons = root.querySelectorAll( '.wc-mnm-filter__button' );

		// Show all plus at least two terms, or there's nothing to filter.
		if ( buttons.length < 3 ) {
			return;
		}

		root.hidden = false;

		const multiple = 'yes' === root.dataset.multiple;
		const status = root.querySelector( '.wc-mnm-filter__status' );
		const noResults = root.querySelector( '.wc-mnm-filter__no-results' );
		const hiddenSelections = root.querySelector( '.wc-mnm-filter__hidden-selections' );
		const active = new Set();

		/**
		 * Whether an item matches the selected terms. Items matching any selected term are shown.
		 *
		 * @param {Object} item
		 * @return {boolean} True when nothing is selected or the item has a selected term.
		 */
		const matches = ( item ) => 0 === active.size || item.terms.some( ( term ) => active.has( term ) );

		/**
		 * Restore the WooCommerce loop's first and last classes for themes with float grids.
		 *
		 * @param {Element} wrapper
		 */
		const updateGridClasses = ( wrapper ) => {
			const match = wrapper.className.match( /\bcolumns-(\d+)\b/ );
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
		 * Note how many selected items the filter hides, so shoppers aren't surprised by the total.
		 */
		const updateHiddenSelections = () => {
			const quantity = items.reduce( ( total, item ) => {
				return total + ( matches( item ) ? 0 : parseFloat( item.childItem.get_quantity() ) || 0 );
			}, 0 );

			hiddenSelections.textContent = '';
			hiddenSelections.hidden = 0 === quantity;

			if ( quantity ) {
				const button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'wc-mnm-filter__show-all-link';
				button.textContent = params.i18n_show_all;
				button.addEventListener( 'click', () => select( '*' ) );

				hiddenSelections.append(
					format( 1 === quantity ? params.i18n_hidden_single : params.i18n_hidden_plural, quantity ),
					' ',
					button
				);
			}
		};

		/**
		 * Show the matching items.
		 *
		 * @param {boolean} announce Whether to update the screen reader status.
		 */
		const apply = ( announce ) => {
			buttons.forEach( ( button ) => {
				const pressed = '*' === button.dataset.filter ? 0 === active.size : active.has( button.dataset.filter );
				button.setAttribute( 'aria-pressed', pressed ? 'true' : 'false' );
				button.classList.toggle( 'selected', pressed );
			} );

			let shown = 0;

			items.forEach( ( item ) => {
				const show = matches( item );
				item.el.classList.toggle( HIDDEN_CLASS, ! show );
				shown += show ? 1 : 0;
			} );

			// Hide item wrappers, and their category captions, without any matches.
			form.querySelectorAll( '.mnm_child_products' ).forEach( ( wrapper ) => {
				const hasMatches = !! wrapper.querySelector( `.mnm_item:not(.${ HIDDEN_CLASS })` );
				const caption = getCaption( wrapper );

				wrapper.classList.toggle( HIDDEN_CLASS, ! hasMatches );

				if ( caption ) {
					caption.classList.toggle( HIDDEN_CLASS, ! hasMatches );
				}

				if ( form.classList.contains( 'layout_grid' ) ) {
					updateGridClasses( wrapper );
				}
			} );

			noResults.hidden = shown > 0;

			if ( announce ) {
				status.textContent = 0 === active.size ?
					format( params.i18n_status_all, items.length ) :
					format( params.i18n_status, shown, items.length );
			}

			updateHiddenSelections();

			$form.trigger( 'wc-mnm-filters-updated', [ container, Array.from( active ) ] );
		};

		/**
		 * Select a term, or "*" for all. Multi-select toggles each term, single-select toggles the one term.
		 *
		 * @param {string} term
		 */
		const select = ( term ) => {
			if ( '*' === term ) {
				active.clear();
			} else if ( multiple ) {
				if ( active.has( term ) ) {
					active.delete( term );
				} else {
					active.add( term );
				}
			} else {
				const isOnlySelection = active.has( term ) && 1 === active.size;
				active.clear();
				if ( ! isOnlySelection ) {
					active.add( term );
				}
			}

			apply( true );
		};

		root.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '.wc-mnm-filter__button' );

			if ( button && root.contains( button ) ) {
				event.preventDefault();
				select( button.dataset.filter );
			}
		} );

		$form.on( 'wc-mnm-container-quantities-updated', updateHiddenSelections );

		apply( false );
	}

	/*-----------------------------------------------------------------*/
	/*  Initialization.                                                */
	/*-----------------------------------------------------------------*/

	$( document.body ).on( 'wc-mnm-initializing', ( event, container ) => {
		new WC_MNM_Filter( container ); // eslint-disable-line no-new
	} );
} )( jQuery );
