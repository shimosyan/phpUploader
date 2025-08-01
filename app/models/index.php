<?php

namespace phpUploader\Models;

use PDO;
use Exception;

class Index
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function index()
    {
        try {
            // データベースからアップロードされたファイル一覧を取得
            $stmt = $this->db->prepare("SELECT * FROM uploaded ORDER BY input_date DESC");
            $stmt->execute();
            $r = $stmt->fetchAll();

            return array('data' => $r);
        } catch (Exception $e) {
            // エラーログの記録
            error_log("Failed to fetch uploaded files: " . $e->getMessage());
            return array('data' => [], 'error' => 'ファイル一覧の取得に失敗しました。');
        }
    }
}
