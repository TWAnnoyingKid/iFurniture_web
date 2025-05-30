<?php
/**
 * 儲存商品資料到 MongoDB
 * 將商品資訊儲存到 furniture_db/{使用者公司}_product collection
 */

session_start();
header('Content-Type: application/json');

error_log("POST資料: " . print_r($_POST, true));
error_log("FILES資料: " . print_r($_FILES, true));

// 引入配置文件
require_once 'config.php';

// 檢查用戶是否已登入
if (!isset($_SESSION["login"]) || $_SESSION["login"] !== true) {
    echo json_encode(["success" => false, "message" => "用戶未登入"]);
    exit;
}

// 獲取登入的用戶名
$username = isset($_SESSION["username"]) ? $_SESSION["username"] : "";

if (empty($username)) {
    echo json_encode(["success" => false, "message" => "無法獲取用戶名"]);
    exit;
}

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "僅支援 POST 請求"]);
    exit;
}

try {
    // 檢查是否安裝了 MongoDB 擴展
    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB 擴展未安裝");
    }

    // 連接到 MongoDB
    $manager = new MongoDB\Driver\Manager(config('databases.mongodb.connection_string'));

    // **第一步：獲取使用者的公司資訊**
    $userFilter = ["account" => $username];
    $userQuery = new MongoDB\Driver\Query($userFilter, []);
    $userCursor = $manager->executeQuery(
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
    $collectionName = $collectionSuffix . "_product";

    // **【修改部分】第二步：從前端獲取產品資訊和資料夾結構**
    $productName = $_POST['name'] ?? '';
    $productId = $_POST['product_id'] ?? '';  // **【新增】從前端獲取產品序號**
    $productFolder = $_POST['product_folder'] ?? '';  // **【新增】從前端獲取資料夾名稱**
    
    if (empty($productName)) {
        throw new Exception("商品名稱為必填欄位");
    }
    
    // **【修改】如果沒有提供產品資訊，則生成新的（向後兼容）**
    if (empty($productId)) {
        $productId = uniqid();
        $cleanProductName = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fff}]/u', '_', $productName);
        $productFolderName = $cleanProductName . "_product_" . $productId;
    } else {
        $productFolderName = $productFolder ?: $productName . "_product_" . $productId;
    }
    
    // 基礎路徑
    $baseDir = '../uploads/products/';
    $productDir = $baseDir . $productFolderName . '/';
    $imagesDir = $productDir . 'images/';
    $modelDir = $productDir . 'model/';
    
    // **【修改】檢查資料夾是否已存在（由 Python 端創建），不存在則創建**
    if (!file_exists($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    if (!file_exists($productDir)) {
        mkdir($productDir, 0755, true);
    }
    if (!file_exists($imagesDir)) {
        mkdir($imagesDir, 0755, true);
    }
    if (!file_exists($modelDir)) {
        mkdir($modelDir, 0755, true);
    }

    // **【修改部分】第三步：使用 GridFS 處理商品圖片**
    $uploadedImageIds = [];
    
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $imageFiles = $_FILES['images'];
        $fileCount = count($imageFiles['name']);
        
        // 連接到 GridFS
        $furnitureManager = new MongoDB\Driver\Manager(config('databases.mongodb_furniture.connection_string'));
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($imageFiles['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $imageFiles['name'][$i];
                $tempName = $imageFiles['tmp_name'][$i];
                $fileSize = $imageFiles['size'][$i];
                $fileType = $imageFiles['type'][$i];
                
                // 驗證檔案類型
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($fileType, $allowedTypes)) {
                    continue; // 跳過不支援的檔案類型
                }
                
                // 驗證檔案大小 (5MB)
                if ($fileSize > 5 * 1024 * 1024) {
                    continue; // 跳過過大的檔案
                }
                
                try {
                    // 讀取圖片內容
                    $imageContent = file_get_contents($tempName);
                    if ($imageContent === false) {
                        error_log("無法讀取圖片檔案: " . $fileName);
                        continue;
                    }
                    
                    // 生成新的圖片檔案名稱
                    $imageNumber = $i + 1;
                    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $newImageName = "img" . $imageNumber . "." . $fileExtension;
                    
                    // 生成新的文件ID
                    $imageFileId = new MongoDB\BSON\ObjectId();
                    
                    // 創建文件元數據
                    $imageMetadata = [
                        '_id' => $imageFileId,
                        'filename' => $newImageName,
                        'length' => strlen($imageContent),
                        'chunkSize' => 261120, // 默認chunk大小
                        'uploadDate' => new MongoDB\BSON\UTCDateTime(),
                        'metadata' => [
                            'product_id' => $productId,
                            'product_folder' => $productFolderName,
                            'original_filename' => $fileName,
                            'content_type' => $fileType,
                            'image_index' => $imageNumber,
                            'created_by' => $username,
                            'created_at' => new MongoDB\BSON\UTCDateTime(),
                            'file_type' => 'product_image'
                        ]
                    ];
                    
                    // 將文件分塊並儲存
                    $chunkSize = 261120; // 255KB
                    $chunks = str_split($imageContent, $chunkSize);
                    
                    // 使用 bulk write 操作插入文件元數據
                    $bulk = new MongoDB\Driver\BulkWrite;
                    $bulk->insert($imageMetadata);
                    $result = $furnitureManager->executeBulkWrite(
                        config('databases.mongodb_furniture.dbname') . ".fs.images.files",
                        $bulk
                    );
                    
                    // 插入文件塊到 fs.images.chunks
                    $chunkBulk = new MongoDB\Driver\BulkWrite;
                    foreach ($chunks as $n => $chunk) {
                        $chunkDoc = [
                            '_id' => new MongoDB\BSON\ObjectId(),
                            'files_id' => $imageFileId,
                            'n' => $n,
                            'data' => new MongoDB\BSON\Binary($chunk, MongoDB\BSON\Binary::TYPE_GENERIC)
                        ];
                        $chunkBulk->insert($chunkDoc);
                    }
                    
                    $chunkResult = $furnitureManager->executeBulkWrite(
                        config('databases.mongodb_furniture.dbname') . ".fs.images.chunks",
                        $chunkBulk
                    );
                    
                    // 儲存圖片檔案ID和相關資訊
                    $uploadedImageIds[] = [
                        'file_id' => (string)$imageFileId,
                        'filename' => $newImageName,
                        'original_filename' => $fileName,
                        'content_type' => $fileType,
                        'image_index' => $imageNumber
                    ];
                    
                    error_log("圖片已儲存到GridFS，檔案ID: " . $imageFileId . "，檔案名: " . $newImageName);
                    
                } catch (Exception $e) {
                    error_log("GridFS 圖片儲存失敗: " . $e->getMessage() . "，檔案: " . $fileName);
                    continue; // 繼續處理下一張圖片
                }
            }
        }
    }

    // **【修改部分】第四步：使用 GridFS 處理 3D 模型檔案**
    $modelUrl = $_POST['model_url'] ?? '';
    $modelFileId = null;
    
    if (!empty($modelUrl)) {
        try {
            // **【修正】使用原生MongoDB驅動進行GridFS操作**
            $furnitureManager = new MongoDB\Driver\Manager(config('databases.mongodb_furniture.connection_string'));
            
            // 獲取模型文件內容
            $modelContent = null;
            $originalFileName = '';
            
            if (strpos($modelUrl, '/uploads/products/') === 0) {
                // 本地路徑，讀取文件
                $fullPath = '../' . $modelUrl;
                if (file_exists($fullPath)) {
                    $modelContent = file_get_contents($fullPath);
                    $originalFileName = basename($fullPath);
                    error_log("讀取本地模型文件: " . $fullPath);
                } else {
                    error_log("本地模型文件不存在: " . $fullPath);
                }
            } else {
                // 外部URL，下載文件
                $modelContent = file_get_contents($modelUrl);
                $originalFileName = $productFolderName . ".glb";
                error_log("從URL下載模型文件: " . $modelUrl);
            }
            
            if ($modelContent !== false && !empty($modelContent)) {
                // **【修正】使用原生MongoDB驅動的GridFS操作**
                // 生成新的文件ID
                $modelFileId = new MongoDB\BSON\ObjectId();
                
                // 創建文件元數據
                $fileMetadata = [
                    '_id' => $modelFileId,
                    'filename' => $originalFileName,
                    'length' => strlen($modelContent),
                    'chunkSize' => 261120, // 默認chunk大小
                    'uploadDate' => new MongoDB\BSON\UTCDateTime(),
                    'metadata' => [
                        'product_id' => $productId,
                        'product_folder' => $productFolderName,
                        'original_filename' => $originalFileName,
                        'content_type' => 'model/gltf-binary',
                        'created_by' => $username,
                        'created_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ];
                
                // 將文件分塊並儲存
                $chunkSize = 261120; // 255KB
                $chunks = str_split($modelContent, $chunkSize);
                
                // 使用 bulk write 操作
                $bulk = new MongoDB\Driver\BulkWrite;
                
                // 插入文件元數據到 fs.models.files
                $bulk->insert($fileMetadata);
                $result = $furnitureManager->executeBulkWrite(
                    config('databases.mongodb_furniture.dbname') . ".fs.models.files",
                    $bulk
                );
                
                // 插入文件塊到 fs.models.chunks
                $chunkBulk = new MongoDB\Driver\BulkWrite;
                foreach ($chunks as $n => $chunk) {
                    $chunkDoc = [
                        '_id' => new MongoDB\BSON\ObjectId(),
                        'files_id' => $modelFileId,
                        'n' => $n,
                        'data' => new MongoDB\BSON\Binary($chunk, MongoDB\BSON\Binary::TYPE_GENERIC)
                    ];
                    $chunkBulk->insert($chunkDoc);
                }
                
                $chunkResult = $furnitureManager->executeBulkWrite(
                    config('databases.mongodb_furniture.dbname') . ".fs.models.chunks",
                    $chunkBulk
                );
                
                error_log("3D模型文件已儲存到GridFS，文件ID: " . $modelFileId);
                
                // 刪除本地文件（如果存在）
                if (strpos($modelUrl, '/uploads/products/') === 0) {
                    $fullPath = '../' . $modelUrl;
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                        error_log("已刪除本地模型文件: " . $fullPath);
                    }
                }
            } else {
                error_log("無法獲取模型文件內容");
                throw new Exception("無法獲取3D模型文件內容");
            }
            
        } catch (Exception $e) {
            error_log("GridFS 操作失敗: " . $e->getMessage());
            throw new Exception("儲存3D模型到資料庫失敗: " . $e->getMessage());
        }
    }

    // **第五步：準備商品資料**
    $productData = [
        'product_id' => $productId, // 唯一產品序號
        'name' => $productName,
        'price' => isset($_POST['price']) ? (float)$_POST['price'] : 0,
        'category' => $_POST['category'] ?? '',
        'description' => $_POST['description'] ?? '',
        'url' => $_POST['url'] ?? '',
        'model_file_id' => $modelFileId, // 儲存模型文件ID
        'brand' => $userCompany,
        'images' => $uploadedImageIds, // 只儲存模態對話框中選擇的圖片
        'width' => isset($_POST['width']) ? (float)$_POST['width'] : 0,
        'height' => isset($_POST['height']) ? (float)$_POST['height'] : 0,
        'depth' => isset($_POST['depth']) ? (float)$_POST['depth'] : 0,
        'folder_path' => 'uploads/products/' . $productFolderName, // 產品資料夾路徑
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
        'created_by' => $username,
        'status' => 'active'
    ];

    // 驗證必填欄位
    if (empty($productData['name'])) {
        throw new Exception("商品名稱為必填欄位");
    }
    if (empty($productData['category'])) {
        throw new Exception("商品種類為必填欄位");
    }
    if ($productData['price'] <= 0) {
        throw new Exception("商品價格必須大於 0");
    }

    // **第六步：將商品資料插入到 MongoDB**
    $bulk = new MongoDB\Driver\BulkWrite;
    $insertedId = $bulk->insert($productData);
    
    $result = $manager->executeBulkWrite(
        "furniture_db." . $collectionName,
        $bulk
    );

    // 檢查插入結果
    if ($result->getInsertedCount() > 0) {
        // 構建模型URL，指向GridFS文件服務
        $modelUrl = '';
        if ($modelFileId) {
            $modelUrl = '../php/get_model_file.php?file_id=' . (string)$modelFileId;
        }
        
        // 成功插入，返回成功回應
        $response = [
            "success" => true,
            "message" => "商品儲存成功",
            "product" => [
                "id" => (string)$insertedId,
                    "product_id" => $productId, // 回傳產品序號
                "name" => $productData['name'],
                "price" => $productData['price'],
                "category" => $productData['category'],
                "brand" => $productData['brand'],
                "collection" => $collectionName,
                "folder_path" => $productData['folder_path'], //回傳資料夾路徑
                "model_file_id" => (string)$modelFileId, //回傳GridFS文件ID
                "model_url" => $modelUrl, //回傳GridFS模型URL
                "images_count" => count($uploadedImageIds)
            ]
        ];
        
        echo json_encode($response);
    } else {
        throw new Exception("商品儲存失敗，未知錯誤");
    }

} catch (MongoDB\Driver\Exception\Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "MongoDB 錯誤: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage()
    ]);
}
?>