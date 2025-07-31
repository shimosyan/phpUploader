/**
 * FileManager 一括操作クラス
 * ファイルの一括移動・一括削除などの操作を担当
 */
class FileManagerBulkActions {
  constructor(coreInstance) {
    this.core = coreInstance;
  }

  /**
   * 一括移動処理
   */
  async bulkMove() {
    const selectedIds = this.core.getSelectedFileIds();
    if (selectedIds.length === 0) {
      alert('移動するファイルを選択してください');
      return;
    }

    try {
      // フォルダ一覧を取得
      const response = await fetch('./app/api/folders.php');
      if (!response.ok) throw new Error('フォルダ読み込み失敗');
      
      const data = await response.json();
      const folders = data.folders || [];
      
      // 移動先選択のプロンプト作成
      let options = 'ルートフォルダに移動する場合は「root」と入力してください。\n\n利用可能なフォルダ:\n';
      const addFolderOptions = (folders, level = 0) => {
        folders.forEach(folder => {
          options += '　'.repeat(level) + `${folder.id}: ${folder.name}\n`;
          if (folder.children && folder.children.length > 0) {
            addFolderOptions(folder.children, level + 1);
          }
        });
      };
      addFolderOptions(folders);
      
      const targetId = prompt(options + `\n${selectedIds.length}個のファイルの移動先フォルダIDを入力してください:`);
      if (targetId === null) return; // キャンセル
      
      const folderId = targetId.toLowerCase() === 'root' ? null : parseInt(targetId) || null;
      
      // 一括移動API呼び出し
      const moveResponse = await fetch('./app/api/move-files.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file_ids: selectedIds, folder_id: folderId })
      });
      
      const moveData = await moveResponse.json();
      
      if (!moveResponse.ok) {
        throw new Error(moveData.error || '一括移動に失敗しました');
      }
      
      alert(`${selectedIds.length}個のファイルを移動しました`);
      location.reload();
      
    } catch (error) {
      console.error('一括移動エラー:', error);
      alert('エラー: ' + error.message);
    }
  }

  /**
   * 一括削除処理
   */
  async bulkDelete() {
    const selectedIds = this.core.getSelectedFileIds();
    if (selectedIds.length === 0) {
      alert('削除するファイルを選択してください');
      return;
    }

    if (!confirm(`選択した${selectedIds.length}個のファイルを削除しますか？\n\nこの操作は取り消せません。`)) {
      return;
    }

    try {
      // 一括削除API呼び出し（個別削除の繰り返し）
      let successCount = 0;
      let errorCount = 0;
      
      for (const fileId of selectedIds) {
        try {
          // 既存のdel_certificat関数を使用
          await new Promise((resolve, reject) => {
            // del_certificat関数は非同期ではないため、XMLHttpRequestを直接使用
            const xhr = new XMLHttpRequest();
            xhr.open('POST', './app/api/router.php', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function() {
              if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                  resolve();
                } else {
                  reject(new Error('削除に失敗しました'));
                }
              }
            };
            xhr.send(JSON.stringify({
              action: 'delete',
              file_id: fileId,
              del_key: ''
            }));
          });
          successCount++;
        } catch (error) {
          errorCount++;
          console.error(`ファイルID ${fileId} の削除エラー:`, error);
        }
      }
      
      if (errorCount > 0) {
        alert(`${successCount}個のファイルを削除しました。\n${errorCount}個のファイルの削除に失敗しました。`);
      } else {
        alert(`${successCount}個のファイルを削除しました。`);
      }
      
      location.reload();
      
    } catch (error) {
      console.error('一括削除エラー:', error);
      alert('エラー: ' + error.message);
    }
  }

  /**
   * 選択されたファイルの情報を取得
   */
  getSelectedFilesInfo() {
    const selectedIds = this.core.getSelectedFileIds();
    const selectedFiles = this.core.files.filter(file => selectedIds.includes(file.id));
    
    return {
      count: selectedFiles.length,
      files: selectedFiles,
      totalSize: selectedFiles.reduce((sum, file) => sum + file.size, 0),
      extensions: [...new Set(selectedFiles.map(file => 
        FileManagerUtils.getFileExtension(file.origin_file_name)
      ))]
    };
  }

  /**
   * 選択状態をクリア
   */
  clearSelection() {
    const checkboxes = document.querySelectorAll('.file-checkbox');
    checkboxes.forEach(checkbox => {
      checkbox.checked = false;
    });
    this.core.updateBulkActions();
  }

  /**
   * 全選択
   */
  selectAll() {
    const checkboxes = document.querySelectorAll('.file-checkbox');
    checkboxes.forEach(checkbox => {
      checkbox.checked = true;
    });
    this.core.updateBulkActions();
  }

  /**
   * 表示中のファイルのみ選択
   */
  selectCurrentPage() {
    const currentPageFiles = this.core.getCurrentPageFiles();
    const checkboxes = document.querySelectorAll('.file-checkbox');
    
    checkboxes.forEach(checkbox => {
      const fileId = parseInt(checkbox.value);
      checkbox.checked = currentPageFiles.some(file => file.id === fileId);
    });
    
    this.core.updateBulkActions();
  }
}

// エクスポート
window.FileManagerBulkActions = FileManagerBulkActions;