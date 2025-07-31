/**
 * 再開可能アップロード機能
 * Tus.ioプロトコルを使用し、失敗時は従来方式にフォールバック
 * phpUploader - Resumable Upload Module
 */

// グローバル変数
var resumableUploads = {};
var isResumableAvailable = false;

// Tus.ioの利用可能性をチェック
$(document).ready(function() {
    checkTusAvailability();
});

/**
 * Tus.ioサーバーの利用可能性をチェック
 */
function checkTusAvailability() {
    if (typeof tus === 'undefined') {
        console.warn('Tus.js library not loaded, falling back to traditional upload');
        isResumableAvailable = false;
        return;
    }
    
    // サーバーのTus.io対応をチェック（タイムアウトを短縮）
    $.ajax({
        url: './app/api/tus-upload.php',
        method: 'OPTIONS',
        timeout: 2000
    })
    .done(function(data, textStatus, xhr) {
        var tusResumable = xhr.getResponseHeader('Tus-Resumable');
        if (tusResumable) {
            isResumableAvailable = true;
            console.log('Resumable upload available (Tus ' + tusResumable + ')');
        } else {
            console.warn('Tus.io server response invalid, falling back to traditional upload');
            isResumableAvailable = false;
        }
    })
    .fail(function(xhr, status, error) {
        console.warn('Tus.io server not available (' + status + ': ' + error + '), falling back to traditional upload');
        isResumableAvailable = false;
    });
}

/**
 * 単一ファイルの再開可能アップロード
 */
function uploadFileResumable(file, options) {
    return new Promise(function(resolve, reject) {
        options = options || {};
        var comment = options.comment || '';
        var dlkey = options.dlkey || '';
        var delkey = options.delkey || '';
        var maxDownloads = options.maxDownloads || null;
        var expiresDays = options.expiresDays || null;
        var folderId = options.folderId || null;
        
        // 設定に基づくアップロード方式の選択
        const uploadPriority = window.config?.upload_method_priority || 'resumable';
        const fallbackEnabled = window.config?.upload_fallback_enabled !== false;
        
        // 通常アップロード優先の場合
        if (uploadPriority === 'normal') {
            console.log('Using normal upload priority for:', file.name);
            fallbackUpload(file, options).then(resolve).catch(function(error) {
                if (fallbackEnabled && isResumableAvailable) {
                    console.log('Fallback to resumable upload after normal failed:', file.name);
                    proceedWithResumableUpload();
                } else {
                    reject(error);
                }
            });
            return;
        }
        
        // 再開可能アップロード優先（デフォルト）
        // Tus.ioが利用できない場合はフォールバック
        if (!isResumableAvailable) {
            if (fallbackEnabled) {
                console.log('Tus.io unavailable, using fallback for:', file.name);
                fallbackUpload(file, options).then(resolve).catch(reject);
            } else {
                reject(new Error('Resumable upload not available and fallback disabled'));
            }
            return;
        }
        
        proceedWithResumableUpload();
        
        function proceedWithResumableUpload() {
        
        // メタデータを準備
        var metadata = {
            filename: file.name,
            filetype: file.type || 'application/octet-stream'
        };
        
        if (comment) metadata.comment = comment;
        if (dlkey) metadata.dlkey = dlkey;
        if (delkey) metadata.delkey = delkey;
        if (maxDownloads) metadata.max_downloads = maxDownloads.toString();
        if (expiresDays) metadata.expires_days = expiresDays.toString();
        if (folderId) metadata.folder_id = folderId.toString();
        if (options.replacekey) metadata.replacekey = options.replacekey.toString();
        
            // Tus.ioアップロードを作成（Docker環境対応）
            var upload = new tus.Upload(file, {
            endpoint: './app/api/tus-upload.php',
            retryDelays: [0, 1000, 3000, 5000],
            metadata: metadata,
            chunkSize: 512 * 1024, // 512KB chunks for better compatibility
            removeFingerprintOnSuccess: true,
            // アップロード再開を無効にして新規アップロードのみ使用
            resume: false,
            
            onError: function(error) {
                console.error('Tus upload failed:', error);
                
                // グローバルリストから削除
                delete resumableUploads[file.name];
                
                // Tus.ioが失敗した場合はフォールバック（設定で有効な場合）
                if (fallbackEnabled) {
                    console.log('Falling back to traditional upload for:', file.name);
                    fallbackUpload(file, options).then(resolve).catch(reject);
                } else {
                    reject(error);
                }
            },
            
            onProgress: function(bytesUploaded, bytesTotal) {
                var percentage = Math.round((bytesUploaded / bytesTotal) * 100);
                
                // 速度計算
                var currentTime = Date.now();
                var uploadInfo = resumableUploads[file.name];
                if (uploadInfo) {
                    if (!uploadInfo.lastTime) {
                        uploadInfo.lastTime = currentTime;
                        uploadInfo.lastBytes = 0;
                    }
                    
                    var timeDiff = (currentTime - uploadInfo.lastTime) / 1000;
                    var bytesDiff = bytesUploaded - uploadInfo.lastBytes;
                    var speed = timeDiff > 0 ? bytesDiff / timeDiff : 0;
                    
                    uploadInfo.lastTime = currentTime;
                    uploadInfo.lastBytes = bytesUploaded;
                    uploadInfo.progress = percentage;
                    
                    updateUploadProgress(file.name, percentage, bytesUploaded, bytesTotal, 'resumable', speed);
                }
            },
            
            onSuccess: function() {
                console.log('Resumable upload completed:', file.name);
                onUploadComplete(file.name, 'resumable');
                resolve();
            }
        });
        
        // アップロード開始
        upload.start();
        
        // グローバルに保存（後で中断・再開できるように）
        resumableUploads[file.name] = {
            upload: upload,
            file: file,
            options: options,
            progress: 0
        };
      } // Close proceedWithResumableUpload
    });
}

