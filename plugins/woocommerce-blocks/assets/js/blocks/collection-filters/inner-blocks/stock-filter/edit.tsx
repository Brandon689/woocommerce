/**
 * External dependencies
 */
import { useMemo } from '@wordpress/element';
import classnames from 'classnames';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { CheckboxList } from '@woocommerce/blocks-components';
import Label from '@woocommerce/base-components/filter-element-label';
import type { BlockEditProps, Template } from '@wordpress/blocks';
import { getSetting } from '@woocommerce/settings';
import { useCollectionData } from '@woocommerce/base-context/hooks';

/**
 * Internal dependencies
 */
import { BlockProps } from './types';
import { Inspector } from './components/inspector';
import { PreviewDropdown } from '../components/preview-dropdown';

type CollectionData = {
	stock_status_counts: StockStatusCount[];
};

type StockStatusCount = {
	status: string;
	count: number;
};

const Edit = ( props: BlockEditProps< BlockProps > ) => {
	const blockProps = useBlockProps( {
		className: classnames(
			'wc-block-stock-filter',
			props.attributes.className
		),
	} );

	const template: Template[] = [
		[
			'core/heading',
			{
				content: __( 'Filter by Stock Status', 'woocommerce' ),
				level: 3,
			},
		],
	];

	const { showCounts, displayStyle } = props.attributes;
	const stockStatusOptions: Record< string, string > = getSetting(
		'stockStatusOptions',
		{}
	);

	const { results: filteredCounts } = useCollectionData( {
		queryStock: true,
		queryState: {},
		isEditor: true,
	} );

	const listOptions = useMemo( () => {
		return Object.entries( stockStatusOptions ).map( ( [ key, value ] ) => {
			const count =
				// @ts-expect-error - there is a fault with useCollectionData types, it can be non-array.
				( filteredCounts as CollectionData )?.stock_status_counts?.find(
					( item: StockStatusCount ) => item.status === key
				)?.count;

			return {
				value: key,
				label: (
					<Label
						name={ value }
						count={ showCounts && count ? Number( count ) : null }
					/>
				),
			};
		} );
	}, [ stockStatusOptions, filteredCounts, showCounts ] );

	return (
		<>
			{
				<div { ...blockProps }>
					<Inspector { ...props } />
					<InnerBlocks
						template={ template }
						allowedBlocks={ [ 'core/heading' ] }
					/>
					<Disabled>
						<div
							className={ classnames(
								'wc-block-stock-filter',
								`style-${ displayStyle }`,
								{
									'is-loading': false,
								}
							) }
						>
							{ displayStyle === 'dropdown' ? (
								<>
									<PreviewDropdown
										placeholder={
											props.attributes.selectType ===
											'single'
												? __(
														'Select stock status',
														'woocommerce'
												  )
												: __(
														'Select stock statuses',
														'woocommerce'
												  )
										}
									/>
								</>
							) : (
								<CheckboxList
									className={ 'wc-block-stock-filter-list' }
									options={ listOptions }
									checked={ [] }
									onChange={ () => {
										// noop
									} }
									isLoading={ false }
									isDisabled={ true }
								/>
							) }
						</div>
					</Disabled>
				</div>
			}
		</>
	);
};

export default Edit;
