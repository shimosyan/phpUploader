<?php

/**
 * RESTful APIルーター
 * 統一されたAPIエンドポイント管理
 */

require_once 'auth.php';
require_once 'response.php';

class ApiRouter {
    private $config;
    private $auth;
    private $response;
    private $routes = array();

    public function __construct($config) {
        $this->config = $config;
        $this->auth = new ApiAuth($config);
        $this->response = new ApiResponse();
        $this->setupRoutes();
    }

    /**
     * APIルートの設定
     */
    private function setupRoutes() {
        // ファイル操作エンドポイント
        $this->addRoute('GET', '/api/files', 'handleGetFiles', array('read'));
        $this->addRoute('POST', '/api/files', 'handlePostFile', array('write'));
        $this->addRoute('GET', '/api/files/(\d+)', 'handleGetFile', array('read'));
        $this->addRoute('DELETE', '/api/files/(\d+)', 'handleDeleteFile', array('delete'));
        $this->addRoute('PUT', '/api/files/(\d+)', 'handleReplaceFile', array('write'));
        $this->addRoute('POST', '/api/files/(\d+)/replace', 'handleReplaceFile', array('write'));
        $this->addRoute('PATCH', '/api/files/(\d+)', 'handleUpdateFile', array('write'));
        
        // フォルダ操作エンドポイント
        $this->addRoute('GET', '/api/folders', 'handleGetFolders', array('read'));
        $this->addRoute('POST', '/api/folders', 'handlePostFolder', array('write'));
        $this->addRoute('DELETE', '/api/folders/(\d+)', 'handleDeleteFolder', array('delete'));
        
        // システム情報エンドポイント
        $this->addRoute('GET', '/api/status', 'handleGetStatus', array('read'));
    }

    /**
     * ルートを追加
     */
    private function addRoute($method, $pattern, $handler, $permissions = array()) {
        $this->routes[] = array(
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'permissions' => $permissions
        );
    }

    /**
     * リクエストを処理
     */
    public function handleRequest() {
        try {
            // 認証
            if (!$this->auth->authenticate()) {
                return; // 認証失敗時はauth.phpでレスポンス済み
            }

            // ルートマッチング
            $method = $_SERVER['REQUEST_METHOD'];
            $requestUri = $_SERVER['REQUEST_URI'];
            
            // PHP内蔵サーバー対応: /app/api/index.php?path=/status のような形式も処理
            if (isset($_GET['path'])) {
                $path = $_GET['path'];
            } else {
                $path = parse_url($requestUri, PHP_URL_PATH);
                // /app/api/index.php の場合は /api/status に変換
                if (strpos($path, '/app/api/index.php') !== false) {
                    $path = '/api/status'; // デフォルトでstatusエンドポイントをテスト
                }
            }
            
            error_log("API Router Debug - Method: $method, Original URI: $requestUri, Parsed Path: $path");
            
            foreach ($this->routes as $route) {
                if ($route['method'] !== $method) {
                    continue;
                }
                
                if (preg_match('#^' . $route['pattern'] . '$#', $path, $matches)) {
                    // 権限チェック
                    foreach ($route['permissions'] as $permission) {
                        if (!$this->auth->hasPermission($permission)) {
                            $this->response->sendError(403, 'PERMISSION_DENIED', '権限が不足しています: ' . $permission);
                            return;
                        }
                    }
                    
                    // ハンドラー実行
                    array_shift($matches); // 完全マッチを除去
                    call_user_func_array(array($this, $route['handler']), $matches);
                    return;
                }
            }
            
            // マッチするルートが見つからない
            $this->response->sendError(404, 'ENDPOINT_NOT_FOUND', 'エンドポイントが見つかりません');
            
        } catch (Exception $e) {
            error_log('API Error: ' . $e->getMessage());
            $this->response->sendError(500, 'INTERNAL_ERROR', 'サーバー内部エラーが発生しました');
        }
    }

