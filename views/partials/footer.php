<!-- ============================================================
     FOOTER.PHP - FOOTER CHUNG + AI CHAT + SẢN PHẨM ĐÃ XEM
     ============================================================
     
     File được require_once bởi tất cả các trang public.
     
     CHỨC NĂNG:
     1. SẢN PHẨM ĐÃ XEM (localStorage):
        - Lưu lịch sử SP đã xem vào localStorage (tối đa 10 SP)
        - Hiển thị dạng carousel scroll ngang
        - Nút xóa từng SP hoặc xóa tất cả
        - Không hiện trên trang product_detail (tránh trùng)
     
     2. AI CHAT PRO (Google Gemini API):
        - Cửa sổ chat floating ở góc dưới phải
        - Nút FAB (Floating Action Button) với animation ping
        - Gợi ý nhanh (Quick messages)
        - Tự nhận diện context sản phẩm đang xem
        - Fallback qua nhiều model Gemini nếu model chính lỗi
     
     3. FOOTER WEBSITE:
        - Logo + Copyright
     
     4. HÀM JS GLOBAL:
        - addToCartAjax()      : Thêm SP vào giỏ bằng AJAX
        - buyNowAjax()         : Thêm SP + redirect sang cart
        - submitInstallment()  : Gửi đăng ký trả góp AJAX
        - showSuccessModal()   : Hiện thông báo thành công (SweetAlert2)
        - viewProduct()        : Lưu SP vào lịch sử đã xem + redirect
     
     @requires config_api.php - API Key cho Google Gemini
     ============================================================ -->

<!-- ==========================================
     SECTION: SẢN PHẨM ĐÃ XEM GẦN ĐÂY
     Dữ liệu từ localStorage, render bằng JS
     Chỉ hiện ở các trang KHÔNG phải product_detail
     ========================================== -->
<?php require_once __DIR__ . '/../../core/config_api.php'; ?>
<?php if (basename($_SERVER['PHP_SELF']) !== 'product_detail.php'): ?>
    <style>
        .viewed-section-container {
            position: relative;
        }
        #viewed-products-container {
            scroll-behavior: smooth;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        #viewed-products-container::-webkit-scrollbar { display: none; }

        /* Style chung cho nút điều hướng Carousel trên toàn website */
        .carousel-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            z-index: 40;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            color: #1e293b;
        }
        .carousel-nav-btn:hover {
            background: #0046ab;
            color: white;
            border-color: #0046ab;
            box-shadow: 0 8px 20px rgba(0, 70, 171, 0.3);
            transform: translateY(-50%) scale(1.1);
        }
        .carousel-nav-btn:active {
            transform: translateY(-50%) scale(0.95);
        }
        .carousel-nav-btn.prev { left: -15px; }
        .carousel-nav-btn.next { right: -15px; }
        
        @media (max-width: 768px) {
            .carousel-nav-btn { display: none; }
        }
    </style>
    <section id="recently-viewed-section" class="container mx-auto px-4 mt-10 mb-10 hidden">

        <div class="bg-white p-4 md:p-5 rounded-xl shadow-sm border border-gray-200">

            <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-primary"></i> <?= __('recently_viewed') ?>
                </h2>
                <div class="flex items-center gap-2">
                    <a href="recently_viewed.php" class="text-xs text-primary hover:underline font-bold bg-blue-50 px-3 py-1.5 rounded-lg transition">
                        <?= __('view_all') ?> <i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                    <!-- Nút xóa toàn bộ lịch sử đã xem -->
                    <button onclick="clearAllViewed()"
                        class="text-xs text-red-500 hover:text-red-700 font-medium bg-red-50 px-3 py-1.5 rounded-lg transition"><?= __('clear_all') ?></button>
                </div>

            </div>
            <!-- Container sẽ được JS render các thẻ sản phẩm vào đây -->
            <div class="viewed-section-container">
                <button onclick="scrollCarousel('viewed-products-container', 'prev')" class="carousel-nav-btn prev"><i class="fa-solid fa-chevron-left"></i></button>
                <button onclick="scrollCarousel('viewed-products-container', 'next')" class="carousel-nav-btn next"><i class="fa-solid fa-chevron-right"></i></button>
                <div id="viewed-products-container" class="flex gap-4 overflow-x-auto pb-2"></div>
            </div>
        </div>
    </section>
<?php endif; ?>



<!-- ==========================================
         AI CHAT WINDOW - Cửa sổ chat AI
         ========================================== -->

<!-- CSS cho AI Chat -->
<style>
    /* Cửa sổ chat - fixed ở góc dưới phải */
    #ai-chat-window {
        display: none;
        position: fixed;
        bottom: 80px;
        right: 10px;
        width: calc(100% - 20px);
        max-width: 360px;
        height: 500px;
        max-height: 80vh;
        background: white;
        border-radius: 12px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        z-index: 1001;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }

    /* Responsive: tăng kích thước trên desktop */
    @media (min-width: 768px) {
        #ai-chat-window {
            bottom: 90px;
            right: 20px;
            height: 550px;
        }
    }

    /* Animation slide up khi mở chat */
    #ai-chat-window.active {
        display: flex;
        animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Khu vực tin nhắn */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #f9fafb;
        scroll-behavior: smooth;
    }

    /* Bong bóng tin nhắn chung */
    .message {
        max-width: 85%;
        padding: 10px 14px;
        font-size: 13.5px;
        line-height: 1.5;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        overflow-wrap: break-word;
    }

    /* Tin nhắn của user (bên phải, nền xanh) */
    .message.user {
        align-self: flex-end;
        background: #0046ab;
        color: white;
        border-radius: 16px 16px 4px 16px;
    }

    /* Tin nhắn của AI (bên trái, nền trắng) */
    .message.ai {
        align-self: flex-start;
        background: white;
        color: #1f2937;
        border-radius: 16px 16px 16px 4px;
        border: 1px solid #e5e7eb;
    }

    .message.ai b {
        color: #0046ab;
    }

    /* Animation loading dots (3 chấm nhảy) */
    .loading-dots span {
        display: inline-block;
        width: 6px;
        height: 6px;
        background: #999;
        border-radius: 50%;
        margin: 0 2px;
        animation: bounce 1.4s infinite ease-in-out both;
    }

    .loading-dots span:nth-child(1) {
        animation-delay: -0.32s;
    }

    .loading-dots span:nth-child(2) {
        animation-delay: -0.16s;
    }

    @keyframes bounce {

        0%,
        80%,
        100% {
            transform: scale(0);
        }

        40% {
            transform: scale(1.0);
        }
    }
</style>

<!-- Cửa sổ Chat AI -->
<div id="ai-chat-window">
    <!-- Header chat: Avatar AI + Trạng thái online + Nút đóng -->
    <div class="bg-primary text-white p-3 flex justify-between items-center shadow-sm z-10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-primary text-lg"><i
                    class="fa-solid fa-robot"></i></div>
            <div>
                <div class="font-bold text-sm"><?= __("ai_assistant_title") ?></div>
                <div class="text-[10px] text-blue-200 flex items-center gap-1"><span
                        class="w-1.5 h-1.5 bg-green-400 rounded-full inline-block"></span> <?= __("online_status") ?></div>
            </div>
        </div>
        <button onclick="toggleAIChat()" class="hover:bg-white/20 w-8 h-8 rounded-full transition"><i
                class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- Khu vực hiển thị tin nhắn (JS render vào đây) -->
    <div class="chat-messages" id="chat-messages">
    </div>

    <!-- Gợi ý nhanh (Quick messages) - scroll ngang -->
    <div
        class="px-3 pb-2 pt-2 bg-white overflow-x-auto hide-scrollbar whitespace-nowrap border-t border-gray-100 shadow-[0_-5px_10px_rgba(0,0,0,0.02)]">
        <button onclick="sendQuickMessage('<?= addslashes(__("ai_quick_msg_1")) ?>')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition mr-1"><?= __("ai_quick_btn_1") ?></button>
        <button onclick="sendQuickMessage('<?= addslashes(__("ai_quick_msg_2")) ?>')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition mr-1"><?= __("ai_quick_btn_2") ?></button>
        <button onclick="sendQuickMessage('<?= addslashes(__("ai_quick_msg_3")) ?>')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition"><?= __("ai_quick_btn_3") ?></button>
    </div>

    <!-- Input gửi tin nhắn -->
    <div class="p-3 bg-white border-t border-gray-100 flex gap-2 items-center">
        <input type="text" id="ai-input" placeholder="<?= __("ai_input_placeholder") ?>"
            class="flex-1 text-[13px] bg-gray-100 border-transparent rounded-full px-4 py-2.5 focus:outline-none focus:ring-1 focus:ring-primary focus:bg-white transition">
        <button onclick="sendMessage()"
            class="bg-primary text-white w-9 h-9 rounded-full flex items-center justify-center hover:bg-blue-800 transition shadow-sm"><i
                class="fa-solid fa-paper-plane text-[13px]"></i></button>
    </div>
