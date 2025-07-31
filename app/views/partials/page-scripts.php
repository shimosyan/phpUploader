<?php
/**
 * ページ専用JavaScript部分テンプレート
 * インラインJavaScriptとデータの受け渡しを担当
 */
?>

<!-- ファイルデータをJavaScriptに渡す -->
<script>
  // PHPからJavaScriptにファイルデータを渡す
  window.fileData = <?php echo json_encode(isset($data) ? $data : [], JSON_UNESCAPED_UNICODE); ?>;
  
      // 設定情報をJavaScriptに渡す
    window.config = {
        allow_comment_edit: <?php echo json_encode(isset($allow_comment_edit) ? $allow_comment_edit : false); ?>,
        allow_file_replace: <?php echo json_encode(isset($allow_file_replace) ? $allow_file_replace : false); ?>,
        folders_enabled: <?php echo json_encode(isset($folders_enabled) ? $folders_enabled : false); ?>,
        upload_method_priority: <?php echo json_encode(isset($upload_method_priority) ? $upload_method_priority : 'resumable'); ?>,
        upload_fallback_enabled: <?php echo json_encode(isset($upload_fallback_enabled) ? $upload_fallback_enabled : true); ?>,
        csrf_token: <?php echo json_encode(isset($csrf_token) ? $csrf_token : ''); ?>
    };
  
  // フォルダデータをJavaScriptに渡す  
  window.folderData = <?php echo json_encode(isset($folders) ? $folders : [], JSON_UNESCAPED_UNICODE); ?>;
  
  // URLパラメータからエラーメッセージを取得して表示
  function checkForErrors() {
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    
    if (error) {
      let errorMessage = '';
      let errorTitle = 'エラー';
      
      switch (error) {
        case 'expired':
          errorTitle = '共有リンクエラー';
          errorMessage = 'この共有リンクは有効期限が切れています。';
          break;
        case 'limit_exceeded':
          errorTitle = 'ダウンロード制限エラー';
          errorMessage = 'このファイルは最大ダウンロード数に達しているため、ダウンロードできません。';
          break;
        default:
          errorMessage = '不明なエラーが発生しました。';
          break;
      }
      
      // jQueryが利用可能になるまで待つ
      if (typeof $ !== 'undefined' && typeof showError === 'function') {
        // エラーメッセージを表示
        showError(errorTitle + ': ' + errorMessage);
        
        // URLからエラーパラメータを削除（ブラウザの履歴を汚さないため）
        if (window.history && window.history.replaceState) {
          const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]error=[^&]*/, '').replace(/^&/, '?');
          window.history.replaceState({}, document.title, cleanUrl);
        }
      } else {
        // jQueryがまだ読み込まれていない場合は少し待つ
        setTimeout(checkForErrors, 100);
      }
    }
  }
  
  // DOMが読み込まれたらエラーチェックを開始
  document.addEventListener('DOMContentLoaded', checkForErrors);
</script>