    /**
     * ファイル一覧取得
     */
    private function handleGetFiles() {
        require_once '../models/init.php';
        
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
        $folder = isset($_GET['folder']) ? intval($_GET['folder']) : null;
        
        $offset = ($page - 1) * $limit;
        
        try {
            // データベース接続パラメータの設定
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $sql = "SELECT id, origin_file_name as original_name, origin_file_name as filename, 
                           comment, dl_key as password_dl, del_key as password_del, 
                           size as file_size, 'application/octet-stream' as mime_type, 
                           input_date as upload_date, count as download_count, folder_id 
                    FROM uploaded WHERE 1=1";
            $params = array();
            
            if ($folder !== null) {
                $sql .= " AND folder_id = ?";
                $params[] = $folder;
            }
            
            $sql .= " ORDER BY upload_date DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 総件数取得
            $countSql = "SELECT COUNT(*) FROM uploaded WHERE 1=1";
            $countParams = array();
            if ($folder !== null) {
                $countSql .= " AND folder_id = ?";
                $countParams[] = $folder;
            }
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();
            
            // 成功レスポンスを直接送信
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => true,
                'data' => array(
                    'files' => $files,
                    'pagination' => array(
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit)
                    )
                ),
                'timestamp' => date('c')
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->sendError(500, 'DATABASE_ERROR', 'データベースエラーが発生しました');
        }
    }

    /**
     * ファイルアップロード
     */
    private function handlePostFile() {
        // 既存のupload.phpの機能を活用
        // JSONレスポンス形式に変更
        ob_start();
        include 'upload.php';
        $output = ob_get_clean();
        
        // 既存のoutputがJSONかどうかチェックして適切に処理
        $decoded = json_decode($output, true);
        if ($decoded !== null) {
            echo $output;
        } else {
            $this->response->sendError(500, 'UPLOAD_ERROR', 'アップロードに失敗しました');
        }
    }

    /**
     * ファイル情報取得
     */
    private function handleGetFile($fileId) {
        try {
            // データベース接続パラメータの設定
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT id, origin_file_name as original_name, origin_file_name as filename, 
                                          comment, dl_key as password_dl, del_key as password_del, 
                                          size as file_size, 'application/octet-stream' as mime_type, 
                                          input_date as upload_date, count as download_count, folder_id 
                                   FROM uploaded WHERE id = ?");
            $stmt->execute(array($fileId));
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                $this->response->sendError(404, 'FILE_NOT_FOUND', 'ファイルが見つかりません');
                return;
            }
            
            $this->response->sendSuccess(array('file' => $file));
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->sendError(500, 'DATABASE_ERROR', 'データベースエラーが発生しました');
        }
    }

    /**
     * ファイル削除
     */
    private function handleDeleteFile($fileId) {
        try {
            // データベース接続パラメータの設定
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // ファイル情報取得
            $stmt = $pdo->prepare("SELECT origin_file_name as filename FROM uploaded WHERE id = ?");
            $stmt->execute(array($fileId));
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                $this->response->sendError(404, 'FILE_NOT_FOUND', 'ファイルが見つかりません');
                return;
            }
            
            // 物理ファイル削除
            $filePath = '../../data/' . $file['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // データベースから削除
            $stmt = $pdo->prepare("DELETE FROM uploaded WHERE id = ?");
            $stmt->execute(array($fileId));
            
            $this->response->sendSuccess(array('message' => 'ファイルを削除しました'));
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->sendError(500, 'DATABASE_ERROR', 'データベースエラーが発生しました');
        }
    }

    /**
     * フォルダ一覧取得
     */
    private function handleGetFolders() {
        if (!$this->config['folders_enabled']) {
            $this->response->sendError(503, 'FOLDERS_DISABLED', 'フォルダ機能が無効です');
            return;
        }
        
        try {
            // データベース接続パラメータの設定
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT id, name, parent_id, created_at FROM folders ORDER BY parent_id, name");
            $stmt->execute();
            $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->response->sendSuccess(array('folders' => $folders));
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->sendError(500, 'DATABASE_ERROR', 'データベースエラーが発生しました');
        }
    }

    /**
     * フォルダ作成
     */
    private function handlePostFolder() {
        if (!$this->config['folders_enabled'] || !$this->config['allow_folder_creation']) {
            $this->response->sendError(403, 'FOLDER_CREATION_DISABLED', 'フォルダ作成が無効です');
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $name = isset($input['name']) ? trim($input['name']) : '';
        $parentId = isset($input['parent_id']) ? intval($input['parent_id']) : null;
        
        if (empty($name)) {
            $this->response->sendError(400, 'FOLDER_NAME_REQUIRED', 'フォルダ名が必要です');
            return;
        }
        
        try {
            // データベース接続パラメータの設定
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("INSERT INTO folders (name, parent_id, created_at) VALUES (?, ?, ?)");
            $stmt->execute(array($name, $parentId, time()));
            
            $folderId = $pdo->lastInsertId();
            
            $this->response->sendSuccess(array(
                'message' => 'フォルダを作成しました',
                'folder_id' => $folderId
            ));
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->sendError(500, 'DATABASE_ERROR', 'データベースエラーが発生しました');
        }
    }

    /**
     * フォルダ削除
     */
    private function handleDeleteFolder($folderId) {
        if (!$this->config['folders_enabled'] || !$this->config['allow_folder_deletion']) {
            $this->response->sendError(403, 'FOLDER_DELETION_DISABLED', 'フォルダ削除が無効です');
            return;
        }
        
        try {
            // データベース接続パラメータの設定
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // フォルダ内にファイルがあるかチェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uploaded WHERE folder_id = ?");
            $stmt->execute(array($folderId));
            $fileCount = $stmt->fetchColumn();
            
            if ($fileCount > 0) {
                $this->response->sendError(409, 'FOLDER_NOT_EMPTY', 'フォルダが空ではありません');
                return;
            }
            
            // 子フォルダがあるかチェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM folders WHERE parent_id = ?");
            $stmt->execute(array($folderId));
            $childCount = $stmt->fetchColumn();
            
            if ($childCount > 0) {
                $this->response->sendError(409, 'FOLDER_HAS_CHILDREN', 'フォルダに子フォルダが存在します');
                return;
            }
            
            // フォルダ削除
            $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ?");
            $stmt->execute(array($folderId));
            
            $this->response->sendSuccess(array('message' => 'フォルダを削除しました'));
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->sendError(500, 'DATABASE_ERROR', 'データベースエラーが発生しました');
        }
    }

    /**
     * システム状態取得
     */
    private function handleGetStatus() {
        $this->response->sendSuccess(array(
            'status' => 'ok',
            'version' => '1.0.0',
            'api_enabled' => $this->config['api_enabled'],
            'folders_enabled' => $this->config['folders_enabled'],
            'server_time' => date('c')
        ));
    }

    /**
     * ファイル差し替え処理
     */
    private function handleReplaceFile($fileId) {
        // 機能の有効性チェック
        if (!isset($this->config['allow_file_replace']) || !$this->config['allow_file_replace']) {
            $this->response->sendError(403, 'FILE_REPLACE_DISABLED', 'ファイル差し替え機能が無効です');
            return;
        }

        // 管理者のみ許可設定のチェック
        if (isset($this->config['file_edit_admin_only']) && $this->config['file_edit_admin_only']) {
            if (!$this->auth->hasPermission('admin')) {
                $this->response->sendError(403, 'ADMIN_REQUIRED', '管理者権限が必要です');
                return;
            }
        }

        // ファイルアップロードチェック
        error_log('DEBUG: $_FILES = ' . print_r($_FILES, true));
        error_log('DEBUG: $_POST = ' . print_r($_POST, true));
        
        if (!isset($_FILES['file'])) {
            $this->response->sendError(400, 'FILE_UPLOAD_ERROR', 'ファイルが送信されていません');
            return;
        }
        
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->response->sendError(400, 'FILE_UPLOAD_ERROR', 'ファイルのアップロードに失敗しました: エラーコード ' . $_FILES['file']['error']);
            return;
        }

        try {
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // 既存ファイル情報取得
            $stmt = $pdo->prepare("SELECT * FROM uploaded WHERE id = ?");
            $stmt->execute(array($fileId));
            $existingFile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingFile) {
                $this->response->sendError(404, 'FILE_NOT_FOUND', 'ファイルが見つかりません');
                return;
            }

            // 差し替えキー認証
            $inputReplaceKey = $_POST['replacekey'] ?? '';
            if (empty($inputReplaceKey)) {
                $this->response->sendError(400, 'REPLACE_KEY_REQUIRED', '差し替えキーが必要です');
                return;
            }

            if (empty($existingFile['replace_key'])) {
                $this->response->sendError(400, 'NO_REPLACE_KEY', 'このファイルには差し替えキーが設定されていません');
                return;
            }

            // 差し替えキーの検証
            $storedReplaceKey = openssl_decrypt($existingFile['replace_key'], 'aes-256-ecb', $this->config['key']);
            if ($inputReplaceKey !== $storedReplaceKey) {
                $this->response->sendError(403, 'INVALID_REPLACE_KEY', '差し替えキーが正しくありません');
                return;
            }

            // アップロードされたファイルの情報
            $newFileName = htmlspecialchars($_FILES['file']['name'], ENT_QUOTES, 'UTF-8');
            $fileSize = $_FILES['file']['size'];
            $tmpPath = $_FILES['file']['tmp_name'];

            // 拡張子チェック
            $ext = strtolower(pathinfo($newFileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->config['extension'])) {
                $this->response->sendError(400, 'INVALID_EXTENSION', '許可されていない拡張子です');
                return;
            }

            // ファイルサイズチェック
            if ($fileSize > $this->config['max_file_size'] * 1024 * 1024) {
                $this->response->sendError(400, 'FILE_TOO_LARGE', 'ファイルサイズが制限を超えています');
                return;
            }

            // 新しいファイルパスを決定
            $data_directory = '../../data';
            if (isset($this->config['encrypt_filename']) && $this->config['encrypt_filename']) {
                $newFilePath = $data_directory . '/file_' . str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', 
                    openssl_encrypt($fileId, 'aes-256-ecb', $this->config['key'])) . '.' . $ext;
            } else {
                $newFilePath = $data_directory . '/file_' . $fileId . '.' . $ext;
            }

            // 古いファイルを削除
            $oldExt = strtolower(pathinfo($existingFile['origin_file_name'], PATHINFO_EXTENSION));
            if (isset($this->config['encrypt_filename']) && $this->config['encrypt_filename']) {
                $oldFilePath = $data_directory . '/file_' . str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', 
                    openssl_encrypt($fileId, 'aes-256-ecb', $this->config['key'])) . '.' . $oldExt;
            } else {
                $oldFilePath = $data_directory . '/file_' . $fileId . '.' . $oldExt;
            }

            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            // 新しいファイルを移動
            if (!move_uploaded_file($tmpPath, $newFilePath)) {
                $this->response->sendError(500, 'FILE_MOVE_ERROR', 'ファイルの移動に失敗しました');
                return;
            }

            // データベース更新
            $stmt = $pdo->prepare("UPDATE uploaded SET origin_file_name = ?, size = ? WHERE id = ?");
            $stmt->execute(array($newFileName, $fileSize, $fileId));

            // 履歴記録
            $stmt = $pdo->prepare("INSERT INTO file_history (file_id, old_filename, new_filename, change_type, changed_at, changed_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(array(
                $fileId,
                $existingFile['origin_file_name'],
                $newFileName,
                'file_replace',
                time(),
                $this->auth->getApiKey()
            ));

            $this->response->sendSuccess(array(
                'message' => 'ファイルを差し替えました',
                'file_id' => $fileId,
                'new_filename' => $newFileName
            ));

        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->sendError(500, 'DATABASE_ERROR', 'データベースエラーが発生しました');
        } catch (Exception $e) {
            error_log('File replace error: ' . $e->getMessage());
            $this->response->sendError(500, 'INTERNAL_ERROR', 'サーバー内部エラーが発生しました');
        }
    }

    /**
     * ファイル情報更新処理（コメント編集）
     */
    private function handleUpdateFile($fileId) {
        // 機能の有効性チェック
        if (!isset($this->config['allow_comment_edit']) || !$this->config['allow_comment_edit']) {
            $this->response->sendError(403, 'COMMENT_EDIT_DISABLED', 'コメント編集機能が無効です');
            return;
        }

        // 管理者のみ許可設定のチェック
        if (isset($this->config['file_edit_admin_only']) && $this->config['file_edit_admin_only']) {
            if (!$this->auth->hasPermission('admin')) {
                $this->response->sendError(403, 'ADMIN_REQUIRED', '管理者権限が必要です');
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['comment'])) {
            $this->response->sendError(400, 'COMMENT_REQUIRED', 'コメントが必要です');
            return;
        }

        $newComment = htmlspecialchars(trim($input['comment']), ENT_QUOTES, 'UTF-8');

        // コメント文字数チェック
        if (mb_strlen($newComment) > $this->config['max_comment']) {
            $this->response->sendError(400, 'COMMENT_TOO_LONG', 'コメントが長すぎます');
            return;
        }

        try {
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // 既存ファイル情報取得
            $stmt = $pdo->prepare("SELECT * FROM uploaded WHERE id = ?");
            $stmt->execute(array($fileId));
            $existingFile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingFile) {
                $this->response->sendError(404, 'FILE_NOT_FOUND', 'ファイルが見つかりません');
                return;
            }

            // コメント更新
            $stmt = $pdo->prepare("UPDATE uploaded SET comment = ? WHERE id = ?");
            $stmt->execute(array($newComment, $fileId));

            // 履歴記録（コメントが変更された場合のみ）
            if ($existingFile['comment'] !== $newComment) {
                $stmt = $pdo->prepare("INSERT INTO file_history (file_id, old_comment, new_comment, change_type, changed_at, changed_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute(array(
                    $fileId,
                    $existingFile['comment'],
                    $newComment,
                    'comment_edit',
                    time(),
                    $this->auth->getApiKey()
                ));
            }

            $this->response->sendSuccess(array(
                'message' => 'コメントを更新しました',
                'file_id' => $fileId,
                'new_comment' => $newComment
            ));

        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->sendError(500, 'DATABASE_ERROR', 'データベースエラーが発生しました');
        } catch (Exception $e) {
            error_log('Comment update error: ' . $e->getMessage());
            $this->response->sendError(500, 'INTERNAL_ERROR', 'サーバー内部エラーが発生しました');
        }
    }
}

?>