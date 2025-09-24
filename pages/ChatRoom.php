<?php
include '../includes/config.php';
include '../includes/functions.php';
use MongoDB\BSON\ObjectId;
$user_id_raw = $_SESSION['user_id'] ?? null;
try {
    $user_id = new ObjectId($user_id_raw);
} catch (Exception $e) {
    die("ID phiên không hợp lệ");
}
$user = $mongoDB->users->findOne(['_id' => $user_id]);
// Lấy danh sách tất cả user và admin (hoặc chỉ user liên quan)
$users = iterator_to_array($mongo->users->find([], ['projection' => ['_id' => 1, 'name' => 1]]));
$userMap = [];
foreach ($users as $u) {
    $userMap[(string)$u['_id']] = $u['name'];
}

// ===== Preload rooms directly from MongoDB (no API) =====
try {
    $waitingCursor = $mongoDB->chatrooms->find(['status' => 'waiting'], ['sort' => ['updatedAt' => -1, 'createdAt' => -1]]);
    $activeCursor  = $mongoDB->chatrooms->find(['status' => 'active', 'adminId' => (string)$user_id], ['sort' => ['updatedAt' => -1, 'createdAt' => -1]]);
    $closedCursor  = $mongoDB->chatrooms->find(['status' => 'closed'], ['sort' => ['updatedAt' => -1, 'createdAt' => -1], 'limit' => 20]);
    $preloadedWaitingRooms = iterator_to_array($waitingCursor);
    $preloadedActiveRooms  = iterator_to_array($activeCursor);
    $preloadedClosedRooms  = iterator_to_array($closedCursor);
} catch (Exception $e) {
    $preloadedWaitingRooms = [];
    $preloadedActiveRooms = [];
    $preloadedClosedRooms = [];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat Support</title>
    
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
        }

        .container {
            display: flex;
            height: 100vh;
            backdrop-filter: blur(10px);
        }

        .sidebar {
            width: 320px;
            background: rgba(255, 255, 255, 0.95);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }

        .sidebar-header {
            padding: 24px;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .sidebar-header h3 {
            margin-bottom: 8px;
            font-size: 20px;
            font-weight: 600;
        }

        .sidebar-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .room-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px 0;
        }

        .room-list::-webkit-scrollbar {
            width: 6px;
        }

        .room-list::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.1);
        }

        .room-list::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.3);
            border-radius: 3px;
        }

        .room-section {
            margin-bottom: 16px;
        }

        .section-title {
            padding: 8px 20px;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(0,0,0,0.05);
        }

        .room-item {
            padding: 16px 20px;
            margin: 4px 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .room-item:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .room-item.active {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 4px solid #2196f3;
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
        }

        .room-item.waiting {
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
            border-left: 4px solid #ff9800;
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .room-user {
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 14px;
            color: #2c3e50;
        }

        .room-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }

        .room-time {
            font-size: 11px;
            opacity: 0.8;
        }

        .room-id {
            font-family: monospace;
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.95);
            margin: 12px 12px 12px 0;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            overflow: hidden;
        }

        .chat-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border-bottom: 1px solid rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: linear-gradient(to bottom, rgba(248,249,250,0.5), rgba(255,255,255,0.8));
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.1);
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.3);
            border-radius: 3px;
        }

        .message {
            margin-bottom: 16px;
            display: flex;
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.admin {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 70%;
            padding: 14px 18px;
            border-radius: 20px;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border: 1px solid rgba(0,0,0,0.1);
        }

        .message.admin .message-content {
            background: linear-gradient(135deg, #2196f3, #1976d2);
            color: white;
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 6px;
        }

        .chat-input {
            padding: 20px 24px;
            background: rgba(255, 255, 255, 0.9);
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        .input-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .message-input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 25px;
            outline: none;
            font-size: 14px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .message-input:focus {
            border-color: #2196f3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
            transform: scale(1.02);
        }

        .send-btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, #2196f3, #1976d2);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(33, 150, 243, 0.4);
        }

        .send-btn:active {
            transform: translateY(0);
        }

        .typing-indicator {
            padding: 12px 24px;
            font-style: italic;
            color: #666;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.8);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: #666;
            text-align: center;
        }

        .empty-state h3 {
            margin-bottom: 12px;
            font-size: 24px;
            color: #2c3e50;
        }

        .empty-state p {
            font-size: 16px;
            opacity: 0.8;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-success {
            background: linear-gradient(135deg, #4caf50, #388e3c);
            color: white;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-waiting {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-active {
            background: linear-gradient(135deg, #d4edda, #00b894);
            color: #155724;
            border: 1px solid #00b894;
        }

        .room-stats {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .stat-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 8px;
            background: rgba(0,0,0,0.1);
            color: #666;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .back-arrow-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: #f3f4f6;
        border: 2px solid #d1d5db;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 16px;
        color: #111827;
        }

        .back-arrow-btn:hover {
        background: #e5e7eb;
        transform: translateX(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .back-arrow-btn .arrow {
        position: relative;
        display: inline-block;
        width: 10px;
        height: 10px;
        border-left: 3px solid #111827;
        border-bottom: 3px solid #111827;
        transform: rotate(45deg);
        margin-right: 4px;
        }

        .back-arrow-btn .label {
        user-select: none;
        }
        .site-name {
            display: flex;
            align-items: center;
            font-size: 1.8rem; /* Lớn hơn h3 (1.5rem mặc định của Bootstrap) */
            font-weight: 690;
            color: #ffffff;
            background: linear-gradient(45deg,rgb(224, 226, 228)23, 38),rgb(211, 215, 216));
            padding: 12px 15px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        .site-name i {
            margin-right: 10px;
            font-size: 1.4rem;
        }
        .room-item.has-unread {
        box-shadow: 0 0 12px rgba(255,107,129,0.6);
        }
        .room-item {
        transition: all .25s ease;
        border-radius: 12px;
        padding: 10px;
        margin-bottom: 8px;
        }
        .seen-label {
        background: rgba(255,255,255,0.07);
        padding: 2px 6px;
        border-radius: 10px;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
            <a href="trang_chu" class="site-name">
            <i class="fas fa-home"></i> <?= htmlspecialchars($settings['site_name']) ?>
             </a>
                <h3>Chat Support</h3>
                <p>Admin: <?php echo htmlspecialchars($user['name']) ?></p>
            </div>
            
            <div class="room-list" id="roomList">
                <div class="room-section">
                    <div class="section-title">⏳ Chờ hỗ trợ</div>
                    <div id="waitingRooms"></div>
                </div>
                
                <div class="room-section">
                    <div class="section-title">💬 Đang chat</div>
                    <div id="activeRooms"></div>
                </div>

                <div class="room-section">
                    <div class="section-title">🗄️ Đã đóng</div>
                    <div id="closedRooms"></div>
                </div>
            </div>
            <button class="back-arrow-btn" onclick="goBack()">
            <span class="arrow"></span>
            <span class="label">Quay lại</span>
            </button>
        </div>

        <div class="chat-area">
            <div id="emptyChatState" class="empty-state">
                <h3>💬 Chọn cuộc trò chuyện</h3>
                <p>Chọn một phòng chat từ danh sách bên trái để bắt đầu hỗ trợ khách hàng</p>
            </div>

            <div id="chatInterface" style="display: none; height: 100%; flex-direction: column;">
                <div class="chat-header">
                    <div>
                        <h4 id="currentRoomUser">User</h4>
                        <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                            <span class="status-badge status-active" id="roomStatus">Active</span>
                            <span class="stat-badge" id="roomId">ID: loading...</span>
                        </div>
                    </div>
                    <button class="btn btn-danger" onclick="closeRoom()">🔒 Đóng Chat</button>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <!-- Messages will appear here -->
                </div>

                <div class="typing-indicator" id="typingIndicator" style="display: none;">
                    ⌨️ Khách hàng đang gõ...
                </div>

                <div class="chat-input">
                    <div class="input-group">
                        <input type="text" class="message-input" id="messageInput" 
                               placeholder="💬 Nhập tin nhắn hỗ trợ..." onkeypress="handleKeyPress(event)">
                        <button class="send-btn" onclick="sendMessage()">
                            <span id="sendBtnText">📤 Gửi</span>
                            <div id="sendBtnLoading" class="loading" style="display: none;"></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="http://localhost:5000/socket.io/socket.io.js"></script>
    <script>
        // Configuration
        const SOCKET_URL = 'http://localhost:5000';
        const API_URL = 'http://localhost:5000/api';
        
        // Preloaded data (from PHP, direct DB)
        const PRELOADED_WAITING_ROOMS = <?php echo json_encode(array_map(fn($r) => [
            'roomId' => (string)($r['roomId'] ?? ''),
            'userId' => (string)($r['userId'] ?? ''),
            'adminId' => (string)($r['adminId'] ?? ''),
            'status' => (string)($r['status'] ?? 'waiting'),
            'createdAt' => isset($r['createdAt']) ? $r['createdAt']->toDateTime()->format(DateTime::ATOM) : null,
            'updatedAt' => isset($r['updatedAt']) ? $r['updatedAt']->toDateTime()->format(DateTime::ATOM) : null,
        ], $preloadedWaitingRooms)); ?>;
        const PRELOADED_ACTIVE_ROOMS = <?php echo json_encode(array_map(fn($r) => [
            'roomId' => (string)($r['roomId'] ?? ''),
            'userId' => (string)($r['userId'] ?? ''),
            'adminId' => (string)($r['adminId'] ?? ''),
            'status' => (string)($r['status'] ?? 'active'),
            'createdAt' => isset($r['createdAt']) ? $r['createdAt']->toDateTime()->format(DateTime::ATOM) : null,
            'updatedAt' => isset($r['updatedAt']) ? $r['updatedAt']->toDateTime()->format(DateTime::ATOM) : null,
        ], $preloadedActiveRooms)); ?>;
        const PRELOADED_CLOSED_ROOMS = <?php echo json_encode(array_map(fn($r) => [
            'roomId' => (string)($r['roomId'] ?? ''),
            'userId' => (string)($r['userId'] ?? ''),
            'adminId' => (string)($r['adminId'] ?? ''),
            'status' => (string)($r['status'] ?? 'closed'),
            'createdAt' => isset($r['createdAt']) ? $r['createdAt']->toDateTime()->format(DateTime::ATOM) : null,
            'updatedAt' => isset($r['updatedAt']) ? $r['updatedAt']->toDateTime()->format(DateTime::ATOM) : null,
        ], $preloadedClosedRooms)); ?>;
        let usedPreloadedOnce = false;

        // Global variables
        let socket;
        let currentRoom = null;
        let adminId = '<?php echo $_SESSION['user_id'] ; ?>';
        console.log('Admin ID:', adminId);
        let typingTimeout;
        let refreshInterval;
        let messageRefreshInterval;
        let lastRenderedMessageAt = null;
        let isLoadingHistory = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeSocket();
            // Render preloaded rooms immediately for first paint
            if (PRELOADED_WAITING_ROOMS.length || PRELOADED_ACTIVE_ROOMS.length || PRELOADED_CLOSED_ROOMS.length) {
                displayWaitingRooms(PRELOADED_WAITING_ROOMS);
                displayActiveRooms(PRELOADED_ACTIVE_ROOMS);
                displayClosedRooms(PRELOADED_CLOSED_ROOMS);
                usedPreloadedOnce = true;
            }
            loadRooms();
            startAutoRefresh();
            
            // Setup message input events
            const messageInput = document.getElementById('messageInput');
            messageInput.addEventListener('input', handleTyping);
        });
        // Khi admin gửi tin nhắn
        document.getElementById('sendBtnText').addEventListener('click', () => {
                const message = document.getElementById('messageInput').value;
                if (!message || !currentRoom) return;

                const messageData = {
                    roomId: currentRoom.roomId,
                    senderId: adminId,           // <-- ID của admin hiện tại
                    senderType: 'admin',         // <-- QUAN TRỌNG
                    message: message,
                    messageType: 'text'
                };
                console.log('Sending message:', messageData);
                socket.emit('send_message', messageData); // 🚀 Emit để server lưu vào DB

                // Optionally: hiển thị ngay lập tức trên giao diện
                displayMessage({
                    ...messageData,
                    timestamp: new Date().toISOString()
                });

                document.getElementById('messageInput').value = '';
                });
        function initializeSocket() {
             socket = io(SOCKET_URL, {
                transports: ['websocket'],
                reconnection: true,
                reconnectionAttempts: Infinity,
                reconnectionDelay: 1000,
                reconnectionDelayMax: 5000,
                path: '/socket.io'
             });

            socket.on('connect', () => {
                console.log('✅ Connected to server');
                updateConnectionStatus(true);
                // Re-join current room on reconnect
                if (currentRoom && currentRoom.roomId) {
                    const joinPayload = {
                        roomId: currentRoom.roomId,
                        userId: adminId,
                        userType: 'admin'
                    };
                    socket.emit('join_room', joinPayload);
                    socket.emit('joinRoom', joinPayload);
                }
            });

            socket.on('disconnect', () => {
                console.log('❌ Disconnected from server');
                updateConnectionStatus(false);
            });

            const getMessageRoomId = (m) => m?.roomId || m?.room_id || m?.room || m?.roomID || m?.room_id_str;
            const handleIncoming = (message) => {
                const incomingRoomId = getMessageRoomId(message);
                if (currentRoom && incomingRoomId === currentRoom.roomId) {
                    displayMessage(message);
                }
                // Update room list to show new message indicator
                loadRooms();
            };

            socket.on('receive_message', handleIncoming);
            socket.on('receiveMessage', handleIncoming);
            socket.on('message', handleIncoming);
            socket.on('new_message', handleIncoming);
            socket.on('newMessage', handleIncoming);
            socket.on('chat_message', handleIncoming);
            socket.on('private_message', handleIncoming);

            // Catch-all logger & fallback handler
            socket.onAny((event, payload) => {
                try { console.debug('socket event:', event, payload); } catch (e) {}
                if (payload && (payload.message || payload.content)) {
                    handleIncoming(payload);
                }
            });

            socket.on('user_typing', (data) => {
                if (currentRoom && data.roomId === currentRoom.roomId && data.userType === 'user') {
                    showTypingIndicator(data.isTyping);
                }
            });
            socket.on('messages_read', ({ roomId, updatedCount }) => {
                const roomEls = document.querySelectorAll('.room-item');
                roomEls.forEach(el => {
                    if (el.innerHTML.includes(roomId.split('_').pop().substr(-8))) {
                    el.classList.remove('has-unread');
                    const badge = el.querySelector('.unread-badge');
                    if (badge) badge.remove();
                    }
                });
                });
            socket.on('room_closed', (data) => {
                if (currentRoom && data.roomId === currentRoom.roomId) {
                    showNotification('Cuộc trò chuyện đã được đóng', 'info');
                    currentRoom = null;
                    showEmptyState();
                    loadRooms();
                }
            });

            socket.on('new_room_created', () => {
                loadRooms();
                playNotificationSound();
            });
            // Initialize connection error handling AFTER socket is ready
            handleConnectionError();
        }

        async function loadRooms() {
            await Promise.all([
                loadWaitingRooms(),
                loadActiveRooms(),
                // loadClosedRooms()
            ]);
        }

        async function loadWaitingRooms() {
            try {
                const response = await fetch(`${API_URL}/chat/rooms/waiting`, {
                    headers: {
                        'Authorization': `Bearer ${getAuthToken()}`
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    displayWaitingRooms(data.rooms || []);
                } else {
                    console.error('Error loading waiting rooms:', data.message);
                }
            } catch (error) {
                if (!usedPreloadedOnce && PRELOADED_WAITING_ROOMS.length) {
                    displayWaitingRooms(PRELOADED_WAITING_ROOMS);
                } else {
                    console.error('Error loading waiting rooms:', error);
                }
            }
        }

        async function loadActiveRooms() {
            try {
                const response = await fetch(`${API_URL}/chat/rooms/active/${adminId}`, {
                    headers: {
                        'Authorization': `Bearer ${getAuthToken()}`
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    displayActiveRooms(data.rooms || []);
                } else {
                    console.error('Error loading active rooms:', data.message);
                }
            } catch (error) {
                if (!usedPreloadedOnce && PRELOADED_ACTIVE_ROOMS.length) {
                    displayActiveRooms(PRELOADED_ACTIVE_ROOMS);
                } else {
                    console.error('Error loading active rooms:', error);
                }
            }
        }

        async function loadClosedRooms() {
            try {
                const response = await fetch(`${API_URL}/chat/rooms/closed`, {
                    headers: { 'Authorization': `Bearer ${getAuthToken()}` }
                });
                if (!response.ok) throw new Error('closed rooms ' + response.status);
                const data = await response.json();
                if (data.success && Array.isArray(data.rooms) && data.rooms.length > 0) {
                    displayClosedRooms(data.rooms);
                } else if (PRELOADED_CLOSED_ROOMS.length > 0) {
                    // Fallback to preloaded DB data if API returns empty
                    displayClosedRooms(PRELOADED_CLOSED_ROOMS);
                } else {
                    displayClosedRooms([]);
                }
            } catch (e) {
                if (PRELOADED_CLOSED_ROOMS.length > 0) {
                    displayClosedRooms(PRELOADED_CLOSED_ROOMS);
                } else {
                    displayClosedRooms([]);
                }
            }
        }

        function displayWaitingRooms(rooms) {
            const container = document.getElementById('waitingRooms');
            container.innerHTML = '';
            
            if (rooms.length === 0) {
                container.innerHTML = '<div style="padding: 16px 20px; text-align: center; color: #666; font-size: 13px;">Không có phòng nào đang chờ</div>';
                return;
            }
            
            rooms.forEach(room => {
                const roomElement = createRoomElement(room, 'waiting');
                container.appendChild(roomElement);
            });
        }

        function displayActiveRooms(rooms) {
            const container = document.getElementById('activeRooms');
            container.innerHTML = '';
            
            if (rooms.length === 0) {
                container.innerHTML = '<div style="padding: 16px 20px; text-align: center; color: #666; font-size: 13px;">Không có cuộc trò chuyện nào</div>';
                return;
            }
            
            rooms.forEach(room => {
                const roomElement = createRoomElement(room, 'active');
                container.appendChild(roomElement);
            });
        }

        function displayClosedRooms(rooms) {
            const container = document.getElementById('closedRooms');
            container.innerHTML = '';
            if (!Array.isArray(rooms) || rooms.length === 0) {
                container.innerHTML = '<div style="padding: 12px 20px; color:#666; font-size: 13px;">Không có cuộc trò chuyện đã đóng</div>';
                return;
            }
            rooms.forEach(room => {
                const el = createRoomElement(room, 'closed');
                el.style.opacity = '0.7';
                container.appendChild(el);
            });
        }

        function createRoomElement(room, type) {
  const div = document.createElement('div');
  div.className = `room-item ${type}`;
  div.onclick = () => type === 'waiting' ? joinRoom(room) : (type === 'active' ? openActiveRoom(room) : openClosedRoom(room));
  const userMap = <?php echo json_encode($userMap); ?>;
  // Extract short room ID for display
  const shortRoomId = room.roomId ? room.roomId.split('_').pop().substr(-8) : 'N/A';

  // Skeleton nội dung ban đầu
  div.innerHTML = `
    <div class="room-user">👤 User: ${userMap[room.userId] || 'Unknown'}</div>
    <div class="room-meta">
      <div class="room-header">
        <span class="room-time">🕒 ${formatTime(room.createdAt)}</span>
        <span class="room-id">ID: ${shortRoomId}</span>
      </div>
      <div class="last-message" style="margin-top:4px; font-size:12px; color: #ccc;">
        Đang tải tin nhắn...
      </div>
    </div>
    <div class="room-stats">
      <span class="stat-badge">📍 ${room.status || 'unknown'}</span>
      ${room.adminId ? `<span class="stat-badge">👨‍💼 ${userMap[room.adminId]}</span>` : ''}
    </div>
    ${type === 'waiting' ? '<button class="btn btn-success" style="margin-top: 12px; width: 100%;" onclick="event.stopPropagation()">🚀 Tham gia hỗ trợ</button>' : ''}
  `;

  // (Ẩn unread badge do API chưa có)

  // Lấy tin nhắn cuối cùng để preview + hiển thị "Đã xem" nếu phù hợp
  fetch(`${API_URL}/chat/messages/${room.roomId}`, { // fallback: dùng endpoint gốc rồi lấy cuối
    headers: { Authorization: `Bearer ${getAuthToken()}` }
  })
    .then(r => r.json())
    .then(data => {
      const lastMsgEl = div.querySelector('.last-message');
      if (!data.success || !Array.isArray(data.messages)) {
        if (lastMsgEl) {
          lastMsgEl.innerHTML = '<span style="color: #888; font-style: italic;">Không lấy được tin nhắn</span>';
        }
        return;
      }

      // Lấy tin nhắn cuối cùng (mới nhất)
      const messages = data.messages;
      const lastMessage = messages[messages.length - 1];

      if (!lastMessage) {
        if (lastMsgEl) {
          lastMsgEl.innerHTML = '<span style="color: #888; font-style: italic;">Chưa có tin nhắn nào</span>';
        }
        return;
      }

      // Kiểm tra xem tin nhắn có mới không (trong vòng 5 phút)
      const messageTime = new Date(lastMessage.timestamp);
      const now = new Date();
      const isRecentMessage = (now - messageTime) < 5 * 60 * 1000; // 5 phút

      // Hiển thị preview (rút gọn, escape HTML)
      const previewText = (lastMessage.message || '').replace(/\n/g, ' ').trim();
      const truncated = previewText.length > 50 ? previewText.slice(0, 47) + '...' : previewText;
      
      let display = '';
      let messageStyle = '';
      let senderIcon = '';
      
      if (lastMessage.senderType === 'admin') {
        display += 'Bạn: ';
        senderIcon = '';
        messageStyle = 'color: #0084ff; font-weight: 500;';
      } else {
        // Tin nhắn từ user
        senderIcon = '💬 ';
        if (!lastMessage.isRead) {
          // Tin nhắn chưa đọc - làm nổi bật giống Messenger
          messageStyle = 'color: #fff; font-weight: 600; background: rgba(0,132,255,0.1); padding: 2px 6px; border-radius: 8px;';
        } else {
          messageStyle = 'color: #ccc; font-weight: 400;';
        }
      }

      // Thêm indicator cho tin nhắn mới
      let newIndicator = '';
      if (isRecentMessage && lastMessage.senderType === 'user' && !lastMessage.isRead) {
        newIndicator = '<span style="color: #00d4aa; font-size: 10px; font-weight: 600; margin-left: 4px;">● MỚI</span>';
      }

      display = `${senderIcon}${escapeHtml(truncated)}`;

      // Nếu tin nhắn cuối là của admin và đã được user đọc (isRead === true), show "Đã xem"
      let seenTag = '';
      if (lastMessage.senderType === 'admin' && lastMessage.isRead) {
        seenTag = `<span class="seen-label" style="margin-left:6px; font-size:10px; color:#00d4aa; font-weight: 500;">✓ Đã xem</span>`;
      } else if (lastMessage.senderType === 'admin' && !lastMessage.isRead) {
        seenTag = `<span class="seen-label" style="margin-left:6px; font-size:10px; color:#888; font-weight: 500;">✓ Đã gửi</span>`;
      }

      if (lastMsgEl) {
        lastMsgEl.innerHTML = `<span style="${messageStyle}">${display}</span>${seenTag}${newIndicator}`;
        
        // Thêm animation cho tin nhắn mới
        if (isRecentMessage && lastMessage.senderType === 'user') {
          lastMsgEl.style.animation = 'pulse 2s ease-in-out infinite';
        }
      }

      // Thêm timestamp cho tin nhắn (giống Messenger)
      const timeAgo = getTimeAgo(messageTime);
      const existingTime = div.querySelector('.message-time');
      if (!existingTime) {
        const timeEl = document.createElement('div');
        timeEl.className = 'message-time';
        timeEl.style.cssText = 'font-size: 10px; color: #888; margin-top: 2px;';
        timeEl.textContent = timeAgo;
        lastMsgEl.parentNode.appendChild(timeEl);
      }
    })
    .catch(() => {
      const lastMsgEl = div.querySelector('.last-message');
      if (lastMsgEl) {
        lastMsgEl.innerHTML = '<span style="color: #f44336; font-style: italic;">⚠️ Lỗi tải tin nhắn</span>';
      }
    });

  return div;
}

// Helper function để tính thời gian "time ago"
function getTimeAgo(date) {
  const now = new Date();
  const diffInSeconds = Math.floor((now - date) / 1000);
  
  if (diffInSeconds < 60) return 'Vừa xong';
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} phút trước`;
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} giờ trước`;
  return `${Math.floor(diffInSeconds / 86400)} ngày trước`;
}

// Thêm CSS animations (chỉ thêm một lần)
if (!document.getElementById('messenger-style-css')) {
  const messengerStyle = document.createElement('style');
  messengerStyle.id = 'messenger-style-css';
  messengerStyle.textContent = `
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }
    
    .room-item:hover {
      transform: translateY(-1px);
      transition: transform 0.2s ease;
    }
    
    .unread-badge {
      animation: fadeInScale 0.3s ease-out;
    }
    
    @keyframes fadeInScale {
      0% { opacity: 0; transform: scale(0.5); }
      100% { opacity: 1; transform: scale(1); }
    }
  `;
  document.head.appendChild(messengerStyle);
}
        async function joinRoom(room) {
            if (!room || !room.roomId) {
                showNotification('Thông tin phòng không hợp lệ', 'error');
                return;
            }

            try {
                showLoadingState(true);
                
                const response = await fetch(`${API_URL}/chat/room/join`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${getAuthToken()}`
                    },
                    body: JSON.stringify({
                        roomId: room.roomId,
                        adminId: adminId
                    })
                });

                const data = await response.json();
                
                if (data.success && data.room) {
                    await openRoom(data.room);
                    loadRooms();
                    showNotification('Đã tham gia phòng chat thành công!', 'success');
                } else {
                    showNotification(data.message || 'Không thể tham gia phòng chat', 'error');
                }
            } catch (error) {
                console.error('Error joining room:', error);
                showNotification('Lỗi kết nối, vui lòng thử lại', 'error');
            } finally {
                showLoadingState(false);
            }
        }

        async function openActiveRoom(room) {
            if (!room || !room.roomId) {
                showNotification('Thông tin phòng không hợp lệ', 'error');
                return;
            }
            await openRoom(room);
            // Hiện lại input nếu trước đó xem phòng đã đóng
            const inputBox = document.querySelector('.chat-input');
            if (inputBox) inputBox.style.display = 'block';
            const typing = document.getElementById('typingIndicator');
            if (typing) typing.style.display = 'none';
        }

        async function openClosedRoom(room) {
            if (!room || !room.roomId) {
                showNotification('Thông tin phòng không hợp lệ', 'error');
                return;
            }
            // Chỉ xem lại tin nhắn, không join socket, không cho gửi
            try {
                currentRoom = { ...room, status: 'closed' };
                showChatInterface(room);
                // Ẩn input gửi tin
                document.querySelector('.chat-input').style.display = 'none';
                document.getElementById('typingIndicator').style.display = 'none';
                await loadMessages(room.roomId);
            } catch (e) {
                console.error('Error open closed room:', e);
                showNotification('Không thể mở phòng đã đóng', 'error');
            }
        }

        async function openRoom(room) {
            try {
                currentRoom = room;
                
                // Join socket room
                const joinPayload = {
                    roomId: room.roomId,
                    userId: adminId,
                    userType: 'admin'
                };
                socket.emit('join_room', joinPayload);
                socket.emit('joinRoom', joinPayload);
                
                // Load messages (suppress notification sounds during initial load)
                isLoadingHistory = true;
                await loadMessages(room.roomId);
                isLoadingHistory = false;
                // Đánh dấu tất cả tin nhắn user trong phòng này là đã đọc
                socket.emit('mark_as_read', { roomId: room.roomId });
                // Show chat interface
                showChatInterface(room);
                
                // Mark room as active in sidebar
                updateActiveRoom(room.roomId);
                
            } catch (error) {
                console.error('Error opening room:', error);
                showNotification('Không thể mở phòng chat', 'error');
            }
        }

        async function loadMessages(roomId) {
            try {
                const response = await fetch(`${API_URL}/chat/messages/${roomId}`, {
                    headers: {
                        'Authorization': `Bearer ${getAuthToken()}`
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    const messagesContainer = document.getElementById('chatMessages');
                    messagesContainer.innerHTML = '';
                    
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            displayMessage(message);
                        });
                    } else {
                        messagesContainer.innerHTML = '<div style="text-align: center; color: #666; padding: 20px;">Chưa có tin nhắn nào. Hãy bắt đầu cuộc trò chuyện!</div>';
                    }
                    
                    // Scroll to bottom
                    setTimeout(() => {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }, 100);
                } else {
                    console.error('Error loading messages:', data.message);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                showNotification('Không thể tải tin nhắn', 'error');
            }
        }
        function displayMessage(msg) {
            const container = document.querySelector('.chat-messages');
            const msgEl = document.createElement('div');
            msgEl.className = msg.senderType === 'admin' ? 'message admin' : 'message user';
            msgEl.innerHTML = `<p>${msg.message}</p><span>${new Date(msg.timestamp).toLocaleTimeString()}</span>`;
            container.appendChild(msgEl);
            container.scrollTop = container.scrollHeight;
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const text = input.value.trim();

            if (!text || !currentRoom) return;

            // Ẩn nút gửi, hiện loading
            document.getElementById('sendBtnText').style.display = 'none';
            document.getElementById('sendBtnLoading').style.display = 'inline-block';

            const messageData = {
                roomId: currentRoom.roomId,
                senderId: adminId,        // biến này là ID của admin
                senderType: 'admin',
                message: text,
                messageType: 'text'
            };

            // Gửi lên server
            socket.emit('send_message', messageData);
            socket.emit('sendMessage', messageData);

            // Hiển thị ngay lập tức lên giao diện
            displayMessage({
                ...messageData,
                timestamp: new Date().toISOString()
            });

            input.value = '';

            // Reset nút
            setTimeout(() => {
                document.getElementById('sendBtnText').style.display = 'inline';
                document.getElementById('sendBtnLoading').style.display = 'none';
            }, 200);
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendMessage();
            }
        }
        function handleTyping() {
            if (!currentRoom) return;
            
            socket.emit('typing', {
                roomId: currentRoom.roomId,
                userId: adminId,
                userType: 'admin',
                isTyping: true
            });
            
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                socket.emit('typing', {
                    roomId: currentRoom.roomId,
                    userId: adminId,
                    userType: 'admin',
                    isTyping: false
                });
            }, 1000);
        }

        function showTypingIndicator(isTyping) {
            const indicator = document.getElementById('typingIndicator');
            indicator.style.display = isTyping ? 'block' : 'none';
        }

        function showChatInterface(room) {
            document.getElementById('emptyChatState').style.display = 'none';
            document.getElementById('chatInterface').style.display = 'flex';
            document.getElementById('currentRoomUser').textContent = `👤 User: ${room.userId || 'Unknown'}`;
            document.getElementById('roomStatus').textContent = room.status || 'active';
            document.getElementById('roomId').textContent = `ID: ${room.roomId ? room.roomId.split('_').pop().substr(-8) : 'N/A'}`;
            
            // Focus on input
            setTimeout(() => {
                document.getElementById('messageInput').focus();
            }, 100);
        }

        function showEmptyState() {
            document.getElementById('emptyChatState').style.display = 'flex';
            document.getElementById('chatInterface').style.display = 'none';
        }

        function closeRoom() {
            if (!currentRoom) return;
            
            if (confirm('Bạn có chắc chắn muốn đóng cuộc trò chuyện này?')) {
                socket.emit('close_room', {
                    roomId: currentRoom.roomId,
                    closedBy: adminId
                });
                
                showNotification('Đã đóng cuộc trò chuyện', 'info');
                setTimeout(() => {
                location.reload();
                 }, 300);
            }
        }

        function updateActiveRoom(roomId) {
            // Remove active class from all rooms
            document.querySelectorAll('.room-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to current room
            const roomElements = document.querySelectorAll('.room-item');
            roomElements.forEach(element => {
                if (element.innerHTML.includes(roomId.split('_').pop().substr(-8))) {
                    element.classList.add('active');
                }
            });
        }

        function startAutoRefresh() {
            // Refresh rooms every 15 seconds
            refreshInterval = setInterval(loadRooms, 15000);
        }

        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }

        // Message polling fallback disabled by default (re-enable only if server events are unavailable)

        function showLoadingState(isLoading) {
            const buttons = document.querySelectorAll('.btn-success');
            buttons.forEach(btn => {
                if (isLoading) {
                    btn.disabled = true;
                    btn.innerHTML = '<div class="loading"></div> Đang tham gia...';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '🚀 Tham gia hỗ trợ';
                }
            });
        }

        function showSendButtonLoading(isLoading) {
            const btnText = document.getElementById('sendBtnText');
            const btnLoading = document.getElementById('sendBtnLoading');
            const sendBtn = document.querySelector('.send-btn');
            
            if (isLoading) {
                btnText.style.display = 'none';
                btnLoading.style.display = 'block';
                sendBtn.disabled = true;
            } else {
                btnText.style.display = 'block';
                btnLoading.style.display = 'none';
                sendBtn.disabled = false;
            }
        }
        

        function updateConnectionStatus(isConnected) {
            const header = document.querySelector('.sidebar-header');
            if (isConnected) {
                header.style.background = 'linear-gradient(135deg, #2c3e50, #34495e)';
            } else {
                header.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
            }
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 16px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                max-width: 400px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                animation: slideInRight 0.3s ease;
            `;
            
            // Set background color based on type
            const colors = {
                success: 'linear-gradient(135deg, #4caf50, #388e3c)',
                error: 'linear-gradient(135deg, #f44336, #d32f2f)',
                warning: 'linear-gradient(135deg, #ff9800, #f57c00)',
                info: 'linear-gradient(135deg, #2196f3, #1976d2)'
            };
            
            notification.style.background = colors[type] || colors.info;
            notification.textContent = message;
            
            // Add to document
            document.body.appendChild(notification);
            
            // Remove after 4 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }

        function playNotificationSound() {
            // Sound disabled globally per user request
            return;
        }

        function formatTime(timestamp) {
            if (!timestamp) return 'N/A';
            
            const date = new Date(timestamp);
            const now = new Date();
            const diffInMinutes = Math.floor((now - date) / (1000 * 60));
            
            if (diffInMinutes < 1) {
                return 'Vừa xong';
            } else if (diffInMinutes < 60) {
                return `${diffInMinutes} phút trước`;
            } else if (diffInMinutes < 24 * 60) {
                const hours = Math.floor(diffInMinutes / 60);
                return `${hours} giờ trước`;
            } else {
                return date.toLocaleString('vi-VN', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getAuthToken() {
            // Return JWT token from PHP session or cookie
            return '<?php echo $_SESSION['auth_token'] ?? 'dummy-token'; ?>';
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .notification {
                transition: all 0.3s ease;
            }
            
            /* Responsive design */
            @media (max-width: 768px) {
                .container {
                    flex-direction: column;
                }
                
                .sidebar {
                    width: 100%;
                    height: 300px;
                }
                
                .chat-area {
                    margin: 0;
                    border-radius: 0;
                    flex: 1;
                }
                
                .room-list {
                    flex-direction: row;
                    overflow-x: auto;
                    overflow-y: hidden;
                }
                
                .room-item {
                    min-width: 200px;
                    margin: 4px;
                }
                
                .message-content {
                    max-width: 85%;
                }
                
                .input-group {
                    flex-direction: column;
                    gap: 8px;
                }
                
                .send-btn {
                    width: 100%;
                }
            }
            
            /* Dark mode support */
            @media (prefers-color-scheme: dark) {
                .room-item {
                    background: rgba(45, 45, 45, 0.9);
                    color: #e0e0e0;
                }
                
                .message.user .message-content {
                    background: rgba(45, 45, 45, 0.9);
                    color: #e0e0e0;
                    border-color: rgba(255, 255, 255, 0.1);
                }
                
                .chat-messages {
                    background: rgba(30, 30, 30, 0.5);
                }
                
                .message-input {
                    background: rgba(45, 45, 45, 0.9);
                    color: #e0e0e0;
                    border-color: rgba(255, 255, 255, 0.1);
                }
                
                .message-input::placeholder {
                    color: rgba(255, 255, 255, 0.6);
                }
            }
        `;
        document.head.appendChild(style);

        // Handle page visibility change to pause/resume auto-refresh
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
                loadRooms(); // Refresh immediately when page becomes visible
            }
        });

        // Handle beforeunload to clean up
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
            if (socket) {
                socket.disconnect();
            }
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl/Cmd + Enter to send message
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                sendMessage();
            }
            
            // Escape to close current chat
            if (event.key === 'Escape' && currentRoom) {
                showEmptyState();
                currentRoom = null;
                updateActiveRoom('');
            }
        });

        // Add connection retry logic
        function handleConnectionError() {
            if (!socket || typeof socket.on !== 'function') return;
            let retryCount = 0;
            const maxRetries = 5;
            const retryDelay = 2000; // 2 seconds
            
            function retry() {
                retryCount++;
                if (retryCount <= maxRetries) {
                    showNotification(`Đang thử kết nối lại... (${retryCount}/${maxRetries})`, 'warning');
                    setTimeout(() => {
                        socket.connect();
                    }, retryDelay * retryCount);
                } else {
                    showNotification('Không thể kết nối đến server. Vui lòng tải lại trang.', 'error');
                }
            }
            
            socket.on('connect_error', retry);
            socket.on('disconnect', () => {
                if (retryCount === 0) {
                    setTimeout(retry, retryDelay);
                }
            });
        }


        // Add auto-scroll to new messages
        function scrollToBottom(smooth = true) {
            const messagesContainer = document.getElementById('chatMessages');
            if (messagesContainer) {
                messagesContainer.scrollTo({
                    top: messagesContainer.scrollHeight,
                    behavior: smooth ? 'smooth' : 'auto'
                });
            }
        }

        // Enhanced message display with better formatting
        function displayMessage(message) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.senderType}`;
            
            const timeStr = formatTime(message.timestamp || message.createdAt);
            const messageText = message.message || message.content || '';
            
            // Process message text for URLs and formatting
            const processedMessage = processMessageText(messageText);
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    <div>${processedMessage}</div>
                    <div class="message-time">${timeStr}</div>
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
            
            // Play sound for new user messages
            if (!isLoadingHistory && message.senderType === 'user') {
                playNotificationSound();
            }
        }

        function processMessageText(text) {
            // Escape HTML first
            let processed = escapeHtml(text);
            
            // Convert URLs to links
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            processed = processed.replace(urlRegex, '<a href="$1" target="_blank" style="color: inherit; text-decoration: underline;">$1</a>');
            
            // Convert line breaks to <br>
            processed = processed.replace(/\n/g, '<br>');
            
            return processed;
        }
        function goBack() {
        window.history.back();
        }
        // Add room statistics
        function updateRoomStats() {
            const waitingCount = document.querySelectorAll('#waitingRooms .room-item').length;
            const activeCount = document.querySelectorAll('#activeRooms .room-item').length;
            
            document.querySelector('.sidebar-header p').innerHTML = 
                `Admin: <?php echo htmlspecialchars($user['name']) ?><br>
                <small>🏠 ${activeCount} đang chat • ⏳ ${waitingCount} chờ hỗ trợ</small>`;
        }

        // Call updateRoomStats after loading rooms
        const originalLoadRooms = loadRooms;
        loadRooms = async function() {
            await originalLoadRooms();
            updateRoomStats();
        };

        console.log('🚀 Admin Chat Support initialized successfully!');
    </script>
</body>
</html> 