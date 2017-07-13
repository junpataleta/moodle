@core @core_user
Feature: Edit user enrolment
  In order to manage students' enrolments
  As a teacher
  I need to be able to view enrolment details and edit student enrolments in the course participants page

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                 |
      | teacher1  | Teacher   | 1        | teacher1@example.com  |
      | student1  | Student   | 1        | student1@example.com  |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user      | course | role           | status |
      | teacher1  | C1     | editingteacher |    0   |
      | student1  | C1     | student        |    0   |

  @javascript
  Scenario: Edit a user's enrolment status
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    When I click on "//a[@class='editenrollink']" "xpath_element" in the "student1" "table_row"
    And I set the field "Status" to "Suspended"
    And I click on "Save changes" "button"
    Then I should see "Suspended" in the "student1" "table_row"

  @javascript
  Scenario: Unenrol a student
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    When I click on "//a[@class='unenrollink']" "xpath_element" in the "student1" "table_row"
    And I click on "Yes" "button"
    Then I should not see "Student 1" in the "participants" "table"

  @javascript
  Scenario: View as student's enrolment details
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    When I click on "//a[@class='enroldetails']" "xpath_element" in the "student1" "table_row"
    Then I should see "Enrolment details"
    And I should see "Student 1" in the "Full name" "table_row"
    And I should see "Active" in the "Status" "table_row"
