@qtype @qtype_ddimageortext @_switch_window
Feature: Preview a drag-drop onto image question
  As a teacher
  In order to check my drag-drop onto image questions will work for students
  I need to preview them

  Background:
    Given the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype         | name                     | template |
      | Test questions   | ddimageortext | Drag onto image          | xsection |
      | Test questions   | ddimageortext | Drag to mathjax equation | mathjax  |

  @javascript @_bug_phantomjs
  Scenario: Preview a question using the mouse.
    When I am on the "Drag onto image" "core_question > preview" page logged in as teacher
    And I wait "2" seconds
    # Odd, but the <br>s go to nothing, not a space.
    And I drag "mountainbelt" to place "1" in the drag and drop onto image question
    And I drag "continentalshelf" to place "2" in the drag and drop onto image question
    And I drag "oceantrench" to place "3" in the drag and drop onto image question
    And I drag "abyssalplain" to place "4" in the drag and drop onto image question
    And I drag "continentalslope" to place "5" in the drag and drop onto image question
    And I drag "continentalrise" to place "6" in the drag and drop onto image question
    And I drag "islandarc" to place "7" in the drag and drop onto image question
    And I drag "mid-oceanridge" to place "8" in the drag and drop onto image question
    And I press "Submit and finish"
    Then the state of "Identify the features" question is shown as "Correct"
    And I should see "Mark 1.00 out of 1.00"

  @javascript
  Scenario: Preview a question using the keyboard.
    When I am on the "Drag onto image" "core_question > preview" page logged in as teacher
    # Increase window size and wait 2 seconds to ensure elements are placed properly by js.
    And I choose "mountainbelt" on place "1" of the drag and drop onto image question using the keyboard
    And I choose "continentalshelf" on place "2" of the drag and drop onto image question using the keyboard
    And I choose "oceantrench" on place "3" of the drag and drop onto image question using the keyboard
    And I choose "abyssalplain" on place "4" of the drag and drop onto image question using the keyboard
    And I choose "continentalslope" on place "5" of the drag and drop onto image question using the keyboard
    And I choose "continentalrise" on place "6" of the drag and drop onto image question using the keyboard
    And I choose "islandarc" on place "7" of the drag and drop onto image question using the keyboard
    And I choose "mid-oceanridge" on place "8" of the drag and drop onto image question using the keyboard
    And I press "Submit and finish"
    Then the state of "Identify the features" question is shown as "Correct"
    And I should see "Mark 1.00 out of 1.00"

  @javascript
  Scenario: Preview a question by clicking instead of dragging.
    When I am on the "Drag onto image" "core_question > preview" page logged in as teacher
    And I click on ".dropzone.place1" "css_element"
    And I click on "abyssalplain" "text" in the "[role=listbox].show" "css_element"
    And I click on ".draghome.inplace1" "css_element"
    And I click on "mountainbelt" "text" in the "[role=listbox].show" "css_element"
    And I click on ".dropzone.place2" "css_element"
    And I click on "continentalshelf" "text" in the "[role=listbox].show" "css_element"
    And I press "Save"
    Then the state of "Identify the features" question is shown as "Incomplete answer"
    And I should see "mountain" in the ".draghome.inplace1" "css_element"
    And I should see "continental" in the ".draghome.inplace2" "css_element"

  @javascript
  Scenario: Preview a drag-drop into image question with mathjax question.
    Given the "mathjaxloader" filter is "on"
    And the "mathjaxloader" filter applies to "content and headings"
    And I am on the "Drag to mathjax equation" "core_question > preview" page logged in as teacher
    And I press "Fill in correct responses"
    When I press "Submit and finish"
    Then ".filter_mathjaxloader_equation" "css_element" should exist in the ".draghome" "css_element"
