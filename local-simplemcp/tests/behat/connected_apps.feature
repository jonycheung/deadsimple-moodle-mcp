@local @local_simplemcp
Feature: Learners manage their own MCP connections
  In order to stay in control of what an AI client can read
  As a learner
  I need to see and revoke the apps connected to my account

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | learner1 | Mabel     | Learner  | learner1@example.com |
    And the following config values are set as admin:
      | enabled     | 1 | local_simplemcp |
      | enableoauth | 1 | local_simplemcp |

  @javascript
  Scenario: A learner with no connections sees the empty state
    Given I log in as "learner1"
    When I visit "/local/simplemcp/oauth/manage.php"
    Then I should see "Connected apps"
    And I should see "No apps are currently connected"

  @javascript
  Scenario: A learner can read the connection instructions
    Given I log in as "learner1"
    When I visit "/local/simplemcp/oauth/instructions.php"
    Then I should see "Server address"
    And I should see "/local/simplemcp/endpoint.php"
    And I should see "ChatGPT"
    And I should see "Claude"

  @javascript
  Scenario: The instructions page refuses to advertise a disabled server
    Given the following config values are set as admin:
      | enableoauth | 0 | local_simplemcp |
    And I log in as "learner1"
    When I visit "/local/simplemcp/oauth/instructions.php"
    Then I should see "Connections are not currently available on this site"
    And I should not see "Server address"

  @javascript
  Scenario: An administrator sees the OAuth clients screen
    Given I log in as "admin"
    When I visit "/local/simplemcp/admin/clients.php"
    Then I should see "Simple MCP OAuth clients"
    And I should see "No OAuth clients are registered yet"
