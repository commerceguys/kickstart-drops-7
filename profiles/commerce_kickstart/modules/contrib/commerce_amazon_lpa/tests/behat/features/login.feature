@api @javascript
Feature: Login with Amazon
  As an anonymous user
  I should be able to Login

  Scenario: Log in with Amazon
    When I go to "/user/login"
      And I wait for Amazon to load
    When I click the Login with Amazon button
      And I switch to popup
    Then I fill in the following:
      | email    | matt+1@commerceguys.com |
      | password | password                |
      And I press the "Sign in using our secure server" button
    When I switch back to original window
      Then I should see "Account information"

  Scenario: As a user, I can create an order, log in, and my cart persists.
    When I go to "/bags-cases/commerce-guys-laptop-bag"
      And I press "Add to cart"
    When I go to "/user/login"
      And I wait for Amazon to load
    When I click the Login with Amazon button
      And I switch to popup
    Then I fill in the following:
      | email    | matt+1@commerceguys.com |
      | password | password                |
    And I press the "Sign in using our secure server" button
    When I switch back to original window
      Then I should see "Account information"
    When I go to "/cart"
      Then I should see "LAP1-BLK-13"
      And I should see "£50.40"
    # Clean up.
    Then I press "Remove"
      And I should see "Laptop Bag 1 removed from your cart."

