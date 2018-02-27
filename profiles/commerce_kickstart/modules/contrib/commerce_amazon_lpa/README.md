Amazon Pay and Login with Amazon
-------------------------

This module integrates Amazon Pay and Login with Amazon into Drupal and [Drupal Commerce][drupalcommerce].

Amazon Pay provides Amazon buyers a secure, trusted, and convenient way to log in and pay for their purchases on your site. Buyers use Amazon Pay to share their profile information (name and email address) and access the shipping and payment information stored in their Amazon account to complete their purchase. Learn more at

* [Amazon Pay US][amazonpay_us]
* [Amazon Pay UK][amazonpay_uk]
* [Amazon Pay DE][amazonpay_de]

[amazonpay_us]: https://payments.amazon.com
[amazonpay_uk]: https://payments.amazon.co.uk
[amazonpay_de]: https://payments.amazon.de
[drupalcommerce]: https://www.drupal.org/project/commerce

## Requirements

You must have [Drupal Commerce][drupalcommerce] and the Cart, Customer, and Payment submodules enabled.

The shop owner must have an Amazon merchant account. Sign up now:
* US : https://pay.amazon.com/us/merchant?ld=SPEXUSAPA-drupal%20commerce-CP-DP-2017-Q1
* UK : https://pay.amazon.com/uk/merchant?ld=SPEXUKAPA-drupal%20commerce-CP-DP-2017-Q1
* DE : https://pay.amazon.com/de/merchant?ld=SPEXDEAPA-drupal%20commerce-CP-DP-2017-Q1

This module utilizes the [Amazon Pay PHP SDK][php-sdk] to communicate with Amazon. You must have the [Libraries][libraries] module installed in order to load the SDK properly.

[php-sdk]: https://github.com/amzn/amazon-pay-sdk-php
[libraries]: https://www.drupal.org/project/libraries

## Features

The module's integration provides the following features:

* When using the *Amazon Pay and Login with Amazon* mode, users logging in with their Amazon accounts will have an account created in Drupal.
* Ability to provide the normal checkout experience or only provide Amazon based checkout.
* Multilingual support
* Support for United States, United Kingdom, and Germany regions.

The module's documentation can be found on Drupal.org at https://www.drupal.org/docs/7/modules/commerce-amazon-pay

## Installation

1. Install as you would normally install a contributed drupal module and its dependencies. See: https://drupal.org/documentation/install/modules-themes/modules-7 for further information.
2. Download the latest Amazon Pay PHP SDK from [GitHub][php-sdk] and place it in *sites/all/libraries*
3. Visit *admin/commerce/config/amazon-lpa* and enter your Merchant ID, MWS Access Key, MWS Secret key, and LWA Client ID.
4. Save the configuration, your API credentials will be validated.
5. Specify your domain as the *Allowed JavaScript Origins*
5. Add the following URLs as *Allowed Return URLs*
* https://example.com/checkout/amazon
* https://example.com/user/login/amazon?amzn=LwA
6. Add https://example.com/commerce-amazon-lpa/ipn as your *Merchant URL* in the *Integration Settings* form. 

## Frequently Asked Questions

### Only allow users to login through Amazon

You have the ability to disable Drupal's user registration and support registration and login solely through Login with Amazon.

1. Visit *admin/commerce/config/amazon-lpa* 
1. Ensure the **Operation mode** is set to *Amazon Pay and Login with Amazon*
1. Visit *admin/config/people/accounts*
1. Change **Who can register accounts?** to *Administrators only*

### Using just Amazon Pay

You can use the module to only support Amazon Pay, without Login with Amazon.

1. Visit *admin/commerce/config/amazon-lpa* 
1. Ensure the **Operation mode** is set to *Amazon Pay only*

When entering the Amazon checkout, user's will be prompt to log in with their Amazon account before beginning. However, no Drupal account will be created.

## Maintainers

Current maintainer:
* Matt Glaman ([mglaman])

Development sponsored by **[Commerce Guys][commerceguys]**:

Commerce Guys are the creators of and experts in Drupal Commerce, the eCommerce solution that capitalizes on the virtues and power of Drupal, the premier open-source content management system. We focus our knowledge and expertise on providing online merchants with the powerful, responsive, innovative eCommerce solutions they need to thrive.

[mglaman]: https://www.drupal.org/u/mglaman
[commerceguys]: https://commerceguys.com/
