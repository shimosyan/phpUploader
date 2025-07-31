/**
 * FileManager ユーティリティクラス
 * ファイル拡張子、アイコン、HTMLエスケープなどの共通機能
 */
class FileManagerUtils {
  /**
   * ファイル拡張子を取得
   * @param {string} filename - ファイル名
   * @returns {string} - 拡張子
   */
  static getFileExtension(filename) {
    return filename.split('.').pop() || '';
  }
  
  /**
   * ファイルタイプに対応するアイコンを取得
   * @param {string} extension - ファイル拡張子
   * @returns {string} - 絵文字アイコン
   */
  static getFileIcon(extension) {
    const iconMap = {
      // 画像
      'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️', 'bmp': '🖼️', 'svg': '🖼️', 'webp': '🖼️',
      // 動画
      'mp4': '🎬', 'avi': '🎬', 'mov': '🎬', 'wmv': '🎬', 'flv': '🎬', 'webm': '🎬', 'mkv': '🎬',
      // 音声
      'mp3': '🎵', 'wav': '🎵', 'aac': '🎵', 'flac': '🎵', 'ogg': '🎵', 'm4a': '🎵',
      // ドキュメント
      'pdf': '📕', 'doc': '📄', 'docx': '📄', 'txt': '📝', 'rtf': '📄',
      'xls': '📊', 'xlsx': '📊', 'csv': '📊',
      'ppt': '📊', 'pptx': '📊',
      // アーカイブ
      'zip': '🗜️', 'rar': '🗜️', '7z': '🗜️', 'tar': '🗜️', 'gz': '🗜️',
      // コード
      'html': '🌐', 'css': '🎨', 'js': '⚡', 'php': '🐘', 'py': '🐍', 'java': '☕', 'cpp': '🔧', 'c': '🔧',
      // その他
      'exe': '⚙️', 'msi': '⚙️', 'dmg': '💽', 'iso': '💽'
    };
    
    return iconMap[extension.toLowerCase()] || '📄';
  }
  
  /**
   * HTMLエスケープ
   * @param {string} text - エスケープする文字列
   * @returns {string} - エスケープ済み文字列
   */
  static escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  /**
   * ファイルサイズを人間が読みやすい形式に変換
   * @param {number} bytes - バイト数
   * @returns {string} - フォーマット済みサイズ
   */
  static formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
  
  /**
   * 日付を相対時間で表示
   * @param {number} timestamp - UNIXタイムスタンプ
   * @returns {string} - 相対時間文字列
   */
  static formatRelativeTime(timestamp) {
    const now = Date.now() / 1000;
    const diff = now - timestamp;
    
    if (diff < 60) return '今';
    if (diff < 3600) return Math.floor(diff / 60) + '分前';
    if (diff < 86400) return Math.floor(diff / 3600) + '時間前';
    if (diff < 2592000) return Math.floor(diff / 86400) + '日前';
    if (diff < 31536000) return Math.floor(diff / 2592000) + 'ヶ月前';
    
    return Math.floor(diff / 31536000) + '年前';
  }
}

// エクスポート
window.FileManagerUtils = FileManagerUtils;