</div>

<!-- NÚT FAB MỞ AI CHAT (Floating Action Button - góc dưới phải) -->
<div onclick="toggleAIChat()" class="fixed bottom-4 right-4 md:bottom-6 md:right-6 z-50 group">
    <div
        class="bg-secondary text-primary p-3 rounded-full shadow-xl flex items-center justify-center cursor-pointer hover:scale-110 transition duration-300 w-12 h-12 md:w-14 md:h-14">
        <i class="fa-solid fa-robot text-xl md:text-2xl"></i>
        <!-- Hiệu ứng ping đỏ (thu hút chú ý) -->
        <span class="absolute 0 top-0 right-0 flex h-3 w-3"><span
                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span
                class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white"></span></span>
    </div>
</div>

<!-- ==========================================
         FOOTER WEBSITE
         ========================================== -->
<footer class="bg-primary text-gray-200 pt-16 pb-8 border-t-4 border-secondary mt-auto relative overflow-hidden">
    <!-- Decorative Element -->
    <div class="absolute top-0 right-0 w-64 h-64 bg-primary rounded-full mix-blend-multiply filter blur-3xl opacity-20 transform translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 left-0 w-80 h-80 bg-secondary rounded-full mix-blend-multiply filter blur-3xl opacity-10 transform -translate-x-1/2 translate-y-1/2"></div>

    <div class="container mx-auto px-4 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
            
            <!-- Cột 1: Thông tin chung -->
            <div>
                <div class="font-black text-3xl text-white mb-6 tracking-tight">
                    DIENMAY<span class="text-secondary">PRO</span>
                </div>
                <p class="text-sm text-gray-400 mb-6 leading-relaxed">
                    <?= __("footer_about_desc") ?>
                </p>
                <!-- Social Links -->
                <div class="flex gap-3">
                    <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 hover:bg-secondary hover:text-primary transition-all duration-300 shadow-lg transform hover:-translate-y-1"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 hover:bg-red-500 hover:text-white transition-all duration-300 shadow-lg transform hover:-translate-y-1"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 hover:bg-pink-500 hover:text-white transition-all duration-300 shadow-lg transform hover:-translate-y-1"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 hover:bg-blue-400 hover:text-white transition-all duration-300 shadow-lg transform hover:-translate-y-1"><i class="fa-brands fa-twitter"></i></a>
                </div>
            </div>

            <!-- Cột 2: Thông tin liên hệ -->
            <div>
                <h3 class="text-white font-bold text-lg mb-6 uppercase tracking-wider relative inline-block">
                    <?= __('contact') ?>
                    <span class="absolute -bottom-2 left-0 w-12 h-1 bg-secondary rounded"></span>
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3 group">
                        <div class="w-8 h-8 rounded bg-white/10 flex items-center justify-center flex-shrink-0 group-hover:bg-secondary group-hover:text-primary transition-colors text-secondary">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <span class="text-sm text-gray-300 leading-snug">123 Đường Điện Máy, Phường Đổi Mới, Quận Công Nghệ, TP. Hồ Chí Minh</span>
                    </li>
                    <li class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded bg-white/10 flex items-center justify-center flex-shrink-0 group-hover:bg-secondary group-hover:text-primary transition-colors text-secondary">
                            <i class="fa-solid fa-phone-volume"></i>
                        </div>
                        <div>
                            <span class="text-xs text-blue-200 block"><?= __("hotline_label") ?></span>
                            <a href="tel:19009999" class="text-base text-white font-bold hover:text-secondary transition-colors">1900 9999</a>
                        </div>
                    </li>
                    <li class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded bg-white/10 flex items-center justify-center flex-shrink-0 group-hover:bg-secondary group-hover:text-primary transition-colors text-secondary">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <span class="text-xs text-blue-200 block"><?= __("email_support_label") ?></span>
                            <a href="mailto:support@dienmaypro.vn" class="text-sm hover:text-secondary transition-colors">support@dienmaypro.vn</a>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Cột 3: Hỗ trợ khách hàng -->
            <div>
                <h3 class="text-white font-bold text-lg mb-6 uppercase tracking-wider relative inline-block">
                    <?= __('customer_support') ?>
                    <span class="absolute -bottom-2 left-0 w-12 h-1 bg-secondary rounded"></span>
                </h3>
                <ul class="space-y-3 mb-6">
                    <li><a href="javascript:showPolicyModal('warranty')" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("warranty_policy") ?></a></li>
                    <li><a href="javascript:showPolicyModal('return')" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("return_policy_1_1") ?></a></li>
                    <li><a href="javascript:showPolicyModal('delivery')" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("delivery_install") ?></a></li>
                    <li><a href="javascript:showPolicyModal('installment')" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("installment_guide") ?></a></li>
                    <li><a href="javascript:showPolicyModal('faq')" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("faq") ?></a></li>
                </ul>

                <h3 class="text-white font-bold text-sm mb-4 uppercase tracking-wider relative inline-block">
                    <?= __('download_app') ?>
                    <span class="absolute -bottom-1.5 left-0 w-8 h-0.5 bg-secondary rounded"></span>
                </h3>
                <?php
                $base_url = '/' . trim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
                if ($base_url === '//') {
                    $base_url = '/';
                }
                ?>
                <div class="flex flex-col gap-2">
                    <a href="<?= $base_url ?>apk/dienmaypro.apk" download class="flex items-center gap-2.5 bg-white/10 hover:bg-secondary hover:text-primary transition-all duration-300 px-3.5 py-2 rounded-lg text-gray-300 font-semibold text-xs border border-white/5 hover:border-secondary shadow-md group w-fit">
                        <i class="fa-brands fa-android text-base text-green-400 group-hover:text-primary transition-colors"></i>
                        <span><?= __('android_standard') ?></span>
                    </a>
                    <a href="<?= $base_url ?>apk/dienmaypromayyeu.apk" download class="flex items-center gap-2.5 bg-white/10 hover:bg-secondary hover:text-primary transition-all duration-300 px-3.5 py-2 rounded-lg text-gray-300 font-semibold text-xs border border-white/5 hover:border-secondary shadow-md group w-fit">
                        <i class="fa-brands fa-android text-base text-yellow-400 group-hover:text-primary transition-colors"></i>
                        <span><?= __('android_old') ?></span>
                    </a>
                </div>
            </div>

            <!-- Cột 4: Đăng ký & Thanh toán -->
            <div>
                <h3 class="text-white font-bold text-lg mb-6 uppercase tracking-wider relative inline-block">
                    <?= __('get_deals') ?>
                    <span class="absolute -bottom-2 left-0 w-12 h-1 bg-secondary rounded"></span>
                </h3>
                <p class="text-sm text-gray-300 mb-4"><?= __("newsletter_desc") ?></p>
                <form id="newsletter-form" class="relative mb-8" onsubmit="submitNewsletter(event)">
                    <input type="email" id="newsletter-email" name="email" placeholder="<?= __("newsletter_placeholder") ?>" required class="w-full bg-white/10 border border-white/10 text-sm text-white px-4 py-3 rounded-lg focus:outline-none focus:border-secondary focus:ring-1 focus:ring-secondary transition-all">
                    <button type="submit" class="absolute right-1 top-1 bottom-1 bg-secondary hover:bg-yellow-400 text-primary px-4 rounded-md font-medium transition-colors">
                        <?= __("send") ?>
                    </button>
                </form>
                
                <h4 class="text-sm font-bold text-white mb-3"><?= __("payment_methods") ?></h4>
                <div class="flex gap-2 flex-wrap">
                    <div class="w-10 h-7 bg-white rounded flex items-center justify-center p-1 shadow"><i class="fa-brands fa-cc-visa text-blue-800 text-xl"></i></div>
                    <div class="w-10 h-7 bg-white rounded flex items-center justify-center p-1 shadow"><i class="fa-brands fa-cc-mastercard text-red-600 text-xl"></i></div>
                    <div class="w-10 h-7 bg-white rounded flex items-center justify-center p-1 shadow"><i class="fa-brands fa-cc-paypal text-blue-500 text-xl"></i></div>
                    <div class="w-10 h-7 bg-white rounded flex items-center justify-center p-1 shadow"><i class="fa-solid fa-money-bill-wave text-green-600 text-base"></i></div>
                </div>
            </div>
        </div>

        <!-- Dòng bản quyền -->
        <div class="pt-6 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-xs text-blue-100">© 2026 DIENMAY<span class="text-white font-bold">PRO</span>. <?= __("all_rights_reserved") ?></p>
            <div class="flex gap-4 text-xs text-blue-100">
                <a href="javascript:showPolicyModal('terms')" class="hover:text-white transition"><?= __("terms_of_use") ?></a>
                <span class="text-white/20">|</span>
                <a href="javascript:showPolicyModal('privacy')" class="hover:text-white transition"><?= __("privacy_policy") ?></a>
            </div>
        </div>
    </div>
