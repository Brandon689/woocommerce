<?php
namespace Automattic\WooCommerce\Blocks\BlockTypes;

use Automattic\WooCommerce\Blocks\InteractivityComponents\Dropdown;
use Automattic\WooCommerce\Blocks\Utils\StyleAttributesUtils;

/**
 * CollectionStockFilter class.
 */
final class CollectionStockFilter extends AbstractBlock {

	/**
	 * Block name.
	 *
	 * @var string
	 */
	protected $block_name = 'collection-stock-filter';

	const STOCK_STATUS_QUERY_VAR = 'filter_stock_status';

	/**
	 * Initialize this block type.
	 *
	 * - Hook into WP lifecycle.
	 * - Register the block with WordPress.
	 */
	protected function initialize() {
		parent::initialize();

		add_filter( 'collection_filter_query_param_keys', array( $this, 'get_filter_query_param_keys' ), 10, 2 );
		add_filter( 'collection_active_filters_data', array( $this, 'register_active_filters_data' ), 10, 2 );
	}

	/**
	 * Register the query param keys.
	 *
	 * @param array $filter_param_keys The active filters data.
	 * @param array $url_param_keys    The query param parsed from the URL.
	 *
	 * @return array Active filters param keys.
	 */
	public function get_filter_query_param_keys( $filter_param_keys, $url_param_keys ) {
		$stock_param_keys = array_filter(
			$url_param_keys,
			function( $param ) {
				return self::STOCK_STATUS_QUERY_VAR === $param;
			}
		);

		return array_merge(
			$filter_param_keys,
			$stock_param_keys
		);
	}

	/**
	 * Register the active filters data.
	 *
	 * @param array $data   The active filters data.
	 * @param array $params The query param parsed from the URL.
	 * @return array Active filters data.
	 */
	public function register_active_filters_data( $data, $params ) {
		$stock_status_options = wc_get_product_stock_status_options();

		if ( empty( $params[ self::STOCK_STATUS_QUERY_VAR ] ) ) {
			return $data;
		}

		$active_stock_statuses = array_filter(
			explode( ',', $params[ self::STOCK_STATUS_QUERY_VAR ] )
		);

		if ( empty( $active_stock_statuses ) ) {
			return $data;
		}

		$active_stock_statuses = array_map(
			function( $status ) use ( $stock_status_options ) {
				return array(
					'title'      => $stock_status_options[ $status ],
					'attributes' => array(
						'data-wc-on--click' => 'woocommerce/collection-stock-filter::actions.removeFilter',
						'data-wc-context'   => 'woocommerce/collection-stock-filter::' . wp_json_encode( array( 'value' => $status ) ),
					),
				);
			},
			$active_stock_statuses
		);

		$data['stock'] = array(
			'type'  => __( 'Stock Status', 'woocommerce' ),
			'items' => $active_stock_statuses,
		);

		return $data;
	}

