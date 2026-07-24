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

namespace aiplacement_translate;

use core_ai\aiactions\generate_text;
use core_ai\manager;

/**
 * Unit tests for aiplacement_translate utils.
 *
 * @package    aiplacement_translate
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiplacement_translate\utils
 */
final class utils_test extends \advanced_testcase {

    /**
     * Set up a course module context with a student enrolled.
     *
     * @return array{context: \context_module, student: \stdClass, teacher: \stdClass}
     */
    private function setup_context(): array {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \context_module::instance($assign->cmid);

        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        return ['context' => $context, 'student' => $student, 'teacher' => $teacher];
    }

    /**
     * Inject a mock AI manager that reports the given action as available/enabled.
     *
     * @param bool $available
     * @param bool $enabled
     * @param bool $hasproviders
     */
    private function mock_manager(bool $available = true, bool $enabled = true, bool $hasproviders = true): void {
        $mock = $this->createMock(manager::class);
        $mock->method('is_action_available')->willReturn($available);
        $mock->method('is_action_enabled')->willReturn($enabled);
        $mock->method('get_providers_for_actions')->willReturn(
            $hasproviders ? [generate_text::class => ['aiprovider_openai']] : [generate_text::class => []]
        );
        \core\di::set(manager::class, fn() => $mock);
    }

    /**
     * Plugin disabled → not available.
     */
    public function test_is_translate_available_plugin_disabled(): void {
        $this->resetAfterTest();
        ['context' => $context, 'student' => $student] = $this->setup_context();
        $this->setUser($student);

        set_config('enabled', 0, 'aiplacement_translate');
        $this->assertFalse(utils::is_translate_available($context));
    }

    /**
     * Plugin enabled but user lacks capability → not available.
     */
    public function test_is_translate_available_no_capability(): void {
        global $DB;
        $this->resetAfterTest();
        ['context' => $context, 'student' => $student] = $this->setup_context();

        set_config('enabled', 1, 'aiplacement_translate');
        $this->mock_manager();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        assign_capability('aiplacement/translate:translate_text', CAP_PROHIBIT, $studentrole->id, $context);
        $this->setUser($student);

        $this->assertFalse(utils::is_translate_available($context));
    }

    /**
     * Action not available in any provider → not available.
     */
    public function test_is_translate_available_action_not_available(): void {
        $this->resetAfterTest();
        ['context' => $context, 'student' => $student] = $this->setup_context();
        $this->setUser($student);

        set_config('enabled', 1, 'aiplacement_translate');
        $this->mock_manager(available: false);

        $this->assertFalse(utils::is_translate_available($context));
    }

    /**
     * Action not enabled for the placement → not available.
     */
    public function test_is_translate_available_action_not_enabled(): void {
        $this->resetAfterTest();
        ['context' => $context, 'student' => $student] = $this->setup_context();
        $this->setUser($student);

        set_config('enabled', 1, 'aiplacement_translate');
        $this->mock_manager(enabled: false);

        $this->assertFalse(utils::is_translate_available($context));
    }

    /**
     * No providers configured → not available.
     */
    public function test_is_translate_available_no_providers(): void {
        $this->resetAfterTest();
        ['context' => $context, 'student' => $student] = $this->setup_context();
        $this->setUser($student);

        set_config('enabled', 1, 'aiplacement_translate');
        $this->mock_manager(hasproviders: false);

        $this->assertFalse(utils::is_translate_available($context));
    }

    /**
     * All conditions met → available.
     */
    public function test_is_translate_available_success(): void {
        $this->resetAfterTest();
        ['context' => $context, 'student' => $student] = $this->setup_context();
        $this->setUser($student);

        set_config('enabled', 1, 'aiplacement_translate');
        $this->mock_manager();

        $this->assertTrue(utils::is_translate_available($context));
    }

    /**
     * Context is not a module context → not available (preflight check fails).
     */
    public function test_is_translate_available_wrong_context_level(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        set_config('enabled', 1, 'aiplacement_translate');
        $this->mock_manager();

        // Course context (not CONTEXT_MODULE) must return false.
        $this->assertFalse(utils::is_translate_available($context));
    }

    /**
     * Default list is returned when no config has been set.
     */
    public function test_get_language_list_defaults(): void {
        $this->resetAfterTest();
        $languages = utils::get_language_list();
        $this->assertEquals(utils::DEFAULT_LANGUAGES, $languages);
    }

    /**
     * Custom list from admin config replaces the defaults.
     */
    public function test_get_language_list_custom(): void {
        $this->resetAfterTest();
        set_config('languages', "Klingon\nElvish\nDothraki", 'aiplacement_translate');
        $this->assertEquals(['Klingon', 'Elvish', 'Dothraki'], utils::get_language_list());
    }

    /**
     * Blank lines in config are silently ignored.
     */
    public function test_get_language_list_blank_lines_ignored(): void {
        $this->resetAfterTest();
        set_config('languages', "\nSwahili\n\nUrdu\n", 'aiplacement_translate');
        $this->assertEquals(['Swahili', 'Urdu'], utils::get_language_list());
    }

    /**
     * Duplicate entries are de-duplicated, keeping only the first occurrence.
     */
    public function test_get_language_list_deduplication(): void {
        $this->resetAfterTest();
        set_config('languages', "Welsh\nWelsh\nBreton", 'aiplacement_translate');
        $this->assertEquals(['Welsh', 'Breton'], utils::get_language_list());
    }

    /**
     * Saving an empty config falls back to the default list.
     */
    public function test_get_language_list_empty_config_falls_back(): void {
        $this->resetAfterTest();
        set_config('languages', '', 'aiplacement_translate');
        $this->assertEquals(utils::DEFAULT_LANGUAGES, utils::get_language_list());
    }

    /**
     * Config containing only whitespace falls back to the default list.
     */
    public function test_get_language_list_whitespace_only_falls_back(): void {
        $this->resetAfterTest();
        set_config('languages', "   \n   \n   ", 'aiplacement_translate');
        $this->assertEquals(utils::DEFAULT_LANGUAGES, utils::get_language_list());
    }
}