/**
 * 従来方式へのフォールバック
 */
function fallbackUpload(file, options) {
    return new Promise(function(resolve, reject) {
        console.log('Using fallback upload for:', file.name);
        
        var formData = new FormData();
        formData.append('file', file);
        formData.append('comment', options.comment || '');
        // CSRFトークンを追加
        var csrfToken = $('#csrfToken').val();
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }
        formData.append('dlkey', options.dlkey || '');
        formData.append('delkey', options.delkey || '');
        formData.append('replacekey', options.replacekey || '');
        
        if (options.maxDownloads) {
            formData.append('max_downloads', options.maxDownloads);
        }
        if (options.expiresDays) {
            formData.append('expires_days', options.expiresDays);
        }
        if (options.folderId) {
            formData.append('folder_id', options.folderId);
        }
        
        // アップロード開始時間を記録
        var startTime = Date.now();
        var lastTime = startTime;
        var lastBytes = 0;
        
        $.ajax({
            url: './app/api/upload.php',
            type: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            dataType: 'json',
            xhr: function() {
                var xhr = $.ajaxSettings.xhr();
                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percentage = Math.round((e.loaded / e.total) * 100);
                            
                            // 速度計算
                            var currentTime = Date.now();
                            var timeDiff = (currentTime - lastTime) / 1000;
                            var bytesDiff = e.loaded - lastBytes;
                            var speed = timeDiff > 0 ? bytesDiff / timeDiff : 0;
                            
                            lastTime = currentTime;
                            lastBytes = e.loaded;
                            
                            updateUploadProgress(file.name, percentage, e.loaded, e.total, 'fallback', speed);
                        }
                    }, false);
                }
                return xhr;
            }
        })
        .done(function(data) {
            console.log('Fallback upload response:', data);
            if (data.status === 'ok' || data.status === 'success') {
                console.log('Fallback upload completed:', file.name);
                onUploadComplete(file.name, 'fallback');
                resolve();
            } else {
                console.error('Fallback upload failed with response:', data);
                handleUploadError(data, file.name);
                reject(new Error('Upload failed: ' + data.status));
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Fallback upload network error:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                statusCode: xhr.status
            });
            handleUploadError({
                status: 'network_error',
                message: 'Network error: ' + error
            }, file.name);
            reject(new Error('Network error: ' + error));
        });
    });
}

