@media @media_ableplayer
Feature: Play media with Able Player
  In order to make the media in my course usable by everyone
  As a teacher
  I need Able Player to be built around the video and audio I embed

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following config values are set as admin:
      | media_plugins_sortorder | ableplayer |
    And the following "activities" exist:
      | activity | course | name       | intro                    | defaultfilename                                 | uploaded |
      | resource | C1     | Video file | Example of a video file  | media/player/ableplayer/tests/fixtures/test.mp4 | 1        |
      | resource | C1     | Audio file | Example of an audio file | media/player/ableplayer/tests/fixtures/test.mp3 | 1        |

  Scenario: Media is served with the native controls so that it plays without JavaScript
    When I am on the "Video file" "resource activity" page logged in as admin
    Then ".mediaplugin_ableplayer video[controls]" "css_element" should exist

  @accessibility @javascript
  Scenario: The player Able Player builds meets accessibility standards
    When I am on the "Video file" "resource activity" page logged in as admin
    And ".able-wrapper" "css_element" should exist
    # Scoped to the player: the rest of the page is not this plugin's to answer for.
    Then the ".able-wrapper" "css_element" should meet accessibility standards

  @javascript
  Scenario: Able Player is built around a video file
    When I am on the "Video file" "resource activity" page logged in as admin
    Then "Play" "button" should exist
    And "Preferences" "button" should exist
    And "Enter full screen" "button" should exist
    # Able Player builds its own controls, and never removes the native ones itself, so the
    # loader has to hand them over or the two sets end up shown side by side.
    And "video[controls]" "css_element" should not exist

  @javascript
  Scenario: Able Player is built around an audio file
    When I am on the "Audio file" "resource activity" page logged in as admin
    Then "Play" "button" should exist
    And "Preferences" "button" should exist
    # There is nothing to show full screen for audio.
    And "Enter full screen" "button" should not exist
    And "audio[controls]" "css_element" should not exist

  @javascript
  Scenario: The player preferences are reachable from the controls
    # Driven from the audio player because the big play button of a video covers its controls
    # until the video reports a size, which it may not have done by the time the click lands.
    When I am on the "Audio file" "resource activity" page logged in as admin
    And I click on "Preferences" "button"
    And I click on "Keyboard" "list_item"
    Then I should see "Keyboard Preferences"
    And I should see "Modifier keys used for shortcuts"

  @javascript
  Scenario: A captions track gives the player its captions and transcript controls
    Given the following "activities" exist:
      | activity | course | name       | content                                                                                                                                                                                                                                          | contentformat |
      | page     | C1     | Media page | <video controls="true"><source src="#wwwroot#/media/player/ableplayer/tests/fixtures/test.mp4" type="video/mp4"><track src="#wwwroot#/media/player/ableplayer/tests/fixtures/captions.vtt" kind="captions" srclang="en" label="English"></video> | 1             |
    When I am on the "Media page" "page activity" page logged in as admin
    Then "Hide captions" "button" should exist
    And I click on "Show transcript" "button"
    And I should see "This is the first caption." in the ".able-transcript-area" "css_element"
