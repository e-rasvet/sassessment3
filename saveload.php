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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id   = optional_param('id', 0, PARAM_CLEAN); // course_module ID, or
$a    = optional_param('a', 'save', PARAM_ALPHA);  
$p    = optional_param('p', NULL, PARAM_CLEAN);  

if (is_numeric($id)) {
  if ($id) {
      $cm         = get_coursemodule_from_id('sassessment', $id, 0, false, MUST_EXIST);
      $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
      $sassessment  = $DB->get_record('sassessment', array('id' => $cm->instance), '*', MUST_EXIST);
  } else {
      error('You must specify a course_module ID or an instance ID');
  }

} else {
  
}


if ($a == "save") {
  $p = json_decode($p);

  /*
  * Clear old Vars
  */
  $add = new stdClass;
  $add->id = $sassessment->id;

  for($i=1;$i<=10;$i++){
    $add->{"var".$i} = "";
    $add->{"varcheck".$i} = 0;
  }


  $DB->update_record("sassessment", $add);

  $DB->delete_records("sassessment_responses", array("aid"=>$sassessment->id));



  $add = new stdClass;
  $add->id = $sassessment->id;

  foreach($p->item as $k=>$v){
    if (!empty($v))
      $add->{"var".$k} = $v;
  }


  foreach($p->varcheck as $k=>$v){
    if (!empty($v))
      $add->{"varcheck".$k} = $v;
    else
      $add->{"varcheck".$k} = 0;
  }


  $DB->update_record("sassessment", $add);


  $add = new stdClass;
  $add->aid = $sassessment->id;

  foreach($p->resp as $k=>$v){
    $add->iid = $k;
    foreach($v as $k2=>$v2){
      $add->rid  = $k2;
      $add->text = $v2;
      
      if (!empty($v2)) {
        $DB->insert_record("sassessment_responses", $add);
      }
    }
  }

}


if (isset($sassessment) && $a == "load") {
  if (is_object($sassessment)) {
    $data = array();
    
    for($i=1;$i<=10;$i++){
      if (!empty($sassessment->{"var".$i})) 
        $data["itemd"][$i] = $sassessment->{"var".$i};
      
      
      if (!empty($sassessment->{"varcheck".$i})) 
        $data["varcheck"][$i] = 1;
      else
        $data["varcheck"][$i] = 0;
    }
    
    if ($responses = $DB->get_records("sassessment_responses", array("aid"=>$sassessment->id))) {
      foreach($responses as $k=>$v){
        $data["resp"][$v->iid][$v->rid] = $v->text;
      }
    }
    
    echo json_encode($data);
  }
}


if ($a == "loadScore") {
  if (isset($sassessment) && is_object($sassessment)) {
    $data = array();
    
    if ($scores = $DB->get_records("sassessment_scoretexts", array("aid"=>$sassessment->id), "sfrom ASC")) {
      foreach($scores as $k=>$v){
        $v->score = $v->sfrom."-".$v->sto;
        $data[$v->score] = $v->scoretext;
      }
    } else {
      $data[get_string('score_20_title', 'sassessment')] = get_string('score_20', 'sassessment');
      $data[get_string('score_40_title', 'sassessment')] = get_string('score_40', 'sassessment');
      $data[get_string('score_60_title', 'sassessment')] = get_string('score_60', 'sassessment');
      $data[get_string('score_80_title', 'sassessment')] = get_string('score_80', 'sassessment');
      $data[get_string('score_100_title', 'sassessment')] = get_string('score_100', 'sassessment');
    }
    
    echo json_encode($data);
  } else {
      $data = array();
      $data[get_string('score_20_title', 'sassessment')] = get_string('score_20', 'sassessment');
      $data[get_string('score_40_title', 'sassessment')] = get_string('score_40', 'sassessment');
      $data[get_string('score_60_title', 'sassessment')] = get_string('score_60', 'sassessment');
      $data[get_string('score_80_title', 'sassessment')] = get_string('score_80', 'sassessment');
      $data[get_string('score_100_title', 'sassessment')] = get_string('score_100', 'sassessment');
      
      echo json_encode($data);
  }
}
