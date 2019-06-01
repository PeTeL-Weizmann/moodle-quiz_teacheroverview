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

namespace quiz_teacheroverview\output;

use moodle_url;
use core_plugin_manager;

defined('MOODLE_INTERNAL') || die;

/**
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    quiz_teacheroverview
 * @copyright  2012 Bas Brands, www.basbrands.nl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Devlion Moodle Development <service@devlion.co> 
 */

class mod_quiz_teacheroverview_renderer extends \core_renderer {

    /**
     * Returns a dataformat selection (CSV by default) and download form
     *
     * @param string $label A text label
     * @param moodle_url|string $base The download page url
     * @param string $name The query param which will hold the type of the download
     * @param array $params Extra params sent to the download page
     * @return string HTML fragment
     */
    public function download_dataformat_selector_csv($label, $base, $name = 'dataformat', $params = array()) {
        $formats = core_plugin_manager::instance()->get_plugins_of_type('dataformat');
        $options = array();
        foreach ($formats as $format) {
            if ($format->is_enabled()) {
                $options[] = array(
                    'value' => $format->name,
                    'label' => get_string('dataformat', $format->component),
                    'selected' =>  $format->name == 'csv' ? 'selected' : '',
                );
            }
        }
        $hiddenparams = array();
        foreach ($params as $key => $value) {
            $hiddenparams[] = array(
                'name' => $key,
                'value' => $value,
            );
        }
        $data = array(
            'label' => $label,
            'base' => $base,
            'name' => $name,
            'params' => $hiddenparams,
            'options' => $options,
            'sesskey' => sesskey(),
            'submit' => get_string('download'),
        );

        return $this->render_from_template('quiz_teacheroverview/dataformat_selector_csv', $data);
    }

}
