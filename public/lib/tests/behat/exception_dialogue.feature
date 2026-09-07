@core @javascript @accessibility
Feature: The exception dialogue is accessible
  In order to understand an error the site has reported to me
  As a user
  I need the exception dialogue to be readable

  Background:
    Given I log in as "admin"

  Scenario: The exception dialogue meets accessibility standards
    When I am on fixture page "/lib/tests/behat/fixtures/yui_exception_dialogue_testpage.php"
    Then I should see "fixture_inner_call" in the "Fixture exception" "dialogue"
    And the "Fixture exception" "dialogue" should meet "wcag131, wcag143, wcag412" accessibility standards
