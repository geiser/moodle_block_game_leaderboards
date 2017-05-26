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
 * Leaderboards block function definitions.
 *
 * @package    block_game_leaderboards
 * @copyright  2016 Loys Henrique Saccomano Gibertoni
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . "/badgeslib.php");

function print_badges($badges, $userid, $profile = false, $external = false) {
        global $USER, $CFG;
        foreach ($badges as $badge) {
            if (!$external) {
                $context = ($badge->type == BADGE_TYPE_SITE) ? context_system::instance() : context_course::instance($badge->courseid);
                $bname = $badge->name;
                $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
            } else {
                $bname = s($badge->assertion->badge->name);
                $imageurl = $badge->imageUrl;
            }

            $name = html_writer::tag('span', $bname, array('class' => 'badge-name'));

            $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'badge-image', 'style'=>'width: 32px; height: 32px;'));
            if (!empty($badge->dateexpire) && $badge->dateexpire < time()) {
                $image .= $this->output->pix_icon('i/expired',
                        get_string('expireddate', 'badges', userdate($badge->dateexpire)),
                        'moodle',
                        array('class' => 'expireimage'));
                $name .= '(' . get_string('expired', 'badges') . ')';
            }

            $download = $status = $push = '';
            if (($userid == $USER->id) && !$profile) {
                $url = new moodle_url('mybadges.php', array('download' => $badge->id, 'hash' => $badge->uniquehash, 'sesskey' => sesskey()));
                $notexpiredbadge = (empty($badge->dateexpire) || $badge->dateexpire > time());
                $backpackexists = badges_user_has_backpack($USER->id);
                if (!empty($CFG->badges_allowexternalbackpack) && $notexpiredbadge && $backpackexists) {
                    $assertion = new moodle_url('/badges/assertion.php', array('b' => $badge->uniquehash));
                    $action = new component_action('click', 'addtobackpack', array('assertion' => $assertion->out(false)));
                    $push = $this->output->action_icon(new moodle_url('#'), new pix_icon('t/backpack', get_string('addtobackpack', 'badges')), $action);
                }

                $download = $this->output->action_icon($url, new pix_icon('t/download', get_string('download')));
                if ($badge->visible) {
                    $url = new moodle_url('mybadges.php', array('hide' => $badge->issuedid, 'sesskey' => sesskey()));
                    $status = $this->output->action_icon($url, new pix_icon('t/hide', get_string('makeprivate', 'badges')));
                } else {
                    $url = new moodle_url('mybadges.php', array('show' => $badge->issuedid, 'sesskey' => sesskey()));
                    $status = $this->output->action_icon($url, new pix_icon('t/show', get_string('makepublic', 'badges')));
                }
            }

            if (!$profile) {
                $url = new moodle_url('badge.php', array('hash' => $badge->uniquehash));
            } else {
                if (!$external) {
                    $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
                } else {
                    $hash = hash('md5', $badge->hostedUrl);
                    $url = new moodle_url('/badges/external.php', array('hash' => $hash, 'user' => $userid));
                }
            }
            $actions = html_writer::tag('div', $push . $download . $status, array('class' => 'badge-actions'));
            $items[] = html_writer::tag('span', $image); //
            //$items[] = html_writer::link($url, $image . $actions . $name, array('title' => $bname));
        }
        $toreturn = " ";
        foreach($items as $item) $toreturn .= $item;
        return $toreturn;
        //return html_writer::alist($items, array('class' => 'badges'));
    }



