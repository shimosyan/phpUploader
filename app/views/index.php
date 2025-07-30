
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

        <!-- 差し替えキー設定 -->
        <div class="row">
          <div class="col-sm-12">
            <div class="form-group" style="background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 5px; padding: 15px;">
              <label for="replaceKeyInput">
                <i class="glyphicon glyphicon-lock" style="color: #dc3545;"></i>
                差し替えキー
                <span class="text-danger" style="font-weight: bold; font-size: 1.1em;">*必須</span>
              </label>
              <input type="text" class="form-control" id="replaceKeyInput" name="replacekey" placeholder="差し替えキーを入力してください" required style="border: 2px solid #ffc107;">
              <div class="alert alert-warning" style="margin-top: 10px; margin-bottom: 0;">
                <i class="glyphicon glyphicon-warning-sign"></i>
                <strong>重要:</strong> 差し替えキーは必須入力です。ファイル差し替え時にこのキーが必要になります。<strong style="color: #dc3545;">忘れると差し替えできません！</strong>
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
      <!-- ファイルマネージャー (DataTablesサポート) -->
      <div id="fileManagerContainer"></div>
      
      <?php if(isset($folders_enabled) && $folders_enabled): ?>
      <!-- フォルダナビゲーション -->
      <div class="folder-navigation" style="margin-bottom: 20px;">
        <div class="folder-breadcrumb">
          <h3 style="display: inline-block; margin-right: 15px;">📁 現在の場所:</h3>
          <ol class="breadcrumb" style="display: inline-block; margin: 0; background: transparent; padding: 0;">
            <li><a href="?folder=" class="breadcrumb-link">🏠 ルート</a></li>
            <?php if(isset($current_folder) && $current_folder): ?>
              <li class="active"><?php echo htmlspecialchars($current_folder['name']); ?></li>
            <?php endif; ?>
          </ol>
        </div>
        
        <!-- フォルダ一覧表示と管理 -->
        <div class="folder-list" style="margin: 15px 0; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h4 style="margin: 0;">📂 フォルダ一覧</h4>
            <div class="folder-actions">
              <button type="button" class="btn btn-success btn-sm" id="create-folder-btn" title="新しいフォルダを作成">
                <span class="glyphicon glyphicon-plus"></span> フォルダ作成
              </button>
            </div>
          </div>
          
          <?php if (!empty($folders)): ?>
          <div class="row" id="folder-grid">
            <?php foreach($folders as $folder): ?>
              <?php if((isset($current_folder_id) && $folder['parent_id'] == $current_folder_id) || (!isset($current_folder_id) && !$folder['parent_id'])): ?>
              <div class="col-sm-3 col-xs-6" style="margin-bottom: 10px;" data-folder-id="<?php echo $folder['id']; ?>">
                <div class="folder-item-wrapper" style="position: relative;">
                  <a href="?folder=<?php echo $folder['id']; ?>" class="folder-item" style="display: block; padding: 10px; text-decoration: none; border: 1px solid #ddd; border-radius: 5px; background: white; color: #333;">
                    <span style="font-size: 1.2em;">📁</span>
                    <span style="margin-left: 5px;"><?php echo htmlspecialchars($folder['name']); ?></span>
                  </a>
                  <?php if($folder['id'] != 1): // ルートフォルダ以外に管理メニューを表示 ?>
                  <div class="folder-menu" style="position: absolute; top: 5px; right: 5px; opacity: 0; transition: opacity 0.2s;">
                    <div class="dropdown">
                      <button class="btn btn-xs btn-default dropdown-toggle" type="button" data-toggle="dropdown" style="padding: 2px 6px; border-radius: 50%; width: 24px; height: 24px;">
                        <span class="glyphicon glyphicon-option-vertical" style="font-size: 10px;"></span>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-right" style="min-width: 120px;">
                        <li><a href="#" class="rename-folder" data-folder-id="<?php echo $folder['id']; ?>"><span class="glyphicon glyphicon-edit"></span> 名前変更</a></li>
                        <li><a href="#" class="move-folder" data-folder-id="<?php echo $folder['id']; ?>"><span class="glyphicon glyphicon-move"></span> 移動</a></li>
                        <li class="divider"></li>
                        <li><a href="#" class="delete-folder" data-folder-id="<?php echo $folder['id']; ?>" style="color: #d9534f;"><span class="glyphicon glyphicon-trash"></span> 削除</a></li>
                      </ul>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="text-center text-muted" style="padding: 20px;">
            <span class="glyphicon glyphicon-folder-open" style="font-size: 2em; margin-bottom: 10px; display: block;"></span>
            フォルダがありません
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <p class="h2">
        ファイル一覧
        <?php if(isset($current_folder) && $current_folder): ?>
          <small>- <?php echo htmlspecialchars($current_folder['name']); ?></small>
        <?php else: ?>
          <small>- ルートフォルダ</small>
        <?php endif; ?>
      </p>

      <!-- ファイルリストテーブル -->
      <div class="file-table-container" style="display: none;">
        <table id="fileList" class="table table-striped" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th>ID</th>
              <th>ファイル名</th>
              <?php if(isset($folders_enabled) && $folders_enabled && !isset($current_folder_id)): ?>
              <th>フォルダ</th>
              <?php endif; ?>
              <th>コメント</th>
              <th>サイズ</th>
              <th>日付</th>
              <th>DL数</th>
              <th>共有</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
        <?php 
          if (isset($data) && is_array($data)) {
            foreach($data as $s){
              echo '<tr>';
              echo '<td>'.$s['id'].'</td>';
              echo '<td><a href="javascript:void(0);" onclick="dl_button('.$s['id'].');">'.htmlspecialchars($s['origin_file_name']).'</a></td>';
              
              if(isset($folders_enabled) && $folders_enabled && !isset($current_folder_id)) {
                if(!empty($s['folder_name'])) {
                  echo '<td><a href="?folder='.$s['folder_id'].'" class="folder-link">📁 '.htmlspecialchars($s['folder_name']).'</a></td>';
                } else {
                  echo '<td><a href="?folder=" class="folder-link">🏠 ルート</a></td>';
                }
              }
              
              echo '<td>'.htmlspecialchars($s['comment']).'</td>';
              echo '<td>'.round($s['size'] / (1024*1024), 1 ).'MB</td>';
              echo '<td>'.date("Y/m/d H:i:s", $s['input_date']).'</td>';
              echo '<td>'.$s['count'].'</td>';
              echo '<td><a href="javascript:void(0);" onclick="share_button('.$s['id'].');">[共有]</a></td>';
              echo '<td>';
              echo '<a href="javascript:void(0);" onclick="del_button('.$s['id'].');">[DEL]</a> ';
              if(isset($folders_enabled) && $folders_enabled) {
                echo '<a href="javascript:void(0);" onclick="moveFile('.$s['id'].');" title="ファイルを移動">[移動]</a> ';
              }
              echo '<a href="javascript:void(0);" onclick="editFile('.$s['id'].',\''.$s['origin_file_name'].'\',\''.$s['comment'].'\');" title="ファイルを編集">[編集]</a>';
              echo '</td>';
            echo '</tr>';
        }
        ?>
        </tbody>
        <tfoot>
          <tr>
            <th>ID</th>
            <th>ファイル名</th>
            <?php if(isset($folders_enabled) && $folders_enabled && !isset($current_folder_id)): ?>
            <th>フォルダ</th>
            <?php endif; ?>
            <th>コメント</th>
            <th>サイズ</th>
            <th>日付</th>
            <th>DL数</th>
            <th>共有</th>
            <th>操作</th>
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
            echo htmlspecialchars($s['origin_file_name']) . '</a>';
            echo '      <p class="file-card__comment">' . htmlspecialchars($s['comment']) . '</p>';
            
            if(isset($folders_enabled) && $folders_enabled && !isset($current_folder_id)) {
              if(!empty($s['folder_name'])) {
                echo '      <p class="file-card__folder"><a href="?folder='.$s['folder_id'].'" onclick="event.stopPropagation();">📁 '.htmlspecialchars($s['folder_name']).'</a></p>';
              } else {
                echo '      <p class="file-card__folder"><a href="?folder=" onclick="event.stopPropagation();">🏠 ルート</a></p>';
              }
            }
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
            echo 'onclick="dl_button(' . $s['id'] . ');">ダウンロード</a>';
            echo '      <a href="javascript:void(0);" class="file-card__action-btn file-card__action-btn--share" ';
            echo 'onclick="share_button(' . $s['id'] . ');">共有</a>';
            if(isset($folders_enabled) && $folders_enabled) {
              echo '      <a href="javascript:void(0);" class="file-card__action-btn file-card__action-btn--move" onclick="moveFile('.$s['id'].');">移動</a>';
            }
            echo '      <a href="javascript:void(0);" class="file-card__action-btn file-card__action-btn--edit" onclick="editFile('.$s['id'].',\''.$s['origin_file_name'].'\',\''.$s['comment'].'\');">編集</a>';
            echo '      <a href="javascript:void(0);" class="file-card__action-btn file-card__action-btn--delete" ';
            echo 'onclick="del_button(' . $s['id'] . ');">削除</a>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        }
        ?>
      </div>

      <!-- ファイルカードコンテナ -->
      <div class="file-cards-container" style="display: none;"></div>
    </div>
    <p class="text-right">@<a href="https://github.com/shimosyan/phpUploader" target="_blank">
      shimosyan/phpUploader</a> v<?php echo $version; ?> (GitHub)</p>
  </div>
</div>

<!-- ファイル編集モーダル -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="editModalLabel">ファイル編集</h4>
      </div>
      <div class="modal-body">
        <!-- タブナビゲーション -->
        <ul class="nav nav-tabs" role="tablist">
          <li role="presentation" class="active">
            <a href="#commentTab" aria-controls="commentTab" role="tab" data-toggle="tab">コメント編集</a>
          </li>
          <li role="presentation">
            <a href="#replaceTab" aria-controls="replaceTab" role="tab" data-toggle="tab">ファイル差し替え</a>
          </li>
        </ul>
        
        <!-- タブコンテント -->
        <div class="tab-content" style="margin-top: 15px;">
          <!-- コメント編集タブ -->
          <div role="tabpanel" class="tab-pane active" id="commentTab">
            <form id="editCommentForm">
              <div class="form-group">
                <label for="editFileName">ファイル名</label>
                <input type="text" class="form-control" id="editFileName" readonly>
              </div>
              <div class="form-group">
                <label for="editComment">コメント</label>
                <input type="text" class="form-control" id="editComment" placeholder="コメントを入力...">
                <p class="help-block"><?php echo isset($max_comment) ? $max_comment : 80; ?>字まで入力できます。</p>
              </div>
              <input type="hidden" id="editFileId">
            </form>
          </div>
          
          <!-- ファイル差し替えタブ -->
          <div role="tabpanel" class="tab-pane" id="replaceTab">
            <form id="replaceFileForm" enctype="multipart/form-data">
              <div class="form-group">
                <label for="replaceFileName">現在のファイル名</label>
                <input type="text" class="form-control" id="replaceFileName" readonly>
              </div>
              <div class="form-group">
                <label for="replaceFileInput">新しいファイル</label>
                <input type="file" class="form-control" id="replaceFileInput" name="file" required>
                <p class="help-block">
                  <?php echo isset($max_file_size) ? $max_file_size : 100; ?>MBまでのファイルがアップロードできます。<br>
                  対応拡張子： <?php 
                    if (isset($extension) && is_array($extension)) {
                        foreach($extension as $ext){
                            echo $ext.' ';
                        }
                    } else {
                        echo 'zip pdf jpg png';
                    }
                  ?>
                </p>
              </div>
              <div class="form-group">
                <label for="replaceKeyInput">差し替えキー</label>
                <input type="password" class="form-control" id="replaceKeyInput" name="replacekey" placeholder="差し替えキーを入力してください" required>
                <p class="help-block">アップロード時に設定した差し替えキーを入力してください。</p>
              </div>
              <input type="hidden" id="replaceFileId">
              <div class="alert alert-warning">
                <span class="glyphicon glyphicon-warning-sign"></span>
                <strong>注意:</strong> ファイルを差し替えると、元のファイルは削除されます。この操作は取り消せません。
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">キャンセル</button>
        <button type="button" class="btn btn-primary" id="saveCommentBtn" style="display: none;">コメント保存</button>
        <button type="button" class="btn btn-warning" id="replaceFileBtn" style="display: none;">ファイル差し替え</button>
      </div>
    </div>
  </div>
</div>

<!-- ファイルデータをJavaScriptに渡す -->
<script>
  // PHPからJavaScriptにファイルデータを渡す
  window.fileData = <?php echo json_encode(isset($data) ? $data : [], JSON_UNESCAPED_UNICODE); ?>;
</script>
