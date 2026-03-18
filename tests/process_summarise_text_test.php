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
 * Test Summarise text process class for Mistral provider.
 *
 * @package    aiprovider_mistral
 * @copyright  2026 Fondation UNIT <webmaster@unit.eu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_mistral\provider
 * @covers     \aiprovider_mistral\process_summarise_text
 * @covers     \aiprovider_mistral\abstract_processor
 */
final class process_summarise_text_test extends \advanced_testcase {
    use testcase_helper_trait;

    /** @var string A successful response in JSON format. */
    protected string $responsebodyjson;

    /** @var \core_ai\manager */
    private $manager;

    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->responsebodyjson = file_get_contents(
            __DIR__ . '/fixtures/text_request_success.json'
        );

        $this->manager = \core\di::get(\core_ai\manager::class);
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\summarise_text::class,
        );
        $this->create_action();
    }

    /**
     * Create the action object.
     *
     * @param int $userid The user id to use in the action.
     */
    private function create_action(int $userid = 1): void {
        $this->action = new \core_ai\aiactions\summarise_text(
            contextid: 1,
            userid: $userid,
            prompttext: 'This is a test prompt',
        );
    }

    /**
     * Create a mocked Guzzle client and inject it via reflection into the processor.
     *
     * Mistral uses raw GuzzleHttp\Client, so we use GuzzleHttp's MockHandler to intercept requests.
     * The $client property is defined on abstract_processor, so we reflect on that class.
     *
     * @param process_summarise_text $processor The processor to inject the mock into.
     * @return MockHandler The mock handler to append responses to.
     */
    private function mock_guzzle_client(process_summarise_text $processor): MockHandler {
        $mock = new MockHandler();
        $handlerstack = HandlerStack::create($mock);
        $client = new \GuzzleHttp\Client(['handler' => $handlerstack]);

        // Inject the mocked client into the processor via reflection.
        $reflection = new \ReflectionClass(abstract_processor::class);
        $property = $reflection->getProperty('client');
        $property->setValue($processor, $client);

        return $mock;
    }

    /**
     * Test create_request_object uses the core summarise_text system instruction.
     */
    public function test_create_request_object(): void {
        $processor = new process_summarise_text($this->provider, $this->action);

        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $body = json_decode($request->getBody()->getContents());

        $this->assertEquals('system', $body->messages[0]->role);
        $this->assertEquals(get_string('action_summarise_text_instruction', 'core_ai'), $body->messages[0]->content);
        $this->assertEquals('This is a test prompt', $body->messages[1]->content);
        $this->assertEquals('user', $body->messages[1]->role);
    }

    /**
     * Test create_request_object with extra model settings.
     */
    public function test_create_request_object_with_model_settings(): void {
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\summarise_text::class,
            actionconfig: [
                'temperature' => '0.5',
                'max_tokens' => '100',
            ],
        );
        $processor = new process_summarise_text($this->provider, $this->action);

        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $body = json_decode($request->getBody()->getContents());

        $this->assertEquals('0.5', $body->temperature);
        $this->assertEquals('100', $body->max_tokens);

        // Test with modelextraparams JSON.
        $this->provider = $this->create_provider(
            actionclass: \core_ai\aiactions\summarise_text::class,
            actionconfig: [
                'model' => 'mistral-medium-latest',
                'modelextraparams' => '{"temperature": 0.5, "max_tokens": 100}',
            ],
        );
        $processor = new process_summarise_text($this->provider, $this->action);

        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $body = json_decode($request->getBody()->getContents());

        $this->assertEquals('mistral-medium-latest', $body->model);
        $this->assertEquals('0.5', $body->temperature);
        $this->assertEquals('100', $body->max_tokens);
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
            404 => new Response(
                404,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => 'Not found']),
            ),
            429 => new Response(
                429,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => 'Rate limit reached for requests']),
            ),
        ];

        $expectedmessages = [
            401 => 'Unauthorized: Invalid API key',
            404 => 'Not found',
            429 => 'Rate limit reached for requests',
        ];

        $processor = new process_summarise_text($this->provider, $this->action);
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
     * Test the API success response handler.
     */
    public function test_handle_api_success(): void {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            $this->responsebodyjson,
        );

        $processor = new process_summarise_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $response);

        $this->assertTrue($result['success']);
        $this->assertEquals('cmpl-mistral-abc123', $result['id']);
        $this->assertStringContainsString('Sure, here is some sample text', $result['generatedcontent']);
        $this->assertEquals('stop', $result['finishreason']);
        $this->assertEquals('11', $result['prompttokens']);
        $this->assertEquals('568', $result['completiontokens']);
        $this->assertEquals('mistral-small-latest', $result['model']);
    }

    /**
     * Test prepare_response success.
     */
    public function test_prepare_response_success(): void {
        $processor = new process_summarise_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
            'success' => true,
            'id' => 'cmpl-mistral-abc123',
            'generatedcontent' => 'Sure, here is some sample text',
            'finishreason' => 'stop',
            'prompttokens' => '11',
            'completiontokens' => '568',
            'model' => 'mistral-small-latest',
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('summarise_text', $result->get_actionname());
        $this->assertEquals($response['generatedcontent'], $result->get_response_data()['generatedcontent']);
        $this->assertEquals($response['model'], $result->get_response_data()['model']);
    }

    /**
     * Test prepare_response error.
     */
    public function test_prepare_response_error(): void {
        $processor = new process_summarise_text($this->provider, $this->action);
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
        $this->assertEquals('summarise_text', $result->get_actionname());
        $this->assertEquals($response['errorcode'], $result->get_errorcode());
        $this->assertEquals($response['error'], $result->get_error());
        $this->assertEquals($response['errormessage'], $result->get_errormessage());
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
                \core_ai\aiactions\summarise_text::class => [
                    'settings' => [
                        'model' => 'mistral-small-latest',
                        'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
                    ],
                ],
            ],
        );

        // Case 1: User rate limit has not been reached.
        $this->create_action($user1->id);
        $processor = new process_summarise_text($provider, $this->action);
        $mock = $this->mock_guzzle_client($processor);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: User rate limit has been reached.
        $clock->bump(HOURSECS - 10);
        $this->create_action($user1->id);
        $processor = new process_summarise_text($provider, $this->action);
        $mock = $this->mock_guzzle_client($processor);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));
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
        $processor = new process_summarise_text($provider, $this->action);
        $mock = $this->mock_guzzle_client($processor);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 4: Time window has passed - user rate limit resets.
        $clock->bump(11);
        $this->setUser($user1);
        $this->create_action($user1->id);
        $processor = new process_summarise_text($provider, $this->action);
        $mock = $this->mock_guzzle_client($processor);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));
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
                \core_ai\aiactions\summarise_text::class => [
                    'settings' => [
                        'model' => 'mistral-small-latest',
                        'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
                    ],
                ],
            ],
        );

        // Case 1: Global rate limit has not been reached.
        $this->create_action($user1->id);
        $processor = new process_summarise_text($provider, $this->action);
        $mock = $this->mock_guzzle_client($processor);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: Global rate limit has been reached.
        $clock->bump(HOURSECS - 10);
        $this->create_action($user1->id);
        $processor = new process_summarise_text($provider, $this->action);
        $mock = $this->mock_guzzle_client($processor);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals(
            'The AI service has reached the maximum number of site-wide requests per hour. Try again later.',
            $result->get_errormessage(),
        );

        // Case 3: Global rate limit also blocks a different user.
        $this->setUser($user2);
        $this->create_action($user2->id);
        $processor = new process_summarise_text($provider, $this->action);
        $mock = $this->mock_guzzle_client($processor);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));
        $result = $processor->process();
        $this->assertFalse($result->get_success());

        // Case 4: Time window has passed - global rate limit resets.
        $clock->bump(11);
        $this->setUser($user1);
        $this->create_action($user1->id);
        $processor = new process_summarise_text($provider, $this->action);
        $mock = $this->mock_guzzle_client($processor);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }
}
