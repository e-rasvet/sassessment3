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
 * Library of interface functions and constants for module sassessment
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the sassessment specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_sassessment
 * @copyright  2014 Igor Nikulin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('SASSESSMENT_VIDEOTYPES', json_encode(array("video/quicktime", "video/mp4", "video/3gp", "video/3gpp", "video/x-ms-wmv")));
define('SASSESSMENT_AUDIOTYPES', json_encode(array("audio/x-wav", "audio/mpeg", "audio/wav", "audio/mp4a", "audio/mp4", "audio/mp3", "audio/3gpp")));

defined('MOODLE_INTERNAL') || die();


/** example constant */
//define('sassessment_ULTIMATE_ANSWER', 42);

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function sassessment_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return false;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_ADVANCED_GRADING:        return true;

        default: return null;
    }
}


function sassessment_grading_areas_list(){
  return array('submission' => get_string('submissions', 'mod_sassessment'));
}

/**
 * Saves a new instance of the sassessment into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $sassessment An object from the form in mod_form.php
 * @param mod_sassessment_mod_form $mform
 * @return int The id of the newly inserted sassessment record
 */
function sassessment_add_instance(stdClass $sassessment, mod_sassessment_mod_form $mform = null) {
    global $DB;

    $sassessment->timecreated = time();
    
    if (!isset($sassessment->transcribe)) $sassessment->transcribe = 0;
    if (!isset($sassessment->audio)) $sassessment->audio = 0;
    if (!isset($sassessment->textanalysis)) $sassessment->textanalysis = 0;
    if (!isset($sassessment->textcomparison)) $sassessment->textcomparison = 0;
    if (!isset($sassessment->humanevaluation)) $sassessment->humanevaluation = 0;
    if (!isset($sassessment->timedue)) $sassessment->timedue = 0;
    if (!isset($sassessment->autodelete)) $sassessment->autodelete = 0;
    
    for($i=1;$i<=10;$i++){
      if(!empty($_POST['var'.$i])) {
        $sassessment->{'var'.$i} = $_POST['var'.$i];
      }
      
      if(!empty($_POST['resp_check_'.$i])) {
        $sassessment->{'varcheck'.$i} = 1;
      } else {
        $sassessment->{'varcheck'.$i} = 0;
      }
      
      if (!empty($_POST['submitfile'][$i])){
        if ($file = sassessment_getfile($_POST['submitfile'][$i])){
          $add = new stdClass;
          $add->id = $file->id;
          $add->filearea = 'public';
          
          $DB->update_record('files', $add);
          
          $sassessment->{'file'.$i} = $_POST['submitfile'][$i];
        }
      }
      
      if (!empty($_POST['submitfile2'][$i])){
        if ($file = sassessment_getfile($_POST['submitfile2'][$i])){
          $add = new stdClass;
          $add->id = $file->id;
          $add->filearea = 'public';
          
          $DB->update_record('files', $add);
          
          $sassessment->{'filesr'.$i} = $_POST['submitfile2'][$i];
        }
      }
    }
    

    # You may have to add extra stuff in here #

    $id = $DB->insert_record('sassessment', $sassessment);
    $sassessment->id = $id;
    
    $add = new stdClass;
    $add->aid = $id;

    for($i=1;$i<=10;$i++){
      if(!empty($_POST['var'.$i])) {
        $add->iid = $i;
        for($j=1;$j<=10;$j++){
          if(!empty($_POST['resp_'.$i.'_'.$j])) {
            $add->rid  = $j;
            $add->text = $_POST['resp_'.$i.'_'.$j];

            $DB->insert_record("sassessment_responses", $add);
          }
        }
      }
    }
    
    
    $add = new stdClass;
    $add->aid = $sassessment->id;

    for($i=1;$i<=10;$i++){
      if(!empty($_POST['score_'.$i])) {
        list($sfrom, $sto) = explode("-", $_POST['score_'.$i]);
        $add->sfrom = $sfrom;
        $add->sto = $sto;
        $add->scoretext = $_POST['score_text_'.$i];
        
        $DB->insert_record("sassessment_scoretexts", $add);
      }
    }
    
    //if ($sassessment->grademethod == "default")
      sassessment_grade_item_update($sassessment);
      
    if ($sassessment->grademethod == "rubricauto") {
        $DB->set_field("grade_items", "grademax", 100, array("courseid" => $sassessment->course, "iteminstance"=> $sassessment->id, "itemmodule" => 'sassessment'));
        $DB->set_field("sassessment", "grade", 100, array("id"=> $sassessment->id));
        $sassessment->grade = 100;
    } else {
        $DB->set_field("grade_items", "grademax", 5, array("courseid" => $sassessment->course, "iteminstance"=> $sassessment->id, "itemmodule" => 'sassessment'));
        $DB->set_field("sassessment", "grade", 5, array("id"=> $sassessment->id));
        $sassessment->grade = 5;
    }
    
    return $id;
}

/**
 * Updates an instance of the sassessment in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $sassessment An object from the form in mod_form.php
 * @param mod_sassessment_mod_form $mform
 * @return boolean Success/Fail
 */
function sassessment_update_instance(stdClass $sassessment, mod_sassessment_mod_form $mform = null) {
    global $DB;
    
    
    $sassessment->timemodified = time();
    $sassessment->id = $sassessment->instance;
    
    if (!isset($sassessment->transcribe)) $sassessment->transcribe = 0;
    if (!isset($sassessment->audio)) $sassessment->audio = 0;
    if (!isset($sassessment->textanalysis)) $sassessment->textanalysis = 0;
    if (!isset($sassessment->textcomparison)) $sassessment->textcomparison = 0;
    if (!isset($sassessment->humanevaluation)) $sassessment->humanevaluation = 0;
    if (!isset($sassessment->timedue)) $sassessment->timedue = 0;
    if (!isset($sassessment->autodelete)) $sassessment->autodelete = 0;

    
    for($i=1;$i<=10;$i++){
      if(!empty($_POST['var'.$i])) {
        $sassessment->{'var'.$i} = $_POST['var'.$i];
      } else {
        $sassessment->{'var'.$i} = "";
      }
      
      if(!empty($_POST['resp_check_'.$i])) {
        $sassessment->{'varcheck'.$i} = 1;
      } else {
        $sassessment->{'varcheck'.$i} = 0;
      }
      
      if (!empty($_POST['submitfile'][$i])){
        if ($file = sassessment_getfile($_POST['submitfile'][$i])){
          $add = new stdClass;
          $add->id = $file->id;
          $add->filearea = 'public';
          
          $DB->update_record('files', $add);
          
          $sassessment->{'file'.$i} = $_POST['submitfile'][$i];
        }
      } else {
        $sassessment->{'file'.$i} = "";
      }
      
      if (!empty($_POST['submitfile2'][$i])){
        if ($file = sassessment_getfile($_POST['submitfile2'][$i])){
          $add = new stdClass;
          $add->id = $file->id;
          $add->filearea = 'public';
          
          $DB->update_record('files', $add);
          
          $sassessment->{'filesr'.$i} = $_POST['submitfile2'][$i];
        }
      } else {
        $sassessment->{'filesr'.$i} = "";
      }
    }
    
    
    $DB->delete_records("sassessment_responses", array("aid"=>$sassessment->id));
    

    $add = new stdClass;
    $add->aid = $sassessment->id;

    for($i=1;$i<=10;$i++){
      if(!empty($_POST['var'.$i])) {
        $add->iid = $i;
        for($j=1;$j<=10;$j++){
          if(!empty($_POST['resp_'.$i.'_'.$j])) {
            $add->rid  = $j;
            $add->text = $_POST['resp_'.$i.'_'.$j];

            $DB->insert_record("sassessment_responses", $add);
          }
        }
      }
    }
    
    
    $DB->delete_records("sassessment_scoretexts", array("aid"=>$sassessment->id));
    
    $add = new stdClass;
    $add->aid = $sassessment->id;

    for($i=1;$i<=10;$i++){
      if(!empty($_POST['score_'.$i])) {
        list($sfrom, $sto) = explode("-", $_POST['score_'.$i]);
        $add->sfrom = $sfrom;
        $add->sto = $sto;
        $add->scoretext = $_POST['score_text_'.$i];
        
        $DB->insert_record("sassessment_scoretexts", $add);
      }
    }
    
    //if ($sassessment->grademethod == "default")
      sassessment_grade_item_update($sassessment);
      
    if ($sassessment->grademethod == "rubricauto") {
        $DB->set_field("grade_items", "grademax", 100, array("courseid" => $sassessment->course, "iteminstance"=> $sassessment->id, "itemmodule" => 'sassessment'));
        $DB->set_field("sassessment", "grade", 100, array("id"=> $sassessment->id));
        $sassessment->grade = 100;
    } else {
        $DB->set_field("grade_items", "grademax", 5, array("courseid" => $sassessment->course, "iteminstance"=> $sassessment->id, "itemmodule" => 'sassessment'));
        $DB->set_field("sassessment", "grade", 5, array("id"=> $sassessment->id));
        $sassessment->grade = 5;
    }

    # You may have to add extra stuff in here #

    return $DB->update_record('sassessment', $sassessment);
}

/**
 * Removes an instance of the sassessment from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function sassessment_delete_instance($id) {
    global $DB;

    if (! $sassessment = $DB->get_record('sassessment', array('id' => $id))) {
        return false;
    }

    # Delete any dependent records here #

    $DB->delete_records('sassessment', array('id' => $sassessment->id));

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
 
function sassessment_cron () {
    global $DB;

    if ($assessments = $DB->get_records_sql("SELECT * FROM {sassessment} WHERE autodelete > 0")) {
        foreach ($assessments as $assessment){
            $time = time() - $assessment->autodelete * 3600 * 24;

            if ($files = $DB->get_records_sql("SELECT * FROM {sassessment_studdent_answers} WHERE timecreated < {$time}")){
                foreach ($files as $file){
                    for($i=1;$i<=10;$i++){
                        if (!empty($file->{'file'.$i})){
                            $DB->set_field("sassessment_studdent_answers", 'file'.$i, 0, array("id"=>$file->id));

                            if ($subfiles = $DB->get_records("files", array("itemid"=>$file->{'file'.$i}))){
                                foreach($subfiles as $subfile){
                                    $fs = get_file_storage();

                                    $file = $fs->get_file($subfile->contextid, $subfile->component, $subfile->filearea,
                                        $subfile->itemid, $subfile->filepath, $subfile->filename);

                                    if ($file) {
                                        $file->delete();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return true;
}


function sassessment_runExternal( $cmd, &$code ) {

   $descriptorspec = array(
       0 => array("pipe", "r"), 
       1 => array("pipe", "w"), 
       2 => array("pipe", "w") 
   );

   $pipes= array();
   $process = proc_open($cmd, $descriptorspec, $pipes);

   $output= "";

   if (!is_resource($process)) return false;

   fclose($pipes[0]);

   stream_set_blocking($pipes[1],false);
   stream_set_blocking($pipes[2],false);

   $todo= array($pipes[1],$pipes[2]);

   while( true ) {
       $read= array();
       if( !feof($pipes[1]) ) $read[]= $pipes[1];
       if( !feof($pipes[2]) ) $read[]= $pipes[2];

       if (!$read) break;

       $ready= stream_select($read, $write=NULL, $ex= NULL, 2);

       if ($ready === false) {
           break; 
       }

       foreach ($read as $r) {
           $s= fread($r,1024);
           $output.= $s;
       }
   }

   fclose($pipes[1]);
   fclose($pipes[2]);

   $code= proc_close($process);

   return $output;
}


function sassessment_similar_text($text1, $text2){
  global $CFG;
  
  //make_temp_directory('sassessment/cmp');
  //$tmpdir = $CFG->dirroot . '/mod/sassessment/tmp';
  //$tmpdir = $CFG->tempdir . '/sassessment/cmp';
  
  //$name = md5($text1.$text2);
  
  ///if (is_file($tmpdir."/".$name)) {
  //  return file_get_contents($tmpdir."/".$name);
  //} else {
    $res = sassessment_cmp_phon($text1, $text2);
    //file_put_contents($tmpdir."/".$name, $res['percent']);
    
    return $res['percent'];
  //}

  //$text1 = strtolower(preg_replace("/[^A-Za-z0-9]/",'',$text1));
  //$text2 = strtolower(preg_replace("/[^A-Za-z0-9]/",'',$text2));
  
  //similar_text($text1, $text2, $percent); 
  //$res = sassessment_cmp_phon($text1, $text2);
  
  //return $percent;
  //return $res['percent'];
}


function sassessment_cmp_phon($spoken, $target){
    global $CFG;
  
    $time_start = microtime(true); 
  
    if (!isset($CFG->pron_dict_loaded)) {
      $lines = explode("\n",file_get_contents($CFG->dirroot . '/mod/sassessment/pron-dict.txt'));

      $pron_dict = array();

      foreach($lines as $line){
        $elements = explode(",",$line);
        $pron_dict[$elements[0]] = $elements[1];
      }
      
      $CFG->pron_dict_loaded = $pron_dict;
    } else
      $pron_dict = $CFG->pron_dict_loaded;

    if (isset($lines))
      foreach($lines as $line){
        $elements=explode(",",$line);
        $pron_dict[$elements[0]]=$elements[1];
      }

    // Set up two objects, spoken and target

    $spoken_obj=new stdClass;
    $target_obj=new stdClass;

    $spoken_obj->raw=$spoken;
    $spoken_obj->clean=strtolower(preg_replace("/[^a-zA-Z0-9' ]/","",$spoken_obj->raw));
    $spoken_obj->words=array_filter(explode(" ", $spoken_obj->clean));
    $spoken_obj->phonetic=array();

    // Convert each spoken word to phonetic script

    foreach($spoken_obj->words as $word){
      if(array_key_exists(strtoupper($word), $pron_dict)){
        $spoken_obj->phonetic[]=$pron_dict[strtoupper($word)];
      }
      else{
        $spoken_obj->phonetic[]=$word;
      }
    }

    $target_obj->raw=$target;
    $target_obj->clean=strtolower(preg_replace("/[^a-zA-Z0-9' ]/","",$target_obj->raw));
    $target_obj->words=array_filter(explode(" ", $target_obj->clean));
    $target_obj->phonetic=array();

    // Convert each target word to phonetic script

    foreach($target_obj->words as $word){
      if(array_key_exists(strtoupper($word), $pron_dict)){
        $target_obj->phonetic[]=$pron_dict[strtoupper($word)];
      }
      else{
        $target_obj->phonetic[]=$word;
      }
    }

    // Check for matches

    $matched=array();
    $unmatched=array();
    $score=0;

    foreach($target_obj->phonetic as $index=>$word){
      if(in_array($word, $spoken_obj->phonetic)){
        $score++;
        if(!in_array($target_obj->words[$index], $matched)){
          $matched[]=$target_obj->words[$index];
        }
      }
      else if(!in_array($word, $spoken_obj->phonetic)){
        if(!in_array($target_obj->words[$index], $unmatched)){
          $unmatched[]=$target_obj->words[$index];
        }
      }
    }
    $percent=round($score/count($spoken_obj->words)*100);
    return array("spoken"=>$spoken_obj,"target"=>$target_obj,"matched"=>$matched,"unmatched"=>$unmatched,"percent"=>$percent);
}


function sassessment_scoreFilter($score, $sassessment){
  global $DB;

  if (empty($score))
      return false;
  
  if ($sassessment->scoretype == 0) {
    return $score."%";
  } else {
    if ($scoreData = $DB->get_records("sassessment_scoretexts", array("aid"=>$sassessment->id))){
      foreach ($scoreData as $sc){
        if ($score >= $sc->sfrom && $score <= $sc->sto){
          return $sc->scoretext;
        }
      }
      
      return $score."%";
    } else {
      return $score."%";
    }
  }
}


////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function sassessment_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for sassessment file areas
 *
 * @package mod_sassessment
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function sassessment_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}



////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding sassessment nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the sassessment module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function sassessment_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the sassessment settings
 *
 * This function is called when the context for the page is a sassessment module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $sassessmentnode {@link navigation_node}
 */
function sassessment_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $sassessmentnode=null) {
}





/** Include eventslib.php */
require_once($CFG->libdir.'/eventslib.php');
/** Include formslib.php */
require_once($CFG->libdir.'/formslib.php');
/** Include calendar/lib.php */
require_once($CFG->dirroot.'/calendar/lib.php');

/** sassessment_COUNT_WORDS = 1 */
define('sassessment_COUNT_WORDS', 1);
/** sassessment_COUNT_LETTERS = 2 */
define('sassessment_COUNT_LETTERS', 2);

