<?php
/**
 * RECENTLY_VIEWED.PHP - TRANG HIỂN THỊ TẤT CẢ SẢN PHẨM ĐÃ XEM
 * Dữ liệu được lấy từ localStorage của trình duyệt.
 */
?>

<?php require_once __DIR__ . '/../partials/header.php'; ?>

<div class="container mx-auto px-4 py-8 min-h-screen">
    <!-- Breadcrumb -->
    <nav class="flex mb-6 text-sm text-gray-500 gap-2 items-center">
        <a href="index.php" class="hover:text-primary transition flex items-center gap-1">
            <i class="fa-solid fa-house text-[10px]"></i> <?= __('home') ?>
        </a>
        <i class="fa-solid fa-chevron-right text-[10px]"></i>
        <span class="text-gray-800 font-medium"><?= __('recently_viewed') ?></span>
    </nav>

    <!-- Tiêu đề & Công cụ -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-gray-800 uppercase flex items-center gap-3">
                <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                <?= __('recently_viewed') ?>
            </h1>
            <p class="text-gray-500 mt-1" id="total-viewed-count">Đang tải lịch sử...</p>
        </div>
        
        <button onclick="clearAllViewedAndRedirect()" 
                class="bg-red-50 text-red-600 hover:bg-red-100 px-6 py-2.5 rounded-xl font-bold transition flex items-center gap-2 border border-red-100 shadow-sm">
            <i class="fa-solid fa-trash-can"></i> <?= __('clear_all') ?>
        </button>
    </div>

    <!-- Danh sách sản phẩm -->
    <div id="full-viewed-container" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
        <!-- Render bằng JS -->
    </div>

    <!-- Empty State -->
    <div id="empty-viewed-state" class="hidden flex flex-col items-center justify-center py-24 bg-white rounded-3xl border-2 border-dashed border-gray-100">
        <div class="w-32 h-32 bg-gray-50 rounded-full flex items-center justify-center mb-6">
            <i class="fa-solid fa-ghost text-5xl text-gray-200"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Lịch sử trống</h3>
        <p class="text-gray-500 mb-8 max-w-xs text-center">Bạn chưa xem sản phẩm nào gần đây hoặc đã xóa lịch sử.</p>
        <a href="index.php" class="bg-primary text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-800 transition shadow-lg shadow-blue-100">
            Khám phá sản phẩm ngay
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', renderFullRecentlyViewed);

    function renderFullRecentlyViewed() {
        const container = document.getElementById('full-viewed-container');
        const emptyState = document.getElementById('empty-viewed-state');
        const countText = document.getElementById('total-viewed-count');
        
        let viewed = JSON.parse(localStorage.getItem('dienmay_viewed_products')) || [];

        if (viewed.length === 0) {
            container.classList.add('hidden');
            emptyState.classList.remove('hidden');
            countText.innerText = 'Bạn chưa xem sản phẩm nào.';
            return;
        }

        container.classList.remove('hidden');
        emptyState.classList.add('hidden');
        countText.innerText = `Bạn đã xem ${viewed.length} sản phẩm trong thời gian qua.`;

        container.innerHTML = '';
        viewed.forEach(p => {
            const priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
            let oldPriceHTML = '';
            if (p.old_price && p.discount > 0) {
                const oldPriceFmt = new Intl.NumberFormat('vi-VN').format(p.old_price) + 'đ';
                oldPriceHTML = `
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-gray-400 text-xs line-through">${oldPriceFmt}</span>
                        <span class="text-danger text-xs font-bold">-${p.discount}%</span>
                    </div>
                `;
            }

            const item = document.createElement('div');
            item.className = "bg-white rounded-2xl border border-gray-100 p-4 hover:shadow-xl hover:border-primary transition-all duration-300 group relative flex flex-col h-full cursor-pointer";
            item.setAttribute('onclick', `window.location.href='product_detail.php?id=${p.id}'`);
            
            item.innerHTML = `
                <button onclick="event.stopPropagation(); removeViewedAndRefresh('${p.id}')" 
                        class="absolute top-3 right-3 w-8 h-8 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 hover:bg-red-500 hover:text-white transition z-10 shadow-sm opacity-0 group-hover:opacity-100">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>

                <div class="h-40 md:h-48 mb-4 flex items-center justify-center overflow-hidden p-2">
                    <img src="${jsAsset(p.image)}" class="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-500">
                </div>

                <div class="mb-2">
                    <span class="text-[10px] md:text-xs font-bold text-primary uppercase tracking-wider">${p.brand_name || 'HÃNG KHÁC'}</span>
                </div>

                <h3 class="text-sm md:text-base font-bold text-gray-800 line-clamp-2 mb-3 leading-snug group-hover:text-primary transition-colors h-10 md:h-12">
                    ${p.name}
                </h3>

                <div class="mt-auto">
                    <div class="text-danger font-black text-lg md:text-xl">${priceFmt}</div>
                    ${oldPriceHTML}
                </div>

                <div class="mt-4 pt-4 border-t border-gray-50 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="event.stopPropagation(); addToCartAjax(${p.id})" 
                            class="flex-1 bg-primary text-white text-[10px] font-bold py-2 rounded-lg hover:bg-blue-800 transition">
                        MUA NGAY
                    </button>
                    <button onclick="event.stopPropagation(); askAIAboutProduct('${p.name.replace(/'/g, "\\'")}')" 
                            class="w-10 bg-white border border-primary text-primary rounded-lg flex items-center justify-center hover:bg-blue-50 transition">
                        <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                    </button>
                </div>
            `;
            container.appendChild(item);
        });
    }

    function removeViewedAndRefresh(id) {
        removeViewedProduct(id);
        renderFullRecentlyViewed();
    }

    function clearAllViewedAndRedirect() {
        Swal.fire({
            title: 'Xóa lịch sử?',
            text: 'Bạn có chắc muốn xóa toàn bộ danh sách sản phẩm đã xem không?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Xóa sạch',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                localStorage.removeItem('dienmay_viewed_products');
                renderFullRecentlyViewed();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
