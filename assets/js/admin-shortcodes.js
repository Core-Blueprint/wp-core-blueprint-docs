/**
 * Core Blueprint Docs admin shortcode controls.
 *
 * This adapter only binds Docs-owned buttons to the public Base Clipboard
 * Foundation. Clipboard access, fallback, icon state and Toast feedback remain
 * owned by Core Blueprint Base.
 *
 * @package CB_Docs
 */

import clipboard from '@cb-core/clipboard';

document.querySelectorAll( '[data-cb-docs-shortcode-copy]' ).forEach( ( button ) => {
	if ( ! ( button instanceof HTMLButtonElement ) ) return;

	clipboard.enhance( button, {
		text: () => button.dataset.cbDocsShortcodeCopy || '',
	} );
} );
