@format @format_topics
Feature: Sections can be edited and deleted in topics format
  In order to rearrange my course contents
  As a teacher
  I need to edit and Delete topics

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | coursedisplay | numsections |
      | Course 1 | C1        | topics | 0             | 5           |
    And the following "activities" exist:
      | activity   | name                   | intro                         | course | idnumber    | section |
      | assign     | Test assignment name   | Test assignment description   | C1     | assign1     | 0       |
      | book       | Test book name         | Test book description         | C1     | book1       | 1       |
      | chat       | Test chat name         | Test chat description         | C1     | chat1       | 4       |
      | choice     | Test choice name       | Test choice description       | C1     | choice1     | 5       |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on

  Scenario: View the default name of the general section in topics format
    When I click on "Edit summary" "link" in the "li#section-0" "css_element"
    Then I should see "Use default section name [General]"

  Scenario: Edit the default name of the general section in topics format
    When I click on "Edit summary" "link" in the "li#section-0" "css_element"
    And I set the following fields to these values:
      | Use default section name | 0                           |
      | name                     | This is the general section |
    And I press "Save changes"
    Then I should see "This is the general section" in the "li#section-0" "css_element"

  Scenario: View the default name of the second section in topics format
    When I click on "Edit summary" "link" in the "li#section-2" "css_element"
    Then I should see "Use default section name [Topic 2]"
