
<div class="container">
  <?php if (isset($status_message)) : ?>
      <?php if ($status_message === 'success') : ?>
      <div id="statusMessage" class="alert alert-success" role="alert">
        <strong>?????</strong> ????????????????
      </div>
      <?php elseif ($status_message === 'error') : ?>
      <div id="statusMessage" class="alert alert-danger" role="alert">
        <strong>???</strong> ???????????????
      </div>
    <?php endif; ?>
  <?php endif; ?>

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
              <p>?????????????</p>
            </div>
          </div>
        </div>
        
        <!-- ?????????? -->
        <input id="multipleFileInput" type="file" multiple style="display:none">
        <input id="folderInput" type="file" webkitdirectory multiple style="display:none">
        
        <!-- ??????????? -->
        <div id="selectedFilesContainer" class="selected-files-container" style="display: none;">
          <h5>?????????:</h5>
          <div id="selectedFilesList" class="selected-files-list"></div>
          <button type="button" class="btn btn-sm btn-default" id="clearFilesBtn">???</button>
        </div>

        <!-- ファイル入力エリア（レガシーサポート） -->
        <div class="form-section file-input-group">
          <input id="lefile" name="file" type="file" style="display:none">
          <div class="input-group">
            <input type="text" id="fileInput" class="form-control" name="file" placeholder="ファイルを選択..." readonly>
            <span class="input-group-btn">
              <button type="button" class="btn btn-primary" onclick="$('input[id=lefile]').click();">
                ファイル 選択
              </button>
            </span>
          </div>
          <p class="help-block">
            最大 サイズ: <?php echo $max_file_size ?? 2; ?>MBまでアップロード可能<br>
            対応 拡張子: <?php echo implode(', ', $extension ?? ['zip', 'pdf', 'jpg', 'png']); ?>
          </p>
        </div>

        <div class="form-section">
          <div class="form-group">
            <label for="commentInput">コメント （任意）</label>
            <input type="text" class="form-control" id="commentInput" name="comment" placeholder="コメントを入力...">
            <p class="help-block"><?php echo $max_comment ?? 80; ?>文字以内で入力</p>
          </div>
        </div>

        <div class="form-section">
          <div class="row">
            <div class="col-sm-6">
              <div class="form-group">
                <label for="dlkeyInput">
                  DLキー
                  <?php if (isset($dlkey_required) && $dlkey_required) : ?>
                    <span class="text-danger">*必須</span>
                  <?php else : ?>
                    <small class="text-muted">(任意・自動生成)</small>
                  <?php endif; ?>
                </label>
                <input type="text" class="form-control" id="dleyInput" name="dlkey"
                       placeholder="<?php echo (isset($dlkey_required) && $dlkey_required) ?
                                      'DLキーを入力してください' :
                                      'DLキーを入力... (空白時は自動生成)'; ?>"
                       <?php echo (isset($dlkey_required) && $dlkey_required) ? 'required' : ''; ?>>
                <p class="help-block">ファイルダウンロード時に必要なキーです</p>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="form-group">
                <label for="delkeyInput">
                  DELキー
                  <?php if (isset($delkey_required) && $delkey_required) : ?>
                    <span class="text-danger">*必須</span>
                  <?php else : ?>
                    <small class="text-muted">(任意・自動生成)</small>
                  <?php endif; ?>
                </label>
                <input type="text" class="form-control" id="deleyInput" name="delkey"
                       placeholder="<?php echo (isset($delkey_required) && $delkey_required) ?
                                      'DELキーを入力してください' :
                                      'DELキーを入力... (空白時は自動生成)'; ?>"
                       <?php echo (isset($delkey_required) && $delkey_required) ? 'required' : ''; ?>>
                <p class="help-block">ファイル削除時に必要なキーです</p>
              </div>
            </div>
          </div>
        </div>

        <!-- 共有制限設定 -->
        <div class="panel panel-default">
          <div class="panel-heading">
            <h4 class="panel-title">
              <a data-toggle="collapse" href="#shareLimitsPanel" aria-expanded="false" aria-controls="shareLimitsPanel">
                共有制限設定 <small class="text-muted">(オプション)</small>
                <span class="glyphicon glyphicon-chevron-down pull-right"></span>
              </a>
            </h4>
          </div>
          <div id="shareLimitsPanel" class="panel-collapse collapse">
            <div class="panel-body">
              <div class="row">
                <div class="col-sm-6">
                  <div class="form-group">
                    <label for="maxDownloadsInput">最大ダウンロード数</label>
                    <input type="number" class="form-control" id="maxDownloadsUploadInput" name="max_downloads" placeholder="無制限" min="1">
                    <p class="help-block">空白で無制限</p>
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="form-group">
                    <label for="expiresInput">有効期限（日数）</label>
                    <input type="number" class="form-control" id="expiresDaysUploadInput" name="expires_days" placeholder="無期限" min="1">
                    <p class="help-block">空白で無期限</p>
                  </div>
                </div>
              </div>
              <p class="text-info">
                <span class="glyphicon glyphicon-info-sign"></span>
                ここで設定した制限は、このファイルの共有リンクに適用されます。後から変更も可能です。
              </p>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-sm-offset-10 col-sm-2">
            <button type="button" class="btn btn-success btn-block" onclick="file_upload()">送信</button>
          </div>
        </div>
      </form>

      <div id="uploadContainer" class="upload-progress" style="display: none;">
        <div class="panel-heading">
          <h4>? ???????...</h4>
        </div>
        <div class="panel-body">
          <div class="progress">
            <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;">
              <span id="progressText">0%</span>
            </div>
          </div>
        </div>
      </div>

      <div id="errorContainer" class="panel panel-danger" style="<?php echo !empty($error_message) ? 'display: block;' : 'display: none;'; ?>">
        <div class="panel-heading">
          <h4>?? ???</h4>
        </div>
        <div class="panel-body">
          <?php echo !empty($error_message) ? htmlspecialchars($error_message) : ''; ?>
        </div>
      </div>

    </div>
  </div>

  <div class="row bg-white radius box-shadow">
    <div class="col-sm-12">
      <!-- ????????????? (DataTables?????) -->
      <div id="fileManagerContainer"></div>
      
      <!-- ??????????????? -->
      <div class="file-table-container" style="display: none;">
        <table id="fileList" class="table table-striped" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th>ID</th>
              <th>?????</th>
              <th>????</th>
              <th>???</th>
              <th>??</th>
              <th>DL?</th>
              <th>??</th>
              <th>??</th>
            </tr>
          </thead>
          <tbody>
                <?php
        foreach ($data as $s) {
            echo '<tr>';
            echo '<td>' . $s['id'] . '</td>';
            echo '<td><a href="javascript:void(0);" onclick="dl_button(' . $s['id'] . ');">' 
                . $s['origin_file_name'] . '</a></td>';
            echo '<td>' . $s['comment'] . '</td>';
            echo '<td>' . round($s['size'] / (1024 * 1024), 1) . 'MB</td>';
            echo '<td>' . date("Y/m/d H:i:s", $s['input_date']) . '</td>';
            echo '<td>' . $s['count'] . '</td>';
            echo '<td><a href="javascript:void(0);" onclick="share_button(' . $s['id'] . ');">[??]</a></td>';
            echo '<td><a href="javascript:void(0);" onclick="del_button(' . $s['id'] . ');">[DEL]</a></td>';
            echo '</tr>';
        }
        ?>
          </tbody>
          <tfoot>
            <tr>
              <th>ID</th>
              <th>?????</th>
              <th>????</th>
              <th>???</th>
              <th>??</th>
              <th>DL?</th>
              <th>??</th>
              <th>??</th>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- ???????????? -->
      <div id="fileListCards">
                <?php
        foreach ($data as $s) {
            echo '<div class="file-card">';
            echo '  <div class="file-card__main" onclick="toggleCardDetails(this)">';
            echo '    <div class="file-card__content">';
            echo '      <a href="javascript:void(0);" class="file-card__filename" ';
            echo 'onclick="event.stopPropagation(); dl_button(' . $s['id'] . ');">';
            echo $s['origin_file_name'] . '</a>';
            echo '      <p class="file-card__comment">' . $s['comment'] . '</p>';
            echo '    </div>';
            echo '    <button class="file-card__toggle" type="button">?</button>';
            echo '  </div>';
            echo '  <div class="file-card__details">';
            echo '    <div class="file-card__detail-row">';
            echo '      <span class="file-card__detail-label">ID:</span>';
            echo '      <span class="file-card__detail-value">' . $s['id'] . '</span>';
            echo '    </div>';
            echo '    <div class="file-card__detail-row">';
            echo '      <span class="file-card__detail-label">???:</span>';
            echo '      <span class="file-card__detail-value file-card__detail-value--size">';
            echo round($s['size'] / (1024 * 1024), 1) . 'MB</span>';
            echo '    </div>';
            echo '    <div class="file-card__detail-row">';
            echo '      <span class="file-card__detail-label">??:</span>';
            echo '      <span class="file-card__detail-value file-card__detail-value--date">';
            echo date("Y/m/d H:i:s", $s['input_date']) . '</span>';
            echo '    </div>';
            echo '    <div class="file-card__detail-row">';
            echo '      <span class="file-card__detail-label">DL?:</span>';
            echo '      <span class="file-card__detail-value file-card__detail-value--count">' . $s['count'] . '</span>';
            echo '    </div>';
            echo '    <div class="file-card__actions">';
            echo '      <a href="javascript:void(0);" class="file-card__action-btn" ';
            echo 'onclick="dl_button(' . $s['id'] . ');">??????</a>';
            echo '      <a href="javascript:void(0);" class="file-card__action-btn file-card__action-btn--share" ';
            echo 'onclick="share_button(' . $s['id'] . ');">??</a>';
            echo '      <a href="javascript:void(0);" class="file-card__action-btn file-card__action-btn--delete" ';
            echo 'onclick="del_button(' . $s['id'] . ');">??</a>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        }
        ?>
      </div>

      <!-- ?????????????? -->
      <div class="file-cards-container" style="display: none;"></div>
    </div>
    <p class="text-right">@<a href="https://github.com/shimosyan/phpUploader" target="_blank">
      shimosyan/phpUploader</a> v<?php echo $version; ?> (GitHub)</p>
  </div>
</div>

<!-- ????????JavaScript??? -->
<script>
  // PHP??JavaScript???????????
  window.fileData = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE); ?>;
</script>
