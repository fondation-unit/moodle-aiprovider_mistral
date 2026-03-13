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

use aiprovider_mistral\test\testcase_helper_trait;
use core_ai\aiactions\base;
use core_ai\provider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Test Generate image process class for Mistral provider.
 *
 * @package    aiprovider_mistral
 * @copyright  2026 Fondation UNIT <webmaster@unit.eu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_mistral\provider
 * @covers     \aiprovider_mistral\process_generate_image
 * @covers     \aiprovider_mistral\abstract_processor
 */
final class process_generate_image_test extends \advanced_testcase {
    use testcase_helper_trait;

    /** @var \core_ai\manager */
    private $manager;

    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /** @var string A fake agent ID for testing. */
    private string $fakeagentid = 'agent-test-123';

    /** @var string A fake file ID for testing. */
    private string $fakefileid = 'file-test-abc456';

    /** @var \ReflectionProperty Reflection property for the $client field on abstract_processor. */
    private \ReflectionProperty $clientproperty;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->manager = \core\di::get(\core_ai\manager::class);
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\generate_image::class,
        );
        $this->create_action();

        // Cache the reflection property once - reused across all tests.
        $reflection = new \ReflectionClass(abstract_processor::class);
        $this->clientproperty = $reflection->getProperty('client');
        $this->clientproperty->setAccessible(true);
    }

    /**
     * Create the action object.
     *
     * @param int $userid The user id to use in the action.
     */
    private function create_action(int $userid = 1): void {
        $this->action = new \core_ai\aiactions\generate_image(
            contextid: 1,
            userid: $userid,
            prompttext: 'A test prompt for image generation',
            quality: 'hd',
            aspectratio: 'square',
            numimages: 1,
            style: 'vivid',
        );
    }

    /**
     * Inject a MockHandler into the processor's $client property.
     *
     * All HTTP calls in process_generate_image go through $this->client,
     * so a single MockHandler covers the full request sequence.
     *
     * @param process_generate_image $processor The processor to inject into.
     * @param Response[] $responses Ordered responses to queue.
     * @return MockHandler
     */
    private function mock_client(process_generate_image $processor, array $responses = []): MockHandler {
        $mock = new MockHandler($responses);
        $handlerstack = HandlerStack::create($mock);
        $this->clientproperty->setValue(
            $processor,
            new \GuzzleHttp\Client(['handler' => $handlerstack])
        );
        return $mock;
    }

    /**
     * Build a fake successful agent creation response.
     *
     * @return Response
     */
    private function agent_creation_response(): Response {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['id' => $this->fakeagentid]),
        );
    }

    /**
     * Build a fake successful conversation response containing an image file_id.
     *
     * @param string|null $fileid Override file ID.
     * @return Response
     */
    private function conversation_response(?string $fileid = null): Response {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'outputs' => [
                    [
                        'type' => 'message.output',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Here is the generated image.',
                            ],
                            [
                                'type' => 'tool_file',
                                'tool' => 'image_generation',
                                'file_id' => $fileid ?? $this->fakefileid,
                                'file_name' => 'image_generated_0',
                                'file_type' => 'png',
                            ],
                        ],
                    ],
                ],
            ]),
        );
    }

    /**
     * Build a fake PNG binary response for the file download.
     *
     * @return Response
     */
    private function image_binary_response(): Response {
        // Minimal valid 1x1 PNG binary.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        return new Response(200, ['Content-Type' => 'image/png'], $png);
    }

    /**
     * Test calculate_size returns correct dimensions for each aspect ratio.
     */
    public function test_calculate_size(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'calculate_size');

        $this->assertEquals('1024x1024', $method->invoke($processor, 'square'));
        $this->assertEquals('1024x1792', $method->invoke($processor, 'portrait'));
        $this->assertEquals('1792x1024', $method->invoke($processor, 'landscape'));
    }

    /**
     * Test calculate_size throws on invalid ratio.
     */
    public function test_calculate_size_invalid(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'calculate_size');

        $this->expectException(\coding_exception::class);
        $method->invoke($processor, 'invalid');
    }

    /**
     * Test create_request_object throws coding_exception (not used for agent flow).
     */
    public function test_create_request_object_throws(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'create_request_object');

        $this->expectException(\coding_exception::class);
        $method->invoke($processor);
    }

    /**
     * Test handle_api_success parses the conversation response correctly.
     */
    public function test_handle_api_success(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $this->conversation_response());

        $this->assertTrue($result['success']);
        $this->assertEquals($this->fakefileid, $result['fileid']);
        $this->assertEquals('Here is the generated image.', $result['revisedprompt']);
        $this->assertEquals('mistral-medium-latest', $result['model']);
    }

    /**
     * Test handle_api_success returns error when no file_id found in response.
     */
    public function test_handle_api_success_no_fileid(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'outputs' => [
                    [
                        'type'    => 'message.output',
                        'content' => [
                            ['type' => 'text', 'text' => 'I cannot generate an image.'],
                        ],
                    ],
                ],
            ]),
        );

        $result = $method->invoke($processor, $response);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
    }

    /**
     * Test the API error response handler.
     */
    public function test_handle_api_error(): void {
        $responses = [
            500 => new Response(500, ['Content-Type' => 'application/json']),
            503 => new Response(503, ['Content-Type' => 'application/json']),
            401 => new Response(
                401,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => 'Unauthorized: Invalid API key']),
            ),
            429 => new Response(
                429,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => 'Rate limit reached for requests']),
            ),
        ];

        $expectedmessages = [
            401 => 'Unauthorized: Invalid API key',
            429 => 'Rate limit reached for requests',
        ];

        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        foreach ($responses as $status => $response) {
            $result = $method->invoke($processor, $response);
            $this->assertEquals($status, $result['errorcode']);
            if ($status === 500) {
                $this->assertEquals('Internal Server Error', $result['errormessage']);
            } else if ($status === 503) {
                $this->assertEquals('Service Unavailable', $result['errormessage']);
            } else {
                $this->assertEquals($expectedmessages[$status], $result['errormessage']);
            }
        }
    }

    /**
     * Test prepare_response success.
     */
    public function test_prepare_response_success(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $fs = get_file_storage();
        $fileinfo = [
            'contextid' => \context_user::instance($user->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => 'test.png',
        ];
        $fileobj = $fs->create_file_from_string($fileinfo, 'fakepngcontent');

        $response = [
            'success' => true,
            'revisedprompt' => 'Here is the generated image.',
            'sourceurl' => 'https://api.mistral.ai/v1/files/' . $this->fakefileid . '/content',
            'model' => 'mistral-medium-latest',
            'draftfile' => $fileobj,
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals($response['revisedprompt'], $result->get_response_data()['revisedprompt']);
        $this->assertEquals($response['model'], $result->get_response_data()['model']);
    }

    /**
     * Test prepare_response error.
     */
    public function test_prepare_response_error(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
            'success' => false,
            'errorcode' => 500,
            'error' => 'Internal server error',
            'errormessage' => 'Try again later',
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals($response['errorcode'], $result->get_errorcode());
        $this->assertEquals($response['error'], $result->get_error());
        $this->assertEquals($response['errormessage'], $result->get_errormessage());
    }

    /**
     * Test create_image_agent uses cached agent ID if available.
     */
    public function test_create_image_agent_uses_cache(): void {
        set_config('image_agent_id', $this->fakeagentid, 'aiprovider_mistral');

        $processor = new process_generate_image($this->provider, $this->action);
        $mock = $this->mock_client($processor);

        $method = new \ReflectionMethod($processor, 'create_image_agent');
        $result = $method->invoke($processor);

        $this->assertEquals($this->fakeagentid, $result);
        $this->assertEquals(0, $mock->count());
    }

    /**
     * Test create_image_agent creates and caches a new agent when none is cached.
     */
    public function test_create_image_agent_creates_and_caches(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $this->mock_client($processor, [$this->agent_creation_response()]);

        $method = new \ReflectionMethod($processor, 'create_image_agent');
        $result = $method->invoke($processor);

        $this->assertEquals($this->fakeagentid, $result);
        $this->assertEquals($this->fakeagentid, get_config('aiprovider_mistral', 'image_agent_id'));
    }

    /**
     * Test create_image_agent returns null on API failure.
     */
    public function test_create_image_agent_returns_null_on_failure(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $this->mock_client($processor, [
            new Response(500, ['Content-Type' => 'application/json']),
        ]);

        $method = new \ReflectionMethod($processor, 'create_image_agent');
        $result = $method->invoke($processor);

        $this->assertNull($result);
    }

    /**
     * Test full process flow: agent creation → conversation → file download → draft file.
     */
    public function test_process_success(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->create_action($user->id);

        $processor = new process_generate_image($this->provider, $this->action);
        $this->mock_client($processor, [
            $this->agent_creation_response(),
            $this->conversation_response(),
            $this->image_binary_response(),
        ]);

        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_image', $result->get_actionname());
        $this->assertEquals('Here is the generated image.', $result->get_response_data()['revisedprompt']);
        $this->assertNotEmpty($result->get_response_data()['draftfile']);
    }

    /**
     * Test process returns error when agent creation fails.
     */
    public function test_process_agent_creation_failure(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->create_action($user->id);

        $processor = new process_generate_image($this->provider, $this->action);
        $this->mock_client($processor, [
            new Response(500, ['Content-Type' => 'application/json']),
        ]);

        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals(500, $result->get_errorcode());
    }

    /**
     * Test process returns error when conversation fails with 401.
     */
    public function test_process_conversation_failure(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->create_action($user->id);

        $processor = new process_generate_image($this->provider, $this->action);
        $this->mock_client($processor, [
            $this->agent_creation_response(),
            new Response(
                401,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => 'Unauthorized: Invalid API key']),
            ),
        ]);

        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals(401, $result->get_errorcode());
        $this->assertEquals('Unauthorized: Invalid API key', $result->get_errormessage());
    }

    /**
     * Test that a 404 conversation response clears the cached agent and retries.
     */
    public function test_process_conversation_404_retries_with_new_agent(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->create_action($user->id);

        set_config('image_agent_id', 'stale-agent-id', 'aiprovider_mistral');

        $processor = new process_generate_image($this->provider, $this->action);
        $this->mock_client($processor, [
            new Response(404, ['Content-Type' => 'application/json']), // Stale agent: 404.
            $this->agent_creation_response(), // New agent created.
            $this->conversation_response(), // Retry succeeds.
            $this->image_binary_response(), // File download.
        ]);

        $result = $processor->process();

        $this->assertTrue($result->get_success());
        $this->assertEquals($this->fakeagentid, get_config('aiprovider_mistral', 'image_agent_id'));
    }

    /**
     * Test process method with user rate limiter.
     */
    public function test_process_with_user_rate_limiter(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $clock = $this->mock_clock_with_frozen();

        $provider = $this->manager->create_provider_instance(
            classname: '\aiprovider_mistral\provider',
            name: 'dummy',
            config: [
                'apikey' => 'testapikey123',
                'enableuserratelimit' => true,
                'userratelimit' => 1,
            ],
            actionconfig: [
                \core_ai\aiactions\generate_image::class => [
                    'settings' => [
                        'model' => 'mistral-medium-latest',
                        'endpoint' => 'https://api.mistral.ai/v1/agents',
                    ],
                ],
            ],
        );

        // Case 1: User rate limit has not been reached.
        $this->create_action($user1->id);
        $processor = new process_generate_image($provider, $this->action);
        $this->mock_client($processor, [
            $this->agent_creation_response(),
            $this->conversation_response(),
            $this->image_binary_response(),
        ]);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: User rate limit has been reached - no HTTP calls needed.
        $clock->bump(HOURSECS - 10);
        $this->create_action($user1->id);
        $processor = new process_generate_image($provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals(
            'You have reached the maximum number of AI requests you can make in an hour. Try again later.',
            $result->get_errormessage(),
        );

        // Case 3: Different user - rate limit not reached yet.
        $this->setUser($user2);
        $this->create_action($user2->id);
        $processor = new process_generate_image($provider, $this->action);
        $this->mock_client($processor, [
            $this->conversation_response(), // Agent already cached.
            $this->image_binary_response(),
        ]);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 4: Time window has passed - user rate limit resets.
        $clock->bump(11);
        $this->setUser($user1);
        $this->create_action($user1->id);
        $processor = new process_generate_image($provider, $this->action);
        $this->mock_client($processor, [
            $this->conversation_response(), // Agent still cached.
            $this->image_binary_response(),
        ]);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }

    /**
     * Test process method with global rate limiter.
     */
    public function test_process_with_global_rate_limiter(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $clock = $this->mock_clock_with_frozen();

        $provider = $this->manager->create_provider_instance(
            classname: '\aiprovider_mistral\provider',
            name: 'dummy',
            config: [
                'apikey' => 'testapikey123',
                'enableglobalratelimit' => true,
                'globalratelimit' => 1,
            ],
            actionconfig: [
                \core_ai\aiactions\generate_image::class => [
                    'settings' => [
                        'model' => 'mistral-medium-latest',
                        'endpoint' => 'https://api.mistral.ai/v1/agents',
                    ],
                ],
            ],
        );

        // Case 1: Global rate limit has not been reached.
        $this->create_action($user1->id);
        $processor = new process_generate_image($provider, $this->action);
        $this->mock_client($processor, [
            $this->agent_creation_response(),
            $this->conversation_response(),
            $this->image_binary_response(),
        ]);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: Global rate limit has been reached - no HTTP calls needed.
        $clock->bump(HOURSECS - 10);
        $this->create_action($user1->id);
        $processor = new process_generate_image($provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals(
            'The AI service has reached the maximum number of site-wide requests per hour. Try again later.',
            $result->get_errormessage(),
        );

        // Case 3: Global rate limit also blocks a different user - no HTTP calls needed.
        $this->setUser($user2);
        $this->create_action($user2->id);
        $processor = new process_generate_image($provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());

        // Case 4: Time window has passed - global rate limit resets.
        $clock->bump(11);
        $this->setUser($user1);
        $this->create_action($user1->id);
        $processor = new process_generate_image($provider, $this->action);
        $this->mock_client($processor, [
            $this->conversation_response(), // Agent still cached.
            $this->image_binary_response(),
        ]);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }
}
