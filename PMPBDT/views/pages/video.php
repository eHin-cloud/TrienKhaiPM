<?php
/**
 * ============================================================
 * VIDEO.PHP - TRANG VIDEO / NỘI DUNG NỔI BẬT
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../partials/header.php';
?>

<style>
    .video-page-shell {
        background: linear-gradient(180deg, #eff6ff 0%, #ffffff 28%);
    }

    .video-hero {
        background: linear-gradient(135deg, #0046ab 0%, #0b63d1 55%, #0ea5e9 100%);
        color: #fff;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 45px -20px rgba(0, 70, 171, 0.55);
    }

    .video-card {
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 18px;
        box-shadow: 0 10px 25px -18px rgba(15, 23, 42, 0.35);
    }

    .video-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(8px);
    }

    .video-thumb {
        position: relative;
        aspect-ratio: 16 / 9;
        overflow: hidden;
        border-radius: 16px;
        background: #dbeafe;
    }

    .video-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform .5s ease;
    }

    .video-card:hover .video-thumb img {
        transform: scale(1.05);
    }

    .video-play {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        background: linear-gradient(180deg, rgba(0, 70, 171, 0.05), rgba(0, 70, 171, 0.35));
        color: #fff;
    }

    .video-play span {
        width: 72px;
        height: 72px;
        border-radius: 9999px;
        display: grid;
        place-items: center;
        background: rgba(255, 255, 255, 0.94);
        color: #0046ab;
        box-shadow: 0 18px 35px -18px rgba(0, 0, 0, 0.35);
    }

    .video-list-item {
        border: 1px solid #dbeafe;
        border-radius: 16px;
        background: #fff;
        transition: all .2s ease;
    }

    .video-list-item:hover {
        border-color: #0046ab;
        box-shadow: 0 16px 30px -20px rgba(0, 70, 171, 0.35);
        transform: translateY(-2px);
    }
</style>

<div class="video-page-shell min-h-screen pb-16">
    <main class="container mx-auto px-4 py-6 md:py-10">
        <section class="video-hero p-6 md:p-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                <div>
                    <div class="video-pill mb-4">
                        <i class="fa-solid fa-video"></i>
                        Video nổi bật
                    </div>
                    <h1 class="text-3xl md:text-5xl font-black leading-tight mb-4">
                        Khám phá video sản phẩm<br>
                        <span class="text-secondary">theo phong cách xanh dương</span>
                    </h1>
                    <p class="text-blue-100 text-sm md:text-base leading-7 max-w-xl">
                        Trang video riêng với layout 2 cột, bố cục rõ ràng, card hiện đại và điểm nhấn xanh dương đồng bộ với thương hiệu.
                    </p>

                    <div class="flex flex-wrap gap-3 mt-6">
                        <a href="#featured-video" class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-secondary text-primary font-bold hover:bg-yellow-400 transition">
                            <i class="fa-solid fa-circle-play"></i> Xem video nổi bật
                        </a>
                        <a href="index.php" class="inline-flex items-center gap-2 px-5 py-3 rounded-full border border-white/25 text-white font-semibold hover:bg-white/10 transition">
                            <i class="fa-solid fa-arrow-left"></i> Quay lại trang chủ
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="video-card p-4">
                        <div class="video-thumb mb-4">
                            <img src="https://images.unsplash.com/photo-1523473827538-1f9f1d6b86fe?auto=format&fit=crop&w=1200&q=80" alt="Video 1">
                            <div class="video-play"><span><i class="fa-solid fa-play text-2xl"></i></span></div>
                        </div>
                        <h3 class="font-bold text-gray-800">Review sản phẩm mới</h3>
                        <p class="text-sm text-gray-500 mt-1">Giới thiệu nhanh, đúng trọng tâm.</p>
                    </div>
                    <div class="video-card p-4 mt-6 sm:mt-12">
                        <div class="video-thumb mb-4">
                            <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80" alt="Video 2">
                            <div class="video-play"><span><i class="fa-solid fa-play text-2xl"></i></span></div>
                        </div>
                        <h3 class="font-bold text-gray-800">Mẹo sử dụng hiệu quả</h3>
                        <p class="text-sm text-gray-500 mt-1">Nội dung ngắn gọn, dễ xem.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="featured-video" class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 video-card p-5 md:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-extrabold text-gray-800">Video nổi bật</h2>
                        <p class="text-sm text-gray-500 mt-1">Trình bày theo layout 2 cột, phù hợp xem trên desktop.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-blue-50 text-primary text-xs font-bold">Blue layout</span>
                </div>

                <div class="video-thumb">
                    <img src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1400&q=80" alt="Featured video">
                    <div class="video-play"><span><i class="fa-solid fa-play text-3xl"></i></span></div>
                </div>

                <div class="mt-5">
                    <h3 class="text-xl font-bold text-gray-800">Video giới thiệu sản phẩm và tính năng chính</h3>
                    <p class="text-gray-600 mt-2 leading-7">
                        Khu vực này có thể nhúng YouTube, TikTok, hoặc video sản phẩm nội bộ. Bố cục ưu tiên hình ảnh lớn bên trái và danh sách hỗ trợ bên phải.
                    </p>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="video-card p-5">
                    <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-list text-primary"></i> Danh sách video
                    </h3>
                    <div class="space-y-3">
                        <a href="#" class="video-list-item p-3 flex gap-3 items-center">
                            <div class="w-20 h-14 rounded-xl overflow-hidden shrink-0 bg-blue-100">
                                <img src="https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=600&q=80" class="w-full h-full object-cover" alt="thumb">
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">Video 01</p>
                                <p class="text-xs text-gray-500 mt-1">Hướng dẫn nhanh</p>
                            </div>
                        </a>
                        <a href="#" class="video-list-item p-3 flex gap-3 items-center">
                            <div class="w-20 h-14 rounded-xl overflow-hidden shrink-0 bg-blue-100">
                                <img src="https://images.unsplash.com/photo-1516321497487-e288fb19713f?auto=format&fit=crop&w=600&q=80" class="w-full h-full object-cover" alt="thumb">
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">Video 02</p>
                                <p class="text-xs text-gray-500 mt-1">Đánh giá sản phẩm</p>
                            </div>
                        </a>
                        <a href="#" class="video-list-item p-3 flex gap-3 items-center">
                            <div class="w-20 h-14 rounded-xl overflow-hidden shrink-0 bg-blue-100">
                                <img src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=600&q=80" class="w-full h-full object-cover" alt="thumb">
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">Video 03</p>
                                <p class="text-xs text-gray-500 mt-1">Mẹo mua sắm</p>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="video-card p-5 bg-gradient-to-br from-blue-50 to-white">
                    <h3 class="font-bold text-gray-800 mb-2">Gợi ý thiết kế</h3>
                    <p class="text-sm text-gray-600 leading-6">
                        Có thể nối dữ liệu thật từ YouTube API hoặc bảng video riêng sau. Hiện tại đã dựng sẵn layout 2 cột xanh dương để bạn dùng ngay.
                    </p>
                </div>
            </aside>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
