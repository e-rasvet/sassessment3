<?php
/**
 * Created by PhpStorm.
 * User: Свет
 * Date: 14.02.2017
 * Time: 15:47
 */

require_once '../../config.php';
require_once 'lib.php';

/*
print_r ($_FILES);
print_r ($_POST);
echo "DONE";
die();
*/

//file_put_contents('/var/www/html/moodle3/mod/sassessment/debug.txt', json_encode($_POST), FILE_APPEND);

$id = optional_param('id', NULL, PARAM_INT);
$fid = optional_param('fid', NULL, PARAM_INT);
$uid = optional_param('uid', NULL, PARAM_INT);
$var = optional_param('var', NULL, PARAM_INT);
$sstText = optional_param('sstText', NULL, PARAM_TEXT);

$filename = "record_aac_" . date("Ymd") . "_" . rand(999, 99999);

$student = $DB->get_record("user", array("id" => $uid));

if (!empty($id))
    $context = context_module::instance($id);
else
    $context = context_user::instance($uid);

$fs = get_file_storage();

$file_record = new stdClass;


if (!empty($id)) {
    $file_record->component = 'mod_sassessment';
    $file_record->filearea = 'private';
} else {
    $file_record->component = 'user';
    $file_record->filearea = 'public';
}


$s = 1;
$nfid = (int)substr(time(), 2) . rand(0, 9) + 0;
if ($files = $fs->get_area_files($context->id, 'mod_sassessment', 'private', $nfid)) {
    $s = 2;
    $nfid = rand(1, 999999999);
    while ($files = $fs->get_area_files($context->id, 'mod_sassessment', 'private', $nfid)) {
        $s = 3;
        $nfid = rand(1, 999999999);
    }
}


$file_record->contextid = $context->id;
$file_record->userid = $uid;
$file_record->filepath = "/";
$file_record->itemid = $nfid;
$file_record->license = $CFG->sitedefaultlicense;
$file_record->author = fullname($student);
$file_record->source = '';
$file_record->filename = $filename . ".aac";


$itemid = $fs->create_file_from_pathname($file_record, $_FILES['record']['tmp_name']);


$add = new stdClass();
$add->instance = $id;
$add->fileid = $itemid->get_id();
$add->sourcefileid = $fid;
$add->var = $var;
$add->userid = $uid;
$add->time = time();

if (!empty($sstText)) {
    $add->text = str_replace('"', "'", $sstText);
}

$DB->insert_record("sassessment_appfiles", $add);


$item = $DB->get_record("files", array("id" => $itemid->get_id()));

echo json_encode(array("id" => $item->id));
