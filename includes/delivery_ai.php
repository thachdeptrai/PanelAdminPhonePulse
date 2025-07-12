<?php
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

function estimateShippingDaysAI($address) {
    $apiKey = $_ENV['OPENAI_API_KEY'];

    $client = new Client([
        'base_uri' => 'https://api.openai.com/v1/',
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json'
        ]
    ]);

    $prompt = "Bạn là một AI chuyên giao hàng tại Việt Nam. "
            . "Hãy dự đoán số ngày (số nguyên) cần thiết để giao hàng từ Hà Nội đến địa chỉ sau: \"$address\". "
            . "Hãy cân nhắc khoảng cách, tình trạng giao thông, thời tiết và các yếu tố khác có thể ảnh hưởng đến thời gian giao hàng. "
            . "Chỉ trả về duy nhất 1 số nguyên."
            ;

    try {
        $response = $client->post('chat/completions', [
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Bạn là một AI logistics tại Việt Nam.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'max_tokens' => 10
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        $text = trim($data['choices'][0]['message']['content']);
        preg_match('/\d+/', $text, $match);
        return isset($match[0]) ? (int)$match[0] : 3;

    } catch (Exception $e) {
        error_log("AI Delivery Error: " . $e->getMessage());
        return 3;
    }
}
