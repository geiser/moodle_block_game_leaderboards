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
 * Block for displaying earned local badges to users
 *
 * @package    block_game_leaderboards
 * @copyright  2016 Loys Henrique Saccomano Gibertoni
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/badgeslib.php");
require_once($CFG->dirroot . '/blocks/game_leaderboards/lib.php');

class block_game_leaderboards extends block_base {

    public function init() {
        $this->title = get_string('title', 'block_game_leaderboards');
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function has_config() {
        return false;
    }

    public function applicable_formats() {
        return array(
            'all'    => true
        );
    }

    public function specialization() {
		if(isset($this->config)) {
			if(empty($this->config->title)) {
				$this->title = get_string('title', 'block_game_leaderboards');            
			}
			else {
				$this->title = $this->config->title;
			}
		}
	}

    public function get_content() {
        global $USER, $PAGE, $CFG, $DB, $OUTPUT;

        //uglyhack to hide ranking based on the groupname indicated in the title 
        $matches = array(); preg_match("/\(\w+\)/", $this->title, $matches);
        if (user_has_role_assignment($USER->id, 5) && !empty($matches)) {
            if (!$DB->record_exists_sql('SELECT * FROM {groups_members} m
                    INNER JOIN {groups} g ON g.id = m.groupid
                    WHERE g.name = :name AND m.userid = :userid',
                    array('name'=>substr($matches[0],1,-1),
                          'userid'=>$USER->id))) {
                return ;
            }
        }

        $this->content = new stdClass();

        if(isset($this->config->blockinstanceid)) {
            if($this->config->period == 0) { // Daily
                $startdate = mktime(0, 0, 0, date('m'),  date('d'),  date('Y'));
            }
            else if($this->config->period == 1) { // Weekly
                $startdate = mktime(0, 0, 0, date('m'),  date('d') - intval(date('w')),  date('Y'));
            }
            else if($this->config->period == 2) { // Monthly
                $startdate = mktime(0, 0, 0, date('m'),  1,  date('Y'));
            }
            else { // Any
                $startdate = 0;
            }

            $this->content->text = get_leaderboard($this->instance->id, $this->page->course->id, $startdate, time(), $USER->id, $this->config->size, 0);

            $leaderboard_url = new moodle_url('/blocks/game_leaderboards/leaderboard.php', array('courseid' => $this->page->course->id, 'blockinstanceid' => $this->instance->id, 'startdate' => $startdate, 'enddate' => time()));
            $this->content->text .= html_writer::link($leaderboard_url, get_string('block_seeall', 'block_game_leaderboards'));
        }


        //uglyhack to remove what is in parenteses
        if(user_has_role_assignment($USER->id, 5)) {
            // Verificar se é estudante? inverter e colcoar contexto pode ser melhor
            $this->title = preg_replace(array("/\(\w+\)/"), array(""), $this->title);
        }

        return $this->content;
    }
}
