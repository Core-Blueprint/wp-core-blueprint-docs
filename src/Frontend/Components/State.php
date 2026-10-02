<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Components;

defined( 'ABSPATH' ) || exit;

final class State {
	public static function render( string $state, string $message ): string {
		return '<div class="cb-docs-state" data-cb-docs-state="' . esc_attr( $state ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}
}
