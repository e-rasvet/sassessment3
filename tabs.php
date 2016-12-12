<?php // $Id: tabs.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $

    $currenttab = $a;

    if (empty($sassessment)) {
        error('You cannot call this script in that way');
    }
    if (empty($currenttab)) {
        $currenttab = 'list';
    }
    if (!isset($cm)) {
        $cm = get_coursemodule_from_instance('sassessment', $sassessment->id);
    }
    if (!isset($course)) {
        $course = $DB->get_record('course', array('id' => $sassessment->course));
    }

    $tabs     = array();
    $row      = array();
    $inactive = array();

    $row[]  = new tabobject('list', new moodle_url('/mod/sassessment/view.php', array('id'=>$id)), get_string('list', 'sassessment'));
   
    $showaddbutton = 1;
    
    /*
    if ($sassessment->allowmultiple > 0) {
      $data = $DB->count_records("sassessment_files", array("instance"=>$id, "userid"=>$USER->id));
      if ($data >= $sassessment->allowmultiple)
        $showaddbutton = 0;
    }
    */
    
    //if ($sassessment->timedue == 0 || ($sassessment->timedue > 0 && time() < $sassessment->timedue) || $sassessment->preventlate == 1)
      if ($showaddbutton == 1)
        $row[]  = new tabobject('add', new moodle_url('/mod/sassessment/view.php', array('id'=>$id, 'a'=>'add')), get_string('addnew', 'sassessment'));
    
    
    //$row[]  = new tabobject('history', new moodle_url('/mod/sassessment/viewhistory.php', array('id'=>$id ,'ids'=>$USER->id, 'a'=>'history')), get_string('sassessment_viewhistory', 'sassessment'));
    
    //$contextmodule = get_context_instance(CONTEXT_MODULE, $cm->id);
    $contextmodule = context_module::instance($cm->id);
    
    //if (has_capability('mod/sassessment:teacher', $contextmodule))
    //  $row[]  = new tabobject('historybyuser', new moodle_url('/mod/sassessment/viewhistory_by_users.php', array('id'=>$id, 'a'=>'historybyuser')), get_string('sassessment_by_student', 'sassessment'));
    
    $tabs[] = $row;

    print_tabs($tabs, $currenttab, $inactive);
