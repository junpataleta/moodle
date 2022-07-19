@core @core_completion
Feature: Ensure completion events are emitted
  In order to conduct postmortems and maintain accountability
  As an admin
  I need to know who performed actions and when

  Background:
    Given the following "courses" exist:
      | fullname | shortname  | category  | enablecompletion |
      | Course 1 | C1         | 0         | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | Frist | teacher1@example.com |
      | student1 | Student | First | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  @javascript
  Scenario: Ensure event is thrown when user unlocks course completion settings
    Given the following "activities" exist:
      | activity  | name           | intro                  | course | section | completion | content             |
      | label     |                | Test label             | C1     | 1       | 1          |                     |
      | page      | Test page name | Test page description  | C1     | 2       | 0          | Test page contents  |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I edit the section "2"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Activity completion" "button" in the "Add restriction..." "dialogue"
    And I set the following fields to these values:
      | cm | Test label |
      | Required completion status | must be marked complete |
    And I press "Save changes"
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I click on "Label - Test label" "checkbox"
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I toggle the manual completion state of "Test label"
    And I log out
    And I log in as "teacher1"
    And I run the scheduled task "\core\task\completion_regular_task"
    And I am on "Course 1" course homepage
    And I navigate to "Course completion" in current page administration
    Then I should see "Completion settings locked"
    And I click on "Unlock completion settings and delete user completion data" "button"
    And I log out
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Reports > Live logs" in site administration
    Then I should see "Course completion settings unlocked"