/**
 * アップロード進行状況の更新
 */
function updateUploadProgress(filename, percentage, loaded, total, method, speed) {
    // プログレスバーの更新
    var $progressContainer = $('.file-item').filter(function() {
        return $(this).data('filename') === filename;
    });
    
    if ($progressContainer.length > 0) {
        // ファイルアイテムの状態を更新
        $progressContainer.addClass('uploading');
        $progressContainer.find('.upload-progress').show();
        $progressContainer.find('.upload-status').show();
        $progressContainer.find('.detailed-progress').show();
        
        // プログレスバーを更新
        var $progressBar = $progressContainer.find('.upload-progress-bar');
        $progressBar.css('width', percentage + '%');
        
        // アップロード方式インジケーターを表示
        var $methodIndicator = $progressContainer.find('.upload-method-indicator');
        $methodIndicator.removeClass('resumable fallback failed').addClass(method);
        $methodIndicator.text(method === 'resumable' ? '再開可能' : '通常');
        $methodIndicator.show();
        
        // 制御ボタンを表示（再開可能アップロードの場合のみ）
        if (method === 'resumable') {
            $progressContainer.find('.upload-control-btn.pause').show();
            $progressContainer.find('.upload-control-btn.cancel').show();
        }
        
        // 詳細な進行状況を更新
        var sizeText = formatFileSize(loaded) + ' / ' + formatFileSize(total);
        var speedText = speed ? formatSpeed(speed) : '計算中...';
        
        $progressContainer.find('.upload-status').text(
            percentage + '% (' + sizeText + ')'
        );
        
        $progressContainer.find('.progress-percentage').text(percentage + '%');
        $progressContainer.find('.progress-size').text(sizeText);
        $progressContainer.find('.speed-info').text('速度: ' + speedText);
        
        // 残り時間の計算と表示
        if (speed && speed > 0) {
            var remaining = (total - loaded) / speed;
            var remainingText = formatTime(remaining);
            $progressContainer.find('.speed-info').text('速度: ' + speedText + ' | 残り: ' + remainingText);
        }
    }
    
    // 全体のプログレスバーも更新
    updateGlobalProgress();
}

/**
 * アップロード完了処理
 */
function onUploadComplete(filename, method) {
    var $progressContainer = $('.file-item').filter(function() {
        return $(this).data('filename') === filename;
    });
    
    if ($progressContainer.length > 0) {
        $progressContainer.removeClass('uploading paused').addClass('completed');
        $progressContainer.find('.upload-progress-bar').css('width', '100%');
        $progressContainer.find('.upload-status').text('完了');
        $progressContainer.find('.upload-controls button').hide();
        $progressContainer.find('.detailed-progress').hide();
        
        // 成功アイコンを追加
        $progressContainer.find('.file-info').append(
            '<span class="upload-success-icon" style="color: #5cb85c; margin-left: 10px;">' +
            '<span class="glyphicon glyphicon-ok-circle"></span>' +
            '</span>'
        );
    }
    
    // グローバルリストから削除
    delete resumableUploads[filename];
    
    // 全体の進行状況を更新
    updateGlobalProgress();
    
    // 全てのアップロードが完了したかチェック
    if (Object.keys(resumableUploads).length === 0) {
        setTimeout(function() {
            $('.global-upload-status').removeClass('active');
            location.reload();
        }, 2000);
    }
}

/**
 * アップロードの中断
 */
function pauseUpload(filename) {
    var resumableUpload = resumableUploads[filename];
    if (resumableUpload && resumableUpload.upload) {
        resumableUpload.upload.abort();
        console.log('Upload paused:', filename);
        
        // UIを更新
        var $progressContainer = $('.file-item').filter(function() {
            return $(this).data('filename') === filename;
        });
        $progressContainer.removeClass('uploading').addClass('paused');
        $progressContainer.find('.upload-control-btn.pause').hide();
        $progressContainer.find('.upload-control-btn.resume').show();
        $progressContainer.find('.upload-status').text('一時停止');
        
        return true;
    }
    return false;
}

