<?php
/**
 * フォルダナビゲーション部分テンプレート
 * フォルダ一覧表示と管理機能を担当
 */
?>

<?php if(isset($folders_enabled) && $folders_enabled): ?>
<!-- フォルダナビゲーション -->
<div class="row bg-white radius box-shadow">
  <div class="col-sm-12">
    <div class="page-header">
      <h1>📁 フォルダ管理 <small>フォルダナビゲーション</small></h1>
    </div>
    
    <!-- パンくずリスト -->
    <div class="form-section">
      <div class="folder-breadcrumb">
        <label>📍 現在の場所:</label>
        <ol class="breadcrumb" style="display: inline-block; margin: 0 0 0 10px; background: transparent; padding: 5px 0;">
          <li><a href="?folder=" class="breadcrumb-link">🏠 ルート</a></li>
          <?php if(isset($breadcrumb) && is_array($breadcrumb)): ?>
            <?php foreach($breadcrumb as $index => $bc): ?>
              <?php if($index + 1 === count($breadcrumb)): ?>
                <li class="active"><?php echo htmlspecialchars($bc['name']); ?></li>
              <?php else: ?>
                <li><a href="?folder=<?php echo $bc['id']; ?>" class="breadcrumb-link"><?php echo htmlspecialchars($bc['name']); ?></a></li>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </ol>
      </div>
    </div>
    
    <!-- フォルダ一覧 -->
    <div class="form-section">
      <div class="folder-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h4 style="margin: 0; color: #333;">📂 フォルダ一覧</h4>
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
        <div class="col-sm-3 col-xs-6" style="margin-bottom: 15px;" data-folder-id="<?php echo $folder['id']; ?>">
          <div class="folder-item-wrapper" style="position: relative;">
            <a href="?folder=<?php echo $folder['id']; ?>" class="folder-item">
              <span class="folder-icon">📁</span>
              <span class="folder-name"><?php echo htmlspecialchars($folder['name']); ?></span>
            </a>
            <?php // 全てのフォルダに管理メニューを表示 ?>
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
</div>
<?php endif; ?>