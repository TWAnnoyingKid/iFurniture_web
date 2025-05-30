<?php
/**
 * 獲取單個商品的詳細資訊
 * 用於商品編輯頁面
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 引入配置文件
require_once 'config.php';

// 獲取商品ID
$productId = $_GET['id'] ?? '';

if (empty($productId)) {
    echo json_encode([
        "success" => false,
        "message" => "缺少商品ID參數",
        "product" => null
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
    
    // 列出所有集合以找到產品集合
    $listCollectionsCmd = new MongoDB\Driver\Command(['listCollections' => 1]);
    $collectionsCursor = $furnitureManager->executeCommand($dbName, $listCollectionsCmd);
    
    $productData = null;
    
    foreach ($collectionsCursor as $collectionInfo) {
        $collectionName = $collectionInfo->name;
        
        // 只處理以 "_product" 結尾的集合
        if (strpos($collectionName, '_product') !== false) {
            try {
                // 查詢該集合中的指定商品
                $productFilter = ["_id" => $objectId];
                $productQuery = new MongoDB\Driver\Query($productFilter);
                $productCursor = $furnitureManager->executeQuery(
                    $dbName . "." . $collectionName,
                    $productQuery
                );
                
                foreach ($productCursor as $productDocument) {
                    $product = json_decode(json_encode($productDocument), true);
                    
                    // 找到商品後，構建詳細資訊
                    $productData = [
                        'id' => $product['_id']['$oid'] ?? $productId,
                        'product_id' => $product['product_id'] ?? '',
                        'name' => $product['name'] ?? '',
                        'price' => $product['price'] ?? 0,
                        'category' => $product['category'] ?? '',
                        'description' => $product['description'] ?? '',
                        'width' => $product['width'] ?? null,
                        'height' => $product['height'] ?? null,
                        'depth' => $product['depth'] ?? null,
                        'created_at' => $product['created_at'] ?? null,
                        'updated_at' => $product['updated_at'] ?? null,
                        'collection_name' => $collectionName // 保存集合名稱，用於更新
                    ];
                    
                    // 處理尺寸
                    $dimensions = [
                        'width' => $product['dimensions']['width'] ?? $product['width'] ?? null,
                        'height' => $product['dimensions']['height'] ?? $product['height'] ?? null,
                        'depth' => $product['dimensions']['depth'] ?? $product['depth'] ?? null
                    ];
                    $productData['dimensions'] = $dimensions;
                    
                    // 處理標籤
                    if (isset($product['tags'])) {
                        if (is_array($product['tags'])) {
                            $productData['tags'] = $product['tags'];
                        } else if (is_string($product['tags'])) {
                            $productData['tags'] = explode(',', $product['tags']);
                        }
                    } else {
                        $productData['tags'] = [];
                    }
                    
                    // 處理圖片
                    $images = [];
                    if (isset($product['images']) && is_array($product['images'])) {
                        foreach ($product['images'] as $image) {
                            if (is_array($image) && isset($image['file_id'])) {
                                // GridFS檔案ID
                                $fileId = $image['file_id'];
                                if (is_array($fileId) && isset($fileId['$oid'])) {
                                    $fileId = $fileId['$oid'];
                                }
                                
                                $images[] = [
                                    'id' => $fileId,
                                    'file_id' => $fileId,
                                    'filename' => $image['filename'] ?? '',
                                    'original_filename' => $image['original_filename'] ?? '',
                                    'content_type' => $image['content_type'] ?? '',
                                    'image_index' => $image['image_index'] ?? 0,
                                    'url' => '../php/get_image.php?file_id=' . $fileId
                                ];
                            }
                        }
                    }
                    $productData['images'] = $images;
                    
                    // 處理 3D 模型
                    if (isset($product['model_file_id'])) {
                        $modelFileId = $product['model_file_id'];
                        if (is_array($modelFileId) && isset($modelFileId['$oid'])) {
                            $modelFileId = $modelFileId['$oid'];
                        }
                        
                        $modelData = [
                            'id' => $modelFileId,
                            'file_id' => $modelFileId,
                            'filename' => $product['model_filename'] ?? 'model.glb',
                            'url' => '../php/get_model.php?file_id=' . $modelFileId
                        ];
                        $productData['model'] = $modelData;
                    }
                    
                    break 2; // 找到商品後跳出兩層循環
                }
                
            } catch (Exception $e) {
                // 跳過有問題的集合，繼續處理其他集合
                error_log("處理集合 {$collectionName} 時發生錯誤: " . $e->getMessage());
                continue;
            }
        }
    }
    
    if ($productData) {
        // 返回成功結果
        echo json_encode([
            "success" => true,
            "product" => $productData,
            "message" => "商品詳細資訊載入成功"
        ]);
    } else {
        // 未找到商品
        echo json_encode([
            "success" => false,
            "message" => "找不到指定的商品",
            "product" => null
        ]);
    }

} catch (MongoDB\Driver\Exception\Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "資料庫錯誤: " . $e->getMessage(),
        "product" => null
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage(),
        "product" => null
    ]);
}
?>
