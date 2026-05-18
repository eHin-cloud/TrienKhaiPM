# Project Memory - Hệ thống Thương Mại Điện Tử (PMVcuoi)

## Trạng thái hiện tại
- Đã khắc phục lỗi không đăng nhập được (Invalid username or password!) do mật khẩu trong database lưu dạng plain text trong khi code sử dụng `password_verify`.
- Đã chạy script hash lại toàn bộ mật khẩu cũ (13 tài khoản) sang định dạng Bcrypt.
- Đã khắc phục lỗi không hiển thị tài khoản trong Admin Panel bằng cách refactor `AdminService` sử dụng `UserRepository`.
- Đã bổ sung cột `address` vào bảng `users` để đồng bộ với giao diện Profile và Admin.
- Đã sửa lỗi SQL trong `UserRepository::update` (sai tên cột `fullname` và thiếu `address`).
- Đã hoàn tất hệ thống đa ngôn ngữ (i18n) cho toàn bộ các trang người dùng (User-facing pages).
- Đã sửa lỗi hiển thị IP `::1` trong lịch sử đăng nhập, chuyển sang `127.0.0.1` và hỗ trợ lấy IP thực qua Proxy.
- Đã bổ sung tính năng xác định vị trí địa lý (Thành phố, Quốc gia) khi đăng nhập và lưu vào database.
- Đã loại bỏ phần "Sản phẩm vừa xem" (dữ liệu từ database) ở trang chủ để tránh trùng lặp với phần "Sản phẩm đã xem" (dữ liệu từ localStorage) ở footer.
- Đã xóa mục "Gợi ý mua kèm" ở trang chủ để tránh rối mắt. Đổi tên mục "Gợi ý cho bạn" thành "Sản Phẩm Đề Xuất" (tiếng Việt) và "Recommended Products" (tiếng Anh) để chuyên nghiệp hơn. Cấu trúc lại và đưa xuống cuối trang bằng giao diện vuốt ngang (carousel).
- Đã sửa lỗi "Unexpected token <... is not valid JSON" và "Unexpected end of JSON input" ở AI Chatbot.
- Nguyên nhân: Hệ thống bảo vệ CSRF (`validate_csrf_request`) đã đọc và tiêu thụ hết dữ liệu từ `php://input` trước khi Chatbot có thể đọc.
- Giải pháp: Lưu trữ dữ liệu JSON đã đọc vào biến toàn cục `$GLOBALS['JSON_INPUT']` trong `core/security.php` để Chatbot có thể tái sử dụng.
- Đã sửa lỗi "Unexpected end of JSON input" do thiếu extension `cURL` trên môi trường XAMPP.
- Nguyên nhân: `curl_init()` không tồn tại gây ra Fatal Error, PHP dừng đột ngột và trả về chuỗi rỗng `""`.
- Giải pháp: Thêm cơ chế fallback sử dụng `file_get_contents` với `stream_context_create` nếu server không hỗ trợ cURL. Áp dụng cho cả `views/api/chatbot.php` và `views/pages/payment.php` (PayOS API). Sử dụng variable-variable `$var = 'http_response_header'` để tránh cảnh báo Deprecated của PHP 8.4 phá hỏng JSON output.
- Đã sửa lỗi "HTTP 404" khi gọi Gemini API.
- Nguyên nhân: Các model cũ như `gemini-1.0-pro` không còn khả dụng hoặc trả về 404 cho API key hiện tại.
- Giải pháp: Cập nhật danh sách model fallback với các alias mạnh mẽ hơn như `gemini-flash-latest`, `gemini-pro-latest` và các model thế hệ mới `gemini-2.0-flash`.
- Đã bổ sung hỗ trợ tiếng Anh cho AI Chatbot (cả giao diện gợi ý nhanh và chỉ thị cho AI trả lời bằng tiếng Anh khi người dùng đổi ngôn ngữ).
- Đã nâng cấp logic prompt của AI Chatbot, giúp AI tự động nhận diện ngôn ngữ câu hỏi của khách hàng (Tiếng Việt hoặc Tiếng Anh) và trả lời bằng ngôn ngữ tương ứng thay vì phụ thuộc cứng vào ngôn ngữ đang chọn trên giao diện.
- Đã thêm chức năng "Lịch sử đăng nhập" dành riêng cho Admin trong bảng điều khiển (`admin.php`), cho phép Admin xem và tìm kiếm (theo tài khoản hoặc ngày) toàn bộ lịch sử đăng nhập của tất cả người dùng (bao gồm IP, Trình duyệt, Thiết bị, Trạng thái). Kèm theo cơ chế phân trang chi tiết (20 bản ghi/trang).
- Bổ sung nút **"Khóa tài khoản / Mở khóa"** trực tiếp trên trang Lịch sử đăng nhập. Thêm cột `is_banned` (TINYINT) vào bảng `users` để hỗ trợ việc ngăn chặn các IP/tài khoản có dấu hiệu spam đăng nhập.
- Đã sửa toàn diện lỗi Chatbot trên hosting InfinityFree (wuaze.com):
  - **Route rename**: `chatbot.php` → `ai_assist.php` (public route) để tránh WAF chặn URL chứa từ "bot" → fix lỗi 403 Forbidden.
  - **Model update**: Các model Gemini 1.5 đã bị Google gỡ (trả 404). Chỉ giữ `gemini-2.0-flash` + `gemini-2.0-flash-lite`. Thêm xử lý lỗi 429 (hết quota).
  - **Raw input preservation**: Lưu `php://input` vào `$GLOBALS['RAW_PHP_INPUT']` tại `index.php` TRƯỚC khi `validate_csrf_request()` đọc, đảm bảo chatbot luôn nhận được data → fix lỗi "Vui lòng nhập câu hỏi".
  - **CSRF exemption**: Route `ai_assist.php` được miễn trừ CSRF (chỉ đọc dữ liệu, không thay đổi).
  - **Session config**: Khôi phục cấu hình session cookie bảo mật (HttpOnly, Secure, SameSite) từ bản cũ.
