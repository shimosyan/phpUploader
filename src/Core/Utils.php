<?php

declare(strict_types=1);

/**
 * 統合ユーティリティクラスローダー
 * Ver.2.0で分離されたクラスファイルを読み込み
 * 
 * リファクタリング後の分離構造：
 * - SecurityUtils -> src/Core/Security.php
 * - Logger -> src/Core/Logger.php  
 * - ResponseHandler -> src/Core/ResponseHandler.php
 * 
 * 既存の依存関係を維持するためのブリッジファイル
 */

// 分離されたクラスファイルを読み込み
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ResponseHandler.php';
