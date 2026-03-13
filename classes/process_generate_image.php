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

use core_ai\ai_image;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class process image generation.
 *
 * @package    aiprovider_mistral
 * @copyright  2026 Fondation UNIT <webmaster@unit.eu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_image extends abstract_processor {
    #[\Override]
    protected function query_ai_api(): array {
        // Create an image generation agent.
        $agentid = $this->create_image_agent();
        if (!$agentid) {
            return \core_ai\error\factory::create(500, 'Failed to create image generation agent.')->get_error_details();
        }

        // Send the prompt to the agent and retrieve the generated file ID.
        $response = $this->start_agent_conversation($agentid);
        if (!$response['success']) {
            return $response;
        }

        // Download the binary from the files endpoint.
        $imagecontent = $this->get_file_content($response['fileid']);
        if (!$imagecontent) {
            return \core_ai\error\factory::create(500, 'Failed to download image from files API.')->get_error_details();
        }

        // Save the image as a Moodle draft.
        $filename = $response['fileid'] . '.png';
        $fileobj  = $this->content_to_file(
            $this->action->get_configuration('userid'),
            $imagecontent,
            $filename
        );

        $response['sourceurl'] = AIPROVIDER_MISTRAL_FILES_ENDPOINT . $response['fileid'] . '/content';
        $response['draftfile'] = $fileobj;
        unset($response['fileid']);

        return $response;
    }

    /**
     * Create an image generation agent and return its ID.
     *
     * The agent ID is cached in Moodle's plugin config to avoid creating
     * a new agent on every request, which would quickly exhaust API rate limits.
     *
     * @return string|null The agent ID, or null on failure.
     */
    private function create_image_agent(): ?string {
        global $CFG;
        require_once($CFG->dirroot . '/ai/provider/mistral/lib.php');

        $cachedagentid = get_config('aiprovider_mistral', 'image_agent_id');
        if (!empty($cachedagentid)) {
            return $cachedagentid;
        }

        $client = $this->client;
        $modelsettings = $this->get_model_settings();

        $body = json_encode([
            'model'           => $this->get_model(),
            'name'            => get_string('action:generate_image:agentname', 'aiprovider_mistral'),
            'description'     => get_string('action:generate_image:agentdescription', 'aiprovider_mistral'),
            'instructions'    => $this->get_system_instruction(),
            'tools'           => [['type' => 'image_generation']],
            'completion_args' => array_filter([
                'temperature' => $modelsettings['temperature'] ?? null,
                'top_p'       => $modelsettings['top_p'] ?? null,
            ], fn($v) => $v !== null),
        ]);

        $request = new Request(
            method: 'POST',
            uri: AIPROVIDER_MISTRAL_AGENTS_ENDPOINT,
            headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            body: $body,
        );
        $request = $this->provider->add_authentication_headers($request);

        try {
            $response = $client->send($request, [\GuzzleHttp\RequestOptions::HTTP_ERRORS => false]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $bodyobj = json_decode($response->getBody()->getContents());
        $agentid = $bodyobj->id ?? null;

        // Cache the agent ID so future requests can reuse it.
        if ($agentid) {
            set_config('image_agent_id', $agentid, 'aiprovider_mistral');
        }

        return $agentid;
    }

    /**
     * Start a conversation with the given agent ID to generate an image.
     *
     * @param string $agentid The Mistral agent ID.
     * @return array Result array with 'success' and 'fileid' keys on success.
     */
    private function start_agent_conversation(string $agentid): array {
        global $CFG;
        require_once($CFG->dirroot . '/ai/provider/mistral/lib.php');

        $client = $this->client;
        $prompt = $this->action->get_configuration('prompttext');
        $size = $this->calculate_size($this->action->get_configuration('aspectratio'));
        $quality = $this->action->get_configuration('quality');
        $style = $this->action->get_configuration('style');

        $enrichedprompt = "{$prompt}. Style: {$style}. Quality: {$quality}. Size: {$size}.";

        $body = json_encode([
            'inputs'   => $enrichedprompt,
            'stream'   => false,
            'agent_id' => $agentid,
        ]);

        $request = new Request(
            method: 'POST',
            uri: AIPROVIDER_MISTRAL_CONVERSATIONS_ENDPOINT,
            headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            body: $body,
        );
        $request = $this->provider->add_authentication_headers($request);

        try {
            $response = $client->send($request, [\GuzzleHttp\RequestOptions::HTTP_ERRORS => false]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return \core_ai\error\factory::create($e->getCode(), $e->getMessage())->get_error_details();
        }

        $status = $response->getStatusCode();

        // If the cached agent no longer exists in Mistral, clear the cache,
        // create a new one, and retry the conversation.
        if ($status === 404) {
            unset_config('image_agent_id', 'aiprovider_mistral');
            $newagentid = $this->create_image_agent();

            if (!$newagentid) {
                return \core_ai\error\factory::create(500, 'Failed to recreate image generation agent.')->get_error_details();
            }

            return $this->start_agent_conversation($newagentid);
        }

        if ($status !== 200) {
            return $this->handle_api_error($response);
        }

        return $this->handle_api_success($response);
    }

    /**
     * Download raw image binary from the Mistral files API.
     *
     * @param string $fileid The Mistral file ID.
     * @return string|null Raw binary content, or null on failure.
     */
    private function get_file_content(string $fileid): ?string {
        global $CFG;
        require_once($CFG->dirroot . '/ai/provider/mistral/lib.php');

        $apikey = $this->provider->config['apikey'];
        $endpoint = AIPROVIDER_MISTRAL_FILES_ENDPOINT . "/{$fileid}/content";

        try {
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$apikey}",
                    'Accept'        => 'application/json',
                ],
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (\Throwable $e) {
            unset($e);
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return $response->getBody()->getContents();
    }

    /**
     * Convert the given aspect ratio to an image size hint for the image generation prompt.
     * Mistral uses Black Forest Labs' Flux model for image generation.
     *
     * @see https://docs.bfl.ai/kontext/kontext_text_to_image for Flux aspect ratio.
     * @param string $ratio The aspect ratio: 'square', 'landscape', or 'portrait'.
     * @return string The WxH size string passed as a hint in the generation prompt.
     * @throws \coding_exception If the aspect ratio is not one of the supported values.
     */
    private function calculate_size(string $ratio): string {
        return match ($ratio) {
            'square'    => '1024x1024',
            'landscape' => '1792x1024',
            'portrait'  => '1024x1792',
            default     => throw new \coding_exception('Invalid aspect ratio: ' . $ratio),
        };
    }

    /**
     * create_request_object() is required by abstract_processor but unused here
     * because query_ai_api() is overridden.
     */
    #[\Override]
    protected function create_request_object(): RequestInterface {
        throw new \coding_exception('create_request_object() is not used for agent-based image generation.');
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode($response->getBody()->getContents());
        $fileid = null;
        $revisedprompt = null;

        foreach (($bodyobj->outputs ?? []) as $output) {
            if (($output->type ?? '') === 'message.output') {
                foreach (($output->content ?? []) as $block) {
                    if (($block->type ?? '') === 'tool_file' && ($block->tool ?? '') === 'image_generation') {
                        $fileid = $block->file_id ?? null;
                    }
                    if (($block->type ?? '') === 'text') {
                        $revisedprompt = $block->text ?? null;
                    }
                }
            }
        }

        if (!$fileid) {
            return \core_ai\error\factory::create(500, 'No image file ID found in agent response.')->get_error_details();
        }

        return [
            'success' => true,
            'fileid' => $fileid,
            'revisedprompt' => trim($revisedprompt ?? ''),
            'model' => $this->get_model(),
        ];
    }

    /**
     * Save raw image binary content as a Moodle draft stored_file.
     *
     * @param int $userid   The user id.
     * @param string $content  Raw binary image content.
     * @param string $filename Filename to use.
     * @return \stored_file
     */
    private function content_to_file(int $userid, string $content, string $filename): \stored_file {
        global $CFG;
        require_once("{$CFG->libdir}/filelib.php");

        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tempdst, $content);

        try {
            $image = new ai_image($tempdst);
            $image->add_watermark()->save();
        } catch (\Throwable $e) {
            // Skip watermarking if failing.
            unset($e);
        }

        $fileinfo = new \stdClass();
        $fileinfo->contextid = \context_user::instance($userid)->id;
        $fileinfo->filearea = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid = file_get_unused_draft_itemid();
        $fileinfo->filepath = '/';
        $fileinfo->filename = $filename;

        $fs = get_file_storage();
        $stored = $fs->create_file_from_string($fileinfo, file_get_contents($tempdst));

        return $stored;
    }
}