- Đã cập nhật logic AI Chatbot để hỗ trợ trả lời chính xác thông tin ngày giờ hiện tại. Chatbot giờ đây có thể trả lời các câu hỏi về Thứ, Ngày, Tháng, Năm và Giờ hiện tại nhờ việc bổ sung "Time Context" vào prompt gửi lên Gemini API.
- Đã khắc phục triệt để lỗi "HTTP 404" khi gọi Gemini API bằng cách chuyển đổi endpoint từ `v1beta` sang `v1` (phiên bản ổn định) và chuẩn hóa lại danh sách model fallback (`gemini-1.5-flash`, `gemini-1.5-pro`, `gemini-pro`, `gemini-1.0-pro`). Đồng thời bổ sung kiểm tra cấu hình API Key.
- Đã triển khai bộ 3 tính năng mới cho Website:
  - **Quản lý địa chỉ (Address Book):** Cho phép người dùng thêm/sửa/xóa và chọn địa chỉ mặc định tại trang Profile. Tích hợp bộ chọn địa chỉ (Address Picker) vào trang Checkout.
  - **Thông báo (Notifications):** Thêm icon chuông trên Header với badge đếm số lượng chưa đọc. Hỗ trợ xem nhanh qua dropdown và trang danh sách chi tiết trong Profile.
  - **Tìm kiếm thông minh (Search Suggestions):** Gợi ý sản phẩm và danh mục theo thời gian thực (real-time) ngay khi người dùng gõ vào thanh tìm kiếm.

## Các file đã can thiệp (Gần đây)
- [x] Đã khắc phục lỗi không hiển thị lịch sử đăng nhập khi sử dụng tài khoản Google.
- [x] Đã refactor logic ghi lịch sử đăng nhập vào hàm helper `record_login_history()` trong `core/security.php` để dùng chung cho toàn bộ hệ thống (Web, API, Google Login, 2FA).
- [x] Đã bổ sung ghi log thất bại cho API Auth.

