/**
 * FileManager レンダリングクラス
 * 全てのHTML生成とレンダリング機能を担当
 */
class FileManagerRenderer {
  constructor(coreInstance) {
    this.core = coreInstance;
  }

  /**
   * メインレンダリング処理
   */
  render() {
    // フォーカス状態を保存
    const activeElement = document.activeElement;
    const wasSearchFocused = activeElement && activeElement.id === 'fileSearchInput';
    const searchValue = wasSearchFocused ? activeElement.value : this.core.searchQuery;
    const cursorPosition = wasSearchFocused ? activeElement.selectionStart : 0;

    const startIndex = (this.core.currentPage - 1) * this.core.itemsPerPage;
    const endIndex = startIndex + this.core.itemsPerPage;
    const pageFiles = this.core.filteredFiles.slice(startIndex, endIndex);

    this.core.container.innerHTML = `
      <div class="file-manager">
        ${this.renderHeader()}
        ${this.renderControls()}
        ${this.renderContent(pageFiles)}
        ${this.renderPagination()}
      </div>
    `;

    // フォーカス状態を復元
    if (wasSearchFocused) {
      const searchInput = document.getElementById('fileSearchInput');
      if (searchInput) {
        searchInput.focus();
        searchInput.setSelectionRange(cursorPosition, cursorPosition);
      }
    }
  }

  /**
   * ヘッダー部分のレンダリング
   */
  renderHeader() {
    const totalFiles = this.core.files.length;
    const filteredCount = this.core.filteredFiles.length;

    return `
      <div class="file-manager__header">
        <h2 class="file-manager__title">
          📁 ファイル一覧
        </h2>
        <div class="file-manager__stats">
          ${filteredCount !== totalFiles ?
            `${filteredCount}件 (全${totalFiles}件中)` :
            `${totalFiles}件`
          }
        </div>
      </div>
    `;
  }

  /**
   * コントロール部分のレンダリング
   */
  renderControls() {
    return `
      <div class="file-controls">
        <div class="file-search">
          <div class="file-search__input">
            <input 
              type="text" 
              placeholder="🔍 ファイル名・コメントで検索..." 
              value="${this.core.searchQuery}"
              id="fileSearchInput"
            >
          </div>
          <div class="file-search__sort">
            <label for="fileSortSelect">並び順:</label>
            <select id="fileSortSelect">
              <option value="date_desc" ${this.core.sortBy === 'date_desc' ? 'selected' : ''}>新しい順</option>
              <option value="date_asc" ${this.core.sortBy === 'date_asc' ? 'selected' : ''}>古い順</option>
              <option value="name_asc" ${this.core.sortBy === 'name_asc' ? 'selected' : ''}>名前 A-Z</option>
              <option value="name_desc" ${this.core.sortBy === 'name_desc' ? 'selected' : ''}>名前 Z-A</option>
              <option value="size_desc" ${this.core.sortBy === 'size_desc' ? 'selected' : ''}>サイズ大順</option>
              <option value="size_asc" ${this.core.sortBy === 'size_asc' ? 'selected' : ''}>サイズ小順</option>
              <option value="downloads_desc" ${this.core.sortBy === 'downloads_desc' ? 'selected' : ''}>DL数多順</option>
              <option value="downloads_asc" ${this.core.sortBy === 'downloads_asc' ? 'selected' : ''}>DL数少順</option>
            </select>
          </div>
          ${this.core.searchQuery ? `
            <button class="file-search__clear" id="fileSearchClear">
              クリア
            </button>
          ` : ''}
        </div>

        <div class="file-view-toggle">
          <button 
            class="file-view-toggle__btn ${this.core.viewMode === 'grid' ? 'file-view-toggle__btn--active' : ''}" 
            data-view="grid"
            title="グリッドビュー"
          >
            ⊞ グリッド
          </button>
          <button 
            class="file-view-toggle__btn ${this.core.viewMode === 'list' ? 'file-view-toggle__btn--active' : ''}" 
            data-view="list"
            title="リストビュー"
          >
            ☰ リスト
          </button>
        </div>
      </div>

      <!-- 一括操作バー -->
      <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
        <div class="bulk-actions-bar__info">
          <label>
            <input type="checkbox" id="selectAllFiles" onchange="window.fileManagerInstance.toggleSelectAll()">
            <span id="selectedCount">0</span>個のファイルを選択中
          </label>
        </div>
        <div class="bulk-actions-bar__actions">
          <button class="bulk-action-btn" onclick="window.fileManagerInstance.bulkMove()">
            📁 一括移動
          </button>
          <button class="bulk-action-btn bulk-action-btn--danger" onclick="window.fileManagerInstance.bulkDelete()">
            🗑️ 一括削除
          </button>
        </div>
      </div>
    `;
  }

