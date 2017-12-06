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
 * Prints a particular instance of sassessment
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_sassessment
 * @copyright  2014 Igor Nikulin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/// (Replace sassessment with the name of your module and remove this line)
//$time_start = microtime(true);
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once $CFG->dirroot . '/grade/lib.php';
require_once $CFG->dirroot . '/grade/report/lib.php';

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$a = optional_param('a', 'list', PARAM_TEXT);
$act = optional_param('act', NULL, PARAM_TEXT);
$n = optional_param('n', 0, PARAM_INT);
$upid = optional_param('upid', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('sassessment', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $sassessment = $DB->get_record('sassessment', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $sassessment = $DB->get_record('sassessment', array('id' => $n), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $sassessment->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('sassessment', $sassessment->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

//add_to_log($course->id, 'sassessment', 'view', "view.php?id={$cm->id}", $sassessment->name, $cm->id);

$frm = data_submitted();

/*
* Adding mew item
*/
if ($a == "add" && isset($frm->useranswer) && is_array($frm->useranswer)) {
    if ($sassessment->grademethod == "default")
        if (!$catdata = $DB->get_record("grade_items", array("courseid" => $sassessment->course, "iteminstance" => $sassessment->id, "itemmodule" => 'sassessment')))
            sassessment_grade_item_update($sassessment);

    $add = new stdClass;
    $add->aid = $sassessment->id;
    $add->uid = $USER->id;
    $add->summary = $frm->summary;

    $text = "";

    foreach ($frm->useranswer as $k => $v) {
        $add->{'var' . $k} = $v;
        $text .= $v . ". ";

        if ($sampleresponse = $DB->get_record("sassessment_responses", array("aid" => $sassessment->id, "iid" => $k))) {
            if (!empty($v)) {
                $add->{'per' . $k} = sassessment_similar_text($sampleresponse->text, $v);
            }
        }
    }

    $add->timecreated = time();

    $add->analize = json_encode(sassessment_printanalizeform($text));

    if (empty($frm->sid)) {
        $DB->insert_record("sassessment_studdent_answers", $add);
    } else {
        $add->id = $frm->sid;
        $DB->update_record("sassessment_studdent_answers", $add);
    }

    redirect("view.php?id={$id}", get_string('postsubmited', 'sassessment'));
}


/*
* Adding mew item
*/
if ($a == "add" && isset($frm->filewav) && is_array($frm->filewav)) {
    $add = new stdClass;
    $add->aid = $sassessment->id;
    $add->uid = $USER->id;
    $add->summary = $frm->summary;

    $text = "";
    $comparetext_orig = "";
    $comparetext_current = "";

    if ($sassessment->audio == 1) { // Disable audio file saving if teacher uncheck checkbox
        foreach ($frm->filewav as $k => $v) {
            $add->{'file' . $k} = $v;
        }
    }


    if (is_array($frm->filetext))
        foreach ($frm->filetext as $k => $v) {
            $add->{'var' . $k} = $v;
            $text .= $v . ". ";

            $maxp = 0;
            $maxi = 1;
            $maxtext = "";

            if ($sampleresponses = $DB->get_records("sassessment_responses", array("aid" => $sassessment->id, "iid" => $k))) {
                foreach ($sampleresponses as $sampleresponse) {
                    $percent = sassessment_similar_text($sampleresponse->text, $v);
                    if ($maxp < $percent) {
                        $maxi = $k;
                        $maxp = $percent;
                        $maxtext = $sampleresponse->text;
                    }
                }
            }

            $comparetext_orig .= $maxtext . " ";
            $comparetext_current .= $v . " ";

            if ($sampleresponse = $DB->get_record("sassessment_responses", array("aid" => $sassessment->id, "iid" => $k))) {
                //if (!empty($v)) {
                    $add->{'per' . $k} = sassessment_similar_text($sampleresponse->text, $v);
                //}
            }
        }

    $add->analize = json_encode(sassessment_printanalizeform($text));

    $total = 0;
    $c = 0;
    for ($i=0;$i<=10;$i++){
        if (isset($add->{'per' . $i})){
            $c++;
            $total += $add->{'per' . $i};
        }
    }

    $add->pertotal = round($total / $c);

    //$add->pertotal = round(sassessment_similar_text($comparetext_orig, $comparetext_current));

    $add->timecreated = time();

    if (empty($frm->sid)) {
        $DB->insert_record("sassessment_studdent_answers", $add);
    } else {
        $add->id = $frm->sid;
        $DB->update_record("sassessment_studdent_answers", $add);
    }

    $sassessment->cmidnumber = $cm->id;

    //Check max grade
    if ($catdata = $DB->get_record("grade_items", array("courseid" => $sassessment->course, "iteminstance" => $sassessment->id, "itemmodule" => 'sassessment'))) {

        $grade = array();
        $grade[$add->uid] = new stdClass;
        $grade[$add->uid]->id = $add->uid;
        $grade[$add->uid]->userid = $add->uid;
        $grade[$add->uid]->rawgrademax = $catdata->grademax;
        $grade[$add->uid]->grademax = $catdata->grademax;
        $grade[$add->uid]->rawgrade = round($catdata->grademax * $percent / 100);
        $grade[$add->uid]->feedback = "autograde";
        $grade[$add->uid]->feedbackformat = 0;


        if ($sassessment->grademethod == "rubricauto") {
            //$grade_item = new grade_item(array("courseid" => $sassessment->course, "iteminstance"=> $sassessment->id, "itemmodule" => 'sassessment'));
            /*
            $data = new stdClass;
            $data->grademax = 100;
            grade_item::set_properties($grade_item, $data);
            $grade_item->rescale_grades_keep_percentage($oldmin, $oldmax, $newmin, $newmax, 'gradebook');
            $grade_item->update();


                $rawgrade       = round($catdata->grademax * $percent/100);
                $feedback       = "autograde";
                $feedbackformat = FORMAT_MOODLE;
                $usermodified   = $USER->id;
                $datesubmitted  = null;
                $dategraded     = null;
                $grade_grade    = null;

                if (!$grade_item->update_raw_grade($add->uid, $rawgrade, $sassessment, $feedback, $feedbackformat, $usermodified, $dategraded, $datesubmitted, $grade_grade)){
                  echo "False";
                }
                */
            sassessment_grade_item_update($sassessment, $grade);
        }

    }

    redirect("view.php?id={$id}", get_string('postsubmited', 'sassessment'));
}


/*
* Delete item
*/
if ($act == "deleteentry" && !empty($upid)) {
    if (has_capability('mod/sassessment:teacher', $context))
        $DB->delete_records("sassessment_studdent_answers", array("id" => $upid));
    else
        $DB->delete_records("sassessment_studdent_answers", array("id" => $upid, "userid" => $USER->id));
}

/*
* Delete Audio
*/
if ($act == "deleteAudio" && !empty($upid)) {
    if (has_capability('mod/sassessment:teacher', $context))
        $file = $DB->get_record("sassessment_studdent_answers", array("id" => $upid));
    else
        $file = $DB->get_record("sassessment_studdent_answers", array("id" => $upid, "userid" => $USER->id));


    for ($i = 1; $i <= 10; $i++) {
        if (!empty($file->{'file' . $i})) {
            $DB->set_field("sassessment_studdent_answers", 'file' . $i, 0, array("id" => $file->id));

            if ($subfiles = $DB->get_records("files", array("itemid" => $file->{'file' . $i}))) {
                foreach ($subfiles as $subfile) {
                    $fs = get_file_storage();

                    $file = $fs->get_file($subfile->contextid, $subfile->component, $subfile->filearea,
                        $subfile->itemid, $subfile->filepath, $subfile->filename);

                    if ($file) {
                        $file->delete();
                    }
                }
            }
        }
    }
}


/// Print the page header

$PAGE->set_url('/mod/sassessment/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($sassessment->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/sassessment/css/style.css');
$PAGE->requires->js('/mod/sassessment/js/jquery.min.js', true);

$PAGE->requires->js('/mod/sassessment/js/flowplayer.min.js', true);
$PAGE->requires->js('/mod/sassessment/js/swfobject.js', true);

$PAGE->requires->js('/mod/sassessment/js/mediaelement-and-player.min.js', true);
$PAGE->requires->css('/mod/sassessment/css/mediaelementplayer.css');

$PAGE->requires->js('/mod/sassessment/js/video.js', true);
$PAGE->requires->css('/mod/sassessment/css/video-js.css');

//if ($sassessment->audio == 1 && $a == "add") {
if ($a == "add") {
    $PAGE->requires->js('/mod/sassessment/js/recorder.js', true);
    $PAGE->requires->js('/mod/sassessment/js/record_wav.js?' . time(), true);
}

$PAGE->requires->css('/mod/sassessment/splayer/css/mp3-player-button.css');
$PAGE->requires->js('/mod/sassessment/splayer/script/soundmanager2.js?' . time(), true);
$PAGE->requires->js('/mod/sassessment/splayer/script/mp3-player-button.js', true);
$PAGE->requires->js('/mod/sassessment/js/main.js?' . time(), true);


// other things you may want to set - remove if not needed
//$PAGE->set_cacheable(false);
//$PAGE->set_focuscontrol('some-html-id');
//$PAGE->add_body_class('sassessment-'.$somevar);

// Output starts here
echo $OUTPUT->header();

require_once('tabs.php');

echo '
<script type="text/javascript">
$( document ).ready(function() {
  soundManager.setup({url: "splayer/swf/"
  });
});
</script>';

$levelst = array("-");
for ($i = 1; $i <= $sassessment->grade; $i++) {
    $levelst[] = $i;
}

if ($a == "summary") {
    $a = "list";
    $aType = "full";
}

if ($a == "list") {
    if (($sassessment->grademethod == "rubric" || $sassessment->grademethod == "rubricauto") && $sassessment->humanevaluation == 1) {
        echo html_writer::start_tag('div');
        echo html_writer::link(new moodle_url('/mod/sassessment/submissions.php', array("id" => $id)), get_string("rubrics", "sassessment"));
        echo html_writer::end_tag('div');
    }

    if ($sassessment->intro) { // Conditions to show the intro can change to look for own settings or whatever
        echo $OUTPUT->box(format_module_intro('sassessment', $sassessment, $cm->id), 'generalbox mod_introbox', 'sassessmentintro');
    }


    $table = new html_table();
    $table->width = "100%";

    $table->head = array(get_string("cell1::student", "sassessment"), get_string("cell2::answer", "sassessment"));
    $table->align = array("left", "left");

    $table->size = array("150px", "300px");

    /*
    if ($sassessment->textcomparison == 1) {
      $table->head[] = get_string("cell4::textcomparison", "sassessment");
      $table->align[] = "left";
      $table->size[] = "200px";
    }
    */

    if ($sassessment->textanalysis == 1) {
        $table->head[] = get_string("cell3::textanalysis", "sassessment");
        $table->align[] = "left";
        $table->size[] = "200px";
    }

    if ($sassessment->humanevaluation == 1 && $sassessment->grademethod == "default") {
        $table->head[] = get_string("cell5::humanevaluation", "sassessment");
        $table->align[] = "center";
        $table->size[] = "50px";
    }


    if (isset($aType)) {
        $lists = $DB->get_records("sassessment_studdent_answers", array(), 'timecreated DESC');
    } else {
        $lists = $DB->get_records("sassessment_studdent_answers", array("aid" => $sassessment->id), 'timecreated DESC');
    }


    foreach ($lists as $list) {
        if ($list->uid == $USER->id || has_capability('mod/sassessment:teacher', $context)) { // Only for Teachers and owners
            $userdata = $DB->get_record("user", array("id" => $list->uid));
            $picture = $OUTPUT->user_picture($userdata, array('popup' => true));

            //1-cell
            $o = "";
            $o .= html_writer::start_tag('div', array("style" => "text-align:left;margin:10px 0;"));
            $o .= html_writer::tag('span', $picture);
            $o .= html_writer::start_tag('span', array("style" => "margin: 8px;position: absolute;"));
            $o .= html_writer::link(new moodle_url('/user/view.php', array("id" => $userdata->id, "course" => $cm->course)), fullname($userdata));
            $o .= html_writer::end_tag('span');
            $o .= html_writer::end_tag('div');

            $o .= html_writer::tag('div', "", array("style" => "clear:both"));

            $o .= html_writer::start_tag('div');
            $o .= $list->summary;
            $o .= html_writer::end_tag('div');


            $o .= html_writer::tag('div', html_writer::tag('small', date("F d, Y @ H:i", $list->timecreated), array("style" => "margin: 2px 0 0 10px;")));


            if ($list->uid == $USER->id || has_capability('mod/sassessment:teacher', $context)) {
                if ($list->uid == $USER->id)
                    $editlink = html_writer::link(new moodle_url('/mod/sassessment/view.php', array("id" => $id, "a" => "add", "upid" => $list->id)), get_string("editlink", "sassessment")) . " ";
                else
                    $editlink = "";

                $deletelink = html_writer::link(new moodle_url('/mod/sassessment/view.php', array("id" => $id, "a" => "list", "act" => "deleteentry", "upid" => $list->id)), get_string("delete", "sassessment"), array("onclick" => "return confirm('" . get_string("confim", "sassessment") . "')"));
                $deleteAudiolink = html_writer::link(new moodle_url('/mod/sassessment/view.php', array("id" => $id, "a" => "list", "act" => "deleteAudio", "upid" => $list->id)), get_string("deleteAudio", "sassessment"), array("onclick" => "return confirm('" . get_string("confim", "sassessment") . "')"));

                $o .= html_writer::tag('div', html_writer::tag('small', $editlink . $deletelink . $deleteAudiolink, array("style" => "margin: 2px 0 0 10px;")));
            }

            $cell1 = new html_table_cell($o);

            $o = "";

            $comparetext_orig = "";
            $comparetext_current = "";
            $comparecurrent = "";

            for ($i = 1; $i <= 10; $i++) {
                if (!empty($list->{'var' . $i}) || !empty($list->{'file' . $i})) {
                    unset($response);

                    $o .= html_writer::start_tag('div', array("style" => "margin:10px 0;"));

                    $o .= "<div>";

                    $o .= "{$i}. ";

                    if (!empty($list->{'file' . $i}))
                        $o .= "&nbsp;" . sassessment_splayer($list->{'file' . $i}) . " &nbsp;";

                    if ($response = $DB->get_record("sassessment_responses", array("aid" => $sassessment->id, "iid" => $i, "rid" => 1)))
                        $o .= '<span style="color: #0099CC;">' . sassessment_scoreFilter($list->{'per' . $i}, $sassessment) . "</span> &nbsp;";

                    if (!empty($list->{'var' . $i}))
                        $o .= '<b>' . get_string("studentanswer", "sassessment") . ":</b> " . $list->{'var' . $i};

                    if (!empty($list->{'var' . $i}) && $sassessment->textcomparison == 1) {
                        $maxp = 0;
                        $maxi = 1;
                        $maxtext = "";
                        if ($sampleresponses = $DB->get_records("sassessment_responses", array("aid" => $sassessment->id, "iid" => $i))) {
                            foreach ($sampleresponses as $sampleresponse) {
                                $percent = $list->{'var' . $i};
                                if ($maxp < $percent) {
                                    $maxi = $i;
                                    $maxp = $percent;
                                    $maxtext = $sampleresponse->text;
                                }
                            }
                        }

                        $comparetext_orig .= $maxtext . " ";
                        $comparetext_current .= $list->{'var' . $i} . " ";

                        $comparecurrent .= "<div>{$i}. <b>" . sassessment_scoreFilter(round($maxp), $sassessment) . "%</b> " . $maxtext . "</div>";
                    }

                    $o .= '</div>';

                    if ($response)
                        $o .= "<div style='font-size: small;color: #888;'><b>" . get_string("targetanswer", "sassessment") . ":</b> " . $response->text . '</div>';


                    $o .= html_writer::end_tag('div');
                }
            }

            $cell2 = new html_table_cell($o);

            $o = "";

            if ($catdata = $DB->get_record("grade_items", array("courseid" => $cm->course, "iteminstance" => $cm->instance, "itemmodule" => 'sassessment'))) {
                if ($grid = $DB->get_record("grade_grades", array("itemid" => $catdata->id, "userid" => $list->uid))) {
                    $rateteacher = round($grid->finalgrade, 1);
                    $o .= html_writer::tag('small', $rateteacher) . " ";
                }
            }

            if (has_capability('mod/sassessment:teacher', $context))
                $o .= html_writer::select($levelst, 'rating', '', true, array("class" => "sassessment_rate_box", "data-url" => $id . ":" . $list->id));

            $cell5 = new html_table_cell($o);

            $cells = array($cell1, $cell2);

            if ($sassessment->textcomparison == 1) {
                //$percent = sassessment_similar_text($comparetext_orig, $comparetext_current);
                //similar_text($comparetext_orig, $comparetext_current, $percent);
                $cell3 = new html_table_cell("<div style=\"color: #0099CC;\">Total: <b>" . sassessment_scoreFilter($list->pertotal, $sassessment) . "</b></div>" . $comparecurrent);
                //$cells[] = $cell3;
            }

            if ($sassessment->textanalysis == 1) {
                if ($sassessment->textcomparison == 1)
                    $cell4 = new html_table_cell("<div style=\"color: #0099CC;\">Total: <b>" . sassessment_scoreFilter($list->pertotal, $sassessment) . "</b></div>" . "<small>" . sassessment_analizereport(json_decode($list->analize, true)) . "</small>");
                else
                    $cell4 = new html_table_cell("<small>" . sassessment_analizereport(json_decode($list->analize, true)) . "</small>");

                $cells[] = $cell4;
            }

            if ($sassessment->grademethod == "default" && $sassessment->humanevaluation == 1)
                $cells[] = $cell5;


            $row = new html_table_row($cells);

            $table->data[] = $row;
        }
    }

    echo html_writer::table($table);


    // Replace the following lines with you own code
    //echo $OUTPUT->heading('Yay! It works!');
}


if ($a == "add") {
    class sassessment_comment_form extends moodleform
    {
        function definition()
        {
            global $CFG, $USER, $DB, $sassessment, $upidm, $id;

            $time = time();
            $filename = str_replace(" ", "_", $USER->username) . "_" . date("Ymd_Hi", $time);

            $mform =& $this->_form;

            $mform->disable_form_change_checker();

            //$mform->addElement('static', 'description', '', '<script type="text/javascript" src="/moodle/mod/sassessment/js/main.js?'.time().'"></script>');

            $mform->addElement('static', 'description', '', $sassessment->instructions);

            if (!empty($upid)) {
                $data = $DB->get_record("sassessment_studdent_answers", array("id" => $upid));
                $mform->addElement('hidden', 'sid', $upid);
            }


            $o = "<div id=\"sassessment-attempt-id\" data-url=\"{$sassessment->id}\"></div><style> .activeborder{ border: 2px solid #000; }  .activeborderb{ border: 2px solid #2700FF; }</style>";

            $o .= '<table style="margin:10px 0;width:100%">';
            for ($i = 1; $i <= 10; $i++) {
                if (!empty($sassessment->{'var' . $i}) || !empty($sassessment->{'file' . $i})) {
                    if (!empty($data->{'var' . $i}))
                        $val = $data->{'var' . $i};
                    else
                        $val = "";

                    //$mform->addElement('static', 'description', ''.$i.".", $sassessment->{'var'.$i});

                    //
                    $o .= '<tr id="questionbox_' . $i . '" style="background-color:#fff;padding:5px;"';

                    if ($i == 1)
                        $o .= ' class="activeborder"';

                    $o .= '>

            <td style="width:50px;"><img src="img/listen.png" /></td>
            <td colspan="3">' . $sassessment->{'var' . $i} . " ";

                    if (!empty($sassessment->{'file' . $i}))
                        $o .= sassessment_splayer($sassessment->{'file' . $i}, "play_l_" . $i);

                    $o .= ' </td>';

                    $o .= '</tr>';

                    $response = $DB->get_record("sassessment_responses", array("aid" => $sassessment->id, "iid" => $i, "rid" => 1));

                    $o .= '<tr id="answerbox_' . $i . '" style="background-color:#add8e6;padding:5px;">
          <td style="width:50px;"><img src="img/speak.png" /></td>';

                    if (!empty($sassessment->{'varcheck' . $i})) {
                        if ($response) {
                            $o .= '<td style="width:400px;">' . $response->text . ' ';

                            if (!empty($sassessment->{'filesr' . $i}))
                                $o .= sassessment_splayer($sassessment->{'filesr' . $i}, "play_t_" . $i);

                            $o .= '</td>';
                        }
                    } else if (!empty($sassessment->{'filesr' . $i})) {
                        $o .= '<td style="width:400px;"> ';
                        $o .= sassessment_splayer($sassessment->{'filesr' . $i}, "play_t_" . $i);
                        $o .= '</td>';
                    } else 
                        $o .= '<td></td>';

                    if ($sassessment->transcribe == 1)
                        $o .= '<td style="">
              <div id="answer_div_' . $i . '" data-url="' . str_replace('"', "'", $response->text) . '"></div>
              <textarea name="filetext[' . $i . ']" id="answer_' . $i . '" style="display:none;width:500px;height:70px;"></textarea>
              <div style="color:blue;" id="answer_score_' . $i . '"></div>
              </td>';
                    else
                        $o .= '<td></td>';

                    if (sassessment_is_ios()) {
                        $time = time();
                        $o .= '<td style="width: 70px;">
            <div style="float:left;width:30px;">

            <div><a href="voiceshadow://?link=' . $CFG->wwwroot . '&id=' . $id . '&uid=' . $USER->id . '&time=' . $time . '&fid=' . $sassessment->{'filesr' . $i} . '&var='.$i.'&mod=sassessment">'.get_string("speak", "sassessment").'</a></div>
            <input type="hidden" name="filewav[' . $i . ']" value="" id="filewav_' . $i . '"/></div>
            </div>';

                        $o .= html_writer::script('
setInterval(function(){
    $.get( "ajax-apprecord.php", { id: '.$id.', uid: '.$USER->id.', i: '.$i.' }, function(json){
        var j = JSON.parse(json);
        var t = +new Date();

        if (j.status == "success") {
            //$(\'#recordappfile_aac_' . $i . '\').html("adding...");
            $(\'#recordappfile_aac_' . $i . '\').append(\'<source src="' . $CFG->wwwroot . '/mod/sassessment/file.php?file=\'+j.fileid+\'" type="audio/aac" />\');
            $(\'#answer_div_ios_' . $i . '\').html(j.text);
            $(\'#answer_' . $i . '\').val(j.text);
            $("#filewav_' . $i . '").val(j.itemid);
            $(\'#recordappfile_aac_' . $i . '\').show();

          $.post( "ajax-score.php", { text1: $("#answer_div_ios_' . $i . '").attr("data-url"), text2: $("#answer_div_ios_' . $i . '").text(), aid: $("#sassessment-attempt-id").attr("data-url") }, function( data ) {
            $("#answer_score_ios_' . $i . '").html( data );
          });

            $.get( "ajax-apprecord.php", { a: "delete", id: '.$id.', uid: '.$USER->id.', i: '.$i.' });
        }
    } );
}, 1000);
                ');
                    } else {
                        $o .= '<td style="width: 70px;">
            <div style="float:left;width:30px;">
            <!--<div id="speech-content-mic_' . $i . '" class="speech-mic" style="float:left;width: 45px;height: 45px;margin-top: -8px;"></div>-->
            <img src="img/recorder_inactive.png" onclick="startRecording(this, ' . $i . ');" title="Record" alt="Record" data-url="speech-content-mic_' . $i . '" style="cursor: pointer;" />
            <img src="img/recorder_active.png" onclick="stopRecording(this, ' . $i . ');" title="Stop" alt="Stop" data-url="speech-content-mic_' . $i . '" style="cursor: pointer;display:none;"/>
            <input type="hidden" name="filewav[' . $i . ']" value="" id="filewav_' . $i . '"/></div>
            </div>';
                    }

                    if ($sassessment->audio == 1){
                        $styleRecording = "";
                    } else {
                        $styleRecording = "display:none;";
                    }


                    $o .= '
          <div style="float:left;"><img src="img/ajax-loader.gif" id="loader_' . $i . '" style="display:none;"/></div>
          <div id="recording_' . $i . '" style="float:left;'.$styleRecording.'"></div>
          <div style="clear:both;"></div>
          </td>';

                    $o .= "</tr>";

                    if (sassessment_is_ios()) {
                        $o .= '<tr>
<td></td>
<td colspan="2"><div id="answer_div_ios_' . $i . '" data-url="' . str_replace('"', "'", $response->text) . '" style="margin: 6px 0;"></div></td>
<td>
<div style="color:blue;" id="answer_score_ios_' . $i . '"></div></td>
</tr>
<tr>
<td></td>
<td colspan="3"><audio id="recordappfile_aac_'.$i.'" controls style="display:none;"></audio></td>
</tr>';
                        }

                }
            }

            $o .= '</table>';
            $mform->addElement('html', $o);

            $mform->addElement('textarea', 'summary', 'Comment (optional)', 'style="width:600px;height:100px;"');

            if (!empty($data->summary))
                $mform->setDefault('summary', $data->summary);

            $this->add_action_buttons(false, $submitlabel = get_string("saverecording", "sassessment"));
        }
    }

    $mform = new sassessment_comment_form('view.php?a=' . $a . '&id=' . $id);

    $mform->display();
}

/*
$sassessment->cmidnumber = $cm->id;
$grade = "reset";

sassessment_grade_item_update($sassessment, $grade);
*/
// Finish the page
echo $OUTPUT->footer();

//$time_end = microtime(true);

//$execution_time = $time_end - $time_start;

//echo $execution_time."<br />";
