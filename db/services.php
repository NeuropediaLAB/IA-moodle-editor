<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_ia_moodle_editor_add_page' => array(
        'classname'   => 'local_ia_moodle_editor\external',
        'methodname'  => 'add_page',
        'classpath'   => 'local/ia_moodle_editor/classes/external.php',
        'description' => 'Add a new page module instance to a course section.',
        'type'        => 'write',
        'ajax'        => false,
    ),
    'local_ia_moodle_editor_update_section' => array(
        'classname'   => 'local_ia_moodle_editor\external',
        'methodname'  => 'update_section',
        'classpath'   => 'local/ia_moodle_editor/classes/external.php',
        'description' => 'Update section title and summary.',
        'type'        => 'write',
        'ajax'        => false,
    ),
    'local_ia_moodle_editor_add_lesson_page' => array(
        'classname'   => 'local_ia_moodle_editor\external',
        'methodname'  => 'add_lesson_page',
        'classpath'   => 'local/ia_moodle_editor/classes/external.php',
        'description' => 'Add a content page to an existing lesson.',
        'type'        => 'write',
        'ajax'        => false,
    ),
    'local_ia_moodle_editor_import_questions' => array(
        'classname'   => 'local_ia_moodle_editor\external',
        'methodname'  => 'import_questions',
        'classpath'   => 'local/ia_moodle_editor/classes/external.php',
        'description' => 'Import questions from XML format.',
        'type'        => 'write',
        'ajax'        => false,
    ),
    'local_ia_moodle_editor_delete_lesson_pages' => array(
        'classname'   => 'local_ia_moodle_editor\external',
        'methodname'  => 'delete_lesson_pages',
        'classpath'   => 'local/ia_moodle_editor/classes/external.php',
        'description' => 'Delete all pages of a lesson.',
        'type'        => 'write',
        'ajax'        => false,
    ),
);
