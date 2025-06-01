<?php
/**
 * 統一的商品管理系統
 * 整合商品的增刪改查功能
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// 引入配置文件
require_once 'config.php';
require_once 'auth_helper.php';

// 獲取請求方法和動作
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 根據請求方法自動判斷動作
if (empty($action)) {
    switch ($method) {
        case 'GET':
            $action = isset($_GET['id']) ? 'get_details' : 'get_list';
            break;
        case 'POST':
            $action = 'create';
            break;
        case 'PUT':
        case 'PATCH':
            $action = 'update';
            break;
        case 'DELETE':
            $action = 'delete';
            break;
    }
}

try {
    switch ($action) {
        case 'get_list':
            handleGetProducts();
            break;
        case 'get_my_list':
            handleGetMyProducts();
            break;
        case 'get_details':
            handleGetProductDetails();
            break;
        case 'create':
        case 'save':
            handleSaveProduct();
            break;
        case 'update':
            handleUpdateProduct();
            break;
        case 'delete':
            handleDeleteProduct();
            break;
        default:
            throw new Exception("不支援的操作: " . $action);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

// 獲取商品列表（公開，無需登入）
function handleGetProducts() {
    // 檢查是否安裝了 MongoDB 擴展
    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB 擴展未安裝");
    }

    // 連接到商品資料庫
    $furnitureManager = new MongoDB\Driver\Manager(config('databases.mongodb_furniture.connection_string'));
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
                    "sort" => ["created_at" => -1],
                    "limit" => 50
                ];
                
                $productQuery = new MongoDB\Driver\Query($productFilter, $productOptions);
                $productCursor = $furnitureManager->executeQuery(
                    $dbName . "." . $collectionName,
                    $productQuery
                );
                
                foreach ($productCursor as $productDocument) {
                    $product = json_decode(json_encode($productDocument), true);
                    $allProducts[] = formatProductData($product);
                }
                
            } catch (Exception $e) {
                error_log("處理集合 {$collectionName} 時發生錯誤: " . $e->getMessage());
                continue;
            }
        }
    }
    
    // 按創建時間排序所有產品
    usort($allProducts, function($a, $b) {
        return parseMongoDate($b['created_at']) - parseMongoDate($a['created_at']);
    });

    echo json_encode([
        "success" => true,
        "products" => $allProducts,
        "total_count" => count($allProducts),
        "message" => "商品列表載入成功"
    ]);
}

function handleGetMyProducts() {
    session_start();
    
    // 檢查用戶是否已登入，使用輔助函數自動恢復登入狀態
    $username = requireLogin(false); // 拋出異常而不是JSON回應
    
    // 檢查是否安裝了 MongoDB 擴展
    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB 擴展未安裝");
    }

    // 連接到用戶資料庫獲取公司信息
    $userManager = new MongoDB\Driver\Manager(config('databases.mongodb.connection_string'));
    $userFilter = ["account" => $username];
    $userQuery = new MongoDB\Driver\Query($userFilter, []);
    $userCursor = $userManager->executeQuery(
        config('databases.mongodb.dbname') . ".profiles", 
        $userQuery
    );

    $userCompany = "default"; // 預設公司名稱
    foreach ($userCursor as $userDocument) {
        if (isset($userDocument->company) && !empty($userDocument->company)) {
            $userCompany = $userDocument->company;
        }
        break;
    }

    // 清理公司名稱，移除特殊字符，用於 collection 名稱
    $collectionSuffix = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fff}]/u', '_', $userCompany);
    $targetCollectionName = $collectionSuffix . "_product";

    // 連接到商品資料庫
    $furnitureManager = new MongoDB\Driver\Manager(config('databases.mongodb_furniture.connection_string'));
    $dbName = config('databases.mongodb_furniture.dbname');
    
    $allProducts = [];
    
    try {
        // 直接查詢該用戶公司的商品集合
        $productFilter = ["status" => "active"];
        $productOptions = [
            "sort" => ["created_at" => -1],
            "limit" => 100 // 增加限制數量，因為只查詢自己的商品
        ];
        
        $productQuery = new MongoDB\Driver\Query($productFilter, $productOptions);
        $productCursor = $furnitureManager->executeQuery(
            $dbName . "." . $targetCollectionName,
            $productQuery
        );
        
        foreach ($productCursor as $productDocument) {
            $product = json_decode(json_encode($productDocument), true);
            // 額外檢查是否為該用戶創建的商品
            if (isset($product['created_by']) && $product['created_by'] === $username) {
                $allProducts[] = formatProductData($product);
            }
        }
        
    } catch (Exception $e) {
        // 如果集合不存在或查詢失敗，記錄錯誤但不拋出異常
        error_log("查詢用戶商品集合 {$targetCollectionName} 時發生錯誤: " . $e->getMessage());
        // 繼續執行，返回空列表
    }
    
    // 按創建時間排序所有產品
    usort($allProducts, function($a, $b) {
        return parseMongoDate($b['created_at']) - parseMongoDate($a['created_at']);
    });

    echo json_encode([
        "success" => true,
        "products" => $allProducts,
        "total_count" => count($allProducts),
        "company" => $userCompany,
        "collection" => $targetCollectionName,
        "username" => $username,
        "message" => count($allProducts) > 0 ? "商品列表載入成功" : "您還沒有上傳任何商品"
    ]);
}

//  獲取商品詳細資訊
function handleGetProductDetails() {
    $productId = $_GET['id'] ?? '';
    
    if (empty($productId)) {
        throw new Exception("缺少商品ID參數");
    }

    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB 擴展未安裝");
    }

    $furnitureManager = getFurnitureDbConnection();
    $dbName = getFurnitureDbName();
    
    $objectId = new MongoDB\BSON\ObjectId($productId);
    
    // 列出所有集合以找到產品集合
    $listCollectionsCmd = new MongoDB\Driver\Command(['listCollections' => 1]);
    $collectionsCursor = $furnitureManager->executeCommand($dbName, $listCollectionsCmd);
    
    $productData = null;
    
    foreach ($collectionsCursor as $collectionInfo) {
        $collectionName = $collectionInfo->name;
        
        if (strpos($collectionName, '_product') !== false) {
            try {
                $productFilter = ["_id" => $objectId];
                $productQuery = new MongoDB\Driver\Query($productFilter);
                $productCursor = $furnitureManager->executeQuery(
                    $dbName . "." . $collectionName,
                    $productQuery
                );
                
                foreach ($productCursor as $productDocument) {
                    $product = json_decode(json_encode($productDocument), true);
                    $productData = formatProductDetailsData($product, $collectionName);
                    break 2;
                }
                
            } catch (Exception $e) {
                error_log("處理集合 {$collectionName} 時發生錯誤: " . $e->getMessage());
                continue;
            }
        }
    }
    
    if ($productData) {
        echo json_encode([
            "success" => true,
            "product" => $productData,
            "message" => "商品詳細資訊載入成功"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "找不到指定的商品",
            "product" => null
        ]);
    }
}

// 儲存新商品
function handleSaveProduct() {
    session_start();
    
    // 檢查用戶是否已登入，使用輔助函數自動恢復登入狀態
    $username = requireLogin(false); // 使用 exception 而不是 JSON 回應

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("僅支援 POST 請求");
    }

    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB 擴展未安裝");
    }

    // 獲取用戶公司資訊
    $manager = new MongoDB\Driver\Manager(config('databases.mongodb.connection_string'));
    $userFilter = ["account" => $username];
    $userQuery = new MongoDB\Driver\Query($userFilter, []);
    $userCursor = $manager->executeQuery(
        config('databases.mongodb.dbname') . ".profiles", 
        $userQuery
    );

    $userCompany = "default";
    foreach ($userCursor as $userDocument) {
        if (isset($userDocument->company) && !empty($userDocument->company)) {
            $userCompany = $userDocument->company;
        }
        break;
    }

    $collectionSuffix = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fff}]/u', '_', $userCompany);
    $collectionName = $collectionSuffix . "_product";

    // 處理商品資料和檔案上傳
    $productData = processProductData($_POST, $_FILES, $username, $userCompany);
    
    // 驗證必填欄位
    validateProductData($productData);

    // 插入到 MongoDB
    $furnitureManager = getFurnitureDbConnection();
    $bulk = new MongoDB\Driver\BulkWrite();
    $insertedId = $bulk->insert($productData);
    
    $result = $furnitureManager->executeBulkWrite(
        "furniture_db." . $collectionName,
        $bulk
    );

    if ($result->getInsertedCount() > 0) {
        $modelUrl = '';
        if ($productData['model_file_id']) {
            $modelUrl = '../php/gridfs_file.php?file_id=' . (string)$productData['model_file_id'] . '&type=model';
        }
        
        echo json_encode([
            "success" => true,
            "message" => "商品儲存成功",
            "product" => [
                "id" => (string)$insertedId,
                "product_id" => $productData['product_id'],
                "name" => $productData['name'],
                "price" => $productData['price'],
                "category" => $productData['category'],
                "brand" => $productData['brand'],
                "collection" => $collectionName,
                "model_file_id" => (string)$productData['model_file_id'],
                "model_url" => $modelUrl,
                "images_count" => count($productData['images'])
            ]
        ]);
    } else {
        throw new Exception("商品儲存失敗，未知錯誤");
    }
}

// 更新商品資訊
function handleUpdateProduct() {
    // 實作商品更新邏輯
    // 這裡可以整合原有的 update_product.php 邏輯
    throw new Exception("更新功能尚未實作");
}

// 刪除商品
function handleDeleteProduct() {
    // 實作商品刪除邏輯
    throw new Exception("刪除功能尚未實作");
}

// 格式化商品資料（列表用）
function formatProductData($product) {
    $productInfo = [
        'id' => $product['_id']['$oid'] ?? '',
        'product_id' => $product['product_id'] ?? '',
        'name' => $product['name'] ?? '',
        'price' => $product['price'] ?? 0,
        'category' => $product['category'] ?? '',
        'description' => $product['description'] ?? '',
        'url' => $product['url'] ?? '',
        'brand' => $product['brand'] ?? '',
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
                $images[] = [
                    'file_id' => $image['file_id'],
                    'filename' => $image['filename'] ?? '',
                    'original_filename' => $image['original_filename'] ?? '',
                    'content_type' => $image['content_type'] ?? '',
                    'image_index' => $image['image_index'] ?? 0,
                    'url' => '../php/gridfs_file.php?file_id=' . $image['file_id'] . '&type=image'
                ];
            }
        }
    }
    $productInfo['images'] = $images;
    
    // 處理 GridFS 模型文件ID
    if (isset($product['model_file_id'])) {
        $productInfo['model_file_id'] = $product['model_file_id']['$oid'] ?? $product['model_file_id'];
        $productInfo['model_url'] = '../php/gridfs_file.php?file_id=' . $productInfo['model_file_id'] . '&type=model';
    }
    
    return $productInfo;
}

// 格式化商品詳細資料
function formatProductDetailsData($product, $collectionName) {
    $productData = [
        'id' => $product['_id']['$oid'] ?? '',
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
        'collection_name' => $collectionName
    ];
    
    // 處理尺寸
    $dimensions = [
        'width' => $product['dimensions']['width'] ?? $product['width'] ?? null,
        'height' => $product['dimensions']['height'] ?? $product['height'] ?? null,
        'depth' => $product['dimensions']['depth'] ?? $product['depth'] ?? null
    ];
    $productData['dimensions'] = $dimensions;
    
    // 處理圖片
    $images = [];
    if (isset($product['images']) && is_array($product['images'])) {
        foreach ($product['images'] as $image) {
            if (is_array($image) && isset($image['file_id'])) {
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
                    'url' => '../php/gridfs_file.php?file_id=' . $fileId . '&type=image'
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
            'url' => '../php/gridfs_file.php?file_id=' . $modelFileId . '&type=model'
        ];
        $productData['model'] = $modelData;
    }
    
    return $productData;
}

// 處理商品資料和檔案上傳
function processProductData($postData, $files, $username, $userCompany) {
    // 這裡可以整合原有的檔案處理邏輯
    // 包括 GridFS 圖片和模型檔案處理
    
    $productId = $postData['product_id'] ?? uniqid();
    
    $productData = [
        'product_id' => $productId,
        'name' => $postData['name'] ?? '',
        'price' => isset($postData['price']) ? (float)$postData['price'] : 0,
        'category' => $postData['category'] ?? '',
        'description' => $postData['description'] ?? '',
        'url' => $postData['url'] ?? '',
        'brand' => $userCompany,
        'width' => isset($postData['width']) ? (float)$postData['width'] : 0,
        'height' => isset($postData['height']) ? (float)$postData['height'] : 0,
        'depth' => isset($postData['depth']) ? (float)$postData['depth'] : 0,
        'images' => [],
        'model_file_id' => null,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
        'created_by' => $username,
        'status' => 'active'
    ];
    
    // TODO: 實作 GridFS 檔案處理邏輯
    
    return $productData;
}

// 驗證商品資料
function validateProductData($productData) {
    if (empty($productData['name'])) {
        throw new Exception("商品名稱為必填欄位");
    }
    if (empty($productData['category'])) {
        throw new Exception("商品種類為必填欄位");
    }
    if ($productData['price'] <= 0) {
        throw new Exception("商品價格必須大於 0");
    }
}

// 解析MongoDB日期格式
function parseMongoDate($dateField) {
    if (!$dateField) return 0;
    
    if (is_array($dateField) && isset($dateField['$date'])) {
        if (is_array($dateField['$date']) && isset($dateField['$date']['$numberLong'])) {
            return (int)$dateField['$date']['$numberLong'] / 1000;
        } else if (is_numeric($dateField['$date'])) {
            return $dateField['$date'];
        } else if (is_string($dateField['$date'])) {
            return strtotime($dateField['$date']);
        }
    } else if (is_string($dateField)) {
        return strtotime($dateField);
    } else if (is_numeric($dateField)) {
        return $dateField;
    }
    
    return 0;
}
?>