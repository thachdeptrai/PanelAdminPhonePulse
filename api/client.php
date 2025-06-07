<?php
// api/client.php
class MongoAPIClient
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = MONGO_API_BASE_URL;
        // $this->apiKey  = API_KEY;
    }

    // Hàm gửi request chung
    private function makeRequest($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        // echo('Request URL: ' . $url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Set headers
        $headers = [
            'Content-Type: application/json',
        ];

        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set method và data
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    $jsonData = json_encode($data);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        curl_close($ch);
                        return [
                            'success' => false,
                            'error'   => 'JSON encode error: ' . json_last_error_msg(),
                        ];
                    }
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                }
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    $jsonData = json_encode($data);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        curl_close($ch);
                        return [
                            'success' => false,
                            'error'   => 'JSON encode error: ' . json_last_error_msg(),
                        ];
                    }
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                }
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // echo "HTTP Status Code: $httpCode\n";
        // echo "Response body: $response\n";

        if (curl_error($ch)) {
            curl_close($ch);
            return ['success' => false, 'error' => curl_error($ch)];
        }

        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success'   => $httpCode >= 200 && $httpCode < 300,
            'data'      => $result,
            'http_code' => $httpCode,
        ];
    }

    // Lấy danh sách users
    public function getUsers($page = 1, $limit = 10)
    {
        return $this->makeRequest("users?page={$page}&limit={$limit}");
    }

    // Lấy user theo ID
    public function getUser($id)
    {
        return $this->makeRequest("users/{$id}");
    }

    // Tạo user mới
    public function createUser($userData)
    {
        return $this->makeRequest('users', 'POST', $userData);
    }

    // Cập nhật user
    public function updateUser($id, $userData)
    {
        return $this->makeRequest("users/{$id}", 'PUT', $userData);
    }

    // Xóa user
    public function deleteUser($id)
    {
        return $this->makeRequest("users/{$id}", 'DELETE');
    }

    // Lấy thống kê dashboard
    public function getDashboardStats()
    {
        return $this->makeRequest('dashboard/stats');
    }
    public function adminLogin($email, $password)
    {
        $data = [
            "email"    => $email,
            "password" => $password,
        ];

        // Log chuỗi JSON
        // echo("Sending login data: " . json_encode($data));

        return $this->makeRequest('/users/login', 'POST', $data);
    }
}