/**
 * Standard base class for all sassessment submodules (sassessment types).
 *
 * @package   mod-sassessment
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sassessment_base {

    const FILTER_ALL             = 0;
    const FILTER_SUBMITTED       = 1;
    const FILTER_REQUIRE_GRADING = 2;

    /** @var object */
    var $cm;
    /** @var object */
    var $course;
    /** @var stdClass */
    var $coursecontext;
    /** @var object */
    var $sassessment;
    /** @var string */
    var $strsassessment;
    /** @var string */
    var $strsassessments;
    /** @var string */
    var $strsubmissions;
    /** @var string */
    var $strlastmodified;
    /** @var string */
    var $pagetitle;
    /** @var bool */
    var $usehtmleditor;
    /**
     * @todo document this var
     */
    var $defaultformat;
    /**
     * @todo document this var
     */
    var $context;
    /** @var string */
    var $type;

    /**
     * Constructor for the base sassessment class
     *
     * Constructor for the base sassessment class.
     * If cmid is set create the cm, course, sassessment objects.
     * If the sassessment is hidden and the user is not a teacher then
     * this prints a page header and notice.
     *
     * @global object
     * @global object
     * @param int $cmid the current course module id - not set for new sassessments
     * @param object $sassessment usually null, but if we have it we pass it to save db access
     * @param object $cm usually null, but if we have it we pass it to save db access
     * @param object $course usually null, but if we have it we pass it to save db access
     */
    public function __construct($cmid='staticonly', $sassessment=NULL, $cm=NULL, $course=NULL) {
        global $COURSE, $DB;

        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }

        global $CFG;

        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('sassessment', $cmid)) {
            print_error('invalidcoursemodule');
        }

        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id'=>$this->cm->course))) {
            print_error('invalidid', 'sassessment');
        }
        $this->coursecontext = context_course::instance($this->course->id);
        $courseshortname = format_text($this->course->shortname, true, array('context' => $this->coursecontext));

        if ($sassessment) {
            $this->sassessment = $sassessment;
        } else if (! $this->sassessment = $DB->get_record('sassessment', array('id'=>$this->cm->instance))) {
            print_error('invalidid', 'sassessment');
        }

        $this->sassessment->cmidnumber = $this->cm->idnumber; // compatibility with modedit sassessment obj
        $this->sassessment->courseid   = $this->course->id; // compatibility with modedit sassessment obj

        $this->strsassessment = get_string('modulename', 'sassessment');
        $this->strsassessments = get_string('modulenameplural', 'sassessment');
        $this->strsubmissions = get_string('submissions', 'sassessment');
        $this->strlastmodified = get_string('lastmodified');
        $this->pagetitle = strip_tags($courseshortname.': '.$this->strsassessment.': '.format_string($this->sassessment->name, true, array('context' => $this->context)));

        // visibility handled by require_login() with $cm parameter
        // get current group only when really needed

    /// Set up things for a HTML editor if it's needed
        $this->defaultformat = editors_get_preferred_format();
    }
    
    
    public function sassessment_base($cmid='staticonly', $sassessment=NULL, $cm=NULL, $course=NULL) {
        self::__construct($cmid='staticonly', $sassessment=NULL, $cm=NULL, $course=NULL);
    }

    /**
     * Display the sassessment, used by view.php
     *
     * This in turn calls the methods producing individual parts of the page
     */
    function view() {
        $context = context_module::instance($this->cm->id);
        require_capability('mod/sassessment:view', $context);

        //add_to_log($this->course->id, "sassessment", "view", "view.php?id={$this->cm->id}",
        //           $this->sassessment->id, $this->cm->id);

        $this->view_header();

        $this->view_intro();

        $this->view_dates();

        $this->view_feedback();

        $this->view_footer();
    }

    /**
     * Display the header and top of a page
     *
     * (this doesn't change much for sassessment types)
     * This is used by the view() method to print the header of view.php but
     * it can be used on other pages in which case the string to denote the
     * page in the navigation trail should be passed as an argument
     *
     * @global object
     * @param string $subpage Description of subpage to be used in navigation trail
     */
    function view_header($subpage='') {
        global $CFG, $PAGE, $OUTPUT;

        if ($subpage) {
            $PAGE->navbar->add($subpage);
        }

        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);

        echo $OUTPUT->header();

        groups_print_activity_menu($this->cm, $CFG->wwwroot . '/mod/sassessment/view.php?id=' . $this->cm->id);

        echo '<div class="reportlink">'.$this->submittedlink().'</div>';
        echo '<div class="clearer"></div>';
    }


    /**
     * Display the sassessment intro
     *
     * This will most likely be extended by sassessment type plug-ins
     * The default implementation prints the sassessment description in a box
     */
    function view_intro() {
        global $OUTPUT;
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo format_module_intro('sassessment', $this->sassessment, $this->cm->id);
        echo $OUTPUT->box_end();
        echo plagiarism_print_disclosure($this->cm->id);
    }

    /**
     * Display the sassessment dates
     *
     * Prints the sassessment start and end dates in a box.
     * This will be suitable for most sassessment types
     */
    function view_dates() {
        global $OUTPUT;
        if (!$this->sassessment->timeavailable && !$this->sassessment->timedue) {
            return;
        }

        echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');
        echo '<table>';
        if ($this->sassessment->timeavailable) {
            echo '<tr><td class="c0">'.get_string('availabledate','sassessment').':</td>';
            echo '    <td class="c1">'.userdate($this->sassessment->timeavailable).'</td></tr>';
        }
        if ($this->sassessment->timedue) {
            echo '<tr><td class="c0">'.get_string('duedate','sassessment').':</td>';
            echo '    <td class="c1">'.userdate($this->sassessment->timedue).'</td></tr>';
        }
        echo '</table>';
        echo $OUTPUT->box_end();
    }


    /**
     * Display the bottom and footer of a page
     *
     * This default method just prints the footer.
     * This will be suitable for most sassessment types
     */
    function view_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }

    /**
     * Display the feedback to the student
     *
     * This default method prints the teacher picture and name, date when marked,
     * grade and teacher submissioncomment.
     * If advanced grading is used the method render_grade from the
     * advanced grading controller is called to display the grade.
     *
     * @global object
     * @global object
     * @global object
     * @param object $submission The submission object or NULL in which case it will be loaded
     */
    function view_feedback($submission=NULL) {
        global $USER, $CFG, $DB, $OUTPUT, $PAGE;
        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/grade/grading/lib.php");

        if (!$submission) { /// Get submission for this sassessment
            $userid = $USER->id;
            $submission = $this->get_submission($userid);
        } else {
            $userid = $submission->userid;
        }
        // Check the user can submit
        $canviewfeedback = ($userid == $USER->id && has_capability('mod/sassessment:submit', $this->context, $USER->id, false));
        // If not then check if the user still has the view cap and has a previous submission
        $canviewfeedback = $canviewfeedback || (!empty($submission) && $submission->userid == $USER->id && has_capability('mod/sassessment:view', $this->context));
        // Or if user can grade (is a teacher or admin)
        $canviewfeedback = $canviewfeedback || has_capability('mod/sassessment:grade', $this->context);

        if (!$canviewfeedback) {
            // can not view or submit sassessments -> no feedback
            return;
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'sassessment', $this->sassessment->id, $userid);
        $item = $grading_info->items[0];
        $grade = $item->grades[$userid];

        if ($grade->hidden or $grade->grade === false) { // hidden or error
            return;
        }

        if ($grade->grade === null and empty($grade->str_feedback)) {   /// Nothing to show yet
            return;
        }

        $graded_date = $grade->dategraded;
        $graded_by   = $grade->usermodified;

    /// We need the teacher info
        if (!$teacher = $DB->get_record('user', array('id'=>$graded_by))) {
            print_error('cannotfindteacher');
        }

    /// Print the feedback
        echo $OUTPUT->heading(get_string('feedbackfromteacher', 'sassessment', fullname($teacher)));

        echo '<table cellspacing="0" class="feedback">';

        echo '<tr>';
        echo '<td class="left picture">';
        if ($teacher) {
            echo $OUTPUT->user_picture($teacher);
        }
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        if ($teacher) {
            echo '<div class="fullname">'.fullname($teacher).'</div>';
        }
        echo '<div class="time">'.userdate($graded_date).'</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        $gradestr = '<div class="grade">'. get_string("grade").': '.$grade->str_long_grade. '</div>';
        if (!empty($submission) && $controller = get_grading_manager($this->context, 'mod_sassessment', 'submission')->get_active_controller()) {
            $controller->set_grade_range(make_grades_menu($this->sassessment->grade));
            echo $controller->render_grade($PAGE, $submission->id, $item, $gradestr, has_capability('mod/sassessment:grade', $this->context));
        } else {
            echo $gradestr;
        }
        echo '<div class="clearer"></div>';

        echo '<div class="comment">';
        echo $grade->str_feedback;
        echo '</div>';
        echo '</tr>';

         if ($this->type == 'uploadsingle') { //@TODO: move to overload view_feedback method in the class or is uploadsingle merging into upload?
            $responsefiles = $this->print_responsefiles($submission->userid, true);
            if (!empty($responsefiles)) {
                echo '<tr>';
                echo '<td class="left side">&nbsp;</td>';
                echo '<td class="content">';
                echo $responsefiles;
                echo '</tr>';
            }
         }

        echo '</table>';
    }

    /**
     * Returns a link with info about the state of the sassessment submissions
     *
     * This is used by view_header to put this link at the top right of the page.
     * For teachers it gives the number of submitted sassessments with a link
     * For students it gives the time of their submission.
     * This will be suitable for most sassessment types.
     *
     * @global object
     * @global object
     * @param bool $allgroup print all groups info if user can access all groups, suitable for index.php
     * @return string
     */
    function submittedlink($allgroups=false) {
        global $USER;
        global $CFG;

        $submitted = '';
        $urlbase = "{$CFG->wwwroot}/mod/sassessment/";

        $context = context_module::instance($this->cm->id);
        if (has_capability('mod/sassessment:grade', $context)) {
            if ($allgroups and has_capability('moodle/site:accessallgroups', $context)) {
                $group = 0;
            } else {
                $group = groups_get_activity_group($this->cm);
            }
            if ($this->type == 'offline') {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('viewfeedback', 'sassessment').'</a>';
            } else if ($count = $this->count_real_submissions($group)) {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('viewsubmissions', 'sassessment', $count).'</a>';
            } else {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('noattempts', 'sassessment').'</a>';
            }
        } else {
            if (isloggedin()) {
                if ($submission = $this->get_submission($USER->id)) {
                    // If the submission has been completed
                    if ($this->is_submitted_with_required_data($submission)) {
                        if ($submission->timemodified <= $this->sassessment->timedue || empty($this->sassessment->timedue)) {
                            $submitted = '<span class="early">'.userdate($submission->timemodified).'</span>';
                        } else {
                            $submitted = '<span class="late">'.userdate($submission->timemodified).'</span>';
                        }
                    }
                }
            }
        }

        return $submitted;
    }


    /**
     * @todo Document this function
     */
    function setup_elements(&$mform) {

    }

    /**
     * Any preprocessing needed for the settings form for
     * this sassessment type
     *
     * @param array $default_values - array to fill in with the default values
     *      in the form 'formelement' => 'value'
     * @param object $form - the form that is to be displayed
     * @return none
     */
    function form_data_preprocessing(&$default_values, $form) {
    }

    /**
     * Any extra validation checks needed for the settings
     * form for this sassessment type
     *
     * See lib/formslib.php, 'validation' function for details
     */
    function form_validation($data, $files) {
        return array();
    }

    /**
     * Create a new sassessment activity
     *
     * Given an object containing all the necessary data,
     * (defined by the form in mod_form.php) this function
     * will create a new instance and return the id number
     * of the new instance.
     * The due data is added to the calendar
     * This is common to all sassessment types.
     *
     * @global object
     * @global object
     * @param object $sassessment The data from the form on mod_form.php
     * @return int The id of the sassessment
     */
    function add_instance($sassessment) {
        global $COURSE, $DB;

        $sassessment->timemodified = time();
        $sassessment->courseid = $sassessment->course;

        $returnid = $DB->insert_record("sassessment", $sassessment);
        $sassessment->id = $returnid;

        if ($sassessment->timedue) {
            $event = new stdClass();
            $event->name        = $sassessment->name;
            $event->description = format_module_intro('sassessment', $sassessment, $sassessment->coursemodule);
            $event->courseid    = $sassessment->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'sassessment';
            $event->instance    = $returnid;
            $event->eventtype   = 'due';
            $event->timestart   = $sassessment->timedue;
            $event->timeduration = 0;

            calendar_event::create($event);
        }

        sassessment_grade_item_update($sassessment);

        return $returnid;
    }

    /**
     * Deletes an sassessment activity
     *
     * Deletes all database records, files and calendar events for this sassessment.
     *
     * @global object
     * @global object
     * @param object $sassessment The sassessment to be deleted
     * @return boolean False indicates error
     */
    function delete_instance($sassessment) {
        global $CFG, $DB;

        $sassessment->courseid = $sassessment->course;

        $result = true;

        // now get rid of all files
        $fs = get_file_storage();
        if ($cm = get_coursemodule_from_instance('sassessment', $sassessment->id)) {
            $context = context_module::instance($cm->id);
            $fs->delete_area_files($context->id);
        }

        if (! $DB->delete_records('sassessment_submissions', array('sassessment'=>$sassessment->id))) {
            $result = false;
        }

        if (! $DB->delete_records('event', array('modulename'=>'sassessment', 'instance'=>$sassessment->id))) {
            $result = false;
        }

        if (! $DB->delete_records('sassessment', array('id'=>$sassessment->id))) {
            $result = false;
        }
        $mod = $DB->get_field('modules','id',array('name'=>'sassessment'));

        sassessment_grade_item_delete($sassessment);

        return $result;
    }

    /**
     * Updates a new sassessment activity
     *
     * Given an object containing all the necessary data,
     * (defined by the form in mod_form.php) this function
     * will update the sassessment instance and return the id number
     * The due date is updated in the calendar
     * This is common to all sassessment types.
     *
     * @global object
     * @global object
     * @param object $sassessment The data from the form on mod_form.php
     * @return bool success
     */
    function update_instance($sassessment) {
        global $COURSE, $DB;

        $sassessment->timemodified = time();

        $sassessment->id = $sassessment->instance;
        $sassessment->courseid = $sassessment->course;

        $DB->update_record('sassessment', $sassessment);

        if ($sassessment->timedue) {
            $event = new stdClass();

            if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'sassessment', 'instance'=>$sassessment->id))) {

                $event->name        = $sassessment->name;
                $event->description = format_module_intro('sassessment', $sassessment, $sassessment->coursemodule);
                $event->timestart   = $sassessment->timedue;

                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event);
            } else {
                $event = new stdClass();
                $event->name        = $sassessment->name;
                $event->description = format_module_intro('sassessment', $sassessment, $sassessment->coursemodule);
                $event->courseid    = $sassessment->course;
                $event->groupid     = 0;
                $event->userid      = 0;
                $event->modulename  = 'sassessment';
                $event->instance    = $sassessment->id;
                $event->eventtype   = 'due';
                $event->timestart   = $sassessment->timedue;
                $event->timeduration = 0;

                calendar_event::create($event);
            }
        } else {
            $DB->delete_records('event', array('modulename'=>'sassessment', 'instance'=>$sassessment->id));
        }

        // get existing grade item
        sassessment_grade_item_update($sassessment);

        return true;
    }

    /**
     * Update grade item for this submission.
     */
    function update_grade($submission) {
        sassessment_update_grades($this->sassessment, $submission->userid);
    }

    /**
     * Top-level function for handling of submissions called by submissions.php
     *
     * This is for handling the teacher interaction with the grading interface
     * This should be suitable for most sassessment types.
     *
     * @global object
     * @param string $mode Specifies the kind of teacher interaction taking place
     */
    function submissions($mode) {
        ///The main switch is changed to facilitate
        ///1) Batch fast grading
        ///2) Skip to the next one on the popup
        ///3) Save and Skip to the next one on the popup

        //make user global so we can use the id
        global $USER, $OUTPUT, $DB, $PAGE;

        $mailinfo = optional_param('mailinfo', null, PARAM_BOOL);

        if (optional_param('next', null, PARAM_BOOL)) {
            $mode='next';
        }
        if (optional_param('saveandnext', null, PARAM_BOOL)) {
            $mode='saveandnext';
        }

        if (is_null($mailinfo)) {
            if (optional_param('sesskey', null, PARAM_BOOL)) {
                set_user_preference('sassessment_mailinfo', 0);
            } else {
                $mailinfo = get_user_preferences('sassessment_mailinfo', 0);
            }
        } else {
            set_user_preference('sassessment_mailinfo', $mailinfo);
        }

        if (!($this->validate_and_preprocess_feedback())) {
            // form was submitted ('Save' or 'Save and next' was pressed, but validation failed)
            $this->display_submission();
            return;
        }

        switch ($mode) {
            case 'grade':                         // We are in a main window grading
                if ($submission = $this->process_feedback()) {
                    $this->display_submissions(get_string('changessaved'));
                } else {
                    $this->display_submissions();
                }
                break;

            case 'single':                        // We are in a main window displaying one submission
                if ($submission = $this->process_feedback()) {
                    $this->display_submissions(get_string('changessaved'));
                } else {
                    $this->display_submission();
                }
                break;

            case 'all':                          // Main window, display everything
                $this->display_submissions();
                break;

            case 'fastgrade':
                ///do the fast grading stuff  - this process should work for all 3 subclasses
                $grading    = false;
                $commenting = false;
                $col        = false;
                if (isset($_POST['submissioncomment'])) {
                    $col = 'submissioncomment';
                    $commenting = true;
                }
                if (isset($_POST['menu'])) {
                    $col = 'menu';
                    $grading = true;
                }
                if (!$col) {
                    //both submissioncomment and grade columns collapsed..
                    $this->display_submissions();
                    break;
                }

                foreach ($_POST[$col] as $id => $unusedvalue){

                    $id = (int)$id; //clean parameter name

                    $this->process_outcomes($id);

                    if (!$submission = $this->get_submission($id)) {
                        $submission = $this->prepare_new_submission($id);
                        $newsubmission = true;
                    } else {
                        $newsubmission = false;
                    }
                    unset($submission->data1);  // Don't need to update this.
                    unset($submission->data2);  // Don't need to update this.

                    //for fast grade, we need to check if any changes take place
                    $updatedb = false;

                    if ($grading) {
                        $grade = $_POST['menu'][$id];
                        $updatedb = $updatedb || ($submission->grade != $grade);
                        $submission->grade = $grade;
                    } else {
                        if (!$newsubmission) {
                            unset($submission->grade);  // Don't need to update this.
                        }
                    }
                    if ($commenting) {
                        $commentvalue = trim($_POST['submissioncomment'][$id]);
                        $updatedb = $updatedb || ($submission->submissioncomment != $commentvalue);
                        $submission->submissioncomment = $commentvalue;
                    } else {
                        unset($submission->submissioncomment);  // Don't need to update this.
                    }

                    $submission->teacher    = $USER->id;
                    if ($updatedb) {
                        $submission->mailed = (int)(!$mailinfo);
                    }

                    $submission->timemarked = time();

                    //if it is not an update, we don't change the last modified time etc.
                    //this will also not write into database if no submissioncomment and grade is entered.

                    if ($updatedb){
                        if ($newsubmission) {
                            if (!isset($submission->submissioncomment)) {
                                $submission->submissioncomment = '';
                            }
                            $sid = $DB->insert_record('sassessment_submissions', $submission);
                            $submission->id = $sid;
                        } else {
                            $DB->update_record('sassessment_submissions', $submission);
                        }

                        // trigger grade event
                        $this->update_grade($submission);

                        //add to log only if updating
                        //add_to_log($this->course->id, 'sassessment', 'update grades',
                        //           'submissions.php?id='.$this->cm->id.'&user='.$submission->userid,
                        //           $submission->userid, $this->cm->id);
                    }

                }

                $message = $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');

                $this->display_submissions($message);
                break;


            case 'saveandnext':
                ///We are in pop up. save the current one and go to the next one.
                //first we save the current changes
                if ($submission = $this->process_feedback()) {
                    //print_heading(get_string('changessaved'));
                    //$extra_javascript = $this->update_main_listing($submission);
                }

            case 'next':
                /// We are currently in pop up, but we want to skip to next one without saving.
                ///    This turns out to be similar to a single case
                /// The URL used is for the next submission.
                $offset = required_param('offset', PARAM_INT);
                $nextid = required_param('nextid', PARAM_INT);
                $id = required_param('id', PARAM_INT);
                $filter = optional_param('filter', self::FILTER_ALL, PARAM_INT);

                if ($mode == 'next' || $filter !== self::FILTER_REQUIRE_GRADING) {
                    $offset = (int)$offset+1;
                }
                $redirect = new moodle_url('submissions.php',
                        array('id' => $id, 'offset' => $offset, 'userid' => $nextid,
                        'mode' => 'single', 'filter' => $filter));

                redirect($redirect);
                break;

            case 'singlenosave':
                $this->display_submission();
                break;

            default:
                echo "something seriously is wrong!!";
                break;
        }
    }

    /**
     * Checks if grading method allows quickgrade mode. At the moment it is hardcoded
     * that advanced grading methods do not allow quickgrade.
     *
     * sassessment type plugins are not allowed to override this method
     *
     * @return boolean
     */
    public final function quickgrade_mode_allowed() {
        global $CFG;
        require_once("$CFG->dirroot/grade/grading/lib.php");
        if ($controller = get_grading_manager($this->context, 'mod_sassessment', 'submission')->get_active_controller()) {
            return false;
        }
        return true;
    }

    /**
     * Helper method updating the listing on the main script from popup using javascript
     *
     * @global object
     * @global object
     * @param $submission object The submission whose data is to be updated on the main page
     */
    function update_main_listing($submission) {
        global $SESSION, $CFG, $OUTPUT;

        $output = '';

        $perpage = get_user_preferences('sassessment_perpage', 10);

        $quickgrade = get_user_preferences('sassessment_quickgrade', 0) && $this->quickgrade_mode_allowed();

        /// Run some Javascript to try and update the parent page
        $output .= '<script type="text/javascript">'."\n<!--\n";
        if (empty($SESSION->flextable['mod-sassessment-submissions']->collapse['submissioncomment'])) {
            if ($quickgrade){
                $output.= 'opener.document.getElementById("submissioncomment'.$submission->userid.'").value="'
                .trim($submission->submissioncomment).'";'."\n";
             } else {
                $output.= 'opener.document.getElementById("com'.$submission->userid.
                '").innerHTML="'.shorten_text(trim(strip_tags($submission->submissioncomment)), 15)."\";\n";
            }
        }

        if (empty($SESSION->flextable['mod-sassessment-submissions']->collapse['grade'])) {
            //echo optional_param('menuindex');
            if ($quickgrade){
                $output.= 'opener.document.getElementById("menumenu'.$submission->userid.
                '").selectedIndex="'.optional_param('menuindex', 0, PARAM_INT).'";'."\n";
            } else {
                $output.= 'opener.document.getElementById("g'.$submission->userid.'").innerHTML="'.
                $this->display_grade($submission->grade)."\";\n";
            }
        }
        //need to add student's sassessments in there too.
        if (empty($SESSION->flextable['mod-sassessment-submissions']->collapse['timemodified']) &&
            $submission->timemodified) {
            $output.= 'opener.document.getElementById("ts'.$submission->userid.
                 '").innerHTML="'.addslashes_js($this->print_student_answer($submission->userid)).userdate($submission->timemodified)."\";\n";
        }

        if (empty($SESSION->flextable['mod-sassessment-submissions']->collapse['timemarked']) &&
            $submission->timemarked) {
            $output.= 'opener.document.getElementById("tt'.$submission->userid.
                 '").innerHTML="'.userdate($submission->timemarked)."\";\n";
        }

        if (empty($SESSION->flextable['mod-sassessment-submissions']->collapse['status'])) {
            $output.= 'opener.document.getElementById("up'.$submission->userid.'").className="s1";';
            $buttontext = get_string('update');
            $url = new moodle_url('/mod/sassessment/submissions.php', array(
                    'id' => $this->cm->id,
                    'userid' => $submission->userid,
                    'mode' => 'single',
                    'offset' => (optional_param('offset', '', PARAM_INT)-1)));
            $button = $OUTPUT->action_link($url, $buttontext, new popup_action('click', $url, 'grade'.$submission->userid, array('height' => 450, 'width' => 700)), array('ttile'=>$buttontext));

            $output .= 'opener.document.getElementById("up'.$submission->userid.'").innerHTML="'.addslashes_js($button).'";';
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'sassessment', $this->sassessment->id, $submission->userid);

        if (empty($SESSION->flextable['mod-sassessment-submissions']->collapse['finalgrade'])) {
            $output.= 'opener.document.getElementById("finalgrade_'.$submission->userid.
            '").innerHTML="'.$grading_info->items[0]->grades[$submission->userid]->str_grade.'";'."\n";
        }

        if (!empty($CFG->enableoutcomes) and empty($SESSION->flextable['mod-sassessment-submissions']->collapse['outcome'])) {

            if (!empty($grading_info->outcomes)) {
                foreach($grading_info->outcomes as $n=>$outcome) {
                    if ($outcome->grades[$submission->userid]->locked) {
                        continue;
                    }

                    if ($quickgrade){
                        $output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.
                        '").selectedIndex="'.$outcome->grades[$submission->userid]->grade.'";'."\n";

                    } else {
                        $options = make_grades_menu(-$outcome->scaleid);
                        $options[0] = get_string('nooutcome', 'grades');
                        $output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.'").innerHTML="'.$options[$outcome->grades[$submission->userid]->grade]."\";\n";
                    }

                }
            }
        }

        $output .= "\n-->\n</script>";
        return $output;
    }

    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @global object
     * @param mixed $grade
     * @return string User-friendly representation of grade
     */
    function display_grade($grade) {
        global $DB;

        static $scalegrades = array();   // Cache scales for each sassessment - they might have different scales!!

        if ($this->sassessment->grade >= 0) {    // Normal number
            if ($grade == -1) {
                return '-';
            } else {
                return $grade.' / '.$this->sassessment->grade;
            }

        } else {                                // Scale
            if (empty($scalegrades[$this->sassessment->id])) {
                if ($scale = $DB->get_record('scale', array('id'=>-($this->sassessment->grade)))) {
                    $scalegrades[$this->sassessment->id] = make_menu_from_list($scale->scale);
                } else {
                    return '-';
                }
            }
            if (isset($scalegrades[$this->sassessment->id][$grade])) {
                return $scalegrades[$this->sassessment->id][$grade];
            }
            return '-';
        }
    }

    /**
     *  Display a single submission, ready for grading on a popup window
     *
     * This default method prints the teacher info and submissioncomment box at the top and
     * the student info and submission at the bottom.
     * This method also fetches the necessary data in order to be able to
     * provide a "Next submission" button.
     * Calls preprocess_submission() to give sassessment type plug-ins a chance
     * to process submissions before they are graded
     * This method gets its arguments from the page parameters userid and offset
     *
     * @global object
     * @global object
     * @param string $extra_javascript
     */
    function display_submission($offset=-1,$userid =-1, $display=true) {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/tablelib.php');
        require_once("$CFG->dirroot/repository/lib.php");
        require_once("$CFG->dirroot/grade/grading/lib.php");
        if ($userid==-1) {
            $userid = required_param('userid', PARAM_INT);
        }
        if ($offset==-1) {
            $offset = required_param('offset', PARAM_INT);//offset for where to start looking for student.
        }
        $filter = optional_param('filter', 0, PARAM_INT);

        if (!$user = $DB->get_record('user', array('id'=>$userid))) {
            print_error('nousers');
        }

        if (!$submission = $this->get_submission($user->id)) {
            $submission = $this->prepare_new_submission($userid);
        }
        if ($submission->timemodified > $submission->timemarked) {
            $subtype = 'sassessmentnew';
        } else {
            $subtype = 'sassessmentold';
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'sassessment', $this->sassessment->id, array($user->id));
        $gradingdisabled = $grading_info->items[0]->grades[$userid]->locked || $grading_info->items[0]->grades[$userid]->overridden;

    /// construct SQL, using current offset to find the data of the next student
        $course     = $this->course;
        $sassessment = $this->sassessment;
        $cm         = $this->cm;
        $context = context_module::instance($cm->id);

        //reset filter to all for offline sassessment
        if ($sassessment->sassessmenttype == 'offline' && $filter == self::FILTER_SUBMITTED) {
            $filter = self::FILTER_ALL;
        }
        /// Get all ppl that can submit sassessments

        $currentgroup = groups_get_activity_group($cm);
        $users = get_enrolled_users($context, 'mod/sassessment:submit', $currentgroup, 'u.id');
        if ($users) {
            $users = array_keys($users);
            // if groupmembersonly used, remove users who are not in any group
            if (!empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
                if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                    $users = array_intersect($users, array_keys($groupingusers));
                }
            }
        }

        $nextid = 0;
        $where = '';
        if($filter == self::FILTER_SUBMITTED) {
            $where .= 's.timemodified > 0 AND ';
        } else if($filter == self::FILTER_REQUIRE_GRADING) {
            $where .= 's.timemarked < s.timemodified AND ';
        }

        if ($users) {
            $userfields = user_picture::fields('u', array('lastaccess'));
            $select = "SELECT $userfields,
                              s.id AS submissionid, s.grade, s.submissioncomment,
                              s.timemodified, s.timemarked,
                              CASE WHEN s.timemarked > 0 AND s.timemarked >= s.timemodified THEN 1
                                   ELSE 0 END AS status ";

            $sql = 'FROM {user} u '.
                   'LEFT JOIN {sassessment_submissions} s ON u.id = s.userid
                   AND s.sassessment = '.$this->sassessment->id.' '.
                   'WHERE '.$where.'u.id IN ('.implode(',', $users).') ';

            if ($sort = flexible_table::get_sort_for_table('mod-sassessment-submissions')) {
                $sort = 'ORDER BY '.$sort.' ';
            }
            $auser = $DB->get_records_sql($select.$sql.$sort, null, $offset, 2);

            if (is_array($auser) && count($auser)>1) {
                $nextuser = next($auser);
                $nextid = $nextuser->id;
            }
        }

        if ($submission->teacher) {
            $teacher = $DB->get_record('user', array('id'=>$submission->teacher));
        } else {
            global $USER;
            $teacher = $USER;
        }

        $this->preprocess_submission($submission);

        $mformdata = new stdClass();
        $mformdata->context = $this->context;
        $mformdata->maxbytes = $this->course->maxbytes;
        $mformdata->courseid = $this->course->id;
        $mformdata->teacher = $teacher;
        $mformdata->sassessment = $sassessment;
        $mformdata->submission = $submission;
        $mformdata->lateness = $this->display_lateness($submission->timemodified);
        $mformdata->auser = $auser;
        $mformdata->user = $user;
        $mformdata->offset = $offset;
        $mformdata->userid = $userid;
        $mformdata->cm = $this->cm;
        $mformdata->grading_info = $grading_info;
        $mformdata->enableoutcomes = $CFG->enableoutcomes;
        $mformdata->grade = $this->sassessment->grade;
        $mformdata->gradingdisabled = $gradingdisabled;
        $mformdata->nextid = $nextid;
        $mformdata->submissioncomment= $submission->submissioncomment;
        $mformdata->submissioncommentformat= FORMAT_HTML;
        $mformdata->submission_content= $this->print_user_files($user->id,true);
        $mformdata->filter = $filter;
        $mformdata->mailinfo = get_user_preferences('sassessment_mailinfo', 0);
         if ($sassessment->sassessmenttype == 'upload') {
            $mformdata->fileui_options = array('subdirs'=>1, 'maxbytes'=>$sassessment->maxbytes, 'maxfiles'=>$sassessment->var1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        } elseif ($sassessment->sassessmenttype == 'uploadsingle') {
            $mformdata->fileui_options = array('subdirs'=>0, 'maxbytes'=>$CFG->userquota, 'maxfiles'=>1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        }
        $advancedgradingwarning = false;
        $gradingmanager = get_grading_manager($this->context, 'mod_sassessment', 'submission');
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if (!empty($submission->id)) {
                    $itemid = $submission->id;
                }
                if ($gradingdisabled && $itemid) {
                    $mformdata->advancedgradinginstance = $controller->get_current_instance($USER->id, $itemid);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $mformdata->advancedgradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $itemid);
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }

        $submitform = new mod_sassessment_grading_form( null, $mformdata );

         if (!$display) {
            $ret_data = new stdClass();
            $ret_data->mform = $submitform;
            if (isset($mformdata->fileui_options)) {
                $ret_data->fileui_options = $mformdata->fileui_options;
            }
            return $ret_data;
        }

        if ($submitform->is_cancelled()) {
            redirect('submissions.php?id='.$this->cm->id);
        }

        $submitform->set_data($mformdata);

        $PAGE->set_title($this->course->fullname . ': ' .get_string('feedback', 'sassessment').' - '.fullname($user, true));
        $PAGE->set_heading($this->course->fullname);
        $PAGE->navbar->add(get_string('submissions', 'sassessment'), new moodle_url('/mod/sassessment/submissions.php', array('id'=>$cm->id)));
        $PAGE->navbar->add(fullname($user, true));

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('feedback', 'sassessment').': '.fullname($user, true));

        // display mform here...
        if ($advancedgradingwarning) {
            echo $OUTPUT->notification($advancedgradingwarning, 'error');
        }
        $submitform->display();

        $customfeedback = $this->custom_feedbackform($submission, true);
        if (!empty($customfeedback)) {
            echo $customfeedback;
        }

        echo $OUTPUT->footer();
    }

    /**
     *  Preprocess submission before grading
     *
     * Called by display_submission()
     * The default type does nothing here.
     *
     * @param object $submission The submission object
     */
    function preprocess_submission(&$submission) {
    }

    /**
     *  Display all the submissions ready for grading
     *
     * @global object
     * @global object
     * @global object
     * @global object
     * @param string $message
     * @return bool|void
     */
    function display_submissions($message='') {
        global $CFG, $DB, $USER, $DB, $OUTPUT, $PAGE;
        require_once($CFG->libdir.'/gradelib.php');

        /* first we check to see if the form has just been submitted
         * to request user_preference updates
         */

       $filters = array(self::FILTER_ALL             => get_string('all'),
                        self::FILTER_REQUIRE_GRADING => get_string('requiregrading', 'sassessment'));

        $updatepref = optional_param('updatepref', 0, PARAM_BOOL);
        if ($updatepref) {
            $perpage = optional_param('perpage', 10, PARAM_INT);
            $perpage = ($perpage <= 0) ? 10 : $perpage ;
            $filter = optional_param('filter', 0, PARAM_INT);
            set_user_preference('sassessment_perpage', $perpage);
            set_user_preference('sassessment_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
            set_user_preference('sassessment_filter', $filter);
        }

        /* next we get perpage and quickgrade (allow quick grade) params
         * from database
         */
        $perpage    = get_user_preferences('sassessment_perpage', 10);
        $quickgrade = get_user_preferences('sassessment_quickgrade', 0) && $this->quickgrade_mode_allowed();
        $filter = get_user_preferences('sassessment_filter', 0);
        $grading_info = grade_get_grades($this->course->id, 'mod', 'sassessment', $this->sassessment->id);

        if (!empty($CFG->enableoutcomes) and !empty($grading_info->outcomes)) {
            $uses_outcomes = true;
        } else {
            $uses_outcomes = false;
        }

        $page    = optional_param('page', 0, PARAM_INT);
        $strsaveallfeedback = get_string('saveallfeedback', 'sassessment');

    /// Some shortcuts to make the code read better

        $course     = $this->course;
        $sassessment = $this->sassessment;
        $cm         = $this->cm;
        $hassubmission = false;

        // reset filter to all for offline sassessment only.
        if ($sassessment->sassessmenttype == 'offline') {
            if ($filter == self::FILTER_SUBMITTED) {
                $filter = self::FILTER_ALL;
            }
        } else {
            $filters[self::FILTER_SUBMITTED] = get_string('submitted', 'sassessment');
        }

        $tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet
        //add_to_log($course->id, 'sassessment', 'view submission', 'submissions.php?id='.$this->cm->id, $this->sassessment->id, $this->cm->id);

        $PAGE->set_title(format_string($this->sassessment->name,true));
        $PAGE->set_heading($this->course->fullname);
        echo $OUTPUT->header();

        echo '<div class="usersubmissions">';

        //hook to allow plagiarism plugins to update status/print links.
        echo plagiarism_update_status($this->course, $this->cm);

        $course_context = context_course::instance($course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
                . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }

        if (!empty($message)) {
            echo $message;   // display messages here if any
        }

        $context = context_module::instance($cm->id);

    /// Check to see if groups are being used in this sassessment

        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/sassessment/submissions.php?id=' . $this->cm->id);

        /// Print quickgrade form around the table
        if ($quickgrade) {
            $formattrs = array();
            $formattrs['action'] = new moodle_url('/mod/sassessment/submissions.php');
            $formattrs['id'] = 'fastg';
            $formattrs['method'] = 'post';

            echo html_writer::start_tag('form', $formattrs);
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id',      'value'=> $this->cm->id));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode',    'value'=> 'fastgrade'));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'page',    'value'=> $page));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=> sesskey()));
        }

        /// Get all ppl that are allowed to submit sassessments
        list($esql, $params) = get_enrolled_sql($context, 'mod/sassessment:submit', $currentgroup);

        if ($filter == self::FILTER_ALL) {
            $sql = "SELECT u.id FROM {user} u ".
                   "LEFT JOIN ($esql) eu ON eu.id=u.id ".
                   "WHERE u.deleted = 0 AND eu.id=u.id ";
        } else {
            $wherefilter = ' AND s.sassessment = '. $this->sassessment->id;
            $sassessmentsubmission = "LEFT JOIN {sassessment_submissions} s ON (u.id = s.userid) ";
            if($filter == self::FILTER_SUBMITTED) {
                $wherefilter .= ' AND s.timemodified > 0 ';
            } else if($filter == self::FILTER_REQUIRE_GRADING && $sassessment->sassessmenttype != 'offline') {
                $wherefilter .= ' AND s.timemarked < s.timemodified ';
            } else { // require grading for offline sassessment
                $sassessmentsubmission = "";
                $wherefilter = "";
            }

            $sql = "SELECT u.id FROM {user} u ".
                   "LEFT JOIN ($esql) eu ON eu.id=u.id ".
                   $sassessmentsubmission.
                   "WHERE u.deleted = 0 AND eu.id=u.id ".
                   $wherefilter;
        }

        $users = $DB->get_records_sql($sql, $params);
        if (!empty($users)) {
            if($sassessment->sassessmenttype == 'offline' && $filter == self::FILTER_REQUIRE_GRADING) {
                //remove users who has submitted their sassessment
                foreach ($this->get_submissions() as $submission) {
                    if (array_key_exists($submission->userid, $users)) {
                        unset($users[$submission->userid]);
                    }
                }
            }
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        $extrafields = get_extra_user_fields($context);
        $tablecolumns = array_merge(array('picture', 'fullname'), $extrafields,
                array('grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade'));
        if ($uses_outcomes) {
            $tablecolumns[] = 'outcome'; // no sorting based on outcomes column
        }

        $extrafieldnames = array();
        foreach ($extrafields as $field) {
            $extrafieldnames[] = get_user_field_name($field);
        }
        $tableheaders = array_merge(
                array('', get_string('fullnameuser')),
                $extrafieldnames,
                array(
                    get_string('grade'),
                    get_string('comment', 'sassessment'),
                    get_string('lastmodified').' ('.get_string('submission', 'sassessment').')',
                    get_string('lastmodified').' ('.get_string('grade').')',
                    get_string('status'),
                    get_string('finalgrade', 'grades'),
                ));
        if ($uses_outcomes) {
            $tableheaders[] = get_string('outcome', 'grades');
        }

        require_once($CFG->libdir.'/tablelib.php');
        $table = new flexible_table('mod-sassessment-submissions');

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot.'/mod/sassessment/submissions.php?id='.$this->cm->id.'&amp;currentgroup='.$currentgroup);

        $table->sortable(true, 'lastname');//sorted by lastname by default
        $table->collapsible(true);
        $table->initialbars(true);

        $table->column_suppress('picture');
        $table->column_suppress('fullname');

        $table->column_class('picture', 'picture');
        $table->column_class('fullname', 'fullname');
        foreach ($extrafields as $field) {
            $table->column_class($field, $field);
        }
        $table->column_class('grade', 'grade');
        $table->column_class('submissioncomment', 'comment');
        $table->column_class('timemodified', 'timemodified');
        $table->column_class('timemarked', 'timemarked');
        $table->column_class('status', 'status');
        $table->column_class('finalgrade', 'finalgrade');
        if ($uses_outcomes) {
            $table->column_class('outcome', 'outcome');
        }

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');

        $table->no_sorting('finalgrade');
        $table->no_sorting('outcome');

        // Start working -- this is necessary as soon as the niceties are over
        $table->setup();

        /// Construct the SQL
        list($where, $params) = $table->get_sql_where();
        if ($where) {
            $where .= ' AND ';
        }

        if ($filter == self::FILTER_SUBMITTED) {
           $where .= 's.timemodified > 0 AND ';
        } else if($filter == self::FILTER_REQUIRE_GRADING) {
            $where = '';
            if ($sassessment->sassessmenttype != 'offline') {
               $where .= 's.timemarked < s.timemodified AND ';
            }
        }

        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY '.$sort;
        }

        $ufields = user_picture::fields('u', $extrafields);
        if (!empty($users)) {
            $select = "SELECT $ufields,
                              s.id AS submissionid, s.grade, s.submissioncomment,
                              s.timemodified, s.timemarked,
                              CASE WHEN s.timemarked > 0 AND s.timemarked >= s.timemodified THEN 1
                                   ELSE 0 END AS status ";

            $sql = 'FROM {user} u '.
                   'LEFT JOIN {sassessment_submissions} s ON u.id = s.userid
                    AND s.sassessment = '.$this->sassessment->id.' '.
                   'WHERE '.$where.'u.id IN ('.implode(',',$users).') ';

            $ausers = $DB->get_records_sql($select.$sql.$sort, $params, $table->get_page_start(), $table->get_page_size());

            $table->pagesize($perpage, count($users));

            ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
            $offset = $page * $perpage;
            $strupdate = get_string('update');
            $strgrade  = get_string('grade');
            $strview  = get_string('view');
            $grademenu = make_grades_menu($this->sassessment->grade);

            if ($ausers !== false) {
                $grading_info = grade_get_grades($this->course->id, 'mod', 'sassessment', $this->sassessment->id, array_keys($ausers));
                $endposition = $offset + $perpage;
                $currentposition = 0;
                foreach ($ausers as $auser) {
                    if ($currentposition == $offset && $offset < $endposition) {
                        $rowclass = null;
                        $final_grade = $grading_info->items[0]->grades[$auser->id];
                        $grademax = $grading_info->items[0]->grademax;
                        $final_grade->formatted_grade = round($final_grade->grade,2) .' / ' . round($grademax,2);
                        $locked_overridden = 'locked';
                        if ($final_grade->overridden) {
                            $locked_overridden = 'overridden';
                        }

                        // TODO add here code if advanced grading grade must be reviewed => $auser->status=0

                        $picture = $OUTPUT->user_picture($auser);

                        if (empty($auser->submissionid)) {
                            $auser->grade = -1; //no submission yet
                        }

                        if (!empty($auser->submissionid)) {
                            $hassubmission = true;
                        ///Prints student answer and student modified date
                        ///attach file or print link to student answer, depending on the type of the sassessment.
                        ///Refer to print_student_answer in inherited classes.
                            if ($auser->timemodified > 0) {
                                $studentmodifiedcontent = $this->print_student_answer($auser->id)
                                        . userdate($auser->timemodified);
                                if ($sassessment->timedue && $auser->timemodified > $sassessment->timedue) {
                                    $studentmodifiedcontent .= sassessment_display_lateness($auser->timemodified, $sassessment->timedue);
                                    $rowclass = 'late';
                                }
                            } else {
                                $studentmodifiedcontent = '&nbsp;';
                            }
                            $studentmodified = html_writer::tag('div', $studentmodifiedcontent, array('id' => 'ts' . $auser->id));
                        ///Print grade, dropdown or text
                            if ($auser->timemarked > 0) {
                                $teachermodified = '<div id="tt'.$auser->id.'">'.userdate($auser->timemarked).'</div>';

                                if ($final_grade->locked or $final_grade->overridden) {
                                    $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                                } else if ($quickgrade) {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $menu = html_writer::select(make_grades_menu($this->sassessment->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                    $grade = '<div id="g'.$auser->id.'">'. $menu .'</div>';
                                } else {
                                    $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                                }

                            } else {
                                $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                                if ($final_grade->locked or $final_grade->overridden) {
                                    $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                                } else if ($quickgrade) {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $menu = html_writer::select(make_grades_menu($this->sassessment->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                    $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                                } else {
                                    $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                                }
                            }
                        ///Print Comment
                            if ($final_grade->locked or $final_grade->overridden) {
                                $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';

                            } else if ($quickgrade) {
                                $comment = '<div id="com'.$auser->id.'">'
                                         . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                                         . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                            } else {
                                $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),15).'</div>';
                            }
                        } else {
                            $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                            $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                            $status          = '<div id="st'.$auser->id.'">&nbsp;</div>';

                            if ($final_grade->locked or $final_grade->overridden) {
                                $grade = '<div id="g'.$auser->id.'">'.$final_grade->formatted_grade . '</div>';
                                $hassubmission = true;
                            } else if ($quickgrade) {   // allow editing
                                $attributes = array();
                                $attributes['tabindex'] = $tabindex++;
                                $menu = html_writer::select(make_grades_menu($this->sassessment->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                                $hassubmission = true;
                            } else {
                                $grade = '<div id="g'.$auser->id.'">-</div>';
                            }

                            if ($final_grade->locked or $final_grade->overridden) {
                                $comment = '<div id="com'.$auser->id.'">'.$final_grade->str_feedback.'</div>';
                            } else if ($quickgrade) {
                                $comment = '<div id="com'.$auser->id.'">'
                                         . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                                         . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                            } else {
                                $comment = '<div id="com'.$auser->id.'">&nbsp;</div>';
                            }
                        }

                        if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
                            $auser->status = 0;
                        } else {
                            $auser->status = 1;
                        }

                        $buttontext = ($auser->status == 1) ? $strupdate : $strgrade;
                        if ($final_grade->locked or $final_grade->overridden) {
                            $buttontext = $strview;
                        }

                        ///No more buttons, we use popups ;-).
                        $popup_url = '/mod/sassessment/submissions.php?id='.$this->cm->id
                                   . '&amp;userid='.$auser->id.'&amp;mode=single'.'&amp;filter='.$filter.'&amp;offset='.$offset++;

                        $button = $OUTPUT->action_link($popup_url, $buttontext);

                        $status  = '<div id="up'.$auser->id.'" class="s'.$auser->status.'">'.$button.'</div>';

                        $finalgrade = '<span id="finalgrade_'.$auser->id.'">'.$final_grade->str_grade.'</span>';

                        $outcomes = '';

                        if ($uses_outcomes) {

                            foreach($grading_info->outcomes as $n=>$outcome) {
                                $outcomes .= '<div class="outcome"><label>'.$outcome->name.'</label>';
                                $options = make_grades_menu(-$outcome->scaleid);

                                if ($outcome->grades[$auser->id]->locked or !$quickgrade) {
                                    $options[0] = get_string('nooutcome', 'grades');
                                    $outcomes .= ': <span id="outcome_'.$n.'_'.$auser->id.'">'.$options[$outcome->grades[$auser->id]->grade].'</span>';
                                } else {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $attributes['id'] = 'outcome_'.$n.'_'.$auser->id;
                                    $outcomes .= ' '.html_writer::select($options, 'outcome_'.$n.'['.$auser->id.']', $outcome->grades[$auser->id]->grade, array(0=>get_string('nooutcome', 'grades')), $attributes);
                                }
                                $outcomes .= '</div>';
                            }
                        }

                        $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course=' . $course->id . '">' . fullname($auser, has_capability('moodle/site:viewfullnames', $this->context)) . '</a>';
                        $extradata = array();
                        foreach ($extrafields as $field) {
                            $extradata[] = $auser->{$field};
                        }
                        $row = array_merge(array($picture, $userlink), $extradata,
                                array($grade, $comment, $studentmodified, $teachermodified,
                                $status, $finalgrade));
                        if ($uses_outcomes) {
                            $row[] = $outcomes;
                        }
                        $table->add_data($row, $rowclass);
                    }
                    $currentposition++;
                }
                if ($hassubmission && ($this->sassessment->sassessmenttype=='upload' || $this->sassessment->sassessmenttype=='online' || $this->sassessment->sassessmenttype=='uploadsingle')) { //TODO: this is an ugly hack, where is the plugin spirit? (skodak)
                    echo html_writer::start_tag('div', array('class' => 'mod-sassessment-download-link'));
                    echo html_writer::link(new moodle_url('/mod/sassessment/submissions.php', array('id' => $this->cm->id, 'download' => 'zip')), get_string('downloadall', 'sassessment'));
                    echo html_writer::end_tag('div');
                }
                $table->print_html();  /// Print the whole table
            } else {
                if ($filter == self::FILTER_SUBMITTED) {
                    echo html_writer::tag('div', get_string('nosubmisson', 'sassessment'), array('class'=>'nosubmisson'));
                } else if ($filter == self::FILTER_REQUIRE_GRADING) {
                    echo html_writer::tag('div', get_string('norequiregrading', 'sassessment'), array('class'=>'norequiregrading'));
                }
            }
        }

        /// Print quickgrade form around the table
        if ($quickgrade && $table->started_output && !empty($users)){
            $mailinfopref = false;
            if (get_user_preferences('sassessment_mailinfo', 1)) {
                $mailinfopref = true;
            }
            $emailnotification =  html_writer::checkbox('mailinfo', 1, $mailinfopref, get_string('enablenotification','sassessment'));

            $emailnotification .= $OUTPUT->help_icon('enablenotification', 'sassessment');
            echo html_writer::tag('div', $emailnotification, array('class'=>'emailnotification'));

            $savefeedback = html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'fastg', 'value'=>get_string('saveallfeedback', 'sassessment')));
            echo html_writer::tag('div', $savefeedback, array('class'=>'fastgbutton'));

            echo html_writer::end_tag('form');
        } else if ($quickgrade) {
            echo html_writer::end_tag('form');
        }

        echo '</div>';
        /// End of fast grading form

        /// Mini form for setting user preference

        $formaction = new moodle_url('/mod/sassessment/submissions.php', array('id'=>$this->cm->id));
        $mform = new MoodleQuickForm('optionspref', 'post', $formaction, '', array('class'=>'optionspref'));

        $mform->addElement('hidden', 'updatepref');
        $mform->setDefault('updatepref', 1);
        $mform->addElement('header', 'qgprefs', get_string('optionalsettings', 'sassessment'));
        $mform->addElement('select', 'filter', get_string('show'),  $filters);

        $mform->setDefault('filter', $filter);

        $mform->addElement('text', 'perpage', get_string('pagesize', 'sassessment'), array('size'=>1));
        $mform->setDefault('perpage', $perpage);

        if ($this->quickgrade_mode_allowed()) {
            $mform->addElement('checkbox', 'quickgrade', get_string('quickgrade','sassessment'));
            $mform->setDefault('quickgrade', $quickgrade);
            $mform->addHelpButton('quickgrade', 'quickgrade', 'sassessment');
        }

        $mform->addElement('submit', 'savepreferences', get_string('savepreferences'));

        $mform->display();

        echo $OUTPUT->footer();
    }

    /**
     * If the form was cancelled ('Cancel' or 'Next' was pressed), call cancel method
     * from advanced grading (if applicable) and returns true
     * If the form was submitted, validates it and returns false if validation did not pass.
     * If validation passes, preprocess advanced grading (if applicable) and returns true.
     *
     * Note to the developers: This is NOT the correct way to implement advanced grading
     * in grading form. The sassessment grading was written long time ago and unfortunately
     * does not fully use the mforms. Usually function is_validated() is called to
     * validate the form and get_data() is called to get the data from the form.
     *
     * Here we have to push the calculated grade to $_POST['xgrade'] because further processing
     * of the form gets the data not from form->get_data(), but from $_POST (using statement
     * like  $feedback = data_submitted() )
     */
    protected function validate_and_preprocess_feedback() {
        global $USER, $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        if (!($feedback = data_submitted()) || !isset($feedback->userid) || !isset($feedback->offset)) {
            return true;      // No incoming data, nothing to validate
        }
        $userid = required_param('userid', PARAM_INT);
        $offset = required_param('offset', PARAM_INT);
        $gradinginfo = grade_get_grades($this->course->id, 'mod', 'sassessment', $this->sassessment->id, array($userid));
        $gradingdisabled = $gradinginfo->items[0]->grades[$userid]->locked || $gradinginfo->items[0]->grades[$userid]->overridden;
        if ($gradingdisabled) {
            return true;
        }
        $submissiondata = $this->display_submission($offset, $userid, false);
        $mform = $submissiondata->mform;
        $gradinginstance = $mform->use_advanced_grading();
        if (optional_param('cancel', false, PARAM_BOOL) || optional_param('next', false, PARAM_BOOL)) {
            // form was cancelled
            if ($gradinginstance) {
                $gradinginstance->cancel();
            }
        } else if ($mform->is_submitted()) {
            // form was submitted (= a submit button other than 'cancel' or 'next' has been clicked)
            if (!$mform->is_validated()) {
                return false;
            }
            // preprocess advanced grading here
            if ($gradinginstance) {
                $data = $mform->get_data();
                // create submission if it did not exist yet because we need submission->id for storing the grading instance
                $submission = $this->get_submission($userid, true);
                $_POST['xgrade'] = $gradinginstance->submit_and_get_grade($data->advancedgrading, $submission->id);
            }
        }
        return true;
    }

    /**
     *  Process teacher feedback submission
     *
     * This is called by submissions() when a grading even has taken place.
     * It gets its data from the submitted form.
     *
     * @global object
     * @global object
     * @global object
     * @return object|bool The updated submission object or false
     */
    function process_feedback($formdata=null) {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir.'/gradelib.php');

        if (!$feedback = data_submitted() or !confirm_sesskey()) {      // No incoming data?
            return false;
        }

        ///For save and next, we need to know the userid to save, and the userid to go
        ///We use a new hidden field in the form, and set it to -1. If it's set, we use this
        ///as the userid to store
        if ((int)$feedback->saveuserid !== -1){
            $feedback->userid = $feedback->saveuserid;
        }

        if (!empty($feedback->cancel)) {          // User hit cancel button
            return false;
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'sassessment', $this->sassessment->id, $feedback->userid);

        // store outcomes if needed
        $this->process_outcomes($feedback->userid);

        $submission = $this->get_submission($feedback->userid, true);  // Get or make one

        if (!($grading_info->items[0]->grades[$feedback->userid]->locked ||
            $grading_info->items[0]->grades[$feedback->userid]->overridden) ) {

            $submission->grade      = $feedback->xgrade;
            $submission->submissioncomment    = $feedback->submissioncomment_editor['text'];
            $submission->teacher    = $USER->id;
            $mailinfo = get_user_preferences('sassessment_mailinfo', 0);
            if (!$mailinfo) {
                $submission->mailed = 1;       // treat as already mailed
            } else {
                $submission->mailed = 0;       // Make sure mail goes out (again, even)
            }
            $submission->timemarked = time();

            unset($submission->data1);  // Don't need to update this.
            unset($submission->data2);  // Don't need to update this.

            if (empty($submission->timemodified)) {   // eg for offline sassessments
                // $submission->timemodified = time();
            }

            $DB->update_record('sassessment_submissions', $submission);

            // triger grade event
            $this->update_grade($submission);

            //add_to_log($this->course->id, 'sassessment', 'update grades',
            //           'submissions.php?id='.$this->cm->id.'&user='.$feedback->userid, $feedback->userid, $this->cm->id);
             if (!is_null($formdata)) {
                    if ($this->type == 'upload' || $this->type == 'uploadsingle') {
                        $mformdata = $formdata->mform->get_data();
                        $mformdata = file_postupdate_standard_filemanager($mformdata, 'files', $formdata->fileui_options, $this->context, 'mod_sassessment', 'response', $submission->id);
                    }
             }
        }

        return $submission;

    }

    function process_outcomes($userid) {
        global $CFG, $USER;

        if (empty($CFG->enableoutcomes)) {
            return;
        }

        require_once($CFG->libdir.'/gradelib.php');

        if (!$formdata = data_submitted() or !confirm_sesskey()) {
            return;
        }

        $data = array();
        $grading_info = grade_get_grades($this->course->id, 'mod', 'sassessment', $this->sassessment->id, $userid);

        if (!empty($grading_info->outcomes)) {
            foreach($grading_info->outcomes as $n=>$old) {
                $name = 'outcome_'.$n;
                if (isset($formdata->{$name}[$userid]) and $old->grades[$userid]->grade != $formdata->{$name}[$userid]) {
                    $data[$n] = $formdata->{$name}[$userid];
                }
            }
        }
        if (count($data) > 0) {
            grade_update_outcomes('mod/sassessment', $this->course->id, 'mod', 'sassessment', $this->sassessment->id, $userid, $data);
        }

    }

    /**
     * Load the submission object for a particular user
     *
     * @global object
     * @global object
     * @param $userid int The id of the user whose submission we want or 0 in which case USER->id is used
     * @param $createnew boolean optional Defaults to false. If set to true a new submission object will be created in the database
     * @param bool $teachermodified student submission set if false
     * @return object The submission
     */
    function get_submission($userid=0, $createnew=false, $teachermodified=false) {
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $submission = $DB->get_record('sassessment_submissions', array('sassessment'=>$this->sassessment->id, 'userid'=>$userid));

        if ($submission || !$createnew) {
            return $submission;
        }
        $newsubmission = $this->prepare_new_submission($userid, $teachermodified);
        $DB->insert_record("sassessment_submissions", $newsubmission);

        return $DB->get_record('sassessment_submissions', array('sassessment'=>$this->sassessment->id, 'userid'=>$userid));
    }

    /**
     * Check the given submission is complete. Preliminary rows are often created in the sassessment_submissions
     * table before a submission actually takes place. This function checks to see if the given submission has actually
     * been submitted.
     *
     * @param  stdClass $submission The submission we want to check for completion
     * @return bool                 Indicates if the submission was found to be complete
     */
    public function is_submitted_with_required_data($submission) {
        return $submission->timemodified;
    }

    /**
     * Instantiates a new submission object for a given user
     *
     * Sets the sassessment, userid and times, everything else is set to default values.
     *
     * @param int $userid The userid for which we want a submission object
     * @param bool $teachermodified student submission set if false
     * @return object The submission
     */
    function prepare_new_submission($userid, $teachermodified=false) {
        $submission = new stdClass();
        $submission->sassessment   = $this->sassessment->id;
        $submission->userid       = $userid;
        $submission->timecreated = time();
        // teachers should not be modifying modified date, except offline sassessments
        if ($teachermodified) {
            $submission->timemodified = 0;
        } else {
            $submission->timemodified = $submission->timecreated;
        }
        $submission->numfiles     = 0;
        $submission->data1        = '';
        $submission->data2        = '';
        $submission->grade        = -1;
        $submission->submissioncomment      = '';
        $submission->format       = 0;
        $submission->teacher      = 0;
        $submission->timemarked   = 0;
        $submission->mailed       = 0;
        return $submission;
    }

    /**
     * Return all sassessment submissions by ENROLLED students (even empty)
     *
     * @param string $sort optional field names for the ORDER BY in the sql query
     * @param string $dir optional specifying the sort direction, defaults to DESC
     * @return array The submission objects indexed by id
     */
    function get_submissions($sort='', $dir='DESC') {
        return sassessment_get_all_submissions($this->sassessment, $sort, $dir);
    }

    /**
     * Counts all complete (real) sassessment submissions by enrolled students
     *
     * @param  int $groupid (optional) If nonzero then count is restricted to this group
     * @return int          The number of submissions
     */
    function count_real_submissions($groupid=0) {
        global $CFG;
        global $DB;

        // Grab the context assocated with our course module
        $context = context_module::instance($this->cm->id);

        // Get ids of users enrolled in the given course.
        list($enroledsql, $params) = get_enrolled_sql($context, 'mod/sassessment:view', $groupid);
        $params['sassessmentid'] = $this->cm->instance;

        // Get ids of users enrolled in the given course.
        return $DB->count_records_sql("SELECT COUNT('x')
                                         FROM {sassessment_submissions} s
                                    LEFT JOIN {sassessment} a ON a.id = s.sassessment
                                   INNER JOIN ($enroledsql) u ON u.id = s.userid
                                        WHERE s.sassessment = :sassessmentid AND
                                              s.timemodified > 0", $params);
    }

    /**
     * Alerts teachers by email of new or changed sassessments that need grading
     *
     * First checks whether the option to email teachers is set for this sassessment.
     * Sends an email to ALL teachers in the course (or in the group if using separate groups).
     * Uses the methods email_teachers_text() and email_teachers_html() to construct the content.
     *
     * @global object
     * @global object
     * @param $submission object The submission that has changed
     * @return void
     */
    function email_teachers($submission) {
        global $CFG, $DB;

        if (empty($this->sassessment->emailteachers)) {          // No need to do anything
            return;
        }

        $user = $DB->get_record('user', array('id'=>$submission->userid));

        if ($teachers = $this->get_graders($user)) {

            $strsassessments = get_string('modulenameplural', 'sassessment');
            $strsassessment  = get_string('modulename', 'sassessment');
            $strsubmitted  = get_string('submitted', 'sassessment');

            foreach ($teachers as $teacher) {
                $info = new stdClass();
                $info->username = fullname($user, true);
                $info->sassessment = format_string($this->sassessment->name,true);
                $info->url = $CFG->wwwroot.'/mod/sassessment/submissions.php?id='.$this->cm->id;
                $info->timeupdated = userdate($submission->timemodified, '%c', $teacher->timezone);

                $postsubject = $strsubmitted.': '.$info->username.' -> '.$this->sassessment->name;
                $posttext = $this->email_teachers_text($info);
                $posthtml = ($teacher->mailformat == 1) ? $this->email_teachers_html($info) : '';

                $eventdata = new stdClass();
                $eventdata->modulename       = 'sassessment';
                $eventdata->userfrom         = $user;
                $eventdata->userto           = $teacher;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->smallmessage     = $postsubject;

                $eventdata->name            = 'sassessment_updates';
                $eventdata->component       = 'mod_sassessment';
                $eventdata->notification    = 1;
                $eventdata->contexturl      = $info->url;
                $eventdata->contexturlname  = $info->sassessment;

                message_send($eventdata);
            }
        }
    }

    /**
     * @param string $filearea
     * @param array $args
     * @return bool
     */
    function send_file($filearea, $args) {
        debugging('plugin does not implement file sending', DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Returns a list of teachers that should be grading given submission
     *
     * @param object $user
     * @return array
     */
    function get_graders($user) {
        //potential graders
        $potgraders = get_users_by_capability($this->context, 'mod/sassessment:grade', '', '', '', '', '', '', false, false);

        $graders = array();
        if (groups_get_activity_groupmode($this->cm) == SEPARATEGROUPS) {   // Separate groups are being used
            if ($groups = groups_get_all_groups($this->course->id, $user->id)) {  // Try to find all groups
                foreach ($groups as $group) {
                    foreach ($potgraders as $t) {
                        if ($t->id == $user->id) {
                            continue; // do not send self
                        }
                        if (groups_is_member($group->id, $t->id)) {
                            $graders[$t->id] = $t;
                        }
                    }
                }
            } else {
                // user not in group, try to find graders without group
                foreach ($potgraders as $t) {
                    if ($t->id == $user->id) {
                        continue; // do not send self
                    }
                    if (!groups_get_all_groups($this->course->id, $t->id)) { //ugly hack
                        $graders[$t->id] = $t;
                    }
                }
            }
        } else {
            foreach ($potgraders as $t) {
                if ($t->id == $user->id) {
                    continue; // do not send self
                }
                $graders[$t->id] = $t;
            }
        }
        return $graders;
    }

    /**
     * Creates the text content for emails to teachers
     *
     * @param $info object The info used by the 'emailteachermail' language string
     * @return string
     */
    function email_teachers_text($info) {
        $posttext  = format_string($this->course->shortname, true, array('context' => $this->coursecontext)).' -> '.
                     $this->strsassessments.' -> '.
                     format_string($this->sassessment->name, true, array('context' => $this->context))."\n";
        $posttext .= '---------------------------------------------------------------------'."\n";
        $posttext .= get_string("emailteachermail", "sassessment", $info)."\n";
        $posttext .= "\n---------------------------------------------------------------------\n";
        return $posttext;
    }

     /**
     * Creates the html content for emails to teachers
     *
     * @param $info object The info used by the 'emailteachermailhtml' language string
     * @return string
     */
    function email_teachers_html($info) {
        global $CFG;
        $posthtml  = '<p><font face="sans-serif">'.
                     '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">'.format_string($this->course->shortname, true, array('context' => $this->coursecontext)).'</a> ->'.
                     '<a href="'.$CFG->wwwroot.'/mod/sassessment/index.php?id='.$this->course->id.'">'.$this->strsassessments.'</a> ->'.
                     '<a href="'.$CFG->wwwroot.'/mod/sassessment/view.php?id='.$this->cm->id.'">'.format_string($this->sassessment->name, true, array('context' => $this->context)).'</a></font></p>';
        $posthtml .= '<hr /><font face="sans-serif">';
        $posthtml .= '<p>'.get_string('emailteachermailhtml', 'sassessment', $info).'</p>';
        $posthtml .= '</font><hr />';
        return $posthtml;
    }

    /**
     * Produces a list of links to the files uploaded by a user
     *
     * @param $userid int optional id of the user. If 0 then $USER->id is used.
     * @param $return boolean optional defaults to false. If true the list is returned rather than printed
     * @return string optional
     */
    function print_user_files($userid=0, $return=false) {
        global $CFG, $USER, $OUTPUT;

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $output = '';

        $submission = $this->get_submission($userid);
        if (!$submission) {
            return $output;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_sassessment', 'submission', $submission->id, "timemodified", false);
        if (!empty($files)) {
            require_once($CFG->dirroot . '/mod/sassessment/locallib.php');
            if ($CFG->enableportfolios) {
                require_once($CFG->libdir.'/portfoliolib.php');
                $button = new portfolio_add_button();
            }
            foreach ($files as $file) {
                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_sassessment/submission/'.$submission->id.'/'.$filename);
                $output .= '<a href="'.$path.'" ><img src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" class="icon" alt="'.$mimetype.'" />'.s($filename).'</a>';
                if ($CFG->enableportfolios && $this->portfolio_exportable() && has_capability('mod/sassessment:exportownsubmission', $this->context)) {
                    $button->set_callback_options('sassessment_portfolio_caller', array('id' => $this->cm->id, 'submissionid' => $submission->id, 'fileid' => $file->get_id()), '/mod/sassessment/locallib.php');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }

                if ($CFG->enableplagiarism) {
                    require_once($CFG->libdir.'/plagiarismlib.php');
                    $output .= plagiarism_get_links(array('userid'=>$userid, 'file'=>$file, 'cmid'=>$this->cm->id, 'course'=>$this->course, 'sassessment'=>$this->sassessment));
                    $output .= '<br />';
                }
            }
            if ($CFG->enableportfolios && count($files) > 1  && $this->portfolio_exportable() && has_capability('mod/sassessment:exportownsubmission', $this->context)) {
                $button->set_callback_options('sassessment_portfolio_caller', array('id' => $this->cm->id, 'submissionid' => $submission->id), '/mod/sassessment/locallib.php');
                $output .= '<br />'  . $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
            }
        }

        $output = '<div class="files">'.$output.'</div>';

        if ($return) {
            return $output;
        }
        echo $output;
    }

    /**
     * Count the files uploaded by a given user
     *
     * @param $itemid int The submission's id as the file's itemid.
     * @return int
     */
    function count_user_files($itemid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_sassessment', 'submission', $itemid, "id", false);
        return count($files);
    }

    /**
     * Returns true if the student is allowed to submit
     *
     * Checks that the sassessment has started and, if the option to prevent late
     * submissions is set, also checks that the sassessment has not yet closed.
     * @return boolean
     */
    function isopen() {
        $time = time();
        if ($this->sassessment->preventlate && $this->sassessment->timedue) {
            return ($this->sassessment->timeavailable <= $time && $time <= $this->sassessment->timedue);
        } else {
            return ($this->sassessment->timeavailable <= $time);
        }
    }


    /**
     * Return true if is set description is hidden till available date
     *
     * This is needed by calendar so that hidden descriptions do not
     * come up in upcoming events.
     *
     * Check that description is hidden till available date
     * By default return false
     * sassessments types should implement this method if needed
     * @return boolen
     */
    function description_is_hidden() {
        return false;
    }

    /**
     * Return an outline of the user's interaction with the sassessment
     *
     * The default method prints the grade and timemodified
     * @param $grade object
     * @return object with properties ->info and ->time
     */
    function user_outline($grade) {

        $result = new stdClass();
        $result->info = get_string('grade').': '.$grade->str_long_grade;
        $result->time = $grade->dategraded;
        return $result;
    }

    /**
     * Print complete information about the user's interaction with the sassessment
     *
     * @param $user object
     */
    function user_complete($user, $grade=null) {
        global $OUTPUT;

        if ($submission = $this->get_submission($user->id)) {

            $fs = get_file_storage();

            if ($files = $fs->get_area_files($this->context->id, 'mod_sassessment', 'submission', $submission->id, "timemodified", false)) {
                $countfiles = count($files)." ".get_string("uploadedfiles", "sassessment");
                foreach ($files as $file) {
                    $countfiles .= "; ".$file->get_filename();
                }
            }

            echo $OUTPUT->box_start();
            echo get_string("lastmodified").": ";
            echo userdate($submission->timemodified);
            echo $this->display_lateness($submission->timemodified);

            $this->print_user_files($user->id);

            echo '<br />';

            $this->view_feedback($submission);

            echo $OUTPUT->box_end();

        } else {
            if ($grade) {
                echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
                if ($grade->str_feedback) {
                    echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
                }
            }
            print_string("notsubmittedyet", "sassessment");
        }
    }

    /**
     * Return a string indicating how late a submission is
     *
     * @param $timesubmitted int
     * @return string
     */
    function display_lateness($timesubmitted) {
        return sassessment_display_lateness($timesubmitted, $this->sassessment->timedue);
    }

    /**
     * Empty method stub for all delete actions.
     */
    function delete() {
        //nothing by default
        redirect('view.php?id='.$this->cm->id);
    }

    /**
     * Empty custom feedback grading form.
     */
    function custom_feedbackform($submission, $return=false) {
        //nothing by default
        return '';
    }

    /**
     * Add a get_coursemodule_info function in case any sassessment type wants to add 'extra' information
     * for the course (see resource).
     *
     * Given a course_module object, this function returns any "extra" information that may be needed
     * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
     *
     * @param $coursemodule object The coursemodule object (record).
     * @return cached_cm_info Object used to customise appearance on course page
     */
    function get_coursemodule_info($coursemodule) {
        return null;
    }

    /**
     * Plugin cron method - do not use $this here, create new sassessment instances if needed.
     * @return void
     */
    function cron() {
        //no plugin cron by default - override if needed
    }

    /**
     * Reset all submissions
     */
    function reset_userdata($data) {
        global $CFG, $DB;

        if (!$DB->count_records('sassessment', array('course'=>$data->courseid, 'sassessmenttype'=>$this->type))) {
            return array(); // no sassessments of this type present
        }

        $componentstr = get_string('modulenameplural', 'sassessment');
        $status = array();

        $typestr = get_string('type'.$this->type, 'sassessment');
        // ugly hack to support pluggable sassessment type titles...
        if($typestr === '[[type'.$this->type.']]'){
            $typestr = get_string('type'.$this->type, 'sassessment_'.$this->type);
        }

        if (!empty($data->reset_sassessment_submissions)) {
            $sassessmentssql = "SELECT a.id
                                 FROM {sassessment} a
                                WHERE a.course=? AND a.sassessmenttype=?";
            $params = array($data->courseid, $this->type);

            // now get rid of all submissions and responses
            $fs = get_file_storage();
            if ($sassessments = $DB->get_records_sql($sassessmentssql, $params)) {
                foreach ($sassessments as $sassessmentid=>$unused) {
                    if (!$cm = get_coursemodule_from_instance('sassessment', $sassessmentid)) {
                        continue;
                    }
                    $context = context_module::instance($cm->id);
                    $fs->delete_area_files($context->id, 'mod_sassessment', 'submission');
                    $fs->delete_area_files($context->id, 'mod_sassessment', 'response');
                }
            }

            $DB->delete_records_select('sassessment_submissions', "sassessment IN ($sassessmentssql)", $params);

            $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallsubmissions','sassessment').': '.$typestr, 'error'=>false);

            if (empty($data->reset_gradebook_grades)) {
                // remove all grades from gradebook
                sassessment_reset_gradebook($data->courseid, $this->type);
            }
        }

        /// updating dates - shift may be negative too
        if ($data->timeshift) {
            shift_course_mod_dates('sassessment', array('timedue', 'timeavailable'), $data->timeshift, $data->courseid);
            $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged').': '.$typestr, 'error'=>false);
        }

        return $status;
    }


    function portfolio_exportable() {
        return false;
    }

    /**
     * base implementation for backing up subtype specific information
     * for one single module
     *
     * @param filehandle $bf file handle for xml file to write to
     * @param mixed $preferences the complete backup preference object
     *
     * @return boolean
     *
     * @static
     */
    static function backup_one_mod($bf, $preferences, $sassessment) {
        return true;
    }

    /**
     * base implementation for backing up subtype specific information
     * for one single submission
     *
     * @param filehandle $bf file handle for xml file to write to
     * @param mixed $preferences the complete backup preference object
     * @param object $submission the sassessment submission db record
     *
     * @return boolean
     *
     * @static
     */
    static function backup_one_submission($bf, $preferences, $sassessment, $submission) {
        return true;
    }

    /**
     * base implementation for restoring subtype specific information
     * for one single module
     *
     * @param array  $info the array representing the xml
     * @param object $restore the restore preferences
     *
     * @return boolean
     *
     * @static
     */
    static function restore_one_mod($info, $restore, $sassessment) {
        return true;
    }

    /**
     * base implementation for restoring subtype specific information
     * for one single submission
     *
     * @param object $submission the newly created submission
     * @param array  $info the array representing the xml
     * @param object $restore the restore preferences
     *
     * @return boolean
     *
     * @static
     */
    static function restore_one_submission($info, $restore, $sassessment, $submission) {
        return true;
    }

} ////// End of the sassessment_base class


class mod_sassessment_grading_form extends moodleform {
    /** @var stores the advaned grading instance (if used in grading) */
    private $advancegradinginstance;

    function definition() {
        global $OUTPUT;
        $mform =& $this->_form;

        if (isset($this->_customdata->advancedgradinginstance)) {
            $this->use_advanced_grading($this->_customdata->advancedgradinginstance);
        }

        $formattr = $mform->getAttributes();
        $formattr['id'] = 'submitform';
        $mform->setAttributes($formattr);
        // hidden params
        $mform->addElement('hidden', 'offset', ($this->_customdata->offset+1));
        $mform->setType('offset', PARAM_INT);
        $mform->addElement('hidden', 'userid', $this->_customdata->userid);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'nextid', $this->_customdata->nextid);
        $mform->setType('nextid', PARAM_INT);
        $mform->addElement('hidden', 'id', $this->_customdata->cm->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHANUM);
        $mform->addElement('hidden', 'mode', 'grade');
        $mform->setType('mode', PARAM_TEXT);
        $mform->addElement('hidden', 'menuindex', "0");
        $mform->setType('menuindex', PARAM_INT);
        $mform->addElement('hidden', 'saveuserid', "-1");
        $mform->setType('saveuserid', PARAM_INT);
        $mform->addElement('hidden', 'filter', "0");
        $mform->setType('filter', PARAM_INT);

        $mform->addElement('static', 'picture', $OUTPUT->user_picture($this->_customdata->user),
                                                fullname($this->_customdata->user, true) . '<br/>' .
                                                userdate($this->_customdata->submission->timemodified) .
                                                $this->_customdata->lateness );

        $this->add_submission_content();
        $this->add_grades_section();

        $this->add_feedback_section();

        if ($this->_customdata->submission->timemarked) {
            $datestring = userdate($this->_customdata->submission->timemarked)."&nbsp; (".format_time(time() - $this->_customdata->submission->timemarked).")";
            $mform->addElement('header', 'Last Grade', get_string('lastgrade', 'sassessment'));
            $mform->addElement('static', 'picture', $OUTPUT->user_picture($this->_customdata->teacher) ,
                                                    fullname($this->_customdata->teacher,true).
                                                    '<br/>'.$datestring);
        }
        // buttons
        $this->add_action_buttons();


    }

    /**
     * Gets or sets the instance for advanced grading
     *
     * @param gradingform_instance $gradinginstance
     */
    public function use_advanced_grading($gradinginstance = false) {
        if ($gradinginstance !== false) {
            $this->advancegradinginstance = $gradinginstance;
        }
        return $this->advancegradinginstance;
    }

    function add_grades_section() {
        global $CFG;
        $mform =& $this->_form;
        $attributes = array();
        if ($this->_customdata->gradingdisabled) {
            $attributes['disabled'] ='disabled';
        }

        $mform->addElement('header', 'Grades', get_string('grades', 'grades'));

        $grademenu = make_grades_menu($this->_customdata->sassessment->grade);
        if ($gradinginstance = $this->use_advanced_grading()) {
            $gradinginstance->get_controller()->set_grade_range($grademenu);
            $gradingelement = $mform->addElement('grading', 'advancedgrading', get_string('grade').':', array('gradinginstance' => $gradinginstance));
            if ($this->_customdata->gradingdisabled) {
                $gradingelement->freeze();
            } else {
                $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
                $mform->setType('advancedgradinginstanceid', PARAM_INT);
            }
        } else {
            // use simple direct grading
            $grademenu['-1'] = get_string('nograde');

            $mform->addElement('select', 'xgrade', get_string('grade').':', $grademenu, $attributes);
            $mform->setDefault('xgrade', $this->_customdata->submission->grade ); //@fixme some bug when element called 'grade' makes it break
            $mform->setType('xgrade', PARAM_INT);
        }

        if (!empty($this->_customdata->enableoutcomes)) {
            foreach($this->_customdata->grading_info->outcomes as $n=>$outcome) {
                $options = make_grades_menu(-$outcome->scaleid);
                if ($outcome->grades[$this->_customdata->submission->userid]->locked) {
                    $options[0] = get_string('nooutcome', 'grades');
                    $mform->addElement('static', 'outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->name.':',
                            $options[$outcome->grades[$this->_customdata->submission->userid]->grade]);
                } else {
                    $options[''] = get_string('nooutcome', 'grades');
                    $attributes = array('id' => 'menuoutcome_'.$n );
                    $mform->addElement('select', 'outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->name.':', $options, $attributes );
                    $mform->setType('outcome_'.$n.'['.$this->_customdata->userid.']', PARAM_INT);
                    $mform->setDefault('outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->grades[$this->_customdata->submission->userid]->grade );
                }
            }
        }
        $course_context = context_module::instance($this->_customdata->cm->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            $grade = '<a href="'.$CFG->wwwroot.'/grade/report/grader/index.php?id='. $this->_customdata->courseid .'" >'.
                        $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade . '</a>';
        }else{
            $grade = $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade;
        }
        $mform->addElement('static', 'finalgrade', get_string('currentgrade', 'sassessment').':' ,$grade);
        $mform->setType('finalgrade', PARAM_INT);
    }

    /**
     *
     * @global core_renderer $OUTPUT
     */
    function add_feedback_section() {
        global $OUTPUT;
        $mform =& $this->_form;
        $mform->addElement('header', 'Feed Back', get_string('feedback', 'grades'));

        if ($this->_customdata->gradingdisabled) {
            $mform->addElement('static', 'disabledfeedback', $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_feedback );
        } else {
            // visible elements

            $mform->addElement('editor', 'submissioncomment_editor', get_string('feedback', 'sassessment').':', null, $this->get_editor_options() );
            $mform->setType('submissioncomment_editor', PARAM_RAW); // to be cleaned before display
            $mform->setDefault('submissioncomment_editor', $this->_customdata->submission->submissioncomment);
            //$mform->addRule('submissioncomment', get_string('required'), 'required', null, 'client');
            switch ($this->_customdata->sassessment->sassessmenttype) {
                case 'upload' :
                case 'uploadsingle' :
                    $mform->addElement('filemanager', 'files_filemanager', get_string('responsefiles', 'sassessment'). ':', null, $this->_customdata->fileui_options);
                    break;
                default :
                    break;
            }
            $mform->addElement('hidden', 'mailinfo_h', "0");
            $mform->setType('mailinfo_h', PARAM_INT);
            $mform->addElement('checkbox', 'mailinfo',get_string('enablenotification','sassessment').
            $OUTPUT->help_icon('enablenotification', 'sassessment') .':' );
            $mform->setType('mailinfo', PARAM_INT);
        }
    }

    function add_action_buttons($cancel = true, $submitlabel = NULL) {
        $mform =& $this->_form;
        //if there are more to be graded.
        if ($this->_customdata->nextid>0) {
            $buttonarray=array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
            //@todo: fix accessibility: javascript dependency not necessary
            $buttonarray[] = &$mform->createElement('submit', 'saveandnext', get_string('saveandnext'));
            $buttonarray[] = &$mform->createElement('submit', 'next', get_string('next'));
            $buttonarray[] = &$mform->createElement('cancel');
        } else {
            $buttonarray=array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
            $buttonarray[] = &$mform->createElement('cancel');
        }
        $mform->addGroup($buttonarray, 'grading_buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('grading_buttonar');
        $mform->setType('grading_buttonar', PARAM_RAW);
    }

    function add_submission_content() {
        $mform =& $this->_form;
        $mform->addElement('header', 'Submission', get_string('submission', 'sassessment'));
        $mform->addElement('static', '', '' , $this->_customdata->submission_content );
    }

    protected function get_editor_options() {
        $editoroptions = array();
        $editoroptions['component'] = 'mod_sassessment';
        $editoroptions['filearea'] = 'feedback';
        $editoroptions['noclean'] = false;
        $editoroptions['maxfiles'] = 0; //TODO: no files for now, we need to first implement sassessment_feedback area, integration with gradebook, files support in quickgrading, etc. (skodak)
        $editoroptions['maxbytes'] = $this->_customdata->maxbytes;
        $editoroptions['context'] = $this->_customdata->context;
        return $editoroptions;
    }

    public function set_data($data) {
        $editoroptions = $this->get_editor_options();
        if (!isset($data->text)) {
            $data->text = '';
        }
        if (!isset($data->format)) {
            $data->textformat = FORMAT_HTML;
        } else {
            $data->textformat = $data->format;
        }

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null;
        }

        switch ($this->_customdata->sassessment->sassessmenttype) {
                case 'upload' :
                case 'uploadsingle' :
                    $data = file_prepare_standard_filemanager($data, 'files', $editoroptions, $this->_customdata->context, 'mod_sassessment', 'response', $itemid);
                    break;
                default :
                    break;
        }

        $data = file_prepare_standard_editor($data, 'submissioncomment', $editoroptions, $this->_customdata->context, $editoroptions['component'], $editoroptions['filearea'], $itemid);
        return parent::set_data($data);
    }

    public function get_data() {
        $data = parent::get_data();

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null; //TODO: this is wrong, itemid MUST be known when saving files!! (skodak)
        }

        if ($data) {
            $editoroptions = $this->get_editor_options();
            switch ($this->_customdata->sassessment->sassessmenttype) {
                case 'upload' :
                case 'uploadsingle' :
                    $data = file_postupdate_standard_filemanager($data, 'files', $editoroptions, $this->_customdata->context, 'mod_sassessment', 'response', $itemid);
                    break;
                default :
                    break;
            }
            $data = file_postupdate_standard_editor($data, 'submissioncomment', $editoroptions, $this->_customdata->context, $editoroptions['component'], $editoroptions['filearea'], $itemid);
        }

        if ($this->use_advanced_grading() && !isset($data->advancedgrading)) {
            $data->advancedgrading = null;
        }

        return $data;
    }
}




function sassessment_user_outline($course, $user, $mod, $sassessment) {
    global $CFG;

    require_once("$CFG->libdir/gradelib.php");
    require_once("$CFG->dirroot/mod/sassessment/type/$sassessment->sassessmenttype/sassessment.class.php");
    $sassessmentclass = "sassessment_$sassessment->sassessmenttype";
    $ass = new $sassessmentclass($mod->id, $sassessment, $mod, $course);
    $grades = grade_get_grades($course->id, 'mod', 'sassessment', $sassessment->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        return $ass->user_outline(reset($grades->items[0]->grades));
    } else {
        return null;
    }
}

/**
 * Prints the complete info about a user's interaction with an sassessment
 *
 * This is done by calling the user_complete() method of the sassessment type class
 */
function sassessment_user_complete($course, $user, $mod, $sassessment) {
    global $CFG;

    require_once("$CFG->libdir/gradelib.php");
    require_once("$CFG->dirroot/mod/sassessment/type/$sassessment->sassessmenttype/sassessment.class.php");
    $sassessmentclass = "sassessment_$sassessment->sassessmenttype";
    $ass = new $sassessmentclass($mod->id, $sassessment, $mod, $course);
    $grades = grade_get_grades($course->id, 'mod', 'sassessment', $sassessment->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }
    return $ass->user_complete($user, $grade);
}


/**
 * Return grade for given user or all users.
 *
 * @param int $sassessmentid id of sassessment
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function sassessment_get_user_grades($sassessment, $userid=0) {
    global $CFG, $DB;

    if ($userid) {
        $user = "AND u.id = :userid";
        $params = array('userid'=>$userid);
    } else {
        $user = "";
    }
    $params['aid'] = $sassessment->id;

    $sql = "SELECT u.id, u.id AS userid, s.grade AS rawgrade, s.submissioncomment AS feedback, s.format AS feedbackformat,
                   s.teacher AS usermodified, s.timemarked AS dategraded, s.timemodified AS datesubmitted
              FROM {user} u, {sassessment_submissions} s
             WHERE u.id = s.userid AND s.sassessment = :aid
                   $user";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Update activity grades
 *
 * @param object $sassessment
 * @param int $userid specific user only, 0 means all
 */
function sassessment_update_grades($sassessment, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if ($sassessment->grade == 0) {
        sassessment_grade_item_update($sassessment);

    } else if ($grades = sassessment_get_user_grades($sassessment, $userid)) {
        foreach($grades as $k=>$v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }
        sassessment_grade_item_update($sassessment, $grades);

    } else {
        sassessment_grade_item_update($sassessment);
    }
}

/**
 * Update all grades in gradebook.
 */
function sassessment_upgrade_grades() {
    global $DB;

    $sql = "SELECT COUNT('x')
              FROM {sassessment} a, {course_modules} cm, {modules} m
             WHERE m.name='sassessment' AND m.id=cm.module AND cm.instance=a.id";
    $count = $DB->count_records_sql($sql);

    $sql = "SELECT a.*, cm.idnumber AS cmidnumber, a.course AS courseid
              FROM {sassessment} a, {course_modules} cm, {modules} m
             WHERE m.name='sassessment' AND m.id=cm.module AND cm.instance=a.id";
    $rs = $DB->get_recordset_sql($sql);
    if ($rs->valid()) {
        // too much debug output
        $pbar = new progress_bar('sassessmentupgradegrades', 500, true);
        $i=0;
        foreach ($rs as $sassessment) {
            $i++;
            upgrade_set_timeout(60*5); // set up timeout, may also abort execution
            sassessment_update_grades($sassessment);
            $pbar->update($i, $count, "Updating sassessment grades ($i/$count).");
        }
        upgrade_set_timeout(); // reset to default timeout
    }
    $rs->close();
}

/**
 * Create grade item for given sassessment
 *
 * @param object $sassessment object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
 
function sassessment_grade_item_update($sassessment, $grades=NULL) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($sassessment->courseid)) {
        $sassessment->courseid = $sassessment->course;
    }

    $params = array('itemname'=>$sassessment->name, 'idnumber'=>$sassessment->cmidnumber);

    if ($sassessment->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $sassessment->grade;
        $params['grademin']  = 0;

    } else if ($sassessment->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$sassessment->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/sassessment', $sassessment->courseid, 'mod', 'sassessment', $sassessment->id, 0, $grades, $params);
}


/**
 * Delete grade item for given sassessment
 *
 * @param object $sassessment object
 * @return object sassessment
 */
function sassessment_grade_item_delete($sassessment) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($sassessment->courseid)) {
        $sassessment->courseid = $sassessment->course;
    }

    return grade_update('mod/sassessment', $sassessment->courseid, 'mod', 'sassessment', $sassessment->id, 0, NULL, array('deleted'=>1));
}

/**
 * Returns the users with data in one sassessment (students and teachers)
 *
 * @todo: deprecated - to be deleted in 2.2
 *
 * @param $sassessmentid int
 * @return array of user objects
 */
function sassessment_get_participants($sassessmentid) {
    global $CFG, $DB;

    //Get students
    $students = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                        FROM {user} u,
                                             {sassessment_submissions} a
                                       WHERE a.sassessment = ? and
                                             u.id = a.userid", array($sassessmentid));
    //Get teachers
    $teachers = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                        FROM {user} u,
                                             {sassessment_submissions} a
                                       WHERE a.sassessment = ? and
                                             u.id = a.teacher", array($sassessmentid));

    //Add teachers to students
    if ($teachers) {
        foreach ($teachers as $teacher) {
            $students[$teacher->id] = $teacher;
        }
    }
    //Return students array (it contains an array of unique users)
    return ($students);
}

/**
 * Serves sassessment submissions and other files.
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - just send the file
 */
 /*
function sassessment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$sassessment = $DB->get_record('sassessment', array('id'=>$cm->instance))) {
        return false;
    }

    require_once($CFG->dirroot.'/mod/sassessment/type/'.$sassessment->sassessmenttype.'/sassessment.class.php');
    $sassessmentclass = 'sassessment_'.$sassessment->sassessmenttype;
    $sassessmentinstance = new $sassessmentclass($cm->id, $sassessment, $cm, $course);

    return $sassessmentinstance->send_file($filearea, $args);
}
*/


function sassessment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;
    
    $id = array_shift($args);
    
    //if ($context->contextlevel != CONTEXT_MODULE) {
    //    return false;
    //}

    //require_login($course, false, $cm);

    //if (!$sassessment = $DB->get_record('sassessment', array('id'=>$cm->instance))) {
    //    return false;
    //}
    
    $f = get_file_storage();
    
    if ($file_record = $DB->get_record('files', array('id'=>$id))) {
      $file = $f->get_file_instance($file_record);
      sassessment_send_stored_file($file, 86400, 0, false);  //forcedownload false
      
      //send_stored_file($file);  //forcedownload false
    } else {
      send_file_not_found();
    }
}



