/**
 * ファイル管理システム Ver.2.0 - リファクタリング版
 * 分離されたコンポーネントを統合した管理クラス
 * 
 * コンポーネント構成:
 * - FileManagerCore: データ管理とコア機能
 * - FileManagerRenderer: HTML生成とレンダリング
 * - FileManagerEvents: イベント処理
 * - FileManagerBulkActions: 一括操作
 * - FileManagerUtils: ユーティリティ機能
 */

class FileManager {
  constructor(container, options = {}) {
    // コアインスタンスを作成
    this.core = new FileManagerCore(container, options);
    
    // 各コンポーネントを初期化
    this.renderer = new FileManagerRenderer(this.core);
    this.events = new FileManagerEvents(this.core);
    this.bulkActions = new FileManagerBulkActions(this.core);
    
    // コアに依存コンポーネントを設定
    this.core.renderer = this.renderer;
    this.core.events = this.events;
    this.core.bulkActions = this.bulkActions;
    
    // 既存APIの互換性のためのプロパティエイリアス
    this.container = this.core.container;
    this.files = this.core.files;
    this.filteredFiles = this.core.filteredFiles;
    this.currentPage = this.core.currentPage;
    this.itemsPerPage = this.core.itemsPerPage;
    this.searchQuery = this.core.searchQuery;
    this.sortBy = this.core.sortBy;
    this.viewMode = this.core.viewMode;

    this.init();
  }

  // 既存APIとの互換性を保つメソッド群
  loadViewMode() {
    return this.core.loadViewMode();
  }

  init() {
    this.core.init();
  }

  setFiles(files) {
    this.core.setFiles(files);
    this.updateProperties();
  }

  applyFilters() {
    this.core.applyFilters();
    this.updateProperties();
  }

  render() {
    this.renderer.render();
    this.updateProperties();
  }

  // プロパティ同期ヘルパー
  updateProperties() {
    this.files = this.core.files;
    this.filteredFiles = this.core.filteredFiles;
    this.currentPage = this.core.currentPage;
    this.itemsPerPage = this.core.itemsPerPage;
    this.searchQuery = this.core.searchQuery;
    this.sortBy = this.core.sortBy;
    this.viewMode = this.core.viewMode;
  }

  // 既存APIとの互換性のためのメソッド委譲
  refresh() {
    this.core.refresh();
    this.updateProperties();
  }
  
  search(query) {
    this.core.search(query);
    this.updateProperties();
  }
  
  sort(sortBy) {
    this.core.sort(sortBy);
    this.updateProperties();
  }
  
  goToPage(page) {
    this.core.goToPage(page);
    this.updateProperties();
  }

  updateBulkActions() {
    this.core.updateBulkActions();
  }

  toggleSelectAll() {
    this.core.toggleSelectAll();
  }

  getSelectedFileIds() {
    return this.core.getSelectedFileIds();
  }

  // 一括操作メソッド
  async bulkMove() {
    await this.bulkActions.bulkMove();
  }

  async bulkDelete() {
    await this.bulkActions.bulkDelete();
  }

  // ユーティリティメソッド（後方互換性）
  getFileExtension(filename) {
    return FileManagerUtils.getFileExtension(filename);
  }

  getFileIcon(extension) {
    return FileManagerUtils.getFileIcon(extension);
  }

  escapeHtml(text) {
    return FileManagerUtils.escapeHtml(text);
  }

  // 統計情報の取得
  getStats() {
    return this.core.getStats();
  }
}

// グローバルに公開
window.FileManager = FileManager;