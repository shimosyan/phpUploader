<?php
/**
 * 共有リンクモーダル部分テンプレート
 * 共有リンク機能のモーダルダイアログを担当
 */
?>

<!-- 共有リンクモーダル -->
<div class="modal fade" id="shareLinkModal" tabindex="-1" role="dialog" aria-labelledby="shareLinkModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="shareLinkModalLabel">共有リンク生成</h4>
      </div>
      <div class="modal-body">
        <!-- ファイル情報 -->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="shareFileName">ファイル名</label>
              <input type="text" class="form-control" id="shareFileName" readonly>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="shareFileComment">コメント</label>
              <input type="text" class="form-control" id="shareFileComment" readonly>
            </div>
          </div>
        </div>

        <!-- 共有設定パネル -->
        <div class="panel panel-default">
          <div class="panel-heading">
            <h5 class="panel-title">共有設定</h5>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="shareMaxDownloads">最大ダウンロード数</label>
                  <input type="number" class="form-control" id="shareMaxDownloads" min="1" max="1000" placeholder="制限なし">
                  <p class="help-block">指定した回数ダウンロードされると、リンクが無効になります。</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="shareExpiresDays">有効期限（日数）</label>
                  <input type="number" class="form-control" id="shareExpiresDays" min="1" max="365" placeholder="制限なし">
                  <p class="help-block">指定した日数後にリンクが無効になります。</p>
                </div>
              </div>
            </div>
            <div class="form-group">
              <button type="button" class="btn btn-primary" id="generateShareLinkBtn">
                <span class="glyphicon glyphicon-link"></span> 共有リンクを生成
              </button>
              <button type="button" class="btn btn-default" id="regenerateShareLinkBtn" style="display: none;">
                <span class="glyphicon glyphicon-refresh"></span> 再生成
              </button>
            </div>
          </div>
        </div>

        <!-- 生成された共有リンク -->
        <div id="shareResultPanel" style="display: none;">
          <!-- 共有形式選択 -->
          <div class="form-group">
            <label>共有形式を選択</label>
            <div class="radio">
              <label>
                <input type="radio" name="shareFormat" value="url_only" checked>
                URLのみ
              </label>
            </div>
            <div class="radio">
              <label>
                <input type="radio" name="shareFormat" value="url_with_comment">
                コメント + URL
              </label>
            </div>
          </div>

          <div class="form-group">
            <label for="shareUrl">共有内容</label>
            <div class="input-group">
              <textarea class="form-control" id="shareUrl" rows="3" readonly></textarea>
              <span class="input-group-btn" style="vertical-align: top;">
                <button class="btn btn-success" type="button" id="copyShareUrlBtn" title="クリップボードにコピー" style="height: 80px;">
                  <span class="glyphicon glyphicon-copy"></span><br>
                  コピー
                </button>
              </span>
            </div>
            <p class="help-block">このリンクを使って誰でもファイルをダウンロードできます。</p>
          </div>
          
          <!-- 現在の設定情報 -->
          <div class="alert alert-info">
            <h5><strong>📊 現在の設定</strong></h5>
            <ul class="list-unstyled">
              <li id="currentMaxDownloads"><strong>最大ダウンロード数:</strong> 制限なし</li>
              <li id="currentExpiresDays"><strong>有効期限:</strong> 制限なし</li>
            </ul>
          </div>
          
          <div class="alert alert-success">
            <strong>📋 クリップボードにコピーされました！</strong><br>
            このリンクを共有したい相手に送信してください。
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">閉じる</button>
      </div>
    </div>
  </div>
</div>