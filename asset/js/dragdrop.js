/**
 * ドラッグ&ドロップアップロード機能
 * phpUploader - Drag & Drop Upload Module
 */

// グローバル変数
var selectedFiles = [];
var isUploading = false;

// DOM読み込み完了後の初期化
$(document).ready(function() {
  initializeDragDrop();
});

function initializeDragDrop() {
  var $dragDropArea = $('#dragDropArea');
  var $selectFilesBtn = $('#selectFilesBtn');
  var $selectFolderBtn = $('#selectFolderBtn');
  var $multipleFileInput = $('#multipleFileInput');
  var $folderInput = $('#folderInput');
  var $clearFilesBtn = $('#clearFilesBtn');

  // ドラッグ&ドロップイベント
  $dragDropArea.on('dragover dragenter', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass('drag-over');
  });

  $dragDropArea.on('dragleave', function(e) {
    e.preventDefault();
    e.stopPropagation();
    // 子要素から出る場合は無視
    if (!$.contains(this, e.relatedTarget)) {
      $(this).removeClass('drag-over');
    }
  });

  $dragDropArea.on('drop', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('drag-over');
    
    if (isUploading) return;
    
    var files = e.originalEvent.dataTransfer.files;
    handleFiles(files);
  });

  // ファイル選択ボタン（複数ファイル選択）
  $selectFilesBtn.click(function() {
    if (isUploading) return;
    $multipleFileInput.click();
  });
  
  // ドラッグ&ドロップエリア全体のクリック（ファイル選択のフォールバック）
  $dragDropArea.click(function(e) {
    if (isUploading) return;
    // ボタンクリック以外の場合のみ
    if (!$(e.target).is('button')) {
      $multipleFileInput.click();
    }
  });

  $selectFolderBtn.click(function() {
    if (isUploading) return;
    $folderInput.click();
  });

  // ファイル入力変更イベント
  $multipleFileInput.change(function() {
    handleFiles(this.files);
  });

  $folderInput.change(function() {
    handleFiles(this.files);
  });
  
  // 従来の単一ファイル入力は削除（multipleFileInputで統一）
  // $('#lefile').change(function() {
  //   if (this.files.length > 0) {
  //     var fileName = this.files[0].name;
  //     $('#fileInput').val(fileName);
  //     
  //     // 複数ファイル選択をクリア
  //     if (selectedFiles.length > 0) {
  //       clearSelectedFiles();
  //     }
  //   }
  // });

  // クリアボタン
  $clearFilesBtn.click(function() {
    if (isUploading) return;
    clearSelectedFiles();
  });

  // 送信ボタンのクリックイベント（再開可能アップロード統合）
  $(document).on('click', '#uploadBtn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    console.log('Upload button clicked, selectedFiles:', selectedFiles.length);
    
    // 再開可能アップロード機能が利用可能かチェック
    if (typeof enhancedFileUpload === 'function') {
      console.log('Using enhanced file upload');
      enhancedFileUpload();
    } else {
      console.log('Using traditional upload');
      // フォールバック: 従来の方式
      if (selectedFiles.length > 0) {
        uploadMultipleFiles();
      } else {
        if ($('#multipleFileInput')[0].files.length > 0) {
          file_upload();
        } else {
          alert('ファイルが選択されていません。');
        }
      }
    }
    return false;
  });
}

function handleFiles(files) {
  if (!files || files.length === 0) return;
  
  // ファイルを配列に変換して追加
  for (var i = 0; i < files.length; i++) {
    var file = files[i];
    
    // 重複チェック
    var isDuplicate = selectedFiles.some(function(existingFile) {
      return existingFile.name === file.name && 
             existingFile.size === file.size && 
             existingFile.lastModified === file.lastModified;
    });
    
    if (!isDuplicate) {
      selectedFiles.push(file);
    }
  }
  
  updateFilesList();
  showSelectedFilesContainer();
}

