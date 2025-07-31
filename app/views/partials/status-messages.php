<?php
/**
 * ステータスメッセージ表示部分テンプレート
 * 成功・エラーメッセージの表示を担当
 */
?>

<?php if (isset($status_message)) : ?>
    <?php if ($status_message === 'success') : ?>
    <div id="statusMessage" class="alert alert-success" role="alert">
      <strong>成功</strong> ファイルのアップロードが完了しました
    </div>
    <?php elseif ($status_message === 'error') : ?>
    <div id="statusMessage" class="alert alert-danger" role="alert">
      <strong>エラー</strong> 処理に失敗しました
    </div>
  <?php endif; ?>
<?php endif; ?>