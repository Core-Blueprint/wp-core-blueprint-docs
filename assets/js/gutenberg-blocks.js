( function ( wp, config ) {
	'use strict';

	if ( ! wp?.blocks || ! wp?.element || ! wp?.blockEditor || ! wp?.components || ! wp?.serverSideRender ) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, RangeControl, TextControl, ToggleControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const labels = config?.labels || {};

	const panel = ( controls ) => el(
		InspectorControls,
		null,
		el( PanelBody, { title: labels.contentSettings || 'Content settings', initialOpen: true }, ...controls )
	);

	const preview = ( block, attributes, inspector = null ) => el(
		Fragment,
		null,
		inspector,
		el(
			'div',
			useBlockProps(),
			el( ServerSideRender, { block, attributes, skipBlockSupportAttributes: true } )
		)
	);

	registerBlockType( 'core-blueprint-docs/document-list', {
		edit: ( { attributes, setAttributes } ) => preview(
			'core-blueprint-docs/document-list',
			attributes,
			panel( [
				el( TextControl, { key: 'category', label: labels.categorySlug || 'Category slug', value: attributes.category || '', onChange: ( category ) => setAttributes( { category } ) } ),
				el( TextControl, { key: 'tag', label: labels.tagSlug || 'Tag slug', value: attributes.tag || '', onChange: ( tag ) => setAttributes( { tag } ) } ),
				el( RangeControl, { key: 'limit', label: labels.resultLimit || 'Result limit', value: attributes.limit || 20, min: 1, max: 100, onChange: ( limit ) => setAttributes( { limit } ) } ),
				el( ToggleControl, { key: 'excerpt', label: labels.showExcerpt || 'Show excerpts', checked: attributes.showExcerpt !== false, onChange: ( showExcerpt ) => setAttributes( { showExcerpt } ) } ),
			] )
		),
		save: () => null,
	} );

	registerBlockType( 'core-blueprint-docs/navigation', {
		edit: ( { attributes, setAttributes } ) => preview(
			'core-blueprint-docs/navigation',
			attributes,
			panel( [
				el( TextControl, { key: 'category', label: labels.categorySlug || 'Category slug', value: attributes.category || '', onChange: ( category ) => setAttributes( { category } ) } ),
			] )
		),
		save: () => null,
	} );

	registerBlockType( 'core-blueprint-docs/search', {
		edit: ( { attributes, setAttributes } ) => preview(
			'core-blueprint-docs/search',
			attributes,
			panel( [
				el( TextControl, { key: 'placeholder', label: labels.placeholder || 'Placeholder', value: attributes.placeholder || '', onChange: ( placeholder ) => setAttributes( { placeholder } ) } ),
				el( RangeControl, { key: 'limit', label: labels.resultLimit || 'Result limit', value: attributes.limit || 20, min: 1, max: 50, onChange: ( limit ) => setAttributes( { limit } ) } ),
				el( RangeControl, { key: 'minChars', label: labels.minimumChars || 'Minimum characters', value: attributes.minChars || 2, min: 1, max: 10, onChange: ( minChars ) => setAttributes( { minChars } ) } ),
				el( ToggleControl, { key: 'excerpt', label: labels.showExcerpt || 'Show excerpts', checked: attributes.showExcerpt !== false, onChange: ( showExcerpt ) => setAttributes( { showExcerpt } ) } ),
				el( ToggleControl, { key: 'category-display', label: labels.showCategory || 'Show result category', checked: attributes.showCategory !== false, onChange: ( showCategory ) => setAttributes( { showCategory } ) } ),
				el( TextControl, { key: 'category', label: labels.categorySlug || 'Category slug', value: attributes.category || '', onChange: ( category ) => setAttributes( { category } ) } ),
				el( TextControl, { key: 'tag', label: labels.tagSlug || 'Tag slug', value: attributes.tag || '', onChange: ( tag ) => setAttributes( { tag } ) } ),
			] )
		),
		save: () => null,
	} );

	registerBlockType( 'core-blueprint-docs/breadcrumbs', {
		edit: ( { attributes } ) => preview( 'core-blueprint-docs/breadcrumbs', attributes ),
		save: () => null,
	} );

	registerBlockType( 'core-blueprint-docs/document-meta', {
		edit: ( { attributes, setAttributes } ) => preview(
			'core-blueprint-docs/document-meta',
			attributes,
			panel( [
				el( TextControl, {
					key: 'document-id',
					type: 'number',
					min: 0,
					label: labels.documentId || 'Document ID',
					help: labels.documentIdHelp || 'Leave at 0 to use the current Docs document.',
					value: attributes.documentId || 0,
					onChange: ( value ) => setAttributes( { documentId: Math.max( 0, Number.parseInt( value || '0', 10 ) || 0 ) } ),
				} ),
			] )
		),
		save: () => null,
	} );
}( window.wp, window.cbDocsBlocks ) );
