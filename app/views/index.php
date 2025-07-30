
<div class="container">
  <?php if (isset($status_message)): ?>
    <?php if ($status_message === 'success'): ?>
      <div id="statusMessage" class="alert alert-success" role="alert">
        <strong>削除完了！</strong> ファイルが正常に削除されました。
      </div>
    <?php elseif ($status_message === 'error'): ?>
      <div id="statusMessage" class="alert alert-danger" role="alert">
        <strong>エラー</strong> ファイルの削除に失敗しました。
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="row bg-white radius box-shadow">
    <div class="col-sm-12">
      <p class="h2">ファイルを登録</p>
      <form id="upload" class="upload-form">
        <input type="hidden" id="csrfToken" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

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
            📊 最大<?php echo $max_file_size; ?>MBまでアップロード可能<br>
            📎 対応拡張子: <?php echo implode(', ', $extension); ?>
          </p>
        </div>

        <div class="form-section">
          <div class="form-group">
            <label for="commentInput">💬 コメント</label>
            <input type="text" class="form-control" id="commentInput" name="comment" placeholder="ファイルの説明を入力...">
            <p class="help-block"><?php echo $max_comment; ?>文字まで入力可能</p>
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
      <p class="h2">📁 ファイル一覧</p>

      <!-- デスクトップ表示: テーブル -->
      <div class="file-table-container">
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
          <tbody>
          <?php
            foreach($data as $s){
              echo '<tr>';
              echo '<td>'.$s['id'].'</td>';
              echo '<td><a href="javascript:void(0);" onclick="dl_button('.$s['id'].');">'.$s['origin_file_name'].'</a></td>';
              echo '<td>'.$s['comment'].'</td>';
              echo '<td>'.round($s['size'] / (1024*1024), 1 ).'MB</td>';
              echo '<td>'.date("Y/m/d H:i:s", $s['input_date']).'</td>';
              echo '<td>'.$s['count'].'</td>';
              echo '<td><a href="javascript:void(0);" onclick="del_button('.$s['id'].');">[DEL]</a></td>';
              echo '</tr>';
            }
          ?>
          </tbody>
          <tfoot>
            <tr>
              <th>ID</th>
              <th>ファイル名</th>
              <th>コメント</th>
              <th>サイズ</th>
              <th>日付</th>
              <th>DL数</th>
              <th>削除</th>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- モバイル表示: カード -->
      <div class="file-cards-container">
        <?php if (empty($data)): ?>
          <div class="text-center" style="padding: 40px; color: #6c757d;">
            <h4>📄 アップロードされたファイルはありません</h4>
            <p>上のフォームからファイルをアップロードしてください。</p>
          </div>
        <?php else: ?>
          <?php foreach($data as $s): ?>
            <div class="file-card fade-in">
              <div class="file-card__header" onclick="toggleCardDetails(this)">
                <div class="file-card__main-info">
                  <a href="javascript:void(0);" class="file-card__filename" onclick="event.stopPropagation(); dl_button(<?php echo $s['id']; ?>);">
                    📄 <?php echo htmlspecialchars($s['origin_file_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                  <p class="file-card__comment"><?php echo htmlspecialchars($s['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <button class="file-card__toggle" type="button">▼</button>
              </div>

              <div class="file-card__details">
                <div class="file-card__detail-grid">
                  <div class="file-card__detail-item">
                    <span class="file-card__detail-label">ID</span>
                    <span class="file-card__detail-value">#<?php echo $s['id']; ?></span>
                  </div>
                  <div class="file-card__detail-item">
                    <span class="file-card__detail-label">サイズ</span>
                    <span class="file-card__detail-value file-card__detail-value--size"><?php echo round($s['size'] / (1024*1024), 1); ?>MB</span>
                  </div>
                  <div class="file-card__detail-item">
                    <span class="file-card__detail-label">アップロード日</span>
                    <span class="file-card__detail-value file-card__detail-value--date"><?php echo date("Y/m/d H:i", $s['input_date']); ?></span>
                  </div>
                  <div class="file-card__detail-item">
                    <span class="file-card__detail-label">ダウンロード数</span>
                    <span class="file-card__detail-value file-card__detail-value--count"><?php echo $s['count']; ?></span>
                  </div>
                </div>

                <div class="file-card__actions">
                  <a href="javascript:void(0);" class="file-card__action-btn" onclick="dl_button(<?php echo $s['id']; ?>);">
                    ⬇️ ダウンロード
                  </a>
                  <a href="javascript:void(0);" class="file-card__action-btn file-card__action-btn--delete" onclick="del_button(<?php echo $s['id']; ?>);">
                    🗑️ 削除
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <p class="text-right">@<a href="https://github.com/shimosyan/phpUploader" target="_blank">shimosyan/phpUploader</a> v<?php echo $version; ?> (GitHub)</p>
  </div>
</div>
