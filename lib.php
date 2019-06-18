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
 * Standard plugin entry points of the quiz statistics report.
 *
 * @package   quiz_teacheroverview
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Devlion Moodle Development <service@devlion.co>
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve questiontext files in the question text when they are displayed in this report.
 *
 * @package  quiz_statistics
 * @category files
 * @param context $previewcontext the quiz context
 * @param int $questionid the question id.
 * @param context $filecontext the file (question) context
 * @param string $filecomponent the component the file belongs to.
 * @param string $filearea the file area.
 * @param array $args remaining file args.
 * @param bool $forcedownload.
 * @param array $options additional options affecting the file serving.
 */

function quiz_teacheroverview_grade_bands($bandwidth, $bands, $quizid, $currentgroup, \core\dml\sql_join $usersjoins = null) {
    global $DB;

    if (!is_int($bands)) {
        debugging('$bands passed to quiz_report_grade_bands must be an integer. (' .
                gettype($bands) . ' passed.)', DEBUG_DEVELOPER);
        $bands = (int) $bands;
    }

    if ($usersjoins && !empty($usersjoins->joins)) {
        $userjoin = "JOIN {user} u ON u.id = qg.userid
                {$usersjoins->joins}";
        $usertest = $usersjoins->wheres;
        $params = $usersjoins->params;
    } else {
        $userjoin = '';
        $usertest = '1=1';
        $params = array();
    }

    if ($currentgroup > 0) {
        $sql = "
        SELECT band, COUNT(1)
        FROM (
            SELECT FLOOR (qg.grade / :bandwidth) AS band
              FROM {quiz_grades} AS qg
              LEFT JOIN {groups_members} AS gm ON (qg.userid = gm.userid)
              $userjoin
              WHERE $usertest AND qg.quiz = :quizid AND gm.groupid = :groupid
        ) subquery
        GROUP BY band
        ORDER BY band ";
    } else {
        $sql = "
        SELECT band, COUNT(1)
        FROM (
            SELECT FLOOR(qg.grade / :bandwidth) AS band
              FROM {quiz_grades} qg
            $userjoin
            WHERE $usertest AND qg.quiz = :quizid
        ) subquery
        GROUP BY band
        ORDER BY band ";
    }

    $params['quizid'] = $quizid;
    $params['bandwidth'] = $bandwidth;
    $params['groupid'] = $currentgroup;

    $data = $DB->get_records_sql_menu($sql, $params);

    // We need to create array elements with values 0 at indexes where there is no element.
    $data = $data + array_fill(0, $bands + 1, 0);
    ksort($data);

    // Place the maximum (perfect grade) into the last band i.e. make last
    // band for example 9 <= g <=10 (where 10 is the perfect grade) rather than
    // just 9 <= g <10.
    $data[$bands - 1] += $data[$bands];
    unset($data[$bands]);

    return $data;
}