<?php
/**
 * 從 GridFS 獲取 3D 模型文件（公開版本，無需登入）
 * 提供模型文件的下載服務，用於主頁面
 */

// 引入配置文件
require_once 'config.php';

// 獲取請求的文件ID
$fileId = $_GET['file_id'] ?? '';

if (empty($fileId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "缺少文件ID參數"]);
    exit;
}

try {
    // 檢查是否安裝了 MongoDB 擴展
    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB 擴展未安裝");
    }

    // 使用原生MongoDB驅動進行GridFS操作
    $furnitureManager = new MongoDB\Driver\Manager(config('databases.mongodb_furniture.connection_string'));
    
    // 將字符串文件ID轉換為 ObjectId
    $objectId = new MongoDB\BSON\ObjectId($fileId);
    
    // 檢查文件是否存在
    $fileInfo = null;
    try {
        // 查詢 fs.files 集合
        $query = new MongoDB\Driver\Query(['_id' => $objectId]);
        $cursor = $furnitureManager->executeQuery(
            config('databases.mongodb_furniture.dbname') . ".fs.models.files",
            $query
        );
        
        foreach ($cursor as $file) {
            $fileInfo = $file;
            break;
        }
    } catch (Exception $e) {
        error_log("查找GridFS模型檔案失敗: " . $e->getMessage());
        throw new Exception("模型檔案不存在或已被刪除");
    }
    
    if (!$fileInfo) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "模型檔案不存在"]);
        exit;
    }
    
    // 獲取文件元數據
    $metadata = $fileInfo->metadata ?? new stdClass();
    $filename = $fileInfo->filename ?? 'model.glb';
    $contentType = isset($metadata->content_type) ? $metadata->content_type : 'model/gltf-binary';
    
    // 設置適當的 HTTP 頭
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: inline; filename="' . basename($filename) . '"');
    header('Cache-Control: public, max-age=31536000'); // 緩存1年
    header('ETag: "' . $fileId . '"');
    header('Access-Control-Allow-Origin: *'); // 允許跨域訪問
    
    // 檢查 If-None-Match 頭以支持瀏覽器緩存
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $fileId . '"') {
        http_response_code(304);
        exit;
    }
    
    // 設置文件大小頭（如果可用）
    if (isset($fileInfo->length)) {
        header('Content-Length: ' . $fileInfo->length);
    }
    
    // 讀取並輸出GridFS文件內容
    try {
        // 查詢文件塊
        $chunksQuery = new MongoDB\Driver\Query(
            ['files_id' => $objectId],
            ['sort' => ['n' => 1]] // 按順序排序
        );
        
        $chunksCursor = $furnitureManager->executeQuery(
            config('databases.mongodb_furniture.dbname') . ".fs.models.chunks",
            $chunksQuery
        );
        
        // 輸出文件內容
        foreach ($chunksCursor as $chunk) {
            if (isset($chunk->data)) {
                echo $chunk->data->getData();
                flush(); // 確保數據立即發送到瀏覽器
            }
        }
        
    } catch (Exception $e) {
        error_log("讀取GridFS模型檔案塊失敗: " . $e->getMessage());
        throw new Exception("無法讀取模型檔案內容");
    }
    
} catch (MongoDB\Driver\Exception\Exception $e) {
    error_log("MongoDB GridFS 錯誤: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false, 
        "message" => "資料庫錯誤: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("獲取模型檔案錯誤: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage()
    ]);
}
?> 