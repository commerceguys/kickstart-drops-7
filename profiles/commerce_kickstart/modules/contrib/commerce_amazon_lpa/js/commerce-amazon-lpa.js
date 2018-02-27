/* global amazon */
(function($, Drupal) {
  'use strict';

  Drupal.AmazonLPA = {
    merchantId: '',
    authRequest: '',
    clientId: '',
    checkoutUrl: '',
    langcode: '',
    orderReferenceId: '',
    isShippable: true,
    loginButtonOptions: {
      style: 'Gold',
      size: 'medium',
      type: 'LwA'
    },
    loginOptions: {
      scope: 'profile postal_code payments:widget payments:shipping_address',
      popup: true
    },
    payButtonOptions: {
      style: 'Gold',
      size: 'medium',
      type: 'PwA'
    },
    addressBookOptions: {
      displayMode: 'edit'
    },
    walletOptions: {
      displayMode: 'edit'
    },

    errorHandler: function (f) {
      console.log(f.getErrorCode()+f.getErrorMessage())
    },

    initialize: function (settings) {
      if (typeof settings.AmazonLPA.callbacks === 'object') {
        for (var item in settings.AmazonLPA.callbacks) {
          if (settings.AmazonLPA.callbacks.hasOwnProperty(item) && typeof eval(settings.AmazonLPA.callbacks[item].callback) === 'function') {
            eval(settings.AmazonLPA.callbacks[item].callback)(settings.AmazonLPA.callbacks[item].param);
          }
        }
      }
    },

    LoginButton: function (elId) {
      if (document.getElementById(elId) !== null) {
        var $el = $('#' + elId);
        OffAmazonPayments.Button(elId, Drupal.AmazonLPA.merchantId, {
          type: $el.data('pay-type'),
          color: Drupal.AmazonLPA.loginButtonOptions.style,
          size: $el.data('pay-size'),
          language: Drupal.AmazonLPA.langcode,
          useAmazonAddressBook: true,
          authorization: function () {
            var loginOptions = Drupal.AmazonLPA.loginOptions;
            Drupal.AmazonLPA.authRequest = amazon.Login.authorize(loginOptions, $el.data('url'));
          },
          onError: function (error) {
            Drupal.AmazonLPA.errorHandler(error);
          }
        });
      }
    },

    PaymentButton: function (elId) {
      if (document.getElementById(elId) !== null) {
        var $el = $('#' + elId);
        OffAmazonPayments.Button(elId, Drupal.AmazonLPA.merchantId, {
          type: $el.data('pay-type'),
          color: Drupal.AmazonLPA.payButtonOptions.style,
          size: $el.data('pay-size'),
          language: Drupal.AmazonLPA.langcode,
          useAmazonAddressBook: true,
          authorization: function () {
            var loginOptions = Drupal.AmazonLPA.loginOptions;
            Drupal.AmazonLPA.authRequest = amazon.Login.authorize(loginOptions, $el.data('checkout-url'));
          },
          onError: function (error) {
            Drupal.AmazonLPA.errorHandler(error);
          }
        });
      }
    },

    AddressBookWidget: function (elId) {
      if (Drupal.AmazonLPA.addressBookOptions.displayMode === 'edit') {
        $('input.checkout-continue').once('amazon-pay', function () {
          $(this).attr('disabled', true);
        });
      }

      new OffAmazonPayments.Widgets.AddressBook({
        sellerId: Drupal.AmazonLPA.merchantId,
        amazonOrderReferenceId: Drupal.AmazonLPA.orderReferenceId || null,
        displayMode: Drupal.AmazonLPA.addressBookOptions.displayMode,
        design: {
          designMode: 'responsive'
        },
        /**
         * Provide a way to return the current order's contract ID.
         *
         * @see c.Widgets.AddressBook.prototype.renderAddressBook
         *
         * @returns string|null
           */
        getContractId: function () {
          if (Drupal.AmazonLPA.orderReferenceId) {
            return Drupal.AmazonLPA.orderReferenceId;
          }

          return null;
        },
        onOrderReferenceCreate: function (orderReference) {
          if (!Drupal.AmazonLPA.orderReferenceId) {
            Drupal.AmazonLPA.orderReferenceId = orderReference.getAmazonOrderReferenceId();
            var $referenceIdField = $('input[name="commerce_amazon_lpa_contract_id[reference_id]"]', document);
            if ($referenceIdField.length > 0) {
              $referenceIdField.val(orderReference.getAmazonOrderReferenceId());
              this.amazonOrderReferenceId = orderReference.getAmazonOrderReferenceId();
            }
          }
        },
        onAddressSelect: function (orderReference) {
          // Support check forms with shipping pane inline and AJAX.
          if (typeof $.fn.commerceCheckShippingRecalculation === 'function') {
            $.fn.commerceCheckShippingRecalculation();
          }
          if (Drupal.AmazonLPA.addressBookOptions.displayMode === 'edit') {
            $('input.checkout-continue').attr('disabled', false);
          }
        },
        onError: function (error) {
          Drupal.AmazonLPA.errorHandler(error);
          console.log(error);
        }
      }).bind(elId);
    },

    WalletWidget: function (elId) {
      $('input.checkout-continue').once('amazon-pay', function () {
        $(this).attr('disabled', true);
      });

      var onOrderReferenceCreate = null;
      if (!Drupal.AmazonLPA.isShippable) {
        onOrderReferenceCreate = function(orderReference) {
          // Use the following cod to get the generated Order Reference ID.
          var $referenceIdField = $('input[name="commerce_payment[payment_details][amazon_order_reference_id]"]', document);
          if ($referenceIdField.length > 0 && $referenceIdField.val() === "") {
            $referenceIdField.val(orderReference.getAmazonOrderReferenceId());
          }
        };
      }

      new OffAmazonPayments.Widgets.Wallet({
        sellerId: Drupal.AmazonLPA.merchantId,
        amazonOrderReferenceId: Drupal.AmazonLPA.orderReferenceId || null,
        onPaymentSelect: function () {
          $('input.checkout-continue').attr('disabled', false);
        },
        onOrderReferenceCreate: onOrderReferenceCreate,
        design: {
          designMode: 'responsive'
        },
        onError: function (error) {
          Drupal.AmazonLPA.errorHandler(error);
        }
      }).bind(elId);
    }
  };

  $(function () {
    var settings = Drupal.settings;
    // Inject Widget.js
    var ws = document.createElement('script');
    $.extend(true, Drupal.AmazonLPA, settings.AmazonLPA);
    ws.type = 'text/javascript';
    ws.src = settings.AmazonLPA.widgetsJsUrl;
    ws.id = 'AmazonLPAWidgets';
    ws.async = true;
    document.getElementsByTagName('head')[0].appendChild(ws);

    window.onAmazonLoginReady = function () {
      amazon.Login.setClientId(settings.AmazonLPA.clientId);
      amazon.Login.setUseCookie(true);
      Drupal.AmazonLPA.initialize(settings);
    };
  });

  Drupal.behaviors.commerceAmazonLPA = {
    attach: function (context, settings) {
      if (typeof amazon !== 'undefined' && context !== document) {
        Drupal.AmazonLPA.initialize(settings);
      }
    }
  };

})(jQuery, Drupal);
