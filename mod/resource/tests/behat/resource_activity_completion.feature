@mod @mod_resource @core_completion @_file_upload
Feature: View activity completion information for the resource
  In order to have visibility of Resource completion requirements
  As a student
  I need to be able to view my Resource completion progress

  Background:
    Given the following "users" exist:
      | username | firstname  | lastname | email                |
      | student1 | Vinnie    | Student1 | student1@example.com |
      | teacher1 | Darrell   | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Enable completion tracking | Yes |
      | Show completion conditions | No |
    And I press "Save and display"

  @javascript
  Scenario: View automatic completion items in embed display mode
    Given I am on "Course 1" course homepage with editing mode on
    And I add a "File" to section "1"
    And I set the following fields to these values:
      | Name                      | Myfile                                             |
      | id_display                | Embed                                             |
      | Show size                 | 0                                                 |
      | Show type                 | 0                                                 |
      | Show upload/modified date  | 0                                                 |
      | Completion tracking       | Show activity as complete when conditions are met |
      | Require view              | 1                                                 |
    And I upload "mod/resource/tests/fixtures/samplefile.txt" file to "Select files" filemanager
    And I press "Save and display"
    # Teacher view.
    And "Myfile" should have the "View" completion condition
    And I log out
    # Student view.
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Myfile"
    Then the "View" completion condition of "Myfile" is displayed as "done"

  @javascript
  Scenario: Manual completion button will not be shown in the course page for Embed display mode
    Given I am on "Course 1" course homepage with editing mode on
    And I add a "File" to section "1"
    And I set the following fields to these values:
      | Name                      | Myfile                                                |
      | id_display                | Embed                                                |
      | Show size                 | 0                                                    |
      | Show type                 | 0                                                    |
      | Show upload/modified date  | 0                                                    |
      | Completion tracking       | Students can manually mark the activity as completed |
    And I upload "mod/resource/tests/fixtures/samplefile.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    # Teacher view.
    And the manual completion button for "Myfile" should not exist
    And I log out
    # Student view.
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then the manual completion button for "Myfile" should not exist
    And I follow "Myfile"
    And the manual completion button for "Myfile" should exist

  @javascript
  Scenario Outline: Manual completion button will be shown in the course page for Open and In pop-up and Force download display mode
    Given I am on "Course 1" course homepage with editing mode on
    And I add a "File" to section "1"
    And I set the following fields to these values:
      | Name                      | Myfile                                                |
      | id_display                | <display>                                            |
      | Show size                 | 0                                                    |
      | Show type                 | 0                                                    |
      | Show upload/modified date  | 0                                                    |
      | Completion tracking       | Students can manually mark the activity as completed |
    And I upload "mod/resource/tests/fixtures/samplefile.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    # Teacher view.
    And the manual completion button for "Myfile" should exist
    And I log out
    # Student view.
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then the manual completion button for "Myfile" should exist

    Examples:
      | display        |
      | Open           |
      | In pop-up      |
      | Force download |
