@core @core_grades @gradereport_user @javascript
Feature: User can toggle the visibility of the grade categories within the user grade report.
  In order to focus only on the information that I am interested in
  As a teacher
  I need to be able to easily toggle the visibility of grade categories in the user grade report

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course   | C1        | 0        |
    And the following "users" exist:
      | username  | firstname | lastname  | email                 | idnumber  |
      | teacher1  | Teacher   | 1         | teacher1@example.com  | t1        |
      | student1  | Student   | 1         | student1@example.com  | s1        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher1  | C1     | editingteacher |
      | student1  | C1     | student        |
    And the following "grade categories" exist:
      | fullname | course |
      | Category 1 | C1 |
    And the following "activities" exist:
      | activity | course | idnumber | name                | intro             | grade | gradepass |
      | assign   | C1     | a1       | Test assignment one | Submit something! | 300   | 150       |
    And the following "activities" exist:
      | activity | course | idnumber | name                | gradecategory | grade | gradepass |
      | assign   | C1     | a2       | Test assignment two | Category 1    | 100   | 50        |
    And the following "grade grades" exist:
      | gradeitem           | user     | grade |
      | Test assignment one | student1 | 250   |
      | Test assignment two | student1 | 20    |

  @accessibility
  Scenario: A teacher can search for and find a user to view
    Given I am on the "Course" "grades > User report > View" page logged in as "teacher1"
    And I click on "Student 1" in the "Search users" search combo box
    And I should see "Test assignment one" in the "user-grade" "table"
    And I should see "Test assignment two" in the "user-grade" "table"
    And I should see "Category 1 total" in the "user-grade" "table"
    And I should see "Course total" in the "user-grade" "table"
    # Every surface of this table is coloured by the report's own stylesheet, and the passing and failing
    # grades are coloured by the theme, so all of them have to be on screen before this runs. The colour mode
    # is left to the run, so one scenario covers light and dark.
    # The "best-practice" form cannot be used here: the course index drawer fails landmark-unique and region
    # on every course page, which has nothing to do with this report.
    And "Pass" "icon" should exist in the "Test assignment one" "table_row"
    And "Fail" "icon" should exist in the "Test assignment two" "table_row"
    And the page should meet accessibility standards
    # Hide the grade category 'Category 1'.
    When I click on ".toggle-category" "css_element" in the "Category 1" "table_row"
    Then I should not see "Test assignment two" in the "user-grade" "table"
    And I should not see "Category 1 total" in the "user-grade" "table"
    And I should see "Test assignment one" in the "user-grade" "table"
    And I should see "Course total" in the "user-grade" "table"
    # Show the grade category 'Category 1'.
    And I click on ".toggle-category" "css_element" in the "Category 1" "table_row"
    And I should see "Test assignment two" in the "user-grade" "table"
    And I should see "Category 1 total" in the "user-grade" "table"
    And I should see "Test assignment one" in the "user-grade" "table"
    And I should see "Course total" in the "user-grade" "table"
    # Hide the grade category 'Course'.
    And I click on ".toggle-category" "css_element" in the "Course" "table_row"
    And I should not see "Test assignment two" in the "user-grade" "table"
    And I should not see "Category 1 total" in the "user-grade" "table"
    And I should not see "Test assignment one" in the "user-grade" "table"
    And I should not see "Course total" in the "user-grade" "table"
