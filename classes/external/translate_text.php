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

namespace aiplacement_translate\external;

use aiplacement_translate\utils;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * External API to call translate text action for this placement.
 *
 * @package    aiplacement_translate
 * @copyright  2026 Sumit Negi <sumitnegi.933@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translate_text extends external_api
{

    /**
     * Translate text parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'contextid' => new external_value(
                PARAM_INT,
                'The context ID',
                VALUE_REQUIRED,
            ),
            'prompttext' => new external_value(
                PARAM_RAW,
                'The text content to translate',
                VALUE_REQUIRED,
            ),
            'targetlanguage' => new external_value(
                PARAM_TEXT,
                'The target language for translation',
                VALUE_REQUIRED,
            ),
        ]);
    }

    /**
     * Translate text using the AI placement.
     *
     * @param int $contextid The context ID.
     * @param string $prompttext The text to translate.
     * @param string $targetlanguage The target language name.
     * @return array The translated content.
     */
    public static function execute(
        int $contextid,
        string $prompttext,
        string $targetlanguage
    ): array {
        global $USER;

        // Parameter validation.
        [
            'contextid'      => $contextid,
            'prompttext'     => $prompttext,
            'targetlanguage' => $targetlanguage,
        ] = self::validate_parameters(self::execute_parameters(), [
            'contextid'      => $contextid,
            'prompttext'     => $prompttext,
            'targetlanguage' => $targetlanguage,
        ]);

        // Context validation and permission check.
        $context = \context::instance_by_id($contextid);
        self::validate_context($context);

        if (!utils::is_translate_available($context)) {
            throw new \moodle_exception('notranslate', 'aiplacement_translate');
        }

        // Persist the user's language preference.
        \set_user_preference('aiplacement_translate_language', $targetlanguage);

        // Build the translation prompt.
        // $fullprompt = "Translate the following text to {$targetlanguage}:\n\n{$prompttext}";

        $fullprompt = <<<PROMPT
            Role: You are an expert multilingual linguist.

            Task:
            1. Detect the source language of the text.
            2. If confidence is high, translate it into {$targetlanguage}.
            3. If confidence is low or the text is unclear, ask for clarification instead of guessing.

            Instructions:
            - Do not assume the language incorrectly.
            - Preserve meaning, tone, and intent.
            - Avoid literal translation if it sounds unnatural.
            - Adapt idioms and cultural context appropriately.
            - Do not hallucinate missing meaning.

            Output Format:
            - Detected Language: <language name>
            - Confidence: <high/medium/low>
            - Translation: <translated text OR request for clarification>

            Text:
            {$prompttext}
        PROMPT;

        // Prepare the action.
        $action = new \core_ai\aiactions\generate_text(
            contextid: $contextid,
            userid: $USER->id,
            prompttext: $fullprompt,
        );

        // Send the action to the AI manager.
        $manager = \core\di::get(\core_ai\manager::class);
        try {
            $response = $manager->process_action($action);
        } catch (\Throwable $e) {
            // Core AI response validation can throw (e.g. when a provider reports a
            // connection-level failure with no HTTP status code). Degrade gracefully instead
            // of fataling the AJAX call, since we cannot fix the underlying core/provider bug
            // from this plugin.
            debugging('AI translate action failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success'          => false,
                'generatedcontent' => '',
                'finishreason'     => '',
                'errorcode'        => -1,
                'error'            => get_string('errorprocessingfailed', 'aiplacement_translate'),
                'timecreated'      => time(),
                'prompttext'       => $prompttext,
                'targetlanguage'   => $targetlanguage,
            ];
        }
        $generatedcontent = $response->get_response_data()['generatedcontent'] ?? '';

        // Return the response.
        return [
            'success'         => $response->get_success(),
            'generatedcontent' => \core_external\util::format_text($generatedcontent, FORMAT_PLAIN, $contextid)[0],
            'finishreason'    => $response->get_response_data()['finishreason'] ?? '',
            'errorcode'       => $response->get_errorcode(),
            'error'           => $response->get_errormessage(),
            'timecreated'     => $response->get_timecreated(),
            'prompttext'      => $prompttext,
            'targetlanguage'  => $targetlanguage,
        ];
    }

    /**
     * Translate text return value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns(): external_function_parameters
    {
        return new external_function_parameters([
            'success' => new external_value(
                PARAM_BOOL,
                'Was the request successful',
                VALUE_REQUIRED
            ),
            'timecreated' => new external_value(
                PARAM_INT,
                'The time the request was created',
                VALUE_REQUIRED,
            ),
            'prompttext' => new external_value(
                PARAM_RAW,
                'The original text sent for translation',
                VALUE_REQUIRED,
            ),
            'targetlanguage' => new external_value(
                PARAM_TEXT,
                'The target language used for translation',
                VALUE_DEFAULT,
                '',
            ),
            'generatedcontent' => new external_value(
                PARAM_RAW,
                'The translated text generated by AI.',
                VALUE_DEFAULT,
            ),
            'finishreason' => new external_value(
                PARAM_ALPHAEXT,
                'The reason generation was stopped',
                VALUE_DEFAULT,
                'stop',
            ),
            'errorcode' => new external_value(
                PARAM_INT,
                'Error code if any',
                VALUE_DEFAULT,
                0,
            ),
            'error' => new external_value(
                PARAM_TEXT,
                'Error message if any',
                VALUE_DEFAULT,
                '',
            ),
        ]);
    }
}
