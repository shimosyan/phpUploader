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

  // ステータスメッセージの自動非表示
  if ($('#statusMessage').length > 0) {
    setTimeout(function() {
      $('#statusMessage').fadeOut();
    }, 5000);
  }
});

// エラー表示ヘルパー関数
function showError(message) {
  $('#errorContainer > .panel-body').html(message);
  $('#errorContainer').fadeIn();
}

// 成功表示ヘルパー関数  
function showSuccess(message) {
  // 成功用のコンテナがない場合は作成
  if ($('#successContainer').length === 0) {
    var successHtml = '<div id="successContainer" class="panel panel-success" style="display: none;">' +
                     '<div class="panel-heading">成功</div>' +
                     '<div class="panel-body"></div>' +
                     '</div>';
    $('#errorContainer').after(successHtml);
  }
  $('#successContainer > .panel-body').html(message);
  $('#successContainer').fadeIn();
  
  // 3秒後に自動で非表示
  setTimeout(function() {
    $('#successContainer').fadeOut();
  }, 3000);
}

// CSRFトークンを取得する関数
function getCSRFToken() {
  return $('#csrfToken').val();
}

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
    showError('ファイルを選択してください。');
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
    if (data.status === 'success') {
      // 成功時はページをリロード
      showSuccess(data.message || 'ファイルのアップロードが完了しました。');
      setTimeout(function() {
        location.reload();
      }, 1500);
    } else if (data.status === 'error') {
      // Ver.2.0のエラーレスポンス形式に対応
      var errorMessage = data.message || 'アップロードに失敗しました。';
      if (data.validation_errors && data.validation_errors.length > 0) {
        errorMessage = data.validation_errors.join('<br>');
      }
      showError(errorMessage);
    } else {
      // 旧バージョン互換
      switch (data.status){
        case 'filesize_over':
            showError('ファイル容量が大きすぎます。');
          break;
        case 'extension_error':
            showError('許可されていない拡張子です。拡張子:'+data.ext);
          break;
        case 'comment_error':
            showError('コメントの文字数が規定数を超えています。');
          break;
        case 'sqlwrite_error':
            showError('データベースの書き込みに失敗しました。');
          break;
        case 'ok':
          location.reload();
          break;
        default:
          showError('アップロードに失敗しました: ' + (data.message || '不明なエラー'));
      }
    }
  })
  .fail(function(jqXHR, textStatus, errorThrown){
    var errorMsg = 'サーバーエラーが発生しました。';
    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
      errorMsg = jqXHR.responseJSON.message;
    }
    showError(errorMsg);
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
  var postdata = {
    id: id,
    key: key,
    csrf_token: getCSRFToken()
  };

  $.ajax({
    url  : './app/api/verifydownload.php',
    type : 'POST',
    data : postdata,
    dataType    : 'json'
  })
  .done(function(data, textStatus, jqXHR){
    if (data.status === 'success') {
      // Ver.2.0の成功レスポンス
      location.href = './download.php?id=' + data.data.id + '&key=' + data.data.token;
    } else if (data.status === 'error') {
      // Ver.2.0のエラーレスポンス
      if (data.error_code === 'AUTH_REQUIRED' || data.error_code === 'INVALID_KEY') {
        // 認証が必要
        var html = '<div class="form-group">' +
                  '<label for="confirmDlkeyInput">DLキーの入力</label>' +
                  '<input type="text" class="form-control" id="confirmDlkeyInput" name="confirmdlkey" placeholder="DLキーを入力...">' +
                  '</div>';
        openModal('okcansel', '認証が必要です', html, 'confirm_dl_button(' + id + ');');
      } else {
        showError(data.message || 'ダウンロードに失敗しました。');
      }
    } else {
      // 旧バージョン互換
      var html = '<div class="form-group">' +
                '<label for="confirmDlkeyInput">DLキーの入力</label>' +
                '<input type="text" class="form-control" id="confirmDlkeyInput" name="confirmdlkey" placeholder="DLキーを入力...">' +
                '</div>';
      switch (data.status){
        case 'failed':
          openModal('okcansel', '認証が必要です', html, 'confirm_dl_button(' + id + ');');
          break;
        case 'ok':
          location.href = './download.php?id=' + data.id + '&key=' + data.key;
          break;
        default:
          showError('ダウンロードに失敗しました。');
      }
    }
  })
  .fail(function(jqXHR, textStatus, errorThrown){
    var errorMsg = 'サーバーエラーが発生しました。';
    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
      errorMsg = jqXHR.responseJSON.message;
    }
    showError(errorMsg);
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
  var postdata = {
    id: id,
    key: key,
    csrf_token: getCSRFToken()
  };

  $.ajax({
    url  : './app/api/verifydelete.php',
    type : 'POST',
    data : postdata,
    dataType    : 'json'
  })
  .done(function(data, textStatus, jqXHR){
    if (data.status === 'success') {
      // Ver.2.0の成功レスポンス
      location.href = './delete.php?id=' + data.data.id + '&key=' + data.data.token;
    } else if (data.status === 'error') {
      // Ver.2.0のエラーレスポンス
      if (data.error_code === 'AUTH_REQUIRED' || data.error_code === 'INVALID_KEY') {
        // 認証が必要
        var html = '<div class="form-group">' +
                  '<label for="confirmDelkeyInput">DELキーの入力</label>' +
                  '<input type="text" class="form-control" id="confirmDelkeyInput" name="confirmdelkey" placeholder="DELキーを入力...">' +
                  '</div>';
        openModal('okcansel', '認証が必要です', html, 'confirm_del_button(' + id + ');');
      } else {
        showError(data.message || '削除に失敗しました。');
      }
    } else {
      // 旧バージョン互換
      var html = '<div class="form-group">' +
                '<label for="confirmDelkeyInput">DELキーの入力</label>' +
                '<input type="text" class="form-control" id="confirmDelkeyInput" name="confirmdelkey" placeholder="DELキーを入力...">' +
                '</div>';
      switch (data.status){
        case 'failed':
          openModal('okcansel', '認証が必要です', html, 'confirm_del_button(' + id + ');');
          break;
        case 'ok':
          location.href = './delete.php?id=' + data.id + '&key=' + data.key;
          break;
        default:
          showError('削除に失敗しました。');
      }
    }
  })
  .fail(function(jqXHR, textStatus, errorThrown){
    var errorMsg = 'サーバーエラーが発生しました。';
    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
      errorMsg = jqXHR.responseJSON.message;
    }
    showError(errorMsg);
  })
  .always(function( jqXHR, textStatus ) {
  });
}
