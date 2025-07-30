<?php

/**
 * RESTful APIレスポンス管理
 * 統一されたJSON形式でのレスポンス処理
 */

class ApiResponse
{
    /**
     * 成功レスポンスを送信
     * @param mixed $data レスポンスデータ
     * @param int $statusCode HTTPステータスコード（デフォルト: 200）
     */
    public function sendSuccess($data = null, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = array(
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        );
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * エラーレスポンスを送信
     * @param int $statusCode HTTPステータスコード
     * @param string $errorCode エラーコード
     * @param string $message エラーメッセージ
     * @param mixed $details 詳細情報（オプション）
     */
    public function sendError($statusCode, $errorCode, $message, $details = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = array(
            'success' => false,
            'error' => array(
                'code' => $errorCode,
                'message' => $message
            ),
            'timestamp' => date('c')
        );
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * バリデーションエラーレスポンスを送信
     * @param array $errors バリデーションエラーの配列
     */
    public function sendValidationError($errors) {
        $this->sendError(400, 'VALIDATION_ERROR', 'バリデーションエラーが発生しました', $errors);
    }
    
    /**
     * ページネーション付きレスポンスを送信
     * @param array $items データの配列
     * @param array $pagination ページネーション情報
     */
    public function sendPaginatedResponse($items, $pagination) {
        $data = array(
            'items' => $items,
            'pagination' => $pagination
        );
        
        $this->sendSuccess($data);
    }
    
    /**
     * アップロード結果レスポンスを送信
     * @param array $uploadResult アップロード結果
     */
    public function sendUploadResult($uploadResult) {
        if ($uploadResult['success']) {
            $this->sendSuccess($uploadResult, 201);
        } else {
            $this->sendError(400, 'UPLOAD_ERROR', 'アップロードに失敗しました', $uploadResult);
        }
    }
}

?>