function updateFilesList() {
  var $filesList = $('#selectedFilesList');
  $filesList.empty();
  
  selectedFiles.forEach(function(file, index) {
    var fileItem = $('<div class="file-item">');
    fileItem.attr('data-filename', file.name);
    
    var fileIcon = getFileIcon(file.name);
    var fileName = file.name;
    var fileSize = formatFileSize(file.size);
    
    fileItem.html(
      '<div class="upload-method-indicator" style="display: none;"></div>' +
      '<span class="file-icon">' + fileIcon + '</span>' +
      '<div class="file-info">' +
        '<span class="file-name">' + escapeHtml(fileName) + '</span>' +
        '<span class="file-size">' + fileSize + '</span>' +
      '</div>' +
      '<div class="upload-controls">' +
        '<button type="button" class="upload-control-btn pause" title="一時停止" style="display: none;">' +
          '<span class="glyphicon glyphicon-pause"></span>' +
        '</button>' +
        '<button type="button" class="upload-control-btn resume" title="再開" style="display: none;">' +
          '<span class="glyphicon glyphicon-play"></span>' +
        '</button>' +
        '<button type="button" class="upload-control-btn cancel" title="キャンセル" style="display: none;">' +
          '<span class="glyphicon glyphicon-stop"></span>' +
        '</button>' +
      '</div>' +
      '<button type="button" class="file-remove" data-index="' + index + '">' +
        '<span class="glyphicon glyphicon-remove"></span>' +
      '</button>' +
      '<div class="upload-progress" style="display: none;">' +
        '<div class="upload-progress-bar"></div>' +
      '</div>' +
      '<div class="upload-status" style="display: none;"></div>' +
      '<div class="detailed-progress" style="display: none;">' +
        '<div class="progress-text">' +
          '<span class="progress-percentage">0%</span>' +
          '<span class="progress-size">0B / ' + fileSize + '</span>' +
        '</div>' +
        '<div class="speed-info">速度: 計算中...</div>' +
      '</div>'
    );
    
    $filesList.append(fileItem);
  });
  
  // 削除ボタンイベント
  $('.file-remove').click(function() {
    if (isUploading) return;
    var index = parseInt($(this).data('index'));
    selectedFiles.splice(index, 1);
    updateFilesList();
    
    if (selectedFiles.length === 0) {
      hideSelectedFilesContainer();
    }
  });
  
  // アップロード制御ボタンイベント
  $('.upload-control-btn.pause').click(function() {
    var filename = $(this).closest('.file-item').data('filename');
    if (typeof pauseUpload === 'function') {
      pauseUpload(filename);
    }
  });
  
  $('.upload-control-btn.resume').click(function() {
    var filename = $(this).closest('.file-item').data('filename');
    if (typeof resumeUpload === 'function') {
      resumeUpload(filename);
    }
  });
  
  $('.upload-control-btn.cancel').click(function() {
    var filename = $(this).closest('.file-item').data('filename');
    if (typeof cancelUpload === 'function') {
      cancelUpload(filename);
    }
  });
}

