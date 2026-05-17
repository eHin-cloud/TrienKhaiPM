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
  - **Hiển thị nhiều hình ảnh sản phẩm (Product Gallery):** Đã nâng cấp trang chi tiết sản phẩm để hỗ trợ hiển thị nhiều hình ảnh (gallery). Thêm cột `more_images` vào bảng `products` để lưu trữ danh sách ảnh bổ sung (dạng JSON). Giao diện cho phép người dùng click vào các thumbnail để chuyển đổi ảnh chính. **Đã bổ sung tính năng Auto Slide tự động chuyển ảnh sau mỗi 5 giây, hỗ trợ reset bộ đếm khi tương tác thủ công và tự động cuộn thanh thumbnail.**


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
- [MODIFY] `views/pages/product_detail.php`: Cập nhật giao diện hiển thị gallery ảnh (Main Image + Thumbnails) và logic JavaScript xử lý chuyển đổi ảnh.
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
    - **Nâng cấp UX chuyên sâu:** Đã thay thế việc cuộn trang bằng giao diện **Modal Tìm kiếm So sánh** chuyên dụng. Tích hợp tính năng **Xác nhận thông minh**: Khi so sánh sản phẩm khác loại, hệ thống sẽ hiển thị hộp thoại hỏi ý kiến người dùng có muốn xóa danh sách cũ để bắt đầu so sánh loại mới không (thay vì chỉ hiện thông báo chặn), giúp tăng tốc quá trình mua sắm.
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

### [2026-05-16] - Security & Audit Refinement
- **SQL Injection Fixes:** Refactored `AdminService.php` to use prepared statements for all administrative queries (orders, products, login history, chart data).
- **Password Consistency:** Unified minimum password length to 8 characters and required at least one letter across the system (`UserService.php` & Admin User Management).
- **Typo & UX Fixes:** Fixed "NHân Viên" typo in `header.php`. Verified CSRF protection handles JSON and header-based tokens for AJAX.
- **Audit:** Conducted deep audit of `product_detail.php` gallery logic, comparison module, and admin CRUD. Verified image upload security with multi-file support.

### [2026-05-17] - Mobile Plan Phase 1 & 2 Execution
- **Giai đoạn 1: Quick Wins UI/UX (Đồng bộ Thông báo, Wishlist, Product Gallery)**
    - **Kích hoạt chuông thông báo Trang chủ:**
        - File đã sửa: [home_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/home/presentation/screens/home_screen.dart)
        - Kế thừa `notificationControllerProvider` để đếm số lượng tin chưa đọc. Gắn `Badge` hiển thị trực quan và liên kết chuyển hướng sang `/notifications`.
    - **Tích hợp nút Yêu thích (Wishlist) vào trang Chi tiết:**
        - File đã sửa: [product_detail_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/screens/product_detail_screen.dart)
        - Thêm nút Trái tim (IconButton) trên AppBar, bám sát trạng thái và gọi API `toggle` qua `wishlistControllerProvider`.
    - **Nâng cấp Slider Album ảnh (Product Gallery):**
        - File đã sửa: [catalog_product.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/catalog/data/models/catalog_product.dart) và [product_detail_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/screens/product_detail_screen.dart)
        - Cập nhật model `CatalogProduct` thêm `moreImages`, hỗ trợ parse JSON string lẫn array an toàn.
        - Nâng cấp `_ProductHero` thành StatefulWidget, hiển thị Slider ảnh chính mượt mà cùng danh sách các Thumbnail phụ để người dùng click lựa chọn tương tác.

