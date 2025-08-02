<div class="container">
  <?php if (isset($status_message)): ?>
    <?php if ($status_message === 'success'): ?>
      <div id="statusMessage" class="alert alert-success" role="alert">
        <strong>成功</strong> ファイルのアップロードが完了しました。
      </div>
    <?php elseif ($status_message === 'error'): ?>
      <div id="statusMessage" class="alert alert-danger" role="alert">
        <strong>エラー</strong> ファイルのアップロードに失敗しました。
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- ドラッグ&ドロップアップロードフォーム -->
  <?php include __DIR__ . '/partials/upload-form.php'; ?>

  <!-- エラー表示 -->
  <?php if (isset($validation_errors) && !empty($validation_errors)): ?>
    <div class="alert alert-danger">
      <h4>⚠️ エラー</h4>
      <p>バリデーションエラー <strong>詳細:</strong>
      <?php foreach ($validation_errors as $error): ?>
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
      <?php endforeach; ?>
      </p>
    </div>
  <?php endif; ?>

  <!-- フッター情報 -->
  <div class="row">
    <div class="col-sm-12">
      <p class="text-right">@<a href="https://github.com/shimosyan/phpUploader" target="_blank">
        shimosyan/phpUploader</a> v<?php echo $version ?? '2.0.0'; ?> (GitHub)</p>
    </div>
  </div>
</div>