</footer>

<!-- ==========================================
     MODAL CHÍNH SÁCH VÀ ĐIỀU KHOẢN (PREMIUM DESIGN)
     ========================================== -->
<div id="policy-modal" class="fixed inset-0 z-[10000] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md opacity-0 pointer-events-none transition-all duration-300">
    <div class="bg-white dark:bg-slate-800 w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-700 overflow-hidden transform scale-95 transition-all duration-300 flex flex-col max-h-[85vh]">
        <!-- Header -->
        <div class="p-6 bg-gradient-to-r from-primary to-blue-700 text-white flex justify-between items-center relative">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg text-secondary" id="policy-modal-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg leading-tight" id="policy-modal-title">Tiêu đề chính sách</h3>
                    <p class="text-xs text-blue-100 mt-0.5"><?= __('customer_support') ?></p>
                </div>
            </div>
            <button onclick="closePolicyModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-all">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <!-- Content -->
        <div class="p-6 md:p-8 overflow-y-auto flex-1 text-slate-600 dark:text-slate-300 leading-relaxed text-sm" id="policy-modal-content">
            Nội dung chính sách...
        </div>
        
        <!-- Footer -->
        <div class="p-4 bg-slate-50 dark:bg-slate-900/50 border-t border-gray-100 dark:border-slate-700 flex justify-end gap-3">
            <button onclick="closePolicyModal()" class="bg-secondary hover:bg-yellow-400 text-primary font-bold px-6 py-2.5 rounded-xl transition shadow-md hover:shadow-lg text-sm flex items-center gap-2">
                <i class="fa-solid fa-check"></i> <?= __('close') ?>
            </button>
        </div>
    </div>
</div>

<!-- Thư viện SweetAlert2 - Hiệu ứng thông báo đẹp mắt -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- ==========================================
         JAVASCRIPT: TẤT CẢ HÀM GLOBAL
         ========================================== -->
