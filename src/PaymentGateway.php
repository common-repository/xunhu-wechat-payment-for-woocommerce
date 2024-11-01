<?php
namespace Xunhu\Wechatpay;

if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Gateway class
 */
class PaymentGateway extends \WC_Payment_Gateway
{
    /** @var \WC_Logger Logger 实例 */
	public $log = false;
	/**
     * @var bool 日志是否启用
     */
    public $is_debug_mod = false;
    /**
	 * 网关支持的功能
	 *
	 * @var array
	 */
	public $supports = [ 'products'];

	public function __construct() {
	    // 支付方法的全局 ID
		$this->id = XH_Wechat_Payment_ID;
		// 支付网关页面显示的支付网关标题
		$this->method_title = __('Wechat Payment',XH_Wechat_Payment);
		// 支付网关设置页面显示的支付网关标题
		$this->method_description = __('Helps to add Wechat payment gateway that supports the features including QR code payment, OA native payment, exchange rate.',XH_Wechat_Payment);
		// 被 init_settings() 加载的基础设置
		$this->init_form_fields();
		$this->init_settings();

		// 前端显示的支付网关名称
		$this->title = $this->get_option( 'title' );
		// 支付网关标题
		$this->icon = apply_filters( 'hpj_wechat_icon', XH_Wechat_Payment_URL . '/images/weixin.png' );
        $this->description        = $this->get_option ( 'description' );
        $this->instructions       = $this->get_option('instructions');
		$this->has_fields = false;
		$this->multi_currency_enabled = in_array( 'woocommerce-multilingual/wpml-woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) && get_option( 'icl_enable_multi_currency' ) === 'yes';
		// 保存设置
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		}
		// 添加 URL
		add_action( 'woocommerce_api_hpj-wc-wechatpay-query', [ $this, 'query_order' ] );
		add_action( 'woocommerce_api_hpj-wc-wechatpay-notify', [ $this, 'listen_notify' ] );
	}
	
	/**
	 * 网关设置
	 */
	public function init_form_fields() {
	    $this->form_fields = array (
				'enabled' => array (
						'title'       => __('Enable/Disable',XH_Wechat_Payment),
						'type'        => 'checkbox',
						'label'       => __('Enable/Disable the wechat payment',XH_Wechat_Payment),
						'default'     => 'no',
						'section'     => 'default'
				),
				'title' => array (
						'title'       => __('Payment gateway title',XH_Wechat_Payment),
						'type'        => 'text',
						'default'     => __('Wechat Payment',XH_Wechat_Payment),
						'desc_tip'    => true,
						'css'         => 'width:400px',
						'section'     => 'default'
				),
				'description' => array (
						'title'       => __('Payment gateway description',XH_Wechat_Payment),
						'type'        => 'textarea',
						'default'     => __('QR code payment or OA native payment, credit card',XH_Wechat_Payment),
						'desc_tip'    => true,
						'css'         => 'width:400px',
						'section'     => 'default'
				),
				'instructions' => array(
    					'title'       => __( 'Instructions', XH_Wechat_Payment ),
    					'type'        => 'textarea',
    					'css'         => 'width:400px',
    					'description' => __( 'Instructions that will be added to the thank you page.', XH_Wechat_Payment ),
    					'default'     => '',
    					'section'     => 'default'
				),
				'appid' => array(
    					'title'       => __( 'APP ID', XH_Wechat_Payment ),
    					'type'        => 'text',
    					'css'         => 'width:400px',
    					'default'=>'',
    					'section'     => 'default',
                        'description'=>'帮助文档：https://www.xunhupay.com/114.html'
				),
				'appsecret' => array(
    					'title'       => __( 'APP Secret', XH_Wechat_Payment ),
    					'type'        => 'text',
    					'css'         => 'width:400px',
    					'default'=>'',
    					'section'     => 'default'
				),
				'tranasction_url' => array(
    					'title'       => __( 'Transaction_url', XH_Wechat_Payment ),
    					'type'        => 'text',
    					'css'         => 'width:400px',
    					 'default'=>'https://api.xunhupay.com',
    					'section'     => 'default',
                        'description'=>'默认：https://api.xunhupay.com，不要加“/payment/do.html”'
				),
				'exchange_rate' => array (
    					'title'       => __( 'Exchange Rate',XH_Wechat_Payment),
    					'type'        => 'text',
    					'default'     => '1',
    					'description' => __(  'Set the exchange rate to RMB. When it is RMB, the default is 1',XH_Wechat_Payment),
    					'css'         => 'width:400px;',
    					'section'     => 'default'
				)
		);
	}

