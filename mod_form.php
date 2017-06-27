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
 * The main sassessment configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_sassessment
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

$PAGE->requires->js('/mod/sassessment/js/jquery.min.js', true);

/**
 * Module instance settings form
 */
class mod_sassessment_mod_form extends moodleform_mod
{

    /**
     * Defines forms elements
     */
    public function definition()
    {
        global $CFG, $DB;

        $mform = $this->_form;

        //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('sassessmentname', 'sassessment'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'sassessmentname', 'sassessment');

        // Adding the standard "intro" and "introformat" fields
        $this->standard_intro_elements();


        $mform->addElement('static', 'label', '', '*Transcription is performed on Google\'s servers. if both: <b>\'transcription\'</b> and <b>\'save audio\'</b> are selected, there is a limit of 10-15 seconds of audio and 50 transcription requests per day. <br /> *Speech recording and transcription will only work with Google Chrome.');

        //-------------------------------------------------------------------------------
        // Adding the rest of sassessment settings, spreeading all them into this fieldset
        // or adding more fieldsets ('header' elements) if needed for better logic
        //$mform->addElement('select', 'grademethod', get_string('grademethod', "sassessment"),
        //                array('transcribe'=>get_string('transcribe', "sassessment"),
        //                      'audio'=>get_string('audio', "sassessment"),
        //                      'textanalysis'=>get_string('textanalysis', "sassessment"),
        //                      'textcomparison'=>get_string('textcomparison', "sassessment"),
        //                      'humanevaluation'=>get_string('humanevaluation', "sassessment")));
        //$mform->setDefault('grademethod', 'transcribe');

        $mform->addElement('select', 'grademethod', get_string('grademethod', "sassessment"),
            array('default' => get_string('default', "sassessment"),
                'rubric' => get_string('rubrics', "sassessment"),
                'rubricauto' => get_string('computerizedgrading', "sassessment")));
        $mform->setDefault('grademethod', 'default');

        $mform->addElement('checkbox', 'transcribe', get_string('transcribe', 'sassessment'));
        $mform->addElement('checkbox', 'audio', get_string('audio', 'sassessment'));
        $mform->addElement('checkbox', 'textanalysis', get_string('textanalysis', 'sassessment'));
        //$mform->addElement('checkbox', 'textcomparison', get_string('textcomparison', 'sassessment'));
        //$mform->addElement('checkbox', 'humanevaluation', get_string('humanevaluation', 'sassessment'));

        $mform->addElement('hidden', 'textcomparison', 1);
        $mform->setType('textcomparison', PARAM_INT);
        $mform->addElement('hidden', 'humanevaluation', 1);
        $mform->setType('humanevaluation', PARAM_INT);

        $availablefromgroup=array();
        $availablefromgroup[] =& $mform->createElement('text', 'autodelete', get_string('autodelete', 'sassessment'), array('size' => '5'));
        $availablefromgroup[] =& $mform->createElement('static', 'label', '', get_string('days', 'sassessment'));
        $mform->addGroup($availablefromgroup, 'availablefromgroup', get_string('autodelete', 'sassessment'), ' ', false);

        //$mform->addElement('select', 'autodelete', get_string('autodelete', "sassessment"), array('0' => 'No', '3' => '3 ' . get_string('days', "sassessment"), '7' => '7 ' . get_string('days', "sassessment"), '14' => '14 ' . get_string('days', "sassessment"), '30' => '30 ' . get_string('days', "sassessment")));
        //$mform->addElement('text', 'autodelete', get_string('autodelete', 'sassessment'), array('size' => '5'));
        $mform->setDefault('autodelete', 0);
        $mform->setType('autodelete', PARAM_INT);

        $mform->addElement('select', 'grade', get_string('simplegrade', "sassessment"), array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10'));
        $mform->setDefault('grade', 5);

        $mform->addElement('select', 'scoretype', get_string('scoretype', 'sassessment'), array('0' => get_string('score', 'sassessment'), '1' => get_string('textfeedback', 'sassessment')));
        $mform->setDefault('scoretype', 0);

        $mform->addElement('header', 'sassessmentitemsset', get_string('scoreTexts', 'sassessment'));

        $mform->addElement('html', '<div id="scorebox"></div>');

        $mform->addElement('static', 'label', '', '<div style="float: right;"><a href="#" onclick="addnewscore(); return false;"><img src="' . $CFG->wwwroot . '/mod/sassessment/img/plus.png" title="' . get_string('addscore', 'sassessment') . '" alt="' . get_string('addscore', 'sassessment') . '" /></a></div><div style="clear:both"></div>');

        /*
        $buttonarray=array();
        $buttonarray[] =& $mform->createElement('text', 'score_1', '', array('size'=>'4'));
        $buttonarray[] =& $mform->createElement('static', 'description', '%', '% ');
        $buttonarray[] =& $mform->createElement('text', 'score_text_1', '', array('size'=>'64'));
        $mform->addGroup($buttonarray, 'buttonar', '<a href="#" onclick="deleteScoreItem(1);return false;">[x]</a>', array(''), false);
        $mform->setType('score_1', PARAM_TEXT);
        $mform->setType('score_text_1', PARAM_TEXT);
        */

        $mform->addElement('header', 'sassessmentitemsset', get_string('itemsandresponses', 'sassessment'));

        $mform->addElement('textarea', 'instructions', get_string("instructions", "sassessment"), 'style="width:600px;height:100px;"');

        $mform->addElement('html', '<div id="itemsandrespbox"></div>');

        //$mform->addElement('textarea', 'var1', 'Item 1', 'wrap="virtual" style="width: 600px;height: 50px;"');
        //$mform->addElement('textarea', 'resp_1_1', 'Sample Response 1', 'wrap="virtual" style="width: 600px;height: 50px;"');
        //$mform->addElement('textarea', 'resp_1_2', 'Sample Response 2', 'wrap="virtual" style="width: 600px;height: 50px;"');
        //$mform->addElement('static', 'label', '', '<div style="float: right;"><a href="#" onclick="addnewresp(1)"><img src="'.$CFG->wwwroot.'/mod/sassessment/img/plus.png" title="'.get_string('addsampleresponse', 'sassessment').'" alt="'.get_string('addsampleresponse', 'sassessment').'" /></a></div><div style="clear:both"></div>');


        $mform->addElement('static', 'label', '<a href="#" id="addnewitem" onclick="addnewitem();return false;">' . get_string('addanotheritem', 'sassessment') . '</a>', '');

        if (!empty($_GET['update']))
            $idpost = $_GET['update'];
        else if (!empty($_GET['course']))
            $idpost = $_GET['course'] . '_' . $_GET['section'];
        else
            $idpost = "";

        if (is_number($idpost)) {
            $cm = get_coursemodule_from_id('sassessment', $idpost, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $sassessment = $DB->get_record('sassessment', array('id' => $cm->instance), '*', MUST_EXIST);
        }

        $mform->addElement('html', '
        <script language="JavaScript">
(function () {
  var script = document.createElement("script");
  script.type = "text/javascript";
  script.src  = "https://cdn.mathjax.org/mathjax/latest/MathJax.js?config=TeX-AMS-MML_HTMLorMML";
  document.getElementsByTagName("head")[0].appendChild(script);
  
  $( "#id_grademethod" ).change(function() {
    if( $(this).val() == "rubric") {
      $("#fitem_id_grade").show();
      $("#fitem_id_scoretype").hide();
    } else if ($(this).val() == "rubricauto"){
      $("#fitem_id_grade").hide();
      $("#fitem_id_scoretype").show();
    } else {
      $("#fitem_id_grade").hide();
      $("#fitem_id_scoretype").hide();
    }
  });
  
  $("#fitem_id_grade").hide();
  $("#fitem_id_scoretype").hide();
})();
        
        function respbox(Iid,Rid){
          return \'<div id="fitem_id_resp_\'+Iid+\'_\'+Rid+\'" class="fitem fitem_ftextarea fim" style="margin:0"><div class="fitemtitle"><label for="id_resp_\'+Iid+\'_\'+Rid+\'">Sample Response \'+Rid+\'</label> <a href="#" onclick="deleteresponse(this);return false;">[x]</a></div><div class="felement ftextarea" id="yui_\'+Iid+\'_\'+Rid+\'"><textarea wrap="virtual" style="width: 600px;height: 50px;" name="resp_\'+Iid+\'_\'+Rid+\'" id="id_resp_\'+Iid+\'_\'+Rid+\'"></textarea></div></div>\';
        }
        function itembox(Iid){
          return \'<div id="fitem_id_var_\'+Iid+\'" class="fitem fitem_ftextarea fim" style="margin:0"><div class="fitemtitle" id="yui_\'+Iid+\'"><label for="id_var\'+Iid+\'" id="yui_\'+Iid+\'">\'+Iid+\'. Enter question or prompt.</label> <a href="#" onclick="deleteitem(\'+Iid+\');return false;">[x]</a></div><div class="felement ftextarea" id="yui_\'+Iid+\'"><textarea wrap="virtual" style="width: 600px;height: 50px;" name="var\'+Iid+\'" id="id_var\'+Iid+\'"></textarea></div>\
          <div id="fitem_id_textcomparison_\'+Iid+\'" class="fitem fitem_fcheckbox " style="margin:40px 0 0 0;"><div class="fitemtitle"><label for="id_respcheck_\'+Iid+\'">Display sample response text</label></div><div class="felement fcheckbox"><span><input name="resp_check_\'+Iid+\'" type="checkbox" value="1" checked="checked" id="id_respcheck_\'+Iid+\'"></span></div></div>\
          </div>\';
        }
        function addnewresponsebox(Iid){
          return \'<div class="fitem femptylabel" id="yui_respa_\'+Iid+\'"><div class="fitemtitle"><div class="fstaticlabel"><label> </label></div></div><div class="felement fstatic" id="yui_respb_\'+Iid+\'"><div style="float: right;" id="yui_resps_\'+Iid+\'"><a href="#" onclick="addnewresp(\'+Iid+\');return false;" id="yui_respd_\'+Iid+\'"><img src="' . $CFG->wwwroot . '/mod/sassessment/img/plus.png" title="' . get_string('addsampleresponse', 'sassessment') . '" alt="' . get_string('addsampleresponse', 'sassessment') . '" id="yui_respe\'+Iid+\'"></a></div><div style="clear:both"></div></div></div>\';
        }
        
        function addnewscorebox(Iid){
          return \'<div id="fitem_id_score_\'+Iid+\'" class="fitem fitem_fgroup "><div class="fitemtitle"><div class="fgrouplabel"><label><a href="#" onclick="deleteScoreItem(\'+Iid+\');return false;">[x]</a> </label></div></div><fieldset class="felement fgroup"><label class="accesshide" for="id_score_\'+Iid+\'">&nbsp;</label><input size="4" name="score_\'+Iid+\'" type="text" id="id_score_\'+Iid+\'">% <label class="accesshide" for="id_score_text_\'+Iid+\'">&nbsp;</label><input size="64" name="score_text_\'+Iid+\'" type="text" id="id_score_text_\'+Iid+\'"></fieldset></div>\';
        }
        function addnewresp(Iid){
          for(i=1; i<=10; i++) {
            if ($("div").is("#fitem_id_resp_"+Iid+"_"+i)==false) {
              var Rid = i;
              break;
            }
          }
          $("#yui_respa_"+Iid).before(respbox(Iid,Rid));
        }
        function addnewscore(){
          for(i=1; i<=10; i++) {
            if ($("div").is("#fitem_id_score_"+i)==false) {
              var Iid = i;
              break;
            }
          }
          $("#scorebox").append(addnewscorebox(Iid));
        }
        function addnewitem(){
          for(i=1; i<=10; i++) {
            if ($("div").is("#fitem_id_var_"+i)==false) {
              var Iid = i;
              break;
            }
          }
          $("#itemsandrespbox").append(itembox(Iid));
          $("#itemsandrespbox").append(respbox(Iid,1));
          $("#itemsandrespbox").append(respbox(Iid,2));
          $("#itemsandrespbox").append(addnewresponsebox(Iid));
        }
        function deleteitem(Iid){
          if (window.confirm("Are you sure?")) {
            $("#fitem_id_var_"+Iid).remove();
            $("#yui_respa_"+Iid).remove();
            for(i=1; i<=10; i++) {
              if ($("div").is("#fitem_id_resp_"+Iid+"_"+i)==true) {
                $("#fitem_id_resp_"+Iid+"_"+i).remove();
              }
            }
            
            //re number cicle
            var lastid = 1;
            for(i=1; i<=10; i++) {
              if ($("div").is("#yui_"+i)==true) {
                $("#yui_"+i+" label").html(lastid+". Enter question or prompt.");
                lastid = lastid + 1;
              }
            }
          }
        }
        function deleteresponse(e){
          if (window.confirm("Are you sure?")) {
            $( e ).parent().parent().remove();
          }
        }
        
        function deleteScoreItem(Iid){
          $("#fitem_id_score_"+Iid).remove();
        }
        
        function postfields(){
          var data  = {};
          var items = {};
          var resp  = {};
          
          for(i=1; i<=10; i++) {
            if ($("div").is("#fitem_id_var_"+i)==true) {
              items[i] = $("#id_var"+i).val();
              resp[i] = [];
            }
            for(j=1; j<=10; j++) {
              if ($("div").is("#fitem_id_resp_"+i+"_"+j)==true) {
                resp[i][j] = $("#id_resp_"+i+"_"+j).val();
              }
            }
          }
          data["item"] = items;
          data["resp"] = resp;
          
          return JSON.stringify(data);
        }
        
        
        $( document ).ready(function() {
          $("#id_submitbutton2").click(function(){
            //var p = postfields();
            //$.post( "' . $CFG->wwwroot . '/mod/sassessment/saveload.php", { id: "' . $idpost . '", a: "save", p: p}, function( data ) {} );
            //return true;
          });
          $("#id_submitbutton").click(function(){
            //var p = postfields();
            //$.post( "' . $CFG->wwwroot . '/mod/sassessment/saveload.php", { id: "' . $idpost . '", a: "save", p: p}, function( data ) {} );
            //return true;
          });
          
          $.get( "' . $CFG->wwwroot . '/mod/sassessment/saveload.php", { id: "' . $idpost . '", a: "loadScore"}, function( data ) {
            if(data) {
              var obj = jQuery.parseJSON(data);
              var i = 0;
              $.each(obj, function(k, v) {
                i++;
                if ($("div").is("#fitem_id_score_"+i)==false) {
                  $("#scorebox").append(addnewscorebox(i));
                }
              }); 
              
              var obj = jQuery.parseJSON(data);
              var i = 0;
              $.each(obj, function(k, v) {
                i++;
                $("#id_score_"+i).val(k);
                $("#id_score_text_"+i).val(v);
              });
            }
          });
          
          $.get( "' . $CFG->wwwroot . '/mod/sassessment/saveload.php", { id: "' . $idpost . '", a: "load"}, function( data ) {
            if(data) {
              var obj = jQuery.parseJSON(data);
              $.each(obj.itemd, function(k, v) {
                if ($("div").is("#fitem_id_var_"+k)==false) {
                  $("#itemsandrespbox").append(itembox(k));
                  $.each(obj.resp[k], function(k2, v2) {
                    if ($("div").is("#fitem_id_resp_"+k+"_"+k2)==false) {
                      $("#itemsandrespbox").append(respbox(k,k2));
                    }
                  });
                  $("#itemsandrespbox").append(addnewresponsebox(k));
                }
              }); 
            
            
              $.each(obj.itemd, function(k, v) {
                $("#id_var"+k).val(v);
              }); 
              $.each(obj.resp, function(k, v) {
                $.each(v, function(k2, v2) {
                  $("#id_resp_"+k+"_"+k2).val(v2);
                });
              }); 
              $.each(obj.varcheck, function(k, v) {
                if (v == 1)
                  $("#id_respcheck_"+k).prop("checked", true);
                else 
                  $("#id_respcheck_"+k).prop("checked", false);
              }); 
            } 
          });
        });
        </script>
        
        <style>
        .fim {
          margin: 0;
          background-color:#fafafa;
          padding-top: 16px;
        }
        </style>
        ');


        if (empty($_GET['update'])) {
            $mform->addElement('html', '
          <script language="JavaScript">
          $("#itemsandrespbox").append(itembox(1));
          $("#itemsandrespbox").append(respbox(1,1));
          $("#itemsandrespbox").append(respbox(1,2));
          $("#itemsandrespbox").append(addnewresponsebox(1));
          </script>
          ');
        }


        /*
        * Upload mp3
        */
        $mform->addElement('header', 'sassessmentitemsset', get_string('uploadmp3', 'sassessment'));
        $mform->addElement('static', 'label', '', '<span style="color: #cc3333">*</span> '.get_string('uplodingwilloveriteaudios', 'sassessment'));

        for ($i = 1; $i <= 10; $i++) {
            if (isset($sassessment->{'file' . $i}) && !empty(($sassessment->{'file' . $i}))) {
                $mform->addElement('static', 'label', '', '<div style="float: right;"><div style="float: left;padding-right:10px;">' . sassessment_player($sassessment->{'file' . $i}) . '</div> <a href="javascript:void(0);" data-text="ajax_form.php?id=' . $idpost . '&a=delete&i=' . $i . '&fileID=' . $sassessment->{'file' . $i} . '&t=file" class="ajax-form-link">' . get_string('deletecurrentaudio', 'sassessment') . '</a></div><div style="clear:both;"></div>');
            }

            $mform->addElement('filepicker', 'submitfile[' . $i . ']', 'item ' . $i . ' (Teacher Question/Prompt)', null, array());

            if (isset($sassessment->{'filesr' . $i}) && !empty(($sassessment->{'filesr' . $i}))) {
                $mform->addElement('static', 'label', '', '<div style="float: right;"><div style="float: left;padding-right:10px;">' . sassessment_player($sassessment->{'file' . $i}) . '</div> <a href="javascript:void(0);" data-text="ajax_form.php?id=' . $idpost . '&a=delete&i=' . $i . '&fileID=' . $sassessment->{'filesr' . $i} . '&t=filesr" class="ajax-form-link">' . get_string('deletecurrentaudio', 'sassessment') . '</a></div><div style="clear:both;"></div>');
            }
            $mform->addElement('filepicker', 'submitfile2[' . $i . ']', 'item ' . $i . ' (Student Response)', null, array());
        }

        $mform->addElement('html', '
          <script language="JavaScript">
          $(".ajax-form-link").click(function() {
              console.log($(this).attr("data-text"));
              $.get(  "' . $CFG->wwwroot . '/mod/sassessment/" + $(this).attr("data-text"), function( data ) {

              });
              $(this).parent().parent().parent().parent().remove();

          });
          </script>
          ');

        //-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
    }
}