<script>
    // ==========================================
    // BIẾN TOÀN CỤC: CSRF TOKEN CHO AJAX
    // ==========================================
    const csrfToken = '<?= generate_csrf_token() ?>';
    const assetPath = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ""), "/\\") ?>/';

    // ==========================================
    // XỬ LÝ ĐĂNG KÝ NHẬN ƯU ĐÃI (NEWSLETTER)
    // ==========================================
    function submitNewsletter(event) {
        event.preventDefault();
        const emailInput = document.getElementById('newsletter-email');
        if (!emailInput || !emailInput.value) return;

        const btn = event.target.querySelector('button[type="submit"]');
        let originalText = '';
        if (btn) {
            originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
            btn.disabled = true;
        }

        const formData = new FormData();
        formData.append('email', emailInput.value);
        formData.append('csrf_token', '<?= get_csrf_token_value() ?>');

        fetch('subscribe.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Thành công!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#004bb9'
                    });
                } else {
                    alert(data.message);
                }
                emailInput.value = '';
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Thông báo', data.message, 'warning');
                } else {
                    alert(data.message);
                }
            }
        })
        .catch(err => {
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            console.error('Lỗi đăng ký nhận ưu đãi:', err);
            Swal.fire('Lỗi!', 'Không thể gửi yêu cầu đăng ký.', 'error');
        });
    }

    /**
     * Hàm giả lập asset() của PHP trong JS
     */
    function jsAsset(path) {
        if (!path) return assetPath + 'assets/img/no-image.png';
        if (path.startsWith('http')) return path;
        return assetPath + path.replace(/^\//, '');
    }

    /**
     * Cuộn carousel theo hướng chỉ định
     */
    function scrollCarousel(containerId, direction) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const scrollAmount = container.offsetWidth * 0.8;
        container.scrollBy({
            left: direction === 'next' ? scrollAmount : -scrollAmount,
            behavior: 'smooth'
        });
    }



    // ==========================================
    // 1. HÀM HIỂN THỊ THÔNG BÁO SWEETALERT2
    // ==========================================

    /**
     * Hiện modal thông báo thành công (tự đóng sau 2 giây)
     * @param {string} message - Nội dung thông báo
     */
    function showSuccessModal(message) {
        Swal.fire({
            icon: 'success',
            title: '<?= __("success") ?>!',
            text: message,
            timer: 2000,
            showConfirmButton: false,
            backdrop: `rgba(0,0,0,0.4)`
        });
    }

    // ==========================================
    // 2. XỬ LÝ ĐĂNG KÝ TRẢ GÓP (AJAX)
    // ==========================================

    /**
     * Gửi form đăng ký trả góp qua AJAX đến save_installment.php
     * Được gọi từ form #installmentForm trên product_detail.php
     * @param {Event} e - Sự kiện submit form
     */
    function submitInstallment(e) {
        e.preventDefault(); // Ngăn form submit mặc định
        const formData = new FormData(document.getElementById('installmentForm'));
        formData.append('csrf_token', csrfToken);
        fetch('save_installment.php', { method: 'POST', body: new URLSearchParams(formData) })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal('<?= __("installment_success_swal") ?>');
                    document.getElementById('installmentModal').classList.add('hidden');
                    document.getElementById('installmentForm').reset();
                } else {
                    Swal.fire({ icon: 'error', title: 'Lỗi!', text: data.message });
                }
            }).catch(error => {
                Swal.fire({ icon: 'error', title: 'Lỗi!', text: 'Có lỗi xảy ra khi gửi yêu cầu!' });
            });
    }

    // ==========================================
    // 3. AJAX THÊM VÀO GIỎ HÀNG
    // ==========================================

    /**
     * Thêm sản phẩm vào giỏ hàng bằng AJAX (không reload trang)
     * Cập nhật badge số lượng giỏ hàng trên header
     * Nếu chưa đăng nhập -> mở modal đăng nhập
     * @param {number} productId - ID sản phẩm cần thêm
     */
    function addToCartAjax(productId) {
        fetch('add_to_cart.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax=1&id=' + productId + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Cập nhật badge số lượng giỏ hàng trên header
                    let badge = document.getElementById('cart-count-badge');
                    if (badge) {
                        badge.innerText = data.cart_count;
                        badge.classList.remove('hidden');
                        badge.classList.add('animate-bounce'); // Hiệu ứng bounce
                        setTimeout(() => badge.classList.remove('animate-bounce'), 1000);
                    }
                    showSuccessModal('<?= __("add_to_cart_success_swal") ?>');
                } else if (data.message === 'not_logged_in') {
                    // Chưa đăng nhập -> mở modal đăng nhập
                    let loginModal = document.getElementById('loginModal');
                    if (loginModal) loginModal.classList.remove('hidden');
                    else Swal.fire({ icon: 'warning', title: '<?= __("notice") ?>', text: '<?= __("login_required_cart_swal") ?>' });
                }
            })
            .catch(error => {
                Swal.fire({ icon: 'error', title: 'Lỗi!', text: 'Lỗi kết nối hoặc phiên đăng nhập!' });
            });
    }

    /**
     * Thêm SP vào giỏ rồi redirect sang trang giỏ hàng (Mua ngay)
     * @param {number} productId - ID sản phẩm
     */
    function buyNowAjax(productId) {
        fetch('add_to_cart.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax=1&id=' + productId + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) { window.location.href = 'cart.php'; } // Redirect sang giỏ hàng
                else if (data.message === 'not_logged_in') {
                    let loginModal = document.getElementById('loginModal');
                    if (loginModal) loginModal.classList.remove('hidden');
                    else Swal.fire({ icon: 'warning', title: '<?= __("notice") ?>', text: '<?= __("login_required_buy_swal") ?>' });
                }
            })
            .catch(error => { window.location.href = 'add_to_cart.php?id=' + productId; }); // Fallback: dùng GET
    }

    // ==========================================
    // 4. QUẢN LÝ SẢN PHẨM ĐÃ XEM (localStorage)
    // ==========================================

    const VIEWED_KEY = 'dienmay_viewed_products'; // Key lưu trong localStorage

    /**
     * Lưu sản phẩm vào lịch sử đã xem và redirect sang trang chi tiết
     * - Xóa SP trùng (nếu đã xem trước đó) -> đưa lên đầu
     * - Giới hạn tối đa 10 SP
     * @param {Object} product - Object chứa {id, name, price, old_price, discount, image, brand_name}
     */
    function viewProduct(product) {
        let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
        // Xóa SP trùng ID (nếu đã xem trước đó)
        viewed = viewed.filter(p => String(p.id) !== String(product.id));
        // Thêm vào đầu mảng (mới nhất ở trước)
        viewed.unshift(product);
        // Giới hạn tối đa 10 SP
        if (viewed.length > 10) viewed.pop();
        localStorage.setItem(VIEWED_KEY, JSON.stringify(viewed));
        // Redirect sang trang chi tiết sản phẩm
        window.location.href = 'product_detail.php?id=' + product.id;
    }

    /**
     * Render danh sách sản phẩm đã xem từ localStorage
     * Tạo card SP nhỏ dạng scroll ngang
     */
    function renderViewedProducts() {
        let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
        const container = document.getElementById('viewed-products-container');
        const section = document.getElementById('recently-viewed-section');
        if (!section || !container) return;

        // Ẩn section nếu không có SP nào
        if (viewed.length === 0) { section.classList.add('hidden'); return; }
        section.classList.remove('hidden'); container.innerHTML = '';

        // Render từng thẻ SP
        viewed.forEach(p => {
            let priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
            let oldPriceHTML = '';
            if (p.old_price && p.discount > 0) {
                let oldPriceFmt = new Intl.NumberFormat('vi-VN').format(p.old_price) + 'đ';
                oldPriceHTML = `<div class="flex items-center gap-1"><span class="text-gray-400 text-[10px] line-through">${oldPriceFmt}</span><span class="text-danger text-[10px] font-bold">-${p.discount}%</span></div>`;
            }
            const card = document.createElement('div');
            card.className = 'min-w-[150px] w-[150px] flex-shrink-0 bg-white border border-gray-200 hover:border-primary rounded-xl p-3 relative group transition cursor-pointer flex flex-col shadow-sm';
            card.setAttribute('onclick', `window.location.href='product_detail.php?id=${p.id}'`);
            card.innerHTML = `
                    <button onclick="event.stopPropagation(); removeViewedProduct('${p.id}')" class="absolute top-1 right-1 bg-gray-100 hover:bg-red-500 text-gray-500 hover:text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px] transition z-20 shadow-sm">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <div class="h-24 mb-2 flex items-center justify-center overflow-hidden"><img src="${jsAsset(p.image)}" class="max-w-full max-h-full object-contain group-hover:scale-105 transition"></div>

                    <span class="text-[10px] text-gray-500 mb-1 block uppercase">${p.brand_name || ''}</span>
                    <h4 class="text-[11px] text-gray-800 line-clamp-2 h-7 leading-snug font-medium group-hover:text-primary transition">${p.name}</h4>
                    <div class="mt-auto pt-2"><div class="text-danger font-bold text-xs">${priceFmt}</div>${oldPriceHTML}</div>
                `;
            container.appendChild(card);
        });
    }

    /**
     * Xóa 1 sản phẩm khỏi lịch sử đã xem
     * @param {string} id - ID sản phẩm cần xóa
     */
    function removeViewedProduct(id) {
        let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
        viewed = viewed.filter(p => String(p.id) !== String(id));
        localStorage.setItem(VIEWED_KEY, JSON.stringify(viewed));
        renderViewedProducts(); // Re-render lại
    }

    /**
     * Xóa toàn bộ lịch sử SP đã xem (có hộp thoại xác nhận)
     */
    function clearAllViewed() {
        Swal.fire({
            title: '<?= __("clear_history_confirm_title") ?>',
            text: '<?= __("clear_history_confirm_text") ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '<?= __("agree") ?>',
            cancelButtonText: '<?= __("cancel") ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                localStorage.removeItem(VIEWED_KEY);
                renderViewedProducts();
            }
        });
    }

    // Render SP đã xem ngay khi trang load xong
    document.addEventListener('DOMContentLoaded', renderViewedProducts);

    // ==========================================
    // 5. AI CHAT PRO - Tích hợp Google Gemini API
    // ==========================================
    // ==========================================
    // 5. AI CHAT PRO - Tích hợp RAG Backend
    // ==========================================
    // KHÔNG gán cứng API Key ở giao diện nữa (đã chuyển xuống Backend)
    // KHÔNG tải trước kho hàng vào prompt nữa (Backend RAG sẽ tự tìm theo câu hỏi)
    
    // Lấy các DOM element cần thiết
    const chatWindow = document.getElementById('ai-chat-window'), chatMessages = document.getElementById('chat-messages'), aiInput = document.getElementById('ai-input');

    let hasWelcomed = false; // Cờ đánh dấu đã hiện lời chào chưa
    let messageList = [];    // Mảng lưu trữ tin nhắn để lưu lịch sử

    const HISTORY_KEY = 'chatbot_history_pmpbdt';
    const SESSION_TTL = 60 * 60 * 1000; // 60 phút (mili-giây)

    /**
     * Lưu danh sách tin nhắn và thời gian hết hạn vào LocalStorage
     */
    function saveHistory(messages) {
        try {
            const data = {
                messages: messages,
                expires_at: Date.now() + SESSION_TTL
            };
            localStorage.setItem(HISTORY_KEY, JSON.stringify(data));
        } catch (e) {
            console.warn('Cannot save chatbot history:', e);
        }
    }

    /**
     * Tải danh sách tin nhắn nếu còn hạn
     */
    function loadHistory() {
        try {
            const raw = localStorage.getItem(HISTORY_KEY);
            if (!raw) return null;

            const data = JSON.parse(raw);
            if (!data || !data.expires_at || !Array.isArray(data.messages)) {
                localStorage.removeItem(HISTORY_KEY);
                return null;
            }

            if (Date.now() > data.expires_at) {
                localStorage.removeItem(HISTORY_KEY);
                return null;
            }

            // Gia hạn phiên chat thêm 60 phút
            data.expires_at = Date.now() + SESSION_TTL;
            localStorage.setItem(HISTORY_KEY, JSON.stringify(data));

            return data.messages;
        } catch (e) {
            localStorage.removeItem(HISTORY_KEY);
            return null;
        }
    }

    /**
     * Khôi phục phiên chat từ LocalStorage khi load trang
     */
    function initChatSession() {
        const cached = loadHistory();
        if (cached && cached.length > 0) {
            messageList = cached;
            hasWelcomed = true;
            cached.forEach(msg => {
                appendMessage(msg.text, msg.role, false);
            });
        }
    }

    // Tự động khởi chạy khôi phục lịch sử khi DOM load xong
    document.addEventListener('DOMContentLoaded', initChatSession);

    /**
     * Mở/đóng cửa sổ AI Chat
     * Lần đầu mở sẽ hiện lời chào (tuỳ context trang hiện tại)
     */
    function toggleAIChat() {
        chatWindow.classList.toggle('active');
        if (chatWindow.classList.contains('active')) {
            aiInput.focus();
            if (!hasWelcomed) {
                hasWelcomed = true;
                // Lời chào khác nhau nếu đang xem SP cụ thể hay ở trang chủ
                let greeting = '<?= __("ai_greeting_default") ?>';
                if (typeof currentProductContext !== 'undefined') {
                    greeting = '<?= sprintf(__("ai_greeting_product"), "' + currentProductName + '") ?>';
                }
                appendMessage(greeting, 'ai', true);
            }
        }
    }

    /**
     * Thêm 1 tin nhắn vào khu vực chat
     * @param {string} text - Nội dung HTML tin nhắn
     * @param {string} role - 'user' hoặc 'ai'
     * @param {boolean} save - Có lưu vào cache hay không
     */
    function appendMessage(text, role, save = true) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `message ${role}`;
        msgDiv.innerHTML = text;
        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight; // Auto scroll xuống cuối

        if (save) {
            messageList.push({ text: text, role: role });
            saveHistory(messageList);
        }
    }

    /**
     * Hiện animation loading (3 chấm nhảy) khi đang chờ AI trả lời
     */
    function showLoading() {
        const loader = document.createElement('div');
        loader.className = 'message ai loading-indicator';
        loader.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>';
        loader.id = 'ai-loading';
        chatMessages.appendChild(loader);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    /**
     * Xóa animation loading sau khi AI trả lời xong
     */
    function removeLoading() {
        const loader = document.getElementById('ai-loading');
        if (loader) loader.remove();
    }

    /**
     * Gửi tin nhắn nhanh (từ nút gợi ý)
     * Tự mở chat window nếu chưa mở
     * @param {string} text - Nội dung tin nhắn
     */
    function sendQuickMessage(text) {
        if (!chatWindow.classList.contains('active')) toggleAIChat();
        aiInput.value = text;
        sendMessage();
    }

    /**
     * Gọi Backend RAG Chatbot
     * Thay vì gọi trực tiếp Google API (lộ key, tốn token), ta gọi server của mình.
     * 
     * @param {string} prompt - Câu hỏi/yêu cầu của người dùng
     * @returns {string} - Câu trả lời từ AI
     */
    async function callGemini(prompt) {
        // Ngữ cảnh hiện tại (nếu đang ở chi tiết SP)
        let currentContext = typeof currentProductContext !== 'undefined' ? currentProductContext : '';

        try {
            const response = await fetch('ai_assist.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken // Token bảo vệ
                },
                body: JSON.stringify({ 
                    prompt: prompt,
                    context: currentContext,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();

            if (data.success) {
                return data.response;
            } else {
                throw new Error(data.message || "Lỗi phản hồi từ máy chủ");
            }

        } catch (e) {
            console.error("Chatbot Backend Error:", e);
            throw e;
        }
    }

    /**
     * Xử lý gửi tin nhắn: hiện tin user, gọi API, hiện tin AI
     */
    async function sendMessage() {
        const text = aiInput.value.trim(); if (!text) return;
        appendMessage(text, 'user');  // Hiện tin nhắn user
        aiInput.value = '';           // Xoá input
        showLoading();                // Hiện loading

        try {
            const aiResponse = await callGemini(text);  // Gọi Gemini API
            removeLoading();
            
            let cleanResponse = (aiResponse || "Xin lỗi, tôi gặp chút trục trặc.").trim();

            // Loại bỏ các cú pháp Markdown mà AI có thể vẫn trả về
            cleanResponse = cleanResponse
                .replace(/\*\*(.*?)\*\*/g, '<b>$1</b>')    // **text** → <b>text</b>
                .replace(/^[\s]*[-•*]\s/gm, '👉 ')           // Dấu gạch đầu dòng Markdown -> 👉
                .replace(/^[\s]*#{1,4}\s*(.*)/gm, '<b>$1</b>') // Heading Markdown -> <b>text</b>
                .replace(/\r\n/g, '\n')
                .replace(/\n{2,}/g, '\n\n')                 // Gom các dòng trắng liên tiếp
                .replace(/\n/g, '<br>')
                .replace(/(<br>\s*){3,}/gi, '<br><br>')     // Giới hạn tối đa 2 thẻ <br> liên tiếp
                .replace(/^(<br>\s*)+/i, '')                // Xóa các thẻ <br> thừa ở đầu
                .replace(/(<br>\s*)+$/i, '');               // Xóa các thẻ <br> thừa ở cuối
                
            appendMessage(cleanResponse, 'ai', true);
        } catch (error) {
            removeLoading();
            console.error("Lỗi:", error);
            appendMessage(`<b class="text-red-500">LỖI:</b> <br> <span class="text-xs text-gray-500">${error.message}</span>`, 'ai', false);
        }
    }

    /**
     * Hỏi AI về 1 sản phẩm cụ thể (từ nút "Hỏi AI" trên thẻ SP)
     * @param {string} productName - Tên sản phẩm
     */
    function askAIAboutProduct(productName) {
        if (!chatWindow.classList.contains('active')) toggleAIChat();
        aiInput.value = `Phân tích ưu nhược điểm của: ${productName}.`;
        sendMessage();
    }

    /**
     * Tìm kiếm bằng AI (được gọi từ 1 input tìm kiếm nào đó)
     * @param {string} inputId - ID của input element
     */
    function searchAssistant(inputId) {
        const query = document.getElementById(inputId).value;
        if (query) askAIAboutProduct(query);
    }

    // Gửi tin nhắn khi bấm phím Enter
    aiInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // ==========================================
    // CẤU HÌNH & XỬ LÝ HIỂN THỊ MODAL CHÍNH SÁCH
    // ==========================================
    const policyData = {
        warranty: {
            title_vi: "Chính Sách Bảo Hành Điện Máy",
            title_en: "Electronic Warranty Policy",
            icon: "fa-shield-halved",
            content_vi: `
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Thời gian bảo hành vượt trội</h4>
                            <p class="text-slate-600">Bảo hành chính hãng <b>12 tháng</b> cho toàn bộ sản phẩm do Điện Máy PRO phân phối.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-arrow-rotate-left"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Lỗi 1 đổi 1 nhanh chóng</h4>
                            <p class="text-slate-600">Áp dụng chính sách <b>1 đổi 1 trong vòng 30 ngày</b> đầu tiên nếu sản phẩm phát sinh lỗi phần cứng từ nhà sản xuất.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-truck-fast"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Bảo hành tận nơi miễn phí</h4>
                            <p class="text-slate-600">Điện Máy PRO hỗ trợ kỹ thuật viên kiểm tra và bảo hành sản phẩm tận nhà miễn phí trong phạm vi bán kính 20km tính từ cửa hàng gần nhất.</p>
                        </div>
                    </div>
                </div>
            `,
            content_en: `
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Extended Warranty Period</h4>
                            <p class="text-slate-600">Official manufacturer warranty of <b>12 months</b> for all products distributed by Điện Máy PRO.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-arrow-rotate-left"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Hassle-free 1-to-1 Replacement</h4>
                            <p class="text-slate-600">Enjoy <b>1-to-1 replacement within the first 30 days</b> if the product experiences any hardware faults from the manufacturer.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-truck-fast"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Free On-site Home Support</h4>
                            <p class="text-slate-600">Điện Máy PRO supports on-site home inspection and warranty repair completely free of charge within a 20km radius of the nearest store.</p>
                        </div>
                    </div>
                </div>
            `
        },
        return: {
            title_vi: "Chính Sách Đổi Trả 1-1",
            title_en: "1-to-1 Exchange & Return Policy",
            icon: "fa-arrows-rotate",
            content_vi: `
                <div class="space-y-6">
                    <p class="text-slate-600 mb-4">Nhằm bảo vệ tối đa quyền lợi khách hàng, Điện Máy PRO cam kết chính sách đổi trả hàng minh bạch như sau:</p>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-calendar-check"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Thời hạn đổi trả 30 ngày</h4>
                            <p class="text-slate-600">Đổi mới sản phẩm cùng loại hoàn toàn miễn phí nếu phát sinh lỗi kỹ thuật của nhà sản xuất.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-box-open"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Điều kiện áp dụng</h4>
                            <p class="text-slate-600">Sản phẩm đổi trả phải còn đầy đủ vỏ hộp, tem nhãn, phụ kiện đi kèm, quà tặng kèm theo (nếu có) và không bị nứt vỡ, trầy xước phần cứng.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-circle-dollar-to-slot"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Chính sách thu hồi & hoàn tiền</h4>
                            <p class="text-slate-600">Nếu không có sản phẩm cùng model để đổi, khách hàng có thể chọn đổi sang mẫu khác (bù trừ chênh lệch) hoặc được hoàn trả 100% giá trị hóa đơn.</p>
                        </div>
                    </div>
                </div>
            `,
            content_en: `
                <div class="space-y-6">
                    <p class="text-slate-600 mb-4">To ensure maximum customer satisfaction, Điện Máy PRO guarantees a fully transparent exchange policy:</p>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-calendar-check"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">30-Day Exchange Period</h4>
                            <p class="text-slate-600">Exchange for a brand-new identical product completely free of charge in case of manufacturer engineering faults.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-box-open"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Eligibility Conditions</h4>
                            <p class="text-slate-600">Returned products must remain in original packaging, with intact warranty stamps, all accessories, gifts (if any), and no scratches or physical damage.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-circle-dollar-to-slot"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Refund & Alternative Solutions</h4>
                            <p class="text-slate-600">If the same model is out of stock, customers can choose an alternative product (pay/receive the price difference) or request a 100% full refund.</p>
                        </div>
                    </div>
                </div>
            `
        },
        delivery: {
            title_vi: "Dịch Vụ Giao Hàng & Lắp Đặt",
            title_en: "Delivery & Installation Service",
            icon: "fa-truck-ramp-box",
            content_vi: `
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-truck-fast"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Giao hàng hỏa tốc trong 2 giờ</h4>
                            <p class="text-slate-600">Áp dụng miễn phí vận chuyển trong bán kính 10km cho tất cả đơn hàng từ 5.000.000đ. Đội ngũ giao hàng hỏa tốc phục vụ trong 2 giờ kể từ khi xác nhận đơn.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Lắp đặt chuyên nghiệp tại nhà</h4>
                            <p class="text-slate-600">Kỹ thuật viên lành nghề chịu trách nhiệm lắp đặt đầy đủ các thiết bị gia dụng lớn (Tivi, Tủ lạnh, Máy giặt, Điều hòa...) chuẩn kỹ thuật an toàn.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-chalkboard-user"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Bàn giao & Hướng dẫn sử dụng</h4>
                            <p class="text-slate-600">Sau khi lắp đặt, nhân viên sẽ tiến hành chạy thử sản phẩm ổn định, bàn giao phiếu bảo hành và hướng dẫn quý khách cách sử dụng chi tiết, bền bỉ nhất.</p>
                        </div>
                    </div>
                </div>
            `,
            content_en: `
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-truck-fast"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Express 2-Hour Delivery</h4>
                            <p class="text-slate-600">Free shipping within a 10km radius for all orders over 5,000,000đ. Our express delivery team delivers within 2 hours of order confirmation.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Professional Home Installation</h4>
                            <p class="text-slate-600">Certified technicians handle full assembly and installation for large home appliances (TV, Refrigerator, Washing Machine, AC) under strict safety guidelines.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-chalkboard-user"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Product Walkthrough & Handover</h4>
                            <p class="text-slate-600">Once installed, the crew runs standard tests, hands over the warranty certificate, and provides a thorough user demonstration for optimal appliance life.</p>
                        </div>
                    </div>
                </div>
            `
        },
        installment: {
            title_vi: "Hướng Dẫn Mua Trả Góp 0%",
            title_en: "0% Interest Installment Guide",
            icon: "fa-credit-card",
            content_vi: `
                <div class="space-y-6">
                    <p class="text-slate-600 mb-4">Điện Máy PRO cung cấp hai hình thức trả góp linh hoạt giúp quý khách dễ dàng sở hữu sản phẩm yêu thích:</p>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-credit-card"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Trả góp 0% lãi suất qua thẻ tín dụng</h4>
                            <p class="text-slate-600">Hỗ trợ kỳ hạn linh hoạt 3, 6, 9, 12 tháng qua thẻ tín dụng của 25+ ngân hàng uy tín. Không cần chứng minh thu nhập, không mất phí làm hồ sơ.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Trả góp duyệt siêu tốc qua Công ty tài chính</h4>
                            <p class="text-slate-600">Chỉ cần Căn cước công dân gắn chip. Duyệt hồ sơ nhanh chóng chỉ trong 10-15 phút tại hệ thống cửa hàng Điện Máy PRO toàn quốc (Home Credit, HD Saison).</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-piggy-bank"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Khoản trả trước linh động</h4>
                            <p class="text-slate-600">Trả trước từ 0đ đến 30% giá trị sản phẩm tùy điều kiện tài chính cá nhân của quý khách.</p>
                        </div>
                    </div>
                </div>
            `,
            content_en: `
                <div class="space-y-6">
                    <p class="text-slate-600 mb-4">Điện Máy PRO offers two flexible installment methods to help you purchase your dream products with ease:</p>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-credit-card"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">0% Interest Credit Card Installment</h4>
                            <p class="text-slate-600">Support flexible terms of 3, 6, 9, 12 months with cards from 25+ major banks. No income proof required, zero document fees.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Financial Company Quick Approval</h4>
                            <p class="text-slate-600">Only National ID required. File review and approval completed within 10-15 minutes at any Điện Máy PRO outlet nationwide (Home Credit, HD Saison).</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fa-solid fa-piggy-bank"></i></div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-base mb-1">Flexible Down Payments</h4>
                            <p class="text-slate-600">Choose to pay upfront from 0đ up to 30% of the item's total invoice value depending on your needs.</p>
                        </div>
                    </div>
                </div>
            `
        },
        faq: {
            title_vi: "Câu Hỏi Thường Gặp (FAQ)",
            title_en: "Frequently Asked Questions (FAQ)",
            icon: "fa-circle-question",
            content_vi: `
                <div class="space-y-5">
                    <div class="border-b border-gray-100 pb-4">
                        <h4 class="font-bold text-slate-800 text-sm mb-1.5 flex items-center gap-2"><span class="text-primary font-black">Q:</span> Tôi có thể thanh toán qua những hình thức nào?</h4>
                        <p class="text-slate-600 text-xs pl-5"><span class="text-green-600 font-bold">A:</span> Điện Máy PRO hỗ trợ tiền mặt khi nhận hàng (COD), Chuyển khoản qua mã VietQR, Trả góp qua cổng Home PayLater, và Thẻ tín dụng quốc tế.</p>
                    </div>
                    <div class="border-b border-gray-100 pb-4">
                        <h4 class="font-bold text-slate-800 text-sm mb-1.5 flex items-center gap-2"><span class="text-primary font-black">Q:</span> Cửa hàng có hỗ trợ giao lắp thiết bị trong ngày không?</h4>
                        <p class="text-slate-600 text-xs pl-5"><span class="text-green-600 font-bold">A:</span> Có! Đối với các đơn hàng điện lạnh, điện máy lớn đặt trước 15h00 trong khu vực nội thành, chúng tôi hỗ trợ giao hàng và lắp đặt hoàn thiện ngay trong ngày.</p>
                    </div>
                    <div class="border-b border-gray-100 pb-4">
                        <h4 class="font-bold text-slate-800 text-sm mb-1.5 flex items-center gap-2"><span class="text-primary font-black">Q:</span> Bảo hành điện tử của tôi được kích hoạt như thế nào?</h4>
                        <p class="text-slate-600 text-xs pl-5"><span class="text-green-600 font-bold">A:</span> Kể từ thời điểm hoàn tất thanh toán và bàn giao máy, số điện thoại đăng ký mua hàng của quý khách sẽ được lưu trữ tự động trên máy chủ bảo hành quốc gia để tra cứu mà không cần giữ lại giấy tờ phiền toái.</p>
                    </div>
                </div>
            `,
            content_en: `
                <div class="space-y-5">
                    <div class="border-b border-gray-100 pb-4">
                        <h4 class="font-bold text-slate-800 text-sm mb-1.5 flex items-center gap-2"><span class="text-primary font-black">Q:</span> Which payment methods do you support?</h4>
                        <p class="text-slate-600 text-xs pl-5"><span class="text-green-600 font-bold">A:</span> Điện Máy PRO supports Cash on Delivery (COD), Direct Bank Transfer with VietQR, Installment plans via Home PayLater, and international credit/debit cards.</p>
                    </div>
                    <div class="border-b border-gray-100 pb-4">
                        <h4 class="font-bold text-slate-800 text-sm mb-1.5 flex items-center gap-2"><span class="text-primary font-black">Q:</span> Do you offer same-day delivery and installation?</h4>
                        <p class="text-slate-600 text-xs pl-5"><span class="text-green-600 font-bold">A:</span> Yes! For all major appliances ordered before 3:00 PM inside metropolitan areas, we guarantee full same-day shipping and functional on-site assembly.</p>
                    </div>
                    <div class="border-b border-gray-100 pb-4">
                        <h4 class="font-bold text-slate-800 text-sm mb-1.5 flex items-center gap-2"><span class="text-primary font-black">Q:</span> How is my electronic warranty activated?</h4>
                        <p class="text-slate-600 text-xs pl-5"><span class="text-green-600 font-bold">A:</span> Immediately after invoice payment, your buyer phone number is registered automatically on our online national warranty database, allowing hassle-free verification without physical paper documents.</p>
                    </div>
                </div>
            `
        },
        terms: {
            title_vi: "Điều Khoản Sử Dụng Website",
            title_en: "Website Terms of Use",
            icon: "fa-gavel",
            content_vi: `
                <div class="space-y-4">
                    <p class="text-slate-600">Khi truy cập và đặt mua sản phẩm tại Điện Máy PRO, quý khách đồng ý cam kết tuân thủ các điều khoản sau:</p>
                    <ul class="list-disc pl-5 space-y-2 text-slate-600">
                        <li><b>Thông tin tài khoản:</b> Cung cấp đầy đủ, trung thực thông tin liên hệ và địa chỉ giao nhận hàng.</li>
                        <li><b>Quyền sở hữu trí tuệ:</b> Mọi nội dung, hình ảnh thiết kế trên website đều thuộc bản quyền sở hữu của Điện Máy PRO.</li>
                        <li><b>Thay đổi dịch vụ:</b> Điện Máy PRO giữ quyền điều chỉnh giá sản phẩm, thông tin khuyến mãi mà không cần thông báo trước tùy theo nguồn cung thị trường.</li>
                    </ul>
                </div>
            `,
            content_en: `
                <div class="space-y-4">
                    <p class="text-slate-600">By accessing and buying from Điện Máy PRO, you acknowledge and agree to comply with the following terms:</p>
                    <ul class="list-disc pl-5 space-y-2 text-slate-600">
                        <li><b>Account Accuracy:</b> Provide complete and truthful contact details and shipping addresses.</li>
                        <li><b>Intellectual Property:</b> All written media, graphics, and interface assets remain the exclusive copyrighted property of Điện Máy PRO.</li>
                        <li><b>Service Adjustments:</b> Điện Máy PRO reserves the right to modify prices and campaigns without prior notice due to supplier inventory.</li>
                    </ul>
                </div>
            `
        },
        privacy: {
            title_vi: "Bảo Mật Thông Tin Khách Hàng",
            title_en: "Customer Data Privacy Policy",
            icon: "fa-lock",
            content_vi: `
                <div class="space-y-4">
                    <p class="text-slate-600">Điện Máy PRO hiểu rõ tầm quan trọng của việc bảo vệ dữ liệu cá nhân, chúng tôi cam kết thực thi nghiêm ngặt:</p>
                    <ul class="list-disc pl-5 space-y-2 text-slate-600">
                        <li><b>Mục đích thu thập:</b> Chỉ sử dụng thông tin cá nhân (Họ tên, SĐT, Địa chỉ, Email) để thực hiện giao dịch, vận chuyển và kích hoạt bảo hành điện tử.</li>
                        <li><b>Cam kết bảo mật:</b> Không bán, cho thuê hay tiết lộ thông tin của quý khách cho bất cứ đơn vị thứ ba nào ngoài đối tác vận tải được chỉ định.</li>
                        <li><b>Mã hóa giao dịch:</b> Toàn bộ cổng thanh toán điện tử đều được bảo mật đa tầng bởi chứng chỉ bảo mật mã hóa SSL hàng đầu thế giới.</li>
                    </ul>
                </div>
            `,
            content_en: `
                <div class="space-y-4">
                    <p class="text-slate-600">Điện Máy PRO values customer confidentiality, adhering strictly to global privacy policies:</p>
                    <ul class="list-disc pl-5 space-y-2 text-slate-600">
                        <li><b>Purpose of Processing:</b> We collect and process user contact information solely for order fulfillment, delivery logistics, and e-warranty indexing.</li>
                        <li><b>No Third-Party Sharing:</b> Your details are never rented or shared with external third parties except authorized logistic companies.</li>
                        <li><b>Encrypted Payments:</b> Online transaction pipelines are protected under secure layers via premium global SSL certificates.</li>
                    </ul>
                </div>
            `
        }
    };

    /**
     * Hiển thị modal chính sách với hiệu ứng fade-in & scale-up premium
     * @param {string} type - Loại chính sách (warranty, return, delivery, installment, faq, terms, privacy)
     */
    function showPolicyModal(type) {
        const data = policyData[type];
        if (!data) return;
        
        const lang = '<?= getCurrentLang() ?>';
        const title = lang === 'en' ? data.title_en : data.title_vi;
        const content = lang === 'en' ? data.content_en : data.content_vi;
        
        // Cập nhật tiêu đề, icon và nội dung
        document.getElementById('policy-modal-title').innerText = title;
        document.getElementById('policy-modal-icon').innerHTML = `<i class="fa-solid ${data.icon}"></i>`;
        document.getElementById('policy-modal-content').innerHTML = content;
        
        // Kích hoạt hiển thị modal
        const modal = document.getElementById('policy-modal');
        modal.classList.remove('opacity-0', 'pointer-events-none');
        
        const dialog = modal.querySelector('.transform');
        dialog.classList.remove('scale-95');
        dialog.classList.add('scale-100');
    }

    /**
     * Đóng modal chính sách với hiệu ứng thu nhỏ mượt mà
     */
    function closePolicyModal() {
        const modal = document.getElementById('policy-modal');
        modal.classList.add('opacity-0', 'pointer-events-none');
        
        const dialog = modal.querySelector('.transform');
        dialog.classList.remove('scale-100');
        dialog.classList.add('scale-95');
    }

    // Đóng modal khi nhấp chuột ngoài vùng nội dung (Click outside to close)
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('policy-modal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closePolicyModal();
                }
            });
        }
    });

    aiInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Hiển thị thông báo nếu tài khoản bị khóa
    <?php if (isset($_GET['banned']) && $_GET['banned'] === '1'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: <?= json_encode(__("account_banned_title", "Tài khoản bị khóa"), JSON_UNESCAPED_UNICODE) ?>,
                text: <?= json_encode(__("account_banned_desc", "Tài khoản của bạn đã bị khóa tạm thời do vi phạm điều khoản dịch vụ hoặc có dấu hiệu bất thường. Vui lòng liên hệ ban quản trị để được hỗ trợ."), JSON_UNESCAPED_UNICODE) ?>,
                confirmButtonColor: '#0046ab',
                confirmButtonText: <?= json_encode(__("agree", "Đồng ý"), JSON_UNESCAPED_UNICODE) ?>
            });
        } else {
            alert(<?= json_encode(__("account_banned_desc", "Tài khoản của bạn đã bị khóa tạm thời do vi phạm điều khoản dịch vụ hoặc có dấu hiệu bất thường. Vui lòng liên hệ ban quản trị để được hỗ trợ."), JSON_UNESCAPED_UNICODE) ?>);
        }

        // Xóa tham số ?banned=1 khỏi URL để giao diện sạch sẽ hơn khi reload
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('banned');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }
    });
    <?php endif; ?>
