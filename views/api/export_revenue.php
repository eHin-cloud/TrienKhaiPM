<?php
/**
 * ============================================================
 * EXPORT REVENUE TO CSV (EXCEL COMPATIBLE)
 * ============================================================
 */

// Chặn truy cập nếu không phải Admin/Manager
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    die("Unauthorized");
}

// Lấy dữ liệu doanh thu
$stmt = $db->query("
    SELECT id, fullname, total_price, payment_method, completed_at 
    FROM orders 
    WHERE status = 'completed' 
    ORDER BY completed_at DESC
");
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Header cho việc download file
$filename = "bao_cao_doanh_thu_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Mở output stream
$output = fopen('php://output', 'w');

// Thêm BOM cho UTF-8 để Excel hiển thị đúng tiếng Việt
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Tiêu đề cột
fputcsv($output, ['Mã đơn hàng', 'Khách hàng', 'Phương thức thanh toán', 'Thời gian hoàn thành', 'Doanh thu (VNĐ)']);

// Dữ liệu
foreach ($transactions as $tx) {
    fputcsv($output, [
        'ORD-' . $tx['id'],
        $tx['fullname'],
        strtoupper($tx['payment_method']),
        date('d/m/Y H:i', strtotime($tx['completed_at'])),
        $tx['total_price']
    ]);
}

fclose($output);
exit;