function sassessment_send_stored_file($stored_file, $lifetime=86400 , $filter=0, $forcedownload=false, $filename=null, $dontdie=false) {
    global $CFG, $COURSE, $SESSION;

    if (!$stored_file or $stored_file->is_directory()) {
        // nothing to serve
        if ($dontdie) {
            return;
        }
        die;
    }

    if ($dontdie) {
        ignore_user_abort(true);
    }

    \core\session\manager::write_close(); // unlock session during fileserving

    // Use given MIME type if specified, otherwise guess it using mimeinfo.
    // IE, Konqueror and Opera open html file directly in browser from web even when directed to save it to disk :-O
    // only Firefox saves all files locally before opening when content-disposition: attachment stated
    
    $filename     = is_null($filename) ? $stored_file->get_filename() : $filename;
    $isFF         = core_useragent::check_browser_version('Firefox', '1.5'); // only FF > 1.5 properly tested
    $mimetype     = ($forcedownload and !$isFF) ? 'application/x-forcedownload' :
                         ($stored_file->get_mimetype() ? $stored_file->get_mimetype() : mimeinfo('type', $filename));

    $lastmodified = $stored_file->get_timemodified();
    $filesize     = $stored_file->get_filesize();

    if ($lifetime > 0 && !empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        // get unixtime of request header; clip extra junk off first
        $since = strtotime(preg_replace('/;.*$/','',$_SERVER["HTTP_IF_MODIFIED_SINCE"]));
        if ($since && $since >= $lastmodified) {
            header('HTTP/1.1 304 Not Modified');
            header('Expires: '. gmdate('D, d M Y H:i:s', time() + $lifetime) .' GMT');
            header('Cache-Control: max-age='.$lifetime);
            header('Content-Type: '.$mimetype);
            if ($dontdie) {
                return;
            }
            die;
        }
    }

    //do not put '@' before the next header to detect incorrect moodle configurations,
    //error should be better than "weird" empty lines for admins/users
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $lastmodified) .' GMT');

    // if user is using IE, urlencode the filename so that multibyte file name will show up correctly on popup
    if (core_useragent::check_browser_version('MSIE')) {
        $filename = rawurlencode($filename);
    }

    if ($forcedownload) {
        header('Content-Disposition: attachment; filename="'.$filename.'"');
    } else {
        header('Content-Disposition: inline; filename="'.$filename.'"');
    }

        header('Cache-Control: max-age='.$lifetime);
        header('Expires: '. gmdate('D, d M Y H:i:s', time() + $lifetime) .' GMT');
        header('Pragma: ');
        header('Accept-Ranges: bytes');

        if (!empty($_SERVER['HTTP_RANGE']) && strpos($_SERVER['HTTP_RANGE'],'bytes=') !== FALSE) {
            // byteserving stuff - for acrobat reader and download accelerators
            // see: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.35
            // inspired by: http://www.coneural.org/florian/papers/04_byteserving.php
            $ranges = false;
            if (preg_match_all('/(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $ranges, PREG_SET_ORDER)) {
                foreach ($ranges as $key=>$value) {
                    if ($ranges[$key][1] == '') {
                        //suffix case
                        $ranges[$key][1] = $filesize - $ranges[$key][2];
                        $ranges[$key][2] = $filesize - 1;
                    } else if ($ranges[$key][2] == '' || $ranges[$key][2] > $filesize - 1) {
                        //fix range length
                        $ranges[$key][2] = $filesize - 1;
                    }
                    if ($ranges[$key][2] != '' && $ranges[$key][2] < $ranges[$key][1]) {
                        //invalid byte-range ==> ignore header
                        $ranges = false;
                        break;
                    }
                    //prepare multipart header
                    $ranges[$key][0] =  "\r\n--".BYTESERVING_BOUNDARY."\r\nContent-Type: $mimetype\r\n";
                    $ranges[$key][0] .= "Content-Range: bytes {$ranges[$key][1]}-{$ranges[$key][2]}/$filesize\r\n\r\n";
                }
            } else {
                $ranges = false;
            }
            if ($ranges) {
                byteserving_send_file($stored_file->get_content_file_handle(), $mimetype, $ranges, $filesize);
            }
        }

    if (empty($filter)) {
        if ($mimetype == 'text/plain') {
            header('Content-Type: Text/plain; charset=utf-8'); //add encoding
        } else {
            header('Content-Type: '.$mimetype);
        }
        header('Content-Length: '.$filesize);

        //flush the buffers - save memory and disable sid rewrite
        //this also disables zlib compression
        sassessment_prepare_file_content_sending();

        // send the contents
        $stored_file->readfile();
    } else {     // Try to put the file through filters
        if ($mimetype == 'text/html') {
            $options = new stdClass();
            $options->noclean = true;
            $options->nocache = true; // temporary workaround for MDL-5136
            $text = $stored_file->get_content();
            $text = file_modify_html_header($text);
            $output = format_text($text, FORMAT_HTML, $options, $COURSE->id);

            header('Content-Length: '.strlen($output));
            header('Content-Type: text/html');

            //flush the buffers - save memory and disable sid rewrite
            //this also disables zlib compression
            sassessment_prepare_file_content_sending();

            // send the contents
            echo $output;
        } else if (($mimetype == 'text/plain') and ($filter == 1)) {
            // only filter text if filter all files is selected
            $options = new stdClass();
            $options->newlines = false;
            $options->noclean = true;
            $text = $stored_file->get_content();
            $output = '<pre>'. format_text($text, FORMAT_MOODLE, $options, $COURSE->id) .'</pre>';

            header('Content-Length: '.strlen($output));
            header('Content-Type: text/html; charset=utf-8'); //add encoding

            //flush the buffers - save memory and disable sid rewrite
            //this also disables zlib compression
            sassessment_prepare_file_content_sending();

            // send the contents
            echo $output;
        } else {    // Just send it out raw
            header('Content-Length: '.$filesize);
            header('Content-Type: '.$mimetype);

            //flush the buffers - save memory and disable sid rewrite
            //this also disables zlib compression
            sassessment_prepare_file_content_sending();

            // send the contents
            $stored_file->readfile();
        }
    }
    
    if ($dontdie) {
        return;
    }
    die; //no more chars to output!!!
}


