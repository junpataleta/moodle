@core @core_payment @javascript
Feature: Verify the breadcrumbs in payment accounts site administration pages
  As an admin

  Background:
    Given I log in as "admin"

  Scenario: Verify the breadcrumbs in payment account page as an admin
    Given I navigate to "Payments > Payment accounts" in site administration
    And I click on "Create payment account" "button"
    And "Create payment account" "text" should exist in the ".breadcrumb" "css_element"
    And "Payment accounts" "link" should exist in the ".breadcrumb" "css_element"
    And "Payments" "link" should exist in the ".breadcrumb" "css_element"
    And I press "Cancel"
    When I click on "Show archived" "link"
    Then "Payment accounts" "text" should exist in the ".breadcrumb" "css_element"
    And "Payments" "link" should exist in the ".breadcrumb" "css_element"
