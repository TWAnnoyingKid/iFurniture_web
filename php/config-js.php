<?php
/**
 * 動態生成前端 JavaScript 配置
 * 這個文件應該通過 <script src="config-js.php"></script> 引入
 */

// 設置正確的內容類型
header('Content-Type: application/javascript');

// 引入 PHP 配置文件
require_once 'config.php';

// 避免緩存
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
?>

const CONFIG = {
    // API 服務器設置
    api: {
        host: '<?= config('api.host') ?>',
        port: <?= config('api.port') ?>,
        protocol: '<?= config('api.protocol') ?>',
        baseUrl: '<?= config('api.base_url') ?>'
    },
    
    // 模型查看器設置
    modelViewer: {
        defaultExposure: 1.2,
        defaultShadowIntensity: 0.6,
        defaultShadowSoftness: 0.8,
        autoRotate: true
    },
    
    // 圖片上傳設置
    upload: {
        maxFileSize: 5 * 1024 * 1024, // 5MB
        allowedTypes: ['image/jpeg', 'image/png', 'image/gif'],
        maxFiles: 20
    },
    
    
    // 系統信息
    system: {
        environment: '<?= $_SERVER['SERVER_NAME'] ?>',
        timezone: '<?= config('settings.timezone') ?>',
        version: '1.0.0'
    }
};

// 凍結配置對象，防止意外修改
Object.freeze(CONFIG);