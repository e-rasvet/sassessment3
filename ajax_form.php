<?php
/**
 * Created by PhpStorm.
 * User: Свет
 * Date: 30.05.2017
 * Time: 12:30
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id   = optional_param('id', 0, PARAM_CLEAN); // course_module ID, or
$a    = optional_param('a', 'save', PARAM_ALPHA);
$i    = optional_param('i', NULL, PARAM_CLEAN);
$fileID    = optional_param('fileID', NULL, PARAM_CLEAN);
$t    = optional_param('t', 'file', PARAM_CLEAN);

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


if ($a == "delete") {
    if ($subfiles = $DB->get_records("files", array("itemid"=>$fileID))){
        foreach($subfiles as $subfile){
            $fs = get_file_storage();

            $file = $fs->get_file($subfile->contextid, $subfile->component, $subfile->filearea,
                $subfile->itemid, $subfile->filepath, $subfile->filename);

            if ($file) {
                $file->delete();
            }
        }
    }

    echo $t.$i."_".$sassessment->id;
    $DB->set_field("sassessment", $t.$i, 0, array("id"=>$sassessment->id));
}