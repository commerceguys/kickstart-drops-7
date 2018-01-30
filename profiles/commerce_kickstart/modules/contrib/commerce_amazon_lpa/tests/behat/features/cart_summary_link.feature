@api @javascript
Feature: Cart summary link for Amazon
  As a customer
  I can click Pay with Amazon
  From my cart widget

  Scenario: I can click the pay with Amazon button in cart widget
    When I go to "/storage-devices/commerce-guys-usb-key"
      And I press "Add to cart"
    Then I click the cart summary Pay with Amazon button
      And I switch to popup
    Then I fill in the following:
      | email    | matt+1@commerceguys.com |
      | password | password                |
      And I press the "Sign in using our secure server" button
    When I switch back to original window
      And I wait for AJAX loading to finish
    Then I am on the checkout form
      And I should see "Storage 1"
