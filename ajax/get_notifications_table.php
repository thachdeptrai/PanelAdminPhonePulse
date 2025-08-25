<?php
// get_notifications_table.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Lấy params lọc từ GET request
    $type = $_GET['type'] ?? null;       // broadcast / personalized
    $status = $_GET['status'] ?? null;   // pending / sent / failed
    $userId = $_GET['userId'] ?? null;   // lọc theo user
    $dateFrom = $_GET['dateFrom'] ?? null; // yyyy-mm-dd
    $dateTo = $_GET['dateTo'] ?? null;

    // Build query payload
    $query = [];
    if ($type) $query['type'] = $type;
    if ($status) $query['status'] = $status;
    if ($userId) $query['userId'] = $userId;
    if ($dateFrom) $query['dateFrom'] = $dateFrom;
    if ($dateTo) $query['dateTo'] = $dateTo;

    // Chuyển query thành query string
    $qs = http_build_query($query);

    // Gọi API Node.js
    $url = 'http://localhost:5000/api/notifications/log';
    if (!empty($qs)) $url .= '?' . $qs;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Curl error: ' . $err]);
        exit;
    }

    $data = json_decode($response, true);
    if (!$data) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Invalid API response']);
        exit;
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
