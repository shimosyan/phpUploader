<?php
/**
 * アップロードフォーム部分テンプレート
 * ドラッグ&ドロップ、ファイル選択、設定項目を担当
 */
?>

<div class="row bg-white radius box-shadow">
  <div class="col-sm-12">
    <div class="page-header">
      <h1><?php echo $title; ?> <small>ファイルアップロード</small></h1>
    </div>
    <form id="upload" class="upload-form">
      <input type="hidden" id="csrfToken" name="csrf_token" 
             value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
      
      <!-- ドラッグ&ドロップエリア -->
      <div id="dragDropArea" class="drag-drop-area">
        <div class="drag-drop-content">
          <div class="drag-drop-icon">
            <span class="glyphicon glyphicon-cloud-upload"></span>
          </div>
          <h4>ファイルをドラッグ&ドロップ</h4>
          <p class="text-muted">複数ファイルやフォルダにも対応しています</p>
          <p>または</p>
          <button type="button" class="btn btn-primary" id="selectFilesBtn">ファイルを選択</button>
          <button type="button" class="btn btn-info" id="selectFolderBtn">フォルダを選択</button>
        </div>
        <div class="drag-drop-overlay">
          <div class="drag-drop-overlay-content">
            <span class="glyphicon glyphicon-download-alt"></span>
            <p>ここにファイルをドロップ</p>
          </div>
        </div>
      </div>
      
      <!-- ファイル入力要素 -->
      <input id="multipleFileInput" type="file" multiple style="display:none">
      <input id="folderInput" type="file" webkitdirectory multiple style="display:none">
      
      <!-- 選択ファイル表示 -->
      <div id="selectedFilesContainer" class="selected-files-container" style="display: none;">
        <h5>選択されたファイル:</h5>
        <div id="selectedFilesList" class="selected-files-list"></div>
        <button type="button" class="btn btn-sm btn-default" id="clearFilesBtn">クリア</button>
      </div>

      <!-- アップロード制限情報 -->
      <div class="form-section">
        <p class="help-block">
          <strong>最大サイズ:</strong> <?php echo $max_file_size ?? 100; ?>MBまでアップロード可能<br>
          <strong>対応拡張子:</strong> <?php echo implode(', ', $extension ?? ['zip', 'pdf', 'jpg', 'png']); ?>
        </p>
      </div>

      <div class="form-section">
        <div class="form-group">
          <label for="commentInput">コメント （任意）</label>
          <input type="text" class="form-control" id="commentInput" name="comment" placeholder="コメントを入力...">
          <p class="help-block"><?php echo $max_comment ?? 80; ?>文字以内で入力</p>
        </div>
      </div>

      <?php if(isset($folders_enabled) && $folders_enabled): ?>
      <!-- フォルダ選択 -->
      <div class="form-group">
        <label for="folder-select">保存先フォルダ</label>
        <select class="form-control" id="folder-select" name="folder_id">
          <option value="">ルートフォルダ</option>
          <!-- フォルダ一覧はJavaScriptで動的に生成 -->
        </select>
        <p class="help-block">ファイルを保存するフォルダを選択してください</p>
      </div>
      <?php endif; ?>

      <div class="form-section">
        <div class="row">
          <div class="col-sm-6">
            <div class="form-group">
              <label for="dlkeyInput">
                DLキー
                <?php if (isset($dlkey_required) && $dlkey_required) : ?>
                  <span class="text-danger">*必須</span>
                <?php else : ?>
                  <small class="text-muted">(任意)</small>
                <?php endif; ?>
              </label>
              <input type="text" class="form-control" id="dleyInput" name="dlkey"
                     placeholder="<?php echo (isset($dlkey_required) && $dlkey_required) ?
                                    'DLキーを入力してください' :
                                    'DLキーを入力... (空白時は認証不要)'; ?>"
                     <?php echo (isset($dlkey_required) && $dlkey_required) ? 'required' : ''; ?>>
              <p class="help-block">ファイルダウンロード時に必要なキーです</p>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="form-group">
              <label for="delkeyInput">削除キー <small class="text-muted">(任意)</small></label>
              <input type="text" class="form-control" id="delkeyInput" name="delkey" placeholder="削除キーを入力... (空白時は削除不可)">
              <p class="help-block">ファイル削除時に必要なキーです</p>
            </div>
          </div>
        </div>
      </div>

      <?php if(isset($allow_file_replace) && $allow_file_replace): ?>
      <!-- 差し替えキー -->
      <div class="form-section">
        <div class="form-group">
          <label for="replacekeyInput">差し替えキー <span class="text-danger">*必須</span></label>
          <input type="text" class="form-control" id="replaceKeyInput" name="replacekey" placeholder="差し替えキーを入力してください" required>
          <p class="help-block">ファイル差し替え時に必要なキーです（必須項目）</p>
        </div>
      </div>
      <?php endif; ?>

      <!-- プログレスバーエリア -->
      <div id="progressContainer" style="display: none;" class="form-section">
        <h5>アップロード進行状況</h5>
        <div class="progress">
          <div id="progressBar" class="progress-bar progress-bar-info progress-bar-striped active" 
               role="progressbar" style="width: 0%">
            <span id="progressText">0%</span>
          </div>
        </div>
        <div id="uploadStatus" class="text-muted"></div>
      </div>

      <div class="form-section text-right">
        <input type="submit" class="btn btn-success btn-lg btn-upload" value="📁 ファイルをアップロード" id="uploadBtn">
        <button type="button" class="btn btn-default btn-lg" id="cancelBtn" style="display: none;">キャンセル</button>
      </div>
    </form>
  </div>
</div>