function getFileIcon(filename) {
  var ext = filename.split('.').pop().toLowerCase();
  
  // アイコンマッピング
  var iconMap = {
    // 画像
    'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️', 'bmp': '🖼️', 'svg': '🖼️', 'webp': '🖼️',
    // 動画
    'mp4': '🎬', 'avi': '🎬', 'mov': '🎬', 'wmv': '🎬', 'flv': '🎬', 'mkv': '🎬', 'webm': '🎬',
    // 音声
    'mp3': '🎵', 'wav': '🎵', 'aac': '🎵', 'ogg': '🎵', 'flac': '🎵', 'm4a': '🎵', 'wma': '🎵',
    // ドキュメント
    'pdf': '📄', 'doc': '📝', 'docx': '📝', 'xls': '📊', 'xlsx': '📊', 'ppt': '📊', 'pptx': '📊',
    // アーカイブ
    'zip': '🗜️', 'rar': '🗜️', 'lzh': '🗜️', '7z': '🗜️', 'tar': '🗜️', 'gz': '🗜️',
    // 開発
    'html': '🌐', 'css': '🎨', 'js': '⚙️', 'json': '⚙️', 'xml': '⚙️', 'sql': '🗃️'
  };
  
  return iconMap[ext] || '📎';
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  
  var k = 1024;
  var sizes = ['Bytes', 'KB', 'MB', 'GB'];
  var i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function showSelectedFilesContainer() {
  $('#selectedFilesContainer').slideDown();
}

function hideSelectedFilesContainer() {
  $('#selectedFilesContainer').slideUp();
}

function clearSelectedFiles() {
  selectedFiles = [];
  updateFilesList();
  hideSelectedFilesContainer();
  
  // 隠しファイル入力をクリア
  $('#multipleFileInput').val('');
  $('#folderInput').val('');
}

function uploadMultipleFiles() {
  if (selectedFiles.length === 0) {
    alert('ファイルが選択されていません。');
    return;
  }
  
  if (isUploading) {
    alert('アップロード中です。しばらくお待ちください。');
    return;
  }
  
  isUploading = true;
  
  // エラーコンテナを非表示
  $('#errorContainer').fadeOut();
  $('#uploadContainer').fadeIn();
  
  // 各ファイルのプログレスバーを表示
  $('.file-item .upload-progress').show();
  
  // 順次アップロード
  uploadFilesSequentially(0);
}

function uploadFilesSequentially(index) {
  if (index >= selectedFiles.length) {
    // 全ファイルのアップロード完了
    isUploading = false;
    $('#uploadContainer').hide();
    location.reload();
    return;
  }
  
  var file = selectedFiles[index];
  var $fileItem = $('.file-item').eq(index);
  var $progressBar = $fileItem.find('.upload-progress-bar');
  
  // FormDataを作成
  var formData = new FormData();
  formData.append('file', file);
  formData.append('comment', $('#commentInput').val());
  formData.append('dlkey', $('#dleyInput').val());
  formData.append('delkey', $('#deleyInput').val());
  formData.append('replacekey', $('#replaceKeyInput').val());
  
  // 共有制限設定を追加
  var maxDownloads = $('#maxDownloadsUploadInput').val();
  var expiresDays = $('#expiresDaysUploadInput').val();
  
  if (maxDownloads && parseInt(maxDownloads) > 0) {
    formData.append('max_downloads', parseInt(maxDownloads));
  }
  if (expiresDays && parseInt(expiresDays) > 0) {
    formData.append('expires_days', parseInt(expiresDays));
  }
  
  $.ajax({
    url: './app/api/upload.php',
    type: 'POST',
    data: formData,
    cache: false,
    contentType: false,
    processData: false,
    dataType: 'json',
    xhr: function() {
      var xhr = $.ajaxSettings.xhr();
      if (xhr.upload) {
        xhr.upload.addEventListener('progress', function(e) {
          if (e.lengthComputable) {
            var progress = parseInt(e.loaded / e.total * 100);
            $progressBar.css({width: progress + '%'});
          }
        }, false);
      }
      return xhr;
    }
  })
  .done(function(data) {
    switch (data.status) {
      case 'ok':
        $progressBar.css({width: '100%'});
        $fileItem.css('background-color', '#d4edda');
        
        // 次のファイルをアップロード
        setTimeout(function() {
          uploadFilesSequentially(index + 1);
        }, 500);
        break;
        
      default:
        // エラー処理
        handleUploadError(data, file.name);
        isUploading = false;
        $('#uploadContainer').hide();
        break;
    }
  })
  .fail(function(jqXHR, textStatus, errorThrown) {
    handleUploadError({
      status: 'network_error',
      message: 'ネットワークエラー: ' + textStatus
    }, file.name);
    isUploading = false;
    $('#uploadContainer').hide();
  });
}

function handleUploadError(data, filename) {
  var errorMessage = '';
  
  switch (data.status) {
    case 'filesize_over':
      errorMessage = 'ファイル容量が大きすぎます: ' + filename;
      break;
    case 'extension_error':
      errorMessage = '許可されていない拡張子です: ' + filename + ' (拡張子: ' + data.ext + ')';
      break;
    case 'comment_error':
      errorMessage = 'コメントの文字数が規定数を超えています。';
      break;
    case 'dlkey_required':
      errorMessage = 'DLキーは必須入力です。';
      break;
    case 'delkey_required':
      errorMessage = 'DELキーは必須入力です。';
      break;
    case 'sqlwrite_error':
      errorMessage = 'データベースの書き込みに失敗しました: ' + filename;
      break;
    case 'network_error':
      errorMessage = data.message + ': ' + filename;
      break;
    default:
      errorMessage = 'アップロードに失敗しました: ' + filename;
      break;
  }
  
  $('#errorContainer > .panel-body').text(errorMessage);
  $('#errorContainer').fadeIn();
}

// 既存のescapeHtml関数がない場合の定義
if (typeof escapeHtml === 'undefined') {
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}