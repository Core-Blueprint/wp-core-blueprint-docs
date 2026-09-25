<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders\Bricks\Elements;

use CB\Docs\Frontend\Assets;
use CB\Docs\Frontend\Components\Search as SearchComponent;
use CB\Docs\Integration\Builders\Bricks\ElementRegistry;

defined( 'ABSPATH' ) || exit;

final class Search extends \Bricks\Element {
	public $category = ElementRegistry::CATEGORY;
	public $name     = 'cb-docs-search';
	public $icon     = 'ti-search';

	public function get_label(): string {
		return esc_html__( 'Docs Search', 'core-blueprint-docs' );
	}

	/** @return string[] */
	public function get_keywords(): array {
		return [ 'core blueprint', 'docs', 'documentation', 'search' ];
	}

	public function set_control_groups(): void {
		foreach ( [
			'search'        => esc_html__( 'Search', 'core-blueprint-docs' ),
			'formLayout'    => esc_html__( 'Form layout', 'core-blueprint-docs' ),
			'input'         => esc_html__( 'Input', 'core-blueprint-docs' ),
			'button'        => esc_html__( 'Button', 'core-blueprint-docs' ),
			'resultsLayout' => esc_html__( 'Results layout', 'core-blueprint-docs' ),
			'resultItems'   => esc_html__( 'Result items', 'core-blueprint-docs' ),
			'status'        => esc_html__( 'Status', 'core-blueprint-docs' ),
		] as $key => $title ) {
			$this->control_groups[ $key ] = [
				'title' => $title,
				'tab'   => 'content',
			];
		}
	}

