/**
 * FileManager コアクラス
 * データ管理、フィルタリング、ソート、ページネーションなどの基本機能を担当
 */
class FileManagerCore {
  constructor(container, options = {}) {
    this.container = container;
    this.files = [];
    this.filteredFiles = [];
    this.currentPage = 1;
    this.itemsPerPage = options.itemsPerPage || 12;
    this.searchQuery = '';
    this.sortBy = options.defaultSort || 'date_desc';

    // ビューモードの初期化（localStorage から復元）
    this.viewMode = this.loadViewMode() || options.defaultView || 'grid';

    // 依存コンポーネントの初期化はメインクラスで行う
    this.renderer = null;
    this.events = null;
    this.bulkActions = null;
  }

  /**
   * ユーザーのビューモード設定を読み込み
   */
  loadViewMode() {
    try {
      return localStorage.getItem('fileManager_viewMode');
    } catch (e) {
      return null;
    }
  }

  /**
   * ビューモード設定を保存
   */
  saveViewMode() {
    try {
      localStorage.setItem('fileManager_viewMode', this.viewMode);
    } catch (e) {
      // localStorage が使用できない場合は無視
    }
  }

  /**
   * 初期化処理
   */
  init() {
    if (this.renderer) {
      this.renderer.render();
    }
    if (this.events) {
      this.events.bindEvents();
    }
  }

  /**
   * ファイルデータを設定
   */
  setFiles(files) {
    this.files = files;
    this.applyFilters();
    if (this.renderer) {
      this.renderer.render();
    }
  }

  /**
   * フィルタリングとソートを適用
   */
  applyFilters() {
    let filtered = [...this.files];

    // 検索フィルター
    if (this.searchQuery) {
      const query = this.searchQuery.toLowerCase();
      filtered = filtered.filter(file =>
        file.origin_file_name.toLowerCase().includes(query) ||
        file.comment.toLowerCase().includes(query) ||
        FileManagerUtils.getFileExtension(file.origin_file_name).toLowerCase().includes(query)
      );
    }

    // ソート適用
    filtered.sort((a, b) => {
      switch (this.sortBy) {
        case 'name_asc':
          return a.origin_file_name.localeCompare(b.origin_file_name);
        case 'name_desc':
          return b.origin_file_name.localeCompare(a.origin_file_name);
        case 'size_asc':
          return a.size - b.size;
        case 'size_desc':
          return b.size - a.size;
        case 'downloads_asc':
          return a.count - b.count;
        case 'downloads_desc':
          return b.count - a.count;
        case 'date_asc':
          return a.input_date - b.input_date;
        case 'date_desc':
        default:
          return b.input_date - a.input_date;
      }
    });

    this.filteredFiles = filtered;
    this.currentPage = 1; // 検索・ソート時は1ページ目に戻る
  }

  /**
   * 外部から呼び出し可能なリフレッシュメソッド
   */
  refresh() {
    if (this.renderer) {
      this.renderer.render();
    }
  }

  /**
   * 検索クエリを設定してフィルタリング
   */
  search(query) {
    this.searchQuery = query;
    this.applyFilters();
    if (this.renderer) {
      this.renderer.render();
    }
  }

  /**
   * ソート方法を設定してソート
   */
  sort(sortBy) {
    this.sortBy = sortBy;
    this.applyFilters();
    if (this.renderer) {
      this.renderer.render();
    }
  }

  /**
   * 指定ページに移動
   */
  goToPage(page) {
    this.currentPage = page;
    if (this.renderer) {
      this.renderer.render();
    }
  }

  /**
   * ビューモードを変更
   */
  setViewMode(viewMode) {
    if (this.viewMode !== viewMode) {
      this.viewMode = viewMode;
      this.saveViewMode();
      if (this.renderer) {
        this.renderer.render();
      }
    }
  }

  /**
   * 表示件数を変更
   */
  setItemsPerPage(itemsPerPage) {
    this.itemsPerPage = parseInt(itemsPerPage);
    this.currentPage = 1;
    if (this.renderer) {
      this.renderer.render();
    }
  }

  /**
   * 一括操作バーの表示を更新
   */
  updateBulkActions() {
    const checkboxes = document.querySelectorAll('.file-checkbox');
    const checkedBoxes = document.querySelectorAll('.file-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    const selectAllCheckbox = document.getElementById('selectAllFiles');

    if (checkedBoxes.length > 0) {
      bulkBar.style.display = 'flex';
      selectedCount.textContent = checkedBoxes.length;
    } else {
      bulkBar.style.display = 'none';
    }

    // 全選択チェックボックスの状態を更新
    if (selectAllCheckbox) {
      selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
      selectAllCheckbox.checked = checkedBoxes.length === checkboxes.length && checkboxes.length > 0;
    }
  }

  /**
   * 全選択/全解除の切り替え
   */
  toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllFiles');
    const checkboxes = document.querySelectorAll('.file-checkbox');
    
    checkboxes.forEach(checkbox => {
      checkbox.checked = selectAllCheckbox.checked;
    });
    
    this.updateBulkActions();
  }

  /**
   * 選択されたファイルIDを取得
   */
  getSelectedFileIds() {
    const checkedBoxes = document.querySelectorAll('.file-checkbox:checked');
    return Array.from(checkedBoxes).map(checkbox => parseInt(checkbox.value));
  }

  /**
   * データの統計情報を取得
   */
  getStats() {
    return {
      totalFiles: this.files.length,
      filteredFiles: this.filteredFiles.length,
      currentPage: this.currentPage,
      totalPages: Math.ceil(this.filteredFiles.length / this.itemsPerPage),
      itemsPerPage: this.itemsPerPage,
      searchQuery: this.searchQuery,
      sortBy: this.sortBy,
      viewMode: this.viewMode
    };
  }

  /**
   * 現在のページのファイルを取得
   */
  getCurrentPageFiles() {
    const startIndex = (this.currentPage - 1) * this.itemsPerPage;
    const endIndex = startIndex + this.itemsPerPage;
    return this.filteredFiles.slice(startIndex, endIndex);
  }
}

// エクスポート
window.FileManagerCore = FileManagerCore;