<?php

//===================================================
// all.php
//
// Displays a complete list of online sassessments
// for the course. Rather like what happened in
// the old Journal activity.
// Howard Miller 2008
// See MDL-14045
//===================================================

require_once("../../../../config.php");
require_once("{$CFG->dirroot}/mod/sassessment/lib.php");
require_once($CFG->libdir.'/gradelib.php');
require_once('sassessment.class.php');

// get parameter
$id = required_param('id', PARAM_INT);   // course

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('invalidcourse');
}

$PAGE->set_url('/mod/sassessment/type/online/all.php', array('id'=>$id));

require_course_login($course);

// check for view capability at course level
$context = context_course::instance($course->id);
require_capability('mod/sassessment:view',$context);

// various strings
$str = new stdClass;
$str->sassessments = get_string("modulenameplural", "sassessment");
$str->duedate = get_string('duedate','sassessment');
$str->duedateno = get_string('duedateno','sassessment');
$str->editmysubmission = get_string('editmysubmission','sassessment');
$str->emptysubmission = get_string('emptysubmission','sassessment');
$str->nosassessments = get_string('nosassessments','sassessment');
$str->onlinetext = get_string('typeonline','sassessment');
$str->submitted = get_string('submitted','sassessment');

$PAGE->navbar->add($str->sassessments, new moodle_url('/mod/sassessment/index.php', array('id'=>$id)));
$PAGE->navbar->add($str->onlinetext);

// get all the sassessments in the course
$sassessments = get_all_instances_in_course('sassessment',$course, $USER->id );

$sections = get_all_sections($course->id);

// array to hold display data
$views = array();

// loop over sassessments finding online ones
foreach( $sassessments as $sassessment ) {
    // only interested in online sassessments
    if ($sassessment->sassessmenttype != 'online') {
        continue;
    }

    // check we are allowed to view this
    $context = context_module::instance($sassessment->coursemodule);
    if (!has_capability('mod/sassessment:view',$context)) {
        continue;
    }

    // create instance of sassessment class to get
    // submitted sassessments
    $onlineinstance = new sassessment_online( $sassessment->coursemodule );
    $submitted = $onlineinstance->submittedlink(true);
    $submission = $onlineinstance->get_submission();

    // submission (if there is one)
    if (empty($submission)) {
        $submissiontext = $str->emptysubmission;
        if (!empty($sassessment->timedue)) {
            $submissiondate = "{$str->duedate} ".userdate( $sassessment->timedue );

        } else {
            $submissiondate = $str->duedateno;
        }

    } else {
        $submissiontext = format_text( $submission->data1, $submission->data2 );
        $submissiondate  = "{$str->submitted} ".userdate( $submission->timemodified );
    }

    // edit link
    $editlink = "<a href=\"{$CFG->wwwroot}/mod/sassessment/view.php?".
        "id={$sassessment->coursemodule}&amp;edit=1\">{$str->editmysubmission}</a>";

    // format options for description
    $formatoptions = new stdClass;
    $formatoptions->noclean = true;

    // object to hold display data for sassessment
    $view = new stdClass;

    // start to build view object
    $view->section = get_section_name($course, $sections[$sassessment->section]);

    $view->name = $sassessment->name;
    $view->submitted = $submitted;
    $view->description = format_module_intro('sassessment', $sassessment, $sassessment->coursemodule);
    $view->editlink = $editlink;
    $view->submissiontext = $submissiontext;
    $view->submissiondate = $submissiondate;
    $view->cm = $sassessment->coursemodule;

    $views[] = $view;
}

//===================
// DISPLAY
//===================

$PAGE->set_title($str->sassessments);
echo $OUTPUT->header();

foreach ($views as $view) {
    echo $OUTPUT->container_start('clearfix generalbox sassessment');

    // info bit
    echo $OUTPUT->heading("$view->section - $view->name", 3, 'mdl-left');
    if (!empty($view->submitted)) {
        echo '<div class="reportlink">'.$view->submitted.'</div>';
    }

    // description part
    echo '<div class="description">'.$view->description.'</div>';

    //submission part
    echo $OUTPUT->container_start('generalbox submission');
    echo '<div class="submissiondate">'.$view->submissiondate.'</div>';
    echo "<p class='no-overflow'>$view->submissiontext</p>\n";
    echo "<p>$view->editlink</p>\n";
    echo $OUTPUT->container_end();

    // feedback part
    $onlineinstance = new sassessment_online( $view->cm );
    $onlineinstance->view_feedback();

    echo $OUTPUT->container_end();
}

echo $OUTPUT->footer();