</script>
    <!-- THANH SO SÁNH STICKY BOTTOM (Thiết kế linh hoạt: Bar hoặc Ô vuông) -->
    <div id="compare-sticky-bar" class="fixed bottom-0 left-0 right-0 z-[1000] bg-white border-t border-gray-200 shadow-[0_-10px_30px_rgba(0,0,0,0.15)] hidden transition-all duration-500 ease-in-out overflow-hidden">
        <div class="container mx-auto px-4 py-3 h-full">
            <div id="compare-bar-main-flex" class="flex flex-col md:flex-row items-center justify-between gap-4 h-full">
                
                <!-- Khu vực danh sách SP (Sẽ ẩn khi thu gọn) -->
                <div id="compare-bar-content" class="flex items-center gap-3 flex-1 w-full md:w-auto">
                    <div id="compare-bar-items" class="flex items-center gap-3 flex-1 overflow-x-auto hide-scrollbar py-1">
                        <!-- Items sẽ được render bằng JS -->
                    </div>
                </div>

                <!-- Khu vực Actions & Info -->
                <div id="compare-bar-actions" class="flex items-center gap-3 shrink-0 w-full md:w-auto justify-center md:justify-end">
                    <!-- Text thông tin (Sẽ ẩn khi thu gọn để thành ô vuông) -->
                    <div id="compare-bar-info" class="text-right hidden md:block">
                        <p class="text-[13px] font-bold text-gray-800">Đã chọn <span id="compare-bar-count" class="text-primary">0</span> sản phẩm</p>
                    </div>
                    
                    <div class="flex items-center gap-2 w-full md:w-auto justify-center">
                        <!-- Nút Mở rộng/Thu gọn (Sẽ đổi style khi thu gọn) -->
                        <button id="compare-toggle-btn" onclick="toggleCompareBar()" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-bold text-[13px] hover:bg-gray-50 transition-all flex items-center gap-2 bg-white shadow-sm">
                            <i id="compare-bar-toggle-icon" class="fa-solid fa-chevron-down"></i>
                            <span id="compare-bar-toggle-text">Thu gọn</span>
                        </button>

                        <!-- Nút So sánh (Màu đỏ đặc trưng) -->
                        <a id="compare-main-btn" href="compare.php" class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-extrabold text-[13px] hover:bg-red-700 transition-all shadow-lg shadow-red-200 flex items-center gap-2 whitespace-nowrap">
                            So sánh <i class="fa-solid fa-right-left"></i>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <style>
        /* Animation slide up khi hiện lần đầu */
        .animate-slide-up { animation: slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slide-up {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Mode Thu gọn (Ô vuông Floating) */
        #compare-sticky-bar.is-collapsed {
            left: auto !important;
            right: 24px !important;
            bottom: 24px !important;
            width: 160px !important;
            height: auto !important;
            border-radius: 24px !important;
            border: 2px solid #f3f4f6 !important;
            padding: 16px 12px !important;
        }

        #compare-sticky-bar.is-collapsed .container {
            padding: 0 !important;
            width: 100% !important;
        }

        #compare-sticky-bar.is-collapsed #compare-bar-main-flex {
            flex-direction: column !important;
            gap: 12px !important;
        }

        /* Ẩn danh sách SP và Text thừa khi thu gọn */
        #compare-sticky-bar.is-collapsed #compare-bar-content,
        #compare-sticky-bar.is-collapsed #compare-bar-info {
            display: none !important;
        }

        /* Chỉnh lại các nút thành dạng Stack (cột) trong ô vuông */
        #compare-sticky-bar.is-collapsed #compare-bar-actions,
        #compare-sticky-bar.is-collapsed #compare-bar-actions > div {
            width: 100% !important;
            flex-direction: column !important;
        }

        #compare-sticky-bar.is-collapsed #compare-main-btn,
        #compare-sticky-bar.is-collapsed #compare-toggle-btn {
            width: 100% !important;
            justify-content: center !important;
            padding: 10px 8px !important;
            font-size: 12px !important;
        }

        #compare-sticky-bar.is-collapsed #compare-toggle-btn {
            order: 2; /* Nút mở rộng nằm dưới */
            border: none !important;
            background: #f9fafb !important;
            color: #6b7280 !important;
        }

        #compare-sticky-bar.is-collapsed #compare-main-btn {
            order: 1; /* Nút so sánh nằm trên */
        }

        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    
    <!-- MODAL TÌM KIẾM SẢN PHẨM ĐỂ SO SÁNH -->
    <div id="compare-search-modal" 
         onclick="if(event.target === this) closeCompareSearch()"
         class="fixed inset-0 z-[1100] hidden flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden animate-slide-up">
            <!-- Header Modal -->
            <div class="p-4 md:p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <h3 class="text-lg font-extrabold text-gray-800">Thêm sản phẩm so sánh</h3>
                <button onclick="closeCompareSearch()" class="w-10 h-10 rounded-full hover:bg-gray-200 flex items-center justify-center text-gray-500 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <!-- Body Modal -->
            <div class="p-4 md:p-6">
                <!-- Ô nhập liệu kiểu mới -->
                <div class="relative mb-6">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="compare-search-input" 
                           placeholder="Tìm sản phẩm muốn so sánh..." 
                           oninput="handleCompareSearch(this.value)"
                           class="w-full pl-12 pr-4 py-4 bg-white border-2 border-gray-100 rounded-2xl focus:border-primary focus:ring-0 text-gray-700 font-medium transition-all shadow-sm">
                </div>
                
                <!-- Danh sách kết quả (Scrollable) -->
                <div id="compare-search-results" class="max-h-[400px] overflow-y-auto pr-2 custom-scrollbar space-y-3">
                    <div class="text-center py-10 text-gray-400">
                        <i class="fa-solid fa-keyboard text-3xl mb-3 block opacity-20"></i>
                        <p class="text-sm italic">Nhập tên sản phẩm để tìm kiếm...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        @keyframes slide-up {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</body>

</html>