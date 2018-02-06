<?php

require("../../../../config.php");
require("../../lib.php");
require("sassessment.class.php");

$id     = required_param('id', PARAM_INT);      // Course Module ID
$userid = required_param('userid', PARAM_INT);  // User ID

$PAGE->set_url('/mod/sassessment/type/online/file.php', array('id'=>$id, 'userid'=>$userid));

if (! $cm = get_coursemodule_from_id('sassessment', $id)) {
    print_error('invalidcoursemodule');
}

if (! $sassessment = $DB->get_record("sassessment", array("id"=>$cm->instance))) {
    print_error('invalidid', 'sassessment');
}

if (! $course = $DB->get_record("course", array("id"=>$sassessment->course))) {
    print_error('coursemisconf', 'sassessment');
}

if (! $user = $DB->get_record("user", array("id"=>$userid))) {
    print_error('usermisconf', 'sassessment');
}

require_login($course->id, false, $cm);

$context = context_module::instance($cm->id);
if (($USER->id != $user->id) && !has_capability('mod/sassessment:grade', $context)) {
    print_error('cannotviewsassessment', 'sassessment');
}

if ($sassessment->sassessmenttype != 'online') {
    print_error('invalidtype', 'sassessment');
}

$sassessmentinstance = new sassessment_online($cm->id, $sassessment, $cm, $course);


$PAGE->set_pagelayout('popup');
$PAGE->set_title(fullname($user,true).': '.$sassessment->name);

$PAGE->requires->js('/mod/sassessment/js/jquery.min.js', true);
$PAGE->requires->js('/mod/sassessment/js/flowplayer.min.js', true);
$PAGE->requires->js('/mod/sassessment/js/swfobject.js', true);

$PAGE->requires->css('/mod/sassessment/splayer/css/mp3-player-button.css');
$PAGE->requires->js('/mod/sassessment/splayer/script/soundmanager2.js?', true);
$PAGE->requires->js('/mod/sassessment/splayer/script/mp3-player-button.js', true);
$PAGE->requires->js('/mod/sassessment/js/main.js?6', true);

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox boxaligcenter', 'dates');

$lists = $DB->get_records ("sassessment_studdent_answers", array("uid" => $user->id), 'id DESC');


$table = new html_table();
$table->width = "100%";

$table->head = array(get_string("cell1::student", "sassessment"), get_string("cell2::answer", "sassessment"));
$table->align = array("left", "left");

$table->size = array("150px", "300px");

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


foreach ($lists as $list) {

    if ($cm->instance == $list->aid) {
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
                    $studentPlayer = "&nbsp;" . sassessment_splayer($list->{'file' . $i}) . " &nbsp;";
                else
                    $studentPlayer = "";


                if ($response = $DB->get_record("sassessment_responses", array("aid" => $sassessment->id, "iid" => $i, "rid" => 1)))
                    $o .= '<span style="color: #0099CC;">' . sassessment_scoreFilter($list->{'per' . $i}, $sassessment) . "</span> &nbsp;";

                if (!empty($list->{'var' . $i}))
                    $o .= '<b>' . get_string("studentanswer", "sassessment") . ":</b> " . $studentPlayer . " " . $list->{'var' . $i};
                else if (!empty($studentPlayer))
                    $o .= '<b>' . get_string("studentanswer", "sassessment") . ":</b> " . $studentPlayer;

                $o .= '</div>';

                //if (!empty($sassessment->{'varcheck' . $i}) || !empty($sassessment->{'filesr' . $i}))
                if (!empty($sassessment->{'filesr' . $i}))
                    $targetAnswerPlayer = "&nbsp;" . sassessment_splayer($sassessment->{'filesr' . $i}, "play_l_" . $list->id . "_" . $i, $list->id . "_" . $i) . " &nbsp;";
                else
                    $targetAnswerPlayer = "";

                if ($response)
                    $o .= "<div style='font-size: small;color: #888;'><b>" . get_string("targetanswer", "sassessment") . ":</b> " . $targetAnswerPlayer . " " . $response->text . '</div>';
                else
                    $o .= "No response";

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

echo $OUTPUT->box_end();
echo $OUTPUT->close_window_button();
echo $OUTPUT->footer();

