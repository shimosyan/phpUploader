/**
 * FileManager イベントクラス
 * 全てのユーザーインタラクションとイベント処理を担当
 */
class FileManagerEvents {
  constructor(coreInstance) {
    this.core = coreInstance;
  }

  /**
   * 全てのイベントをバインド
   */
  bindEvents() {
    // 検索イベント（デバウンス付き）
    let searchTimeout;
    this.core.container.addEventListener('input', (e) => {
      if (e.target.id === 'fileSearchInput') {
        clearTimeout(searchTimeout);
        this.core.searchQuery = e.target.value;
        
        // デバウンス処理（300ms）でパフォーマンス向上
        searchTimeout = setTimeout(() => {
          this.core.applyFilters();
          if (this.core.renderer) {
            this.core.renderer.render();
          }
        }, 300);
      }
    });
    
    // ソート・表示件数変更イベント
    this.core.container.addEventListener('change', (e) => {
      if (e.target.id === 'fileSortSelect') {
        this.core.sortBy = e.target.value;
        this.core.applyFilters();
        if (this.core.renderer) {
          this.core.renderer.render();
        }
      } else if (e.target.id === 'itemsPerPageSelect') {
        this.core.setItemsPerPage(parseInt(e.target.value));
      }
    });
    
    // クリック イベント
    this.core.container.addEventListener('click', (e) => {
      // 検索クリアボタン
      if (e.target.id === 'fileSearchClear') {
        this.core.searchQuery = '';
        this.core.applyFilters();
        if (this.core.renderer) {
          this.core.renderer.render();
        }
      } 
      // ビュー切り替えボタン
      else if (e.target.classList.contains('file-view-toggle__btn')) {
        const newView = e.target.dataset.view;
        if (newView && newView !== this.core.viewMode) {
          this.core.setViewMode(newView);
        }
      }
      // ページネーションボタン
      else if (e.target.classList.contains('file-pagination__btn') && !e.target.disabled) {
        const page = parseInt(e.target.dataset.page);
        if (page && page !== this.core.currentPage) {
          this.core.currentPage = page;
          if (this.core.renderer) {
            this.core.renderer.render();
          }
          // ページ変更時にトップへスクロール
          this.core.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    });

    // キーボードショートカット
    document.addEventListener('keydown', (e) => {
      // Ctrl+F または Cmd+F で検索にフォーカス
      if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('fileSearchInput');
        if (searchInput) {
          searchInput.focus();
          searchInput.select();
        }
      }
      
      // Escape で検索をクリア
      if (e.key === 'Escape') {
        const searchInput = document.getElementById('fileSearchInput');
        if (searchInput && document.activeElement === searchInput) {
          this.core.searchQuery = '';
          searchInput.value = '';
          this.core.applyFilters();
          if (this.core.renderer) {
            this.core.renderer.render();
          }
          searchInput.blur();
        }
      }

      // 左右矢印キーでページ移動
      if (e.key === 'ArrowLeft' && this.core.currentPage > 1) {
        this.core.goToPage(this.core.currentPage - 1);
      }
      if (e.key === 'ArrowRight') {
        const totalPages = Math.ceil(this.core.filteredFiles.length / this.core.itemsPerPage);
        if (this.core.currentPage < totalPages) {
          this.core.goToPage(this.core.currentPage + 1);
        }
      }
    });

    // ウィンドウリサイズ時の再描画
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        if (this.core.renderer) {
          this.core.renderer.render();
        }
      }, 250);
    });
  }

  /**
   * 特定の要素にフォーカスを設定
   */
  focusSearchInput() {
    const searchInput = document.getElementById('fileSearchInput');
    if (searchInput) {
      searchInput.focus();
      searchInput.select();
    }
  }

  /**
   * 検索をクリア
   */
  clearSearch() {
    this.core.searchQuery = '';
    const searchInput = document.getElementById('fileSearchInput');
    if (searchInput) {
      searchInput.value = '';
    }
    this.core.applyFilters();
    if (this.core.renderer) {
      this.core.renderer.render();
    }
  }

  /**
   * ビューモードを切り替え
   */
  toggleViewMode() {
    const newMode = this.core.viewMode === 'grid' ? 'list' : 'grid';
    this.core.setViewMode(newMode);
  }
}

// エクスポート
window.FileManagerEvents = FileManagerEvents;