function sassessment_prepare_file_content_sending() {
    $olddebug = error_reporting(0);

    if (ini_get_bool('zlib.output_compression')) {
        ini_set('zlib.output_compression', 'Off');
    }

    while(ob_get_level()) {
        if (!ob_end_flush()) {
            break;
        }
    }

    error_reporting($olddebug);
}


function sassessment_is_ios(){
  if (strstr($_SERVER['HTTP_USER_AGENT'], "iPhone") || strstr($_SERVER['HTTP_USER_AGENT'], "iPad")) 
    return true;
  else
    return false;
}


function sassessment_rangeDownload($file) {
  $fp = @fopen($file, 'rb');
 
  $size   = filesize($file); 
  $length = $size;   
  $start  = 0;   
  $end= $size - 1;   

  header("Accept-Ranges: 0-$length");

  if (isset($_SERVER['HTTP_RANGE'])) {
    $c_start = $start;
    $c_end   = $end;

    list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

    if (strpos($range, ',') !== false) {
      header('HTTP/1.1 416 Requested Range Not Satisfiable');
      header("Content-Range: bytes $start-$end/$size");
      exit;
    }

    if ($range0 == '-') {
      $c_start = $size - substr($range, 1);
    } else {
      $range  = explode('-', $range);
      $c_start = $range[0];
      $c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
    }

    $c_end = ($c_end > $end) ? $end : $c_end;

    if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
      header('HTTP/1.1 416 Requested Range Not Satisfiable');
      header("Content-Range: bytes $start-$end/$size");
      exit;
    }
    $start  = $c_start;
    $end= $c_end;
    $length = $end - $start + 1; 
    fseek($fp, $start);
    header('HTTP/1.1 206 Partial Content');
  }

  header("Content-Range: bytes $start-$end/$size");
  header("Content-Length: $length");
 
  $buffer = 1024 * 8;
  while(!feof($fp) && ($p = ftell($fp)) <= $end) {
    if ($p + $buffer > $end) {
      $buffer = $end - $p + 1;
    }
    set_time_limit(0); 
    echo fread($fp, $buffer);
    flush(); 
  }
 
  fclose($fp);
}


