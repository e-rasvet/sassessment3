<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/plagiarismlib.php');

$id = optional_param('id', 0, PARAM_INT);  // Course Module ID
$a  = optional_param('a', 0, PARAM_INT);   // sassessment ID

$url = new moodle_url('/mod/sassessment/view.php');
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
        print_error('invalidid', 'sassessment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$sassessment->course))) {
        print_error('coursemisconf', 'sassessment');
    }
    if (! $cm = get_coursemodule_from_instance("sassessment", $sassessment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

$PAGE->set_url($url);
require_login($course, true, $cm);

$PAGE->requires->js('/mod/sassessment/sassessment.js');

require ("$CFG->dirroot/mod/sassessment/type/$sassessment->sassessmenttype/sassessment.class.php");
$sassessmentclass = "sassessment_$sassessment->sassessmenttype";
$sassessmentinstance = new $sassessmentclass($cm->id, $sassessment, $cm, $course);

/// Mark as viewed
$completion=new completion_info($course);
$completion->set_module_viewed($cm);

$sassessmentinstance->view();   // Actually display the sassessment!