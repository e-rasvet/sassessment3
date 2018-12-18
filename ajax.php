<?php

  require_once '../../config.php';
  require_once 'lib.php';


  $data                        = optional_param('data', 0, PARAM_TEXT); 
  $value                     = optional_param('value', 0, PARAM_INT); 

  list($id,$aid) = explode(":", $data);

  $sassessmentfiles = $DB->get_record("sassessment_studdent_answers", array("id" => $aid));
  $cm  = get_coursemodule_from_id('sassessment', $id);
  $ids = $cm->instance;

  if (!$sassessmentid = $DB->get_record("sassessment_ratings", array("aid" => $ids, "userid" => $USER->id))) {
    $add                = new stdClass;
    $add->aid           = $ids;
    $add->userid        = $USER->id;
    $add->rating        = $value;
    $add->time          = time();
    
    $DB->insert_record("sassessment_ratings", $add);
  } else {
    $DB->set_field("sassessment_ratings", "rating", $value, array("aid" => $ids, "userid" => $USER->id));
  }
  
  echo $value;
  
  $sassessmentid = $DB->get_record("sassessment_ratings", array("aid" => $ids, "userid" => $USER->id));
  $context = context_module::instance($id);
  $sassessment = $DB->get_record("sassessment", array("id"=>$ids));
  
      
  //-----Set grade----//
      
  if (has_capability('mod/sassessment:teacher', $context)) {
      $catdata  = $DB->get_record("grade_items", array("courseid" => $sassessment->course, "iteminstance"=> $sassessment->id, "itemmodule" => 'sassessment'));
      
      $gradesdata               = new stdClass();
      $gradesdata->itemid       = $catdata->id;
      $gradesdata->userid       = $sassessmentfiles->uid;
      $gradesdata->rawgrade     = $value;
      $gradesdata->finalgrade   = $value;
      $gradesdata->rawgrademax  = $catdata->grademax;
      $gradesdata->usermodified = $sassessmentfiles->uid;
      $gradesdata->timecreated  = time();
      $gradesdata->time         = time();
      
      
      if (!$grid = $DB->get_record("grade_grades", array("itemid" => $gradesdata->itemid, "userid" => $gradesdata->userid))) {
          $grid = $DB->insert_record("grade_grades", $gradesdata);
      } else {
          $gradesdata->id = $grid->id;
          $DB->update_record("grade_grades", $gradesdata);
      }
  }