function sassessment_get_browser(){
    if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== FALSE) 
        return 'android';
    //elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== FALSE) 
    elseif(preg_match('~MSIE|Internet Explorer~i', $_SERVER['HTTP_USER_AGENT']) || (strpos($_SERVER['HTTP_USER_AGENT'], 'Trident/7.0; rv:11.0') !== false))
        return 'msie';
    elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== FALSE) 
        return 'firefox';
    elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) 
        return 'chrome';
    elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== FALSE || strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== FALSE) 
        return 'mobileios';
    elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== FALSE) 
        return 'safari';
    else 
        return 'other';
}


/**
 * Checks if a scale is being used by an sassessment
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param $sassessmentid int
 * @param $scaleid int
 * @return boolean True if the scale is used by the sassessment
 */
function sassessment_scale_used($sassessmentid, $scaleid) {
    global $DB;

    $return = false;

    $rec = $DB->get_record('sassessment', array('id'=>$sassessmentid,'grade'=>-$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of sassessment
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any sassessment
 */
function sassessment_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('sassessment', array('grade'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Make sure up-to-date events are created for all sassessment instances
 *
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every sassessment event in the site is checked, else
 * only sassessment events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param $courseid int optional If zero then all sassessments for all courses are covered
 * @return boolean Always returns true
 */
function sassessment_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (! $sassessments = $DB->get_records("sassessment")) {
            return true;
        }
    } else {
        if (! $sassessments = $DB->get_records("sassessment", array("course"=>$courseid))) {
            return true;
        }
    }
    $moduleid = $DB->get_field('modules', 'id', array('name'=>'sassessment'));

    foreach ($sassessments as $sassessment) {
        $cm = get_coursemodule_from_id('sassessment', $sassessment->id);
        $event = new stdClass();
        $event->name        = $sassessment->name;
        $event->description = format_module_intro('sassessment', $sassessment, $cm->id);
        $event->timestart   = $sassessment->timedue;

        if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'sassessment', 'instance'=>$sassessment->id))) {
            update_event($event);

        } else {
            $event->courseid    = $sassessment->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'sassessment';
            $event->instance    = $sassessment->id;
            $event->eventtype   = 'due';
            $event->timeduration = 0;
            $event->visible     = $DB->get_field('course_modules', 'visible', array('module'=>$moduleid, 'instance'=>$sassessment->id));
            add_event($event);
        }

    }
    return true;
}

