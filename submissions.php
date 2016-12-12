<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/plagiarismlib.php');

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // sassessment ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$download = optional_param('download' , 'none', PARAM_ALPHA); //ZIP download asked for?

$url = new moodle_url('/mod/sassessment/submissions.php');
if ($id) {
    if (! $cm = get_coursemodule_from_id('sassessment', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $sassessment = $DB->get_record("sassessment", array("id"=>$cm->instance))) {
        print_error('invalidid', 'sassessment');
    }

    if (! $course = $DB->get_record("course", array("id"=>$sassessment->course))) {
        print_error('coursemisconf', 'sassessment');
    }
    $url->param('id', $id);
} else {
    if (!$sassessment = $DB->get_record("sassessment", array("id"=>$a))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$sassessment->course))) {
        print_error('coursemisconf', 'sassessment');
    }
    if (! $cm = get_coursemodule_from_instance("sassessment", $sassessment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

if ($mode !== 'all') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);
require_login($course->id, false, $cm);

/*
* If is student
*/

if (!has_capability('mod/sassessment:grade', context_module::instance($cm->id))) {
  $url = new moodle_url('/mod/sassessment/viewrubric.php', array("id"=>$id));
  header('Location: '.$url);
  die();
}



$PAGE->requires->js('/mod/sassessment/sassessment.js');

/// Load up the required sassessment code
require($CFG->dirroot.'/mod/sassessment/type/'.$sassessment->sassessmenttype.'/sassessment.class.php');
$sassessmentclass = 'sassessment_'.$sassessment->sassessmenttype;
$sassessmentinstance = new $sassessmentclass($cm->id, $sassessment, $cm, $course);

if($download == "zip") {
    $sassessmentinstance->download_submissions();
} else {
    $sassessmentinstance->submissions($mode);   // Display or process the submissions
}