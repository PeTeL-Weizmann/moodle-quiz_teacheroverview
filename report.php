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
 * This file defines the quiz overview report class.
 *
 * @package   quiz_teacheroverview
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Devlion Moodle Development <service@devlion.co>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/teacheroverview/lib.php');
require_once($CFG->dirroot . '/mod/quiz/report/teacheroverview/overview_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/teacheroverview/overview_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/teacheroverview/overview_table.php');
require_once($CFG->dirroot . '/mod/quiz/report/teacheroverview/classes/output/core_renderer.php');

/**
 * Quiz report subclass for the overview (grades) report.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_teacheroverview_report extends quiz_attempts_report {

    protected $displayfull = true;

    public function display($quiz, $cm, $course) {
        global $DB, $OUTPUT, $PAGE, $CFG;

        if (optional_param('display', 'basic', PARAM_TEXT) == 'basic') {
            $this->displayfull = false;
        }

        $PAGE->requires->css('/mod/quiz/report/teacheroverview/styles/nv.d3.min.css');
        $PAGE->requires->css('/mod/quiz/report/teacheroverview/styles/teacheroverview_toggle.css');

        list($currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins) = $this->init(
                'teacheroverview', 'quiz_teacheroverview_settings_form', $quiz, $cm, $course);

        $options = new quiz_teacheroverview_options('teacheroverview', $quiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);

        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $table = new quiz_teacheroverview_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudentsjoins, $studentsjoins, $questions, $options->get_url());
        $filename = quiz_report_download_filename(get_string('overviewfilename', 'quiz_teacheroverview'),
                $courseshortname, $quiz->name);
        $table->is_downloading($options->download, $filename,
                $courseshortname . ' ' . format_string($quiz->name, true));
        if ($table->is_downloading()) {
            raise_memory_limit(MEMORY_EXTRA);
        }

        $this->hasgroupstudents = false;
        if (!empty($groupstudentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                      FROM {user} u
                    $groupstudentsjoins->joins
                     WHERE $groupstudentsjoins->wheres";
            $this->hasgroupstudents = $DB->record_exists_sql($sql, $groupstudentsjoins->params);
        }
        $hasstudents = false;
        if (!empty($studentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                    FROM {user} u
                    $studentsjoins->joins
                    WHERE $studentsjoins->wheres";
            $hasstudents = $DB->record_exists_sql($sql, $studentsjoins->params);
        }
        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all quiz attempts
            // are accessible, is not a security porblem.
            $allowedjoins = new \core\dml\sql_join();
        }

        $this->course = $course; // Hack to make this available in process_actions.
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudentsjoins, $allowedjoins, $options->get_url());

        // Start output.
        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data.
            $this->print_header_and_tabs($cm, $course, $quiz, $this->mode);
        }

        // Start teacheroverview dashboard here.

        // Block1.
        $block1 = $this->get_max_min_avg_grades($table, $quiz, $allowedjoins);
        $block1->attempts = preg_replace('/[^0-9]/', '', quiz_num_attempt_summary($quiz, $cm, true, $currentgroup));
        // Block4.
        list($enrolleduserscount,
                $usersfinished,
                $usersinprogress,
                $usersnotstarted) = $this->quiz_submissions_stat($quiz, $currentgroup);
        $chartpie = new \core\chart_pie();
        $chartpie->add_series(new \core\chart_series('Grades',
                [$usersfinished, $usersinprogress, $usersnotstarted]));
        $chartpie->set_labels([get_string('submitted', 'quiz_teacheroverview'),
                get_string('notsubmitted', 'quiz_teacheroverview'),
                get_string('notstarted', 'quiz_teacheroverview')]);
        $chartpie->set_doughnut(true);

        $block4 = array();
        // SG - DON'T render chart with moodle chart lib - use D3 in JS.
        $block4['chart'] = "";

        // Encode data for js D3 pie chart.
        $block4j = array(
                array("label" => get_string('submitted', 'quiz_teacheroverview'),
                        "value" => $usersfinished),
                array("label" => get_string('notsubmitted', 'quiz_teacheroverview'),
                        "value" => $usersinprogress),
                array("label" => get_string('notstarted', 'quiz_teacheroverview'),
                        "value" => $usersnotstarted),
        );
        $block4j = json_encode($block4j, JSON_NUMERIC_CHECK);

        // Block3.
        $block3 = array();

        $output = $PAGE->get_renderer('mod_quiz');
        $bands = 10;    // Hardcoded bands count.

        // If Hebrew.
        if (right_to_left()) {
            if ($quiz->grade == 100) {
                $bandwidth = 10; // Hardcoded bandwidth count.
                $labels = [get_string('notsubmitted', 'quiz_teacheroverview'),
                        '55 - 0', '60 - 55', '70 - 60', '80 - 70', '90 - 80', '100 - 90'];
            } else {
                $bandwidth = 1; // Hardcoded bandwidth count.
                $labels = [get_string('notsubmitted', 'quiz_teacheroverview'),
                        '5 - 0', '6 - 5', '7 - 6', '8 - 7', '9 - 8', '10 - 9'];
            }
        } else {
            if ($quiz->grade == 100) {
                $bandwidth = 10; // Hardcoded bandwidth count.
                $labels = [get_string('notsubmitted', 'quiz_teacheroverview'),
                        '0 - 55', '55 - 60', '60 - 70', '70 - 80', '80 - 90', '90 - 100'];
            } else {
                $bandwidth = 1; // Hardcoded bandwidth count.
                $labels = [get_string('notsubmitted', 'quiz_teacheroverview'),
                        '0 - 5', '5 - 6', '6 - 7', '7 - 8', '8 - 9', '9 - 10'];
            }
        }

        if ($DB->record_exists('quiz_grades', array('quiz' => $quiz->id))) {

            $data = quiz_teacheroverview_grade_bands($bandwidth, $bands, $quiz->id, $currentgroup, new \core\dml\sql_join());
            $notsubimtted = [$usersinprogress];     // Add users in progress / not submitted yet.
            $firsthalf = array_slice($data, 0, 5);  // Slice array on two parts.
            $firsthalf = [array_sum($firsthalf)];   // Sum all marks of the first half of the array.
            $secondhalf = array_slice($data, 5);    // Secont part.
            $chartdata = array_merge($notsubimtted, $firsthalf, $secondhalf); // New merged array with data for chart.

            $chart = self::get_chart($labels, $chartdata);
            // SG - DON'T render chart with moodle chart lib - use D3 in JS.
            $block3['chart'] = "";
        } else {
            $chartdata = array(0, 0, 0, 0, 0, 0, 0); // Hardcoded value in case no one passed the quiz.
            $block3['chart'] = "";
        }

        // Encode data for js D3 bar chart.
        $block3j = array(
                array("key" => "keyname", "values" =>
                        array(
                                array("label" => $labels[0],
                                        "value" => $chartdata[0]),
                                array("label" => $labels[1],
                                        "value" => $chartdata[1]),
                                array("label" => $labels[2],
                                        "value" => $chartdata[2]),
                                array("label" => $labels[3],
                                        "value" => $chartdata[3]),
                                array("label" => $labels[4],
                                        "value" => $chartdata[4]),
                                array("label" => $labels[5],
                                        "value" => $chartdata[5]),
                                array("label" => $labels[6],
                                        "value" => $chartdata[6])
                        )
                )
        );
        $block3j = json_encode($block3j, JSON_NUMERIC_CHECK);

        // Block2.
        $block2 = array();
        $block2['questions'] = $this->get_questions_stat($quiz, $currentgroup, $usersfinished);

        // Output dashboard.
        $dashboardcontext = [
                'output' => $OUTPUT,
                'block1' => $block1,
                'block2' => $block2,
                'block3' => $block3,
                'block4' => $block4,
        ];

        if (!$table->is_downloading()) {
            echo $OUTPUT->render_from_template('quiz_teacheroverview/dashboard', $dashboardcontext);
        }

        $groupoutput = '';
        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector if we are not downloading.
            if (!$table->is_downloading()) {
                $d = ($this->displayfull == true) ? 'full' : 'basic';
                $defaulturl = new moodle_url($options->get_url(), array('display' => $d));
                $groupoutput = groups_print_activity_menu($cm, $defaulturl, true);
            }
        }

        $hasquestions = quiz_has_questions($quiz->id);
        if (!$table->is_downloading()) {
            if (!$hasquestions) {
                echo quiz_no_questions_message($quiz, $cm, $this->context);
            } else if (!$hasstudents) {
                echo $OUTPUT->notification(get_string('nostudentsyet'));
            } else if ($currentgroup && !$this->hasgroupstudents) {
                echo $OUTPUT->notification(get_string('nostudentsingroup'));
            }

            // Print the display options.
            $this->form->display();
        }

        $hasstudents = $hasstudents && (!$currentgroup || $this->hasgroupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {
            // Construct the SQL.
            list($fields, $from, $where, $params) = $table->base_sql($allowedjoins);

            $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);

            // Test to see if there are any regraded attempts to be listed.
            $fields .= ", COALESCE((
                                SELECT MAX(qqr.regraded)
                                  FROM {quiz_overview_regrades} qqr
                                 WHERE qqr.questionusageid = quiza.uniqueid
                          ), -1) AS regraded";
            if ($options->onlyregraded) {
                $where .= " AND COALESCE((
                                    SELECT MAX(qqr.regraded)
                                      FROM {quiz_overview_regrades} qqr
                                     WHERE qqr.questionusageid = quiza.uniqueid
                                ), -1) <> -1";
            }
            $table->set_sql($fields, $from, $where, $params);

            $urlbutton = '';
            $namebutton = '';
            $displayfull = true;
            $download = '';

            if (!$table->is_downloading()) {
                if ($this->displayfull) {
                    $urlbutton = new moodle_url($PAGE->url, array('display' => 'basic'));
                    $namebutton = get_string('buttonchangedisplayfull', 'quiz_teacheroverview');
                    $displayfull = true;
                } else {
                    $urlbutton = new moodle_url($PAGE->url, array('display' => 'full'));
                    $namebutton = get_string('buttonchangedisplayfull', 'quiz_teacheroverview');
                    $displayfull = false;
                }

                // Download button.
                $this->baseurl = new moodle_url($PAGE->url);
                $newparams = $this->baseurl->params();
                $newparams['display'] = optional_param('display', 'basic', PARAM_TEXT);
                $newparams['attempts'] = 'enrolled_any';
                $newparams['onlygraded'] = '';
                $newparams['onlygreraded'] = '';

                $download .= '<div>';

                $download .= (new quiz_teacheroverview\output\mod_quiz_teacheroverview_renderer($PAGE,
                    null))->download_dataformat_selector_csv(get_string('downloadas', 'table'),

                $this->baseurl->out_omit_querystring(), 'download', $newparams);
                $download .= '</div>';
            }

            // Buttons.
            $buttons = '';

            if (has_capability('mod/quiz:deleteattempts', $this->context)) {
                $buttons .= '<input type="submit" class="btn btn-secondary m-r-1"
                id="deleteattemptsbuttonui" name="deleteui" value="' .
                get_string('deleteselected', 'quiz_teacheroverview') . '"/>';

                $PAGE->requires->js_amd_inline("
                require(['jquery'], function($) {
                    $('#deleteattemptsbuttonui').click(function(e) {
                        e.preventDefault();
                        $('#deleteattemptsbutton').click();
                    });
                });");

                $buttons .= '<input type="submit" form="attemptsform" class="btn btn-secondary m-r-1"
                    id="closeattemptsbutton" name="closeattempts" value="' .
                    get_string('closeattemptsselected', 'quiz_teacheroverview') . '"/>';
            }

            if (has_capability('mod/quiz:regrade', $this->context)) {
                $buttons .= '<input type="submit" form="attemptsform" class="btn btn-secondary m-r-1" name="regrade" value="' .
                    get_string('regradeselected', 'quiz_teacheroverview') . '"/>';
            }

            if (!$table->is_downloading()) {
                // Chart's filter status block.
                echo $OUTPUT->render_from_template('quiz_teacheroverview/filterstatus',
                [
                    "urlbutton"     => $urlbutton,
                    "namebutton"    => $namebutton,
                    'groupoutput'   => $groupoutput,
                    'download'      => $download,
                    'displayfull'   => $displayfull,
                    'buttons'       => $buttons
                    ]
                );
            }
            // Define table columns.
            $columns = array();
            $headers = array();

            if (!$table->is_downloading() && $options->checkboxcolumn) {
                $columns[] = 'checkbox';
                $headers[] = null;
            }

            $this->add_user_columns($table, $columns, $headers);
            $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);

            $this->add_grade_columns($quiz, $options->usercanseegrades, $columns, $headers, false);

            if (!$table->is_downloading() && has_capability('mod/quiz:regrade', $this->context) &&
                    $this->has_regraded_questions($from, $where, $params)) {
                $columns[] = 'regraded';
                $headers[] = get_string('regrade', 'quiz_teacheroverview');
            }

            if ($options->slotmarks) {
                foreach ($questions as $slot => $question) {
                    // Ignore questions of zero length.
                    $columns[] = 'qsgrade' . $slot;
                    $header = get_string('qbrief', 'quiz', $question->number);
                    if (!$table->is_downloading()) {
                        $header .= '<br />';
                    } else {
                        $header .= ' ';
                    }
                    $header .= '/' . quiz_rescale_grade($question->maxmark, $quiz, 'question');
                    $headers[] = $header;
                }
            }

            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, false);
            $table->set_attribute('class', 'generaltable generalbox grades');

            $table->out($options->pagesize, true);
        }

        if (!$table->is_downloading()) {
            // Output the regrade buttons.
            if (has_capability('mod/quiz:regrade', $this->context)) {
                $regradesneeded = $this->count_question_attempts_needing_regrade(
                        $quiz, $groupstudentsjoins);
                if ($currentgroup) {
                    $a = new stdClass();
                    $a->groupname = groups_get_group_name($currentgroup);
                    $a->coursestudents = get_string('participants');
                    $a->countregradeneeded = $regradesneeded;
                    $regradealldrydolabel =
                            get_string('regradealldrydogroup', 'quiz_teacheroverview', $a);
                    $regradealldrylabel =
                            get_string('regradealldrygroup', 'quiz_teacheroverview', $a);
                    $regradealllabel =
                            get_string('regradeallgroup', 'quiz_teacheroverview', $a);
                } else {
                    $regradealldrydolabel =
                            get_string('regradealldrydo', 'quiz_teacheroverview', $regradesneeded);
                    $regradealldrylabel =
                            get_string('regradealldry', 'quiz_teacheroverview');
                    $regradealllabel =
                            get_string('regradeall', 'quiz_teacheroverview');
                }

                $displayurl = new moodle_url($options->get_url(), array('sesskey' => sesskey()));

                echo '</br>';
                echo '<div class="mdl-align centerbuttons">';
                echo '<form action="' . $displayurl->out_omit_querystring() . '">';
                echo '<div>';
                echo html_writer::input_hidden_params($displayurl);
                echo '<input type="submit" class="btn btn-secondary m-r-1" name="regradeall" value="' . $regradealllabel . '"/>';

                if ($this->displayfull) {
                    echo '<input type="submit" class="btn btn-secondary m-r-1" name="regradealldry" value="' .
                            $regradealldrylabel . '"/>';
                }
                if ($regradesneeded) {
                    echo '<input type="submit" class="btn btn-secondary m-r-1" name="regradealldrydo" value="' .
                            $regradealldrydolabel . '"/>';
                }
                echo '</div>';
                echo '</form>';

                // Send massage to selected users.

                $sendmessagelabel =
                get_string('sendmessage', 'quiz_teacheroverview');

                echo '<input type="button" id="sendmessage" form="participantsform"
                    class="btn btn-secondary m-r-1" name="sendmessage" value="' . $sendmessagelabel . '"/>';

                echo '</div>';
            }
        }

        $options = new stdClass();
        $options->courseid = $course->id;
        $options->noteStateNames = note_get_state_names();
        $options->stateHelpIcon = $OUTPUT->help_icon('publishstate', 'notes');

        $PAGE->requires->js_call_amd('quiz_teacheroverview/participants', 'init', [$options]);

        $PAGE->requires->js_call_amd('quiz_teacheroverview/charts', 'init', array($block3j, $block4j, $quiz->grade));

        return true;
    }

    /**
     * Extends parent function processing any submitted actions.
     *
     * @param object $quiz
     * @param object $cm
     * @param int $currentgroup
     * @param \core\dml\sql_join $groupstudentsjoins (joins, wheres, params)
     * @param \core\dml\sql_join $allowedjoins (joins, wheres, params)
     * @param moodle_url $redirecturl
     */
    protected function process_actions($quiz, $cm, $currentgroup, \core\dml\sql_join $groupstudentsjoins,
            \core\dml\sql_join $allowedjoins, $redirecturl) {
        parent::process_actions($quiz, $cm, $currentgroup, $groupstudentsjoins, $allowedjoins, $redirecturl);

        if (empty($currentgroup) || $this->hasgroupstudents) {
            if (optional_param('regrade', 0, PARAM_BOOL) && confirm_sesskey()) {
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) {
                    $this->start_regrade($quiz, $cm);
                    $this->regrade_attempts($quiz, false, $groupstudentsjoins, $attemptids);
                    $this->finish_regrade($redirecturl);
                }
            }
        }

        if (optional_param('regradeall', 0, PARAM_BOOL) && confirm_sesskey()) {
            $this->start_regrade($quiz, $cm);
            $this->regrade_attempts($quiz, false, $groupstudentsjoins);
            $this->finish_regrade($redirecturl);

        } else if (optional_param('regradealldry', 0, PARAM_BOOL) && confirm_sesskey()) {
            $this->start_regrade($quiz, $cm);
            $this->regrade_attempts($quiz, true, $groupstudentsjoins);
            $this->finish_regrade($redirecturl);

        } else if (optional_param('regradealldrydo', 0, PARAM_BOOL) && confirm_sesskey()) {
            $this->start_regrade($quiz, $cm);
            $this->regrade_attempts_needing_it($quiz, $groupstudentsjoins);
            $this->finish_regrade($redirecturl);
        }
    }

    /**
     * Check necessary capabilities, and start the display of the regrade progress page.
     *
     * @param object $quiz the quiz settings.
     * @param object $cm the cm object for the quiz.
     */
    protected function start_regrade($quiz, $cm) {
        require_capability('mod/quiz:regrade', $this->context);
        $this->print_header_and_tabs($cm, $this->course, $quiz, $this->mode);
    }

    /**
     * Finish displaying the regrade progress page.
     *
     * @param moodle_url $nexturl where to send the user after the regrade.
     * @uses exit. This method never returns.
     */
    protected function finish_regrade($nexturl) {
        global $OUTPUT;
        \core\notification::success(get_string('regradecomplete', 'quiz_teacheroverview'));
        echo $OUTPUT->continue_button($nexturl);
        echo $OUTPUT->footer();
        die();
    }

    /**
     * Unlock the session and allow the regrading process to run in the background.
     */
    protected function unlock_session() {
        \core\session\manager::write_close();
        ignore_user_abort(true);
    }

    /**
     * Regrade a particular quiz attempt. Either for real ($dryrun = false), or
     * as a pretend regrade to see which fractions would change. The outcome is
     * stored in the quiz_teacheroverview_regrades table.
     *
     * Note, $attempt is not upgraded in the database. The caller needs to do that.
     * However, $attempt->sumgrades is updated, if this is not a dry run.
     *
     * @param object $attempt the quiz attempt to regrade.
     * @param bool $dryrun if true, do a pretend regrade, otherwise do it for real.
     * @param array $slots if null, regrade all questions, otherwise, just regrade
     *      the quetsions with those slots.
     */
    protected function regrade_attempt($attempt, $dryrun = false, $slots = null) {
        global $DB;
        // Need more time for a quiz with many questions.
        core_php_time_limit::raise(300);

        $transaction = $DB->start_delegated_transaction();

        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        if (is_null($slots)) {
            $slots = $quba->get_slots();
        }

        $finished = $attempt->state == quiz_attempt::FINISHED;
        foreach ($slots as $slot) {
            $qqr = new stdClass();
            $qqr->oldfraction = $quba->get_question_fraction($slot);

            $quba->regrade_question($slot, $finished);

            $qqr->newfraction = $quba->get_question_fraction($slot);

            if (abs($qqr->oldfraction - $qqr->newfraction) > 1e-7) {
                $qqr->questionusageid = $quba->get_id();
                $qqr->slot = $slot;
                $qqr->regraded = empty($dryrun);
                $qqr->timemodified = time();
                $DB->insert_record('quiz_overview_regrades', $qqr, false);
            }
        }

        if (!$dryrun) {
            question_engine::save_questions_usage_by_activity($quba);
        }

        $transaction->allow_commit();

        // Really, PHP should not need this hint, but without this, we just run out of memory.
        $quba = null;
        $transaction = null;
        gc_collect_cycles();
    }

    /**
     * Regrade attempts for this quiz, exactly which attempts are regraded is
     * controlled by the parameters.
     *
     * @param object $quiz the quiz settings.
     * @param bool $dryrun if true, do a pretend regrade, otherwise do it for real.
     * @param \core\dml\sql_join|array $groupstudentsjoins empty for all attempts, otherwise regrade attempts
     * for these users.
     * @param array $attemptids blank for all attempts, otherwise only regrade
     * attempts whose id is in this list.
     */
    protected function regrade_attempts($quiz, $dryrun = false,
            \core\dml\sql_join $groupstudentsjoins = null, $attemptids = array()) {
        global $DB;
        $this->unlock_session();

        $sql = "SELECT quiza.*
                  FROM {quiz_attempts} quiza";
        $where = "quiz = :qid AND preview = 0";
        $params = array('qid' => $quiz->id);

        if ($this->hasgroupstudents && !empty($groupstudentsjoins->joins)) {
            $sql .= "\nJOIN {user} u ON u.id = quiza.userid
                    {$groupstudentsjoins->joins}";
            $where .= " AND {$groupstudentsjoins->wheres}";
            $params += $groupstudentsjoins->params;
        }

        if ($attemptids) {
            $aids = join(',', $attemptids);
            $where .= " AND quiza.id IN ({$aids})";
        }

        $sql .= "\nWHERE {$where}";
        $attempts = $DB->get_records_sql($sql, $params);
        if (!$attempts) {
            return;
        }

        $this->clear_regrade_table($quiz, $groupstudentsjoins);

        $progressbar = new progress_bar('quiz_overview_regrade', 500, true);
        $a = array(
                'count' => count($attempts),
                'done' => 0,
        );
        foreach ($attempts as $attempt) {
            $this->regrade_attempt($attempt, $dryrun);
            $a['done']++;
            $progressbar->update($a['done'], $a['count'],
                    get_string('regradingattemptxofy', 'quiz_teacheroverview', $a));
        }

        if (!$dryrun) {
            $this->update_overall_grades($quiz);
        }
    }

    // PTL-429 close attemps.
    protected function close_attempts($quiz, $cm, $dryrun = false,
            $groupstudents = array(), $attemptids = array()) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/externallib.php");

        $where = "quiz = ? AND preview = 0";
        $params = array($quiz->id);
        // Obtiene los estudiantes del grupo si es que lo hay.
        if ($groupstudents) {
            list($usql, $uparams) = $DB->get_in_or_equal($groupstudents);
            $where .= " AND userid $usql";
            $params = array_merge($params, $uparams);
        }
        // Obtiene los ids de los intentos.
        if ($attemptids) {
            list($asql, $aparams) = $DB->get_in_or_equal($attemptids);
            $where .= " AND id $asql";
            $params = array_merge($params, $aparams);
        }
        // Obtiene los intentos de la BD.
        $attempts = $DB->get_records_select('quiz_attempts', $where, $params);
        if (!$attempts) {
            return;
        }

        foreach ($attempts as $attempt) {
            if ($attempt->state != 'finished') {
                $timestamp = time();
                $transaction = $DB->start_delegated_transaction();
                $attempt->quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
                $attempt->quba->process_all_actions($timestamp);
                $attempt->quba->finish_all_questions($timestamp);

                question_engine::save_questions_usage_by_activity($attempt->quba);

                $attempt->timemodified = $timestamp;
                $attempt->timefinish = $timestamp;
                $attempt->sumgrades = $attempt->quba->get_total_mark();
                $attempt->state = 'finished';
                $DB->update_record('quiz_attempts', $attempt);
                // Get student name.
                // Agregado 11/02/2013.
                $studentid = $attempt->userid;
                $studentwhere = "id = $studentid";
                $students = $DB->get_records_select('user', $studentwhere);
                foreach ($students as $student) {
                    // Agregado para que el mensaje del log no sea mayor a 40 caracteres.
                    $mensaje = '';
                    if ($student->idnumber != null) {
                        $mensaje = $student->idnumber;
                    } else {
                        $nombre = $student->firstname . ' ' . $student->lastname;
                        if (strlen($nombre) > 23) {
                            $mensaje = substr($nombre, 0, 23);
                        } else {
                            $mensaje = $nombre;
                        }
                    }
                    // Log the end of this attempt.
                    // Agregado el 05/02/2013.
                    add_to_log($quiz->course, 'quiz',
                            'close attempt of ' . $mensaje,
                            'review.php?attempt=' . $attempt->id, $quiz->name, $cm->id);
                }
                $transaction->allow_commit();
            } else {
                continue;
            }
        }
    }

    /**
     * Regrade those questions in those attempts that are marked as needing regrading
     * in the quiz_overview_regrades table.
     *
     * @param object $quiz the quiz settings.
     * @param \core\dml\sql_join $groupstudentsjoins empty for all attempts, otherwise regrade attempts
     * for these users.
     */
    protected function regrade_attempts_needing_it($quiz, \core\dml\sql_join $groupstudentsjoins) {
        global $DB;
        $this->unlock_session();

        $join = '{quiz_overview_regrades} qqr ON qqr.questionusageid = quiza.uniqueid';
        $where = "quiza.quiz = :qid AND quiza.preview = 0 AND qqr.regraded = 0";
        $params = array('qid' => $quiz->id);

        // Fetch all attempts that need regrading.
        if ($this->hasgroupstudents && !empty($groupstudentsjoins->joins)) {
            $join .= "\nJOIN {user} u ON u.id = quiza.userid
                    {$groupstudentsjoins->joins}";
            $where .= " AND {$groupstudentsjoins->wheres}";
            $params += $groupstudentsjoins->params;
        }

        $toregrade = $DB->get_recordset_sql("
                SELECT quiza.uniqueid, qqr.slot
                  FROM {quiz_attempts} quiza
                  JOIN $join
                 WHERE $where", $params);

        $attemptquestions = array();
        foreach ($toregrade as $row) {
            $attemptquestions[$row->uniqueid][] = $row->slot;
        }
        $toregrade->close();

        if (!$attemptquestions) {
            return;
        }

        $attempts = $DB->get_records_list('quiz_attempts', 'uniqueid',
                array_keys($attemptquestions));

        $this->clear_regrade_table($quiz, $groupstudentsjoins);

        $progressbar = new progress_bar('quiz_overview_regrade', 500, true);
        $a = array(
                'count' => count($attempts),
                'done' => 0,
        );
        foreach ($attempts as $attempt) {
            $this->regrade_attempt($attempt, false, $attemptquestions[$attempt->uniqueid]);
            $a['done']++;
            $progressbar->update($a['done'], $a['count'],
                    get_string('regradingattemptxofy', 'quiz_teacheroverview', $a));
        }

        $this->update_overall_grades($quiz);
    }

    /**
     * Count the number of attempts in need of a regrade.
     *
     * @param object $quiz the quiz settings.
     * @param \core\dml\sql_join $groupstudentsjoins (joins, wheres, params) If this is given, only data relating
     * to these users is cleared.
     */
    protected function count_question_attempts_needing_regrade($quiz, \core\dml\sql_join $groupstudentsjoins) {
        global $DB;

        $userjoin = '';
        $usertest = '';
        $params = array();
        if ($this->hasgroupstudents) {
            $userjoin = "JOIN {user} u ON u.id = quiza.userid
                    {$groupstudentsjoins->joins}";
            $usertest = "{$groupstudentsjoins->wheres} AND u.id = quiza.userid AND ";
            $params = $groupstudentsjoins->params;
        }

        $params['cquiz'] = $quiz->id;
        $sql = "SELECT COUNT(DISTINCT quiza.id)
                  FROM {quiz_attempts} quiza
                  JOIN {quiz_overview_regrades} qqr ON quiza.uniqueid = qqr.questionusageid
                $userjoin
                 WHERE
                      $usertest
                      quiza.quiz = :cquiz AND
                      quiza.preview = 0 AND
                      qqr.regraded = 0";
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Are there any pending regrades in the table we are going to show?
     *
     * @param string $from tables used by the main query.
     * @param string $where where clause used by the main query.
     * @param array $params required by the SQL.
     * @return bool whether there are pending regrades.
     */
    protected function has_regraded_questions($from, $where, $params) {
        global $DB;
        return $DB->record_exists_sql("
                SELECT 1
                  FROM {$from}
                  JOIN {quiz_overview_regrades} qor ON qor.questionusageid = quiza.uniqueid
                 WHERE {$where}", $params);
    }

    /**
     * Remove all information about pending/complete regrades from the database.
     *
     * @param object $quiz the quiz settings.
     * @param \core\dml\sql_join $groupstudentsjoins (joins, wheres, params). If this is given, only data relating
     * to these users is cleared.
     */
    protected function clear_regrade_table($quiz, \core\dml\sql_join $groupstudentsjoins) {
        global $DB;

        // Fetch all attempts that need regrading.
        $select = "questionusageid IN (
                    SELECT uniqueid
                      FROM {quiz_attempts} quiza";
        $where = "WHERE quiza.quiz = :qid";
        $params = array('qid' => $quiz->id);
        if ($this->hasgroupstudents && !empty($groupstudentsjoins->joins)) {
            $select .= "\nJOIN {user} u ON u.id = quiza.userid
                    {$groupstudentsjoins->joins}";
            $where .= " AND {$groupstudentsjoins->wheres}";
            $params += $groupstudentsjoins->params;
        }
        $select .= "\n$where)";

        $DB->delete_records_select('quiz_overview_regrades', $select, $params);
    }

    /**
     * Update the final grades for all attempts. This method is used following
     * a regrade.
     *
     * @param object $quiz the quiz settings.
     * @param array $userids only update scores for these userids.
     * @param array $attemptids attemptids only update scores for these attempt ids.
     */
    protected function update_overall_grades($quiz) {
        quiz_update_all_attempt_sumgrades($quiz);
        quiz_update_all_final_grades($quiz);
        quiz_update_grades($quiz);
    }

    /**
     * Get the bands configuration for the quiz.
     *
     * This returns the configuration for having between 11 and 20 bars in
     * a chart based on the maximum grade to be given on a quiz. The width of
     * a band is the number of grade points it encapsulates.
     *
     * @param object $quiz The quiz object.
     * @return array Contains the number of bands, and their width.
     */
    public static function get_bands_count_and_width($quiz) {
        $bands = $quiz->grade;

        // Development TODO.
        return [intval($bands), $quiz->grade / $bands];

        while ($bands > 20 || $bands <= 10) {
            if ($bands > 50) {
                $bands /= 5;
            } else if ($bands > 20) {
                $bands /= 2;
            }
            if ($bands < 4) {
                $bands *= 5;
            } else if ($bands <= 10) {
                $bands *= 2;
            }
        }
        // See MDL-34589. Using doubles as array keys causes problems in PHP 5.4, hence the explicit cast to int.
        $bands = (int) ceil($bands);
        return [$bands, $quiz->grade / $bands];
    }

    /**
     * Get the bands labels.
     *
     * @param int $bands The number of bands.
     * @param int $bandwidth The band width.
     * @param object $quiz The quiz object.
     * @return string[] The labels.
     */
    public static function get_bands_labels($bands, $bandwidth, $quiz) {
        $bandlabels = [];

        $coefficient = 1;
        if ($bands > 0) {
            $coefficient = 100 / $bands;
        }

        for ($i = 1; $i <= $bands; $i++) {
            $bandlabels[] = quiz_format_grade($quiz, ($i - 1) * $bandwidth * $coefficient) .
                    ' - ' . quiz_format_grade($quiz, $i * $bandwidth * $coefficient);
        }
        return $bandlabels;
    }

    public static function get_converted_labels_and_data($data, $quiz) {

        list($bands, $bandwidth) = self::get_bands_count_and_width($quiz);

        if ($bands != 100) {
            $labels = self::get_bands_labels($bands, $bandwidth, $quiz);
            return [$labels, $data];
        }

        // If $bands == 100.
        $bandsnew = $bands / 10;
        $newlabels = self::get_bands_labels($bandsnew, $bandwidth, $quiz);

        $chunkdata = array_chunk($data, 10);

        $newdata = array();
        foreach ($chunkdata as $item) {
            $sum = 0;
            foreach ($item as $val) {
                $sum += $val;
            }
            $newdata[] = $sum;
        }

        return [$newlabels, $newdata];
    }

    /**
     * Get a chart.
     *
     * @param string[] $labels Chart labels.
     * @param int[] $data The data.
     * @return \core\chart_base
     */
    protected static function get_chart($labels, $data) {
        $chart = new \core\chart_bar();
        $chart->set_labels($labels);
        $chart->get_xaxis(0, true)->set_label(get_string('grade'));

        $yaxis = $chart->get_yaxis(0, true);
        $yaxis->set_label(get_string('participants'));
        $yaxis->set_stepsize(max(1, round(max($data) / 10)));

        $series = new \core\chart_series(get_string('participants'), $data);
        $chart->add_series($series);
        return $chart;
    }

    /**
     * Find an average, maximal and minmal grade in report
     *
     * @param $table
     * @param $quiz
     * @param \core\dml\sql_join $usersjoins (joins, wheres, params) for the users to average over.
     * @return stdClass $record - with values
     */
    protected function get_max_min_avg_grades($table, $quiz, \core\dml\sql_join $usersjoins) {
        global $DB;

        list($fields, $from, $where, $params) = $table->base_sql($usersjoins);
        $record = $DB->get_record_sql("
                SELECT AVG(quiza.sumgrades) AS averagegrade, MAX(quiza.sumgrades) AS maxgrade,
                     MIN(quiza.sumgrades) AS mingrade, COUNT(quiza.sumgrades) AS numgrades
                  FROM $from
                 WHERE $where", $params);
        if ($record->numgrades == 0) {
            $record->averagegrade = '-';
            $record->maxgrade = '-';
            $record->mingrade = '-';
        } else {
            $record->averagegrade = round(quiz_rescale_grade($record->averagegrade, $quiz, false), 1);
            $record->maxgrade = round(quiz_rescale_grade($record->maxgrade, $quiz, false));
            $record->mingrade = round(quiz_rescale_grade($record->mingrade, $quiz, false));
        }

        $record->str_max_grade = 'max_grade';
        $record->title_max_grade = get_string('max_grade', 'quiz_teacheroverview');

        $record->str_min_grade = 'min_grade';
        $record->title_min_grade = get_string('min_grade', 'quiz_teacheroverview');

        $record->str_attempts_grade = 'attempts';
        $record->title_attempts_grade = get_string('attempts', 'quiz_teacheroverview');

        // If group.
        if (count($params) > 2) {
            $record->str_max_grade = 'max_grade_group';
            $record->title_max_grade = get_string('max_grade_group', 'quiz_teacheroverview');

            $record->str_min_grade = 'min_grade_group';
            $record->title_min_grade = get_string('min_grade_group', 'quiz_teacheroverview');
        }

        return $record;
    }

    /**
     * Compute quiz submissions statistic
     *
     * @param $quiz
     * @param $currentgroup
     * @return array
     */
    protected function quiz_submissions_stat($quiz, $currentgroup) {
        global $DB;

        $enrolleduserscount = count_enrolled_users($this->context,
                array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'), $currentgroup);

        list($usersincoursejoin, $usersincourseparams) = get_enrolled_sql($this->context,
                array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'), $currentgroup);
        $params = array('quizid' => $quiz->id);
        $params = array_merge($params, $usersincourseparams);

        // Count only one finished attempt for one user.
        $usersfinished = $DB->get_record_sql("
            SELECT COUNT(DISTINCT u.id) AS num
            FROM {user} u
            JOIN ($usersincoursejoin) ue on ue.id = u.id
            LEFT JOIN {quiz_attempts} quiza ON quiza.userid = u.id AND quiza.quiz = :quizid
            WHERE quiza.id IS NOT NULL AND quiza.state = 'finished'",
                $params);

        $usersinprogress = $DB->get_record_sql("
            SELECT COUNT(u.id) AS num
            FROM {user} u
            JOIN ($usersincoursejoin) ue on ue.id = u.id
            LEFT JOIN {quiz_attempts} quiza ON quiza.userid = u.id AND quiza.quiz = :quizid
            WHERE quiza.id IS NOT NULL AND quiza.state = 'inprogress'",
                $params);

        $usersnotstarted = $DB->get_record_sql("
            SELECT COUNT(u.id) AS num
            FROM {user} u
            JOIN ($usersincoursejoin) ue on ue.id = u.id
            LEFT JOIN {quiz_attempts} quiza ON quiza.userid = u.id AND quiza.quiz = :quizid
            WHERE quiza.id IS NULL",
                $params);

        return array($enrolleduserscount, $usersfinished->num, $usersinprogress->num, $usersnotstarted->num);
    }

    /**
     * Compute questions statistic
     *
     * @param $quiz
     * @param $currentgroup
     * @param $usersfinished
     * @return array
     */
    protected function get_questions_stat($quiz, $currentgroup, $usersfinished) {
        global $DB, $COURSE;

        // Find questions (slots) for current Quiz.
        $slots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id), null, 'slot, questionid');

        // Count users, that finished this quiz (if empty argument).
        $ufuesql = "
            SELECT COUNT(qa.userid) AS ufue
            FROM {quiz_attempts} qa
            WHERE qa.quiz = :quiz AND qa.preview = 0
        ";
        $usersfinishedunenrolled = $DB->get_record_sql($ufuesql, array('quiz' => $quiz->id));
        $usersfinished = !empty($usersfinished) ? $usersfinished : $usersfinishedunenrolled->ufue;

        list($usersincoursejoin, $usersincourseparams) = get_enrolled_sql($this->context,
                array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'), $currentgroup);

        $questions = array();
        $i = 1; // Question number iterator.
        // Count each questions right answers.
        foreach ($slots as $slot => $slotrecord) {
            $qtype = $DB->get_record_sql("SELECT q.qtype FROM {question} q WHERE q.id = :quid",
                    array('quid' => $slotrecord->questionid))->qtype;
            if ($qtype === 'description') {
                continue;
            } // Skip description questions.

            $qsql = "
                SELECT COUNT(qa.questionid) AS rigthqcount
                FROM {question_attempt_steps} qas
                INNER JOIN (SELECT DISTINCT eu2_u.id FROM {user} eu2_u
                JOIN {user_enrolments} ej4_ue ON ej4_ue.userid = eu2_u.id
                JOIN {enrol} ej4_e ON (ej4_e.id = ej4_ue.enrolid AND ej4_e.courseid = :courseid)
                JOIN {role_assignments} eu3_ra3 ON (eu3_ra3.userid = eu2_u.id AND eu3_ra3.roleid IN (5))
                WHERE eu2_u.deleted = 0) ue on ue.id = qas.userid
                LEFT JOIN {question_attempts} qa ON qas.questionattemptid = qa.id
                LEFT JOIN {quiz_attempts} quiza ON qa.questionusageid = quiza.uniqueid
                LEFT JOIN mdl_groups_members AS gm ON (qas.userid = gm.userid)
                WHERE qa.questionid = :quid
                AND (qas.state = 'gradedright' OR qas.state = 'mangrright') AND qas.userid > 0
                AND (quiza.userid, quiza.attempt) IN
                    (
                        SELECT userid, MAX(attempt) as att
                        FROM mdl_quiz_attempts WHERE state = 'finished' AND quiz = :quiz GROUP BY userid
                    )
                AND qas.fraction = 1
            ";

            if ($currentgroup > 0) {
                $qsql .= " AND gm.groupid = :gmid ";
            }

            $params = array(
                    'quid' => $slotrecord->questionid,
                    'quiz' => $quiz->id,
                    'courseid' => $COURSE->id,
                    'gmid' => $currentgroup
            );
            $params = array_merge($usersincourseparams, $params);

            $questions[$i] = $DB->get_record_sql($qsql, $params);
            $questions[$i]->slot = $i;

            // Compute ratio of correct answers to all answers and define proper badge color.
            $questions[$i]->ratio = $questions[$i]->rigthqcount / $usersfinished;
            if ($questions[$i]->ratio == 1) {
                $questions[$i]->badgecolor = 'green';
            } else if ($questions[$i]->ratio >= 0.5 && $questions[$i]->ratio < 1) {
                $questions[$i]->badgecolor = 'yellow';
            } else if ($questions[$i]->ratio > 0 && $questions[$i]->ratio < 0.5) {
                $questions[$i]->badgecolor = 'red';
            } else if (empty($questions[$i]->ratio)) {
                $questions[$i]->badgecolor = 'grey';
            }

            $i++; // Increment question number.
        }

        // Get only values for mustache.
        $questions = array_values($questions);
        return $questions;
    }

}
