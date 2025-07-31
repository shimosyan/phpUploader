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

// === Ver.2.0 新FileManagerRenderer用の個別関数 ===

/**
 * 共有リンク生成モーダルを開く
 */
function shareFile(fileId) {
  // グローバル変数として保存（生成ボタンで使用）
  window.currentShareFileId = fileId;
  
  // ファイル情報を取得してモーダルを表示
  // まずはファイル情報だけ表示して、設定パネルを開く
  if (window.fileData) {
    const file = window.fileData.find(f => f.id == fileId);
    if (file) {
      showShareLinkModal(file.origin_file_name, file.comment);
    } else {
      showError('ファイル情報が見つかりません');
    }
  } else {
    showError('ファイルデータが読み込まれていません');
  }
}

/**
 * 実際の共有リンク生成API呼び出し
 */
function generateShareLink(fileId, maxDownloads, expiresDays) {
  const formData = new FormData();
  formData.append('id', fileId);
  
  if (maxDownloads && maxDownloads > 0) {
    formData.append('max_downloads', maxDownloads);
  }
  if (expiresDays && expiresDays > 0) {
    formData.append('expires_days', expiresDays);
  }
  
  // 生成ボタンを無効化
  $('#generateShareLinkBtn').prop('disabled', true).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> 生成中...');
  
  fetch('./app/api/generatesharelink.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    console.log('Share link response:', data); // デバッグ用ログ
    if (data.status === 'success' || data.status === 'ok') {
      // 共有リンクをモーダルで表示
      const shareUrl = data.share_url || data.url || data.link;
      const shareUrlWithComment = data.share_url_with_comment || shareUrl;
      if (shareUrl) {
        // 結果パネルを表示
        displayShareLinkResult(shareUrl, shareUrlWithComment, data.current_max_downloads, data.current_expires_days);
        
        // デフォルトでURLのみをクリップボードにコピー
        if (navigator.clipboard) {
          navigator.clipboard.writeText(shareUrl);
          console.log('共有リンクをクリップボードにコピーしました:', shareUrl);
        }
      } else {
        showError('共有リンクが生成されましたが、URLが取得できませんでした');
      }
    } else {
      showError(data.message || '共有リンクの生成に失敗しました (status: ' + data.status + ')');
    }
  })
  .catch(error => {
    console.error('Share link error:', error);
    showError('共有リンクの生成中にエラーが発生しました');
  })
  .finally(() => {
    // 生成ボタンを有効化
    $('#generateShareLinkBtn').prop('disabled', false).html('<span class="glyphicon glyphicon-link"></span> 共有リンクを生成');
  });
}

/**
 * コメント編集モーダルを開く
 */
function editComment(fileId, currentComment, fileName) {
  // ファイル情報を取得
  if (!fileName && window.fileData) {
    const file = window.fileData.find(f => f.id == fileId);
    fileName = file ? file.origin_file_name : '';
  }
  
  // モーダルに情報を設定
  $('#editFileId').val(fileId);
  $('#editFileName').val(fileName || '');
  $('#replaceFileName').val(fileName || ''); // コメント編集から差し替えタブに切り替えたとき用に設定
  $('#replaceFileId').val(fileId); // コメント編集開始時にファイルIDを保持
  $('#editComment').val(currentComment || '');
  
  // コメント編集タブを表示
  $('.nav-tabs a[href="#commentTab"]').tab('show');
  
  // 保存ボタンを表示・差し替えボタンを非表示
  $('#saveCommentBtn').show();
  $('#replaceFileBtn').hide();
  
  // モーダルを表示
  $('#editModal').modal('show');
}

/**
 * ファイル差し替えモーダルを開く
 */
function replaceFile(fileId, currentFilename) {
  // ファイル情報を取得
  if (!currentFilename && window.fileData) {
    const file = window.fileData.find(f => f.id == fileId);
    currentFilename = file ? file.origin_file_name : '';
  }
  
  // モーダルに情報を設定
  $('#replaceFileId').val(fileId);
  $('#replaceFileName').val(currentFilename || '');
  $('#editFileName').val(currentFilename || ''); // 編集用にも設定
  
  // ファイル差し替えタブを表示
  $('.nav-tabs a[href="#replaceTab"]').tab('show');
  
  // 差し替えボタンを表示・保存ボタンを非表示
  $('#replaceFileBtn').show();
  $('#saveCommentBtn').hide();
  
  // モーダルを表示
  $('#editModal').modal('show');
}

/**
 * 共有リンクモーダルを表示（初期状態）
 */
