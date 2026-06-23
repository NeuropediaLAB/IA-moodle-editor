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
        require_once($CFG->dirroot . '/course/modlib.php'); // Required for add_moduleinfo()
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
        $moduleinfo->section = $cw->section; // Corrected: Use section number instead of db ID
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
        $cw = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $params['sectionnum']), '*', MUST_EXIST);

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

    public static function add_lesson_parameters() {
        return new external_function_parameters(
            array(
                'courseid'   => new external_value(PARAM_INT, 'ID of the course', VALUE_REQUIRED),
                'sectionnum' => new external_value(PARAM_INT, 'Section number in the course', VALUE_REQUIRED),
                'name'       => new external_value(PARAM_TEXT, 'Name of the lesson', VALUE_REQUIRED),
                'grade'      => new external_value(PARAM_INT, 'Grade for the lesson', VALUE_DEFAULT, 100),
                'maxanswers' => new external_value(PARAM_INT, 'Maximum number of answers', VALUE_DEFAULT, 5),
            )
        );
    }

    public static function add_lesson($courseid, $sectionnum, $name, $grade, $maxanswers) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::add_lesson_parameters(), array(
            'courseid'   => $courseid,
            'sectionnum' => $sectionnum,
            'name'       => $name,
            'grade'      => $grade,
            'maxanswers' => $maxanswers,
        ));

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);
        
        // Ensure section exists
        course_create_sections_if_missing($course, $params['sectionnum']);
        $cw = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $params['sectionnum']), '*', MUST_EXIST);

        $lessonmodule = $DB->get_record('modules', array('name' => 'lesson'), '*', MUST_EXIST);

        // Build module info object
        $moduleinfo = new stdClass();
        $moduleinfo->course = $course->id;
        $moduleinfo->module = $lessonmodule->id;
        $moduleinfo->modulename = 'lesson';
        $moduleinfo->section = $cw->section; // Relative section number
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->completion = 0;

        $moduleinfo->name = $params['name'];
        $moduleinfo->intro = '';
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->practice = 0;
        $moduleinfo->modattempts = 0;
        $moduleinfo->usepassword = 0;
        $moduleinfo->password = '';
        $moduleinfo->dependency = 0;
        $moduleinfo->conditions = serialize((object)array('timespent' => 0, 'completed' => 0, 'gradebetterthan' => 0));
        $moduleinfo->grade = $params['grade'];
        $moduleinfo->custom = 1;
        $moduleinfo->ongoing = 0;
        $moduleinfo->usemaxgrade = 0;
        $moduleinfo->maxanswers = $params['maxanswers'];
        $moduleinfo->maxattempts = 1;
        $moduleinfo->review = 0;
        $moduleinfo->nextpagedefault = 0;
        $moduleinfo->feedback = 0;
        $moduleinfo->minquestions = 0;
        $moduleinfo->maxpages = 1;
        $moduleinfo->timelimit = 0;
        $moduleinfo->retake = 0;
        $moduleinfo->activitylink = 0;
        $moduleinfo->mediafile = '';
        $moduleinfo->mediaheight = 480;
        $moduleinfo->mediawidth = 640;
        $moduleinfo->mediaclose = 0;
        $moduleinfo->slideshow = 0;
        $moduleinfo->width = 640;
        $moduleinfo->height = 480;
        $moduleinfo->bgcolor = '#FFFFFF';
        $moduleinfo->displayleft = 0;
        $moduleinfo->displayleftif = 0;
        $moduleinfo->progressbar = 0;
        $moduleinfo->available = 0;
        $moduleinfo->deadline = 0;
        $moduleinfo->allowofflineattempts = 0;

        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return array(
            'coursemoduleid' => $moduleinfo->coursemodule,
            'instanceid'     => $moduleinfo->instance,
        );
    }

    public static function add_lesson_returns() {
        return new external_single_structure(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
                'instanceid'     => new external_value(PARAM_INT, 'Instance ID (lesson ID)'),
            )
        );
    }

    public static function add_quiz_with_questions_parameters() {
        return new external_function_parameters(
            array(
                'courseid'     => new external_value(PARAM_INT, 'ID of the course', VALUE_REQUIRED),
                'sectionnum'   => new external_value(PARAM_INT, 'Section number in the course', VALUE_REQUIRED),
                'name'         => new external_value(PARAM_TEXT, 'Name of the quiz', VALUE_REQUIRED),
                'categoryname' => new external_value(PARAM_TEXT, 'Name of the question category', VALUE_REQUIRED),
                'numquestions' => new external_value(PARAM_INT, 'Number of questions to add', VALUE_REQUIRED),
            )
        );
    }

    public static function add_quiz_with_questions($courseid, $sectionnum, $name, $categoryname, $numquestions) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::add_quiz_with_questions_parameters(), array(
            'courseid'     => $courseid,
            'sectionnum'   => $sectionnum,
            'name'         => $name,
            'categoryname' => $categoryname,
            'numquestions' => $numquestions,
        ));

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);
        
        // Ensure section exists
        course_create_sections_if_missing($course, $params['sectionnum']);
        $cw = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $params['sectionnum']), '*', MUST_EXIST);

        $quizmodule = $DB->get_record('modules', array('name' => 'quiz'), '*', MUST_EXIST);

        // Build module info object
        $moduleinfo = new stdClass();
        $moduleinfo->course = $course->id;
        $moduleinfo->module = $quizmodule->id;
        $moduleinfo->modulename = 'quiz';
        $moduleinfo->instance = 0;
        $moduleinfo->section = $cw->section;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->completion = 0;

        // Quiz specific fields
        $moduleinfo->name = $params['name'];
        $moduleinfo->intro = '';
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->timeopen = 0;
        $moduleinfo->timeclose = 0;
        $moduleinfo->timelimit = 0;
        $moduleinfo->overduehandling = 'autosubmit';
        $moduleinfo->graceperiod = 0;
        $moduleinfo->preferredbehaviour = 'deferredfeedback';
        $moduleinfo->canredoquestions = 0;
        $moduleinfo->attempts = 0;
        $moduleinfo->grademethod = QUIZ_GRADEHIGHEST;
        $moduleinfo->decimalpoints = 2;
        $moduleinfo->questiondecimalpoints = -1;
        $moduleinfo->attemptonlast = 0;
        $moduleinfo->gradepass = 0;
        $moduleinfo->grade = 10.0;
        $moduleinfo->sumgrades = 0;

        // Default review options
        $moduleinfo->reviewattempt = 69888;
        $moduleinfo->reviewcorrectness = 69888;
        $moduleinfo->reviewmarks = 69888;
        $moduleinfo->reviewspecificfeedback = 69888;
        $moduleinfo->reviewgeneralfeedback = 69888;
        $moduleinfo->reviewrightanswer = 69888;
        $moduleinfo->reviewoverallfeedback = 69888;

        $moduleinfo->questionsperpage = 1;
        $moduleinfo->shuffleanswers = 1;
        // Moodle 4.x: feedbacktext must be an array of associative arrays,
        // NOT an array of plain strings. quiz_add_instance() does $text[$i]['text'].
        $moduleinfo->feedbacktext          = [['text' => '', 'format' => FORMAT_HTML]];
        $moduleinfo->feedbackboundarycount = -1;  // -1 = no grade-boundary feedback entries
        $moduleinfo->feedbackboundaries    = [];

        // Add instance
        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        // Load quiz record
        $quiz = $DB->get_record('quiz', array('id' => $moduleinfo->instance), '*', MUST_EXIST);

        // Find question category by name
        $category = $DB->get_record('question_categories', array('name' => $params['categoryname']));
        if (!$category) {
            $category = $DB->get_record('question_categories', array('contextid' => $context->id, 'name' => $params['categoryname']));
        }
        if (!$category) {
            // Find any category with that name
            $category = $DB->get_record_sql("SELECT * FROM {question_categories} WHERE name = ? ORDER BY id DESC", array($params['categoryname']));
        }

        if ($category) {
            // --- Paso 4: Añadir preguntas al quiz (compatible Moodle 4.x) ---
            // Obtener preguntas de la categoría usando la API de question_bank
            $sql = "SELECT q.id, q.qtype
                      FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                     WHERE qbe.questioncategoryid = ?
                       AND qv.version = (
                           SELECT MAX(v2.version)
                             FROM {question_versions} v2
                            WHERE v2.questionbankentryid = qbe.id
                       )
                       AND q.parent = 0
                       AND q.qtype <> 'random'
                     ORDER BY q.id ASC";
            $questions = $DB->get_records_sql($sql, [$category->id]);

            if (!empty($questions)) {
                // Calcular el slot máximo actual del quiz para no solapar
                $maxslot = (int)$DB->get_field('quiz_slots', 'MAX(slot)', ['quizid' => $quiz->id]);
                $currentslot = $maxslot;
                $currentpage = $maxslot > 0
                    ? (int)$DB->get_field('quiz_slots', 'MAX(page)', ['quizid' => $quiz->id])
                    : 0;

                $count = 0;
                foreach ($questions as $q) {
                    if ($count >= $params['numquestions']) {
                        break;
                    }

                    // Obtener questionbankentryid de la versión más reciente
                    $qbankentry = $DB->get_record_sql(
                        "SELECT qbe.id as qbankentryid, qv.id as versionid
                           FROM {question_versions} qv
                           JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                          WHERE qv.questionid = ?
                          ORDER BY qv.version DESC
                          LIMIT 1",
                        [$q->id]
                    );

                    if (!$qbankentry) {
                        continue; // Pregunta sin entrada en el banco, saltar
                    }

                    $currentslot++;
                    $currentpage++;

                    $slot = new stdClass();
                    $slot->quizid             = $quiz->id;
                    $slot->slot               = $currentslot;
                    $slot->page               = $currentpage;
                    $slot->requireprevious    = 0;
                    $slot->questioncontextid  = $context->id;
                    $slot->maxmark            = 1.0000000;
                    // En Moodle 4.x el slot referencia el question_bank_entry, no el question directamente
                    $slot->questionbankentryid = $qbankentry->qbankentryid;

                    $DB->insert_record('quiz_slots', $slot);
                    $count++;
                }

                // Actualizar sumgrades del quiz
                $sumgrades = (float)$DB->get_field_sql(
                    'SELECT COALESCE(SUM(maxmark), 0) FROM {quiz_slots} WHERE quizid = ?',
                    [$quiz->id]
                );
                $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $quiz->id]);
                quiz_update_sumgrades($quiz);
                quiz_delete_previews($quiz);
            }
        }

        return array(
            'coursemoduleid' => $moduleinfo->coursemodule,
            'instanceid'     => $moduleinfo->instance,
        );
    }

    public static function add_quiz_with_questions_returns() {
        return new external_single_structure(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
                'instanceid'     => new external_value(PARAM_INT, 'Instance ID (quiz ID)'),
            )
        );
    }

    public static function delete_module_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value(PARAM_INT, 'Course module ID', VALUE_REQUIRED),
            )
        );
    }

    public static function delete_module($cmid) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::delete_module_parameters(), array(
            'cmid' => $cmid,
        ));

        $cm = $DB->get_record('course_modules', array('id' => $params['cmid']), '*', MUST_EXIST);
        $context = context_course::instance($cm->course);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        require_once($CFG->dirroot . '/course/lib.php');
        course_delete_module($cm->id);

        return array(
            'status' => true,
        );
    }

    public static function delete_module_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'True if successful'),
            )
        );
    }

    public static function update_quiz_settings_parameters() {
        return new external_function_parameters(
            array(
                'quizid'   => new external_value(PARAM_INT, 'Instance ID of the quiz', VALUE_REQUIRED),
                'grade'    => new external_value(PARAM_FLOAT, 'Maximum grade', VALUE_DEFAULT, null),
                'attempts' => new external_value(PARAM_INT, 'Maximum attempts allowed', VALUE_DEFAULT, null),
            )
        );
    }

    public static function update_quiz_settings($quizid, $grade, $attempts) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::update_quiz_settings_parameters(), array(
            'quizid'   => $quizid,
            'grade'    => $grade,
            'attempts' => $attempts,
        ));

        $quiz = $DB->get_record('quiz', array('id' => $params['quizid']), '*', MUST_EXIST);
        $context = context_course::instance($quiz->course);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $update = new stdClass();
        $update->id = $quiz->id;

        if ($params['grade'] !== null) {
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            if (class_exists('\mod_quiz\quiz_settings')) {
                \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->update_quiz_maximum_grade($params['grade']);
            } else {
                $update->grade = $params['grade'];
                $DB->update_record('quiz', $update);
                quiz_update_grades($quiz);
            }
        }

        if ($params['attempts'] !== null) {
            $update->attempts = $params['attempts'];
            $DB->update_record('quiz', $update);
        }

        return array(
            'status' => true,
        );
    }

    public static function update_quiz_settings_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'True if successful'),
            )
        );
    }
}

