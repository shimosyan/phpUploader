<?php

declare(strict_types=1);

namespace phpUploader\Core;

/**
 * レスポンスハンドラークラス
 * API応答を統一化
 */
class ResponseHandler
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * 成功応答を送信
     */
    public function success(string $message, array $data = []): void
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];

        $this->sendJson($response);
    }

    /**
     * エラー応答を送信
     */
    public function error(
        string $message,
        array $validationErrors = [],
        int $httpCode = 400,
        ?string $errorCode = null
    ): void {
        $response = [
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('c')
        ];

        if (!empty($validationErrors)) {
            $response['validation_errors'] = $validationErrors;
        }

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        http_response_code($httpCode);
        $this->sendJson($response);
    }

    /**
     * JSON応答を送信
     */
    private function sendJson(array $data): void
    {
        // 出力バッファをクリア
        if (ob_get_level()) {
            ob_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * リダイレクト
     */
    public function redirect(string $url, int $httpCode = 302): void
    {
        http_response_code($httpCode);
        header("Location: {$url}");
        exit;
    }
}
