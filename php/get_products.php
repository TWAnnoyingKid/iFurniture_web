<?php
/**
 * 獲取所有公開的產品列表（無需登入）
 * 用於主頁面顯示所有公司的產品
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 引入配置文件
require_once 'config.php';

try {
    // 檢查是否安裝了 MongoDB 擴展
    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB 擴展未安裝");
    }

    // 連接到商品資料庫
    $furnitureManager = new MongoDB\Driver\Manager(config('databases.mongodb_furniture.connection_string'));
    
    // 獲取所有公司的產品集合
    $dbName = config('databases.mongodb_furniture.dbname');
    
    // 列出所有集合以找到產品集合
    $listCollectionsCmd = new MongoDB\Driver\Command(['listCollections' => 1]);
    $collectionsCursor = $furnitureManager->executeCommand($dbName, $listCollectionsCmd);
    
    $allProducts = [];
    
    foreach ($collectionsCursor as $collectionInfo) {
        $collectionName = $collectionInfo->name;
        
        // 只處理以 "_product" 結尾的集合
        if (strpos($collectionName, '_product') !== false) {
            try {
                // 查詢該集合的產品
                $productFilter = ["status" => "active"];
                $productOptions = [
                    "sort" => ["created_at" => -1], // 按創建時間倒序
                    "limit" => 50 // 每個公司最多50個產品
                ];
                
                $productQuery = new MongoDB\Driver\Query($productFilter, $productOptions);
                $productCursor = $furnitureManager->executeQuery(
                    $dbName . "." . $collectionName,
                    $productQuery
                );
                
                foreach ($productCursor as $productDocument) {
                    $product = json_decode(json_encode($productDocument), true);
                    
                    // 構建產品資訊
                    $productInfo = [
                        'id' => $product['_id']['$oid'] ?? '',
                        'product_id' => $product['product_id'] ?? '',
                        'name' => $product['name'] ?? '',
                        'price' => $product['price'] ?? 0,
                        'category' => $product['category'] ?? '',
                        'description' => $product['description'] ?? '',
                        'url' => $product['url'] ?? '',
                        'brand' => $product['brand'] ?? '',
                        // 'size_options' => $product['size_options'] ?? null,
                        'width' => $product['width'] ?? null,
                        'height' => $product['height'] ?? null,
                        'depth' => $product['depth'] ?? null,
                        'created_at' => $product['created_at'] ?? null,
                        'updated_at' => $product['updated_at'] ?? null
                    ];
                    
                    // 處理圖片
                    $images = [];
                    if (isset($product['images']) && is_array($product['images'])) {
                        foreach ($product['images'] as $image) {
                            if (is_array($image) && isset($image['file_id'])) {
                                // GridFS檔案ID
                                $images[] = [
                                    'file_id' => $image['file_id'],
                                    'filename' => $image['filename'] ?? '',
                                    'original_filename' => $image['original_filename'] ?? '',
                                    'content_type' => $image['content_type'] ?? '',
                                    'image_index' => $image['image_index'] ?? 0,
                                    'url' => '../php/get_image.php?file_id=' . $image['file_id']
                                ];
                            }
                        }
                    }
                    $productInfo['images'] = $images;
                    
                    // 處理 GridFS 模型文件ID
                    if (isset($product['model_file_id'])) {
                        $productInfo['model_file_id'] = $product['model_file_id']['$oid'] ?? $product['model_file_id'];
                        // 構建模型URL，指向公開的GridFS文件服務
                        $productInfo['model_url'] = '../php/get_model.php?file_id=' . $productInfo['model_file_id'];
                    }
                    
                    $allProducts[] = $productInfo;
                }
                
            } catch (Exception $e) {
                // 跳過有問題的集合，繼續處理其他集合
                error_log("處理集合 {$collectionName} 時發生錯誤: " . $e->getMessage());
                continue;
            }
        }
    }
    
    // 按創建時間排序所有產品
    usort($allProducts, function($a, $b) {
        // 處理創建時間，支援多種MongoDB日期格式
        $timeA = 0;
        $timeB = 0;
        
        if (isset($a['created_at'])) {
            if (is_array($a['created_at']) && isset($a['created_at']['$date'])) {
                // 處理MongoDB BSON日期格式
                if (is_array($a['created_at']['$date']) && isset($a['created_at']['$date']['$numberLong'])) {
                    // MongoDB BSON格式: {"$date":{"$numberLong":"1748584928975"}}
                    $timeA = (int)$a['created_at']['$date']['$numberLong'] / 1000; // 轉換毫秒為秒
                } else if (is_numeric($a['created_at']['$date'])) {
                    $timeA = $a['created_at']['$date'];
                } else if (is_string($a['created_at']['$date'])) {
                    $timeA = strtotime($a['created_at']['$date']);
                }
            } else if (is_string($a['created_at'])) {
                $timeA = strtotime($a['created_at']);
            } else if (is_numeric($a['created_at'])) {
                $timeA = $a['created_at'];
            }
        }
        
        if (isset($b['created_at'])) {
            if (is_array($b['created_at']) && isset($b['created_at']['$date'])) {
                // 處理MongoDB BSON日期格式
                if (is_array($b['created_at']['$date']) && isset($b['created_at']['$date']['$numberLong'])) {
                    // MongoDB BSON格式: {"$date":{"$numberLong":"1748584928975"}}
                    $timeB = (int)$b['created_at']['$date']['$numberLong'] / 1000; // 轉換毫秒為秒
                } else if (is_numeric($b['created_at']['$date'])) {
                    $timeB = $b['created_at']['$date'];
                } else if (is_string($b['created_at']['$date'])) {
                    $timeB = strtotime($b['created_at']['$date']);
                }
            } else if (is_string($b['created_at'])) {
                $timeB = strtotime($b['created_at']);
            } else if (is_numeric($b['created_at'])) {
                $timeB = $b['created_at'];
            }
        }
        
        // 確保時間戳是數值型別
        $timeA = is_numeric($timeA) ? (int)$timeA : 0;
        $timeB = is_numeric($timeB) ? (int)$timeB : 0;
        
        return $timeB - $timeA; // 降序排列（最新的在前面）
    });

    // 返回成功結果
    echo json_encode([
        "success" => true,
        "products" => $allProducts,
        "total_count" => count($allProducts),
        "message" => "公開產品列表載入成功"
    ]);

} catch (MongoDB\Driver\Exception\Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "資料庫錯誤: " . $e->getMessage(),
        "products" => []
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage(),
        "products" => []
    ]);
}
?> 