function get_leaderboard($blockinstanceid, $courseid, $startdate, $enddate, $searchuserid = 0, $limit = 0, $limit_badges = 0, $class_badges) {
	global $DB, $OUTPUT, $PAGE;

	$block_info = $DB->get_record('block_instances', array('id' => $blockinstanceid));
	$block_instance = block_instance('game_leaderboards', $block_info);

	if($block_instance->config->groupmode == NOGROUPS) {
		$leaderboard_users = get_user_leaderboard($block_instance->config->blockinstanceid, $courseid, $startdate, $enddate);
	}
	else if($block_instance->config->groupmode == SEPARATEGROUPS) {
		$leaderboard_users = get_user_leaderboard($block_instance->config->blockinstanceid, $courseid, $startdate, $enddate, isset($block_instance->config->groupingid) ? $block_instance->config->groupingid : 0);
	}
	else {
		$leaderboard_groups = get_group_leaderboard($block_instance->config->blockinstanceid, $courseid, $startdate, $enddate, isset($block_instance->config->groupingid) ? $block_instance->config->groupingid : 0);
	}

	$leaderboard_contents = array();
	$found_userid = null;
	if($block_instance->config->groupmode != VISIBLEGROUPS) { // Show users points

		foreach($leaderboard_users as $userid => $leaderboard_user) {
			$info = $DB->get_record('user', array('id' => $userid));
			$text = '<li>' . $OUTPUT->user_picture($info, array('size' => 32, 'alttext' => false)) . ' ' . $info->firstname . ' ' . $info->lastname . ': ' . $leaderboard_user . ' ' . get_string('configpage_points', 'block_game_leaderboards');

            // Print user badges
            if ($limit_badges != 0) {



	    		if ($courseid == SITEID) {
		    		$badges_courseid = null;
			    }
		    	else {
			    	$badges_courseid = $courseid;
			    }

                $output = $PAGE->get_renderer('core', 'badges');
                $perpage = 0;
                if ($limit_badges > 0) $perpage = $limit_badges;
                if ($user_badges = badges_get_user_badges($userid, $courseid, 0, $perpage)) {


                    //var_dump($user_badges);
                    $text .= print_badges($user_badges, $userid);
		    		//$text .= $output->print_badges_list($user_badges, $userid, true);
                }
            }

			// List user groups if groupmode is separate groups
			if($block_instance->config->groupmode == SEPARATEGROUPS) {
				$groups = groups_get_all_groups($courseid, $userid, isset($block_instance->config->groupingid) ? $block_instance->config->groupingid : 0);
				$group_names = array();
				foreach ($groups as $group) {
					$group_names[] = $group->name;
				}
				sort($group_names);

				$text .= ' (' . implode(', ', $group_names) . ')';
			}

			$text .= '</li>';
			if($searchuserid == $userid) {
				$text = '<b>' . $text . '</b>';
				$found_userid = count($leaderboard_contents);
			}

			$leaderboard_contents[] = $text;
		}
	}
	else { // Show groups points
        foreach($leaderboard_groups as $groupid => $leaderboard_group) {
            $text = '';
            $group = $DB->get_record('groups', array("id"=>$groupid));
            if ($group->picture) {
                $file = 'f2';
                $pcontext = context_course::instance($courseid); 
                $grouppictureurl = moodle_url::make_pluginfile_url($pcontext->id, 'group',
                    'icon', $group->id, '/', $file);
                $grouppictureurl->param('rev', $group->picture);
                $text .= '<img class="grouppicture" src="'.$grouppictureurl.'"'.
                            ' alt="'.s(get_string('group').' '.$group->name).'" title="'.s($group->name).'"/>';
            }
            
			$text = '<li>'. $text. groups_get_group_name($groupid) . ': ' . $leaderboard_group . ' ' . get_string('configpage_points', 'block_game_leaderboards') . '</li>';
			//var_dump($text);
            //die;
			if(groups_is_member($groupid, $searchuserid)) {
				$text = '<b>' . $text . '</b>';
				if(is_null($found_userid)) {
					$found_userid = count($leaderboard_contents);
				}
			}

			$leaderboard_contents[] = $text;
		}
	}

	$leaderboard_size = count($leaderboard_contents);
	$begin = 0;
	if($limit) {
		if(is_null($found_userid)) {
			$found_userid = 0;
		}
		
		$begin = $found_userid - $limit;
		$end = $found_userid + $limit;
		if($begin < 0) {
			$end += -$begin;
			$begin = 0;
			if($end >= $leaderboard_size) {
				$end = $leaderboard_size - 1;
			}
		}
		if($end >= $leaderboard_size) {
			$begin -= $end - $leaderboard_size + 1;
			$end = $leaderboard_size - 1;
			if($begin < 0) {
				$begin = 0;
			}
		}
		$leaderboard_contents = array_splice($leaderboard_contents, $begin, $end - $begin + 1);
	}

	$content = '<ol start="' . ($begin + 1) . '">' . implode($leaderboard_contents) . '</ol>';

	return $content;
}

