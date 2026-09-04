<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders\Bricks;

use CB\Docs\Frontend\Conditions\Documents;

defined( 'ABSPATH' ) || exit;

final class Conditions {
	private const GROUP    = 'cb_docs';
	private const IS_DOC   = 'cb_docs_is_document';
	private const CAN_READ = 'cb_docs_user_can_read';
	private const CATEGORY = 'cb_docs_in_category';
	private const TAG      = 'cb_docs_has_tag';

	public static function init(): void {
		add_filter( 'bricks/conditions/groups', [ self::class, 'register_group' ] );
		add_filter( 'bricks/conditions/options', [ self::class, 'register_options' ] );
		add_filter( 'bricks/conditions/result', [ self::class, 'result' ], 10, 3 );
	}

	/** @param array<int,array<string,mixed>> $groups @return array<int,array<string,mixed>> */
	public static function register_group( array $groups ): array {
		$groups[] = [
			'name'  => self::GROUP,
			'label' => __( 'Core Blueprint Docs', 'core-blueprint-docs' ),
		];
		return $groups;
	}

	/** @param array<int,array<string,mixed>> $options @return array<int,array<string,mixed>> */
	public static function register_options( array $options ): array {
		$options[] = [
			'key'   => self::IS_DOC,
			'label' => __( 'Current item is a Doc', 'core-blueprint-docs' ),
			'group' => self::GROUP,
		];
		$options[] = [
			'key'   => self::CAN_READ,
			'label' => __( 'User can read current Doc', 'core-blueprint-docs' ),
			'group' => self::GROUP,
		];
		$options[] = self::term_condition(
			self::CATEGORY,
			__( 'Doc category', 'core-blueprint-docs' ),
			__( 'Category slug or ID', 'core-blueprint-docs' )
		);
		$options[] = self::term_condition(
			self::TAG,
			__( 'Doc tag', 'core-blueprint-docs' ),
			__( 'Tag slug or ID', 'core-blueprint-docs' )
		);
		return $options;
	}

	public static function result( bool $result, string $condition_key, array $condition ): bool {
		if ( ! in_array( $condition_key, [ self::IS_DOC, self::CAN_READ, self::CATEGORY, self::TAG ], true ) ) {
			return $result;
		}

		$document_id = DocumentContext::identifier();
		if ( null === $document_id ) {
			return false;
		}

		if ( self::IS_DOC === $condition_key ) {
			return true;
		}
		if ( self::CAN_READ === $condition_key ) {
			return Documents::user_can_read( $document_id );
		}

		$value = isset( $condition['value'] ) && is_scalar( $condition['value'] )
			? trim( (string) $condition['value'] )
			: '';
		if ( '' === $value ) {
			return false;
		}

		$compare = isset( $condition['compare'] ) && is_scalar( $condition['compare'] )
			? (string) $condition['compare']
			: '==';
		$matches = self::CATEGORY === $condition_key
			? Documents::in_category( $value, $document_id )
			: Documents::has_tag( $value, $document_id );

		return '!=' === $compare ? ! $matches : $matches;
	}

	/** @return array<string,mixed> */
	private static function term_condition( string $key, string $label, string $placeholder ): array {
		return [
			'key'     => $key,
			'label'   => $label,
			'group'   => self::GROUP,
			'compare' => [
				'type'        => 'select',
				'options'     => [
					'==' => __( 'is', 'core-blueprint-docs' ),
					'!=' => __( 'is not', 'core-blueprint-docs' ),
				],
				'placeholder' => __( 'is', 'core-blueprint-docs' ),
			],
			'value'   => [
				'type'        => 'text',
				'placeholder' => $placeholder,
			],
		];
	}
}
