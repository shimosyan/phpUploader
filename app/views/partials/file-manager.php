<?php
/**
 * ファイルマネージャー部分テンプレート
 * ファイル一覧表示機能を担当
 */
?>

<div class="row bg-white radius box-shadow">
  <div class="col-sm-12">
    
    <!-- ファイルマネージャー (リファクタリング版) -->
    <div id="fileManagerContainer"></div>

    <!-- ファイルリストテーブル (旧式 - 非表示) -->
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
        if(isset($data) && is_array($data)):
          foreach($data as $file): 
            // ファイルサイズをMBに変換
            $fileSizeMB = number_format($file['size'] / (1024 * 1024), 1);
            
            // 日付をフォーマット
            $uploadDate = date('Y/m/d H:i', $file['input_date']);
            
            // フォルダ名取得（フォルダ有効時）
            $folderName = '';
            if(isset($folders_enabled) && $folders_enabled && !empty($folders)) {
              foreach($folders as $folder) {
                if($folder['id'] == $file['folder_id']) {
                  $folderName = $folder['name'];
                  break;
                }
              }
            }
      ?>
          <tr data-file-id="<?php echo $file['id']; ?>">
            <td><?php echo $file['id']; ?></td>
            <td>
              <a href="javascript:void(0);" onclick="dl_button(<?php echo $file['id']; ?>);">
                <?php echo htmlspecialchars($file['origin_file_name']); ?>
              </a>
            </td>
            <?php if(isset($folders_enabled) && $folders_enabled && !isset($current_folder_id)): ?>
            <td><?php echo $folderName ? htmlspecialchars($folderName) : 'ルート'; ?></td>
            <?php endif; ?>
            <td><?php echo htmlspecialchars($file['comment']); ?></td>
            <td><?php echo $fileSizeMB; ?>MB</td>
            <td><?php echo $uploadDate; ?></td>
            <td><?php echo $file['count']; ?></td>
            <td>
              <button class="btn btn-info btn-xs" onclick="shareFile(<?php echo $file['id']; ?>);" title="共有リンク生成">
                <span class="glyphicon glyphicon-share"></span>
              </button>
            </td>
            <td>
              <div class="btn-group">
                <button class="btn btn-primary btn-xs" onclick="dl_button(<?php echo $file['id']; ?>);" title="ダウンロード">
                  <span class="glyphicon glyphicon-download-alt"></span>
                </button>
                <?php if(isset($allow_comment_edit) && $allow_comment_edit): ?>
                <button class="btn btn-warning btn-xs" onclick="editComment(<?php echo $file['id']; ?>, '<?php echo addslashes($file['comment']); ?>');" title="コメント編集">
                  <span class="glyphicon glyphicon-edit"></span>
                </button>
                <?php endif; ?>
                <?php if(isset($allow_file_replace) && $allow_file_replace): ?>
                <button class="btn btn-info btn-xs" onclick="replaceFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['origin_file_name']); ?>');" title="ファイル差し替え">
                  <span class="glyphicon glyphicon-refresh"></span>
                </button>
                <?php endif; ?>
                <button class="btn btn-danger btn-xs" onclick="del_certificat(<?php echo $file['id']; ?>);" title="削除">
                  <span class="glyphicon glyphicon-trash"></span>
                </button>
              </div>
            </td>
          </tr>
      <?php 
          endforeach;
        endif; 
      ?>
        </tbody>
      </table>
    </div>

  </div>
</div>