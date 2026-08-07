@mod @mod_lesson @accessibility
Feature: Lesson skip link moves keyboard focus past the menu
  In order to navigate a lesson efficiently with a keyboard
  As a user
  I need the "Skip navigation" link to move focus beyond the lesson menu, without trapping it

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity | name        | course | idnumber | displayleft |
      | lesson   | Test lesson | C1     | lesson1  | 1           |
    And the following "mod_lesson > pages" exist:
      | lesson      | qtype   | title       | content             |
      | Test lesson | content | First page  | First page content  |
      | Test lesson | content | Second page | Second page content |
    And the following "mod_lesson > answers" exist:
      | page        | answer    | jumpto    |
      | First page  | Next page | Next page |
      | Second page | Next page | Next page |
    And I am on the "Test lesson" "lesson activity" page logged in as "admin"

  @javascript
  Scenario: Activating the skip navigation link moves focus past the lesson menu
    When I set the focus on the "Skip navigation" "link"
    And I press the enter key
    Then the focused element is "#lessoncontent" "css_element"