	/**
	 * 管理选项
	 */
	public function admin_options() { ?>

        <h3>
			<?php echo ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', XH_Wechat_Payment ); ?>
			<?php wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>
        </h3>

		<?php echo ( ! empty( $this->method_description ) ) ? wpautop( $this->method_description ) : ''; ?>

        <table class="form-table">
			<?php $this->generate_settings_html(); ?>
        </table>

		<?php
	}

	/**
	 * WooCommerce 支付处理 function/method.
	 *
	 * @inheritdoc
	 *
	 * @param int $order_id
	 *
	 * @return array|string[]
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
	    if(!$order||(method_exists($order, 'is_paid')?$order->is_paid():in_array($order->get_status(),  array( 'processing', 'completed' )))){
        	return array(
                 'result'   => 'success',
                 'redirect' => $this->get_return_url($order)
             );
        }
        return array(
            'result'   => 'success',
            'redirect' =>$order->get_checkout_payment_url(true)
        );
	}

	function receipt_page( int $order_id ) {
	    $order = wc_get_order( $order_id );
        $turl = $this->get_option('tranasction_url');
        $expire_rate      = floatval($this->get_option('exchange_rate',1));
        if($expire_rate<=0){
            $expire_rate=1;
        }
        $total_amount     = round($order->get_total()*$expire_rate,2);
        $trade_order_id = date("YmdH")."_".$order_id;
        $data=array(
              'version'   => '1.1',//api version
              'plugins'   => 'woo-wechat',
              'appid'     => $this->get_option('appid'),
              'trade_order_id'=> $trade_order_id,
              'total_fee' => $total_amount,
              'title'     => $this->get_order_title($order),
              'time'      => time(),
              'notify_url'=> WC()->api_request_url( 'hpj-wc-wechatpay-notify' ),
              'return_url'=> $order->get_checkout_order_received_url(),
              'callback_url'=>wc_get_checkout_url(),
              'nonce_str' => str_shuffle(time())
        );
        if($this->isWebApp()){
            $data['type'] = "WAP";
            $data['wap_url'] = home_url();
            $data['wap_name'] = home_url();
        }
        $hashkey          = $this->get_option('appsecret');
        $data['hash']     = $this->generate_xh_hash($data,$hashkey);
        $url              = $turl.'/payment/do.html';
        $response     = $this->http_post($url, json_encode($data));
        $result       = $response?json_decode($response,true):null;
        $error_msg = '';
        if(!$result){
            $error_msg = $response;
        }
        if($result['errcode']!=0){
            $error_msg = $result['errmsg'];
        }
        if($this->isWebApp() && $error_msg == ''){
            wp_redirect($result['url']);
            exit;
        }
        $url_qrcode = isset($result['url_qrcode'])?$result['url_qrcode']:'';
        ?>
        <style type="text/css">
            .pay-weixin-design{ display: block;background: #fff;/*padding:100px;*/overflow: hidden;}
              .page-wrap {padding: 50px 0;min-height: auto !important;  }
              .pay-weixin-design #WxQRCode{width:196px;height:auto}
              .pay-weixin-design .p-w-center{ display: block;overflow: hidden;margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;}
              .pay-weixin-design .p-w-center h3{    font-family: Arial,微软雅黑;margin: 0 auto 10px;display: block;overflow: hidden;}
              .pay-weixin-design .p-w-center h3 font{ display: block;font-size: 14px;font-weight: bold;    float: left;margin: 10px 10px 0 0;}
              .pay-weixin-design .p-w-center h3 strong{position: relative;text-align: center;line-height: 40px;border: 2px solid #3879d1;display: block;font-weight: normal;width: 130px;height: 44px; float: left;}
              .pay-weixin-design .p-w-center h3 strong #img1{margin-top: 10px;display: inline-block;width: 22px;vertical-align: top;}
              .pay-weixin-design .p-w-center h3 strong span{    display: inline-block;font-size: 14px;vertical-align: top;}
              .pay-weixin-design .p-w-center h3 strong #img2{    position: absolute;right: 0;bottom: 0;}
              .pay-weixin-design .p-w-center h4{font-family: Arial,微软雅黑;      margin: 0; font-size: 14px;color: #666;}
              .pay-weixin-design .p-w-left{ display: block;overflow: hidden;float: left;}
              .pay-weixin-design .p-w-left p{ display: block;width:196px;background:#00c800;color: #fff;text-align: center;line-height:2.4em; font-size: 12px; }
              .pay-weixin-design .p-w-left img{ margin-bottom: 10px;}
              .pay-weixin-design .p-w-right{ margin-left: 50px; display: block;float: left;}
            </style>
            		
            <div class="pay-weixin-design">
            
                 <div class="p-w-center">
                    <h3>
            		   <font>支付方式已选择微信支付</font>
            		   <strong>
            		      <img id="img1" src="<?php print XH_Wechat_Payment_URL?>/images/weixin.png">
            			  <span>微信支付</span>
            			  <img id="img2" src="<?php print XH_Wechat_Payment_URL?>/images/ep_new_sprites1.png">
            		   </strong>
            		</h3>
            	    <h4>通过微信首页右上角扫一扫，或者在“发现-扫一扫”扫描二维码支付。本页面将在支付完成后自动刷新。</h4>
            	    <span style="color:red;"><?php print $error_msg?></span>
            	 </div>
            		
                 <div class="p-w-left">		  
            		<div style="width: 200px;height: 200px;"><img src="<?php echo $url_qrcode;?>"/></div>
            		<p>使用微信扫描二维码进行支付</p>
            		
                 </div>
            
            	 <div class="p-w-right">
            	    <img src="<?php print XH_Wechat_Payment_URL?>/images/ep_sys_wx_tip.jpg">
            	 </div>
            
            </div>
        <script type="text/javascript">
        		(function () {
        		    function queryOrderStatus() {
        		    	 var $=jQuery;
        				 if(!$){return;}
        				    
        		        $.ajax({
        		            type: "GET",
        		            url: '<?php print WC()->api_request_url( 'hpj-wc-wechatpay-query' );?>',
        		            data: {
        		                order_id: '<?php print $trade_order_id;?>'
        		            },
        		            timeout:6000,
        		            cache:false,
        		            dataType:'json',
        		            async:true,
        		            success:function(data){
        		                if (data && data.status === "paid") {
        		                    location.href = data.message;
        		                    return;
        		                }
                                setTimeout(queryOrderStatus, 2000);
        		            },
        		            error:function(){
        		            	setTimeout(queryOrderStatus, 2000);
        		            }
        		        });
        		    }
                    setTimeout(function(){
                        queryOrderStatus();
                    },3000);
        		})();
        </script>
        <?php 
	}

	/**
	 * 监听微信扫码支付返回
	 */
	public function query_order() {
        $order_id = $_GET[ 'order_id' ];
        if ( $order_id ) {
            $request=array(
                'appid'     => $this->get_option('appid'), //必须的，APPID
                'out_trade_order'=> $order_id, //网站订单号(out_trade_order，open_order_id 二选一)
                'time'      => time(),//必须的，当前时间戳，根据此字段判断订单请求是否已超时，防止第三方攻击服务器
                'nonce_str' => str_shuffle(time())//必须的，随机字符串，作用：1.避免服务器缓存，2.防止安全密钥被猜测出来
            );
            $request['hash'] =  $this->generate_xh_hash($request,$this->get_option('appsecret'));
            $url = $this->get_option('tranasction_url').'/payment/query.html';
            $response     = $this->http_post($url, http_build_query($request));
            $result       = $response?json_decode($response,true):null;
            if($result['errcode']!=0){
                echo $result['errmsg'];
                exit;
            }
            if($result['data']['status']=='OD'){
                $order = wc_get_order ( explode("_",$order_id)[1] );
                if(!(method_exists($order, 'is_paid')?$order->is_paid():in_array($order->get_status(),  array( 'processing', 'completed' )))){
                    $order->payment_complete ($result['data']['transacton_id']);
                    WC()->cart->empty_cart();
                    // 添加订单备注
                    $order->add_order_note(sprintf( __( 'Wechatpay payment complete (Transaction ID: %s)', XH_Wechat_Payment ), $result['data'][ 'transaction_id' ] ));
                }
            	echo json_encode ( array (
            	    'status' =>'paid',
            	    'message' => $order->get_checkout_order_received_url()
            	) );
            }else{
            	echo json_encode( array (
            	    'status' =>'npaid',
            	    'message' => ''
            	));
            }
            exit;
        } else {
            wp_send_json_error();
        }
	}

	/**
	 * 处理支付接口异步返回的信息
	 */
	public function listen_notify() {
        $data = $_POST;
        if(!isset($data['hash'])
            ||!isset($data['trade_order_id'])){
                exit('faild!');
        }
        if(isset($data['plugins'])&&$data['plugins']!='woo-wechat'){
            return 'error';
        }
        $hash = $this->generate_xh_hash($data,$this->get_option('appsecret'));
        if($data['hash']!=$hash){
            return 'hash error';
        }
        $order_id = explode("_",$data['trade_order_id'])[1];
        $order = wc_get_order($order_id);
        if(!$order){
            throw new Exception('Unknow Order (id:'.$order_id.')');
        }
        if(!(method_exists($order, 'is_paid')?$order->is_paid():in_array($order->get_status(),  array( 'processing', 'completed' )))&&$data['status']=='OD'){
            $order->payment_complete($data['transacton_id']);
            WC()->cart->empty_cart();
            // 添加订单备注
            $order->add_order_note(sprintf( __( 'Wechatpay payment complete (Transaction ID: %s)', XH_Wechat_Payment ), $result['data'][ 'transaction_id' ] ));
        }
        print 'success';
        exit;
	}
    
    public function  generate_xh_hash(array $datas,$hashkey){
        ksort($datas);
        reset($datas);
        $arg  = '';
        foreach ($datas as $key=>$val){
            if($key=='hash'||is_null($val)||$val===''){continue;}
            if($arg){$arg.='&';}
            $arg.="$key=$val";
        }
        return md5($arg.$hashkey);
    }

    private function http_post($url,$data){
        if(!function_exists('curl_init')){
            throw new Exception('php未安装curl组件',500);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_REFERER,get_option('siteurl'));
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error=curl_error($ch);
        curl_close($ch);
    
        return $response;
    }
    
    public function isWebApp(){
        $_SERVER['ALL_HTTP'] = isset($_SERVER['ALL_HTTP']) ? $_SERVER['ALL_HTTP'] : ''; 
        $mobile_browser = '0'; 
        if(preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) 
            $mobile_browser++; 
        if((isset($_SERVER['HTTP_ACCEPT'])) and (strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') !== false)) 
            $mobile_browser++; 
        if(isset($_SERVER['HTTP_X_WAP_PROFILE'])) 
            $mobile_browser++; 
        if(isset($_SERVER['HTTP_PROFILE'])) 
            $mobile_browser++; 
        $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'],0,4)); 
        $mobile_agents = array( 
            'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac', 
            'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno', 
            'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-', 
            'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-', 
            'newt','noki','oper','palm','pana','pant','phil','play','port','prox', 
            'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar', 
            'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-', 
            'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp', 
            'wapr','webc','winw','winw','xda','xda-'
            ); 
        if(in_array($mobile_ua, $mobile_agents)) 
            $mobile_browser++; 
        if(strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false) 
            $mobile_browser++; 
        // Pre-final check to reset everything if the user is on Windows 
        if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false) 
            $mobile_browser=0; 
        // But WP7 is also Windows, with a slightly different characteristic 
        if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows phone') !== false) 
            $mobile_browser++; 
        if($mobile_browser>0) 
            return true; 
        else
            return false; 
    }

	public function get_order_title($order, $limit = 98) {
	    $order_id = method_exists($order, 'get_id')? $order->get_id():$order->id;
		$title ="#{$order_id}";

		$order_items = $order->get_items();
		if($order_items){
		    $qty = count($order_items);
		    foreach ($order_items as $item_id =>$item){
		        $title.="|{$item['name']}";
		        break;
		    }
		    if($qty>1){
		        $title.='...';
		    }
		}

		$title = mb_strimwidth($title, 0, $limit,'utf-8');
		return apply_filters('xh-payment-get-order-title', $title,$order);
	}
	/**
	 * Logger 辅助功能
	 *
	 * @param $message
	 */
	public function log( $message ) {
		if ( $this->is_debug_mod ) {
			if ( ! ( $this->log ) ) {
				$this->log = new \WC_Logger();
			}
			$this->log->add( 'xh-wechat-payment-wc', var_export( $message, true ) );
		}
	}
}