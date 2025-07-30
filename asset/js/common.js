$(document).ready(function(){

  if(document.getElementById('fileList') != null){

    $.extend( $.fn.dataTable.defaults, {
      language: {
        url: 'https://cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json'
      }
    });

    $('#fileList').DataTable({
      "order": [ [0, "desc"] ],
      "columnDefs": [ {
        "ordered": false,
        "targets": [6]
      } ]
    });
  }

  $('input[id=lefile]').change(function() {
    $('#fileInput').val($(this).val().replace('C:\\fakepath\\', ''));
  });
});

// カードの詳細部分の開閉機能
function toggleCardDetails(element) {
  var $main = $(element);
  var $details = $main.next('.file-card__details');
  var $toggle = $main.find('.file-card__toggle');
  
  if ($details.hasClass('expanded')) {
    // 閉じる
    $details.removeClass('expanded');
    $toggle.removeClass('expanded');
  } else {
    // 開く
    $details.addClass('expanded');
    $toggle.addClass('expanded');
  }
}

// 画面リサイズ時の対応（必要に応じて）
$(window).resize(function() {
  // DataTablesのリサイズ対応（デスクトップ表示時）
  if ($(window).width() > 768 && $.fn.DataTable.isDataTable('#fileList')) {
    $('#fileList').DataTable().columns.adjust();
  }
});

function file_upload()
{
  if($('#fileInput').val() == ''){
    return;
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
    $('#errorContainer > .panel-body').html('サーバーエラー<br>jqXHR: '+JSON.stringify(jqXHR)+'<br>textSattus: '+textStatus+'<br>errorThrown: '+errorThrown);
    $('#errorContainer').fadeIn();
  })
  .always(function( jqXHR, textStatus ) {
    $('#uploadContainer').hide();
  });
}

// DLボタンを押すと実行
function dl_button(id){
  // DLkey空白で投げる
  dl_certificat(id ,'');
}

function confirm_dl_button(id){
  closeModal();
  dl_certificat(id ,$('#confirmDlkeyInput').val());
}

function dl_certificat(id, key){
  var postdata ={
    id: id,
    key: key
  }

  $.ajax({
    url  : './app/api/verifydownload.php',
    type : 'POST',
    data : postdata,
    dataType    : 'json'
  })
  .done(function(data, textStatus, jqXHR){
    var html = '<div class="form-group"><label for="confirmDlkeyInput">DLキーの入力</label><input type="text" class="form-control" id="confirmDlkeyInput" name="confirmdlkey" placeholder="DLキーを入力..."></div>';
    switch (data.status){
      case 'failed':
        openModal('okcansel', '認証が必要です', html, 'confirm_dl_button('+id+');');
        break;
      case 'ok':
        location.href = './download.php?id='+data.id+'&key='+data.key;
        break;
    }

  })
  .fail(function(jqXHR, textStatus, errorThrown){
    alert(JSON.stringify(jqXHR));
  })
  .always(function( jqXHR, textStatus ) {
  });
}

// DELボタンを押すと実行
function del_button(id){
  // DLkey空白で投げる
  del_certificat(id ,'');
}

function confirm_del_button(id){
  closeModal();
  del_certificat(id ,$('#confirmDelkeyInput').val());
}

function del_certificat(id, key){
  var postdata ={
    id: id,
    key: key
  }

  $.ajax({
    url  : './app/api/verifydelete.php',
    type : 'POST',
    data : postdata,
    dataType    : 'json'
  })
  .done(function(data, textStatus, jqXHR){
    //alert(data.tmp_file);

    var html = '<div class="form-group"><label for="confirmDelkeyInput">DELキーの入力</label><input type="text" class="form-control" id="confirmDelkeyInput" name="confirmdelkey" placeholder="DELキーを入力..."></div>';
    switch (data.status){
      case 'failed':
        openModal('okcansel', '認証が必要です', html, 'confirm_del_button('+id+');');
        break;
      case 'ok':
        location.href = './delete.php?id='+data.id+'&key='+data.key;
        break;
    }

  })
  .fail(function(jqXHR, textStatus, errorThrown){
    alert(JSON.stringify(jqXHR));
  })
  .always(function( jqXHR, textStatus ) {
  });
}
