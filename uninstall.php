<?php
/**
 * Core Blueprint Docs intentionally preserves documentation content on uninstall.
 *
 * @package CB_Docs
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Preserve documentation content and structure, but never retain an operational mutation lock.
delete_option( 'cb_docs_structure_lock' );
