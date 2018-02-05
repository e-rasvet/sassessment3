<?php // $Id: uploadmp3.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $


require_once '../../config.php';
require_once 'lib.php';

$filename = optional_param('name', NULL, PARAM_TEXT);
$file = optional_param('audio', NULL, PARAM_TEXT);
$p = optional_param('p', NULL, PARAM_TEXT);
$fid  = optional_param('fid', 0, PARAM_INT);

$p = json_decode(urldecode($p));

$id = $p->id;
$userid = $p->userid;

$file = base64_decode($file);

$student = $DB->get_record("user", array("id" => $userid));

if (!empty($id))
    $context = context_module::instance($id);
else
    $context = context_course::instance($userid);

$fs = get_file_storage();

///Delete old records
$fs->delete_area_files($context->id, 'mod_sassessment', 'private', $fid);

$file_record = new stdClass();

if (!empty($id)) {
    $file_record->component = 'mod_sassessment';
    $file_record->filearea = 'private';
} else {
    $file_record->component = 'user';
    $file_record->filearea = 'public';
}

if (isset($p->itemid) && is_numeric($p->itemid))
    $fid = $p->itemid;
else {
    if (!empty($id)) {
        if (!$data = $DB->get_record_sql("SELECT itemid FROM {files} WHERE component='mod_sassessment' AND filearea='private' ORDER BY itemid DESC LIMIT 1", array($context->id))) { //AND contextid=?
            $fid = 1;
        } else {
            $fid = $data->itemid + 1;
        }
    } else {
        if (!$data = $DB->get_record_sql("SELECT itemid FROM {files} WHERE component='user' AND filearea='public' ORDER BY itemid DESC LIMIT 1", array($context->id))) {
            $fid = 1;
        } else {
            $fid = $data->itemid + 1;
        }
    }
}


$file_record->contextid = $context->id;
$file_record->userid = $userid;
$file_record->filepath = "/";
$file_record->itemid = $fid;
$file_record->license = $CFG->sitedefaultlicense;
$file_record->author = fullname($student);
$file_record->source = '';
$file_record->filename = $filename . ".mp3";

$to = $CFG->dataroot . "/temp/" . $filename . ".mp3";

file_put_contents($to, $file);

$itemid = $fs->create_file_from_pathname($file_record, $to);

$json = array("id" => $itemid->get_id());

$item = $DB->get_record("files", array("id" => $itemid->get_id()));

echo json_encode(array("id" => $fid, "url" => "/pluginfile.php/" . $item->contextid . "/mod_sassessment/" . $id . "/" . $item->id . "/" . $item->filename));
//echo json_encode(array("id"=>$itemid->get_id(), "url"=>(new moodle_url("/mod/sassessment/js/recorder.swf"))));

unlink($to);

    