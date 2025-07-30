/**
 * 新しいフォルダマネージャ機能
 * のじゃロリ娘（七瀬陽葵）作成
 * 
 * 既存のフォルダナビゲーションに統合された管理機能
 */

class SimpleFolderManager {
    constructor() {
        this.currentFolderId = null;
        this.init();
    }
    
    init() {
        // URL パラメータから現在のフォルダIDを取得
        const urlParams = new URLSearchParams(window.location.search);
        this.currentFolderId = urlParams.get('folder') || null;
        
        this.setupEventListeners();
        this.loadFolderOptions();
    }
    
    setupEventListeners() {
        // フォルダ作成ボタン
        const createBtn = document.getElementById('create-folder-btn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateFolderDialog());
        }
        
        // フォルダ管理メニュー
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('rename-folder')) {
                e.preventDefault();
                const folderId = e.target.dataset.folderId;
                this.showRenameFolderDialog(folderId);
            }
            
            if (e.target.classList.contains('move-folder')) {
                e.preventDefault();
                const folderId = e.target.dataset.folderId;
                this.showMoveFolderDialog(folderId);
            }
            
            if (e.target.classList.contains('delete-folder')) {
                e.preventDefault();
                const folderId = e.target.dataset.folderId;
                this.showDeleteFolderDialog(folderId);
            }
        });
    }
    
    // フォルダ選択プルダウンの選択肢を読み込み
    async loadFolderOptions() {
        try {
            const response = await fetch('./app/api/folders.php');
            if (!response.ok) throw new Error('フォルダ読み込み失敗');
            
            const data = await response.json();
            this.updateFolderSelect(data.folders || []);
        } catch (error) {
            console.error('フォルダ読み込みエラー:', error);
        }
    }
    
    // フォルダ選択プルダウンを更新
    updateFolderSelect(folders) {
        const folderSelect = document.getElementById('folder-select');
        if (!folderSelect) return;
        
        // 現在選択されている値を保持
        const currentValue = folderSelect.value;
        
        // プルダウンを再構築
        folderSelect.innerHTML = '<option value="">ルートフォルダ</option>';
        
        const addOptions = (folders, level = 0) => {
            folders.forEach(folder => {
                const option = document.createElement('option');
                option.value = folder.id;
                option.textContent = '　'.repeat(level) + folder.name;
                folderSelect.appendChild(option);
                
                if (folder.children && folder.children.length > 0) {
                    addOptions(folder.children, level + 1);
                }
            });
        };
        
        addOptions(folders);
        
        // 値を復元
        folderSelect.value = currentValue;
    }
    
    // フォルダ作成ダイアログ
    showCreateFolderDialog() {
        const folderName = prompt('新しいフォルダ名を入力してください:');
        if (!folderName || !folderName.trim()) return;
        
        this.createFolder(folderName.trim(), this.currentFolderId);
    }
    
    // フォルダ作成
    async createFolder(name, parentId = null) {
        try {
            const response = await fetch('./app/api/folders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name, parent_id: parentId })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'フォルダ作成に失敗しました');
            }
            
            alert('フォルダを作成しました: ' + name);
            // ページを再読み込み
            window.location.href = window.location.href;
            
        } catch (error) {
            console.error('フォルダ作成エラー:', error);
            alert('エラー: ' + error.message);
        }
    }
    
    // フォルダ名変更ダイアログ
    showRenameFolderDialog(folderId) {
        const folderElement = document.querySelector(`[data-folder-id="${folderId}"] .folder-item`);
        const currentName = folderElement ? folderElement.textContent.trim().replace('📁', '').trim() : '';
        
        const newName = prompt('新しいフォルダ名を入力してください:', currentName);
        if (!newName || !newName.trim() || newName.trim() === currentName) return;
        
        this.renameFolder(folderId, newName.trim());
    }
    
    // フォルダ名変更
    async renameFolder(folderId, newName) {
        try {
            const response = await fetch('./app/api/folders.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: folderId, name: newName })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'フォルダ名変更に失敗しました');
            }
            
            alert('フォルダ名を変更しました: ' + newName);
            // 名前変更後は現在のページを適切にリロード
            window.location.href = window.location.href;
            
        } catch (error) {
            console.error('フォルダ名変更エラー:', error);
            alert('エラー: ' + error.message);
        }
    }
    
    // フォルダ移動ダイアログ
    async showMoveFolderDialog(folderId) {
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
                    if (folder.id != folderId) { // 自分自身は除外
                        options += '　'.repeat(level) + `${folder.id}: ${folder.name}\n`;
                    }
                    if (folder.children && folder.children.length > 0) {
                        addFolderOptions(folder.children, level + 1);
                    }
                });
            };
            addFolderOptions(folders);
            
            const targetId = prompt(options + '\n移動先のフォルダIDを入力してください:');
            if (targetId === null) return; // キャンセル
            
            const parentId = targetId.toLowerCase() === 'root' ? null : parseInt(targetId) || null;
            this.moveFolder(folderId, parentId);
            
        } catch (error) {
            console.error('フォルダ移動ダイアログエラー:', error);
            alert('エラー: ' + error.message);
        }
    }
    
    // フォルダ移動
    async moveFolder(folderId, newParentId) {
        try {
            const response = await fetch('./app/api/folders.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: folderId, parent_id: newParentId })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'フォルダ移動に失敗しました');
            }
            
            alert('フォルダを移動しました');
            // 移動後は現在のページを適切にリロード
            window.location.href = window.location.href;
            
        } catch (error) {
            console.error('フォルダ移動エラー:', error);
            alert('エラー: ' + error.message);
        }
    }
    
    // フォルダ削除確認ダイアログ
    showDeleteFolderDialog(folderId) {
        const folderElement = document.querySelector(`[data-folder-id="${folderId}"] .folder-item`);
        const folderName = folderElement ? folderElement.textContent.trim().replace('📁', '').trim() : 'フォルダ';
        
        if (!confirm(`フォルダ「${folderName}」を削除しますか？\n\n注意: 空のフォルダのみ削除できます。`)) return;
        
        this.deleteFolder(folderId);
    }
    
    // フォルダ削除
    async deleteFolder(folderId) {
        try {
            const response = await fetch(`./app/api/folders.php?id=${folderId}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'フォルダ削除に失敗しました');
            }
            
            alert('フォルダを削除しました');
            // 削除後は現在のページを適切にリロード
            window.location.href = window.location.href;
            
        } catch (error) {
            console.error('フォルダ削除エラー:', error);
            alert('エラー: ' + error.message);
        }
    }
}

// DOMContentLoaded時に初期化
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('folder-grid') || document.getElementById('folder-select')) {
        window.folderManager = new SimpleFolderManager();
    }
});

// グローバルファイル移動関数
async function moveFile(fileId) {
    if (!window.folderManager) {
        alert('フォルダマネージャが初期化されていません');
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
        
        const targetId = prompt(options + '\n移動先のフォルダIDを入力してください:');
        if (targetId === null) return; // キャンセル
        
        const folderId = targetId.toLowerCase() === 'root' ? null : parseInt(targetId) || null;
        
        // ファイル移動API呼び出し
        const moveResponse = await fetch('./app/api/move-file.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: fileId, folder_id: folderId })
        });
        
        const moveData = await moveResponse.json();
        
        if (!moveResponse.ok) {
            throw new Error(moveData.error || 'ファイル移動に失敗しました');
        }
        
        alert('ファイルを移動しました');
        location.reload();
        
    } catch (error) {
        console.error('ファイル移動エラー:', error);
        alert('エラー: ' + error.message);
    }
}