@theme @theme_boost
Feature: Only clicks on the drawer backdrop close the drawers
  In order to keep my place on the page
  As a user
  I need the drawers to close when I click the backdrop, and not when I use the content behind it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activity" exists:
      | activity | page                                                                                         |
      | course   | C1                                                                                           |
      | idnumber | page1                                                                                        |
      | name     | Video page                                                                                   |
      | content  | <a href="#wwwroot#/lib/editor/tiny/tests/behat/fixtures/moodle-logo.mp4">moodle-logo.mp4</a> |
    And the following "blocks" exist:
      | blockname    | contextlevel    | reference | pagetypepattern | defaultregion |
      | online_users | Activity module | page1     | mod-page-*      | side-pre      |

  @javascript
  Scenario Outline: Clicking the backdrop closes the open drawer
    Given I change window size to "700x900"
    And I am on the "Video page" "page activity" page logged in as "student1"
    And I click on "<openbutton>" "button"
    And "<drawer>" "region" should be visible
    When I click on ".modal-backdrop.show" "css_element"
    Then "<drawer>" "region" should not be visible

    Examples:
      | openbutton        | drawer                          |
      | Open course index | theme_boost-drawers-courseindex |
      | Open block drawer | theme_boost-drawers-blocks      |

  @javascript
  Scenario: Using a video player after leaving fullscreen keeps the drawers open
    # Tall enough for no scrollbar, so nothing resizes the page before it goes fullscreen.
    Given I change window size to "large"
    And I am on the "Video page" "page activity" page logged in as "student1"
    And "theme_boost-drawers-courseindex" "region" should be visible
    And I click on "Play Video" "button"
    And I click on "Pause" "button" in the ".vjs-control-bar" "css_element"
    And ".video-js" "css_element" is shown in fullscreen
    And I leave fullscreen
    When I click on "Play" "button" in the ".vjs-control-bar" "css_element"
    Then the focused element is "Pause" "button" in the ".vjs-control-bar" "css_element"
    And "theme_boost-drawers-courseindex" "region" should be visible

  @javascript
  Scenario: Using a video player with the keyboard after leaving fullscreen keeps the drawers open
    # Tall enough for no scrollbar, so nothing resizes the page before it goes fullscreen.
    Given I change window size to "large"
    And I am on the "Video page" "page activity" page logged in as "student1"
    And "theme_boost-drawers-courseindex" "region" should be visible
    And I click on "Play Video" "button"
    And I click on "Pause" "button" in the ".vjs-control-bar" "css_element"
    And ".video-js" "css_element" is shown in fullscreen
    And I leave fullscreen
    And I set the focus on the ".vjs-control-bar .vjs-play-control" "css_element"
    When I press the space key
    Then the focused element is "Pause" "button" in the ".vjs-control-bar" "css_element"
    And "theme_boost-drawers-courseindex" "region" should be visible