## Các file đã can thiệp (Gần đây)
- [MODIFY] `core/security.php`: Thêm hàm `record_login_history()` để chuẩn hóa việc ghi log đăng nhập (bao gồm xử lý IP Proxy/Cloudflare và IPv6 `::1`).
- [MODIFY] `views/api/google_callback.php`: Tích hợp ghi log khi đăng nhập Google thành công.
- [MODIFY] `views/partials/header.php`: Chuyển sang dùng `record_login_history()` cho luồng đăng nhập Web thông thường.
- [MODIFY] `public/api/auth.php`: Tích hợp ghi log (thành công & thất bại) cho luồng API Mobile.
- [MODIFY] `views/pages/two_factor.php`: Chuyển sang dùng `record_login_history()` cho luồng xác thực 2 lớp.
- [MODIFY] `views/partials/header.php`: Cập nhật logic xử lý đăng nhập, chặn truy cập nếu tài khoản đang bị khóa (`is_banned == 1`) và ghi log thất bại.
- [MODIFY] `src/Service/AdminService.php`: Thêm logic tự động phân trang cho tất cả các tab và bổ sung luồng action `toggle_user_lock` để xử lý việc khóa tài khoản.
- [MODIFY] `views/admin/admin.php`: Thêm cột "Thao tác" chứa nút khóa/mở khóa tài khoản ngay trên bảng Lịch sử đăng nhập. Đưa cụm giao diện hiển thị phân trang ra thành một khối Global dùng chung.
- [MODIFY] `views/api/chatbot.php`: Sửa đổi instruction prompt của RAG để AI tự do trả lời theo ngôn ngữ đầu vào. Bọc toàn bộ logic trong try-catch Throwable. Chuyển nhận data từ `$_POST` (form-urlencoded) thay vì chỉ JSON body. Đặt `ob_start()` + `error_reporting(0)` ngay đầu file để chặn hosting chèn mã rác. **Bổ sung logic Real-time Time Context và fix lỗi 404 bằng cách dùng endpoint v1 + model chuẩn.**
- [MODIFY] `views/partials/footer.php`: Đổi hàm `callGemini()` từ gửi `application/json` sang `application/x-www-form-urlencoded` để tránh WAF/ModSecurity trên hosting InfinityFree chặn request 403.
- [MODIFY] `core/lang/vi.php` & `en.php`: Mở rộng hệ thống dịch thuật với hàng trăm key mới.
- [MODIFY] `views/pages/track_order.php`: Chuyển đổi toàn bộ sang đa ngôn ngữ, bao gồm các modal bảo hành, đổi trả.
- [MODIFY] `views/pages/payment.php`: Chuyển đổi trang thanh toán QR sang đa ngôn ngữ.
- [MODIFY] `views/pages/checkout.php`: Chuyển đổi trang đặt hàng sang đa ngôn ngữ.
- [MODIFY] `views/pages/profile.php`: Chuyển đổi trang cá nhân sang đa ngôn ngữ. Bổ sung tab Quản lý địa chỉ và Thông báo kèm logic JS tương ứng.
- [MODIFY] `views/pages/cart.php`: Chuyển đổi giỏ hàng sang đa ngôn ngữ.
- [MODIFY] `views/pages/checkout.php`: Tích hợp tính năng chọn địa chỉ từ Address Book.
- [CREATE] `public/api/search.php`: API endpoint phục vụ gợi ý tìm kiếm.
- [MODIFY] `views/partials/header.php`: Tích hợp UI Tìm kiếm thông minh và Dropdown Thông báo. Bổ sung JS logic điều khiển.
- [MODIFY] `core/lang/vi.php` & `en.php`: Thêm các key dịch thuật cho địa chỉ và thông báo.

## Logic i18n
- Sử dụng hàm helper `__()` được định nghĩa trong `core/lang/vi.php` và `en.php`.
- Ngôn ngữ được lưu trong `$_SESSION['lang']`, mặc định là `vi`.
- Toàn bộ chuỗi cứng (Hardcoded Vietnamese) đã được thay thế bằng các key tương ứng trong mảng `$_LANG`.

