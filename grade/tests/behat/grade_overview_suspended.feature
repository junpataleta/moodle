@core @core_grades @gradereport_overview
Feature: Suspended students should not see grades in overview report
  As a student
  I only want to see courses I am active in when viewing the grade overview report
  So that I can focus on the courses I am enrolled in.

  Scenario:
    Given the following "courses" exist:
      | fullname    | shortname |
      | Active      | C1        |
      | Suspended   | C2        |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    | status |
      | student1 | C1     | student | 0      |
      | student1 | C2     | student | 1      |
    And I log in as "student1"
    And I follow "Active"
    And I navigate to "Grades" node in "Course administration"
    And I set the field with xpath "//*[@id='choosepluginreport']/div/select" to "Overview report"
    And I should see "Active"
    And I should not see "Suspended"
