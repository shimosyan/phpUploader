
<div class="container">
  <div class="row bg-white radius box-shadow">
    <div class="col-sm-12">
      <p class="h2">ファイルを登録</p>
      <form id="upload">
        <input id="lefile" name="file" type="file" style="display:none">
        <div class="input-group">
          <input type="text" id="fileInput" class="form-control" name="file" placeholder="ファイルを選択...">
          <span class="input-group-btn"><button type="button" class="btn btn-primary" onclick="$('input[id=lefile]').click();">Browse</button></span>
        </div>
        <p class="help-block"><?php echo $max_file_size; ?>MBまでのファイルがアップロードできます。<br>対応拡張子： <?php 
          foreach($extension as $s){
            echo $s.' ';
          }
         ?></p>

        <div class="form-group">
          <label for="commentInput">コメント</label>
          <input type="text" class="form-control" id="commentInput" name="comment" placeholder="コメントを入力...">
          <p class="help-block"><?php echo $max_comment; ?>字までの入力できます。</p>
        </div>


        <div class="row">
          <div class="col-sm-6">
            <div class="form-group">
              <label for="dlkeyInput">DLキー</label>
              <input type="text" class="form-control" id="dleyInput" name="dlkey" placeholder="DLキーを入力...">
            </div>
          </div>
          <div class="col-sm-6">
            <div class="form-group">
              <label for="delkeyInput">DELキー</label>
              <input type="text" class="form-control" id="deleyInput" name="delkey" placeholder="DELキーを入力...">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-sm-offset-10 col-sm-2">
            <button type="button" class="btn btn-success btn-block" onclick="file_upload()">送信</button>
          </div>
        </div>
      </form>

      <div id="uploadContainer" class="panel panel-success" style="display: none;">
        <div class="panel-heading">
          アップロード中
        </div>
        <div class="panel-body">
          <div class="progress">
            <div id="progressBar" class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width: 0%;">
            </div>
          </div>
        </div>
      </div>

      <div id="errorContainer" class="panel panel-danger" style="display: none;">
        <div class="panel-heading">
          エラー
        </div>
        <div class="panel-body">
        </div>
      </div>

    </div>
  </div>
  
  <div class="row bg-white radius box-shadow">
    <div class="col-sm-12">
      <p class="h2">ファイル一覧</p>

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
    <p class="text-right">@<a href="https://github.com/shimosyan/phpUploader" target="_blank">shimosyan/phpUploader</a> (GitHub)</p>
  </div>
</div>
