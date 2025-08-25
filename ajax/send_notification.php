<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Check nếu là resend
if (!empty($input['resend']) && !empty($input['notification_id'])) {
    $notif_id = $input['notification_id'];
    try {
        $notification = $mongoDB->Notification->findOne(['_id' => new ObjectId($notif_id)]);
        if (!$notification) {
            throw new Exception("Không tìm thấy thông báo");
        }

        // Reset trạng thái để gửi lại
        $mongoDB->Notification->updateOne(
            ['_id' => new ObjectId($notif_id)],
            ['$set' => ['status' => 'pending']]
        );

        // Gọi Node.js server với dữ liệu của thông báo cũ
        $payload = [
            'title' => $notification['title'],
            'body' => $notification['body'],
            'type' => $notification['type'],
            'userIds' => $notification['userIds'] ?? []
        ];

        $ch = curl_init('http://localhost:5000/api/notifications/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        curl_close($ch);

        echo json_encode(['success' => true, 'message' => 'Đã gửi lại thông báo']);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Trường hợp gửi mới
$title = $input['title'] ?? '';
$body = $input['body'] ?? '';
$type = $input['type'] ?? 'broadcast';
$userIds = $input['userIds'] ?? [];

if (!$title || !$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Title and body required']);
    exit;
}

// Gọi Node.js server
$ch = curl_init('http://localhost:5000/api/notifications/send');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'title' => $title,
    'body' => $body,
    'type' => $type,
    'userIds' => $userIds
]));
$response = curl_exec($ch);
$result = json_decode($response, true) ?? [];
echo json_encode($result);

if (!$result) {
    $result = ['message' => $response];
}

// Nếu có message từ Node, gửi về client luôn
$output = [
    'success' => false,            // có thể false nếu muốn JS vẫn hiểu
    'message' => $result['message'] ?? 'Không có phản hồi từ server'
];

// Lưu Mongo record nếu muốn
try {
    $notificationData = [
        'title' => $title,
        'body' => $body,
        'type' => $type,
        'userIds' => array_map(fn($uid)=>new ObjectId($uid), $userIds),
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
        'status' => $result['success'] ? 'sent' : 'failed',
    ];
} catch (Exception $e) {
    $result['mongo_error'] = $e->getMessage();
}

echo json_encode($result);
?>
