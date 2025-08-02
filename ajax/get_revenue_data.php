<?php
require_once '../includes/config.php'; // $mongoDB có ở đây
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json');

$type  = $_GET['type']  ?? 'month';
$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;

try {
    // ✅ Chỉ lấy đơn hàng đã hoàn thành (không bị hủy)
    $match = [
        'status' => ['$ne' => 'cancelled']
    ];
    
    $format = '';
    $now = new DateTime();
    $from = null;
    $to = new DateTime(); // now

    switch ($type) {
        case 'week':  // ✅ Khớp với JS: 'week'
            $from = (new DateTime())->modify('-6 days');
            $format = '%Y-%m-%d';
            break;

        case 'year':  // ✅ Khớp với JS: 'year' 
            $from = (new DateTime())->modify('-11 months');
            $format = '%Y-%m';
            break;

        case 'custom': // ✅ Khớp với JS: 'custom'
            if (!$start || !$end) {
                throw new Exception("Missing start or end date for custom range");
            }

            $from = new DateTime($start);
            $to   = new DateTime($end . ' 23:59:59');
            
            // Tự động chọn format dựa trên khoảng thời gian
            $diff = $from->diff($to)->days;
            $format = ($diff > 90) ? '%Y-%m' : '%Y-%m-%d';
            break;

        default: // 'month' - ✅ Khớp với JS: 'month'
            $from = (new DateTime())->modify('-29 days'); // 30 ngày gần đây
            $format = '%Y-%m-%d';
            break;
    }

    // ✅ Thêm điều kiện thời gian vào match
    if ($from && $to) {
        $match['created_date'] = [
            '$gte' => new UTCDateTime($from->getTimestamp() * 1000),
            '$lte' => new UTCDateTime($to->getTimestamp() * 1000),
        ];
    }

    $pipeline = [
        ['$match' => $match],
        ['$group' => [
            '_id' => [
                '$dateToString' => [
                    'format' => $format,
                    'date' => '$created_date'
                ]
            ],
            'total' => ['$sum' => '$final_price']
        ]],
        ['$sort' => ['_id' => 1]]
    ];

    $cursor = $mongoDB->orders->aggregate($pipeline);
    $data = [];

    // ✅ Xử lý kết quả và đảm bảo có đủ điểm dữ liệu
    $results = [];
    foreach ($cursor as $row) {
        $results[$row['_id']] = $row['total'];
    }

    // ✅ Tạo dữ liệu đầy đủ cho biểu đồ (điền 0 cho ngày không có dữ liệu)
    if ($type === 'week') {
        for ($i = 6; $i >= 0; $i--) {
            $date = (new DateTime())->modify("-$i days")->format('Y-m-d');
            $data[] = [
                'label' => $date,
                'total' => $results[$date] ?? 0
            ];
        }
    } elseif ($type === 'month') {
        for ($i = 29; $i >= 0; $i--) {
            $date = (new DateTime())->modify("-$i days")->format('Y-m-d');
            $data[] = [
                'label' => $date,
                'total' => $results[$date] ?? 0
            ];
        }
    } elseif ($type === 'year') {
        for ($i = 11; $i >= 0; $i--) {
            $date = (new DateTime())->modify("-$i months")->format('Y-m');
            $data[] = [
                'label' => $date . '-01', // Thêm -01 để JS có thể parse date
                'total' => $results[$date] ?? 0
            ];
        }
    } else {
        // Custom range - sử dụng kết quả trực tiếp
        foreach ($results as $label => $total) {
            $data[] = [
                'label' => $label,
                'total' => $total
            ];
        }
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>