function showShareLinkModal(filename, comment) {
  // ファイル情報を設定
  $('#shareFileName').val(filename || '');
  $('#shareFileComment').val(comment || '');
  
  // 設定をリセット
  $('#shareMaxDownloads').val('');
  $('#shareExpiresDays').val('');
  
  // 結果パネルを非表示・再生成ボタンを非表示
  $('#shareResultPanel').hide();
  $('#regenerateShareLinkBtn').hide();
  $('#generateShareLinkBtn').show();
  
  // モーダルを表示
  $('#shareLinkModal').modal('show');
}

/**
 * 共有リンク結果を表示
 */
function displayShareLinkResult(shareUrl, shareUrlWithComment, maxDownloads, expiresDays) {
  // 共有リンクデータをグローバル変数に保存
  window.shareData = {
    urlOnly: shareUrl,
    urlWithComment: shareUrlWithComment
  };
  
  // デフォルトでURLのみを表示
  $('#shareUrl').val(shareUrl);
  $('input[name="shareFormat"][value="url_only"]').prop('checked', true);
  
  // 現在の設定を表示
  const maxDownloadsText = maxDownloads ? `${maxDownloads}回` : '制限なし';
  const expiresDaysText = expiresDays ? `${expiresDays}日後` : '制限なし';
  
  $('#currentMaxDownloads').html(`<strong>最大ダウンロード数:</strong> ${maxDownloadsText}`);
  $('#currentExpiresDays').html(`<strong>有効期限:</strong> ${expiresDaysText}`);
  
  // 結果パネルを表示・再生成ボタンを表示
  $('#shareResultPanel').show();
  $('#regenerateShareLinkBtn').show();
  $('#generateShareLinkBtn').hide();
}

/**
 * 共有形式に応じてテキストエリアの内容を更新
 */
