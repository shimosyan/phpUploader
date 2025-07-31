<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Core/Utils.php';

/**
 * ファイルAPI操作ハンドラー
 * ファイルの CRUD 操作を担当
 */
class FileApiHandler
{
    private array $config;
    private $auth;
    private ResponseHandler $response;

    public function __construct($config, $auth, ResponseHandler $response)
    {
        $this->config = $config;
        $this->auth = $auth;
        $this->response = $response;
    }

    /**
     * ファイル一覧取得
     */
    public function handleGetFiles(): void
    {
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
                           comment, size as file_size, 'application/octet-stream' as mime_type, 
                           input_date as upload_date, \"count\" as download_count, folder_id 
                    FROM uploaded WHERE 1=1";
            $params = array();
            
            if ($folder !== null) {
                $sql .= " AND folder_id = ?";
                $params[] = $folder;
            }

            // LIMIT / OFFSET はSQLiteではプレースホルダーを使用できないため数値を直接埋め込む
            $limit  = (int) $limit;
            $offset = (int) $offset;
            $sql .= " ORDER BY input_date DESC LIMIT {$limit} OFFSET {$offset}";

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
            $this->response->error('データベースエラー', [], 500, 'DATABASE_ERROR');
        }
    }

    /**
     * ファイルアップロード
     */
    public function handlePostFile(): void
    {
        // 既存のupload.phpの機能を活用
        // JSONレスポンス形式に変更
        ob_start();
        include 'upload.php';
        $output = ob_get_clean();
        
        // 既存のoutputがJSONかどうかチェックして適切に処理
        $decoded = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // 既にJSONの場合はそのまま出力
            header('Content-Type: application/json; charset=utf-8');
            echo $output;
        } else {
            // HTMLまたは他の形式の場合はJSONでラップ
            $this->response->success('ファイルアップロード処理完了', ['output' => $output]);
        }
    }

    /**
     * 単一ファイル取得
     */
    public function handleGetFile(int $fileId): void
    {
        try {
            // データベース接続パラメータの設定
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT id, origin_file_name as original_name, origin_file_name as filename, 
                                          comment, size as file_size, 'application/octet-stream' as mime_type, 
                                          input_date as upload_date, \"count\" as download_count, folder_id 
                                   FROM uploaded WHERE id = ?");
            $stmt->execute(array($fileId));
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                $this->response->error('ファイルが見つかりません', [], 404, 'FILE_NOT_FOUND');
                return;
            }
            
            $this->response->success('ファイル情報を取得しました', ['file' => $file]);
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->error('データベースエラー', [], 500, 'DATABASE_ERROR');
        }
    }

    /**
     * ファイル削除
     */
    public function handleDeleteFile(int $fileId): void
    {
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
                $this->response->error('ファイルが見つかりません', [], 404, 'FILE_NOT_FOUND');
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
            
            $this->response->success('ファイルを削除しました', ['file_id' => $fileId]);
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->error('データベースエラー', [], 500, 'DATABASE_ERROR');
        }
    }

    /**
     * ファイル差し替え
     */
    public function handleReplaceFile(int $fileId): void
    {
        // CSRFトークン検証（CSRFトークンが送信された場合）
        $csrfToken = $_POST['csrf_token'] ?? null;
        if ($csrfToken) {
            if (!SecurityUtils::validateCSRFToken($csrfToken)) {
                $this->response->error('セキュリティトークンが無効です', [], 403, 'CSRF_TOKEN_INVALID');
                return;
            }
        }

        // 機能の有効性チェック
        if (!isset($this->config['allow_file_replace']) || !$this->config['allow_file_replace']) {
            $this->response->error('ファイル差し替え機能が無効です', [], 403, 'FILE_REPLACE_DISABLED');
            return;
        }

        // 管理者のみ許可設定のチェック
        if (isset($this->config['file_edit_admin_only']) && $this->config['file_edit_admin_only']) {
            if (!$this->auth->hasPermission('admin')) {
                $this->response->error('管理者権限が必要です', [], 403, 'ADMIN_REQUIRED');
                return;
            }
        }

        // ファイルアップロードチェック
        error_log('DEBUG: $_FILES = ' . print_r($_FILES, true));
        error_log('DEBUG: $_POST = ' . print_r($_POST, true));
        
        if (!isset($_FILES['file'])) {
            $this->response->error('ファイルが送信されていません', [], 400, 'FILE_UPLOAD_ERROR');
            return;
        }
        
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->response->error('ファイルのアップロードに失敗しました: エラーコード ' . $_FILES['file']['error'], [], 400, 'FILE_UPLOAD_ERROR');
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
                $this->response->error('ファイルが見つかりません', [], 404, 'FILE_NOT_FOUND');
                return;
            }

            // 差し替えキー認証
            $inputReplaceKey = $_POST['replacekey'] ?? '';
            if (empty($inputReplaceKey)) {
                $this->response->error('差し替えキーが必要です', [], 400, 'REPLACE_KEY_REQUIRED');
                return;
            }

            if (empty($existingFile['replace_key'])) {
                $this->response->error('このファイルには差し替えキーが設定されていません', [], 400, 'NO_REPLACE_KEY');
                return;
            }

            // 差し替えキーの検証
            $storedReplaceKey = openssl_decrypt($existingFile['replace_key'], 'aes-256-ecb', $this->config['key']);
            if ($inputReplaceKey !== $storedReplaceKey) {
                $this->response->error('差し替えキーが正しくありません', [], 403, 'INVALID_REPLACE_KEY');
                return;
            }

            // アップロードされたファイルの情報
            $newFileName = SecurityUtils::escapeHtml($_FILES['file']['name']);
            $fileSize = $_FILES['file']['size'];
            $tmpPath = $_FILES['file']['tmp_name'];

            // 拡張子チェック
            $ext = strtolower(pathinfo($newFileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->config['extension'])) {
                $this->response->error('許可されていない拡張子です', [], 400, 'INVALID_EXTENSION');
                return;
            }

            // ファイルサイズチェック
            if ($fileSize > $this->config['max_file_size'] * 1024 * 1024) {
                $this->response->error('ファイルサイズが制限を超えています', [], 400, 'FILE_TOO_LARGE');
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
            $oldFilePath = $data_directory . '/' . $existingFile['origin_file_name'];
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            // 新しいファイルを移動
            if (!move_uploaded_file($tmpPath, $newFilePath)) {
                $this->response->error('ファイルの保存に失敗しました', [], 500, 'FILE_SAVE_ERROR');
                return;
            }

            // データベース更新
            $stmt = $pdo->prepare("UPDATE uploaded SET origin_file_name = ?, size = ? WHERE id = ?");
            $stmt->execute(array(basename($newFilePath), $fileSize, $fileId));

            $this->response->success('ファイルを差し替えました', [
                'file_id' => $fileId,
                'new_filename' => $newFileName,
                'size' => $fileSize
            ]);

        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->error('データベースエラー', [], 500, 'DATABASE_ERROR');
        } catch (Exception $e) {
            error_log('File replace error: ' . $e->getMessage());
            $this->response->error('サーバー内部エラーが発生しました', [], 500, 'INTERNAL_ERROR');
        }
    }

    /**
     * ファイル情報更新（コメント編集）
     */
    public function handleUpdateFile(int $fileId): void
    {
        // 機能の有効性チェック
        if (!isset($this->config['allow_comment_edit']) || !$this->config['allow_comment_edit']) {
            $this->response->error('コメント編集機能が無効です', [], 403, 'COMMENT_EDIT_DISABLED');
            return;
        }

        // 管理者のみ許可設定のチェック
        if (isset($this->config['file_edit_admin_only']) && $this->config['file_edit_admin_only']) {
            if (!$this->auth->hasPermission('admin')) {
                $this->response->error('管理者権限が必要です', [], 403, 'ADMIN_REQUIRED');
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['comment'])) {
            $this->response->error('コメントが必要です', [], 400, 'COMMENT_REQUIRED');
            return;
        }

        $newComment = SecurityUtils::escapeHtml(trim($input['comment']));

        // コメント文字数チェック
        if (mb_strlen($newComment) > $this->config['max_comment']) {
            $this->response->error('コメントが長すぎます', [], 400, 'COMMENT_TOO_LONG');
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
                $this->response->error('ファイルが見つかりません', [], 404, 'FILE_NOT_FOUND');
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

            $this->response->success('コメントを更新しました', [
                'file_id' => $fileId,
                'new_comment' => $newComment
            ]);

        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->response->error('データベースエラー', [], 500, 'DATABASE_ERROR');
        } catch (Exception $e) {
            error_log('Comment update error: ' . $e->getMessage());
            $this->response->error('サーバー内部エラーが発生しました', [], 500, 'INTERNAL_ERROR');
        }
    }
}