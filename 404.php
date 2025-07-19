<?php
// Thiết lập HTTP status code 404
http_response_code(404);

// Lấy thông tin về trang được yêu cầu
$requested_url = $_SERVER['REQUEST_URI'];
$current_time = date('Y-m-d H:i:s');

// Log lỗi 404 (tùy chọn)
error_log("404 Error: $requested_url at $current_time");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Space Adventure - Tìm Đường Về Nhà</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0c0c0c 0%, #1a1a2e 50%, #16213e 100%);
            color: white;
            overflow: hidden;
            height: 100vh;
            cursor: none;
        }

        .game-container {
            position: relative;
            width: 100%;
            height: 100vh;
            z-index: 2;
        }

        .hud {
            position: fixed;
            top: 20px;
            left: 20px;
            right: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .score {
            background: rgba(0, 0, 0, 0.7);
            padding: 10px 20px;
            border-radius: 25px;
            border: 2px solid #00ff88;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.5);
        }

        .health {
            background: rgba(0, 0, 0, 0.7);
            padding: 10px 20px;
            border-radius: 25px;
            border: 2px solid #ff4444;
            box-shadow: 0 0 20px rgba(255, 68, 68, 0.5);
        }

        .game-title {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 5;
            transition: all 0.5s ease;
        }

        .error-code {
            font-size: 8rem;
            font-weight: bold;
            background: linear-gradient(45deg, #ff6b6b, #ffd93d, #6bcf7f, #4d9de0);
            background-size: 400% 400%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientShift 3s ease infinite;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.5);
        }

        .game-message {
            font-size: 1.8rem;
            margin: 20px 0;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.7);
        }

        .game-instructions {
            font-size: 1.2rem;
            margin: 20px 0;
            opacity: 0.9;
        }

        .start-btn {
            padding: 15px 40px;
            background: linear-gradient(45deg, #00ff88, #00ccff);
            border: none;
            border-radius: 50px;
            color: white;
            font-size: 1.3rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px 10px;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.5);
        }

        .start-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 30px rgba(0, 255, 136, 0.8);
        }

        .home-btn {
            background: linear-gradient(45deg, #ff6b6b, #ffd93d);
            box-shadow: 0 0 20px rgba(255, 107, 107, 0.5);
        }

        .home-btn:hover {
            box-shadow: 0 0 30px rgba(255, 107, 107, 0.8);
        }

        .spaceship {
            position: absolute;
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #00ff88, #00ccff);
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            transform: translate(-50%, -50%);
            transition: all 0.1s ease;
            z-index: 3;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.8);
        }

        .asteroid {
            position: absolute;
            background: linear-gradient(45deg, #8b4513, #a0522d);
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(139, 69, 19, 0.6);
            animation: float 3s ease-in-out infinite;
        }

        .star {
            position: absolute;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
            animation: twinkle 2s ease-in-out infinite;
        }

        .portal {
            position: absolute;
            width: 80px;
            height: 80px;
            border: 3px solid #00ff88;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 255, 136, 0.3) 0%, transparent 70%);
            animation: portal-spin 2s linear infinite;
            box-shadow: 0 0 30px rgba(0, 255, 136, 0.8);
        }

        .game-over {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 15;
            background: rgba(0, 0, 0, 0.9);
            padding: 40px;
            border-radius: 20px;
            border: 2px solid #ff4444;
            box-shadow: 0 0 40px rgba(255, 68, 68, 0.5);
            display: none;
        }

        .victory {
            border-color: #00ff88;
            box-shadow: 0 0 40px rgba(0, 255, 136, 0.5);
        }

        .cursor {
            position: fixed;
            width: 20px;
            height: 20px;
            background: radial-gradient(circle, #00ff88 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 20;
            mix-blend-mode: difference;
        }

        #three-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(180deg); }
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        @keyframes portal-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes explosion {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(3); opacity: 0; }
        }

        @media (max-width: 768px) {
            .error-code { font-size: 6rem; }
            .game-message { font-size: 1.4rem; }
            .game-instructions { font-size: 1rem; }
            .hud { font-size: 1rem; }
            .spaceship { width: 40px; height: 40px; }
        }
    </style>
</head>
<body>
    <div class="cursor" id="cursor"></div>
    <div id="three-container"></div>
    
    <div class="game-container">
        <div class="hud">
            <div class="score">⭐ Điểm: <span id="score">0</span></div>
            <div class="health">❤️ Máu: <span id="health">100</span></div>
        </div>

        <div class="game-title" id="gameTitle">
            <div class="error-code">404</div>
            <div class="game-message">🚀 Tàu Vũ Trụ Của Bạn Đã Lạc Đường!</div>
            <div class="game-instructions">
                🎮 Di chuyển chuột để điều khiển tàu<br>
                ⭐ Thu thập sao để có điểm<br>
                🌀 Tìm portal xanh để về nhà<br>
                ☄️ Tránh các thiên thạch!
            </div>
            <button class="start-btn" onclick="startGame()">🚀 Bắt Đầu Phiêu Lưu</button>
            <button class="start-btn home-btn" onclick="goHome()">🏠 Về Trang Chủ</button>
        </div>

        <div class="game-over" id="gameOver">
            <h2 id="gameOverTitle">🎉 Chúc Mừng!</h2>
            <p id="gameOverMessage">Bạn đã tìm thấy đường về nhà!</p>
            <div style="margin: 20px 0;">
                <strong>Điểm Cuối: <span id="finalScore">0</span></strong>
            </div>
            <button class="start-btn" onclick="restartGame()">🔄 Chơi Lại</button>
            <button class="start-btn home-btn" onclick="goHome()">🏠 Về Trang Chủ</button>
        </div>

        <div class="spaceship" id="spaceship"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script>
        // Game variables
        let gameActive = false;
        let score = 0;
        let health = 100;
        let spaceship = document.getElementById('spaceship');
        let gameObjects = [];
        let animationId;
        let portalSpawned = false;

        // Mouse tracking
        let mouseX = window.innerWidth / 2;
        let mouseY = window.innerHeight / 2;

        // Custom cursor
        const cursor = document.getElementById('cursor');
        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            cursor.style.left = mouseX - 10 + 'px';
            cursor.style.top = mouseY - 10 + 'px';
            
            if (gameActive) {
                spaceship.style.left = mouseX + 'px';
                spaceship.style.top = mouseY + 'px';
            }
        });

        // Three.js background
        function init3DBackground() {
            const container = document.getElementById('three-container');
            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({ alpha: true });
            
            renderer.setSize(window.innerWidth, window.innerHeight);
            container.appendChild(renderer.domElement);

            // Create space background
            const starsGeometry = new THREE.BufferGeometry();
            const starsVertices = [];
            
            for (let i = 0; i < 10000; i++) {
                starsVertices.push(
                    (Math.random() - 0.5) * 2000,
                    (Math.random() - 0.5) * 2000,
                    (Math.random() - 0.5) * 2000
                );
            }
            
            starsGeometry.setAttribute('position', new THREE.Float32BufferAttribute(starsVertices, 3));
            
            const starsMaterial = new THREE.PointsMaterial({
                color: 0xffffff,
                size: 2,
                transparent: true,
                opacity: 0.8
            });
            
            const stars = new THREE.Points(starsGeometry, starsMaterial);
            scene.add(stars);

            camera.position.z = 5;

            function animate() {
                requestAnimationFrame(animate);
                stars.rotation.x += 0.0005;
                stars.rotation.y += 0.0005;
                renderer.render(scene, camera);
            }

            animate();

            window.addEventListener('resize', () => {
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(window.innerWidth, window.innerHeight);
            });
        }

        // Game functions
        function startGame() {
            gameActive = true;
            score = 0;
            health = 100;
            portalSpawned = false;
            gameObjects = [];
            
            document.getElementById('gameTitle').style.display = 'none';
            document.getElementById('gameOver').style.display = 'none';
            spaceship.style.display = 'block';
            
            updateHUD();
            spawnGameObjects();
            gameLoop();
        }

        function restartGame() {
            startGame();
        }

        function goHome() {
            window.location.href = '/';
        }

        function updateHUD() {
            document.getElementById('score').textContent = score;
            document.getElementById('health').textContent = health;
        }

        function spawnGameObjects() {
            // Spawn stars
            if (Math.random() < 0.3) {
                createStar();
            }
            
            // Spawn asteroids
            if (Math.random() < 0.2) {
                createAsteroid();
            }
            
            // Spawn portal when score >= 50
            if (score >= 50 && !portalSpawned) {
                createPortal();
                portalSpawned = true;
            }
        }

        function createStar() {
            const star = document.createElement('div');
            star.className = 'star';
            star.style.width = '10px';
            star.style.height = '10px';
            star.style.left = Math.random() * window.innerWidth + 'px';
            star.style.top = Math.random() * window.innerHeight + 'px';
            star.type = 'star';
            document.body.appendChild(star);
            gameObjects.push(star);
        }

        function createAsteroid() {
            const asteroid = document.createElement('div');
            asteroid.className = 'asteroid';
            const size = Math.random() * 30 + 20;
            asteroid.style.width = size + 'px';
            asteroid.style.height = size + 'px';
            asteroid.style.left = Math.random() * window.innerWidth + 'px';
            asteroid.style.top = Math.random() * window.innerHeight + 'px';
            asteroid.type = 'asteroid';
            document.body.appendChild(asteroid);
            gameObjects.push(asteroid);
        }

        function createPortal() {
            const portal = document.createElement('div');
            portal.className = 'portal';
            portal.style.left = Math.random() * (window.innerWidth - 80) + 'px';
            portal.style.top = Math.random() * (window.innerHeight - 80) + 'px';
            portal.type = 'portal';
            document.body.appendChild(portal);
            gameObjects.push(portal);
        }

        function checkCollisions() {
            const spaceshipRect = spaceship.getBoundingClientRect();
            
            gameObjects.forEach((obj, index) => {
                const objRect = obj.getBoundingClientRect();
                
                if (isColliding(spaceshipRect, objRect)) {
                    if (obj.type === 'star') {
                        score += 10;
                        createExplosion(obj, '#ffd93d');
                        playSound('collect');
                    } else if (obj.type === 'asteroid') {
                        health -= 20;
                        createExplosion(obj, '#ff6b6b');
                        playSound('damage');
                        if (health <= 0) {
                            gameOver(false);
                        }
                    } else if (obj.type === 'portal') {
                        gameOver(true);
                    }
                    
                    obj.remove();
                    gameObjects.splice(index, 1);
                }
            });
        }

        function isColliding(rect1, rect2) {
            return rect1.left < rect2.right &&
                   rect1.right > rect2.left &&
                   rect1.top < rect2.bottom &&
                   rect1.bottom > rect2.top;
        }

        function createExplosion(obj, color) {
            const explosion = document.createElement('div');
            explosion.style.position = 'absolute';
            explosion.style.left = obj.style.left;
            explosion.style.top = obj.style.top;
            explosion.style.width = '20px';
            explosion.style.height = '20px';
            explosion.style.background = color;
            explosion.style.borderRadius = '50%';
            explosion.style.animation = 'explosion 0.5s ease-out';
            document.body.appendChild(explosion);
            
            setTimeout(() => explosion.remove(), 500);
        }

        function playSound(type) {
            // Simple sound simulation with visual feedback
            const flash = document.createElement('div');
            flash.style.position = 'fixed';
            flash.style.top = '0';
            flash.style.left = '0';
            flash.style.width = '100%';
            flash.style.height = '100%';
            flash.style.pointerEvents = 'none';
            flash.style.zIndex = '25';
            
            if (type === 'collect') {
                flash.style.background = 'rgba(255, 211, 61, 0.1)';
            } else if (type === 'damage') {
                flash.style.background = 'rgba(255, 107, 107, 0.2)';
            }
            
            document.body.appendChild(flash);
            setTimeout(() => flash.remove(), 100);
        }

        function gameOver(victory) {
            gameActive = false;
            cancelAnimationFrame(animationId);
            
            const gameOverDiv = document.getElementById('gameOver');
            const title = document.getElementById('gameOverTitle');
            const message = document.getElementById('gameOverMessage');
            
            if (victory) {
                title.textContent = '🎉 Chúc Mừng!';
                message.textContent = 'Bạn đã tìm thấy đường về nhà!';
                gameOverDiv.classList.add('victory');
            } else {
                title.textContent = '💥 Game Over!';
                message.textContent = 'Tàu vũ trụ của bạn đã bị phá hủy!';
                gameOverDiv.classList.remove('victory');
            }
            
            document.getElementById('finalScore').textContent = score;
            gameOverDiv.style.display = 'block';
            spaceship.style.display = 'none';
            
            // Clean up game objects
            gameObjects.forEach(obj => obj.remove());
            gameObjects = [];
        }

        function gameLoop() {
            if (!gameActive) return;
            
            updateHUD();
            spawnGameObjects();
            checkCollisions();
            
            // Remove objects that are too old
            gameObjects = gameObjects.filter(obj => {
                if (obj.offsetParent === null) {
                    return false;
                }
                return true;
            });
            
            animationId = requestAnimationFrame(gameLoop);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            init3DBackground();
            spaceship.style.display = 'none';
        });

        // Keyboard controls
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space' && !gameActive) {
                e.preventDefault();
                startGame();
            }
        });
    </script>
</body>
</html>