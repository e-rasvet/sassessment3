<?php

    require_once("../../config.php");
    require_once("lib.php");

    /*
    file_put_contents("debug.txt", "\nNEW REQUEST\n", FILE_APPEND);
    foreach ($_SERVER as $key => $value){
      file_put_contents("debug.txt", $key."::".$value."\n", FILE_APPEND);
    }
    */

    $fileid         = optional_param('file', NULL, PARAM_INT);

    if (!$file     = sassessment_getfile($fileid)){
      $file     = sassessment_getfileid($fileid);
    }

    header("Content-type: audio/x-mpeg");

    if (isset($_SERVER['HTTP_RANGE']))  {
        sassessment_rangeDownload($file->fullpatch);
    } else {
      header("Content-Length: ".filesize($file->fullpatch));
      readfile($file->fullpatch);
    }

/*
$filename = 'audio/1/1.mp3';

if(file_exists($filename)) {
    header('Content-Type: audio/mpeg');
    header('Content-Disposition: filename="test.mp3"');
    header('Content-length: '.filesize($filename));
    header('Cache-Control: no-cache');
    header("Content-Transfer-Encoding: chunked");

    readfile($filename);
} else {
    header("HTTP/1.0 404 Not Found");
}
*/
