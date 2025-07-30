<?php
require_once '../includes/config.php'; // $mongoDB có ở đây
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json');

$type  = $_GET['type']  ?? 'month';
$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;

try {
    $match = ['shipping_status' => 'shipped'];
    $group = [];
    $format = '';

    $now = new DateTime();
    $from = null;
    $to = new DateTime(); // now

    switch ($type) {
        case '7days':
            $from = (new DateTime())->modify('-6 days');
            $format = '%Y-%m-%d';
            break;

        case '3months':
            $from = (new DateTime())->modify('-3 months');
            $format = '%Y-%m';
            break;

        case 'year':
            $from = (new DateTime($now->format('Y') . '-01-01'));
            $format = '%Y-%m';
            break;

        case 'custom':
            if (!$start || !$end) {
                throw new Exception("Missing start or end date for custom range");
            }

            $from = new DateTime($start);
            $to   = new DateTime($end . ' 23:59:59');
            $format = '%Y-%m-%d';
            break;

        default: // month
            $from = new DateTime($now->format('Y-m-01'));
            $format = '%Y-%m-%d';
            break;
    }

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

    foreach ($cursor as $row) {
        $data[] = [
            'label' => $row['_id'],
            'total' => $row['total']
        ];
    }

    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
