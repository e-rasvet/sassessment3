<?php  // $Id: grade.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $


require_once '../../config.php';
require_once($CFG->libdir.'/gradelib.php');
require_once("$CFG->dirroot/grade/grading/lib.php");
require_once($CFG->dirroot.'/course/moodleform_mod.php');


$id                     = optional_param('id', 0, PARAM_INT); 

if ($id) {
    if (! $cm = get_coursemodule_from_id('sassessment', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }

    if (! $sassessment = $DB->get_record('sassessment', array('id' => $cm->instance))) {
        error('Course module is incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

//add_to_log($course->id, "sassessment", "view", "view.php?id=$cm->id", "$sassessment->id");


/// Print the page header
$strsassessments = get_string('modulenameplural', 'sassessment');
$strsassessment  = get_string('modulename', 'sassessment');

$PAGE->set_url('/mod/sassessment/view.php', array('id' => $id));
    
$title = $course->shortname . ': ' . format_string($sassessment->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

redirect(new moodle_url('/mod/sassessment/view.php', array("id" => $id)));

/*
echo $OUTPUT->header();

/// Print the main part of the page

require_once ('tabs.php');

if ($idsub = optional_param('submission', 0, PARAM_INT)) {
    $submission = $DB->get_record('voiceshdow_submissions', array('id' => $idsub));
} elseif (!$submission = voiceshdow_get_submission($user->id)) {
    $submission = voiceshdow_prepare_new_submission($userid);
}
if ($submission->timemodified > $submission->timemarked) {
    $subtype = 'voiceshdownew';
} else {
    $subtype = 'voiceshdowold';
}

$grademenu = make_grades_menu(100);

$gm = get_grading_manager($context, 'mod_sassessment', 'submission');

if ($controller = $gm->get_controller('rubric')) {
  //if ($controller->is_form_available()) {
  //  echo "yes!";
  //}
  print_r ($controller);
}


/// Finish the page
echo $OUTPUT->footer();

*/