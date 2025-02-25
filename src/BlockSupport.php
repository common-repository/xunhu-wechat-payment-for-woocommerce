<?php
namespace Xunhu\Wechatpay;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

final class BlockSupport extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = XH_Wechat_Payment_ID;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'failed_payment_notice' ], 8, 2 );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();

		return $payment_gateways[ XH_Wechat_Payment_ID ]->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_asset_path = XH_Wechat_Payment_DIR . '/frontend/dist/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require $script_asset_path : [ 'dependencies' => [], 'version' => XH_Wechat_Payment_VERSION ];
		$script_url        = XH_Wechat_Payment_URL . '/frontend/dist/blocks.js';

		wp_register_script(
			'xh-wc-wechatpay-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'xh-wc-wechatpay-blocks', 'xh-wechat-payment-wc', );
		}

		return [ 'xh-wc-wechatpay-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();
		$gateway                = $payment_gateways[ XH_Wechat_Payment_ID ];

		return [
			'title'             => $gateway->title,
			'description'       => $gateway->description,
			'supports'          => array_filter( $gateway->supports, [ $gateway, 'supports' ] ),
			'allow_saved_cards' => false,
			'logo_urls'         => [ $gateway->icon ],
		];
	}

	/**
	 * Add failed payment notice to the payment details.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	public function failed_payment_notice( PaymentContext $context, PaymentResult &$result ) {
		if ( XH_Wechat_Payment_ID === $context->payment_method ) {
			add_action(
				'wc_gateway_wechatpay_process_payment_error',
				function ( $failed_notice ) use ( &$result )
				{
					$payment_details                   = $result->payment_details;
					$payment_details[ 'errorMessage' ] = wp_strip_all_tags( $failed_notice );
					$result->set_payment_details( $payment_details );
				}
			);
		}
	}
}