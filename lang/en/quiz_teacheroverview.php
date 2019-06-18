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
 * Strings for component 'quiz_overview', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   quiz_teacheroverview
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Devlion Moodle Development <service@devlion.co>
 */

$string['allattempts'] = 'Show all attempts';
$string['allattemptscontributetograde'] = 'All attempts contribute to final grade for user.';
$string['allstudents'] = 'Show all {$a}';
$string['attemptsonly'] = 'Show {$a} with attempts only';
$string['attemptsprepage'] = 'Attempts shown per page';
$string['deleteselected'] = 'Delete attempts';
$string['done'] = 'Done';
$string['err_failedtodeleteregrades'] = 'Failed to delete calculated attempt grades';
$string['err_failedtorecalculateattemptgrades'] = 'Failed to recalculate attempt grades';
$string['highlightinggraded'] = 'The user attempt that contributes to final grade is highlighted.';
$string['needed'] = 'Needed';
$string['noattemptsonly'] = 'Show / download {$a} with no attempts only';
$string['noattemptstoregrade'] = 'No attempts need regrading';
$string['nogradepermission'] = 'You don\'t have permission to grade this quiz.';
$string['onlyoneattemptallowed'] = 'Only one attempt per user allowed on this quiz.';
$string['optallattempts'] = 'all attempts';
$string['optallstudents'] = 'all {$a} who have or have not attempted the quiz';
$string['optattemptsonly'] = '{$a} who have attempted the quiz';
$string['optnoattemptsonly'] = '{$a} who have not attempted the quiz';
$string['optonlyregradedattempts'] = 'that have been regraded / are marked as needing regrading';
$string['teacheroverview'] = 'Teacher overview report';
$string['overviewdownload'] = 'Overview download';
$string['overviewfilename'] = 'grades';
$string['overviewreport'] = 'Grades report';
$string['overviewreportgraph'] = 'Overall number of students achieving grade ranges';
$string['overviewreportgraphgroup'] = 'Number of students in group \'{$a}\' achieving grade ranges';
$string['pagesize'] = 'Page size';
$string['pluginname'] = 'Teacher overview report';
$string['preferencespage'] = 'Preferences just for this page';
$string['preferencessave'] = 'Show report';
$string['preferencesuser'] = 'Your preferences for this report';
$string['regrade'] = 'Regrade';
$string['regradeall'] = 'Regrade all';
$string['regradealldry'] = 'Dry run a full regrade';
$string['regradealldrydo'] = 'Regrade attempts marked as needing regrading ({$a})';
$string['regradealldrydogroup'] =
        'Regrade attempts ({$a->countregradeneeded}) marked as needing regrading in group \'{$a->groupname}\'';
$string['regradealldrygroup'] = 'Dry run a full regrade for group \'{$a->groupname}\'';
$string['regradeallgroup'] = 'Full regrade for group \'{$a->groupname}\'';
$string['regradecomplete'] = 'Regrade completed successfully';
$string['regradeheader'] = 'Regrading';
$string['regradeselected'] = 'Regrade attempts';
$string['regradingattemptxofy'] = 'Regrading attempt ({$a->done}/{$a->count})';
$string['show'] = 'Show / download';
$string['showattempts'] = 'Only show / download attempts';
$string['showdetailedmarks'] = 'Marks for each question';
$string['showinggraded'] = 'Showing only the attempt graded for each user.';
$string['showinggradedandungraded'] =
        'Showing graded and ungraded attempts for each user. The one attempt for each user that is graded is highlighted. The grading method for this quiz is {$a}.';
$string['showinggradedandungradednew'] = 'The grading method for this quiz is {$a}.';
$string['studentingroup'] = '\'{$a->coursestudent}\' in group \'{$a->groupname}\'';
$string['studentingrouplong'] = '\'{$a->coursestudent}\' in this group';

$string['closeattemptsselected'] = 'Close attempts';
$string['buttonchangedisplayfull'] = 'Full report';
$string['buttonchangedisplaybasic'] = 'Basic report';
$string['sendmessage'] = 'Send a message';

// Teacher overview native.
$string['average_grade'] = 'Average grade';
$string['max_grade'] = 'Max grade';
$string['min_grade'] = 'Min grade';
$string['max_grade_group'] = 'Max grade in group';
$string['min_grade_group'] = 'Min grade in group';
$string['question_details'] = 'Question details';
$string['scores_distribution'] = 'Distribution of scores';
$string['status'] = 'Status';
$string['submitted'] = 'Submitted';
$string['notsubmitted'] = 'Not submitted';
$string['notstarted'] = 'Not started';
$string['attempts'] = 'Total Attempts';

// Filter status.
$string['table_results'] = 'Results:';
$string['all_results'] = 'All students';
$string['filtered_results'] = 'students "{$a->label}" ({$a->value})';
$string['filtered_results_failed_by_questions'] = 'failed in question {$a}';
$string['filtered_results_succeeded_by_questions'] = 'succeeded in question {$a}';

$string['backreturnto'] = 'Back';
