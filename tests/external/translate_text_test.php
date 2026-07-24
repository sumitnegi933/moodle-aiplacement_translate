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

use core_ai\aiactions\generate_text;
use core_ai\aiactions\responses\response_generate_text;

/**
 * Unit tests for the translate_text external function.
 *
 * @package    aiplacement_translate
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiplacement_translate\external\translate_text
 */
final class translate_text_test extends \advanced_testcase {

    /**
     * Create a course module context and enrol a student.
     *
     * @return array{context: \context_module, student: \stdClass}
     */
    private function setup_module_context(): array {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \context_module::instance($assign->cmid);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        return ['context' => $context, 'student' => $student];
    }

    /**
     * Inject a mock AI manager returning a successful generate_text response.
     *
     * @param string $generatedcontent The generated content to return.
     */
    private function mock_manager_success(string $generatedcontent = 'Translated text'): void {
        $response = new response_generate_text(success: true);
        $response->set_response_data([
            'generatedcontent' => $generatedcontent,
            'finishreason'     => 'stop',
        ]);

        $mock = $this->createMock(\core_ai\manager::class);
        $mock->method('process_action')->willReturn($response);
        $mock->method('is_action_available')->willReturn(true);
        $mock->method('is_action_enabled')->willReturn(true);
        $mock->method('get_providers_for_actions')->willReturn([
            generate_text::class => ['aiprovider_openai'],
        ]);
        \core\di::set(\core_ai\manager::class, fn() => $mock);
    }

    /**
     * Successful translation returns generated content and target language.
     */
    public function test_execute_success(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'aiplacement_translate');
        ['context' => $context, 'student' => $student] = $this->setup_module_context();
        $this->setUser($student);
        $this->mock_manager_success('Hola mundo');

        $_POST['sesskey'] = sesskey();
        $result = \core_external\external_api::call_external_function(
            'aiplacement_translate_translate_text',
            [
                'contextid'      => $context->id,
                'prompttext'     => 'Hello world',
                'targetlanguage' => 'Spanish',
            ],
        );

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertStringContainsString('Hola mundo', $result['data']['generatedcontent']);
        $this->assertEquals('Spanish', $result['data']['targetlanguage']);
    }

    /**
     * A user without the translate capability receives an error.
     */
    public function test_execute_no_capability(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 1, 'aiplacement_translate');
        ['context' => $context, 'student' => $student] = $this->setup_module_context();
        $this->mock_manager_success();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        assign_capability('aiplacement/translate:translate_text', CAP_PROHIBIT, $studentrole->id, $context);
        $this->setUser($student);

        $_POST['sesskey'] = sesskey();
        $result = \core_external\external_api::call_external_function(
            'aiplacement_translate_translate_text',
            [
                'contextid'      => $context->id,
                'prompttext'     => 'Hello',
                'targetlanguage' => 'German',
            ],
        );

        $this->assertTrue($result['error']);
    }

    /**
     * execute_parameters() returns the expected parameter definitions.
     */
    public function test_execute_parameters(): void {
        $params = translate_text::execute_parameters();
        $this->assertInstanceOf(\core_external\external_function_parameters::class, $params);
        $keys = array_keys($params->keys);
        $this->assertContains('contextid', $keys);
        $this->assertContains('prompttext', $keys);
        $this->assertContains('targetlanguage', $keys);
    }

    /**
     * execute_returns() returns the expected return definitions.
     */
    public function test_execute_returns(): void {
        $returns = translate_text::execute_returns();
        $this->assertInstanceOf(\core_external\external_function_parameters::class, $returns);
        $keys = array_keys($returns->keys);
        $this->assertContains('success', $keys);
        $this->assertContains('generatedcontent', $keys);
        $this->assertContains('targetlanguage', $keys);
    }
}
