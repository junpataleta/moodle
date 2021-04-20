@mod @mod_chat
Feature: View next chat session
  In order to join the next chat session
  As a student
  I need to see next chat session date time

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  Scenario: Create a chat activity with weekly schedule and check next chat time shown on chat view page
    Given the following "activities" exist:
      | activity | name           | intro                 | course | idnumber | chattime   | schedule |
      | chat     | Test chat name | Test chat description | C1     | chat1    | 1618892627 | 3        |
    And I log in as "student1"
    When I am on the "Test chat name" "mod_chat > View" page
    Then I should see "Next chat time"

  Scenario: Create a chat activity with daily schedule and check next chat time shown on chat view page
    Given the following "activities" exist:
      | activity | name           | intro                 | course | idnumber | chattime   | schedule |
      | chat     | Test chat name | Test chat description | C1     | chat1    | 1618892627 | 2        |
    And I log in as "student1"
    When I am on the "Test chat name" "mod_chat > View" page
    Then I should see "Next chat time"

  Scenario: Create a chat activity with single schedule in the past
    Given the following "activities" exist:
      | activity | name           | intro                 | course | idnumber |  chattime  | schedule |
      | chat     | Test chat name | Test chat description | C1     | chat1    | 1618892627 | 1        |
    And I log in as "student1"
    When I am on the "Test chat name" "mod_chat > View" page
    Then I should not see "Next chat time"
