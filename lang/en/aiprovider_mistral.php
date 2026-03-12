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

/**
 * Strings for component aiprovider_mistral, language 'en'.
 *
 * @package    aiprovider_mistral
 * @copyright  2026 Fondation UNIT <webmaster@unit.eu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action:explain_text:endpoint'] = 'API endpoint';
$string['action:explain_text:model'] = 'AI model';
$string['action:explain_text:model_help'] = 'The model used to explain the provided text.';
$string['action:explain_text:systeminstruction'] = 'System instruction';
$string['action:explain_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';
$string['action:generate_image:agentdescription'] = 'Agent used to generate images.';
$string['action:generate_image:agentname'] = 'Image Generation Agent';
$string['action:generate_image:endpoint'] = 'API endpoint';
$string['action:generate_image:model'] = 'AI model';
$string['action:generate_image:model_help'] = 'The model used to generate images from user prompts.';
$string['action:generate_text:endpoint'] = 'API endpoint';
$string['action:generate_text:model'] = 'AI model';
$string['action:generate_text:model_help'] = 'The model used to generate the text response.';
$string['action:generate_text:systeminstruction'] = 'System instruction';
$string['action:generate_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';
$string['action:summarise_text:endpoint'] = 'API endpoint';
$string['action:summarise_text:model'] = 'AI model';
$string['action:summarise_text:model_help'] = 'The model used to summarise the provided text.';
$string['action:summarise_text:systeminstruction'] = 'System instruction';
$string['action:summarise_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';
$string['apikey'] = 'Mistral API key';
$string['apikey_help'] = 'Get a key from your <a href="https://admin.mistral.ai/organization/api-keys" target="_blank">Mistral API keys</a>.';
$string['custom_model_name'] = 'Custom model name';
$string['enableglobalratelimit'] = 'Set site-wide rate limit';
$string['enableglobalratelimit_desc'] = 'Limit the number of requests that the Mistral API provider can receive across the entire site every hour.';
$string['enableuserratelimit'] = 'Set user rate limit';
$string['enableuserratelimit_desc'] = 'Limit the number of requests each user can make to the Mistral API provider every hour.';
$string['extraparams'] = 'Extra parameters';
$string['extraparams_help'] = 'Extra parameters can be configured here. We support JSON format. For example:
<pre>
{
    "temperature": 0.5,
    "max_tokens": 100
}
</pre>';
$string['globalratelimit'] = 'Maximum number of site-wide requests';
$string['globalratelimit_desc'] = 'The number of site-wide requests allowed per hour.';
$string['invalidjson'] = 'Invalid JSON string';
$string['orgid'] = 'Mistral organization ID';
$string['orgid_help'] = 'Get your Mistral organization ID from your <a href="https://admin.mistral.ai/organization" target="_blank">Mistral account</a>.';
$string['pluginname'] = 'Mistral API provider';
$string['privacy:metadata'] = 'The Mistral API provider plugin does not store any personal data.';
$string['privacy:metadata:aiprovider_mistral:externalpurpose'] = 'This information is sent to the Mistral API in order for a response to be generated. Your Mistral account settings may change how Mistral stores and retains this data. No user data is explicitly sent to Mistral or stored in Moodle LMS by this plugin.';
$string['privacy:metadata:aiprovider_mistral:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_mistral:numberimages'] = 'When generating images the number of images used in the response.';
$string['privacy:metadata:aiprovider_mistral:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_mistral:responseformat'] = 'The format of the response. When generating images.';
$string['settings'] = 'Settings';
$string['settings_frequency_penalty'] = 'frequency_penalty';
$string['settings_frequency_penalty_help'] = 'The frequency penalty adjusts how often words are repeated. The higher the penalty, the less repetitions in the generated text.';
$string['settings_help'] = 'Adjust the settings below to customise how requests are sent to Mistral.';
$string['userratelimit'] = 'Maximum number of requests per user';
$string['userratelimit_desc'] = 'The number of requests allowed per hour, per user.';
