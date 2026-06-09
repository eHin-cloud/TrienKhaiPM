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

$conditions = ["status = 'completed'"];
$params = [];

$month = isset($_GET['month']) && (int)$_GET['month'] >= 1 && (int)$_GET['month'] <= 12 ? (int)$_GET['month'] : null;
$startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = !empty($_GET['end_date']) ? $_GET['end_date'] : null;

if ($month) {
    $conditions[] = "MONTH(completed_at) = ? AND YEAR(completed_at) = YEAR(NOW())";
    $params[] = $month;
} elseif ($startDate && $endDate) {
    $conditions[] = "DATE(completed_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$whereClause = "WHERE " . implode(" AND ", $conditions);

// Lấy dữ liệu doanh thu
$stmt = $db->prepare("
    SELECT id, fullname, total_price, payment_method, completed_at 
    FROM orders 
    $whereClause
    ORDER BY completed_at DESC
");
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xác định tên file báo cáo theo bộ lọc
$filterSuffix = "";
if ($month) {
    $filterSuffix = "_thang_" . $month;
} elseif ($startDate && $endDate) {
    $filterSuffix = "_tu_" . date('Ymd', strtotime($startDate)) . "_den_" . date('Ymd', strtotime($endDate));
} else {
    $filterSuffix = "_tat_ca";
}

$filename = "bao_cao_doanh_thu" . $filterSuffix . "_" . date('Ymd_His') . ".csv";
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