/**
 * Print recent activity from all sassessments in a given course
 *
 * This is used by the recent activity block
 */
function sassessment_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // do not use log table if possible, it may be huge

    if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified, cm.id AS cmid, asb.userid,
                                                     u.firstname, u.lastname, u.email, u.picture
                                                FROM {sassessment_submissions} asb
                                                     JOIN {sassessment} a      ON a.id = asb.sassessment
                                                     JOIN {course_modules} cm ON cm.instance = a.id
                                                     JOIN {modules} md        ON md.id = cm.module
                                                     JOIN {user} u            ON u.id = asb.userid
                                               WHERE asb.timemodified > ? AND
                                                     a.course = ? AND
                                                     md.name = 'sassessment'
                                            ORDER BY asb.timemodified ASC", array($timestart, $course->id))) {
         return false;
    }

    $modinfo =& get_fast_modinfo($course); // reference needed because we might load the groups
    $show    = array();
    $grader  = array();

    foreach($submissions as $submission) {
        if (!array_key_exists($submission->cmid, $modinfo->cms)) {
            continue;
        }
        $cm = $modinfo->cms[$submission->cmid];
        if (!$cm->uservisible) {
            continue;
        }
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }

        // the act of sumbitting of sassessment may be considered private - only graders will see it if specified
        if (empty($CFG->sassessment_showrecentsubmissions)) {
            if (!array_key_exists($cm->id, $grader)) {
                $grader[$cm->id] = has_capability('moodle/grade:viewall', context_module::instance($cm->id));
            }
            if (!$grader[$cm->id]) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups',context_module::instance($cm->id))) {
            if (isguestuser()) {
                // shortcut - guest user does not belong into any group
                continue;
            }

            if (is_null($modinfo->groups)) {
                $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
            }

            // this will be slow - show only users that share group with me in this cm
            if (empty($modinfo->groups[$cm->id])) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->groups[$cm->id]);
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newsubmissions', 'sassessment').':', 3);

    foreach ($show as $submission) {
        $cm = $modinfo->cms[$submission->cmid];
        $link = $CFG->wwwroot.'/mod/sassessment/view.php?id='.$cm->id;
        print_recent_activity_note($submission->timemodified, $submission, $cm->name, $link, false, $viewfullnames);
    }

    return true;
}


