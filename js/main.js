window.recordmark = 0;
window.active     = 1;

insertAtCaret = function(areaId,text) {
        var txtarea = document.getElementById(areaId);
        var scrollPos = txtarea.scrollTop;
        var strPos = 0;
        
        strPos = txtarea.selectionStart;

        var front = (txtarea.value).substring(0,strPos);
        var back = (txtarea.value).substring(strPos,txtarea.value.length);
        txtarea.value=front+text+back;
        strPos = strPos + text.length;
        txtarea.selectionStart = strPos;
        txtarea.selectionEnd = strPos;
        txtarea.focus();
        txtarea.scrollTop = scrollPos;
};

$.fn.getCursorPosition = function() {
        var el = $(this).get(0);
        var pos = 0;
        if('selectionStart' in el) {
            pos = el.selectionStart;
        } else if('selection' in document) {
            el.focus();
            var Sel = document.selection.createRange();
            var SelLength = document.selection.createRange().text.length;
            Sel.moveStart('character', -el.value.length);
            pos = Sel.text.length - SelLength;
        }
        return pos;
};

$.fn.setCursorPosition = function(pos) {
        if ($(this).get(0).setSelectionRange) {
            $(this).get(0).setSelectionRange(pos, pos);
        } else if ($(this).get(0).createTextRange) {
            var range = $(this).get(0).createTextRange();
            range.collapse(true);
            range.moveEnd('character', pos);
            range.moveStart('character', pos);
            range.select();
        }
}


try {
    var recognition = new webkitSpeechRecognition();
} catch(e) {
    var recognition = Object;
}
recognition.continuous = true;
recognition.interimResults = true;
recognition.lang = "en";

var interimResult = '';

recognition.onresult = function (event) {
    var pos = $('#answer_'+window.active).getCursorPosition() - interimResult.length;
    $('#answer_'+window.active).val($('#answer_'+window.active).val().replace(interimResult, ''));
    interimResult = '';
    $('#answer_'+window.active).setCursorPosition(pos);
    for (var i = event.resultIndex; i < event.results.length; ++i) {
      if (event.results[i].isFinal) {
          insertAtCaret('answer_'+window.active, event.results[i][0].transcript);
      } else {
          isFinished = false;
          insertAtCaret('answer_'+window.active, event.results[i][0].transcript + '\u200B');
          interimResult += event.results[i][0].transcript + '\u200B';
      }
    }
    
    $('#answer_div_'+window.active).html( $('#answer_'+window.active).val() );
    
    window.recordmark = 1;
};

recognition.onstart = function() {
    $('#answer_'+window.active).val("");
    $('#speech-content-mic_'+window.active).removeClass('speech-mic').addClass('speech-mic-works');
    $('#answer_'+window.active).focus();
    window.recordmark = 1;
};

recognition.onend = function() {
    $('#speech-content-mic_'+window.active).removeClass('speech-mic-works').addClass('speech-mic');
    window.recordmark = 0;
};




function recordSTT(ids){
    console.log("mark:"+window.recordmark);
    if (window.recordmark == 0) {
      console.log('started '+ids);
      recognition.start();
      window.active = ids;
      window.recordmark = ids;
    } else {
      recognition.stop();
      window.recordmark = 0;
    }
}



$(document).ready(function() {
  $(".sassessment_rate_box").change(function() {
    var value = $(this).val();
    var data  = $(this).attr("data-url");
    
    var e = $(this).parent();
    e.html('<img src="img/ajax-loader.gif" />');
    
    $.get("ajax.php", {act: "setrating", data: data, value: value}, function(data) {
      e.html(data); 
    });
  });
 });