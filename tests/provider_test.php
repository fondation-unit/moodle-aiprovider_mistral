<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiprovider_mistral;

use core_ai\manager;

/**
 * Test Mistral AI provider methods.
 *
 * @package    aiprovider_mistral
 * @copyright  2026 Fondation UNIT <webmaster@unit.eu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_mistral\provider
 */
final class provider_test extends \advanced_testcase {
    /** @var manager $manager */
    private manager $manager;

    /** @var provider $provider */
    private provider $provider;

    /**
     * Overriding setUp() function to always reset after tests.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->manager = \core\di::get(\core_ai\manager::class);
        $this->provider = $this->manager->create_provider_instance(
            classname: '\aiprovider_mistral\provider',
            name: 'dummy',
            config: [],
        );
    }

    /**
     * Test get_action_list returns all supported actions.
     */
    public function test_get_action_list(): void {
        $actionlist = provider::get_supported_actions();

        $this->assertIsArray($actionlist);
        $this->assertCount(4, $actionlist);
        $this->assertContains(\core_ai\aiactions\generate_text::class, $actionlist);
        $this->assertContains(\core_ai\aiactions\generate_image::class, $actionlist);
        $this->assertContains(\core_ai\aiactions\summarise_text::class, $actionlist);
        $this->assertContains(\core_ai\aiactions\explain_text::class, $actionlist);
    }

    /**
     * Test is_request_allowed enforces user rate limit.
     */
    public function test_is_request_allowed_user_rate_limit(): void {
        $provider = $this->manager->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: [
                'enableuserratelimit' => true,
                'userratelimit' => 3,
                'enableglobalratelimit' => false,
            ],
        );

        $action = $this->make_generate_image_action(userid: 1);

        // First 3 requests should be allowed.
        for ($i = 0; $i < 3; $i++) {
            $result = $provider->is_request_allowed($action);
            $this->assertTrue($result === true || ($result['success'] ?? false));
        }

        // The 4th request should be denied.
        $result = $provider->is_request_allowed($action);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(
            'You have reached the maximum number of AI requests you can make in an hour. Try again later.',
            $result['errormessage'],
        );
    }

    /**
     * Test is_request_allowed enforces global rate limit.
     */
    public function test_is_request_allowed_global_rate_limit(): void {
        $provider = $this->manager->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: [
                'enableuserratelimit' => false,
                'enableglobalratelimit' => true,
                'globalratelimit' => 5,
            ],
        );

        // Use different user IDs to avoid hitting the user rate limit.
        for ($i = 1; $i <= 5; $i++) {
            $action = $this->make_generate_image_action(userid: $i);
            $result = $provider->is_request_allowed($action);
            $this->assertTrue($result === true || ($result['success'] ?? false));
        }

        // 6th request (different user) should be denied by the global limit.
        $action = $this->make_generate_image_action(userid: 6);
        $result = $provider->is_request_allowed($action);
        $this->assertFalse($result['success']);
        $this->assertEquals(
            'The AI service has reached the maximum number of site-wide requests per hour. Try again later.',
            $result['errormessage'],
        );
    }

    /**
     * Test is_request_allowed enforces both user and global rate limits.
     */
    public function test_is_request_allowed_both_rate_limits(): void {
        $provider = $this->manager->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: [
                'enableuserratelimit' => true,
                'userratelimit' => 3,
                'enableglobalratelimit' => true,
                'globalratelimit' => 5,
            ],
        );

        $action = $this->make_generate_image_action(userid: 1);

        // 3 requests for user 1 - all allowed.
        for ($i = 0; $i < 3; $i++) {
            $result = $provider->is_request_allowed($action);
            $this->assertTrue($result === true || ($result['success'] ?? false));
        }

        // 4th request for user 1 - denied by user rate limit.
        $result = $provider->is_request_allowed($action);
        $this->assertFalse($result['success']);

        // 2 more requests for user 2 - allowed (global still has room).
        $action2 = $this->make_generate_image_action(userid: 2);
        $this->assertTrue($provider->is_request_allowed($action2));
        $this->assertTrue($provider->is_request_allowed($action2));

        // 6th global request - denied.
        $action3 = $this->make_generate_image_action(userid: 3);
        $result = $provider->is_request_allowed($action3);
        $this->assertFalse($result['success']);
        $this->assertEquals(
            'The AI service has reached the maximum number of site-wide requests per hour. Try again later.',
            $result['errormessage'],
        );
    }

    /**
     * Test is_provider_configured returns false when not configured.
     */
    public function test_is_provider_configured_not_configured(): void {
        $this->assertFalse($this->provider->is_provider_configured());
    }

    /**
     * Test is_provider_configured returns true when API key is set.
     */
    public function test_is_provider_configured_with_apikey(): void {
        $updatedprovider = $this->manager->update_provider_instance(
            provider: $this->provider,
            config: ['apikey' => 'testapikey123'],
        );

        $this->assertTrue($updatedprovider->is_provider_configured());
    }

    /**
     * Test is_provider_configured returns false when API key is empty.
     */
    public function test_is_provider_configured_empty_apikey(): void {
        $updatedprovider = $this->manager->update_provider_instance(
            provider: $this->provider,
            config: ['apikey' => ''],
        );

        $this->assertFalse($updatedprovider->is_provider_configured());
    }

    /**
     * Helper to create a generate_image action for rate limit testing.
     *
     * @param int $userid The user ID to use.
     * @return \core_ai\aiactions\generate_image
     */
    private function make_generate_image_action(int $userid): \core_ai\aiactions\generate_image {
        return new \core_ai\aiactions\generate_image(
            contextid: 1,
            userid: $userid,
            prompttext: 'A test prompt',
            quality: 'hd',
            aspectratio: 'square',
            numimages: 1,
            style: 'vivid',
        );
    }
}
