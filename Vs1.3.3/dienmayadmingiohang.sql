-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th3 30, 2026 lúc 06:49 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `dienmay`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `brands`
--

INSERT INTO `brands` (`id`, `name`) VALUES
(1, 'Samsung'),
(2, 'Sony'),
(3, 'Panasonic'),
(4, 'Aqua'),
(5, 'Daikin');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `cart_items`
--

INSERT INTO `cart_items` (`cart_id`, `user_id`, `product_id`, `quantity`) VALUES
(1, 1, 6, 1),
(2, 1, 5, 6),
(3, 1, 4, 1),
(4, 1, 7, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`id`, `name`, `icon`) VALUES
(1, 'Máy lạnh', 'fa-snowflake'),
(2, 'Tủ lạnh', 'fa-cube'),
(3, 'Tivi', 'fa-tv'),
(4, 'Máy giặt', 'fa-soap');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `installment_requests`
--

CREATE TABLE `installment_requests` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `installment_term` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `note` text DEFAULT NULL,
  `total_price` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_details`
--

CREATE TABLE `order_details` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` int(11) NOT NULL,
  `old_price` int(11) DEFAULT NULL,
  `image` varchar(500) NOT NULL,
  `rate_star` float DEFAULT 0,
  `total_reviews` int(11) DEFAULT 0,
  `gift_text` varchar(255) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `specifications` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `products`
--

INSERT INTO `products` (`id`, `category_id`, `brand_id`, `name`, `price`, `old_price`, `image`, `rate_star`, `total_reviews`, `gift_text`, `tags`, `description`, `specifications`) VALUES
(1, 1, 5, 'Máy lạnh Daikin Inverter 1 HP ATKF25XVMV', 9990000, 11500000, 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?auto=format&fit=crop&q=80&w=400', 4.8, 125, 'Miễn phí vật tư & Lắp đặt', 'Bán chạy', '<p>Máy lạnh Daikin Inverter 1 HP với thiết kế sang trọng, tích hợp công nghệ làm lạnh nhanh và màng lọc diệt khuẩn hiệu quả. Phù hợp cho phòng ngủ dưới 15m2.</p>', '<ul><li>Loại máy: Inverter 1 chiều</li><li>Công suất: 1 HP - 9.000 BTU</li><li>Phạm vi: Dưới 15m2</li><li>Nơi lắp ráp: Việt Nam</li><li>Bảo hành: 12 tháng</li></ul>'),
(2, 1, 3, 'Máy lạnh Panasonic Inverter 1.5 HP CU/CS-PU12', 12490000, 13900000, 'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?auto=format&fit=crop&q=80&w=400', 4.9, 342, 'Tặng phiếu mua hàng 500k', 'Mới 2024', '<p>Máy lạnh Panasonic được trang bị công nghệ Nanoe-G lọc sạch bụi mịn PM2.5, bảo vệ sức khỏe gia đình bạn.</p>', '<ul><li>Loại máy: Inverter 1 chiều</li><li>Công suất: 1.5 HP - 12.000 BTU</li><li>Phạm vi: 15 - 20m2</li><li>Nơi lắp ráp: Malaysia</li><li>Bảo hành: 12 tháng</li></ul>'),
(3, 2, 1, 'Tủ lạnh Samsung Inverter 305 lít RT31CG5424S9SV', 8490000, 9900000, 'https://images.unsplash.com/photo-1584568694244-14fbdf83bd30?auto=format&fit=crop&q=80&w=400', 4.5, 89, 'Trả góp 0%', 'Chỉ bán Online', '<p>Tủ lạnh 2 cửa ngăn đá trên, thiết kế phẳng hiện đại. Công nghệ SpaceMax tối ưu không gian lưu trữ thực phẩm.</p>', '<ul><li>Kiểu tủ: Ngăn đá trên</li><li>Dung tích: 305 lít</li><li>Công nghệ: Digital Inverter</li><li>Kích thước: 171.5 x 60 x 67 cm</li><li>Bảo hành: 24 tháng</li></ul>'),
(4, 2, 4, 'Tủ lạnh Aqua Inverter 189 lít AQR-T219FA(PB)', 4990000, 5500000, 'https://images.unsplash.com/photo-1571175443880-49e1d25b2bc5?auto=format&fit=crop&q=80&w=400', 4.2, 56, 'Tặng lốc 6 lon bia', '', '<p>Tủ lạnh nhỏ gọn phù hợp sinh viên hoặc gia đình 2-3 người. Khử mùi Nano Fresh Ag+ mạnh mẽ.</p>', '<ul><li>Kiểu tủ: Ngăn đá trên</li><li>Dung tích: 189 lít</li><li>Công nghệ: Twin Inverter</li><li>Nơi lắp ráp: Việt Nam</li><li>Bảo hành: 24 tháng</li></ul>'),
(5, 3, 2, 'Android Tivi Sony 4K 65 inch KD-65X75K', 16990000, 19000000, 'https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&q=80&w=400', 5, 512, 'Tặng loa Soundbar', 'Giảm sốc', '<p>Tivi Sony 65 inch độ phân giải 4K sắc nét. Hệ điều hành Google TV dễ sử dụng, kho ứng dụng giải trí phong phú.</p>', '<ul><li>Kích thước màn hình: 65 inch</li><li>Độ phân giải: 4K Ultra HD</li><li>Hệ điều hành: Google TV</li><li>Công nghệ hình ảnh: X1 4K HDR Processor</li><li>Âm thanh: Dolby Audio</li></ul>'),
(6, 4, 3, 'Máy giặt Panasonic Inverter 9.5 Kg NA-V95FC1LVT', 10490000, 11900000, 'https://images.unsplash.com/photo-1610557892470-55d9e80c0bce?auto=format&fit=crop&q=80&w=400', 4.7, 210, 'Tặng nước giặt Omo 3.6kg', '', '<p>Máy giặt lồng ngang với chế độ sấy tiện lợi. Đánh bay vết bẩn cứng đầu với giặt nước nóng StainMaster+.</p>', '<ul><li>Loại máy: Lồng ngang</li><li>Khối lượng giặt: 9.5 Kg / Sấy 2 Kg</li><li>Động cơ: 3D Active Wash</li><li>Tốc độ quay: 1400 vòng/phút</li><li>Bảo hành động cơ: 12 năm</li></ul>'),
(7, 3, 2, 'Tivy V2', 100000000, NULL, 'uploads/1774262249_Screenshot 2026-03-23 173423.png', 0, 0, '1 Bộ Loa', 'Trả góp 0%', '<p>Tivi Si&ecirc;u Đẹp Sắc N&eacute;t<br>Si&ecirc;u Mỏng&nbsp;</p>', '<p>1.1 mm<br>Nặng 5.7kg<br>HDMI 3</p>');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `phone`, `username`, `password`, `fullname`, `role`) VALUES
(1, '0901234567', 'admin', 'admin123', 'Quản Trị Viên', 'admin'),
(2, '0987654321', 'khachhang', 'user123', 'Nguyễn Văn Khách', 'customer');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_id`);

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `installment_requests`
--
ALTER TABLE `installment_requests`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `installment_requests`
--
ALTER TABLE `installment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `order_details`
--
ALTER TABLE `order_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
