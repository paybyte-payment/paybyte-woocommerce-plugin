=== Plugin Name ===
Contributors: izzycoder18
Tags: payments,bitcoin-payments,cryptocurrency,paybyte,payment
Requires at least: 4.6
Tested up to: 5.0.3
Stable tag: trunk
Requires PHP: 5.2.4
License: MIT
License URI: https://github.com/paybyte-payment/paybyte-woocommerce-plugin/blob/master/LICENSE

Accept Crypto payments in your WordPress WooCommerce website using PayByte.

== Description ==

PayByte is a crypto payment processor that acts in a non-custodial fashion when merchants accept payments from their customers. In this scenario, funds go straight from a customer's wallet to the merchant's wallet, without any middlemen involved in the transaction. It's almost trustless!
  
PayByte's key competitive advantage is that it charges flat and fixed fees based on transaction volume, while all other processors charge percentage-based fees.
  
Paybyte doesn't need your Private Keys, by leveraging the power of the Blockchain, PayByte uses your XPUB (Extended Public Key) to generate addresses on your behalf that can be used just to watch incoming transactions. As a result, funds go directly to your wallet and in doing so eliminate middleman from the payment process.
    
Key features:
  
- Non-Custodial payments using HD/BIP-32 addresses generation.
- Segwit address schema fully supported.
- KYC/AML checks for customer and merchants security.
- Supported coins: Bitcoin, Bictoin Cash, Dash, Litecoin, Groestlcoin, BitCore, DigiByte, Bitcoin Gold and more!
  
In order to use PayByte For WooCommerce, you will need to create a Merchant account on PayByte and get an API KEY.
  
Just go to [https://paybyte.io](https://paybyte.io) to register as a merchant and get your API KEY.

== Installation ==

- Copy the entire folder content into your Wordpress installation under wp-content/plugins
- Go to your Wordpress administration panel under: Plugins -> Installed plugins -> PayByte for WooCommerce. 
- Activate PayByte plugin.
- In Wordpress Administration panel, go to WooCommerce -> Settings -> Payments. 
- Enable PayByte option and click on the "Set up" button.
- Configure PayByte plugin as required.

== Frequently Asked Questions ==

Please refer to our website FAQ on [https://paybyte.io/faq](https://paybyte.io/faq)

== Changelog ==

= 1.0.2 =
* Minor improvements.

= 1.0.0 =
* First plugin submission.