<?php
namespace local_ia_moodle_editor;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use stdClass;
use context_course;
use moodle_exception;

class external extends external_api {

    public static function add_page_parameters() {
        return new external_function_parameters(
            array(
                'courseid'   => new external_value(PARAM_INT, 'ID of the course', VALUE_REQUIRED),
                'sectionnum' => new external_value(PARAM_INT, 'Section number in the course', VALUE_REQUIRED),
                'name'       => new external_value(PARAM_TEXT, 'Name of the page', VALUE_REQUIRED),
                'intro'      => new external_value(PARAM_RAW, 'Intro text (HTML)', VALUE_DEFAULT, ''),
                'content'    => new external_value(PARAM_RAW, 'Page HTML content', VALUE_REQUIRED),
            )
        );
    }

    public static function add_page($courseid, $sectionnum, $name, $intro, $content) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::add_page_parameters(), array(
            'courseid'   => $courseid,
            'sectionnum' => $sectionnum,
            'name'       => $name,
            'intro'      => $intro,
            'content'    => $content,
        ));

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/page/lib.php');

        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);
        
        // Ensure section exists
        course_create_sections_if_missing($course, $params['sectionnum']);
        $cw = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $params['sectionnum']), '*', MUST_EXIST);

        // Get module
        $module = $DB->get_record('modules', array('name' => 'page'), '*', MUST_EXIST);

        // Build module info object
        $moduleinfo = new stdClass();
        $moduleinfo->course = $course->id;
        $moduleinfo->module = $module->id;
        $moduleinfo->modulename = 'page';
        $moduleinfo->instance = 0;
        $moduleinfo->section = $cw->id;
        $moduleinfo->visible = 1;
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->completion = 0;

        // Page specific fields
        $moduleinfo->name = $params['name'];
        $moduleinfo->intro = $params['intro'];
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->content = $params['content'];
        $moduleinfo->contentformat = FORMAT_HTML;
        $moduleinfo->legacyfiles = 0;
        $moduleinfo->display = 0;
        $moduleinfo->displayoptions = serialize(array('printheading' => 0, 'printintro' => 0));

        // Add instance
        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return array(
            'coursemoduleid' => $moduleinfo->coursemodule,
            'instanceid'     => $moduleinfo->instance,
        );
    }

    public static function add_page_returns() {
        return new external_single_structure(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
                'instanceid'     => new external_value(PARAM_INT, 'Instance ID (page ID)'),
            )
        );
    }

    public static function update_section_parameters() {
        return new external_function_parameters(
            array(
                'courseid'   => new external_value(PARAM_INT, 'ID of the course', VALUE_REQUIRED),
                'sectionnum' => new external_value(PARAM_INT, 'Section number in the course', VALUE_REQUIRED),
                'name'       => new external_value(PARAM_TEXT, 'New name of the section', VALUE_DEFAULT, null),
                'summary'    => new external_value(PARAM_RAW, 'New summary of the section (HTML)', VALUE_DEFAULT, null),
            )
        );
    }

    public static function update_section($courseid, $sectionnum, $name, $summary) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::update_section_parameters(), array(
            'courseid'   => $courseid,
            'sectionnum' => $sectionnum,
            'name'       => $name,
            'summary'    => $summary,
        ));

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);
        course_create_sections_if_missing($course, $params['sectionnum']);
        $cw = $DB->get_record('course_sections', array('course' => $params['courseid'], 'section' => $params['sectionnum']), '*', MUST_EXIST);

        $update = new stdClass();
        $update->id = $cw->id;
        if ($params['name'] !== null) {
            $update->name = $params['name'];
        }
        if ($params['summary'] !== null) {
            $update->summary = $params['summary'];
            $update->summaryformat = FORMAT_HTML;
        }

        $DB->update_record('course_sections', $update);
        rebuild_course_cache($params['courseid'], true);

        return array(
            'status' => true,
        );
    }

    public static function update_section_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'True if successful'),
            )
        );
    }

    public static function add_lesson_page_parameters() {
        return new external_function_parameters(
            array(
                'lessonid' => new external_value(PARAM_INT, 'ID of the lesson instance', VALUE_REQUIRED),
                'title'    => new external_value(PARAM_TEXT, 'Title of the lesson page', VALUE_REQUIRED),
                'contents' => new external_value(PARAM_RAW, 'The HTML content of the lesson page', VALUE_REQUIRED),
                'jumpto'   => new external_value(PARAM_INT, 'Jump to page ID (default: -1 for next page)', VALUE_DEFAULT, -1),
            )
        );
    }

    public static function add_lesson_page($lessonid, $title, $contents, $jumpto) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::add_lesson_page_parameters(), array(
            'lessonid' => $lessonid,
            'title'    => $title,
            'contents' => $contents,
            'jumpto'   => $jumpto,
        ));

        $lessonrecord = $DB->get_record('lesson', array('id' => $params['lessonid']), '*', MUST_EXIST);
        $context = \context_module::instance(get_coursemodule_from_instance('lesson', $lessonrecord->id, $lessonrecord->course, false, MUST_EXIST)->id);
        
        self::validate_context($context);
        require_capability('mod/lesson:edit', $context);

        require_once($CFG->dirroot . '/mod/lesson/locallib.php');
        require_once($CFG->dirroot . '/mod/lesson/pagetypes/branchtable.php');

        $lesson = new \lesson($lessonrecord);

        // Find last page to append to the end
        $lastpageid = 0;
        $pages = $lesson->load_all_pages();
        if (!empty($pages)) {
            foreach ($pages as $p) {
                if ($p->nextpageid == 0) {
                    $lastpageid = $p->id;
                    break;
                }
            }
        }

        $properties = new stdClass();
        $properties->title = $params['title'];
        $properties->contents_editor = array(
            'text' => $params['contents'],
            'format' => FORMAT_HTML
        );
        $properties->qtype = 20; // LESSON_PAGE_BRANCHTABLE
        $properties->qoption = 0;
        $properties->layout = 1;
        $properties->display = 1;
        $properties->pageid = $lastpageid;

        $properties->answer_editor = array(0 => 'Siguiente');
        $properties->jumpto = array(0 => $params['jumpto']);

        $newpage = \lesson_page::create($properties, $lesson, $context, $CFG->maxbytes);

        rebuild_course_cache($lessonrecord->course, true);

        return array(
            'pageid' => $newpage->id,
        );
    }

    public static function add_lesson_page_returns() {
        return new external_single_structure(
            array(
                'pageid' => new external_value(PARAM_INT, 'The ID of the newly created lesson page'),
            )
        );
    }

    public static function import_questions_parameters() {
        return new external_function_parameters(
            array(
                'courseid'   => new external_value(PARAM_INT, 'ID of the course', VALUE_REQUIRED),
                'xmlcontent' => new external_value(PARAM_RAW, 'XML content of the questions', VALUE_REQUIRED),
                'categoryid' => new external_value(PARAM_INT, 'ID of the category (0 for default)', VALUE_DEFAULT, 0),
            )
        );
    }

    public static function import_questions($courseid, $xmlcontent, $categoryid) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::import_questions_parameters(), array(
            'courseid'   => $courseid,
            'xmlcontent' => $xmlcontent,
            'categoryid' => $categoryid,
        ));

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/question:add', $context);

        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        if ($params['categoryid'] > 0) {
            $category = $DB->get_record('question_categories', array('id' => $params['categoryid']), '*', MUST_EXIST);
        } else {
            $category = question_make_default_categories(array($context));
            if (!$category) {
                $defaultcat = question_get_default_category($context->id);
                if (!$defaultcat) {
                    throw new moodle_exception('cannotfinddefaultsection', 'question');
                }
                $category = $defaultcat;
            }
        }

        $tempdir = make_temp_directory('questionimport');
        $filename = $tempdir . '/' . uniqid('import', true) . '.xml';
        file_put_contents($filename, $params['xmlcontent']);

        $qformat = new \qformat_xml();
        $qformat->setCategory($category);
        $qformat->setFilename($filename);
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(true);
        $qformat->setContextfromfile(true);
        $qformat->displayprogress = false;

        ob_start();
        try {
            $success = $qformat->importprocess();
            $output = ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            @unlink($filename);
            throw $e;
        }

        @unlink($filename);

        if (!$success) {
            throw new moodle_exception('importquestionsfailed', 'question', '', null, $output);
        }

        $questionids = !empty($qformat->questionids) ? $qformat->questionids : array();

        return array(
            'status'      => true,
            'questionids' => $questionids,
        );
    }

    public static function import_questions_returns() {
        return new external_single_structure(
            array(
                'status'      => new external_value(PARAM_BOOL, 'True if successful'),
                'questionids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Question ID')
                ),
            )
        );
    }

    public static function delete_lesson_pages_parameters() {
        return new external_function_parameters(
            array(
                'lessonid' => new external_value(PARAM_INT, 'ID of the lesson to clear', VALUE_REQUIRED),
            )
        );
    }

    public static function delete_lesson_pages($lessonid) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::delete_lesson_pages_parameters(), array(
            'lessonid' => $lessonid,
        ));

        $lessonrecord = $DB->get_record('lesson', array('id' => $params['lessonid']), '*', MUST_EXIST);
        $context = \context_module::instance(get_coursemodule_from_instance('lesson', $lessonrecord->id, $lessonrecord->course, false, MUST_EXIST)->id);
        
        self::validate_context($context);
        require_capability('mod/lesson:edit', $context);

        require_once($CFG->dirroot . '/mod/lesson/locallib.php');

        $lesson = new \lesson($lessonrecord);
        $pages = $lesson->load_all_pages();
        
        $count = 0;
        if (!empty($pages)) {
            foreach ($pages as $p) {
                $p->delete();
                $count++;
            }
        }

        rebuild_course_cache($lessonrecord->course, true);

        return array(
            'status' => true,
            'deletedcount' => $count,
        );
    }

    public static function delete_lesson_pages_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'True if successful'),
                'deletedcount' => new external_value(PARAM_INT, 'Number of pages deleted'),
            )
        );
    }
}
