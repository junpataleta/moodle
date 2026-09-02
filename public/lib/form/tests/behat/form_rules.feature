@core @core_form @core_form-rules
Feature: Test the form rules
  For forms that make use of different rules
  So that users can use form elements
  I need to test the ensure elements are controlled correctly

  Background:
    Given I log in as "admin"
    And I am on fixture page "/lib/form/tests/behat/fixtures/form_rules.php"
    And I wait until the page is ready

  @javascript
  Scenario Outline: Checkbox, Date selector, Editor, Filepicker rules
    Given I should <ckbprevis> "<label>"
    When I click on "<checkbox>" "checkbox"
    Then I should <ckbresultvis> "<label>"
    And the "<id>" "<type>" should be <ckbresultstat>

    Examples:
      | checkbox            | ckbprevis | label                     | ckbresultvis | ckbresultstat | type        | id                   |
      | Checked hide        | see       | Checked hidden            | not see      | enabled       | button      | Checked hidden       |
      | Checked hide        | see       | Label test                | not see      | enabled       | field        | Label test           |
      | Checked disable     | see       | Checked disabled          | see          | disabled      | button      | Checked disabled     |
      | Not checked hide    | not see   | Not checked hidden        | see          | enabled       | button      | Not checked hidden   |
      | Not checked disable | see       | Not checked disabled      | see          | enabled       | button      | Not checked disabled |
      | EQ Checked          | see       | EQ ckb 1 disabled         | see          | disabled      | button      | EQ ckb 1 disabled    |
      | NEQ Checked         | not see   | NEQ ckb 0 hidden          | see          | enabled       | button      | NEQ ckb 0 hidden     |
      | ds_enb              | see       | Date selector for testing | see          | disabled      | css_element | #id_ds_day           |
      | ds_dis              | see       | Date selector for testing | not see      | enabled       | css_element | #id_ds_day           |
      | edt_enb             | see       | Editor for testing        | see          | disabled      | css_element | #id_edt              |
      | edt_dis             | see       | Editor for testing        | not see      | enabled       | css_element | #id_edt              |
      | fp_dis              | see       | Filepicker for testing    | not see      | enabled       | css_element | #id_fp               |

  @javascript
  Scenario Outline: Radio rules
    Given I should <previs> "<label>"
    And the "<label>" "button" should be <prestat>
    And I should <oppprevis> "<opplabel>"
    And the "<opplabel>" "button" should be <oppprestat>
    When I click on "<radio>" "radio"
    Then I should <postvis> "<label>"
    And the "<label>" "button" should be <poststat>
    And I should <opppostvis> "<opplabel>"
    And the "<opplabel>" "button" should be <opppoststat>

    Examples:
      | radio   | label     | previs  | prestat  | postvis | poststat | opplabel  | oppprevis | oppprestat | opppostvis | opppoststat |
      | Enable  | Radio EQ  | see     | enabled  | see     | enabled  | Radio NEQ | not see   | disabled   | not see    | disabled    |
      | Disable | Radio EQ  | see     | enabled  | see     | disabled | Radio NEQ | not see   | disabled   | not see    | disabled    |
      | Hide    | Radio NEQ | not see | disabled | see     | disabled | Radio EQ  | see       | enabled    | not see    | enabled     |

  @javascript
  Scenario Outline: Text input rules
    Given I should <previs> "<alphalabel>"
    And the "<alphalabel>" "button" should be <prestat>
    And I should <previs> "<intlabel>"
    And the "<intlabel>" "button" should be <prestat>
    When I set the field "Text input" to "<textinp>"
    And I set the field "Number input" to "<intinp>"
    Then I should <postvis> "<alphalabel>"
    And the "<alphalabel>" "button" should be <poststat>
    And I should <postvis> "<intlabel>"
    And the "<intlabel>" "button" should be <poststat>

    # NEQ / NE & NOTEQ are the same rule so don't need to run tests for aliased rules.
    Examples:
      | textinp                   | alphalabel  | intlabel  | intinp | previs  | postvis | prestat  | poststat |
      | Disable eq                | Alpha EQ    | Int EQ    | 1      | see     | see     | enabled  | disabled |
      | Hidden eq                 | Alpha EQ    | Int EQ    | 2      | see     | not see | enabled  | enabled  |
      | Disable neq               | Alpha NEQ   | Int NEQ   | 3      | not see | not see | disabled | disabled |
      | Hidden neq                | Alpha NEQ   | Int NEQ   | 4      | not see | see     | disabled | disabled |
      | Tool                      | Alpha IN    | Int IN    | 9      | see     | see     | enabled  | disabled |
      | USAO / Camellia / No Mana | Alpha IN    | Int IN    | 12     | see     | not see | enabled  | enabled  |

  @javascript
  Scenario Outline: Select rule test
    Given I should <sctprevis> "<label>"
    When I set the field "sct_int" to "<sctopt>"
    Then I should <sctresultvis> "<label>"
    And the "<label>" "button" should be <sctresultstat>

    Examples:
      | sctopt  | sctprevis | label      | sctresultvis | sctresultstat |
      | Enable  | see       | Select EQ  | see          | enabled       |
      | Enable  | see       | Select NEQ | see          | disabled      |
      | Disable | see       | Select EQ  | see          | disabled      |
      | Disable | see       | Select NEQ | not see      | disabled      |
      | Hide    | see       | Select EQ  | not see      | enabled       |
      | Hide    | see       | Select NEQ | not see      | disabled      |
