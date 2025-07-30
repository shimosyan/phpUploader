/**
 * ファイル共有機能
 * phpUploader - File Sharing Module
 */

// 共有ボタンを押すと実行
function share_button(id){
  generate_share_link(id);
}

function generate_share_link(id){
  var postdata = {
    id: id
  }

  $.ajax({
    url  : './app/api/generatesharelink.php',
    type : 'POST',
    data : postdata,
    dataType    : 'json'
  })
  .done(function(data, textStatus, jqXHR){
    switch (data.status){
      case 'ok':
        show_share_modal(data);
        break;
      case 'not_found':
        alert('ファイルが見つかりません。');
        break;
      case 'sqlerror':
        alert('データベースエラーが発生しました。');
        break;
      default:
        alert('共有リンクの生成に失敗しました。');
        break;
    }
  })
  .fail(function(jqXHR, textStatus, errorThrown){
    alert('共有リンクの生成中にエラーが発生しました: ' + textStatus);
  })
  .always(function( jqXHR, textStatus ) {
  });
}

function show_share_modal(data){
  var html = '<div class="share-modal-content">';
  html += '<p><strong>ファイル名:</strong> ' + escapeHtml(data.filename) + '</p>';
  if(data.comment) {
    html += '<p><strong>コメント:</strong> ' + escapeHtml(data.comment) + '</p>';
  }
  
  // 共有制限設定
  html += '<div class="form-group">';
  html += '<label>共有制限設定 <small class="text-muted">(現在の設定)</small></label>';
  html += '<div class="row">';
  html += '<div class="col-sm-6">';
  html += '<label for="maxDownloadsInput">最大ダウンロード数</label>';
  html += '<input type="number" class="form-control" id="maxDownloadsInput" placeholder="無制限" min="1" onchange="regenerateShareLink(' + data.id + ')" value="' + (data.current_max_downloads || '') + '">';
  html += '<small class="help-block">現在: ' + (data.current_max_downloads ? data.current_max_downloads + '回' : '無制限') + '</small>';
  html += '</div>';
  html += '<div class="col-sm-6">';
  html += '<label for="expiresInput">有効期限（日数）</label>';
  var expiresValue = '';
  var expiresStatus = '無期限';
  if (data.current_expires_days !== null) {
    if (data.current_expires_days > 0) {
      expiresValue = data.current_expires_days;
      expiresStatus = '残り' + data.current_expires_days + '日';
    } else {
      expiresStatus = '<span class="text-danger">期限切れ</span>';
    }
  }
  html += '<input type="number" class="form-control" id="expiresInput" placeholder="無期限" min="1" onchange="regenerateShareLink(' + data.id + ')" value="' + expiresValue + '">';
  html += '<small class="help-block">現在: ' + expiresStatus + '</small>';
  html += '</div>';
  html += '</div>';
  html += '</div>';
  
  html += '<div class="form-group">';
  html += '<label for="shareUrlInput">共有URL</label>';
  html += '<div class="input-group">';
  html += '<input type="text" class="form-control" id="shareUrlInput" value="' + escapeHtml(data.share_url) + '" readonly>';
  html += '<span class="input-group-btn">';
  html += '<button type="button" class="btn btn-default" onclick="copyToClipboard(\'shareUrlInput\')">コピー</button>';
  html += '</span>';
  html += '</div>';
  html += '</div>';
  html += '<div class="form-group">';
  html += '<label for="shareUrlWithCommentInput">コメント付き共有</label>';
  html += '<div class="input-group">';
  html += '<textarea class="form-control" id="shareUrlWithCommentInput" rows="3" readonly>' + escapeHtml(data.share_url_with_comment) + '</textarea>';
  html += '<span class="input-group-btn">';
  html += '<button type="button" class="btn btn-default" onclick="copyToClipboard(\'shareUrlWithCommentInput\')">コピー</button>';
  html += '</span>';
  html += '</div>';
  html += '</div>';
  html += '</div>';
  
  openModal('ok', 'ファイル共有', html, 'closeModal()');
}

function regenerateShareLink(id) {
  var maxDownloads = $('#maxDownloadsInput').val();
  var expires = $('#expiresInput').val();
  
  var postdata = {
    id: id
  };
  
  // 空文字列でない場合のみパラメータを追加
  if (maxDownloads && parseInt(maxDownloads) > 0) {
    postdata.max_downloads = parseInt(maxDownloads);
  }
  if (expires && parseInt(expires) > 0) {
    postdata.expires_days = parseInt(expires);
  }

  $.ajax({
    url  : './app/api/generatesharelink.php',
    type : 'POST',
    data : postdata,
    dataType    : 'json'
  })
  .done(function(data, textStatus, jqXHR){
    if(data.status === 'ok') {
      $('#shareUrlInput').val(data.share_url);
      $('#shareUrlWithCommentInput').val(data.share_url_with_comment);
    }
  })
  .fail(function(jqXHR, textStatus, errorThrown){
    console.error('共有リンク再生成エラー:', textStatus);
  });
}

function copyToClipboard(elementId) {
  var element = document.getElementById(elementId);
  element.select();
  element.setSelectionRange(0, 99999); // モバイル対応
  
  try {
    var successful = document.execCommand('copy');
    if (successful) {
      // 成功時の視覚的フィードバック
      var button = event.target;
      var originalText = button.textContent;
      button.textContent = 'コピー済み!';
      button.classList.add('btn-success');
      setTimeout(function() {
        button.textContent = originalText;
        button.classList.remove('btn-success');
      }, 2000);
    } else {
      alert('コピーに失敗しました。手動でコピーしてください。');
    }
  } catch (err) {
    // フォールバック: Clipboard APIを試す
    if (navigator.clipboard) {
      navigator.clipboard.writeText(element.value).then(function() {
        var button = event.target;
        var originalText = button.textContent;
        button.textContent = 'コピー済み!';
        button.classList.add('btn-success');
        setTimeout(function() {
          button.textContent = originalText;
          button.classList.remove('btn-success');
        }, 2000);
      }).catch(function() {
        alert('コピーに失敗しました。手動でコピーしてください。');
      });
    } else {
      alert('コピーに失敗しました。手動でコピーしてください。');
    }
  }
}

function escapeHtml(text) {
  var div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}