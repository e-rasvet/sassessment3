<?php

  require_once '../../config.php';
  require_once 'lib.php';


  $text1                      = optional_param('text1', 0, PARAM_TEXT); 
  $text2                      = optional_param('text2', 0, PARAM_TEXT); 
  $id                         = optional_param('aid', 0, PARAM_INT); 
  $iid                         = optional_param('iid', 0, PARAM_INT);


  $sassessment  = $DB->get_record('sassessment', array('id' => $id), '*', MUST_EXIST);


$maxp = 0;
$maxi = 1;
$maxtext = "";

if ($sampleresponses = $DB->get_records("sassessment_responses", array("aid" => $sassessment->id, "iid" => $iid))) {
    foreach ($sampleresponses as $sampleresponse) {
        $percent = sassessment_similar_text($sampleresponse->text, $text2);
        if ($maxp < $percent) {
            $maxi = $k;
            $maxp = $percent;
            $maxtext = $sampleresponse->text;
        }
    }
}

  //echo sassessment_scoreFilter(round(sassessment_similar_text($text1, $text2)), $sassessment);
  echo sassessment_scoreFilter(round($maxp), $sassessment);