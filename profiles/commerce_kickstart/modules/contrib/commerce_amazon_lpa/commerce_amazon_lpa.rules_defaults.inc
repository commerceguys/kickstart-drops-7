<?php

/**
 * @file
 * Default rule configurations for Commerce Amazon LPA.
 */

/**
 * Implements hook_default_rules_configuration().
 */
function commerce_amazon_lpa_default_rules_configuration() {
  $rules = array();

  $rules['commerce_amazon_lpa_update_order_status'] = entity_import('rules_config', '{ "commerce_amazon_lpa_update_order_status" : {
    "LABEL" : "Update the order with the Amazon order reference status",
    "PLUGIN" : "reaction rule",
    "WEIGHT" : "10",
    "OWNER" : "rules",
    "TAGS" : [ "Commerce Amazon LPA" ],
    "REQUIRES" : [ "commerce_amazon_lpa", "commerce_checkout" ],
    "ON" : { "commerce_checkout_complete" : [] },
    "IF" : [
      { "commerce_amazon_lpa_is_amazon_order" : { "commerce_order" : [ "commerce_order" ] } }
    ],
    "DO" : [
      { "commerce_amazon_lpa_set_order_status" : { "commerce_order" : [ "commerce_order" ] } }
    ]
  }
}');

  $rules['commerce_amazon_lpa_soft_decline_email'] = entity_import('rules_config', '{ "commerce_amazon_lpa_soft_decline_email" : {
    "LABEL" : "Send customer email on soft decline from Amazon Pay",
    "PLUGIN" : "reaction rule",
    "OWNER" : "rules",
    "TAGS" : [ "Commerce Amazon LPA" ],
    "REQUIRES" : [ "rules", "commerce_amazon_lpa" ],
    "ON" : { "commerce_amazon_lpa_nonsync_auth_soft_decline" : [] },
    "DO" : [
      { "mail" : {
          "to" : [ "commerce-order:mail" ],
          "subject" : "Order [commerce-order:order-number] at [site:name]: Please update your payment information",
          "message" : "Valued customer,\r\n\r\nThank you very much for your order at [site:name].\r\n\r\nAmazon Pay was not able to process your payment.\r\nPlease go to [amazon-pay:account-link] and update the payment information for your order. Afterward, we will automatically request payment again from Amazon Pay and you will receive a confirmation email.\r\n\r\nKind regards,\r\n[site:name]",
          "language" : [ "" ]
        }
      }
    ]
  }
}');

  $rules['commerce_amazon_lpa_hard_decline_email'] = entity_import('rules_config', '{ "commerce_amazon_lpa_hard_decline_email" : {
    "LABEL" : "Send customer email on hard decline from Amazon Pay",
    "PLUGIN" : "reaction rule",
    "OWNER" : "rules",
    "TAGS" : [ "Commerce Amazon LPA" ],
    "REQUIRES" : [ "rules", "commerce_order", "commerce_amazon_lpa" ],
    "ON" : { "commerce_amazon_lpa_nonsync_auth_hard_decline" : [] },
    "DO" : [
      { "mail" : {
          "to" : [ "commerce-order:mail" ],
          "subject" : "Order [commerce-order:order-number] at [site:name]: Please contact us about your order",
          "message" : "Valued customer,\r\n\r\nUnfortunately Amazon Pay declined the payment for your order in our online shop [site:name]. Please contact us.\r\n\r\nKind regards,\r\n[site:name]",
          "language" : [ "" ]
        }
      },
      { "commerce_order_update_status" : { "commerce_order" : [ "commerce_order" ], "order_status" : "cart" } }
    ]
  }
}');


  return $rules;
}