/**
 * アップロードの再開
 */
function resumeUpload(filename) {
    var resumableUpload = resumableUploads[filename];
    if (resumableUpload && resumableUpload.upload) {
        resumableUpload.upload.start();
        console.log('Upload resumed:', filename);
        
        // UIを更新
        var $progressContainer = $('.file-item').filter(function() {
            return $(this).data('filename') === filename;
        });
        $progressContainer.removeClass('paused').addClass('uploading');
        $progressContainer.find('.upload-control-btn.resume').hide();
        $progressContainer.find('.upload-control-btn.pause').show();
        $progressContainer.find('.upload-status').text('再開中...');
        
        return true;
    }
    return false;
}

/**
 * アップロードのキャンセル
 */
function cancelUpload(filename) {
    var resumableUpload = resumableUploads[filename];
    if (resumableUpload && resumableUpload.upload) {
        resumableUpload.upload.abort();
        console.log('Upload cancelled:', filename);
        
        // UIを更新
        var $progressContainer = $('.file-item').filter(function() {
            return $(this).data('filename') === filename;
        });
        $progressContainer.removeClass('uploading paused').addClass('failed');
        $progressContainer.find('.upload-controls button').hide();
        $progressContainer.find('.upload-status').text('キャンセル済み');
        $progressContainer.find('.upload-method-indicator').removeClass('resumable').addClass('failed');
        
        delete resumableUploads[filename];
        updateGlobalProgress();
        
        return true;
    }
    return false;
}

/**
 * 既存のアップロード関数を拡張
 */
function enhancedFileUpload() {
    console.log('Enhanced file upload called');
    
    // 差し替えキー必須チェック
    var replaceKeyInput = $('#replaceKeyInput');
    if (replaceKeyInput.length === 0) {
        alert('差し替えキー入力フィールドが見つかりません。');
        return;
    }
    
    var replaceKey = replaceKeyInput.val();
    if (!replaceKey || replaceKey.trim() === '') {
        alert('差し替えキーの入力は必須です。');
        return;
    }
    
    // デバッグ情報を出力
    var fileInput = $('#multipleFileInput')[0];
    var fileInputExists = fileInput ? true : false;
    var fileInputHasFiles = fileInput && fileInput.files ? fileInput.files.length : 0;
    var selectedFilesLength = typeof selectedFiles !== 'undefined' && selectedFiles ? selectedFiles.length : 0;
    
    console.log('Debug info - fileInputExists:', fileInputExists, 'fileInputHasFiles:', fileInputHasFiles, 'selectedFilesLength:', selectedFilesLength);
    
    // 単一ファイルの場合（multipleFileInputから最初のファイルを取得）
    if (fileInput && fileInput.files && fileInput.files.length > 0) {
        var singleFile = fileInput.files[0];
        console.log('Single file upload:', singleFile.name);
        var options = getUploadOptions();
        uploadFileResumable(singleFile, options);
        return;
    }
    
    // 複数ファイルの場合（既存のselectedFiles配列を使用）
    if (typeof selectedFiles !== 'undefined' && selectedFiles && selectedFiles.length > 0) {
        console.log('Multiple files upload:', selectedFiles.length, 'files');
        enhancedMultipleUpload();
        return;
    }
    
    console.warn('No files selected for upload');
    alert('ファイルが選択されていません。ファイルを選択してからアップロードボタンを押してください。');
}

/**
 * 複数ファイルの拡張アップロード
 */
