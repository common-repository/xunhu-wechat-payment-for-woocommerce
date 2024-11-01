<?php
/*
 * Plugin Name: Xunhu Wechat Payment For WooCommerce
 * Plugin URI: http://www.wpweixin.net
 * Description: 微信扫码支付、微信H5支付
 * Author: 重庆迅虎网络有限公司
 * Version: 1.1.0
 * Author URI:  http://www.wpweixin.net
 * Text Domain: Wechat payment for woocommerce
 * Requires PHP: 7.2
 * Requires at least: 4.7
 * Tested up to: 6.2
 * WC requires at least: 3.6
 * WC tested up to: 8.5
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( PHP_VERSION_ID < 70200 ) {
	// 显示警告信息
	if ( is_admin() ) {
		add_action( 'admin_notices', function ()
		{
			printf( '<div class="error"><p>' . __( 'Xunhu Wechat Payment For WooCommerce 需要 PHP %1$s 以上版本才能运行，您当前的 PHP 版本为 %2$s， 请升级到 PHP 到 %1$s 或更新的版本， 否则插件没有任何作用。',
					'wprs' ) . '</p></div>',
				'7.2.0', PHP_VERSION );
		} );
	}
	return;
}
if (! defined ( 'XH_Wechat_Payment' )) {define ( 'XH_Wechat_Payment', 'XH_Wechat_Payment' );} else {return;}
define('XH_Wechat_Payment_FILE', plugin_basename( __FILE__ ));
define('XH_Wechat_Payment_DIR', plugin_dir_path( __FILE__ ));
define('XH_Wechat_Payment_URL', plugin_dir_url( __FILE__ ));
const XH_Wechat_Payment_FILE_PATH = __FILE__;
const XH_Wechat_Payment_VERSION   = '1.1.0';
const XH_Wechat_Payment_ID = 'xh-wechat-payment-wc';
const XH_Wechat_Payment_ASSETS_URL  = XH_Wechat_Payment_URL . 'frontend/';

add_action( 'plugins_loaded', function ()
{
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
    require_once XH_Wechat_Payment_DIR.'/src/PaymentGateway.php';
    require_once XH_Wechat_Payment_DIR.'/src/Init.php';
    load_plugin_textdomain( XH_Wechat_Payment, false,dirname( plugin_basename( __FILE__ ) ) . '/lang');
	add_action( 'woocommerce_receipt_xh-wechat-payment-wc', [ new \Xunhu\Wechatpay\PaymentGateway(), 'receipt_page' ] );
	add_filter( 'woocommerce_payment_gateways', function ( $methods )
	{
		$methods[] = '\\Xunhu\\Wechatpay\\PaymentGateway';
		return $methods;
	} );

	new \Xunhu\Wechatpay\Init();
}, 0 );