	public function set_controls(): void {
		$this->controls['usage'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'type'    => 'info',
			'content' => esc_html__( 'Live search is powered by the builder-neutral Core Blueprint Docs search service. Bricks only controls presentation.', 'core-blueprint-docs' ),
		];
		$this->controls['placeholder'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'label'   => esc_html__( 'Placeholder', 'core-blueprint-docs' ),
			'type'    => 'text',
			'default' => esc_html__( 'Search documentation…', 'core-blueprint-docs' ),
		];
		$this->controls['limit'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'label'   => esc_html__( 'Result limit', 'core-blueprint-docs' ),
			'type'    => 'number',
			'default' => 20,
			'min'     => 1,
			'max'     => 50,
			'step'    => 1,
		];
		$this->controls['minChars'] = [
			'tab'         => 'content',
			'group'       => 'search',
			'label'       => esc_html__( 'Minimum characters', 'core-blueprint-docs' ),
			'type'        => 'number',
			'default'     => 2,
			'min'         => 1,
			'max'         => 10,
			'step'        => 1,
			'description' => esc_html__( 'Live search starts after this many characters.', 'core-blueprint-docs' ),
		];
		$this->controls['showExcerpt'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'label'   => esc_html__( 'Show excerpts', 'core-blueprint-docs' ),
			'type'    => 'checkbox',
			'default' => true,
		];
		$this->controls['showCategory'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'label'   => esc_html__( 'Show category', 'core-blueprint-docs' ),
			'type'    => 'checkbox',
			'default' => true,
		];
		$this->controls['category'] = [
			'tab'         => 'content',
			'group'       => 'search',
			'label'       => esc_html__( 'Category slug', 'core-blueprint-docs' ),
			'type'        => 'text',
			'description' => esc_html__( 'Optional. Limit search to one Docs category.', 'core-blueprint-docs' ),
		];
		$this->controls['tag'] = [
			'tab'         => 'content',
			'group'       => 'search',
			'label'       => esc_html__( 'Tag slug', 'core-blueprint-docs' ),
			'type'        => 'text',
			'description' => esc_html__( 'Optional. Limit search to one Docs tag.', 'core-blueprint-docs' ),
		];

		$this->controls['formDisplay'] = [
			'tab'     => 'content',
			'group'   => 'formLayout',
			'label'   => esc_html__( 'Display', 'core-blueprint-docs' ),
			'type'    => 'select',
			'options' => [
				'block' => 'block',
				'flex'  => 'flex',
				'grid'  => 'grid',
			],
			'css'     => [ [ 'property' => 'display', 'selector' => '.cb-docs-search__form' ] ],
		];
		$this->controls['formFlexWrap'] = [
			'tab'      => 'content',
			'group'    => 'formLayout',
			'label'    => esc_html__( 'Flex wrap', 'core-blueprint-docs' ),
			'type'     => 'select',
			'options'  => [
				'nowrap'       => esc_html__( 'No wrap', 'core-blueprint-docs' ),
				'wrap'         => esc_html__( 'Wrap', 'core-blueprint-docs' ),
				'wrap-reverse' => esc_html__( 'Wrap reverse', 'core-blueprint-docs' ),
			],
			'inline'   => true,
			'css'      => [ [ 'property' => 'flex-wrap', 'selector' => '.cb-docs-search__form' ] ],
			'required' => [ 'formDisplay', '=', 'flex' ],
		];
		$this->controls['formDirection'] = [
			'tab'      => 'content',
			'group'    => 'formLayout',
			'label'    => esc_html__( 'Direction', 'core-blueprint-docs' ),
			'type'     => 'direction',
			'inline'   => true,
			'rerender' => true,
			'css'      => [ [ 'property' => 'flex-direction', 'selector' => '.cb-docs-search__form' ] ],
			'required' => [ 'formDisplay', '=', 'flex' ],
		];
		$this->controls['formJustifyContent'] = [
			'tab'      => 'content',
			'group'    => 'formLayout',
			'label'    => esc_html__( 'Align main axis', 'core-blueprint-docs' ),
			'type'     => 'justify-content',
			'css'      => [ [ 'property' => 'justify-content', 'selector' => '.cb-docs-search__form' ] ],
			'required' => [ 'formDisplay', '=', 'flex' ],
		];
		$this->controls['formAlignItems'] = [
			'tab'      => 'content',
			'group'    => 'formLayout',
			'label'    => esc_html__( 'Align cross axis', 'core-blueprint-docs' ),
			'type'     => 'align-items',
			'css'      => [ [ 'property' => 'align-items', 'selector' => '.cb-docs-search__form' ] ],
			'required' => [ 'formDisplay', '=', 'flex' ],
		];
		$this->controls['formColumnGap'] = [
			'tab'      => 'content',
			'group'    => 'formLayout',
			'label'    => esc_html__( 'Column gap', 'core-blueprint-docs' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [ [ 'property' => 'column-gap', 'selector' => '.cb-docs-search__form' ] ],
			'required' => [ 'formDisplay', '=', 'flex' ],
		];
		$this->controls['formRowGap'] = [
			'tab'      => 'content',
			'group'    => 'formLayout',
			'label'    => esc_html__( 'Row gap', 'core-blueprint-docs' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [ [ 'property' => 'row-gap', 'selector' => '.cb-docs-search__form' ] ],
			'required' => [ 'formDisplay', '=', 'flex' ],
		];
		$this->controls['formGridColumns'] = [
			'tab'      => 'content',
			'group'    => 'formLayout',
			'label'    => esc_html__( 'Grid columns', 'core-blueprint-docs' ),
			'type'     => 'select',
			'options'  => [
				'minmax(0, 1fr) auto' => esc_html__( 'Input + button', 'core-blueprint-docs' ),
				'1fr'                 => esc_html__( 'Stacked', 'core-blueprint-docs' ),
			],
			'css'      => [ [ 'property' => 'grid-template-columns', 'selector' => '.cb-docs-search__form' ] ],
			'required' => [ 'formDisplay', '=', 'grid' ],
		];
		$this->controls['formGridGap'] = [
			'tab'      => 'content',
			'group'    => 'formLayout',
			'label'    => esc_html__( 'Grid gap', 'core-blueprint-docs' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [ [ 'property' => 'gap', 'selector' => '.cb-docs-search__form' ] ],
			'required' => [ 'formDisplay', '=', 'grid' ],
		];

		$this->controls['inputTypography'] = [
			'tab'   => 'content',
			'group' => 'input',
			'label' => esc_html__( 'Typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__input' ] ],
		];
		$this->controls['inputBackground'] = [
			'tab'     => 'content',
			'group'   => 'input',
			'label'   => esc_html__( 'Background', 'core-blueprint-docs' ),
			'type'    => 'background',
			'exclude' => [ 'videoUrl', 'videoScale' ],
			'css'     => [ [ 'property' => 'background', 'selector' => '.cb-docs-search__input' ] ],
		];
		$this->controls['inputBorder'] = [
			'tab'   => 'content',
			'group' => 'input',
			'label' => esc_html__( 'Border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__input' ] ],
		];
		$this->controls['inputPadding'] = [
			'tab'   => 'content',
			'group' => 'input',
			'label' => esc_html__( 'Padding', 'core-blueprint-docs' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'padding', 'selector' => '.cb-docs-search__input' ] ],
		];
		$this->controls['inputMinHeight'] = [
			'tab'   => 'content',
			'group' => 'input',
			'label' => esc_html__( 'Minimum height', 'core-blueprint-docs' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [ [ 'property' => 'min-height', 'selector' => '.cb-docs-search__input' ] ],
		];
		$this->controls['inputFocusBorder'] = [
			'tab'   => 'content',
			'group' => 'input',
			'label' => esc_html__( 'Focus border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__input:focus' ] ],
		];
		$this->controls['inputFocusShadow'] = [
			'tab'   => 'content',
			'group' => 'input',
			'label' => esc_html__( 'Focus box shadow', 'core-blueprint-docs' ),
			'type'  => 'box-shadow',
			'css'   => [ [ 'property' => 'box-shadow', 'selector' => '.cb-docs-search__input:focus' ] ],
		];

		$this->controls['buttonTypography'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__submit' ] ],
		];
		$this->controls['buttonBackground'] = [
			'tab'     => 'content',
			'group'   => 'button',
			'label'   => esc_html__( 'Background', 'core-blueprint-docs' ),
			'type'    => 'background',
			'exclude' => [ 'videoUrl', 'videoScale' ],
			'css'     => [ [ 'property' => 'background', 'selector' => '.cb-docs-search__submit' ] ],
		];
		$this->controls['buttonBorder'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__submit' ] ],
		];
		$this->controls['buttonPadding'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Padding', 'core-blueprint-docs' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'padding', 'selector' => '.cb-docs-search__submit' ] ],
		];
		$this->controls['buttonMinHeight'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Minimum height', 'core-blueprint-docs' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [ [ 'property' => 'min-height', 'selector' => '.cb-docs-search__submit' ] ],
		];
		$this->controls['buttonHoverColor'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Hover text color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'color', 'selector' => '.cb-docs-search__submit:hover' ] ],
		];
		$this->controls['buttonHoverBackground'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Hover background color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'background-color', 'selector' => '.cb-docs-search__submit:hover' ] ],
		];
		$this->controls['buttonHoverBorder'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Hover border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__submit:hover' ] ],
		];
		$this->controls['buttonFocusColor'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Focus text color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'color', 'selector' => '.cb-docs-search__submit:focus-visible' ] ],
		];
		$this->controls['buttonFocusBackground'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Focus background color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'background-color', 'selector' => '.cb-docs-search__submit:focus-visible' ] ],
		];
		$this->controls['buttonFocusBorder'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Focus border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__submit:focus-visible' ] ],
		];

		$this->controls['resultsBackground'] = [
			'tab'     => 'content',
			'group'   => 'resultsLayout',
			'label'   => esc_html__( 'Results background', 'core-blueprint-docs' ),
			'type'    => 'background',
			'exclude' => [ 'videoUrl', 'videoScale' ],
			'css'     => [ [ 'property' => 'background', 'selector' => '.cb-docs-search__results' ] ],
		];
		$this->controls['resultsBorder'] = [
			'tab'   => 'content',
			'group' => 'resultsLayout',
			'label' => esc_html__( 'Results border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__results' ] ],
		];
		$this->controls['resultsPadding'] = [
			'tab'   => 'content',
			'group' => 'resultsLayout',
			'label' => esc_html__( 'Results padding', 'core-blueprint-docs' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'padding', 'selector' => '.cb-docs-search__results' ] ],
		];
		$this->controls['listDisplay'] = [
			'tab'     => 'content',
			'group'   => 'resultsLayout',
			'label'   => esc_html__( 'List display', 'core-blueprint-docs' ),
			'type'    => 'select',
			'options' => [
				'block' => 'block',
				'flex'  => 'flex',
				'grid'  => 'grid',
			],
			'css'     => [ [ 'property' => 'display', 'selector' => '.cb-docs-search__list' ] ],
		];
		$this->controls['listStyleType'] = [
			'tab'     => 'content',
			'group'   => 'resultsLayout',
			'label'   => esc_html__( 'List marker', 'core-blueprint-docs' ),
			'type'    => 'select',
			'options' => [
				''                     => esc_html__( 'Browser default', 'core-blueprint-docs' ),
				'none'                 => esc_html__( 'None', 'core-blueprint-docs' ),
				'disc'                 => esc_html__( 'Disc', 'core-blueprint-docs' ),
				'circle'               => esc_html__( 'Circle', 'core-blueprint-docs' ),
				'square'               => esc_html__( 'Square', 'core-blueprint-docs' ),
				'decimal'              => esc_html__( 'Decimal', 'core-blueprint-docs' ),
				'decimal-leading-zero' => esc_html__( 'Decimal leading zero', 'core-blueprint-docs' ),
				'lower-alpha'          => esc_html__( 'Lower alpha', 'core-blueprint-docs' ),
				'upper-alpha'          => esc_html__( 'Upper alpha', 'core-blueprint-docs' ),
				'lower-roman'          => esc_html__( 'Lower roman', 'core-blueprint-docs' ),
				'upper-roman'          => esc_html__( 'Upper roman', 'core-blueprint-docs' ),
			],
			'default' => '',
			'css'     => [ [ 'property' => 'list-style-type', 'selector' => '.cb-docs-search__list' ] ],
		];
		$this->controls['listMargin'] = [
			'tab'   => 'content',
			'group' => 'resultsLayout',
			'label' => esc_html__( 'List margin', 'core-blueprint-docs' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'margin', 'selector' => '.cb-docs-search__list' ] ],
		];
		$this->controls['listPadding'] = [
			'tab'   => 'content',
			'group' => 'resultsLayout',
			'label' => esc_html__( 'List padding', 'core-blueprint-docs' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'padding', 'selector' => '.cb-docs-search__list' ] ],
		];
		$this->controls['listColumns'] = [
			'tab'      => 'content',
			'group'    => 'resultsLayout',
			'label'    => esc_html__( 'Grid columns', 'core-blueprint-docs' ),
			'type'     => 'select',
			'options'  => [
				'1fr'                       => '1',
				'repeat(2, minmax(0, 1fr))' => '2',
				'repeat(3, minmax(0, 1fr))' => '3',
				'repeat(4, minmax(0, 1fr))' => '4',
			],
			'css'      => [ [ 'property' => 'grid-template-columns', 'selector' => '.cb-docs-search__list' ] ],
			'required' => [ 'listDisplay', '=', 'grid' ],
		];
		$this->controls['listFlexWrap'] = [
			'tab'      => 'content',
			'group'    => 'resultsLayout',
			'label'    => esc_html__( 'Flex wrap', 'core-blueprint-docs' ),
			'type'     => 'select',
			'options'  => [
				'nowrap'       => esc_html__( 'No wrap', 'core-blueprint-docs' ),
				'wrap'         => esc_html__( 'Wrap', 'core-blueprint-docs' ),
				'wrap-reverse' => esc_html__( 'Wrap reverse', 'core-blueprint-docs' ),
			],
			'inline'   => true,
			'css'      => [ [ 'property' => 'flex-wrap', 'selector' => '.cb-docs-search__list' ] ],
			'required' => [ 'listDisplay', '=', 'flex' ],
		];
		$this->controls['listDirection'] = [
			'tab'      => 'content',
			'group'    => 'resultsLayout',
			'label'    => esc_html__( 'Direction', 'core-blueprint-docs' ),
			'type'     => 'direction',
			'inline'   => true,
			'rerender' => true,
			'css'      => [ [ 'property' => 'flex-direction', 'selector' => '.cb-docs-search__list' ] ],
			'required' => [ 'listDisplay', '=', 'flex' ],
		];
		$this->controls['listJustifyContent'] = [
			'tab'      => 'content',
			'group'    => 'resultsLayout',
			'label'    => esc_html__( 'Align main axis', 'core-blueprint-docs' ),
			'type'     => 'justify-content',
			'css'      => [ [ 'property' => 'justify-content', 'selector' => '.cb-docs-search__list' ] ],
			'required' => [ 'listDisplay', '=', 'flex' ],
		];
		$this->controls['listAlignItems'] = [
			'tab'      => 'content',
			'group'    => 'resultsLayout',
			'label'    => esc_html__( 'Align cross axis', 'core-blueprint-docs' ),
			'type'     => 'align-items',
			'css'      => [ [ 'property' => 'align-items', 'selector' => '.cb-docs-search__list' ] ],
			'required' => [ 'listDisplay', '=', 'flex' ],
		];
		$this->controls['listColumnGap'] = [
			'tab'      => 'content',
			'group'    => 'resultsLayout',
			'label'    => esc_html__( 'Column gap', 'core-blueprint-docs' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [ [ 'property' => 'column-gap', 'selector' => '.cb-docs-search__list' ] ],
			'required' => [ 'listDisplay', '=', 'flex' ],
		];
		$this->controls['listRowGap'] = [
			'tab'      => 'content',
			'group'    => 'resultsLayout',
			'label'    => esc_html__( 'Row gap', 'core-blueprint-docs' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [ [ 'property' => 'row-gap', 'selector' => '.cb-docs-search__list' ] ],
			'required' => [ 'listDisplay', '=', 'flex' ],
		];
		$this->controls['listGridGap'] = [
			'tab'      => 'content',
			'group'    => 'resultsLayout',
			'label'    => esc_html__( 'Grid gap', 'core-blueprint-docs' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [ [ 'property' => 'gap', 'selector' => '.cb-docs-search__list' ] ],
			'required' => [ 'listDisplay', '=', 'grid' ],
		];

		$this->controls['itemBackground'] = [
			'tab'     => 'content',
			'group'   => 'resultItems',
			'label'   => esc_html__( 'Item background', 'core-blueprint-docs' ),
			'type'    => 'background',
			'exclude' => [ 'videoUrl', 'videoScale' ],
			'css'     => [ [ 'property' => 'background', 'selector' => '.cb-docs-search__option' ] ],
		];
		$this->controls['itemBorder'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Item border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__option' ] ],
		];
		$this->controls['itemPadding'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Item padding', 'core-blueprint-docs' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'padding', 'selector' => '.cb-docs-search__option' ] ],
		];
		$this->controls['itemShadow'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Item box shadow', 'core-blueprint-docs' ),
			'type'  => 'box-shadow',
			'css'   => [ [ 'property' => 'box-shadow', 'selector' => '.cb-docs-search__option' ] ],
		];
		$this->controls['itemHoverBackground'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Hover background color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'background-color', 'selector' => '.cb-docs-search__option:hover' ] ],
		];
		$this->controls['itemFocusBackground'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Focus background color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'background-color', 'selector' => '.cb-docs-search__option:focus-within' ] ],
		];
		$this->controls['itemSelectedBackground'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Selected background color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'background-color', 'selector' => '.cb-docs-search__option[aria-selected="true"]' ] ],
		];
		$this->controls['resultTitleTypography'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Title typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__title' ] ],
		];
		$this->controls['resultTitleHoverColor'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Title hover color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'color', 'selector' => '.cb-docs-search__result:hover .cb-docs-search__title' ] ],
		];
		$this->controls['resultTitleFocusColor'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Title focus color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'color', 'selector' => '.cb-docs-search__result:focus-visible .cb-docs-search__title' ] ],
		];
		$this->controls['resultTitleSelectedColor'] = [
			'tab'   => 'content',
			'group' => 'resultItems',
			'label' => esc_html__( 'Title selected color', 'core-blueprint-docs' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'color', 'selector' => '.cb-docs-search__option[aria-selected="true"] .cb-docs-search__title' ] ],
		];
		$this->controls['resultCategoryTypography'] = [
			'tab'      => 'content',
			'group'    => 'resultItems',
			'label'    => esc_html__( 'Category typography', 'core-blueprint-docs' ),
			'type'     => 'typography',
			'css'      => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__category' ] ],
			'required' => [ 'showCategory', '=', true ],
		];
		$this->controls['resultExcerptTypography'] = [
			'tab'      => 'content',
			'group'    => 'resultItems',
			'label'    => esc_html__( 'Excerpt typography', 'core-blueprint-docs' ),
			'type'     => 'typography',
			'css'      => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__excerpt' ] ],
			'required' => [ 'showExcerpt', '=', true ],
		];

		$this->controls['statusTypography'] = [
			'tab'   => 'content',
			'group' => 'status',
			'label' => esc_html__( 'Typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__status' ] ],
		];
	}

	public function enqueue_scripts(): void {
		Assets::enqueue_search();
	}

	public function render(): void {
		$settings = $this->settings;
		$args = [
			'placeholder'   => (string) ( $settings['placeholder'] ?? __( 'Search documentation…', 'core-blueprint-docs' ) ),
			'limit'         => $settings['limit'] ?? 20,
			'min_chars'     => $settings['minChars'] ?? 2,
			'show_excerpt'  => self::checkbox( $settings, 'showExcerpt', true ),
			'show_category' => self::checkbox( $settings, 'showCategory', true ),
			'category'      => (string) ( $settings['category'] ?? '' ),
			'tag'           => (string) ( $settings['tag'] ?? '' ),
		];

		$this->set_attribute( '_root', 'class', 'cb-docs-bricks-search' );
		echo '<div ' . $this->render_attributes( '_root' ) . '>' . SearchComponent::render( $args ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted builder-neutral Docs renderer output.
	}

	/** @param array<string,mixed> $settings */
	private static function checkbox( array $settings, string $key, bool $default ): bool {
		if ( ! array_key_exists( $key, $settings ) ) {
			return $default;
		}
		return (bool) $settings[ $key ];
	}
}