- **Giai đoạn 2: Nâng cấp Bảo mật & Xác thực (2FA Gmail OTP & Quên mật khẩu)**
    - **Nâng cấp API 2FA ở Backend:**
        - File đã sửa: [auth.php](file:///d:/Sever/htdocs/PMPBDT/public/api/auth.php)
        - Action `login` được nâng cấp kiểm tra cờ `two_factor_enabled`. Nếu có bật 2FA, backend tự động sinh OTP, gửi email qua mail helper và trả về cờ `requires_2fa: true` cùng mã token ngắn hạn `pending_token` đã ký bằng JWT.
        - Action `two-factor-verify` được tối ưu hóa để xác thực OTP gửi lên cùng `pending_token`, giải quyết triệt để vấn đề phụ thuộc vào Session PHP trên Mobile App di động và đảm bảo chuẩn RESTful/JWT tuyệt đối.
    - **Đồng bộ Repo & Controller trên Flutter:**
        - File đã sửa: [auth_repository.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/data/repositories/auth_repository.dart) và [auth_controller.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/presentation/controllers/auth_controller.dart)
        - Thiết kế thêm lớp `LoginResult` để quản lý luồng 2FA. Tích hợp các hàm gọi API gửi OTP, verify 2FA, forgot password gửi OTP và reset password.
    - **Thiết kế UI & Logic Điều hướng Xác thực 2FA:**
        - File đã sửa: [two_factor_verify_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/presentation/screens/two_factor_verify_screen.dart), [login_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/presentation/screens/login_screen.dart), và [app_router.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/core/router/app_router.dart)
        - Xây dựng màn hình nhập OTP 6 chữ số kèm countdown 60 giây và nút hủy đăng nhập an toàn.
        - Định cấu hình định tuyến cho route `/two-factor-verify` và nâng cấp logic `redirect` bảo mật cao: Bắt buộc khóa chặt người dùng tại màn hình OTP nếu tài khoản đang kích hoạt 2FA chưa xác minh, tự động chuyển hướng về `/home` khi xác thực thành công.
    - **Thiết kế UI Quên mật khẩu (2 bước khôi phục):**
        - File đã sửa: [forgot_password_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/presentation/screens/forgot_password_screen.dart) và [login_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/presentation/screens/login_screen.dart)
        - Tạo màn hình quên mật khẩu quy trình 2 bước: Bước 1 nhập Email nhận mã OTP; Bước 2 nhập OTP và mật khẩu mới để phục hồi tài khoản di động. Liên kết kích hoạt thông qua nút "Quên mật khẩu?" tại màn hình Login.

- **Giai đoạn 3: Tích hợp Đột phá Trải nghiệm (Gemini AI RAG Chatbot, Gợi ý Tìm kiếm, So sánh Sản phẩm)**
    - **Gemini AI Chatbot RAG (Mobile & Backend API):**
        - File đã tạo: [chatbot.php](file:///d:/Sever/htdocs/PMPBDT/public/api/chatbot.php) tại Backend làm proxy an toàn kết nối Google Gemini API (model `gemini-2.5-flash` và fallback `gemini-2.5-flash-lite`), tích hợp cơ chế RAG tự động trích lọc từ khóa trong câu hỏi của khách hàng để truy vấn CSDL PHP MySQL (FullText/LIKE) chèn 10 sản phẩm liên quan làm ngữ cảnh bổ trợ.
        - File đã tạo/sửa: [chat_message.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/chatbot/data/models/chat_message.dart), [chatbot_repository.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/chatbot/data/repositories/chatbot_repository.dart), [chatbot_controller.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/chatbot/presentation/controllers/chatbot_controller.dart) và [chatbot_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/chatbot/presentation/screens/chatbot_screen.dart) phía di động.
        - **Parser HTML sang Link di động mượt mà:** `ChatbotScreen` được tích hợp bộ parser HTML thông minh, tự động parse các thẻ `<b>`, `<br>` và đặc biệt là thẻ `<a href="...">` chèn link từ Gemini API. Khi bấm vào sản phẩm trong chat bong bóng, app tự động điều hướng tới `/product/ID` trên di động; khi bấm vào danh mục/thương hiệu, app tự động mở `/catalog?keyword=...` cực kỳ ảo diệu!
        - **Context RAG Injection:** Tích hợp nút FAB mở AI Chat tại [home_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/home/presentation/screens/home_screen.dart) và nút FAB RAG Context-Aware mở AI chat tại [product_detail_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/screens/product_detail_screen.dart) (tự động đính kèm thông tin bối cảnh sản phẩm khách hàng đang xem để AI tư vấn chính xác).
        - File đã sửa: [mobile_page.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/core/widgets/mobile_page.dart) nâng cấp Scaffold hỗ trợ thuộc tính `floatingActionButton` dùng chung cho toàn app.
    - **Search Suggestions (Gợi ý tìm kiếm tức thời):**
        - File đã tạo: [search_suggestions_provider.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/catalog/presentation/controllers/search_suggestions_provider.dart) tự động gọi `/search.php?keyword=...` để lấy dữ liệu gợi ý danh mục và sản phẩm liên quan từ Backend.
        - File đã sửa: [home_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/home/presentation/screens/home_screen.dart) thay thế thanh tìm kiếm cũ thành `SearchAnchor` Material 3 hiện đại, bung mở giao diện tìm kiếm full-screen cùng suggestionsBuilder phân vùng "Danh mục gợi ý" & "Sản phẩm gợi ý" (có ảnh thu nhỏ, tên và giá bán định dạng VNĐ) vô cùng mượt mà và trực quan.
    - **So sánh sản phẩm (Product Comparison):**
        - File đã tạo/sửa: [compare_controller.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/controllers/compare_controller.dart), [compare_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/screens/compare_screen.dart) và [product_detail_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/screens/product_detail_screen.dart)
        - Triển khai `CompareController` quản lý danh sách tối đa 3 sản phẩm so sánh.
        - Xây dựng màn hình `CompareScreen` dạng bảng cuộn ngang premium: cột nhãn thông số được khóa cố định bên trái, các sản phẩm xếp cột thẳng hàng tăm tắp, so sánh đầy đủ thông tin: Ảnh, Tên, Giá bán, Giá cũ, Thương hiệu, Mô tả ngắn, và tích hợp nút "Mua ngay" (thêm giỏ hàng) + nút "Chi tiết".
        - Tích hợp nút so sánh sản phẩm (IconButton `compare_arrows`) vào AppBar [product_detail_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/screens/product_detail_screen.dart) cùng SnackBar hành động nhanh dẫn hướng đến trang so sánh `/compare` khi thêm thành công.
    - **Định tuyến toàn diện:**
        - File đã sửa: [app_router.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/core/router/app_router.dart) đăng ký các route `/chatbot` và `/compare` full-screen chuyên nghiệp.

- **Giai đoạn 4: Push Notification & Hoàn thiện hạ tầng (FCM Token Sync & Lịch sử xem sản phẩm)**
    - **Backend Migration & API FCM Token:**
        - File đã tạo: [migration_fcm_token.php](file:///d:/Sever/htdocs/PMPBDT/scratch/migration_fcm_token.php) chạy SQL migration thêm cột `fcm_token` vào bảng `users`.
        - File đã tạo: [fcm_token.php](file:///d:/Sever/htdocs/PMPBDT/public/api/fcm_token.php) hỗ trợ POST lưu FCM token từ điện thoại lên CSDL thông qua Bearer JWT    - **Sửa Lỗi Parser Dữ liệu Lỗi Chatbot (type 'String' is not a subtype of type 'int' of 'index'):**
        - File đã sửa: [chatbot_repository.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/chatbot/data/repositories/chatbot_repository.dart).
        - Nguyên nhân: Khi backend gặp lỗi HTTP (ví dụ: mất kết nối, lỗi 500, lỗi PHP Warning/Fatal chèn HTML báo lỗi của Apache) và ném ra `DioException`, Dio trả về `e.response?.data` dưới dạng một chuỗi `String`. Câu lệnh parse lỗi cũ cố gắng truy cập khóa String `e.response?.data?['message']` trên kiểu dữ liệu `String`, dẫn đến lỗi của Dart che lấp toàn bộ lỗi thực tế của server.
        - Giải pháp: Viết lại hàm `getAiResponse` xử lý parse dữ liệu động và xử lý `DioException` an toàn tuyệt đối. Nếu response hoặc error data là kiểu `String` thô, tự động bắt exception, decode nếu là JSON, cắt ngắn hoặc trích xuất trực tiếp chuỗi lỗi của server để hiển thị rõ ràng trên UI giúp người dùng tự khắc phục.
    - **Sửa Lỗi Chức Năng Quên Mật Khẩu và Bật Bảo Mật 2 Lớp (2FA) trên Mobile App:**
        - File đã tạo & thực thi: [migrate_otp.php](file:///d:/Sever/htdocs/PMPBDT/scratch/migrate_otp.php) (Bổ sung 4 trường `reset_password_otp`, `reset_password_otp_expires_at`, `two_factor_otp`, `two_factor_otp_expires_at` vào bảng `users`).
        - File đã tạo & test: [test_mail.php](file:///d:/Sever/htdocs/PMPBDT/scratch/test_mail.php) để debug kiểm tra kết nối SMTP Gmail.
        - File đã sửa: [UserService.php](file:///d:/Sever/htdocs/PMPBDT/src/Service/UserService.php) và [auth.php](file:///d:/Sever/htdocs/PMPBDT/public/api/auth.php).
        - Nguyên nhân: Mobile App giao tiếp qua API không duy trì Session Cookie (`PHPSESSID`) tự động như trình duyệt Web. Logic cũ lưu mã OTP quên mật khẩu và OTP bật 2FA vào `$_SESSION` dẫn đến khi Mobile gửi request reset mật khẩu hoặc xác minh 2FA, session bị trống trơn (null). Đồng thời file `auth.php` thiếu từ khóa `break;` và **thiếu nạp file `.env`** khiến toàn bộ cấu hình SMTP Gmail bị trống rỗng khi gửi API trực tiếp từ di động.
        - Giải pháp: Chuyển toàn bộ cơ chế lưu và xác thực mã OTP quên mật khẩu cũng như bật 2FA từ `$_SESSION` sang lưu trữ trực tiếp vào các trường CSDL mới trong bảng `users` có thiết lập thời gian hết hạn (10 phút). **Nạp biến môi trường bằng `\App\Support\Env::load` ở đầu file `public/api/auth.php`** để các API gửi OTP nhận diện đúng SMTP Gmail. Sửa chữa thêm `break;` cho cấu trúc switch-case của `auth.php`. Kết quả test kiểm tra kết nối SMTP Gmail gửi thành công rực rỡ!

### Việc cần làm tiếp theo (TODO)fications/presentation/controllers/notification_controller.dart) bổ sung phương thức `updateFcmToken` và `syncFcmToken` hỗ trợ kết nối API.
        - File đã sửa: [app.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/app.dart) chuyển class `PmpbdtApp` sang ConsumerStatefulWidget gọi khởi tạo service một lần duy nhất trong `initState`.
    - **Lưu trữ & Hiển thị Sản phẩm vừa xem (Recently Viewed):**
        - File đã tạo: [recently_viewed_controller.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/controllers/recently_viewed_controller.dart) quản lý danh sách tối đa 10 sản phẩm đã xem bằng SharedPreferences (hỗ trợ lọc trùng, đẩy lên đầu, và xóa lịch sử).
        - File đã sửa: [product_detail_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/product/presentation/screens/product_detail_screen.dart) tích hợp `ref.listen` thông minh tại hàm `build` ghi nhận sản phẩm ngay khi tải dữ liệu thành công.
        - File đã sửa: [home_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/home/presentation/screens/home_screen.dart) hiển thị widget `_RecentlyViewedRow` dạng slide ngang premium ở cuối trang chủ cùng nút "Xóa lịch sử" tiện ích.
    - **Cải tiến Luồng Hiển thị Lỗi Authentication (Đăng nhập, 2FA, Quên mật khẩu):**
        - File đã sửa: [login_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/presentation/screens/login_screen.dart), [two_factor_verify_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/presentation/screens/two_factor_verify_screen.dart), và [forgot_password_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/auth/presentation/screens/forgot_password_screen.dart).
        - Loại bỏ hoàn toàn các SnackBar và component AppErrorView khổng lồ làm méo mó và bung lệch giao diện đăng nhập cũ.
        - Thay thế bằng Banner Error nhỏ nhắn viền mỏng đỏ pastel (errorContainer & onErrorContainer) vô cùng tinh tế, đặt gọn gàng phía trên nút bấm chính của Form giúp nâng cao trải nghiệm premium.
    - **Sửa Lỗi Layout Làm Trắng Tinh Giao Diện (Sản phẩm yêu thích, Thông báo, Chatbot):**
        - File đã sửa: [wishlist_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/wishlist/presentation/screens/wishlist_screen.dart), [notification_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/notifications/presentation/screens/notification_screen.dart), và [chatbot_screen.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/chatbot/presentation/screens/chatbot_screen.dart).
        - Nguyên nhân: `MobilePage` mặc định thiết lập `scrollable: true` (bọc child bên trong một `ListView`). Khi các trang con truyền vào một `ListView`, `GridView` hay một `Column` chứa `Expanded`, Flutter bị lỗi "Unbounded height" hoặc "RenderFlex non-zero flex" khiến giao diện bị crash và trắng tinh hoàn toàn trên máy ảo/thiết bị thực tế.
        - Giải pháp: Khai báo `scrollable: false` cho `MobilePage` tại cả 3 trang trên, giúp chuyển sang cơ chế Layout không cố định chiều dọc dưới nền, giúp các scrollable/flex widget bên trong tự định vị kích thước và hoạt động cuộn hoàn hảo. Dọn dẹp RefreshIndicator thừa tại WishlistScreen.
    - **Sửa Lỗi Parser Dữ liệu Lỗi Chatbot (type 'String' is not a subtype of type 'int' of 'index'):**
        - File đã sửa: [chatbot_repository.dart](file:///d:/Sever/htdocs/PMPBDT/mobile_app/lib/features/chatbot/data/repositories/chatbot_repository.dart).
        - Nguyên nhân: Khi backend gặp lỗi HTTP (ví dụ: mất kết nối, lỗi 500, lỗi PHP Warning/Fatal chèn HTML báo lỗi của Apache) và ném ra `DioException`, Dio trả về `e.response?.data` dưới dạng một chuỗi `String`. Câu lệnh parse lỗi cũ cố gắng truy cập khóa String `e.response?.data?['message']` trên kiểu dữ liệu `String`, dẫn đến lỗi của Dart che lấp toàn bộ lỗi thực tế của server.
        - Giải pháp: Viết lại hàm `getAiResponse` xử lý parse dữ liệu động và xử lý `DioException` an toàn tuyệt đối. Nếu response hoặc error data là kiểu `String` thô, tự động bắt exception, decode nếu là JSON, cắt ngắn hoặc trích xuất trực tiếp chuỗi lỗi của server để hiển thị rõ ràng trên UI giúp người dùng tự khắc phục.

### Việc cần làm tiếp theo (TODO)
1. **Hoàn thiện API Catalog & Checkout:** Đồng bộ hóa cho Mobile App.
2. **Cải thiện Catalog Filter:** Thêm lọc theo đánh giá sao (vừa mới có dữ liệu reviews).
3. **Đăng nhập bên thứ ba (Google OAuth):** Cấu hình client IDs và keystore SHA-1 để tích hợp thêm nút đăng nhập Google trên App di động.
4. **Modal Đăng ký trả góp (Installment Requests):** Xây dựng bottom sheet đăng ký trả góp tại di động liên kết với API backend.