function get_user_leaderboard($blockinstanceid, $courseid, $startdate, $enddate, $groupingid = 0, $limit = 0) {
    global $DB;

    if($groupingid) {
        $userids = array_keys(groups_get_grouping_members($groupingid));
    }
    else {
        if($courseid == SITEID) {
            $sql = "SELECT DISTINCT(u.id)
                    FROM {role_assignments} a
                        INNER JOIN {user} u ON u.id = a.userid
                    WHERE roleid = :roleid";
            $params['roleid'] = 5;
        }
        else {
            $context = context_course::instance($courseid);
            $sql = "SELECT DISTINCT(u.id)
                    FROM {role_assignments} a
                        INNER JOIN {user} u ON u.id = a.userid
                    WHERE contextid = :contextid
                        AND roleid = :roleid";
            $params['contextid'] = $context->id;
            $params['roleid'] = 5;
        }
        $userids = $DB->get_fieldset_sql($sql, $params);
    }

    $leaderboard = array();
    foreach($userids as $userid) {
        $leaderboard[$userid] = get_period_points($blockinstanceid, $userid, $startdate, $enddate);
    }
    arsort($leaderboard);
 
    if($limit) {
        array_splice($leaderboard, $limit);
    }

    return $leaderboard;
}

function get_group_leaderboard($blockinstanceid, $courseid, $startdate, $enddate, $groupingid = 0, $limit = 0) {
    global $DB;

    $groupids = array_keys(groups_get_all_groups($courseid, 0, $groupingid));

    $leaderboard = array();
    foreach($groupids as $groupid) {
        $leaderboard[$groupid] = get_period_group_points($blockinstanceid, $groupid, $startdate, $enddate);
    }
    arsort($leaderboard);

    if($limit) {
        array_splice($leaderboard, $limit);
    }

    return $leaderboard;
}

function get_period_block_points($blockid, $userid, $startdate, $enddate)
{
	global $DB;
	
	$sql = "SELECT sum(p.points) as points
		FROM
			{points_log} p
		INNER JOIN {logstore_standard_log} l ON p.logid = l.id
		INNER JOIN {points_system} s ON p.pointsystemid = s.id
		WHERE l.userid = :userid
			AND s.blockinstanceid = :blockinstanceid
            AND l.timecreated BETWEEN :startdate AND :enddate
		GROUP BY l.userid";
		
	$params['userid'] = $userid;
	$params['blockinstanceid'] = $blockid;
    $params['startdate'] = $startdate;
    $params['enddate'] = $enddate;

	$points = $DB->get_record_sql($sql, $params);

	if(empty($points))
	{
		$points = new stdClass();
		$points->points = 0;
	}
	
	return $points->points;
}

function get_period_points($blockid, $userid, $startdate, $enddate)
{
	global $DB;

	$points = get_period_block_points($blockid, $userid, $startdate, $enddate);
	
	$links = $DB->get_records('points_link', array('blockinstanceid' => $blockid), '', 'accfromblockinstanceid');
	if(empty($links))
	{
		return $points;
	}
	
	foreach($links as $link)
	{
		$points += get_period_points($link->accfromblockinstanceid, $userid, $startdate, $enddate);
	}
	
	return $points;
}

function get_period_block_group_points($blockid, $groupid, $startdate, $enddate)
{
	global $DB;
	
	$sql = "SELECT sum(l.points)
				FROM {points_group_log} g
					INNER JOIN {points_log} l ON l.id = g.pointslogid
                    INNER JOIN {logstore_standard_log} p ON l.logid = p.id
					INNER JOIN {points_system} s ON s.id = l.pointsystemid
				WHERE g.groupid = :groupid
					AND s.blockinstanceid = :blockinstanceid
                    AND p.timecreated BETWEEN :startdate AND :enddate";
	
	$params['groupid'] = $groupid;
	$params['blockinstanceid'] = $blockid;
    $params['startdate'] = $startdate;
    $params['enddate'] = $enddate;
	
	$grouppoints = $DB->get_field_sql($sql, $params);
	
	return (empty($grouppoints) ? 0 : $grouppoints);
}

function get_period_group_points($blockid, $groupid, $startdate, $enddate)
{
	global $DB;

	$points = get_period_block_group_points($blockid, $groupid, $startdate, $enddate);
	
	$links = $DB->get_records('points_link', array('blockinstanceid' => $blockid), '', 'accfromblockinstanceid');
	if(empty($links))
	{
		return $points;
	}
	
	foreach($links as $link)
	{
		$points += get_period_group_points($link->accfromblockinstanceid, $groupid, $startdate, $enddate);
	}
	
	return $points;
}