### 2. Internationalization (i18n) Status
- [x] Horizontal category menu translated using `__cat()` helper.
- [x] "Recommended for You", "Cross-sell", and "Recently Viewed" sections in `index.php` translated.
- [x] Footer (including contact info, links, and AI Chat window) fully internationalized.
- [x] Language files `vi.php` and `en.php` updated with over 340+ keys.
- [x] SweetAlert2 notifications and JS-driven strings translated via PHP injection.

### 3. Recent Changes
- Updated `views/partials/header.php`, `views/partials/footer.php`, `views/pages/index.php`, and `views/partials/product_card.php` to use `__()` and `__cat()`.
- Added missing translation keys for newsletter, clear history, and product card actions.
- Synchronized Vietnamese and English language files.

## TODO
- [ ] Tách tiếp các module Catalog, Cart, Checkout, Order sang API JSON.
- [ ] Xây dựng chuẩn auth token/JWT cho mobile app, thay vì phụ thuộc session web.
- [ ] Thực hiện refactor các module còn lại trong Admin Panel sang Repository Pattern.
- [ ] Kiểm tra tính nhất quán của giao diện khi chuyển đổi ngôn ngữ (đặc biệt là các nút bấm có text dài).
- [ ] Xóa các file script tạm đã liệt kê trước đó.
- [ ] Chạy migration `sql/add_two_factor_columns_to_users.sql` trên hosting nếu chưa có cột `two_factor_enabled`.
- [ ] Đăng ký redirect URI hosting trong Google Cloud Console.

## API tách dần cho mobile (2026-05-11)
- Đã bắt đầu tách lớp API JSON cho module Auth, Profile, Catalog, Cart, Checkout/Order và Payment.
- Đã thêm `core/api.php` với helper `api_json_response()`, `api_request_data()` và `api_bearer_token()`.
- Đã thêm chuẩn token JWT nội bộ ở `core/jwt.php` để mobile app có thể dùng Bearer token.
- Đã cập nhật `public/api/auth.php` để trả về JWT khi login / 2FA success, và thêm action `me`.
- Đã cập nhật `public/api/profile.php` để ưu tiên đọc Bearer token, sau đó fallback sang session.
- Đã tạo các endpoint mới:
  - `public/api/auth.php`
  - `public/api/profile.php`
  - `public/api/catalog.php`
  - `public/api/cart.php`
  - `public/api/checkout.php`
  - `public/api/order.php`
  - `public/api/payment.php`
  - `public/api/webhook_payos.php`
  - `public/api/webhook_sepay.php`
