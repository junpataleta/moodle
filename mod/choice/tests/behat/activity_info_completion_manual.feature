@mod @mod_choice
Feature: Manual completion in the choice activity
  To avoid navigating from the choice activity to the course homepage to mark the choice activity as complete
  As a student
  I need to be able to mark the choice activity as complete within the choice activity itself

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | 1        | teacher1@example.com  |
      | student1 | Student   | 1        | student1@example.com  |
    And the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
      | category          | 0         |
      | enablecompletion  | 1         |
    And the following "activity" exists:
      | activity    | choice                                          |
      | name        | Where to eat?                                   |
      | intro       | We're eating out. Where do you want to eat out? |
      | course      | C1                                              |
      | idnumber    | choice1                                         |
      | option[0]   | Tim's Thai                                      |
      | option[1]   | Emma's                                          |
      | option[2]   | Ciao Italia                                     |
      | option[3]   | Ten-Ten Kitchen                                 |
      | completion  | 1                                               |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |

  @javascript
  Scenario: Toggle manual completion as a student
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Where to eat?"
    And "Mark as done" "button" should exist
    When I press "Mark as done"
    And I wait until the page is ready
    Then "Done" "button" should exist
    But "Mark as done" "button" should not exist
    # Just make sure that the change persisted.
    And I reload the page
    And I wait until the page is ready
    And I should not see "Mark as done"
    And I should see "Done"
    And I press "Done"
    And I wait until the page is ready
    And "Mark as done" "button" should exist
    But "Done" "button" should not exist
    # Just make sure that the change persisted.
    And I reload the page
    And I wait until the page is ready
    And "Mark as done" "button" should exist
    But "Done" "button" should not exist

  Scenario: Viewing a choice activity with manual completion as a teacher
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Where to eat?"
    And "Mark as done" "button" should not exist
