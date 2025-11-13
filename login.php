<?php
session_start();
require_once 'connection/db_connect.php';

$error_message = '';
$success_message = '';

// Check for authentication messages from middleware
if (isset($_GET['auth_error'])) {
    switch ($_GET['auth_error']) {
        case 'session_expired':
            $error_message = 'Your session has expired. Please log in again.';
            break;
        case 'not_authenticated':
            $error_message = 'Please log in to access this page.';
            break;
        default:
            $error_message = 'Authentication required. Please log in.';
    }
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard_main.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, suffix, email, password, status FROM sso_user WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Verify password (assuming passwords are hashed with password_hash())
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_password'] = $password;
                $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name'] . ' ' . $user['suffix']);
                $_SESSION['login_time'] = time();

                // Redirect to dashboard
                header('Location: dashboard_main.php');
                exit();
            } else {
                $error_message = 'Invalid email or password.';
            }
        } else {
            $error_message = 'Invalid email or password.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login — SSO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --purple-500: #10b981;
            --purple-600: #136515;
            --purple-50: #f5f3ff;
            --bg: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            color: var(--text);
            background: var(--bg);
        }

        .split {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .left {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .content {
            max-width: 420px;
            width: 100%;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 32px;
        }

        .logo img {
            max-width: 56px;
            border-radius: 6px;
        }

        h1 {
            font-size: 1.8rem;
            margin: 0 0 8px;
        }

        .subtitle {
            color: var(--muted);
            margin-bottom: 18px;
        }

        .field {
            margin-bottom: 14px;
        }

        .label {
            display: block;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 6px;
        }

        .input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .input:focus {
            border-color: var(--purple-500);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }

        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }

        input[type="password"]::-webkit-textfield-decoration-container {
            display: none;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .input {
            padding-right: 44px;
        }

        .toggle-password {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            padding: 0;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--muted);
        }

        .toggle-password:hover {
            color: var(--purple-600);
        }

        .toggle-password:focus {
            outline: none;
        }

        .toggle-password svg {
            display: block;
            width: 22px;
            height: 22px;
        }

        .btn {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #18a558 0%, #136515 100%);
        }

        .btn:hover {
            background: var(--purple-600);
        }

        .right {
            flex: 1;
            position: relative;
            overflow: hidden;
            border-left: 1px solid var(--border);
        }

        #canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            display: block;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin: 12px 0;
            font-size: 14px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        @media (max-width: 768px) {
            .split {
                flex-direction: column;
            }

            .right {
                display: none;
            }

            .left {
                padding: 20px 16px;
            }

            .content {
                max-width: 360px;
                width: 100%;
                margin: 0 auto;
            }

            .brand {
                justify-content: center;
                margin-bottom: 20px;
            }

            .logo img {
                max-width: 40px;
            }

            h1 {
                font-size: 1.5rem;
                text-align: center;
            }

            .subtitle {
                font-size: 0.95rem;
                text-align: center;
            }

            .field {
                margin-bottom: 12px;
            }

            .label {
                font-size: 0.85rem;
            }

            .input {
                padding: 10px 12px;
                font-size: 0.95rem;
            }

            .btn {
                padding: 10px 12px;
                font-size: 1rem;
            }
        }
    </style>
    <style>
        /* Full-page loader */
        .loader-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loader-overlay.active {
            display: flex;
        }

        .loader-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .loader {
            width: 56px;
            height: 56px;
            border: 6px solid var(--border);
            border-top-color: var(--purple-600);
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }

        .loader-text {
            color: #fff;
            font-weight: 600;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div id="loaderOverlay" class="loader-overlay" aria-live="polite" aria-busy="true">
        <div class="loader-wrap">
            <div class="loader"></div>
            <div class="loader-text">Signing you in...</div>
        </div>
    </div>
    <div class="split">
        <div class="left">
            <div class="content">
                <div class="brand">
                    <div class="logo" aria-hidden="true"><img src="./images/Inang_pamantasan_logo.png" alt="sso logo"></div>
                    <div class="logo" aria-hidden="true"><img src="./images/Student_Servoice-removebg-preview.png" alt="sso logo"></div>
                    <div class="logo" aria-hidden="true"><img src="./images/SSO_LOGO.png" alt="sso logo"></div>
                </div>
                <h1>Welcome back</h1>
                <p class="subtitle">Please enter your details</p>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <div class="field">
                        <label class="label" for="email">Email address</label>
                        <input class="input" type="email" id="email" name="email" placeholder="you@example.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="password">Password</label>
                        <div class="input-wrap">
                            <input class="input" type="password" id="password" name="password" placeholder="••••••••" required>
                            <button type="button" class="toggle-password" aria-label="Show or hide password" onclick="togglePassword()">
                                <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye-icon lucide-eye">
                                    <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg id="eye-off-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye-off-icon lucide-eye-off" style="display:none">
                                    <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49" />
                                    <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242" />
                                    <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143" />
                                    <path d="m2 2 20 20" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn">Sign in</button>
                </form>
            </div>
        </div>
        <div class="right" aria-hidden="true">
            <div class="logo_right" aria-hidden="true">
                <img src="./images/Student_Servoice-removebg-preview.png" alt="sso logo">
            </div>
            <canvas id="canvas"></canvas>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeOffIcon = document.getElementById('eye-off-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        }
        // ================== PARTICLE EFFECT ==================
        const rightEl = document.querySelector('.right');
        const canvas = document.getElementById("canvas");
        const ctx = canvas.getContext("2d");

        const particles = [];
        const fireworkParticles = [];
        const dustParticles = [];
        const ripples = [];
        const techRipples = [];

        const mouse = (() => {
            let state = {
                x: null,
                y: null
            };
            return {
                get x() {
                    return state.x;
                },
                get y() {
                    return state.y;
                },
                set({
                    x,
                    y
                }) {
                    state = {
                        x,
                        y
                    };
                },
                reset() {
                    state = {
                        x: null,
                        y: null
                    };
                }
            };
        })();

        let frameCount = 0;
        let autoDrift = true;

        function adjustParticleCount() {
            const particleConfig = {
                heightConditions: [200, 300, 400, 500, 600],
                widthConditions: [450, 600, 900, 1200, 1600],
                particlesForHeight: [20, 30, 40, 50, 60],
                particlesForWidth: [20, 30, 40, 50, 60]
            };

            let numParticles = 60;
            for (let i = 0; i < particleConfig.heightConditions.length; i++) {
                if (canvas.height < particleConfig.heightConditions[i]) {
                    numParticles = particleConfig.particlesForHeight[i];
                    break;
                }
            }

            for (let i = 0; i < particleConfig.widthConditions.length; i++) {
                if (canvas.width < particleConfig.widthConditions[i]) {
                    numParticles = Math.min(numParticles, particleConfig.particlesForWidth[i]);
                    break;
                }
            }

            return numParticles;
        }

        class Particle {
            constructor(x, y, isFirework = false) {
                const baseSpeed = isFirework ? Math.random() * 2 + 1 : Math.random() * 0.5 + 0.3;

                Object.assign(this, {
                    isFirework,
                    x,
                    y,
                    vx: Math.cos(Math.random() * Math.PI * 2) * baseSpeed,
                    vy: Math.sin(Math.random() * Math.PI * 2) * baseSpeed,
                    size: isFirework ? Math.random() * 1 + 1 : Math.random() * 1.5 + 0.5,
                    hue: Math.random() * 60 + 90, // soft lime green range
                    alpha: 1,
                    sizeDirection: Math.random() < 0.5 ? -1 : 1,
                    trail: []
                });
            }

            update(mouse) {
                const dist = mouse.x !== null ? (mouse.x - this.x) ** 2 + (mouse.y - this.y) ** 2 : 0;
                if (!this.isFirework) {
                    const force = dist && dist < 22500 ? (22500 - dist) / 22500 : 0;

                    if (mouse.x === null && autoDrift) {
                        this.vx += (Math.random() - 0.5) * 0.03;
                        this.vy += (Math.random() - 0.5) * 0.03;
                    }

                    if (dist) {
                        const sqrtDist = Math.sqrt(dist);
                        this.vx += ((mouse.x - this.x) / sqrtDist) * force * 0.1;
                        this.vy += ((mouse.y - this.y) / sqrtDist) * force * 0.1;
                    }

                    this.vx *= mouse.x !== null ? 0.99 : 0.998;
                    this.vy *= mouse.y !== null ? 0.99 : 0.998;
                } else {
                    this.alpha -= 0.02;
                }

                this.x += this.vx;
                this.y += this.vy;

                if (this.x <= 0 || this.x >= canvas.width - 1) this.vx *= -0.9;
                if (this.y < 0 || this.y > canvas.height) this.vy *= -0.9;

                this.size += this.sizeDirection * 0.1;
                if (this.size > 3 || this.size < 0.5) this.sizeDirection *= -1;

                this.hue = (this.hue + 0.3) % 360;

                if (frameCount % 2 === 0 && (Math.abs(this.vx) > 0.1 || Math.abs(this.vy) > 0.1)) {
                    this.trail.push({
                        x: this.x,
                        y: this.y,
                        hue: this.hue,
                        alpha: this.alpha
                    });
                    if (this.trail.length > 15) this.trail.shift();
                }
            }

            draw(ctx) {
                const glowColor = `hsl(${this.hue}, 90%, 60%)`;
                const gradient = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.size);
                gradient.addColorStop(0, `hsla(${this.hue}, 100%, 70%, ${Math.max(this.alpha, 0)})`);
                gradient.addColorStop(1, `hsla(${this.hue}, 80%, 40%, 0)`);

                ctx.fillStyle = gradient;
                ctx.shadowBlur = 20;
                ctx.shadowColor = glowColor;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.shadowBlur = 0;

                if (this.trail.length > 1) {
                    ctx.beginPath();
                    ctx.lineWidth = 1.5;
                    for (let i = 0; i < this.trail.length - 1; i++) {
                        const {
                            x: x1,
                            y: y1,
                            hue: h1,
                            alpha: a1
                        } = this.trail[i];
                        const {
                            x: x2,
                            y: y2
                        } = this.trail[i + 1];
                        ctx.strokeStyle = `hsla(${h1}, 100%, 60%, ${Math.max(a1, 0)})`;
                        ctx.moveTo(x1, y1);
                        ctx.lineTo(x2, y2);
                    }
                    ctx.stroke();
                }
            }

            isDead() {
                return this.isFirework && this.alpha <= 0;
            }
        }

        class DustParticle {
            constructor() {
                Object.assign(this, {
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 1.2 + 0.3,
                    hue: Math.random() * 60 + 40, // yellow-green tint
                    vx: (Math.random() - 0.5) * 0.05,
                    vy: (Math.random() - 0.5) * 0.05
                });
            }

            update() {
                this.x = (this.x + this.vx + canvas.width) % canvas.width;
                this.y = (this.y + this.vy + canvas.height) % canvas.height;
                this.hue = (this.hue + 0.1) % 360;
            }

            draw(ctx) {
                ctx.fillStyle = `hsla(${this.hue}, 50%, 70%, 0.25)`;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        class Ripple {
            constructor(x, y, hue = 90, maxRadius = 30) {
                Object.assign(this, {
                    x,
                    y,
                    radius: 0,
                    maxRadius,
                    alpha: 0.5,
                    hue
                });
            }

            update() {
                this.radius += 1.5;
                this.alpha -= 0.01;
                this.hue = (this.hue + 5) % 360;
            }

            draw(ctx) {
                ctx.strokeStyle = `hsla(${this.hue}, 90%, 60%, ${this.alpha})`;
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.stroke();
            }

            isDone() {
                return this.alpha <= 0;
            }
        }

        function createParticles() {
            particles.length = 0;
            dustParticles.length = 0;

            const numParticles = adjustParticleCount();
            for (let i = 0; i < numParticles; i++) {
                particles.push(new Particle(Math.random() * canvas.width, Math.random() * canvas.height));
            }
            for (let i = 0; i < 80; i++) {
                dustParticles.push(new DustParticle());
            }
        }

        function resizeCanvas() {
            // Size canvas to the right panel so it doesn't overlap the form
            if (!rightEl || rightEl.offsetParent === null) {
                canvas.width = 0;
                canvas.height = 0;
                return;
            }
            const rect = rightEl.getBoundingClientRect();
            canvas.width = Math.max(0, Math.floor(rect.width));
            canvas.height = Math.max(0, Math.floor(rect.height));
            createParticles();
        }

        function drawBackground() {
            const gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
            gradient.addColorStop(0, "#0A1B0A");
            gradient.addColorStop(1, "#133D1C");
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }

        function connectParticles() {
            const gridSize = 120;
            const grid = new Map();

            particles.forEach((p) => {
                const key = `${Math.floor(p.x / gridSize)},${Math.floor(p.y / gridSize)}`;
                if (!grid.has(key)) grid.set(key, []);
                grid.get(key).push(p);
            });

            ctx.lineWidth = 1.2;
            particles.forEach((p) => {
                const gridX = Math.floor(p.x / gridSize);
                const gridY = Math.floor(p.y / gridSize);

                for (let dx = -1; dx <= 1; dx++) {
                    for (let dy = -1; dy <= 1; dy++) {
                        const key = `${gridX + dx},${gridY + dy}`;
                        if (grid.has(key)) {
                            grid.get(key).forEach((neighbor) => {
                                if (neighbor !== p) {
                                    const diffX = neighbor.x - p.x;
                                    const diffY = neighbor.y - p.y;
                                    const dist = diffX * diffX + diffY * diffY;
                                    if (dist < 10000) {
                                        ctx.strokeStyle = `rgba(0, 255, 100, ${1 - Math.sqrt(dist) / 100})`;
                                        ctx.beginPath();
                                        ctx.moveTo(p.x, p.y);
                                        ctx.lineTo(neighbor.x, neighbor.y);
                                        ctx.stroke();
                                    }
                                }
                            });
                        }
                    }
                }
            });
        }

        function animate() {
            drawBackground();

            [dustParticles, particles, ripples, techRipples, fireworkParticles].forEach((arr) => {
                for (let i = arr.length - 1; i >= 0; i--) {
                    const obj = arr[i];
                    obj.update(mouse);
                    obj.draw(ctx);
                    if (obj.isDone?.() || obj.isDead?.()) arr.splice(i, 1);
                }
            });

            connectParticles();
            frameCount++;
            requestAnimationFrame(animate);
        }

        canvas.addEventListener("mousemove", (e) => {
            const rect = canvas.getBoundingClientRect();
            mouse.set({
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            });
            techRipples.push(new Ripple(mouse.x, mouse.y));
            autoDrift = false;
        });

        canvas.addEventListener("mouseleave", () => {
            mouse.reset();
            autoDrift = true;
        });

        canvas.addEventListener("click", (e) => {
            const rect = canvas.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const clickY = e.clientY - rect.top;

            ripples.push(new Ripple(clickX, clickY, 100, 60));

            for (let i = 0; i < 10; i++) {
                const angle = Math.random() * Math.PI * 2;
                const speed = Math.random() * 2 + 1;
                const particle = new Particle(clickX, clickY, true);
                particle.vx = Math.cos(angle) * speed;
                particle.vy = Math.sin(angle) * speed;
                fireworkParticles.push(particle);
            }
        });

        window.addEventListener("resize", resizeCanvas);
        resizeCanvas();
        animate();

        // Show full-page loader on form submit
        const loginForm = document.querySelector('form');
        const loaderOverlay = document.getElementById('loaderOverlay');
        if (loginForm && loaderOverlay) {
            loginForm.addEventListener('submit', function() {
                loaderOverlay.classList.add('active');
            });
        }
    </script>
</body>

</html>