function updateShareContent() {
  const selectedFormat = $('input[name="shareFormat"]:checked').val();
  const shareData = window.shareData;
  
  if (shareData) {
    if (selectedFormat === 'url_only') {
      $('#shareUrl').val(shareData.urlOnly);
    } else if (selectedFormat === 'url_with_comment') {
      $('#shareUrl').val(shareData.urlWithComment);
    }
  }
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

  // 共有リンク生成ボタンイベント
  $(document).on('click', '#generateShareLinkBtn', function() {
    const fileId = window.currentShareFileId;
    const maxDownloads = parseInt($('#shareMaxDownloads').val()) || null;
    const expiresDays = parseInt($('#shareExpiresDays').val()) || null;
    
    if (fileId) {
      generateShareLink(fileId, maxDownloads, expiresDays);
    } else {
      showError('ファイルIDが取得できません');
    }
  });

  // 共有リンク再生成ボタンイベント
  $(document).on('click', '#regenerateShareLinkBtn', function() {
    const fileId = window.currentShareFileId;
    const maxDownloads = parseInt($('#shareMaxDownloads').val()) || null;
    const expiresDays = parseInt($('#shareExpiresDays').val()) || null;
    
    if (fileId) {
      // 結果パネルを非表示にして再生成
      $('#shareResultPanel').hide();
      $('#regenerateShareLinkBtn').hide();
      $('#generateShareLinkBtn').show();
      generateShareLink(fileId, maxDownloads, expiresDays);
    } else {
      showError('ファイルIDが取得できません');
    }
  });

  // 共有形式変更イベント
  $(document).on('change', 'input[name="shareFormat"]', function() {
    updateShareContent();
  });

  // 共有リンクモーダルのコピーボタンイベント
  $(document).on('click', '#copyShareUrlBtn', function() {
    const shareContent = $('#shareUrl').val();
    const selectedFormat = $('input[name="shareFormat"]:checked').val();
    const formatText = selectedFormat === 'url_only' ? 'URL' : 'コメント付きURL';
    
    if (shareContent) {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(shareContent).then(function() {
          showSuccess(`${formatText}をクリップボードにコピーしました！`);
        }).catch(function(err) {
          console.error('クリップボードコピーエラー:', err);
          // フォールバック: テキストエリアを選択
          $('#shareUrl').select();
          showSuccess(`${formatText}を選択しました。Ctrl+Cでコピーしてください。`);
        });
      } else {
        // クリップボードAPIが使えない場合は選択
        $('#shareUrl').select();
        showSuccess(`${formatText}を選択しました。Ctrl+Cでコピーしてください。`);
      }
    }
  });
  
  // コメント保存ボタンのクリックイベント
  $(document).on('click', '#saveCommentBtn', function() {
    var fileId = $('#editFileId').val();
    var comment = $('#editComment').val().trim();
    
    if (!fileId) {
      showError('ファイルIDが見つかりません。');
      return;
    }
    
    // 保存ボタンを無効化
    $('#saveCommentBtn').prop('disabled', true).text('保存中...');
    
    // CSRFトークンを使用した安全なAPI呼び出し
    const csrfToken = window.config?.csrf_token || '';
    
    if (!csrfToken) {
      showError('セキュリティトークンが取得できません。ページを再読み込みしてください。');
      return;
    }
    
    // 専用エンドポイントでCSRFトークンを送信
    const formData = new FormData();
    formData.append('file_id', fileId);
    formData.append('comment', comment);
    formData.append('csrf_token', csrfToken);
    
    fetch('./app/api/edit-comment.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      console.log('Comment update response:', data);
      if (data.success || data.status === 'success') {
        showSuccess('コメントを更新しました。');
        $('#editModal').modal('hide');
        
        // ローカルデータを即時更新
        if (Array.isArray(window.fileData)) {
          const fileObj = window.fileData.find(f => parseInt(f.id) === parseInt(fileId));
          if (fileObj) {
            fileObj.comment = comment;
          }
        }
        
        // FileManager内部データを即時更新
        if (window.fileManagerInstance && window.fileManagerInstance.core) {
          const target = window.fileManagerInstance.core.files?.find(f => parseInt(f.id) === parseInt(fileId));
          if (target) {
            target.comment = comment;
          }
          window.fileManagerInstance.core.applyFilters();
          window.fileManagerInstance.refresh();
        }
      } else {
        showError(data.message || 'コメントの更新に失敗しました。');
      }
    })
    .catch(error => {
      console.error('Comment update error:', error);
      showError('コメントの更新中にエラーが発生しました。');
    })
    .finally(() => {
      // 保存ボタンを有効化
      $('#saveCommentBtn').prop('disabled', false).text('コメント保存');
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
    var replaceKey = $('#modalReplaceKeyInput').val();
    
    // 差し替えキー必須チェック（常に強制）
    if (!replaceKey || replaceKey.trim() === '') {
      showError('差し替えキーを入力してください。');
      return;
    }
    
    // FormDataを作成
    var formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('replacekey', replaceKey);
    
    // CSRFトークンを取得
    var csrfToken = window.config?.csrf_token || '';
    
    // CSRFトークンをFormDataに追加
    if (csrfToken) {
      formData.append('csrf_token', csrfToken);
    }
    
    $.ajax({
      url: './app/api/replace-file.php?id=' + fileId,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json'
    })
    .done(function(data) {
      console.log('File replace response:', data);
      if (data.success || data.status === 'success') {
        showSuccess('ファイルを差し替えました。');
        $('#editModal').modal('hide');
        
        // ローカルデータ更新
        if (Array.isArray(window.fileData)) {
          const fileObj = window.fileData.find(f => parseInt(f.id) === parseInt(fileId));
          if (fileObj) {
            fileObj.origin_file_name = data.new_original_name || fileObj.origin_file_name;
            fileObj.size = data.size || fileObj.size;
          }
        }
        
        // FileManager内部データを更新
        if (window.fileManagerInstance && window.fileManagerInstance.core) {
          const target = window.fileManagerInstance.core.files?.find(f => parseInt(f.id) === parseInt(fileId));
          if (target) {
            target.origin_file_name = data.new_original_name || target.origin_file_name;
            target.size = data.size || target.size;
          }
          window.fileManagerInstance.core.applyFilters();
          window.fileManagerInstance.refresh();
        }
      } else {
        const errorMsg = data.message || (data.error ? data.error.message : '不明なエラー');
        showError('エラー: ' + errorMsg);
        alert('差し替えに失敗しました: ' + errorMsg);
      }
    })
    .fail(function(xhr) {
      console.error('File replace error:', xhr);
      var errorMessage = 'ファイルの差し替えに失敗しました。';
      try {
        var errorData = JSON.parse(xhr.responseText);
        if (errorData.message) {
          errorMessage = errorData.message;
        } else if (errorData.error && errorData.error.message) {
          errorMessage = errorData.error.message;
        }
      } catch (e) {
        // JSON解析に失敗した場合はデフォルトメッセージを使用
        console.error('Failed to parse error response:', e);
        if (xhr.responseText) {
          errorMessage += " (詳細: " + xhr.responseText.trim() + ")";
        }
      }
      showError('エラー: ' + errorMessage);
      alert('差し替えに失敗しました: ' + errorMessage);
    });
  });
});