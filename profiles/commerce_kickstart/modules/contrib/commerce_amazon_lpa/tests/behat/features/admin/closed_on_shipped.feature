@api @javascript
Feature: Orders are closed on shipped
  As a store owner
  I can mark the order as shipped
  With Amazon then closing the order

  Scenario: Marking order complete closes order
    When I am logged in as a user with the "administrator" role
      And an Amazon order has been created
    And I go to "/admin/commerce/orders"
    When I click on Quick Edit link
    Then I select "completed" from "status"
    And I press "edit-save"
      And I wait for AJAX loading to finish
    And I take an awesome screenshot
