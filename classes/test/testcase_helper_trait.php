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

namespace aiprovider_mistral\test;

/**
 * Trait for test cases.
 *
 * @package    aiprovider_mistral
 * @copyright  2026 Fondation UNIT <webmaster@unit.eu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait testcase_helper_trait {
    /**
     * Create the provider object.
     *
     * @param string $actionclass The action class to use.
     * @param array $actionconfig Additional action configuration to merge in.
     * @return \core_ai\provider The configured provider instance.
     */
    public function create_provider(
        string $actionclass,
        array $actionconfig = [],
    ): \core_ai\provider {
        global $CFG;
        require_once($CFG->dirroot . '/ai/provider/mistral/lib.php');

        $manager = \core\di::get(\core_ai\manager::class);

        $config = [
            'apikey'                 => 'testapikey123',
            'enableuserratelimit'    => true,
            'userratelimit'          => 1,
            'enableglobalratelimit'  => true,
            'globalratelimit'        => 1,
        ];

        // Choose the correct default endpoint based on the action being tested.
        $isimageaction = $actionclass === \core_ai\aiactions\generate_image::class;
        $defaultendpoint = $isimageaction
            ? AIPROVIDER_MISTRAL_AGENTS_ENDPOINT
            : AIPROVIDER_MISTRAL_CHAT_COMPLETION_ENDPOINT;

        $defaultmodel = $isimageaction
            ? AIPROVIDER_MISTRAL_MEDIUM_MODEL
            : AIPROVIDER_MISTRAL_SMALL_MODEL;

        $defaultactionconfig = [
            $actionclass => [
                'settings' => [
                    'model'    => $defaultmodel,
                    'endpoint' => $defaultendpoint,
                ],
            ],
        ];

        // Merge any additional settings provided by the caller.
        foreach ($actionconfig as $key => $value) {
            $defaultactionconfig[$actionclass]['settings'][$key] = $value;
        }

        return $manager->create_provider_instance(
            classname: '\aiprovider_mistral\provider',
            name: 'dummy',
            config: $config,
            actionconfig: $defaultactionconfig,
        );
    }
}
