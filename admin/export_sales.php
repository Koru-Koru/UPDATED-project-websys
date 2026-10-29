<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
function export_date(string $name, string $default): string {
    $value = $_GET[$name] ?? $default;
    if (!is_string($value)) { http_response_code(400); exit('Invalid report date.'); }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) { http_response_code(400); exit('Invalid report date.'); }
    return $value;
}
$start = export_date('start_date', date('Y-m-01'));
$end = export_date('end_date', date('Y-m-d'));
if ($start > $end) { http_response_code(400); exit('Start date must be before the end date.'); }
$stmt = $conn->prepare("SELECT order_id, created_at, status, payment_method, subtotal, discount, total, pickup_at
 FROM orders WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) AND status <> 'Cancelled'
 ORDER BY created_at, order_id");
$stmt->bind_param('ss', $start, $end); $stmt->execute();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sales-' . $start . '-to-' . $end . '.csv"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Order ID', 'Date', 'Status', 'Payment method', 'Subtotal (PHP)', 'Discount (PHP)', 'Total (PHP)', 'Pickup time']);
foreach ($stmt->get_result() as $row) fputcsv($out, array_values($row));
fclose($out);
