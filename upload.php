<?php

//print_r ($_POST);
//print_r ($_FILES);

    require_once '../../config.php';
    require_once 'lib.php';
    
    $filename                     = optional_param('fname', NULL, PARAM_TEXT);
    $id                           = optional_param('id', NULL, PARAM_INT);
    $itemid                       = optional_param('itemid', NULL, PARAM_INT);

    $student = $DB->get_record("user", array("id" => $USER->id));
    
    if (!empty($id))
      $context = context_module::instance($id);
    else
      $context = context_user::instance($USER->id);
    
    $fs = get_file_storage();
    
///Delete old records
    //$fs->delete_area_files($context->id, 'mod_sassessment', 'private', $fid);
      
    $file_record = new stdClass;
    
    if (!empty($id)) {
      $file_record->component = 'mod_sassessment';
      $file_record->filearea  = 'private';
    } else {
      $file_record->component = 'user';
      $file_record->filearea  = 'public';
    }
    
    
    if(isset($itemid) && is_numeric($itemid)) {
      $s = 0;
      $fid                    = $itemid;
    } else {
      $s = 1;
      
      $fid                    = (int)substr(time(), 2).rand(0,9) + 0;
      if ($files = $fs->get_area_files($context->id, 'mod_sassessment', 'private', $fid)){
        $s = 2;
        $fid = rand(1, 999999999);
        while ($files = $fs->get_area_files($context->id, 'mod_sassessment', 'private', $fid)) {
          $s = 3;
          $fid = rand(1, 999999999);
        }
      }
    }
    
    
    $file_record->contextid = $context->id;
    $file_record->userid    = $USER->id;
    $file_record->filepath  = "/";
    $file_record->itemid    = $fid;
    $file_record->license   = $CFG->sitedefaultlicense;
    $file_record->author    = fullname($student);
    $file_record->source    = '';
    $file_record->filename  = $filename;
    
    
    $itemid = $fs->create_file_from_pathname($file_record, $_FILES['data']['tmp_name']);
    
    $json = array("id" => $itemid->get_id());
    
    $item = $DB->get_record("files", array("id"=>$itemid->get_id()));
    
    //echo json_encode(array("id"=>$fid, "url"=>"/pluginfile.php/".$item->contextid."/mod_sassessment/".$id."/".$item->id."/".$item->filename, "text"=>sassessment_runExternal("python /var/www/html/moodle/_py/speechtotext.py {$_FILES['data']['tmp_name']}")));
    echo json_encode(array("id"=>$fid, "url"=>"/pluginfile.php/".$item->contextid."/mod_sassessment/".$id."/".$item->id."/".$item->filename, "fullurl"=>$CFG->wwwroot."/pluginfile.php/".$item->contextid."/mod_sassessment/".$id."/".$item->id."/".$item->filename, "text"=>"ERROR01"));

    