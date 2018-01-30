@api @javascript
Feature: Sandbox: Testing rejected authorizations
  As store owner
  Amazon module should handle rejected authorizations

  Background:
    When I set the Amazon setting "commerce_amazon_lpa_popup" to "redirect"
    When I set the Amazon setting "commerce_amazon_lpa_authorization_mode" to "automatic_sync"
    And I set the Amazon setting "commerce_amazon_lpa_capture_mode" to "shipment_capture"
    And I set the Amazon setting "commerce_amazon_lpa_simulation" to "Authorizations_AmazonRejected"

  Scenario: Sync auth with Amazon Rejected
    When I go to "/bags-cases/commerce-guys-laptop-bag"
    And I press "Add to cart"
    When I go to "/cart"
    And I wait for Amazon to load
    Then I click the Pay with Amazon button
    And I switch to popup
    Then I fill in the following:
      | email    | matt+1@commerceguys.com |
      | password | password                |
    And I press the "Sign in using our secure server" button
    When I switch back to original window
    Then I am on the checkout form
    And I wait for Amazon to load
    And I wait for the Amazon order reference
    Then the Amazon address book exists
    Then I press "Continue to next step"
    And I should see "Shipping service"
    Then I press "Continue to next step"
    And I wait for Amazon to load
        # Find better way to see if this is loaded.
    And I wait for 3 seconds
    Then I should see "Review order"
    And the Amazon wallet exists
    And the Amazon address book review exists
    Then I press "Continue to next step"
    Then I should see "Shopping cart"
      And I should see "Your order could not be completed, please select a different payment method."
      And I should see "Your shopping cart is empty."
    And I set the Amazon setting "commerce_amazon_lpa_simulation" to "_none"
