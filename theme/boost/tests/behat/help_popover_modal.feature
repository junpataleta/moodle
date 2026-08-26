@theme_boost
Feature: Using the help popover inside a modal
  As a user who wants to use the help popover in a modal dialogue
  The help popover must be dismissable without losing the dialogue

  @javascript
  Scenario: Escape on a help popover inside a modal closes only the popover
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I am on the "Course 1" "grades > gradebook setup" page logged in as "teacher1"
    And I choose the "Add category" item in the "Add" action menu
    And I click on "Help" "button" in the "Aggregation" "form_row"
    And ".popover" "css_element" should be visible
    # The first escape key press dismisses the popover only, leaving the dialogue open.
    When I press the escape key
    Then "New category" "dialogue" should exist
    And ".popover" "css_element" should not be visible
    # A second escape key press then closes the dialogue, even though the help icon still
    # has focus, because no popover is open for it to dismiss.
    And I press the escape key
    And "New category" "dialogue" should not exist
