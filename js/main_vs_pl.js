window.recordmark = 0;

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
};


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
    //console.log(event);

    var pos = $('#speechtext').get(0) - interimResult.length;
    $('#speechtext').val($('#speechtext').val().replace(interimResult, ''));
    interimResult = '';
    $('#speechtext').get(0).setSelectionRange;
    for (var i = event.resultIndex; i < event.results.length; ++i) {
      if (event.results[i].isFinal) {
          insertAtCaret('speechtext', event.results[i][0].transcript);
      } else {
          isFinished = false;
          insertAtCaret('speechtext', event.results[i][0].transcript + '\u200B');
          interimResult += event.results[i][0].transcript + '\u200B';
      }
    }

    var textarea = document.getElementById('speechtext');
    textarea.scrollTop = textarea.scrollHeight;

    window.recordmark = 1;
};

recognition.onstart = function() {
    $('#speechtext').focus();
    window.recordmark = 1;
};

recognition.onend = function() {
    window.recordmark = 0;

    var id = 'transcript_'+$('.selectaudiomodel').val();

    $("#id_speechtext").val("" + $("#speechtext").val() + "");

};


$( document ).ready(function() {
    //var audioElement = document.createElement('audio');

    $('#btn_rec').click(function() {
        $('.p-content').show();
        recognition.start();
        window.recordmark = 1;
    });

    $('#btn_stop').click(function() {
        recognition.stop();
        window.recordmark = 0;
    });



});