function enhancedMultipleUpload() {
    if (selectedFiles.length === 0) return;
    
    isUploading = true;
    $('#errorContainer').fadeOut();
    $('#uploadContainer').fadeIn();
    $('.file-item .upload-progress').show();
    
    var options = getUploadOptions();
    var completedCount = 0;
    var totalCount = selectedFiles.length;
    
    // 各ファイルを順次アップロード
    selectedFiles.forEach(function(file, index) {
        setTimeout(function() {
            uploadFileResumable(file, options)
                .then(function() {
                    completedCount++;
                    if (completedCount === totalCount) {
                        isUploading = false;
                        $('#uploadContainer').hide();
                    }
                })
                .catch(function(error) {
                    console.error('Upload failed for', file.name, error);
                    completedCount++;
                    if (completedCount === totalCount) {
                        isUploading = false;
                        $('#uploadContainer').hide();
                    }
                });
        }, index * 100); // 100ms間隔でスタートして同時接続数を制限
    });
}

/**
 * アップロードオプションを取得
 */
function getUploadOptions() {
    return {
        comment: $('#commentInput').val() || '',
        dlkey: $('#dleyInput').val() || '',
        delkey: $('#deleyInput').val() || '',
        replacekey: $('#replaceKeyInput').val() || '',
        maxDownloads: $('#maxDownloadsUploadInput').val() || null,
        expiresDays: $('#expiresDaysUploadInput').val() || null,
        folderId: $('#folder-select').val() || null
    };
}

/**
 * 全体のアップロード進行状況を更新
 */
function updateGlobalProgress() {
    var totalUploads = Object.keys(resumableUploads).length;

    // 右上の小窓は常に非表示にする
    $('.global-upload-status').removeClass('active').hide();

    // フォーム内プログレスバー要素
    var $progressContainer = $('#progressContainer');
    var $progressBar       = $('#progressBar');
    var $progressText      = $('#progressText');
    var $uploadStatus      = $('#uploadStatus');

    if (totalUploads === 0) {
        // すべて完了、またはアップロード未開始
        $progressContainer.hide();
        return;
    }

    var totalProgress  = 0;
    var completedCount = 0;

    Object.values(resumableUploads).forEach(function (uploadInfo) {
        if (uploadInfo.progress !== undefined) {
            totalProgress += uploadInfo.progress;
        }
        if (uploadInfo.completed) {
            completedCount++;
        }
    });

    var averageProgress = Math.round(totalProgress / totalUploads);

    // プログレスバーを表示・更新
    $progressContainer.show();
    $progressBar.css('width', averageProgress + '%');
    $progressText.text(averageProgress + '%');
    $uploadStatus.text('完了: ' + completedCount + '/' + totalUploads);
}

/**
 * ファイルサイズを読みやすい形式でフォーマット
 */
function formatSpeed(bytesPerSecond) {
    if (bytesPerSecond < 1024) return bytesPerSecond.toFixed(1) + ' B/s';
    if (bytesPerSecond < 1024 * 1024) return (bytesPerSecond / 1024).toFixed(1) + ' KB/s';
    if (bytesPerSecond < 1024 * 1024 * 1024) return (bytesPerSecond / (1024 * 1024)).toFixed(1) + ' MB/s';
    return (bytesPerSecond / (1024 * 1024 * 1024)).toFixed(1) + ' GB/s';
}

/**
 * 時間を読みやすい形式でフォーマット
 */
function formatTime(seconds) {
    if (seconds < 60) return Math.round(seconds) + '秒';
    if (seconds < 3600) return Math.round(seconds / 60) + '分';
    return Math.round(seconds / 3600) + '時間';
}

// 既存のファイルアップロード機能を拡張
$(document).ready(function() {
    // グローバルアップロード状況表示を追加
    if ($('.global-upload-status').length === 0) {
        $('body').append(
            '<div class="global-upload-status">' +
                '<h6>アップロード進行状況</h6>' +
                '<div class="global-upload-progress">' +
                    '<div class="global-upload-progress-bar"></div>' +
                '</div>' +
                '<div class="global-upload-info"></div>' +
            '</div>'
        );
    }
    
    // dragdrop.jsとの統合は不要（dragdrop.js側で呼び出される）
    console.log('Resumable upload module loaded');
});