<?php
/**
 * 更新商品資訊
 * 用於商品編輯頁面
 * 包含新增/刪除圖片和模型的功能
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 引入配置文件
require_once 'config.php';

// 檢查請求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "message" => "僅支援 POST 請求"
    ]);
    exit;
}

// 獲取商品ID和操作類型
$productId = $_POST['productId'] ?? '';
$action = $_POST['action'] ?? 'update'; // 預設為更新操作

if (empty($productId)) {
    echo json_encode([
        "success" => false,
        "message" => "缺少商品ID參數"
    ]);
    exit;
}

try {
    // 檢查是否安裝了 MongoDB 擴展
    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB 擴展未安裝");
    }

    // 連接到商品資料庫
    $furnitureManager = getFurnitureDbConnection();
    $dbName = getFurnitureDbName();
    
    // 將字符串ID轉換為 ObjectId
    $objectId = new MongoDB\BSON\ObjectId($productId);
    
    // 首先獲取商品詳細資訊，以確定集合名稱
    $productData = null;
    $collectionName = null;
    
    // 列出所有集合以找到產品集合
    $listCollectionsCmd = new MongoDB\Driver\Command(['listCollections' => 1]);
    $collectionsCursor = $furnitureManager->executeCommand($dbName, $listCollectionsCmd);
    
    foreach ($collectionsCursor as $collectionInfo) {
        $currentCollectionName = $collectionInfo->name;
        
        // 只處理以 "_product" 結尾的集合
        if (strpos($currentCollectionName, '_product') !== false) {
            try {
                // 查詢該集合中的指定商品
                $productFilter = ["_id" => $objectId];
                $productQuery = new MongoDB\Driver\Query($productFilter);
                $productCursor = $furnitureManager->executeQuery(
                    $dbName . "." . $currentCollectionName,
                    $productQuery
                );
                
                foreach ($productCursor as $productDocument) {
                    $collectionName = $currentCollectionName;
                    $productData = json_decode(json_encode($productDocument), true);
                    break 2; // 找到商品後跳出兩層循環
                }
                
            } catch (Exception $e) {
                // 跳過有問題的集合，繼續處理其他集合
                error_log("處理集合 {$currentCollectionName} 時發生錯誤: " . $e->getMessage());
                continue;
            }
        }
    }
    
    if (!$collectionName || !$productData) {
        throw new Exception("找不到指定的商品");
    }
    
    // 準備更新數據
    $updateData = [];
    
    // 基本文字資訊
    $updateData['name'] = $_POST['productName'] ?? $productData['name'] ?? '';
    $updateData['price'] = floatval($_POST['productPrice'] ?? $productData['price'] ?? 0);
    $updateData['category'] = $_POST['productCategory'] ?? $productData['category'] ?? '';
    $updateData['description'] = $_POST['productDescription'] ?? $productData['description'] ?? '';
    $updateData['stock'] = intval($_POST['productStock'] ?? $productData['stock'] ?? 0);
    
    // 處理尺寸
    $dimensions = json_decode($_POST['dimensions'] ?? '{}', true);
    if (!empty($dimensions)) {
        $updateData['dimensions'] = $dimensions;
        // 同時更新單獨的尺寸欄位，以兼容舊版本
        $updateData['width'] = $dimensions['width'] ?? null;
        $updateData['height'] = $dimensions['height'] ?? null;
        $updateData['depth'] = $dimensions['depth'] ?? null;
    }
    
    // 更新時間戳
    $updateData['updated_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
    
    // 處理要刪除的圖片
    $imagesToDelete = [];
    if (isset($_POST['imagesToDelete'])) {
        $imagesToDelete = json_decode($_POST['imagesToDelete'], true);
        
        // 從 GridFS 中刪除圖片
        if (!empty($imagesToDelete)) {
            try {
                foreach ($imagesToDelete as $imageId) {
                    try {
                        // 將字符串ID轉換為 ObjectId
                        $imageObjectId = new MongoDB\BSON\ObjectId($imageId);
                        
                        // 刪除 fs.images.files 中的文件記錄
                        $bulk = new MongoDB\Driver\BulkWrite();
                        $bulk->delete(['_id' => $imageObjectId]);
                        $furnitureManager->executeBulkWrite($dbName . ".fs.images.files", $bulk);
                        
                        // 刪除 fs.images.chunks 中的所有塊
                        $chunkBulk = new MongoDB\Driver\BulkWrite();
                        $chunkBulk->delete(['files_id' => $imageObjectId]);
                        $furnitureManager->executeBulkWrite($dbName . ".fs.images.chunks", $chunkBulk);
                        
                        error_log("已從 GridFS 刪除圖片: {$imageId}");
                    } catch (Exception $e) {
                        error_log("刪除 GridFS 圖片時發生錯誤: {$imageId}, " . $e->getMessage());
                        // 繼續處理其他圖片
                    }
                }
            } catch (Exception $e) {
                error_log("刪除 GridFS 圖片時發生錯誤: " . $e->getMessage());
                // 繼續處理，即使 GridFS 刪除失敗
            }
        }
    }
    
    // 處理現有圖片
    $currentImages = $productData['images'] ?? [];
    $updatedImages = [];
    
    foreach ($currentImages as $image) {
        $imageFileId = $image['file_id'];
        if (is_array($imageFileId) && isset($imageFileId['$oid'])) {
            $imageFileId = $imageFileId['$oid'];
        }
        
        // 如果圖片不在刪除列表中，則保留
        if (!in_array($imageFileId, $imagesToDelete)) {
            $updatedImages[] = $image;
        }
    }
    
    // 處理新上傳的圖片
    if (isset($_FILES['newProductImages']) && !empty($_FILES['newProductImages']['name'][0])) {
        $newImages = $_FILES['newProductImages'];
        $imageCount = count($newImages['name']);
        
        for ($i = 0; $i < $imageCount; $i++) {
            if ($newImages['error'][$i] === UPLOAD_ERR_OK) {
                $tempPath = $newImages['tmp_name'][$i];
                $originalFilename = $newImages['name'][$i];
                $contentType = $newImages['type'][$i];
                
                // 生成唯一文件名
                $filename = uniqid('img_') . '_' . $originalFilename;
                
                // 讀取文件內容
                $imageContent = file_get_contents($tempPath);
                if ($imageContent === false) {
                    error_log("無法讀取圖片檔案: " . $originalFilename);
                    continue;
                }
                
                // 生成新的文件ID
                $imageFileId = new MongoDB\BSON\ObjectId();
                
                // 創建文件元數據
                $imageMetadata = [
                    '_id' => $imageFileId,
                    'filename' => $filename,
                    'length' => strlen($imageContent),
                    'chunkSize' => 261120, // 默認chunk大小
                    'uploadDate' => new MongoDB\BSON\UTCDateTime(),
                    'metadata' => [
                        'product_id' => $productData['product_id'] ?? $productId,
                        'product_folder' => $productData['folder_path'] ?? '',
                        'original_filename' => $originalFilename,
                        'content_type' => $contentType,
                        'image_index' => count($updatedImages) + 1,
                        'created_by' => $productData['created_by'] ?? 'unknown',
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'file_type' => 'product_image'
                    ]
                ];
                
                // 將文件分塊並儲存
                $chunkSize = 261120; // 255KB
                $chunks = str_split($imageContent, $chunkSize);
                
                // 使用 bulk write 操作插入文件元數據
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->insert($imageMetadata);
                $furnitureManager->executeBulkWrite($dbName . ".fs.images.files", $bulk);
                
                // 插入文件塊到 fs.images.chunks
                $chunkBulk = new MongoDB\Driver\BulkWrite();
                foreach ($chunks as $n => $chunk) {
                    $chunkDoc = [
                        '_id' => new MongoDB\BSON\ObjectId(),
                        'files_id' => $imageFileId,
                        'n' => $n,
                        'data' => new MongoDB\BSON\Binary($chunk, MongoDB\BSON\Binary::TYPE_GENERIC)
                    ];
                    $chunkBulk->insert($chunkDoc);
                }
                
                $furnitureManager->executeBulkWrite($dbName . ".fs.images.chunks", $chunkBulk);
                
                // 添加到圖片列表
                $updatedImages[] = [
                    'file_id' => (string)$imageFileId,
                    'filename' => $filename,
                    'original_filename' => $originalFilename,
                    'content_type' => $contentType,
                    'image_index' => count($updatedImages) + 1
                ];
                
                error_log("新圖片已儲存到GridFS，檔案ID: " . $imageFileId . "，檔案名: " . $filename);
            }
        }
    }
    
    // 更新圖片列表
    $updateData['images'] = $updatedImages;
    
    // 處理模型刪除
    if (isset($_POST['deleteModel']) && $_POST['deleteModel'] === 'true') {
        // 如果有現有模型，從 GridFS 中刪除
        if (isset($productData['model_file_id'])) {
            try {
                // 獲取模型 ID
                $modelFileId = $productData['model_file_id'];
                if (is_array($modelFileId) && isset($modelFileId['$oid'])) {
                    $modelFileId = $modelFileId['$oid'];
                }
                
                // 將字符串ID轉換為 ObjectId
                $modelObjectId = new MongoDB\BSON\ObjectId($modelFileId);
                
                // 刪除 fs.models.files 中的文件記錄
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete(['_id' => $modelObjectId]);
                $furnitureManager->executeBulkWrite($dbName . ".fs.models.files", $bulk);
                
                // 刪除 fs.models.chunks 中的所有塊
                $chunkBulk = new MongoDB\Driver\BulkWrite();
                $chunkBulk->delete(['files_id' => $modelObjectId]);
                $furnitureManager->executeBulkWrite($dbName . ".fs.models.chunks", $chunkBulk);
                
                error_log("已從 GridFS 刪除模型: {$modelFileId}");
            } catch (Exception $e) {
                error_log("刪除 GridFS 模型時發生錯誤: " . $e->getMessage());
                // 繼續處理，即使 GridFS 刪除失敗
            }
        }
        
        // 刪除模型相關欄位
        $updateData['model_file_id'] = null;
        $updateData['model_filename'] = null;
    }
    
    // 處理新上傳的模型
    if (isset($_FILES['newProductModel']) && !empty($_FILES['newProductModel']['name'])) {
        $modelFile = $_FILES['newProductModel'];
        
        if ($modelFile['error'] === UPLOAD_ERR_OK) {
            $tempPath = $modelFile['tmp_name'];
            $originalFilename = $modelFile['name'];
            $contentType = $modelFile['type'];
            
            // 生成唯一文件名
            $filename = uniqid('model_') . '_' . $originalFilename;
            
            // 讀取文件內容
            $modelContent = file_get_contents($tempPath);
            if ($modelContent === false) {
                error_log("無法讀取模型檔案: " . $originalFilename);
                throw new Exception("無法讀取3D模型文件內容");
            }
            
            // 生成新的文件ID
            $modelFileId = new MongoDB\BSON\ObjectId();
            
            // 創建文件元數據
            $modelMetadata = [
                '_id' => $modelFileId,
                'filename' => $filename,
                'length' => strlen($modelContent),
                'chunkSize' => 261120, // 默認chunk大小
                'uploadDate' => new MongoDB\BSON\UTCDateTime(),
                'metadata' => [
                    'product_id' => $productData['product_id'] ?? $productId,
                    'product_folder' => $productData['folder_path'] ?? '',
                    'original_filename' => $originalFilename,
                    'content_type' => 'model/gltf-binary',
                    'created_by' => $productData['created_by'] ?? 'unknown',
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ];
            
            // 將文件分塊並儲存
            $chunkSize = 261120; // 255KB
            $chunks = str_split($modelContent, $chunkSize);
            
            // 使用 bulk write 操作插入文件元數據
            $bulk = new MongoDB\Driver\BulkWrite();
            $bulk->insert($modelMetadata);
            $furnitureManager->executeBulkWrite($dbName . ".fs.models.files", $bulk);
            
            // 插入文件塊到 fs.models.chunks
            $chunkBulk = new MongoDB\Driver\BulkWrite();
            foreach ($chunks as $n => $chunk) {
                $chunkDoc = [
                    '_id' => new MongoDB\BSON\ObjectId(),
                    'files_id' => $modelFileId,
                    'n' => $n,
                    'data' => new MongoDB\BSON\Binary($chunk, MongoDB\BSON\Binary::TYPE_GENERIC)
                ];
                $chunkBulk->insert($chunkDoc);
            }
            
            $furnitureManager->executeBulkWrite($dbName . ".fs.models.chunks", $chunkBulk);
            
            // 更新模型相關欄位
            $updateData['model_file_id'] = $modelFileId;
            $updateData['model_filename'] = $filename;
            
            error_log("新模型已儲存到GridFS，檔案ID: " . $modelFileId . "，檔案名: " . $filename);
        }
    }
    
    // 根據操作類型執行不同的操作
    if ($action === 'delete') {
        // 刪除商品相關的所有圖片
        if (isset($productData['images']) && is_array($productData['images'])) {
            try {
                foreach ($productData['images'] as $image) {
                    if (isset($image['file_id'])) {
                        try {
                            // 獲取圖片 ID
                            $imageFileId = $image['file_id'];
                            if (is_array($imageFileId) && isset($imageFileId['$oid'])) {
                                $imageFileId = $imageFileId['$oid'];
                            }
                            
                            // 將字符串ID轉換為 ObjectId
                            $imageObjectId = new MongoDB\BSON\ObjectId($imageFileId);
                            
                            // 刪除 fs.images.files 中的文件記錄
                            $bulk = new MongoDB\Driver\BulkWrite();
                            $bulk->delete(['_id' => $imageObjectId]);
                            $furnitureManager->executeBulkWrite($dbName . ".fs.images.files", $bulk);
                            
                            // 刪除 fs.images.chunks 中的所有塊
                            $chunkBulk = new MongoDB\Driver\BulkWrite();
                            $chunkBulk->delete(['files_id' => $imageObjectId]);
                            $furnitureManager->executeBulkWrite($dbName . ".fs.images.chunks", $chunkBulk);
                            
                            error_log("已從 GridFS 刪除圖片: {$imageFileId}");
                        } catch (Exception $e) {
                            error_log("刪除 GridFS 圖片時發生錯誤: " . $e->getMessage());
                            // 繼續處理其他圖片
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("刪除 GridFS 圖片時發生錯誤: " . $e->getMessage());
                // 繼續處理，即使 GridFS 刪除失敗
            }
        }
        
        // 刪除商品相關的 3D 模型
        if (isset($productData['model_file_id'])) {
            try {
                // 獲取模型 ID
                $modelFileId = $productData['model_file_id'];
                if (is_array($modelFileId) && isset($modelFileId['$oid'])) {
                    $modelFileId = $modelFileId['$oid'];
                }
                
                // 將字符串ID轉換為 ObjectId
                $modelObjectId = new MongoDB\BSON\ObjectId($modelFileId);
                
                // 刪除 fs.models.files 中的文件記錄
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete(['_id' => $modelObjectId]);
                $furnitureManager->executeBulkWrite($dbName . ".fs.models.files", $bulk);
                
                // 刪除 fs.models.chunks 中的所有塊
                $chunkBulk = new MongoDB\Driver\BulkWrite();
                $chunkBulk->delete(['files_id' => $modelObjectId]);
                $furnitureManager->executeBulkWrite($dbName . ".fs.models.chunks", $chunkBulk);
                
                error_log("已從 GridFS 刪除模型: {$modelFileId}");
            } catch (Exception $e) {
                error_log("刪除 GridFS 模型時發生錯誤: " . $e->getMessage());
                // 繼續處理，即使 GridFS 刪除失敗
            }
        }
        
        // 從集合中刪除商品記錄
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete(['_id' => $objectId]);
        
        $result = $furnitureManager->executeBulkWrite($dbName . '.' . $collectionName, $bulk);
        
        // 檢查刪除結果
        if ($result->getDeletedCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "商品刪除成功"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "商品刪除失敗"
            ]);
        }
    } else {
        // 執行更新操作
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(
            ['_id' => $objectId],
            ['$set' => $updateData]
        );
        
        $result = $furnitureManager->executeBulkWrite($dbName . '.' . $collectionName, $bulk);
        
        // 檢查更新結果
        if ($result->getModifiedCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "商品更新成功"
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "商品資訊未變更或更新失敗"
            ]);
        }
    }

} catch (MongoDB\Driver\Exception\Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "資料庫錯誤: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage()
    ]);
}
?>
