# RESTful API ドキュメント

phpUploaderのRESTful API仕様書です。

## 認証

すべてのAPIエンドポイントにはAPIキーによる認証が必要です。

### 認証方法

以下のいずれかの方法でAPIキーを送信してください：

1. **Authorizationヘッダー** (推奨)
   ```
   Authorization: Bearer YOUR_API_KEY
   ```

2. **クエリパラメータ**
   ```
   GET /api/files?api_key=YOUR_API_KEY
   ```

3. **POSTパラメータ**
   ```json
   {
     "api_key": "YOUR_API_KEY",
     "other_data": "..."
   }
   ```

### APIキー設定

`config/config.php`でAPIキーを設定してください：

```php
'api_keys' => array(
  'YOUR_API_KEY' => array(
    'name' => 'API Key Name',
    'permissions' => array('read', 'write', 'delete'),
    'expires' => null  // null = 無期限
  )
)
```

## エンドポイント

### ファイル操作

#### ファイル一覧取得
```
GET /api/files
```

**パラメータ:**
- `page` (optional): ページ番号 (デフォルト: 1)
- `limit` (optional): 取得件数 (デフォルト: 20, 最大: 100)
- `folder` (optional): フォルダID

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "files": [
      {
        "id": 1,
        "original_name": "example.txt",
        "filename": "hashed_filename.txt",
        "comment": "サンプルファイル",
        "file_size": 1024,
        "mime_type": "text/plain",
        "upload_date": "2025-01-XX XX:XX:XX",
        "download_count": 5,
        "folder_id": null
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 50,
      "pages": 3
    }
  },
  "timestamp": "2025-01-XX..."
}
```

#### ファイルアップロード
```
POST /api/files
```

**パラメータ:**
- `file`: アップロードファイル
- `comment` (optional): コメント
- `password_dl` (optional): ダウンロードパスワード
- `password_del` (optional): 削除パスワード
- `folder_id` (optional): フォルダID

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "file_id": 123,
    "download_url": "http://example.com/download.php?key=...",
    "delete_url": "http://example.com/delete.php?key=...",
    "message": "アップロードが完了しました"
  },
  "timestamp": "2025-01-XX..."
}
```

#### ファイル情報取得
```
GET /api/files/{id}
```

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "file": {
      "id": 1,
      "original_name": "example.txt",
      "filename": "hashed_filename.txt",
      "comment": "サンプルファイル",
      "file_size": 1024,
      "mime_type": "text/plain",
      "upload_date": "2025-01-XX XX:XX:XX",
      "download_count": 5,
      "folder_id": null
    }
  },
  "timestamp": "2025-01-XX..."
}
```

#### ファイル削除
```
DELETE /api/files/{id}
```

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "message": "ファイルを削除しました"
  },
  "timestamp": "2025-01-XX..."
}
```

### フォルダ操作

#### フォルダ一覧取得
```
GET /api/folders
```

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "folders": [
      {
        "id": 1,
        "name": "ドキュメント",
        "parent_id": null,
        "created_at": "2025-01-XX XX:XX:XX"
      }
    ]
  },
  "timestamp": "2025-01-XX..."
}
```

#### フォルダ作成
```
POST /api/folders
```

**パラメータ:**
```json
{
  "name": "フォルダ名",
  "parent_id": 1  // optional: 親フォルダID
}
```

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "message": "フォルダを作成しました",
    "folder_id": 123
  },
  "timestamp": "2025-01-XX..."
}
```

#### フォルダ削除
```
DELETE /api/folders/{id}
```

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "message": "フォルダを削除しました"
  },
  "timestamp": "2025-01-XX..."
}
```

### システム情報

#### システム状態取得
```
GET /api/status
```

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "status": "ok",
    "version": "1.0.0",
    "api_enabled": true,
    "folders_enabled": true,
    "server_time": "2025-01-XX..."
  },
  "timestamp": "2025-01-XX..."
}
```

## エラーレスポンス

エラーが発生した場合は以下の形式でレスポンスが返されます：

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "エラーメッセージ",
    "details": {}  // optional: 詳細情報
  },
  "timestamp": "2025-01-XX..."
}
```

### エラーコード一覧

| コード | HTTPステータス | 説明 |
|--------|----------------|------|
| `API_DISABLED` | 503 | API機能が無効 |
| `API_KEY_MISSING` | 401 | APIキーが未指定 |
| `API_KEY_INVALID` | 401 | 無効なAPIキー |
| `PERMISSION_DENIED` | 403 | 権限不足 |
| `RATE_LIMIT_EXCEEDED` | 429 | レート制限超過 |
| `ENDPOINT_NOT_FOUND` | 404 | エンドポイントが見つからない |
| `VALIDATION_ERROR` | 400 | バリデーションエラー |
| `FILE_NOT_FOUND` | 404 | ファイルが見つからない |
| `FOLDER_NOT_EMPTY` | 409 | フォルダが空ではない |
| `FOLDER_HAS_CHILDREN` | 409 | フォルダに子フォルダが存在 |
| `DATABASE_ERROR` | 500 | データベースエラー |
| `INTERNAL_ERROR` | 500 | サーバー内部エラー |

## 使用例

### cURLでのファイルアップロード

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -F "file=@example.txt" \
  -F "comment=APIからのアップロード" \
  http://your-domain.com/app/api/files
```

### cURLでのファイル一覧取得

```bash
curl -X GET \
  -H "Authorization: Bearer YOUR_API_KEY" \
  "http://your-domain.com/app/api/files?page=1&limit=10"
```

### cURLでのファイル削除

```bash
curl -X DELETE \
  -H "Authorization: Bearer YOUR_API_KEY" \
  http://your-domain.com/app/api/files/123
```

## レート制限

APIキーごとに1時間あたりのリクエスト数制限を設定できます。制限に達した場合は`429 Too Many Requests`が返されます。

設定は`config/config.php`の`api_rate_limit`で調整できます（0で無制限）。

## セキュリティ

- APIキーは適切に管理し、外部に漏洩しないよう注意してください
- HTTPSの使用を強く推奨します
- 必要最小限の権限のみを付与してください
- 定期的にAPIキーをローテーションすることを推奨します