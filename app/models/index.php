<?php

namespace PHPUploader\Model;

class Index
{
    public function index()
    {
        $config = new \PHPUploader\Config();
        $ret = $config->index();
        //配列キーが設定されている配列なら展開
        if (!is_null($ret)) {
            if (is_array($ret)) {
                extract($ret);
            }
        }

        //データベースの作成・オープン
        try {
            $db = new \PDO('sqlite:' . $ret['dbDirectoryPath'] . '/uploader.db');
        } catch (Exception $e) {
            $error = '500 - データベースの接続に失敗しました。';
            exit;
        }

        // デフォルトのフェッチモードを連想配列形式に設定
        // (毎回\PDO::FETCH_ASSOCを指定する必要が無くなる)
        $db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // 選択 (プリペアドステートメント)
        $stmt = $db->prepare('SELECT * FROM uploaded');
        $stmt->execute();
        $r = $stmt->fetchAll();

        return [
            'data' => $r,
        ];
    }
}
