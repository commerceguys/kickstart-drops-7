(function (Drupal, $, document) {
  "use strict";

  function getURLParameter(name, source) {
    return decodeURIComponent((new RegExp('[?|&|#]' + name + '=' + '([^&]+?)(&|#|;|$)').exec(source) || [,""])[1].replace(/\+/g, '%20')) || null;
  }

  var accessToken = getURLParameter("access_token", location.hash);

  if (typeof accessToken === 'string' && accessToken.match(/^Atza/)) {
    document.cookie = "amazon_Login_accessToken=" + accessToken + ";secure";
  }

  $(function () {
    window.onAmazonLoginReady = function () {
      amazon.Login.setClientId(Drupal.AmazonLPA.clientId);
      amazon.Login.setUseCookie(true);
      window.location.reload();
    };
  });
})(Drupal, jQuery, document);
