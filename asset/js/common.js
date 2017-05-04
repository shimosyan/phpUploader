$(document).ready(function(){

  if(document.getElementById("fileList") != null){

    $.extend( $.fn.dataTable.defaults, { 
      language: {
        url: "http://cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
      } 
    });

    $('#fileList').DataTable();
  }

  $('input[id=lefile]').change(function() {
    $('#fileInput').val($(this).val().replace("C:\\fakepath\\", ""));
  });
});

function file_upload()
{
  if($('#fileInput').val() == ''){
    retuen;
  }

  $('#errorContainer').fadeOut();
  $('#uploadContainer').fadeIn();
   // フォームデータを取得
  var formdata = new FormData($('#upload').get(0));

  // POSTでアップロード

  $.ajax({
    url  : './app/api/upload.php',
    type : 'POST',
    data : formdata,
    cache       : false,
    contentType : false,
    processData : false,
    dataType    : 'json',
    async: true,
    xhr : function(){
      var XHR = $.ajaxSettings.xhr();
      if(XHR.upload){
        XHR.upload.addEventListener('progress',function(e){
          var progre = parseInt(e.loaded/e.total*100);
          $('#progressBar').css({width: progre+'%'});
        }, false);
      }
      return XHR;
    },
  })
  .done(function(data, textStatus, jqXHR){
    //alert(data.tmp_file);
    switch (data.status){
      case 'filesize_over':
          $('#errorContainer > .panel-body').text('ファイル容量が大きすぎます。');
          $('#errorContainer').fadeIn();
        break;
      case 'extension_error':
          $('#errorContainer > .panel-body').text('許可されていない拡張子です。拡張子:'+data.ext);
          $('#errorContainer').fadeIn();
        break;
      case 'comment_error':
          $('#errorContainer > .panel-body').text('コメントの文字数が規定数を超えています。');
          $('#errorContainer').fadeIn();
        break;
      case 'sqlwrite_error':
          $('#errorContainer > .panel-body').text('データベースの書き込みに失敗しました。');
          $('#errorContainer').fadeIn();
        break;
      case 'ok':
        location.reload();
        break;
    }

  })
  .fail(function(jqXHR, textStatus, errorThrown){
    $('#errorContainer > .panel-body').text('サーバーエラー');
    $('#errorContainer').fadeIn();
  })
  .always(function( jqXHR, textStatus ) {
    $('#uploadContainer').fadeOut();
  });
}