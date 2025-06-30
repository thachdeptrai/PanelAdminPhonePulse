<?php
include 'includes/config.php';
include 'includes/functions.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: trang_chu");
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password']) && $user['role'] == true) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        header("Location: trang_chu");
        exit();
    } else {
        $error = "Invalid credentials or insufficient privileges";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phonepulse Admin | Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary: #6c5ce7;
            --dark: #1e293b;
            --darker: #0f172a;
            --glow: rgba(108, 92, 231, 0.6);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--darker);
            color: white;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        
        .bg-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .login-container {
            width: 380px;
            perspective: 1000px;
            transform-style: preserve-3d;
        }
        
        .login-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transform-style: preserve-3d;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent,
                rgba(108, 92, 231, 0.3),
                transparent
            );
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: rotate(45deg) translate(-30%, -30%); }
            100% { transform: rotate(45deg) translate(30%, 30%); }
        }
        
        .login-card:hover {
            transform: translateY(-5px) rotateX(5deg) rotateY(0deg) scale(1.01);
            box-shadow: 0 35px 60px rgba(0, 0, 0, 0.6);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            background: linear-gradient(90deg, #6c5ce7, #a29bfe);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .login-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        .input-group input {
            width: 100%;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--glow);
        }
        
        .input-group label {
            position: absolute;
            top: 15px;
            left: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 15px;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: -10px;
            left: 15px;
            font-size: 12px;
            background: var(--dark);
            padding: 0 5px;
            color: var(--primary);
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-container input {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            position: relative;
            cursor: pointer;
        }
        
        .checkbox-container input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .checkbox-container input:checked::after {
            content: '✓';
            position: absolute;
            color: white;
            font-size: 12px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .forgot-password {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: var(--primary);
        }
        
        .login-button {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, #a29bfe, #6c5ce7);
            transition: width 0.3s ease;
            z-index: -1;
        }
        
        .login-button:hover::before {
            width: 100%;
        }
        
        .login-button:active {
            transform: translateY(2px);
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0) rotateX(0deg) rotateY(0deg); }
            50% { transform: translateY(-10px) rotateX(2deg) rotateY(2deg); }
        }
        
        .login-container {
            animation: floating 6s ease-in-out infinite;
        }
        
        /* Particle.js effect */
        canvas {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Particle.js Background -->
    <div class="bg-particles" id="particles-js"></div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#6c5ce7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                    </svg>
                </div>
                <h2>Welcome Back</h2>
                <p>Please login to access your dashboard</p>
            </div>
            
            <form method="POST" action="">
                <div class="input-group">
                    <input type="email" name="email" placeholder=" " required>
                    <label for="email">Email Address</label>
                </div>
                
                <div class="input-group">
                    <input type="password" name="password" placeholder=" " required>
                    <label for="password">Password</label>
                </div>
                
                <div class="remember-forgot">
                    <div class="checkbox-container">
                        <input type="checkbox" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="login-button">Login</button>
                <?php if (isset($error)): ?>
                    <p class="text-red-500 text-center mt-2"><?php echo $error; ?></p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        // Initialize particle.js
        particlesJS('particles-js', {
            particles: {
                number: { value: 80, density: { enable: true, value_area: 800 } },
                color: { value: "#6c5ce7" },
                shape: { type: "circle" },
                opacity: { value: 0.5, random: true },
                size: { value: 3, random: true },
                line_linked: { enable: true, distance: 150, color: "#6c5ce7", opacity: 0.4, width: 1 },
                move: { enable: true, speed: 2, direction: "none", random: true, straight: false, out_mode: "out" }
            },
            interactivity: {
                detect_on: "canvas",
                events: {
                    onhover: { enable: true, mode: "repulse" },
                    onclick: { enable: true, mode: "push" },
                    resize: true
                }
            }
        });

        // 3D tilt effect
        const loginContainer = document.querySelector('.login-container');
        
        loginContainer.addEventListener('mousemove', (e) => {
            const xAxis = (window.innerWidth / 2 - e.pageX) / 25;
            const yAxis = (window.innerHeight / 2 - e.pageY) / 25;
            loginContainer.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
        });
        
        loginContainer.addEventListener('mouseenter', () => {
            loginContainer.style.transition = 'transform 0.1s ease';
        });
        
        loginContainer.addEventListener('mouseleave', () => {
            loginContainer.style.transition = 'all 0.5s ease';
            loginContainer.style.transform = 'rotateX(0) rotateY(0)';
        });
    </script>
</body>
</html>
