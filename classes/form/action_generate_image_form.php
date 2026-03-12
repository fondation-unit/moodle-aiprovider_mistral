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

namespace aiprovider_mistral\form;

use aiprovider_mistral\aimodel\mistral_base;

/**
 * Generate image action provider settings form.
 *
 * Image generation uses the Mistral agent API flow. The agents
 * and conversations endpoints are fixed constants defined in lib.php.
 *
 * @package    aiprovider_mistral
 * @copyright  2026 Fondation UNIT <webmaster@unit.eu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_generate_image_form extends action_form {
    #[\Override]
    protected function definition(): void {
        global $CFG;
        require_once($CFG->dirroot . '/ai/provider/mistral/lib.php');

        parent::definition();

        $mform = $this->_form;

        $this->add_model_fields(mistral_base::MODEL_TYPE_IMAGE);

        // The image generation endpoint is fixed to the Mistral agents API.
        // We store it as a hidden field so it is persisted in config without exposing it.
        $mform->addElement('hidden', 'endpoint', AIPROVIDER_MISTRAL_AGENTS_ENDPOINT);
        $mform->setType('endpoint', PARAM_URL);

        if ($this->returnurl) {
            $mform->addElement('hidden', 'returnurl', $this->returnurl);
            $mform->setType('returnurl', PARAM_LOCALURL);
        }

        // Add the action class as a hidden field.
        $mform->addElement('hidden', 'action', $this->action);
        $mform->setType('action', PARAM_TEXT);

        // Add the provider class as a hidden field.
        $mform->addElement('hidden', 'provider', $this->providername);
        $mform->setType('provider', PARAM_TEXT);

        // Add the provider id as a hidden field.
        $mform->addElement('hidden', 'providerid', $this->providerid);
        $mform->setType('providerid', PARAM_INT);

        $this->set_data($this->actionconfig);
    }
}
