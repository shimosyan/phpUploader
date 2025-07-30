$(document).ready(function(){

  // === 新しいファイル管理システム初期化 (Ver.2.0) ===
  if (window.fileData && document.getElementById('fileManagerContainer')) {
    // DataTables完全廃止・新ファイルマネージャー使用
    const fileManager = new FileManager(
      document.getElementById('fileManagerContainer'), {
        itemsPerPage: 12,
        defaultSort: 'date_desc'
      }
    );
    
    // PHPから渡されたファイルデータを設定
    fileManager.setFiles(window.fileData);
    
    // グローバルに公開（デバッグ・外部操作用）
    window.fileManagerInstance = fileManager;
  }

  // === レガシー DataTables 処理（Ver.2.0では無効化） ===
  if(document.getElementById('fileList') != null && !window.fileData){
    // Ver.1.x互換性のための緊急フォールバック
    console.warn('FileManager initialization failed, falling back to DataTables');
    
    $.extend( $.fn.dataTable.defaults, {
      language: {
        url: 'https://cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json'
      }
    });

    $('#fileList').DataTable({
      "order": [ [0, "desc"] ],
      "columnDefs": [ {
        "ordered": false,
        "targets": [6, 7]  // 共有と削除列をソート無効化
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
  var $header = $(element);
  var $details = $header.next('.file-card__details');
  var $toggle = $header.find('.file-card__toggle');

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

// プログレスバーのテキスト更新
function updateProgressBar(percent) {
  $('#progressBar').css({width: percent + '%'});
  $('#progressText').text(percent + '%');
}

// 画面リサイズ時の対応
$(window).resize(function() {
  // 新ファイルマネージャーのリサイズ対応
  if (window.fileManagerInstance) {
    window.fileManagerInstance.refresh();
  }
  
  // レガシー DataTables 対応（フォールバック用）
  if ($(window).width() > 768 && $.fn.DataTable && $.fn.DataTable.isDataTable('#fileList')) {
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
          updateProgressBar(progre);
        }, false);
      }
      return XHR;
    },
  })
  .done(function(data, textStatus, jqXHR){
    if (data.status === 'success') {
      // 成功時はページをリロード（新ファイルマネージャーはリロード後に更新される）
      showSuccess(data.message || 'ファイルのアップロードが完了しました。');
      setTimeout(function() {
        location.reload();
      }, 1500);
    } else if (data.status === 'error') {
      // Ver.2.0のエラーレスポンス形式に対応
      var errorMessage = '';

      // バリデーションエラーがある場合は詳細を表示
      if (data.validation_errors && data.validation_errors.length > 0) {
        errorMessage = '<strong>バリデーションエラー:</strong><br>' + data.validation_errors.join('<br>');
      } else if (data.message) {
        errorMessage = data.message;
      } else {
        errorMessage = 'アップロードに失敗しました。';
      }

      // エラーコードがある場合は追加情報として表示
      if (data.error_code) {
        errorMessage += '<br><small class="text-muted">(エラーコード: ' + data.error_code + ')</small>';
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

    // レスポンスがJSONの場合は詳細情報を取得
    if (jqXHR.responseJSON) {
      if (jqXHR.responseJSON.message) {
        errorMsg = jqXHR.responseJSON.message;
      }
      if (jqXHR.responseJSON.error_code) {
        errorMsg += '<br><small class="text-muted">(エラーコード: ' + jqXHR.responseJSON.error_code + ')</small>';
      }
      if (jqXHR.responseJSON.validation_errors && jqXHR.responseJSON.validation_errors.length > 0) {
        errorMsg += '<br><strong>詳細:</strong><br>' + jqXHR.responseJSON.validation_errors.join('<br>');
      }
    } else if (jqXHR.responseText) {
      // JSONでない場合はテキスト内容を確認
      try {
        var parsed = JSON.parse(jqXHR.responseText);
        if (parsed.message) {
          errorMsg = parsed.message;
        }
      } catch(e) {
        // JSONパースに失敗した場合はHTTPステータスを表示
        errorMsg += '<br><small class="text-muted">(HTTP ' + jqXHR.status + ': ' + errorThrown + ')</small>';
      }
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
        var errorMessage = data.message || 'ダウンロードに失敗しました。';
        if (data.error_code) {
          errorMessage += '<br><small class="text-muted">(エラーコード: ' + data.error_code + ')</small>';
        }
        showError(errorMessage);
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
    var errorMsg = 'ダウンロード処理でサーバーエラーが発生しました。';

    // レスポンスがJSONの場合は詳細情報を取得
    if (jqXHR.responseJSON) {
      if (jqXHR.responseJSON.message) {
        errorMsg = jqXHR.responseJSON.message;
      }
      if (jqXHR.responseJSON.error_code) {
        errorMsg += '<br><small class="text-muted">(エラーコード: ' + jqXHR.responseJSON.error_code + ')</small>';
      }
    } else if (jqXHR.responseText) {
      try {
        var parsed = JSON.parse(jqXHR.responseText);
        if (parsed.message) {
          errorMsg = parsed.message;
        }
      } catch(e) {
        errorMsg += '<br><small class="text-muted">(HTTP ' + jqXHR.status + ': ' + errorThrown + ')</small>';
      }
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
        var errorMessage = data.message || '削除に失敗しました。';
        if (data.error_code) {
          errorMessage += '<br><small class="text-muted">(エラーコード: ' + data.error_code + ')</small>';
        }
        showError(errorMessage);
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
    var errorMsg = '削除処理でサーバーエラーが発生しました。';
    
    // レスポンスがJSONの場合は詳細情報を取得
    if (jqXHR.responseJSON) {
      if (jqXHR.responseJSON.message) {
        errorMsg = jqXHR.responseJSON.message;
      }
      if (jqXHR.responseJSON.error_code) {
        errorMsg += '<br><small class="text-muted">(エラーコード: ' + jqXHR.responseJSON.error_code + ')</small>';
      }
    } else if (jqXHR.responseText) {
      try {
        var parsed = JSON.parse(jqXHR.responseText);
        if (parsed.message) {
          errorMsg = parsed.message;
        }
      } catch(e) {
        errorMsg += '<br><small class="text-muted">(HTTP ' + jqXHR.status + ': ' + errorThrown + ')</small>';
      }
    }
    
    showError(errorMsg);
  })
  .always(function( jqXHR, textStatus ) {
  });
}


