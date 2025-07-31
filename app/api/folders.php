<?php

// フォルダ構造管理API
header('Content-Type: application/json; charset=utf-8');

// configをインクルード
include_once('../../config/config.php');
$config = new config();
$ret = $config->index();
// 配列キーが設定されている配列なら展開
if (!is_null($ret)) {
    if (is_array($ret)) {
        extract($ret);
    }
}

// フォルダ機能が無効な場合はエラーを返す
if (!isset($folders_enabled) || !$folders_enabled) {
    http_response_code(403);
    echo json_encode(['error' => 'フォルダ機能が無効です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// データベースの作成・オープン
try {
    $db = new PDO('sqlite:' . $db_directory . '/uploader.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベース接続エラー'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetFolders($db);
        break;
    case 'POST':
        handleCreateFolder($db, $max_folder_depth, $max_folders_per_level, $allow_folder_creation);
        break;
    case 'PUT':
        handleUpdateFolder($db);
        break;
    case 'DELETE':
        handleDeleteFolder($db, $allow_folder_deletion);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'メソッドが許可されていません'], JSON_UNESCAPED_UNICODE);
        break;
}

/**
 * フォルダ一覧取得（階層構造）
 */
function handleGetFolders($db) {
    try {
        // 全フォルダを取得してツリー構造に変換
        $stmt = $db->prepare("SELECT id, name, parent_id, created_at FROM folders ORDER BY parent_id, name");
        $stmt->execute();
        $folders = $stmt->fetchAll();
        
        // ツリー構造に変換
        $tree = buildFolderTree($folders);
        
        echo json_encode(['folders' => $tree], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'フォルダ一覧の取得に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * フォルダ作成
 */
function handleCreateFolder($db, $max_depth, $max_per_level, $allow_creation) {
    if (!$allow_creation) {
        http_response_code(403);
        echo json_encode(['error' => 'フォルダ作成が許可されていません'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name']) || trim($input['name']) === '') {
        http_response_code(400);
        echo json_encode(['error' => 'フォルダ名が必要です'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $name = trim($input['name']);
    $parent_id = isset($input['parent_id']) ? intval($input['parent_id']) : null;
    
    // フォルダ名のバリデーション
    if (strlen($name) > 50) {
        http_response_code(400);
        echo json_encode(['error' => 'フォルダ名は50文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 不正な文字のチェック
    if (preg_match('/[\\\\\/\:*?"<>|]/', $name)) {
        http_response_code(400);
        echo json_encode(['error' => 'フォルダ名に使用できない文字が含まれています'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // 親フォルダの存在確認
        if ($parent_id !== null) {
            $stmt = $db->prepare("SELECT id FROM folders WHERE id = ?");
            $stmt->execute([$parent_id]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => '親フォルダが存在しません'], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            // 階層深さのチェック
            $depth = getFolderDepth($db, $parent_id) + 1;
            if ($depth > $max_depth) {
                http_response_code(400);
                echo json_encode(['error' => "フォルダの階層深さが最大値({$max_depth})を超えています"], JSON_UNESCAPED_UNICODE);
                return;
            }
        }
        
        // 同じ親での同名フォルダの重複チェック
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM folders WHERE name = ? AND parent_id " . ($parent_id === null ? "IS NULL" : "= ?"));
        $params = [$name];
        if ($parent_id !== null) {
            $params[] = $parent_id;
        }
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => '同じ名前のフォルダが既に存在します'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 同じ階層のフォルダ数チェック
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM folders WHERE parent_id " . ($parent_id === null ? "IS NULL" : "= ?"));
        $params = $parent_id !== null ? [$parent_id] : [];
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($result['count'] >= $max_per_level) {
            http_response_code(400);
            echo json_encode(['error' => "1つの階層に作成できるフォルダ数は最大{$max_per_level}個です"], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // フォルダ作成
        $stmt = $db->prepare("INSERT INTO folders (name, parent_id, created_at) VALUES (?, ?, ?)");
        $stmt->execute([$name, $parent_id, time()]);
        
        $folder_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'folder' => [
                'id' => $folder_id,
                'name' => $name,
                'parent_id' => $parent_id,
                'created_at' => time()
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'フォルダの作成に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * フォルダ更新（名前変更・移動）
 */
function handleUpdateFolder($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'フォルダIDが必要です'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $id = intval($input['id']);
    
    // 名前変更の場合
    if (isset($input['name'])) {
        $name = trim($input['name']);
        return handleRenameFolder($db, $id, $name);
    }
    
    // 移動の場合
    if (isset($input['parent_id'])) {
        $parent_id = $input['parent_id'] === null ? null : intval($input['parent_id']);
        return handleMoveFolder($db, $id, $parent_id);
    }
    
    http_response_code(400);
    echo json_encode(['error' => '名前または移動先の指定が必要です'], JSON_UNESCAPED_UNICODE);
}

/**
 * フォルダ名変更
 */
function handleRenameFolder($db, $id, $name) {
    
    // フォルダ名のバリデーション（createと同じ）
    if (strlen($name) > 50) {
        http_response_code(400);
        echo json_encode(['error' => 'フォルダ名は50文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if (preg_match('/[\\\\\/\:*?"<>|]/', $name)) {
        http_response_code(400);
        echo json_encode(['error' => 'フォルダ名に使用できない文字が含まれています'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // フォルダの存在確認
        $stmt = $db->prepare("SELECT parent_id FROM folders WHERE id = ?");
        $stmt->execute([$id]);
        $folder = $stmt->fetch();
        
        if (!$folder) {
            http_response_code(404);
            echo json_encode(['error' => 'フォルダが見つかりません'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 同じ親での同名フォルダの重複チェック
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM folders WHERE name = ? AND parent_id " . ($folder['parent_id'] === null ? "IS NULL" : "= ?") . " AND id != ?");
        $params = [$name];
        if ($folder['parent_id'] !== null) {
            $params[] = $folder['parent_id'];
        }
        $params[] = $id;
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => '同じ名前のフォルダが既に存在します'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // フォルダ名を更新
        $stmt = $db->prepare("UPDATE folders SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        
        echo json_encode([
            'success' => true,
            'folder' => [
                'id' => $id,
                'name' => $name,
                'parent_id' => $folder['parent_id']
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'フォルダ名の変更に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * フォルダ移動
 */
function handleMoveFolder($db, $id, $new_parent_id) {
    try {
        // フォルダの存在確認
        $stmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ?");
        $stmt->execute([$id]);
        $folder = $stmt->fetch();
        
        if (!$folder) {
            http_response_code(404);
            echo json_encode(['error' => 'フォルダが見つかりません'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 移動先フォルダの存在確認（nullでない場合）
        if ($new_parent_id !== null) {
            $stmt = $db->prepare("SELECT id FROM folders WHERE id = ?");
            $stmt->execute([$new_parent_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => '移動先フォルダが見つかりません'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }
        
        // 循環参照チェック（自分の子孫フォルダに移動しようとしていないか）
        if ($new_parent_id !== null && isDescendant($db, $id, $new_parent_id)) {
            http_response_code(400);
            echo json_encode(['error' => '自分の子フォルダには移動できません'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 同名フォルダのチェック
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM folders WHERE name = ? AND parent_id " . ($new_parent_id === null ? "IS NULL" : "= ?") . " AND id != ?");
        $params = [$folder['name']];
        if ($new_parent_id !== null) {
            $params[] = $new_parent_id;
        }
        $params[] = $id;
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => '移動先に同じ名前のフォルダが既に存在します'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // フォルダを移動
        $stmt = $db->prepare("UPDATE folders SET parent_id = ? WHERE id = ?");
        $stmt->execute([$new_parent_id, $id]);
        
        echo json_encode([
            'success' => true,
            'folder' => [
                'id' => $id,
                'name' => $folder['name'],
                'old_parent_id' => $folder['parent_id'],
                'new_parent_id' => $new_parent_id
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'フォルダの移動に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 循環参照チェック（フォルダAがフォルダBの子孫かどうか）
 */
function isDescendant($db, $ancestor_id, $descendant_id) {
    $stmt = $db->prepare("SELECT parent_id FROM folders WHERE id = ?");
    $stmt->execute([$descendant_id]);
    $result = $stmt->fetch();
    
    if (!$result || $result['parent_id'] === null) {
        return false;
    }
    
    if ($result['parent_id'] == $ancestor_id) {
        return true;
    }
    
    return isDescendant($db, $ancestor_id, $result['parent_id']);
}

/**
 * フォルダ削除
 */
function handleDeleteFolder($db, $allow_deletion) {
    if (!$allow_deletion) {
        http_response_code(403);
        echo json_encode(['error' => 'フォルダ削除が許可されていません'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => '有効なフォルダIDが必要です'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        // フォルダの存在確認
        $stmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ?");
        $stmt->execute([$id]);
        $folder = $stmt->fetch();
        
        if (!$folder) {
            http_response_code(404);
            echo json_encode(['error' => 'フォルダが見つかりません'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 真のルートフォルダ（初期作成されたルートフォルダ）の削除を防ぐ
        // parent_idがnullで、名前が「ルート」のフォルダのみ削除禁止
        if ($folder['parent_id'] === null && $folder['name'] === 'ルート') {
            http_response_code(400);
            echo json_encode(['error' => 'システムのルートフォルダは削除できません'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 子フォルダの存在確認
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM folders WHERE parent_id = ?");
        $stmt->execute([$id]);
        $childCount = $stmt->fetch()['count'];
        
        // フォルダ内のファイル数確認
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM uploaded WHERE folder_id = ?");
        $stmt->execute([$id]);
        $fileCount = $stmt->fetch()['count'];
        
        // チェック専用リクエストの場合は情報を返すだけ
        if (isset($_GET['check']) && $_GET['check'] === 'true') {
            echo json_encode([
                'success' => true,
                'file_count' => $fileCount,
                'child_count' => $childCount
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $moveFiles = isset($_GET['move_files']) && $_GET['move_files'] === 'true';
        $movedFiles = 0;
        
        if (($childCount > 0 || $fileCount > 0) && !$moveFiles) {
            http_response_code(400);
            echo json_encode([
                'error' => 'フォルダが空でないため削除できません',
                'details' => [
                    'child_folders' => $childCount,
                    'files' => $fileCount
                ]
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // ファイル移動オプションが有効な場合、ファイルをルートに移動
        if ($moveFiles && $fileCount > 0) {
            $stmt = $db->prepare("UPDATE uploaded SET folder_id = NULL WHERE folder_id = ?");
            $stmt->execute([$id]);
            $movedFiles = $fileCount;
        }
        
        // 子フォルダもルートに移動（再帰的な削除は行わない）
        if ($moveFiles && $childCount > 0) {
            $stmt = $db->prepare("UPDATE folders SET parent_id = NULL WHERE parent_id = ?");
            $stmt->execute([$id]);
        }
        
        // フォルダ削除
        $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'フォルダを削除しました',
            'moved_files' => $movedFiles,
            'moved_folders' => $moveFiles ? $childCount : 0
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'フォルダの削除に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * フォルダの階層深さを取得
 */
function getFolderDepth($db, $folder_id, $depth = 0) {
    if ($folder_id === null) {
        return $depth;
    }
    
    $stmt = $db->prepare("SELECT parent_id FROM folders WHERE id = ?");
    $stmt->execute([$folder_id]);
    $folder = $stmt->fetch();
    
    if (!$folder) {
        return $depth;
    }
    
    return getFolderDepth($db, $folder['parent_id'], $depth + 1);
}

/**
 * フォルダリストをツリー構造に変換
 */
function buildFolderTree($folders, $parent_id = null) {
    $tree = [];
    
    foreach ($folders as $folder) {
        if ($folder['parent_id'] == $parent_id) {
            $folder['children'] = buildFolderTree($folders, $folder['id']);
            $tree[] = $folder;
        }
    }
    
    return $tree;
}

?>