  /**
   * 旧バージョン互換用検索レンダリング
   */
  renderSearch() {
    return this.renderControls();
  }

  /**
   * コンテンツ部分のレンダリング
   */
  renderContent(files) {
    if (files.length === 0) {
      if (this.core.filteredFiles.length === 0 && this.core.files.length === 0) {
        return `
          <div class="file-empty">
            <div class="file-empty__icon">📄</div>
            <h3 class="file-empty__title">アップロードされたファイルはありません</h3>
            <p class="file-empty__message">上のフォームからファイルをアップロードしてください。</p>
          </div>
        `;
      } else {
        return `
          <div class="file-no-results">
            <div class="file-empty__icon">🔍</div>
            <h3 class="file-empty__title">検索結果が見つかりません</h3>
            <p class="file-empty__message">検索条件を変更してお試しください。</p>
          </div>
        `;
      }
    }

    if (this.core.viewMode === 'list') {
      return `
        <div class="file-list">
          ${files.map(file => this.renderFileListItem(file)).join('')}
        </div>
      `;
    } else {
      return `
        <div class="file-cards">
          ${files.map(file => this.renderFileCard(file)).join('')}
        </div>
      `;
    }
  }

  /**
   * ファイルリストアイテムのレンダリング
   */
  renderFileListItem(file) {
    const fileSize = (file.size / (1024 * 1024)).toFixed(1);
    const uploadDate = new Date(file.input_date * 1000);
    const formattedDate = uploadDate.toLocaleDateString('ja-JP', {
      year: 'numeric',
      month: 'numeric',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
    const fileExt = FileManagerUtils.getFileExtension(file.origin_file_name);
    const fileIcon = FileManagerUtils.getFileIcon(fileExt);

    return `
      <div class="file-list-item" data-file-id="${file.id}">
        <div class="file-list-item__checkbox">
          <input type="checkbox" class="file-checkbox" value="${file.id}" onchange="window.fileManagerInstance.updateBulkActions()">
        </div>
        <div class="file-list-item__icon">
          ${fileIcon}
        </div>
        <div class="file-list-item__main">
          <div class="file-list-item__info">
            <a 
              href="javascript:void(0);" 
              class="file-list-item__filename"
              onclick="dl_button(${file.id});"
              title="${FileManagerUtils.escapeHtml(file.origin_file_name)}"
            >
              ${FileManagerUtils.escapeHtml(file.origin_file_name)}
            </a>
            ${file.comment ? `
              <p class="file-list-item__comment" title="${FileManagerUtils.escapeHtml(file.comment)}">
                ${FileManagerUtils.escapeHtml(file.comment)}
              </p>
            ` : ''}
          </div>
          <div class="file-list-item__meta">
            <span class="file-list-item__meta-item">
              <span class="file-list-item__meta-label">サイズ:</span>
              ${fileSize}MB
            </span>
            <span class="file-list-item__meta-item">
              <span class="file-list-item__meta-label">DL:</span>
              ${file.count}回
            </span>
            <span class="file-list-item__meta-item">
              <span class="file-list-item__meta-label">投稿:</span>
              ${formattedDate}
            </span>
            ${this.getFolderMetaItemList(file)}
          </div>
        </div>
        <div class="file-list-item__actions">
          <button class="file-list-item__action" onclick="dl_button(${file.id});" title="ダウンロード">
            ⬇️
          </button>
          <button class="file-list-item__action file-list-item__btn--share" onclick="shareFile(${file.id});" title="共有リンク生成">
            🔗
          </button>
          ${window.config?.folders_enabled ? `
          <button class="file-list-item__action" onclick="moveFile(${file.id});" title="フォルダ移動">
            📂
          </button>
          ` : ''}
          ${this.getEditButtonList(file)}
          ${this.getReplaceButtonList(file)}
          <button class="file-list-item__action" onclick="del_button(${file.id});" title="削除">
            🗑️
          </button>
        </div>
      </div>
    `;
  }

  /**
   * ファイルカードのレンダリング
   */
  renderFileCard(file) {
    const fileSize = (file.size / (1024 * 1024)).toFixed(1);
    const uploadDate = new Date(file.input_date * 1000);
    const formattedDate = uploadDate.toLocaleDateString('ja-JP', {
      year: 'numeric',
      month: 'numeric',
      day: 'numeric'
    });
    const fileExt = FileManagerUtils.getFileExtension(file.origin_file_name);
    const fileIcon = FileManagerUtils.getFileIcon(fileExt);

    return `
      <div class="file-card-v2" data-file-id="${file.id}">
        <div class="file-card-v2__checkbox">
          <input type="checkbox" class="file-checkbox" value="${file.id}" onchange="window.fileManagerInstance.updateBulkActions()">
        </div>
        <div class="file-card-v2__header">
          <div class="file-card-v2__icon">
            ${fileIcon}
          </div>
          <a 
            href="javascript:void(0);" 
            class="file-card-v2__filename"
            onclick="dl_button(${file.id});"
            title="${FileManagerUtils.escapeHtml(file.origin_file_name)}"
          >
            ${FileManagerUtils.escapeHtml(file.origin_file_name)}
          </a>
          ${file.comment ? `
            <p class="file-card-v2__comment" title="${FileManagerUtils.escapeHtml(file.comment)}">
              ${FileManagerUtils.escapeHtml(file.comment)}
            </p>
          ` : ''}
        </div>
        <div class="file-card-v2__body">
          <div class="file-card-v2__meta">
            <div class="file-card-v2__meta-item">
              <span class="file-card-v2__meta-icon">📏</span>
              <span class="file-card-v2__meta-label">サイズ:</span>
              <span class="file-card-v2__meta-value">${fileSize}MB</span>
            </div>
            <div class="file-card-v2__meta-item">
              <span class="file-card-v2__meta-icon">⬇️</span>
              <span class="file-card-v2__meta-label">DL:</span>
              <span class="file-card-v2__meta-value">${file.count}回</span>
            </div>
            <div class="file-card-v2__meta-item">
              <span class="file-card-v2__meta-icon">📅</span>
              <span class="file-card-v2__meta-label">投稿:</span>
              <span class="file-card-v2__meta-value">${formattedDate}</span>
            </div>
            ${this.getFolderMetaItem(file)}
          </div>
        </div>
        <div class="file-card-v2__actions">
          <button class="file-card-v2__btn" onclick="dl_button(${file.id});" title="ダウンロード">
            <span class="file-card-v2__btn-icon">⬇️</span>
            ダウンロード
          </button>
          <button class="file-card-v2__btn file-card-v2__btn--share" onclick="shareFile(${file.id});" title="共有リンク生成">
            <span class="file-card-v2__btn-icon">🔗</span>
            共有
          </button>
          ${window.config?.folders_enabled ? `
          <button class="file-card-v2__btn" onclick="moveFile(${file.id});" title="フォルダ移動">
            <span class="file-card-v2__btn-icon">📂</span>
            移動
          </button>
          ` : ''}
          ${this.getEditButton(file)}
          ${this.getReplaceButton(file)}
          <button class="file-card-v2__btn file-card-v2__btn--delete" onclick="del_button(${file.id});" title="削除">
            <span class="file-card-v2__btn-icon">🗑️</span>
            削除
          </button>
        </div>
      </div>
    `;
  }

  /**
   * 編集ボタンを取得（設定により表示制御）
   */
  getEditButton(file) {
    // 設定情報はグローバル変数またはcoreインスタンスから取得
    const allowCommentEdit = window.config?.allow_comment_edit || false;
    if (!allowCommentEdit) return '';
    
    const escapedComment = FileManagerUtils.escapeHtml(file.comment || '').replace(/'/g, '&#39;');
    const escapedFilename = FileManagerUtils.escapeHtml(file.origin_file_name).replace(/'/g, '&#39;');
    return `
      <button class="file-card-v2__btn file-card-v2__btn--edit" onclick="editComment(${file.id}, '${escapedComment}', '${escapedFilename}');" title="コメント編集">
        <span class="file-card-v2__btn-icon">✏️</span>
        編集
      </button>
    `;
  }

  /**
   * ファイル差し替えボタンを取得（設定により表示制御）
   */
  getReplaceButton(file) {
    // 設定情報はグローバル変数またはcoreインスタンスから取得
    const allowFileReplace = window.config?.allow_file_replace || false;
    if (!allowFileReplace) return '';
    
    const escapedFilename = FileManagerUtils.escapeHtml(file.origin_file_name).replace(/'/g, '&#39;');
    return `
      <button class="file-card-v2__btn file-card-v2__btn--replace" onclick="replaceFile(${file.id}, '${escapedFilename}');" title="ファイル差し替え">
        <span class="file-card-v2__btn-icon">🔄</span>
        差し替え
      </button>
    `;
  }

  /**
   * フォルダ情報を取得
   */
  getFolderInfo(file) {
    // フォルダ機能が無効の場合は空文字を返す
    const foldersEnabled = window.config?.folders_enabled || false;
    if (!foldersEnabled || !file.folder_id) {
      return foldersEnabled ? 'ルート' : '';
    }

    // フォルダ一覧から該当フォルダを検索
    const folders = window.folderData || [];
    const folder = folders.find(f => f.id == file.folder_id);
    return folder ? FileManagerUtils.escapeHtml(folder.name) : 'ルート';
  }

  /**
   * カード用フォルダメタアイテムを取得
   */
  getFolderMetaItem(file) {
    const foldersEnabled = window.config?.folders_enabled || false;
    if (!foldersEnabled) return '';
    
    const folderName = this.getFolderInfo(file);
    return `
      <div class="file-card-v2__meta-item">
        <span class="file-card-v2__meta-icon">📁</span>
        <span class="file-card-v2__meta-label">フォルダ:</span>
        <span class="file-card-v2__meta-value">${folderName}</span>
      </div>
    `;
  }

  /**
   * リスト用編集ボタンを取得
   */
  getEditButtonList(file) {
    const allowCommentEdit = window.config?.allow_comment_edit || false;
    if (!allowCommentEdit) return '';
    
    const escapedComment = FileManagerUtils.escapeHtml(file.comment || '').replace(/'/g, '&#39;');
    const escapedFilename = FileManagerUtils.escapeHtml(file.origin_file_name).replace(/'/g, '&#39;');
    return `
      <button class="file-list-item__action file-list-item__btn--edit" onclick="editComment(${file.id}, '${escapedComment}', '${escapedFilename}');" title="コメント編集">
        ✏️
      </button>
    `;
  }

  /**
   * リスト用差し替えボタンを取得
   */
  getReplaceButtonList(file) {
    const allowFileReplace = window.config?.allow_file_replace || false;
    if (!allowFileReplace) return '';
    
    const escapedFilename = FileManagerUtils.escapeHtml(file.origin_file_name).replace(/'/g, '&#39;');
    return `
      <button class="file-list-item__action file-list-item__btn--replace" onclick="replaceFile(${file.id}, '${escapedFilename}');" title="ファイル差し替え">
        🔄
      </button>
    `;
  }

  /**
   * リスト用フォルダメタアイテムを取得
   */
  getFolderMetaItemList(file) {
    const foldersEnabled = window.config?.folders_enabled || false;
    if (!foldersEnabled) return '';
    
    const folderName = this.getFolderInfo(file);
    return `
      <span class="file-list-item__meta-item">
        <span class="file-list-item__meta-label">📁 フォルダ:</span>
        ${folderName}
      </span>
    `;
  }

  /**
   * ページネーションのレンダリング
   */
  renderPagination() {
    const totalPages = Math.ceil(this.core.filteredFiles.length / this.core.itemsPerPage);
    
    if (totalPages <= 1) {
      return '';
    }
    
    const startItem = (this.core.currentPage - 1) * this.core.itemsPerPage + 1;
    const endItem = Math.min(this.core.currentPage * this.core.itemsPerPage, this.core.filteredFiles.length);
    
    let paginationHTML = `
      <div class="file-pagination">
        <div class="file-pagination__info">
          ${startItem}-${endItem}件 (全${this.core.filteredFiles.length}件)
        </div>
        
        <div class="file-pagination__controls">
          <div class="file-pagination__per-page">
            <label for="itemsPerPageSelect">表示件数:</label>
            <select id="itemsPerPageSelect">
              <option value="6" ${this.core.itemsPerPage === 6 ? 'selected' : ''}>6件</option>
              <option value="12" ${this.core.itemsPerPage === 12 ? 'selected' : ''}>12件</option>
              <option value="24" ${this.core.itemsPerPage === 24 ? 'selected' : ''}>24件</option>
              <option value="48" ${this.core.itemsPerPage === 48 ? 'selected' : ''}>48件</option>
            </select>
          </div>
          
          <div class="file-pagination__nav">
    `;
    
    // 前へボタン
    paginationHTML += `
      <button 
        class="file-pagination__btn" 
        data-page="${this.core.currentPage - 1}"
        ${this.core.currentPage === 1 ? 'disabled' : ''}
      >
        ←
      </button>
    `;
    
    // ページ番号ボタン
    const maxVisiblePages = 5;
    let startPage = Math.max(1, this.core.currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    if (startPage > 1) {
      paginationHTML += `<button class="file-pagination__btn" data-page="1">1</button>`;
      if (startPage > 2) {
        paginationHTML += `<span class="file-pagination__ellipsis">...</span>`;
      }
    }
    
    for (let i = startPage; i <= endPage; i++) {
      paginationHTML += `
        <button 
          class="file-pagination__btn ${i === this.core.currentPage ? 'file-pagination__btn--active' : ''}" 
          data-page="${i}"
        >
          ${i}
        </button>
      `;
    }
    
    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        paginationHTML += `<span class="file-pagination__ellipsis">...</span>`;
      }
      paginationHTML += `<button class="file-pagination__btn" data-page="${totalPages}">${totalPages}</button>`;
    }
    
    // 次へボタン
    paginationHTML += `
      <button 
        class="file-pagination__btn" 
        data-page="${this.core.currentPage + 1}"
        ${this.core.currentPage === totalPages ? 'disabled' : ''}
      >
        →
      </button>
    `;
    
    paginationHTML += `
          </div>
        </div>
      </div>
    `;
    
    return paginationHTML;
  }
}

// エクスポート
window.FileManagerRenderer = FileManagerRenderer;