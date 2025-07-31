<?php
/**
 * メインビューテンプレート - リファクタリング版
 * 分離された部分テンプレートを統合したビュー
 * 
 * 部分テンプレート構成:
 * - status-messages.php: ステータスメッセージ表示
 * - upload-form.php: アップロードフォーム
 * - error-display.php: エラー表示
 * - folder-navigation.php: フォルダナビゲーション
 * - file-manager.php: ファイルマネージャー
 * - modals.php: モーダルダイアログ
 * - page-scripts.php: JavaScript部分
 */
?>

<div class="container">
  <!-- ステータスメッセージ部分 -->
  <?php include __DIR__ . '/partials/status-messages.php'; ?>

  <!-- アップロードフォーム部分 -->
  <?php include __DIR__ . '/partials/upload-form.php'; ?>

  <!-- エラー表示部分 -->
  <?php include __DIR__ . '/partials/error-display.php'; ?>

  <!-- フォルダナビゲーション部分 -->
  <?php include __DIR__ . '/partials/folder-navigation.php'; ?>

  <!-- ファイルマネージャー部分 -->
  <?php include __DIR__ . '/partials/file-manager.php'; ?>

  <!-- フッター情報 -->
  <div class="row">
    <div class="col-sm-12">
      <p class="text-right">@<a href="https://github.com/shimosyan/phpUploader" target="_blank">
        shimosyan/phpUploader</a> v<?php echo $version ?? '2.0.0'; ?> (GitHub)</p>
    </div>
  </div>
</div>

<!-- モーダルダイアログ部分 -->
<?php include __DIR__ . '/partials/modals.php'; ?>

<!-- JavaScript部分 -->
<?php include __DIR__ . '/partials/page-scripts.php'; ?>