- `public/.htaccess` đã được cập nhật để route `/api/*` đi thẳng vào thư mục API.
- `public/index.php` đã bổ sung map riêng cho API routes mà vẫn giữ luồng web hiện tại.
- Catalog API hiện hỗ trợ: products, product-detail, related, same-brand, suggested, categories, brands.
- Cart API hiện hỗ trợ: view, add, update, delete, increase, decrease, count, clear.
- Checkout API hiện hỗ trợ: summary, create_order, apply_voucher.
- Order API hiện hỗ trợ: list, detail, status.
- Payment API hiện hỗ trợ: details, confirm_manual, status, payos_create.
- Webhook PayOS/SePay đã được tách thành API riêng để xử lý cập nhật trạng thái đơn hàng.
- Bước tiếp theo: test luồng end-to-end, rồi quyết định có tách JWT sang package chuẩn hay giữ helper nội bộ.
- Đã bắt đầu chuẩn hóa auth qua `api_authenticated_user()` để các API core dùng chung logic xác thực, giảm lệ thuộc session.
- Đã hoàn tất refactor checkout/payment để không còn phụ thuộc vào session checkout cũ.
- `public/api/checkout.php` hiện yêu cầu mobile gửi trực tiếp `selected_items` và `voucher` trong request khi tạo đơn / xem summary.
- `public/api/payment.php` chỉ làm việc dựa trên `order_id` và token xác thực; không còn đọc context checkout từ session.
- Đã khởi tạo skeleton Flutter ban đầu trong thư mục `mobile_app/` với `Riverpod + GoRouter + Dio + Secure Storage` và các màn hình placeholder cho Splash/Login/Home/Catalog/Cart/Orders/Profile.
- Đã hoàn thiện `ProfileScreen`, `EditProfileScreen`, `ChangePasswordScreen` theo flow thật.
- Đã hoàn thiện `CatalogScreen` (Infinite Scroll, Filter) và `ProductDetailScreen` (Description, Related, Cross-sell).
- Đã nâng cấp `PaymentScreen` (UI QR, url_launcher, polling) và `OrdersScreen` (Status Filter).
- Đã triển khai **Responsive Layout (2 cột)** cho Tablet/Màn hình lớn tại các màn hình: Product Detail, Checkout, Profile.
- Đã chuẩn hóa hệ thống điều hướng với `routerProvider` hỗ trợ **Auto Redirect** dựa trên trạng thái xác thực.
- Đã tích hợp cơ chế tự động đăng xuất khi gặp lỗi **401 (Unauthorized)** tại `ApiClient`.
- Đã xử lý lỗi build Android "different roots" (D: vs C:) bằng cách hướng dẫn người dùng di chuyển `PUB_CACHE` sang ổ D để đồng bộ với vị trí dự án.
- Đã khắc phục lỗi `type 'String' is not a subtype of type 'int' in type cast` tại các model: `CartItem`, `ProfileUser`, `OrderSummary`, `OrderDetailItem`, `AuthUser`, `PaymentDetails` và các logic phân trang tại `CatalogRepository`.
- Đã sửa lỗi Catalog không hiển thị sản phẩm do lệch key dữ liệu (`products` vs `items`) giữa Flutter và Backend.
- [FIX] Models: Sửa lỗi ép kiểu dữ liệu JSON cho Cart, Profile, Orders, Auth, Payment và Catalog pagination.
- [NEW] Auth: Triển khai chức năng Đăng ký tài khoản (Register) hoàn chỉnh trên Mobile App.
- [FIX] Payment: Cải thiện Error Handling cho PayOS, thêm thông báo lỗi chi tiết khi tạo link thất bại.
- [NEW] Catalog: Triển khai bộ lọc nâng cao (Advanced Filter) cho phép lọc theo giá, danh mục và thương hiệu trên App.

## Fix Google OAuth cho Hosting (2026-05-08)
- `core/google_oauth.php`: Sửa `google_oauth_redirect_uri()` tự auto-detect URL. Fallback `getenv()` → `$_ENV` → `$_SERVER` (hosting InfinityFree disable `putenv()`).
- `views/partials/header.php`: Sửa link Google Login và Quên mật khẩu từ hardcode `/PMVSCuoi/public/...` sang relative path.
- `views/api/google_login.php`: Thêm chi tiết exception message để debug trên hosting.
- [MODIFY] `src/Repository/UserRepository.php`: Thêm method `hasTwoFactorColumns()` — fix Fatal Error khi bật 2FA.
- [FIX] Build Android: Hướng dẫn cấu hình `PUB_CACHE` trên ổ D để tránh lỗi `different roots` giữa project (D:) và plugin cache (C:).

## Cập nhật tính năng Mobile App (2026-05-13)
- [x] **Hệ thống Wishlist (Yêu thích):** Hoàn thành Backend (API `wishlist.php`) và Frontend (WishlistScreen, ProductCard toggle).
- [x] **Nâng cấp Order Details:** Hoàn thành giao diện Timeline (Stepper), thông tin người nhận chi tiết và danh sách sản phẩm có ảnh.
- [x] **Quản lý Địa chỉ:** Hoàn thành bảng `addresses`, API `address.php` (CRUD) và giao diện quản lý đa địa chỉ. Tích hợp Address Picker vào trang Checkout.
- [x] **Đánh giá Sản phẩm (Reviews):** Hoàn thành API `review.php` và giao diện `ReviewSection` (Thống kê sao, biểu đồ phân bổ, danh sách bình luận và form gửi đánh giá).
- [x] **Hệ thống Voucher:** Hoàn thành API `voucher.php` (list/apply) và giao diện chọn Voucher (Voucher Wallet) trong Checkout.
- [x] **Tính năng So sánh sản phẩm (Product Comparison):**
    - Đã tạo `ajax_compare.php` để quản lý danh sách trong Session (tối đa 3 SP, cùng loại).
    - Đã tạo giao diện `compare.php` với bảng đối chiếu thông số kỹ thuật tự động.
    - Đã thêm nút So sánh vào thẻ sản phẩm (`product_card.php`) và trang chi tiết (`product_detail.php`).
    - **Nâng cấp:** Đã thêm Thanh so sánh thông minh (Sticky Comparison Bar) ở đáy màn hình với khả năng "biến hình" linh hoạt: Chuyển đổi giữa dạng thanh ngang đầy đủ và dạng ô vuông nhỏ gọn (Floating Box) ở góc màn hình khi thu gọn.
    - **Fix Bug:** Đã sửa lỗi thanh so sánh bị mất khi reload trang bằng cách sử dụng cơ chế truyền dữ liệu trực tiếp từ PHP Session sang JavaScript ngay khi load trang.
    - **UI Polish:** Di chuyển nút So sánh trên thẻ sản phẩm từ góc trái sang góc phải, nằm phía dưới nút Xem nhanh (con mắt) để giao diện gọn gàng hơn.
