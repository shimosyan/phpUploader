
<div class="container">
  <?php if (isset($statusMessage)) : ?>
      <?php if ($statusMessage === 'success') : ?>
      <div id="statusMessage" class="alert alert-success" role="alert">
        <strong>削除完了！</strong> ファイルが正常に削除されました。
      </div>
      <?php elseif ($statusMessage === 'error') : ?>
      <div id="statusMessage" class="alert alert-danger" role="alert">
        <strong>エラー</strong> ファイルの削除に失敗しました。
      </div>
      <?php endif; ?>
  <?php endif; ?>

  <div class="row bg-white radius box-shadow">
    <div class="col-sm-12">
      <p class="h2">ファイルを登録</p>
      <form id="upload" class="upload-form">
        <input
          type="hidden"
          id="csrfToken"
          name="csrf_token"
          value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-section file-input-group">
          <input id="lefile" name="file" type="file" style="display:none">
          <div class="input-group">
            <input type="text" id="fileInput" class="form-control" name="file" placeholder="ファイルを選択..." readonly>
            <span class="input-group-btn">
              <button type="button" class="btn btn-primary" onclick="$('input[id=lefile]').click();">
                📁 ファイル選択
              </button>
            </span>
          </div>
          <p class="help-block">
            📊 最大<?php echo $maxFileSize; ?>MBまでアップロード可能<br>
            📎 対応拡張子: <?php echo implode(', ', $extension); ?>
          </p>
        </div>

        <div class="form-section">
          <div class="form-group">
            <label for="commentInput">💬 コメント</label>
            <input type="text" class="form-control" id="commentInput" name="comment" placeholder="ファイルの説明を入力...">
            <p class="help-block"><?php echo $maxComment; ?>文字まで入力可能</p>
          </div>
        </div>

        <div class="form-section">
          <div class="row">
            <div class="col-sm-6">
              <div class="form-group">
                <label for="dlkeyInput">🔐 ダウンロードキー</label>
                <input type="text" class="form-control" id="dleyInput" name="dlkey" placeholder="任意のパスワード...">
                <p class="help-block">空白で認証なし</p>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="form-group">
                <label for="delkeyInput">🗑️ 削除キー</label>
                <input type="text" class="form-control" id="deleyInput" name="delkey" placeholder="任意のパスワード...">
                <p class="help-block">空白で認証なし</p>
              </div>
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="row">
            <div class="col-sm-offset-10 col-sm-2">
              <button type="button" class="btn btn-success btn-block btn-submit" onclick="file_upload()">
                🚀 アップロード
              </button>
            </div>
          </div>
        </div>
      </form>

      <div id="uploadContainer" class="upload-progress" style="display: none;">
        <div class="panel-heading">
          <h4>⏳ アップロード中...</h4>
        </div>
        <div class="panel-body">
          <div class="progress">
            <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;">
              <span id="progressText">0%</span>
            </div>
          </div>
        </div>
      </div>

      <div id="errorContainer" class="error-container" style="display: none;">
        <div class="panel-heading">
          <h4>⚠️ エラー</h4>
        </div>
        <div class="panel-body">
        </div>
      </div>

    </div>
  </div>

  <div class="row bg-white radius box-shadow">
    <div class="col-sm-12">
      <!-- 新しいファイル管理システム (DataTables完全廃止版) -->
      <div id="fileManagerContainer"></div>

      <!-- レガシーテーブル表示（非表示） -->
      <div class="file-table-container" style="display: none;">
        <table id="fileList" class="table table-striped" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th>ID</th>
              <th>ファイル名</th>
              <th>コメント</th>
              <th>サイズ</th>
              <th>日付</th>
              <th>DL数</th>
              <th>削除</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <!-- レガシーカード表示（非表示） -->
      <div class="file-cards-container" style="display: none;"></div>
    </div>
    <p class="text-right">@<a
      href="https://github.com/shimosyan/phpUploader"
      target="_blank">shimosyan/phpUploader</a> v<?php echo $version; ?> (GitHub)</p>
  </div>
</div>

<!-- ファイルデータをJavaScriptに渡す -->
<script>
  // PHPからJavaScriptにファイルデータを渡す
  window.fileData = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE); ?>;
</script>
