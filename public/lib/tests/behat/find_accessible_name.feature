@core
Feature: Locate elements by their accessible name
  In order to write tests that survive markup changes
  As a test writer
  I need a locator naming an element to find the element that actually has that name

  Background:
    Given I log in as "admin"
    And I am on fixture page "/lib/tests/behat/fixtures/find_accessible_name_testpage.php"

  Scenario: Prefer the element whose accessible name matches the locator without JavaScript
    When I press "Delete"
    Then I should see "Pressed: delete"
    And I press "Export questions to file"
    And I should see "Pressed: export"
    And I press "Help with Export questions to file"
    And I should see "Pressed: helpexport"
    And I press "Continue"
    And I should see "Pressed: continuelater"
    And I press "Submit form"
    And I should see "Pressed: visiblesubmit"
    And I press "Apply now"
    And I should see "Pressed: visibleapply"
    And I press "Sydney"
    And I should see "Pressed: Sydney"

  @javascript
  Scenario: Prefer the element whose accessible name matches the locator with JavaScript
    When I press "Delete"
    Then I should see "Pressed: delete"
    And I press "Export questions to file"
    And I should see "Pressed: export"
    And I press "Help with Export questions to file"
    And I should see "Pressed: helpexport"
    And I press "Continue"
    And I should see "Pressed: continuelater"
    And I press "Submit form"
    And I should see "Pressed: visiblesubmit"
    And I press "Apply now"
    And I should see "Pressed: visibleapply"
    And I press "Sydney"
    And I should see "Pressed: Sydney"
