/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import {Content, ariaLabel, Label} from './base';

const settings = getSetting( 'xh-wechat-payment-wc_data', {} );
const label = ariaLabel({ title: settings.title });

/**
 * Paystack payment method config object.
 */
const Xunhu_Wechat_Gateway = {
  name: 'xh-wechat-payment-wc',
  label: <Label logoUrls={ settings.logo_urls } title={ label } />,
  content: <Content description={ settings.description } />,
  edit: <Content description={ settings.description } />,
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
};

registerPaymentMethod( Xunhu_Wechat_Gateway );