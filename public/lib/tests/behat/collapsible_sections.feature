@core @core_form
Feature: Collapsible form sections
  In order to know whether a section of a form is expanded or collapsed
  As a user
  I need a section's expand/collapse state to be reliable

  @javascript
  Scenario: Collapsing a section after using "Expand all" keeps its own aria-expanded state accurate
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And I am on the "C1" "course editing" page logged in as "admin"
    When I press "Expand all"
    And I wait until "//*[@id='id_completionhdrcontainer'][contains(concat(' ', @class, ' '), ' show ')]" "xpath_element" exists
    Then the "aria-expanded" attribute of "Completion tracking" "button" should contain "true"
    And I press "Completion tracking"
    And I wait until "//*[@id='id_completionhdrcontainer'][not(contains(concat(' ', @class, ' '), ' show '))]" "xpath_element" exists
    And the "aria-expanded" attribute of "Completion tracking" "button" should contain "false"

  @javascript
  Scenario: A section's expanded state survives a failed form resubmission
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | s1       | John      | Doe      | s1@example.com |
    And I log in as "admin"
    And I navigate to "Users > Accounts > Add a new user" in site administration
    # Collapse the User picture section.
    And I press "User picture"
    # Expand the Optional section.
    And I press "Optional"
    When I set the following fields to these values:
      | Username      | s1                |
      | First name    | Jane              |
      | Last name     | Doe               |
      | Email address | jane2@example.com |
      | New password  | test              |
    And I press "Create user"
    Then the "aria-expanded" attribute of "User picture" "button" should contain "false"
    And the "aria-expanded" attribute of "Optional" "button" should contain "true"

  @javascript
  Scenario: A collapsed section containing validation errors is expanded
    Given I log in as "admin"
    When I navigate to "Users > Accounts > Add a new user" in site administration
    And I press "General"
    And I wait until "//fieldset[.//h3[normalize-space()='General']]//div[contains(concat(' ', @class, ' '), ' fcontainer ')][not(contains(concat(' ', @class, ' '), ' show '))]" "xpath_element" exists
    And I press "Create user"
    And I wait until "//fieldset[.//h3[normalize-space()='General']]//div[contains(concat(' ', @class, ' '), ' fcontainer ')][contains(concat(' ', @class, ' '), ' show ')]" "xpath_element" exists
    Then the "aria-expanded" attribute of "General" "button" should contain "true"

  @javascript
  Scenario: A section collapsed via "Collapse all" and containing validation errors is expanded
    Given I log in as "admin"
    When I navigate to "Users > Accounts > Add a new user" in site administration
    And I press "Expand all"
    And I wait until "//fieldset[.//h3[normalize-space()='General']]//div[contains(concat(' ', @class, ' '), ' fcontainer ')][contains(concat(' ', @class, ' '), ' show ')]" "xpath_element" exists
    And I press "Collapse all"
    And I wait until "//fieldset[.//h3[normalize-space()='General']]//div[contains(concat(' ', @class, ' '), ' fcontainer ')][not(contains(concat(' ', @class, ' '), ' show '))]" "xpath_element" exists
    And I press "Create user"
    And I wait until "//fieldset[.//h3[normalize-space()='General']]//div[contains(concat(' ', @class, ' '), ' fcontainer ')][contains(concat(' ', @class, ' '), ' show ')]" "xpath_element" exists
    Then the "aria-expanded" attribute of "General" "button" should contain "true"