	/**
	 * Extra data passed through from server to client for block.
	 *
	 * @param array $stock_statuses  Any stock statuses that currently are available from the block.
	 *                               Note, this will be empty in the editor context when the block is
	 *                               not in the post content on editor load.
	 */
	protected function enqueue_data( array $stock_statuses = [] ) {
		parent::enqueue_data( $stock_statuses );
		$this->asset_data_registry->add( 'stockStatusOptions', wc_get_product_stock_status_options(), true );
		$this->asset_data_registry->add( 'hideOutOfStockItems', 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ), true );
	}

	/**
	 * Include and render the block.
	 *
	 * @param array    $attributes Block attributes. Default empty array.
	 * @param string   $content    Block content. Default empty string.
	 * @param WP_Block $block      Block instance.
	 * @return string Rendered block type output.
	 */
	protected function render( $attributes, $content, $block ) {
		// don't render if its admin, or ajax in progress.
		if ( is_admin() || wp_doing_ajax() ) {
			return '';
		}

		$stock_status_counts = $block->context['collectionData']['stock_status_counts'] ?? [];
		$wrapper_attributes  = get_block_wrapper_attributes();

		return sprintf(
			'<div %1$s>
				%2$s
				<div class="wc-block-stock-filter__controls">%3$s</div>
				<div class="wc-block-stock-filter__actions"></div>
			</div>',
			$wrapper_attributes,
			$content,
			$this->get_stock_filter_html( $stock_status_counts, $attributes ),
		);
	}

	/**
	 * Stock filter HTML
	 *
	 * @param array $stock_counts       An array of stock counts.
	 * @param array $attributes Block attributes. Default empty array.
	 * @return string Rendered block type output.
	 */
	private function get_stock_filter_html( $stock_counts, $attributes ) {
		$display_style  = $attributes['displayStyle'] ?? 'list';
		$show_counts    = $attributes['showCounts'] ?? false;
		$select_type    = $attributes['selectType'] ?? 'single';
		$stock_statuses = wc_get_product_stock_status_options();

		$placeholder_text = 'single' === $select_type ? __( 'Select stock status', 'woocommerce' ) : __( 'Select stock statuses', 'woocommerce' );

		// check the url params to select initial item on page load.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required here.
		$query                   = isset( $_GET[ self::STOCK_STATUS_QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::STOCK_STATUS_QUERY_VAR ] ) ) : '';
		$selected_stock_statuses = explode( ',', $query );

		$list_items = array_map(
			function( $item ) use ( $stock_statuses, $show_counts ) {
				$label = $show_counts ? $stock_statuses[ $item['status'] ] . ' (' . $item['count'] . ')' : $stock_statuses[ $item['status'] ];
				return array(
					'label' => $label,
					'value' => $item['status'],
				);
			},
			$stock_counts
		);

		$selected_items = array_values(
			array_filter(
				$list_items,
				function( $item ) use ( $selected_stock_statuses ) {
						return in_array( $item['value'], $selected_stock_statuses, true );
				}
			)
		);

		$data_directive = wp_json_encode( array( 'namespace' => 'woocommerce/collection-stock-filter' ) );

		ob_start();
		?>

		<div data-wc-interactive='<?php echo esc_attr( $data_directive ); ?>'>
			<?php if ( 'list' === $display_style ) : ?>
				<div class="wc-block-stock-filter style-list">
					<ul class="wc-block-checkbox-list wc-block-components-checkbox-list wc-block-stock-filter-list">
						<?php foreach ( $stock_counts as $stock_count ) { ?>
							<li>
								<div class="wc-block-components-checkbox wc-block-checkbox-list__checkbox">
									<label for="<?php echo esc_attr( $stock_count['status'] ); ?>">
										<input
											id="<?php echo esc_attr( $stock_count['status'] ); ?>"
											class="wc-block-components-checkbox__input"
											type="checkbox"
											aria-invalid="false"
											data-wc-on--change="actions.updateProducts"
											value="<?php echo esc_attr( $stock_count['status'] ); ?>"
											<?php checked( strpos( $query, $stock_count['status'] ) !== false, 1 ); ?>
										>
											<svg class="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 20">
												<path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path>
											</svg>
											<span class="wc-block-components-checkbox__label">
												<?php echo esc_html( $stock_statuses[ $stock_count['status'] ] ); ?>

												<?php if ( $show_counts ) : ?>
													<?php
													// translators: %s: number of products.
													$screen_reader_text = sprintf( _n( '%s product', '%s products', $stock_count['count'], 'woocommerce' ), number_format_i18n( $stock_count['count'] ) );
													?>
													<span>
														<span aria-hidden="true">
															<?php $show_counts ? print( esc_html( '(' . $stock_count['count'] . ')' ) ) : null; ?>
														</span>
														<span class="screen-reader-text">
															<?php esc_html( $screen_reader_text ); ?>
														</span>
													</span>
												<?php endif; ?>
											</span>
									</label>
								</div>
							</li>
						<?php } ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( 'dropdown' === $display_style ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dropdown::render() escapes output.
				echo Dropdown::render(
					array(
						'items'          => $list_items,
						'action'         => 'woocommerce/collection-stock-filter::actions.navigate',
						'selected_items' => $selected_items,
						'select_type'    => $select_type,
						'placeholder'    => $placeholder_text,
					)
				);
				?>
			<?php endif; ?>
		</div>

		<?php
		return ob_get_clean();
	}
}
