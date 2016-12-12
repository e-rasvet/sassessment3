<?php

  require_once '../../config.php';
  require_once 'lib.php';


  $text1                      = optional_param('text1', 0, PARAM_TEXT); 
  $text2                      = optional_param('text2', 0, PARAM_TEXT); 
  $id                         = optional_param('aid', 0, PARAM_INT); 
  

  $sassessment  = $DB->get_record('sassessment', array('id' => $id), '*', MUST_EXIST);
  
  echo sassessment_scoreFilter(round(sassessment_similar_text($text1, $text2)), $sassessment);