<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_custom_ws_add_page' => array(
        'classname'   => 'local_custom_ws\external',
        'methodname'  => 'add_page',
        'classpath'   => 'local/custom_ws/classes/external.php',
        'description' => 'Add a new page module instance to a course section.',
        'type'        => 'write',
        'ajax'        => false,
    ),
    'local_custom_ws_update_section' => array(
        'classname'   => 'local_custom_ws\external',
        'methodname'  => 'update_section',
        'classpath'   => 'local/custom_ws/classes/external.php',
        'description' => 'Update section title and summary.',
        'type'        => 'write',
        'ajax'        => false,
    ),
    'local_custom_ws_add_lesson_page' => array(
        'classname'   => 'local_custom_ws\external',
        'methodname'  => 'add_lesson_page',
        'classpath'   => 'local/custom_ws/classes/external.php',
        'description' => 'Add a content page to an existing lesson.',
        'type'        => 'write',
        'ajax'        => false,
    ),
    'local_custom_ws_import_questions' => array(
        'classname'   => 'local_custom_ws\external',
        'methodname'  => 'import_questions',
        'classpath'   => 'local/custom_ws/classes/external.php',
        'description' => 'Import questions from XML format.',
        'type'        => 'write',
        'ajax'        => false,
    ),
    'local_custom_ws_delete_lesson_pages' => array(
        'classname'   => 'local_custom_ws\external',
        'methodname'  => 'delete_lesson_pages',
        'classpath'   => 'local/custom_ws/classes/external.php',
        'description' => 'Delete all pages of a lesson.',
        'type'        => 'write',
        'ajax'        => false,
    ),
);
