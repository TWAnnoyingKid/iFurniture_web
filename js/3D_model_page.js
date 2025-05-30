document.addEventListener('DOMContentLoaded', () => {
    const modelViewerElement = document.getElementById('modelViewerElement');
    
    if (modelViewerElement) {
        const urlParams = new URLSearchParams(window.location.search);
        const modelUrl = urlParams.get('modelUrl');
        const targetWidthCm = urlParams.get('targetWidthCm');
        const targetHeightCm = urlParams.get('targetHeightCm');
        const targetDepthCm = urlParams.get('targetDepthCm');

        if (modelUrl) {
            modelViewerElement.src = decodeURIComponent(modelUrl);
            console.log('載入模型:', decodeURIComponent(modelUrl));

            modelViewerElement.addEventListener('load', () => {
                console.log('模型已載入事件觸發');
                try {
                    // getDimensions() 方法
                    const intrinsicDimensions = modelViewerElement.getDimensions(); 
                    
                    if (intrinsicDimensions) {
                        console.log('模型固有尺寸 (公尺 - 來自 getDimensions):', intrinsicDimensions);
                        // intrinsicDimensions 是一個 Vector3-like 物件，包含 x, y, z

                        let scaleX = 1, scaleY = 1, scaleZ = 1;

                        if (targetWidthCm && intrinsicDimensions.x > 0) {
                            scaleX = (parseFloat(targetWidthCm) / 100) / intrinsicDimensions.x;
                        }
                        if (targetHeightCm && intrinsicDimensions.y > 0) {
                            scaleY = (parseFloat(targetHeightCm) / 100) / intrinsicDimensions.y;
                        }
                        if (targetDepthCm && intrinsicDimensions.z > 0) {
                            scaleZ = (parseFloat(targetDepthCm) / 100) / intrinsicDimensions.z;
                        }
                        
                        scaleX = isFinite(scaleX) && scaleX > 0 ? scaleX : 1;
                        scaleY = isFinite(scaleY) && scaleY > 0 ? scaleY : 1;
                        scaleZ = isFinite(scaleZ) && scaleZ > 0 ? scaleZ : 1;

                        modelViewerElement.scale = `${scaleX} ${scaleY} ${scaleZ}`;
                        console.log(`套用縮放比例: X=${scaleX}, Y=${scaleY}, Z=${scaleZ}`);

                        // 在設定縮放後更新攝影機取景
                        modelViewerElement.updateFraming();
                        console.log('已調用 updateFraming() 來調整視窗大小。');

                    } else {
                        console.warn('無法透過 getDimensions() 獲取模型的尺寸。模型將以預設大小顯示。');
                    }
                } catch (e) {
                    console.error('計算或套用模型縮放時發生錯誤:', e);
                }
            });

            modelViewerElement.addEventListener('error', (event) => {
                console.error('模型載入錯誤:', event.detail);
                const container = document.getElementById('model-viewer-container');
                if (container) {
                    container.innerHTML = '<p style="text-align: center; padding: 20px;">錯誤：模型檔案無法載入或格式錯誤。</p>';
                }
            });

        } else {
            console.error('找不到模型 URL 參數。');
            const container = document.getElementById('model-viewer-container');
            if (container) {
                container.innerHTML = '<p style="text-align: center; padding: 20px;">錯誤：找不到模型資料。請返回並重試。</p>';
            }
        }
    } else {
        console.error('找不到 model-viewer 元件。');
    }
}); 