- [x] **UI Polish:** Đồng bộ hóa `ProductCard` dùng chung trên toàn App, nâng cấp `CheckoutScreen` và `ProfileScreen` với các lối tắt chức năng mới.
- [x] **Bug Fix (Compiler Errors):** Đã sửa hàng loạt lỗi biên dịch Flutter liên quan đến sai đường dẫn Import, thiếu Model (OrderDetail), thiếu trường dữ liệu (oldPrice trong CatalogProduct) và lỗi tham số Widget (AppTextField, backgroundColor cho Card, showBackButton cho MobilePage). Đã chuẩn hóa toàn bộ dự án sang sử dụng **Package Import**.
- [x] **Bug Fix (Parameter Mismatch):** Sửa lỗi sai tên tham số trong `AppActionButton` (loading) và `MobileSectionTitle` (trailing). Cập nhật `ProfileController` và `ProfileState` (thêm getter `user`) để tương thích với màn hình chỉnh sửa hồ sơ. Bổ sung `go_router` import cho `EditProfileScreen`.
- [x] **Web Wishlist (Danh sách yêu thích):**
    - Đã tích hợp hoàn toàn Wishlist vào `profile.php` dưới dạng một tab (`?tab=wishlist`), không còn dùng file `wishlist.php` riêng lẻ.
    - Hoàn thành giao diện trong tab Wishlist với tính năng list, xóa nhanh (AJAX) và thêm vào giỏ hàng.
    - Tích hợp nút Yêu thích (AJAX) vào `product_card.php` và `product_detail.php`.
    - Bổ sung logic đồng bộ trạng thái Heart icon (`syncWishlistIcons`) trên toàn hệ thống web.
    - Cập nhật menu Header và tất cả Sidebar (Profile, Login History) để trỏ về `profile.php?tab=wishlist`.
    - Đã xóa file `views/pages/wishlist.php` và gỡ bỏ route mapping tương ứng trong `public/index.php`.
- [x] **Dọn dẹp thư mục gốc (Root Cleanup):**
    - Đã tạo thư mục `scripts/` và di chuyển toàn bộ các file PHP tiện ích/test (`seed`, `diag`, `test_db`,...) vào đó.
    - Đã tạo thư mục `docs/` và di chuyển các file tài liệu `.md` (`mobile-api-spec.md`, `ngucanh.md`,...) vào đó.
    - Thư mục gốc hiện chỉ còn các file cấu hình cốt lõi và `ai-memory.md`.

### Việc cần làm tiếp theo (TODO)
1. **Trigger thông báo tự động:** Cần thêm logic ở Backend để tự động tạo thông báo khi trạng thái đơn hàng thay đổi.
2. **Debug PayOS:** Quay lại xử lý khi người dùng sẵn sàng (đã hỗ trợ hiển thị Raw Response để tìm lỗi cụ thể).
3. **Cải thiện Catalog Filter:** Thêm lọc theo đánh giá sao (vừa mới có dữ liệu reviews).
