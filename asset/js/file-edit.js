// ファイル編集機能
function editFile(fileId, fileName, comment) {
  // モーダルに情報を設定
  $('#editFileId').val(fileId);
  $('#replaceFileId').val(fileId);
  $('#editFileName').val(fileName);
  $('#replaceFileName').val(fileName);
  $('#editComment').val(comment);
  
  // タブを初期状態に戻す
  $('.nav-tabs a[href="#commentTab"]').tab('show');
  
  // モーダルを表示
  $('#editModal').modal('show');
}

// タブ切り替え時のボタン表示制御とイベント処理
$(document).ready(function() {
  // タブ切り替えイベント
  $(document).on('shown.bs.tab', '.nav-tabs a[data-toggle="tab"]', function (e) {
    var target = $(e.target).attr('href');
    
    if (target === '#commentTab') {
      $('#saveCommentBtn').show();
      $('#replaceFileBtn').hide();
    } else if (target === '#replaceTab') {
      $('#saveCommentBtn').hide();
      $('#replaceFileBtn').show();
    }
  });
  
  // コメント保存ボタンのクリックイベント
  $(document).on('click', '#saveCommentBtn', function() {
    var fileId = $('#editFileId').val();
    var comment = $('#editComment').val().trim();
    
    if (!fileId) {
      alert('ファイルIDが見つかりません。');
      return;
    }
    
    // APIキーを取得（実際の実装では設定ファイルから取得）
    var apiKey = 'CHANGE_THIS_API_KEY_1'; // 実装時には適切に設定
    
    $.ajax({
      url: './app/api/index.php?path=/api/files/' + fileId,
      type: 'PATCH',
      headers: {
        'Authorization': 'Bearer ' + apiKey,
        'Content-Type': 'application/json'
      },
      data: JSON.stringify({
        comment: comment
      }),
      dataType: 'json'
    })
    .done(function(data) {
      if (data.success) {
        alert('コメントを更新しました。');
        $('#editModal').modal('hide');
        location.reload(); // ページを再読み込みして変更を反映
      } else {
        alert('エラー: ' + (data.error ? data.error.message : '不明なエラー'));
      }
    })
    .fail(function(xhr) {
      var errorMessage = 'コメントの更新に失敗しました。';
      try {
        var errorData = JSON.parse(xhr.responseText);
        if (errorData.error && errorData.error.message) {
          errorMessage = errorData.error.message;
        }
      } catch (e) {
        // JSON解析に失敗した場合はデフォルトメッセージを使用
      }
      alert('エラー: ' + errorMessage);
    });
  });
  
  // ファイル差し替えボタンのクリックイベント
  $(document).on('click', '#replaceFileBtn', function() {
    var fileId = $('#replaceFileId').val();
    var fileInput = $('#replaceFileInput')[0];
    
    if (!fileId) {
      alert('ファイルIDが見つかりません。');
      return;
    }
    
    if (!fileInput.files || fileInput.files.length === 0) {
      alert('ファイルを選択してください。');
      return;
    }
    
    if (!confirm('ファイルを差し替えます。元のファイルは削除されます。この操作は取り消せません。よろしいですか？')) {
      return;
    }
    
    // 差し替えキーを取得
    var replaceKey = $('#replaceKeyInput').val();
    if (!replaceKey) {
      alert('差し替えキーを入力してください。');
      return;
    }
    
    // FormDataを作成
    var formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('replacekey', replaceKey);
    
    // APIキーを取得（実際の実装では設定ファイルから取得）
    var apiKey = 'CHANGE_THIS_API_KEY_1'; // 実装時には適切に設定
    
    $.ajax({
      url: './app/api/index.php?path=/api/files/' + fileId + '/replace',
      type: 'POST',
      headers: {
        'Authorization': 'Bearer ' + apiKey
      },
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json'
    })
    .done(function(data) {
      if (data.success) {
        alert('ファイルを差し替えました。');
        $('#editModal').modal('hide');
        location.reload(); // ページを再読み込みして変更を反映
      } else {
        alert('エラー: ' + (data.error ? data.error.message : '不明なエラー'));
      }
    })
    .fail(function(xhr) {
      var errorMessage = 'ファイルの差し替えに失敗しました。';
      try {
        var errorData = JSON.parse(xhr.responseText);
        if (errorData.error && errorData.error.message) {
          errorMessage = errorData.error.message;
        }
      } catch (e) {
        // JSON解析に失敗した場合はデフォルトメッセージを使用
      }
      alert('エラー: ' + errorMessage);
    });
  });
});