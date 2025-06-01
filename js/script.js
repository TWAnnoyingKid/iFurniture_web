document.addEventListener('DOMContentLoaded', function() {
    // 導航欄邏輯已移至 nav.js，此處不再重複處理
    
    // 載入並顯示產品資料
    loadProducts();
    
    // 滾動行為邏輯已移至 nav.js，此處不再處理
});

/**
 * 載入並顯示產品資料
 */  
async function loadProducts() {
    const productListContainer = document.getElementById('product-list-container');
    if (!productListContainer) {
        console.log('未找到產品列表容器，可能不在產品頁面');
        return;
    }

    try {
        // 從MongoDB公開API載入產品資料
        const response = await fetch('../php/products.php?action=get_list', {
            method: 'GET',
            cache: 'no-cache'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP 錯誤: ${response.status}`);
        }
        
        const result = await response.json();
        
        // 檢查API回應格式
        if (!result.success) {
            throw new Error(result.message || '獲取商品失敗');
        }
        
        const products = result.products || [];

        productListContainer.innerHTML = ''; // 清空現有內容

        // 商品類別過濾邏輯
        const products_Cat = products.filter(product => 
            product.category === 'chair' || 
            product.category === 'sofa' || 
            product.category === 'desk' || 
            product.category === 'cabinet'
        );
        

        // 產品渲染邏輯
        products_Cat.forEach(product => {
            const productItem = document.createElement('div');
            productItem.classList.add('product-item');

            // 產品主圖片處理 - 支援GridFS格式
            let mainImageUrl = 'placeholder.jpg'; // 預設圖片
            if (product.images && product.images.length > 0) {
                const firstImage = product.images[0];
                if (typeof firstImage === 'object' && firstImage.url) {
                    // 新格式：GridFS圖片物件
                    mainImageUrl = firstImage.url;
                }
            }

            // 產品尺寸處理width/height/depth
            let sizeDisplay = '';
            if (product.width != null || product.height != null || product.depth != null) {
                const dimensions = [];
                if (product.width != null && product.width > 0) dimensions.push(`寬${product.width}cm`);
                if (product.height != null && product.height > 0) dimensions.push(`高${product.height}cm`);
                if (product.depth != null && product.depth > 0) dimensions.push(`深${product.depth}cm`);
                
                if (dimensions.length > 0) {
                    sizeDisplay = dimensions.join(' × ');
                } else {
                    sizeDisplay = '尺寸待更新';
                }
            } 

            // 尺寸參數處理 - 更新為新格式
            let sizeParams = "";
            if (product.width != null && product.width > 0) sizeParams += `&targetWidthCm=${product.width}`;
            if (product.height != null && product.height > 0) sizeParams += `&targetHeightCm=${product.height}`;
            if (product.depth != null && product.depth > 0) sizeParams += `&targetDepthCm=${product.depth}`;

            // 建構圖片畫廊HTML - 支援GridFS格式
            let galleryHtml = '';
            if (product.images && product.images.length > 0) {
                galleryHtml = product.images.map(image => {
                    let imageUrl, altText;
                    if (typeof image === 'object' && image.url) {
                        // 新格式：GridFS圖片物件
                        imageUrl = image.url;
                        altText = image.original_filename || image.filename || (product.name + '產品圖片');
                    } else if (typeof image === 'string') {
                        // 舊格式：直接的圖片路徑
                        imageUrl = image;
                        altText = product.name + '產品圖片';
                    } else {
                        return ''; // 跳過無效的圖片資料
                    }
                    
                    const isActive = imageUrl === mainImageUrl ? 'active' : '';
                    return `<img src="${imageUrl}" alt="${altText}" class="gallery-image ${isActive}">`;
                }).join('');
            } else {
                galleryHtml = '<p class="no-gallery-images">無產品圖片</p>';
            }

            // 價格顯示適配MongoDB格式
            const priceDisplay = product.price ? `NT$ ${product.price.toLocaleString()}` : 'NT$ 洽詢';

            productItem.innerHTML = `
                <div class="product-image-section">
                    <img src="${mainImageUrl}" alt="${product.name || '產品圖片'}" class="product-main-image">
                    <div class="gallery-container">
                        <button class="gallery-scroll-arrow prev-arrow" aria-label="上一張圖片">&lt;</button>
                        <div class="product-image-gallery">
                            ${galleryHtml}
                        </div>
                        <button class="gallery-scroll-arrow next-arrow" aria-label="下一張圖片">&gt;</button>
                    </div>
                </div>
                <h3>${product.name || '未知產品'}</h3>
                <p class="product-price">${priceDisplay}</p>
                <p class="product-size">尺寸：${sizeDisplay || '未提供'}</p>
                <div class="product-links">
                    <a href="${product.url || '#'}" target="_blank" class="product-link">查看產品網站</a>
                    <a href="../web/3D_model_page.html?modelUrl=${encodeURIComponent(product.model_url || '')}${sizeParams}" target="_blank" class="product-model-link">查看3D模型</a>
                </div>
            `;
            productListContainer.appendChild(productItem);

            // 附圖點擊更換主圖
            setupProductGallery(productItem);
        });

    } catch (error) {
        console.error('無法載入產品資料:', error);
        if (productListContainer) {
            productListContainer.innerHTML = `
                <div class="error-message" style="text-align: center; padding: 40px; background-color: #f8f9fa; border-radius: 8px; margin: 20px;">
                    <h3 style="color: #dc3545; margin-bottom: 16px;">載入產品資料失敗</h3>
                    <p style="color: #6c757d; margin-bottom: 20px;">${error.message}</p>
                    <button onclick="loadProducts()" style="background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">重新載入</button>
                </div>
            `;
        }
    }
}

// 產品圖片庫邏輯保留在這裡，因為是產品專用的功能
function setupProductGallery(productItem) {
    const mainImageElement = productItem.querySelector('.product-main-image');
    const galleryImages = productItem.querySelectorAll('.gallery-image');

    // 讓附圖直接更新主圖
    galleryImages.forEach(galleryImg => {
        galleryImg.addEventListener('click', () => {
            if (mainImageElement) {
                mainImageElement.src = galleryImg.src;
                mainImageElement.alt = galleryImg.alt; // 同時更新 alt 文字

                // 更新 active class
                galleryImages.forEach(img => img.classList.remove('active'));
                galleryImg.classList.add('active');
            }
        });
    });

    // 附圖庫左右箭頭滾動
    const galleryScrollContainer = productItem.querySelector('.product-image-gallery');
    const prevArrowButton = productItem.querySelector('.prev-arrow');
    const nextArrowButton = productItem.querySelector('.next-arrow');

    function updateGalleryArrows() {
        if (!galleryScrollContainer || !prevArrowButton || !nextArrowButton) return;
        
        const scrollLeft = galleryScrollContainer.scrollLeft;
        const scrollWidth = galleryScrollContainer.scrollWidth;
        const clientWidth = galleryScrollContainer.clientWidth;

        // 檢查是否有足夠內容來滾動
        if (scrollWidth <= clientWidth) {
            prevArrowButton.classList.add('hidden');
            nextArrowButton.classList.add('hidden');
            return;
        }

        prevArrowButton.classList.toggle('hidden', scrollLeft <= 0);
        nextArrowButton.classList.toggle('hidden', scrollLeft >= (scrollWidth - clientWidth -1)); // -1 for subpixel precision
    }

    if (galleryScrollContainer && prevArrowButton && nextArrowButton) {
        updateGalleryArrows(); // 初始狀態

        prevArrowButton.addEventListener('click', () => {
            const imageWidth = galleryScrollContainer.querySelector('.gallery-image')?.offsetWidth || 50;
            const gap = 6; // 與 CSS 中的 gap 一致
            galleryScrollContainer.scrollBy({ left: -(imageWidth + gap) * 2, behavior: 'smooth' }); // 滾動兩張圖片的寬度
        });

        nextArrowButton.addEventListener('click', () => {
            const imageWidth = galleryScrollContainer.querySelector('.gallery-image')?.offsetWidth || 50;
            const gap = 6;
            galleryScrollContainer.scrollBy({ left: (imageWidth + gap) * 2, behavior: 'smooth' });
        });

        galleryScrollContainer.addEventListener('scroll', updateGalleryArrows);
        setTimeout(updateGalleryArrows, 500); 
    }
}

/**
 * 解析尺寸字串
 */
function parseProductDimensions(sizeOptionsArray) {
    const dimensions = { widthCm: null, heightCm: null, depthCm: null };
    if (!sizeOptionsArray || sizeOptionsArray.length === 0 || typeof sizeOptionsArray[0] !== 'string') {
        return dimensions;
    }
    const sizeString = sizeOptionsArray[0]; // 假設主要尺寸資訊在第一個字串元素中

    // 解析寬度 (W)
    const widthMatch = sizeString.match(/(\d+(\.\d+)?)\s*寬/);
    if (widthMatch) dimensions.widthCm = parseFloat(widthMatch[1]);

    // 解析高度 (H)
    const heightMatch = sizeString.match(/(\d+(\.\d+)?)\s*高/);
    if (heightMatch) dimensions.heightCm = parseFloat(heightMatch[1]);

    // 解析深度 (D)
    const depthMatch = sizeString.match(/(\d+(\.\d+)?)\s*深/);
    if (depthMatch) dimensions.depthCm = parseFloat(depthMatch[1]);

    return dimensions;
}