/**
 * Returns all sassessments since a given time in specified forum.
 */
function sassessment_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo =& get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $params = array();
    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND gm.groupid = :groupid";
        $groupjoin   = "JOIN {groups_members} gm ON  gm.userid=u.id";
        $params['groupid'] = $groupid;
    } else {
        $groupselect = "";
        $groupjoin   = "";
    }

    $params['cminstance'] = $cm->instance;
    $params['timestart'] = $timestart;

    $userfields = user_picture::fields('u', null, 'userid');

    if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified,
                                                     $userfields
                                                FROM {sassessment_submissions} asb
                                                JOIN {sassessment} a      ON a.id = asb.sassessment
                                                JOIN {user} u            ON u.id = asb.userid
                                          $groupjoin
                                               WHERE asb.timemodified > :timestart AND a.id = :cminstance
                                                     $userselect $groupselect
                                            ORDER BY asb.timemodified ASC", $params)) {
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cm_context      = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cm_context);

    if (is_null($modinfo->groups)) {
        $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
    }

    $show = array();

    foreach($submissions as $submission) {
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }
        // the act of submitting of sassessment may be considered private - only graders will see it if specified
        if (empty($CFG->sassessment_showrecentsubmissions)) {
            if (!$grader) {
                continue;
            }
        }

        if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
            if (isguestuser()) {
                // shortcut - guest user does not belong into any group
                continue;
            }

            // this will be slow - show only users that share group with me in this cm
            if (empty($modinfo->groups[$cm->id])) {
                continue;
            }
            $usersgroups = groups_get_all_groups($course->id, $cm->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->groups[$cm->id]);
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return;
    }

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $userids = array();
        foreach ($show as $id=>$submission) {
            $userids[] = $submission->userid;

        }
        $grades = grade_get_grades($courseid, 'mod', 'sassessment', $cm->instance, $userids);
    }

    $aname = format_string($cm->name,true);
    foreach ($show as $submission) {
        $tmpactivity = new stdClass();

        $tmpactivity->type         = 'sassessment';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $submission->timemodified;

        if ($grader) {
            $tmpactivity->grade = $grades->items[0]->grades[$submission->userid]->str_long_grade;
        }

        $userfields = explode(',', user_picture::fields());
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                $tmpactivity->user->{$userfield} = $submission->userid; // aliased in SQL above
            } else {
                $tmpactivity->user->{$userfield} = $submission->{$userfield};
            }
        }
        $tmpactivity->user->fullname = fullname($submission, $viewfullnames);

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * Print recent activity from all sassessments in a given course
 *
 * This is used by course/recent.php
 */
