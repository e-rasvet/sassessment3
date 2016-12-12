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

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox boxaligcenter', 'dates');

$lists = $DB->get_records ("sassessment_files", array("userid" => $user->id), 'time DESC');


$table        = new html_table();
$table->head  = array(get_string("sassessment_list", "sassessment"));
$table->align = array ("left");
$table->width = "100%";

foreach ($lists as $list) {
  if ($cml = get_coursemodule_from_id('sassessment', $list->instance)) {
    if ($cml->course == $cm->course && $cml->instance == $cm->instance) {
      $name = "var".$list->var."text";
      
      $userdata  = $DB->get_record("user", array("id" => $list->userid));
      $picture   = $OUTPUT->user_picture($userdata, array('popup' => true));
      
      $o = "";
      $o .= html_writer::start_tag('div', array("style" => "text-align:left;margin:10px 0;"));
      $o .= html_writer::tag('span', $picture);
      $o .= html_writer::start_tag('span', array("style" => "margin: 8px;position: absolute;"));
      $o .= html_writer::link(new moodle_url('/user/view.php', array("id" => $userdata->id, "course" => $cml->course)), fullname($userdata));
      $o .= html_writer::end_tag('span');
      $o .= html_writer::end_tag('div');
      
      $o .= html_writer::tag('div', $list->summary, array('style'=>'margin:10px 0;'));
      
      $o .= html_writer::tag('div', sassessment_player($list->id));
      
      if (!empty($sassessment->{$name}))
        $o .= html_writer::tag('div', "(".$sassessment->{$name}.")");
      
      $o .= html_writer::tag('div', html_writer::tag('small', date(get_string("timeformat1", "sassessment"), $list->time)), array("style" => "float:left;"));
      
      $cell1 = new html_table_cell($o);
      
      $cells = array($cell1);
      
      $row = new html_table_row($cells);
      
      $table->data[] = $row;
    }
  }
}

echo html_writer::table($table);

echo $OUTPUT->box_end();
echo $OUTPUT->close_window_button();
echo $OUTPUT->footer();

