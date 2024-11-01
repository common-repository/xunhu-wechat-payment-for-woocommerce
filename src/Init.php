<?php
namespace Xunhu\Wechatpay;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
require_once XH_Wechat_Payment_DIR.'src/BlockSupport.php';

class Init {
    public function __construct() {
		add_action( 'woocommerce_blocks_loaded', [ $this, 'add_block_support' ] );
		add_action( 'before_woocommerce_init', [ $this, 'add_custom_table_support' ] );

		add_filter( 'option_trp_advanced_settings', [ $this, 'ignore_translate_strings' ] );

		add_filter( 'plugin_action_links_' . XH_Wechat_Payment_FILE, [ $this, 'add_settings_link' ] );
	}
	
	/**
	 * Registers WooCommerce Blocks integration.
	 */
	function add_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				static function ( PaymentMethodRegistry $payment_method_registry )
				{
					$payment_method_registry->register( new BlockSupport() );
				}
			);
		}
	}

    function add_custom_table_support() {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', XH_Wechat_Payment_FILE_PATH );
		}
	}
	
	/**
	 * 避免 TranslatePress 插件翻译签名字符串
	 */
	function ignore_translate_strings( $options ) {
		$options[ 'exclude_gettext_strings' ][ 'string' ][] = 'Pay for order %1$s at %2$s';
		$options[ 'exclude_gettext_strings' ][ 'domain' ][] = 'xh-wechat-payment-wc';
		return $options;
	}

	/**
	 * 插件插件设置链接
	 */
	function add_settings_link( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=xh-wechat-payment-wc' );
		$url = '<a href="' . esc_url( $url ) . '">' . __( 'Settings', XH_Wechat_Payment ) . '</a>';
		array_unshift( $links, $url );

		return $links;
	}
}