function sassessment_print_recent_mod_activity($activity, $courseid, $detail, $modnames)  {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="sassessment-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user);
    echo "</td><td>";

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo "<img src=\"" . $OUTPUT->pix_url('icon', 'sassessment') . "\" ".
             "class=\"icon\" alt=\"$modname\">";
        echo "<a href=\"$CFG->wwwroot/mod/sassessment/view.php?id={$activity->cmid}\">{$activity->name}</a>";
        echo '</div>';
    }

    if (isset($activity->grade)) {
        echo '<div class="grade">';
        echo get_string('grade').': ';
        echo $activity->grade;
        echo '</div>';
    }

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
         ."{$activity->user->fullname}</a>  - ".userdate($activity->timestamp);
    echo '</div>';

    echo "</td></tr></table>";
}


function sassessment_display_lateness($timesubmitted, $timedue) {
    if (!$timedue) {
        return '';
    }
    $time = $timedue - $timesubmitted;
    if ($time < 0) {
        $timetext = get_string('late', 'sassessment', format_time($time));
        return ' (<span class="late">'.$timetext.'</span>)';
    } else {
        $timetext = get_string('early', 'sassessment', format_time($time));
        return ' (<span class="early">'.$timetext.'</span>)';
    }
}


function sassessment_get_all_submissions($sassessment, $sort="", $dir="DESC") {
/// Return all sassessment submissions by ENROLLED students (even empty)
    global $CFG, $DB;

    if ($sort == "lastname" or $sort == "firstname") {
        $sort = "u.$sort $dir";
    } else if (empty($sort)) {
        $sort = "a.timemodified DESC";
    } else {
        $sort = "a.$sort $dir";
    }

    /* not sure this is needed at all since sassessment already has a course define, so this join?
    $select = "s.course = '$sassessment->course' AND";
    if ($sassessment->course == SITEID) {
        $select = '';
    }*/

    return $DB->get_records_sql("SELECT a.*
                                   FROM {sassessment_submissions} a, {user} u
                                  WHERE u.id = a.userid
                                        AND a.sassessment = ?
                               ORDER BY $sort", array($sassessment->id));

}


function sassessment_pack_files($filesforzipping) {
        global $CFG;
        //create path for new zip file.
        $tempzip = tempnam($CFG->tempdir.'/', 'sassessment_');
        //zip files
        $zipper = new zip_packer();
        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            return $tempzip;
        }
        return false;
}


function sassessment_view_dates() {
    global $OUTPUT, $sassessment;
    if (!$sassessment->timeavailable && !$sassessment->timedue) {
        return;
    }

    echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');
    echo '<table>';
    if ($sassessment->timeavailable) {
        echo '<tr><td class="c0">'.get_string('availabledate','sassessment').':</td>';
        echo '    <td class="c1">'.userdate($sassessment->timeavailable).'</td></tr>';
    }
    if ($sassessment->timedue) {
        echo '<tr><td class="c0">'.get_string('duedate','sassessment').':</td>';
        echo '    <td class="c1">'.userdate($sassessment->timedue).'</td></tr>';
    }
    echo '</table>';
    echo $OUTPUT->box_end();
}
    

function sassessment_getfile($itemid){
  global $DB, $CFG;
  
  if ($file = $DB->get_record_sql("SELECT * FROM {files} WHERE `itemid`=? AND `filesize` != 0", array($itemid))){
    $contenthash = $file->contenthash;
    $l1 = $contenthash[0].$contenthash[1];
    $l2 = $contenthash[2].$contenthash[3];
    $filepatch = $CFG->dataroot."/filedir/$l1/$l2/$contenthash";
    
    $file->fullpatch = $filepatch;
    
    return $file;
  } else
    return false;
}


function sassessment_getfileid($itemid){
  global $DB, $CFG;
  
  if ($file = $DB->get_record("files", array("id" => $itemid))){
    $contenthash = $file->contenthash;
    $l1 = $contenthash[0].$contenthash[1];
    $l2 = $contenthash[2].$contenthash[3];
    $filepatch = $CFG->dataroot."/filedir/$l1/$l2/$contenthash";
    
    $file->fullpatch = $filepatch;
    
    return $file;
  } else
    return false;
}



function sassessment_player_video($link, $mime = 'video/mp4', $poster = null, $ids = 0){
    global $OUTPUT, $sassessment, $CFG;
    
    $swfflashmediaelement = new moodle_url('/mod/sassessment/swf/flashmediaelement.swf');
    $flowplayer    = new moodle_url("/mod/sassessment/swf/flowplayer-3.2.7.swf");
    
    if ($mime != 'video/mp4') {
        $mime = 'video/mp4';
        
        $player_html5  = '<video width="269" height="198" id="sassessment-player-'.$ids.'" src="'.$link.'" type="'.$mime.'" controls="controls"></video>';
        
        $player_html5_videojs = '<video id="sassessment-player-'.$ids.'" class="video-js vjs-default-skin" controls preload="auto" width="269" height="198" data-setup=\'{"example_option":true}\'> <source src="'.$link.'" type="'.$mime.'" /> </video>';
        
        $player_flash  = html_writer::script('var fn = function() {var att = { data:"'.$swfflashmediaelement.'", width:"269", height:"198" };var par = { flashvars:"controls=true&file='.$link.'" };var id = "sassessment-player-'.$ids.'";var myObject = swfobject.createSWF(att, par, id);};swfobject.addDomLoadEvent(fn);');
        $player_flash .= '<div id="sassessment-player-'.$ids.'"><a href="'.$link.'">audio</a></div>';
        
        $browser = sassessment_get_browser();
        
        if (sassessment_is_ios()) {
            return $player_html5;
        } else if ($browser == 'firefox'){
            return $player_html5_videojs;
        } else if ($browser == 'msie'){
            return $player_flash;
        } else if ($browser == 'chrome'){
            return $player_html5_videojs;
        } else {
            return $player_html5;
        }
    } else {
        $player_flowplayer  = "";
        $player_flowplayer .= html_writer::start_tag('a', array("id" => "sassessment-player-{$ids}", "style" => "display:block;width:269px;height:198px;background: url('".$poster."') no-repeat 0 0;", "href" => $link));
        $player_flowplayer .= html_writer::empty_tag('img', array("src" => new moodle_url("/mod/sassessment/img/playlayer.png"), "alt" => get_string("video", "sassessment"), "width" => 269, "height" => 198));
        $player_flowplayer .= html_writer::end_tag('a');
        $player_flowplayer .= html_writer::script('flowplayer("sassessment-player-'.$ids.'", "'.$flowplayer.'");');
        
        $player_flash  = html_writer::script('var fn = function() {var att = { data:"'.$swfflashmediaelement.'", width:"269", height:"198" };var par = { flashvars:"controls=true&file='.urlencode($link).'&poster='.urlencode($poster).'" };var id = "sassessment-player-'.$ids.'";var myObject = swfobject.createSWF(att, par, id);};swfobject.addDomLoadEvent(fn);');
        $player_flash .= '<div id="sassessment-player-'.$ids.'"><a href="'.$link.'">video</a></div>';
        
        if (!empty($poster)) $poster = 'poster="'.$poster.'"';

        $player_html5 = '<video width="269" height="198" id="sassessment-player-'.$ids.'" src="'.$link.'" type="'.$mime.'" controls="controls" '.$poster.'></video>';
        $player_html5_mediaelementplayer = '<video width="269" height="198" id="sassessment-player-'.$ids.'" src="'.$link.'" type="'.$mime.'" class="mediaelementplayer" controls="controls" '.$poster.'></video>';
        
        $player_html5_videojs = '<video id="sassessment-player-'.$ids.'" controls class="video-js vjs-default-skin" data-setup=\'{"example_option":true}\' preload="auto" width="269" height="198" '.$poster.'> <source src="'.$link.'" type="'.$mime.'" /> </video>';
        
        $browser = sassessment_get_browser();
        
        if (sassessment_is_ios()) {
            return $player_html5;
        } else if ($browser == 'firefox'){
            return $player_flash;
        } else if ($browser == 'msie'){
            return $player_html5_mediaelementplayer;
        } else if ($browser == 'chrome'){
            return $player_html5_mediaelementplayer;
        } else {
            return $player_html5;
        }
    }
}


function sassessment_splayer($ids, $id = null){
    global $DB, $CFG;
    
    if ($file = sassessment_getfile($ids)){
      
    } else if ($file = sassessment_getfileid($ids)){
      
    } else {
      return false;
    }
    
    $link = new moodle_url("/pluginfile.php/".$file->contextid."/mod_sassessment/".$ids."/".$file->id."/".$file->filename);
    
    if ($id != null)
      $o = '<a href="'.$link.'" class="sm2_button" id="'.$id.'">Audio</a>';
    else
      $o = '<a href="'.$link.'" class="sm2_button">Audio</a>';
    
    return $o;
}


function sassessment_player_mp3($link, $mime = 'audio/mp3', $ids = 0){
    global $OUTPUT, $sassessment, $CFG;
        
    $player_html5  = "";
    $player_html5 .= html_writer::start_tag('div', array("id" => "html5-player-".$ids));
    $player_html5 .= html_writer::start_tag('audio', array("id" => "html5-audioplayer-".$ids, "controls" => "controls", "src" => $link));
    $player_html5 .= html_writer::link($link, get_string("audio", "sassessment"));
    $player_html5 .= html_writer::end_tag('audio');
    $player_html5 .= html_writer::end_tag('div');
        
        
    $player_flash  = "";
    $player_flash .= html_writer::script('var fn = function() {var att = { data:"'.(new moodle_url("/mod/sassessment/swf/mp3player.swf")).'", width:"90", height:"15" };var par = { flashvars:"src='.$link.'" };var id = "sassessment-player-'.$ids.'";var myObject = swfobject.createSWF(att, par, id);};swfobject.addDomLoadEvent(fn);');
    $player_flash .= '<div id="sassessment-player-'.$ids.'"><a href="'.$link.'">audio</a></div>';
                
        
    $browser = sassessment_get_browser();
        
    if (sassessment_is_ios()) {
        return $player_html5;
    } else if ($browser == 'firefox'){
        return $player_flash;
    } else if ($browser == 'msie'){
        return $player_flash;
    } else if ($browser == 'chrome'){
        return $player_html5;
    } else {
        return $player_html5;
    }
}


function sassessment_player_youtube($embed, $ids){
    return '<div id="sassessment-player-'.$ids.'" style="cursor: pointer;">
<img src="http://i.ytimg.com/vi/'.$embed.'/0.jpg" class="sassessment-youtube-poster" data-url="'.$ids.'" data-text="'.$embed.'" style="width: 269px; height:198px" />
</div>
';
}


function sassessment_player($ids){
    global $DB, $CFG;
    
    if ($file = sassessment_getfile($ids)){
      
    } else if ($file = sassessment_getfileid($ids)){
      
    } else {
      return false;
    }
    
    $link = new moodle_url("/pluginfile.php/".$file->contextid."/mod_sassessment/".$ids."/".$file->id."/".$file->filename);
    
    if (in_array($file->mimetype, json_decode(SASSESSMENT_VIDEOTYPES))) {
      $o = sassessment_player_video($link, $file->mimetype, null, $file->id);
    } else if (in_array($file->mimetype, json_decode(SASSESSMENT_AUDIOTYPES))) {
      $o = sassessment_player_mp3($link, $file->mimetype, $file->id);
    } else {
      $o = 'Can\'t detect format';
    }
    
    return $o;
}


function sassessment_printanalizeform($text) {
    $data = Array ();
    
    $text = strip_tags ($text);

    if (empty($text)) {
        return array(
            "wordcount" => 0,
            "worduniquecount" => 0,
            "numberofsentences" => 0,
            "averagepersentence" => 0,
            "hardwords" => 0,
            "hardwordspersent" => 0,
            "lexicaldensity" => 0,
            "fogindex" => 0,
            "laters" => 0
        );
    }
    
    $data['wordcount'] = sassessment_wordcount($text);
    $data['worduniquecount'] = sassessment_worduniquecount ($text);
    $data['numberofsentences'] = sassessment_numberofsentences ($text);
    if ($data['numberofsentences'] == 0 || empty($data['numberofsentences'])) {
        $data['numberofsentences'] = 1;
    }
    $data['averagepersentence'] = sassessment_averagepersentence ($text, $data['wordcount'], $data['numberofsentences']);
    list ($data['hardwords'], $data['hardwordspersent']) = sassessment_hardwords ($text, $data['wordcount']);
    $data['lexicaldensity'] = sassessment_lexicaldensity ($text, $data['wordcount'], $data['worduniquecount']);
    $data['fogindex'] = sassessment_fogindex ($text, $data['averagepersentence'], $data['hardwordspersent']);
    $data['laters'] = sassessment_laters ($text);
    
    return $data;
}


function sassessment_wordcount ($text) {
    return str_word_count ($text);
}


function sassessment_worduniquecount ($text) {
    $words  = str_word_count ($text, 1);
    $words_ = Array ();
    
    foreach ($words as $word) {
        if (!in_array($word, $words_)) {
            $words_[] = strtolower ($word);
        }
    }
    return count ($words_);
}


function sassessment_numberofsentences ($text) {
    $text = strip_tags ($text);
    $noneed = array ("\r", "\n", ".0", ".1", ".2", ".3", ".4", ".5", ".6", ".7", ".8", ".9");
    foreach ($noneed as $noneed_) {
        $text = str_replace ($noneed_, " ", $text);
    }
    $text = str_replace ("!", ".", $text);
    $text = str_replace ("?", ".", $text);
    $textarray = explode (".", $text);
    $textarrayf = array();
    foreach ($textarray as $textarray_) {
        if (!empty($textarray_) && strlen ($textarray_) > 5) {
            $textarrayf[] = $textarray_;
        }
    } 
    $count = count($textarrayf);
    return $count;
}


function sassessment_averagepersentence ($text, $words, $sentences) {
    if ($sentences == 0 || empty($sentences)) {
        return 0;
    }
    $count = round($words / $sentences, 2);
    return $count;
}


function sassessment_lexicaldensity ($text, $word, $wordunic) {
    if ($word == 0 || empty($word)) {
        return 0;
    }
    $count = round(($wordunic / $word) * 100, 2);
    return $count;
}


function sassessment_fogindex ($text, $averagepersentence, $hardwordspersent) {
    $count = round(($averagepersentence + $hardwordspersent) * 0.4, 2);
    return $count;
}


function sassessment_laters ($text) {
    $words  = str_word_count ($text, 1);
    $words_ = array();
    $result = array();
    
    $max = 1;
    
    foreach ($words as $word) {
        if (!in_array($word, $words_)) {
            $words_[] = strtolower ($word);
            if (strlen ($word) > $max) {
                $max = strlen ($word);
            }
        }
    }
    
    for ($i=1; $i<=$max; $i++) {
        foreach ($words as $word) {
            if (strlen($word) == $i) {
              if (!isset($result[$i])) {
                $result[$i] = 0;
              }
              $result[$i] ++;
            }
        }
    }
    return $result;
}


function sassessment_hardwords($text, $wordstotal) {
    $syllables = 0;
    $words = explode(' ', $text);
    for ($i = 0; $i < count($words); $i++) {
        if (sassessment_count_syllables($words[$i]) > 2) {
            $syllables ++;
        }
    }

    if ($syllables == 0) {
        return Array(0, 0);
    }

    $score = round(($syllables / $wordstotal) * 100, 2);
    return Array($syllables, $score);
}


function sassessment_count_syllables($word) {
  $nos = strtoupper($word);
  $syllables = 0;
  $before = strlen($nos);
  if ($before >= 2){
    $nos = str_replace(array('AA','AE','AI','AO','AU',
    'EA','EE','EI','EO','EU','IA','IE','II','IO',
    'IU','OA','OE','OI','OO','OU','UA','UE',
    'UI','UO','UU'), "", $nos);
    $after = strlen($nos);
    $diference = $before - $after;
    if($before != $after) $syllables += $diference / 2;
    if($nos[strlen($nos)-1] == "E") $syllables --;
    if($nos[strlen($nos)-1] == "Y") $syllables ++;
    $before = $after;
    $nos = str_replace(array('A','E','I','O','U'),"",$nos);
    $after = strlen($nos);
    $syllables += ($before - $after);
  } else {
    $syllables = 0;
  }
  return $syllables;
}


function sassessment_analizereport($data) {
    $o  =  "";
    $o .=  '<table><tr>';
    $o .=  '<td align="right">Total Word Count: </td><td> <b>' . $data['wordcount'] . '</b></td></tr><tr>';
    $o .=  '<td align="right">Total Unique Words: </td><td> <b>' . $data['worduniquecount'] . '</b></td></tr><tr>';
    $o .=  '<td align="right">Number of Sentences: </td><td> <b>' . $data['numberofsentences'] . '</b></td></tr><tr>';
    $o .=  '<td align="right">Average Words per Sentence: </td><td> <b>' . $data['averagepersentence'] . '</b></td></tr><tr>';
    $o .=  '<td align="right">Hard Words: </td><td> <b>' . $data['hardwords'] . '</b> ('.$data['hardwordspersent'].'%)' . '</td></tr><tr>';
    $o .=  '<td align="right">Lexical Density: </td><td> <b>'.$data['lexicaldensity'].'</b>%' . '</td></tr><tr>';
    $o .=  '<td align="right">Fog Index: </td><td> <b>'.$data['fogindex'] . '</b></td>';
    
    $o .=  '</tr></table>';
    
    return $o;
}
