<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GA&sup2; Pharmacy Automation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            padding: 40px 20px;
        }

        /* ── PAGE WRAPPER ── */
        .page-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            width: 100%;
            max-width: 1000px;
            animation: slideInUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(40px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── CLOCK BAR ── */
        .clock-bar {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            border-radius: 14px;
            padding: 14px 36px;
            display: flex;
            align-items: center;
            gap: 24px;
            box-shadow: 0 8px 30px rgba(27, 94, 63, 0.25);
            width: 100%;
            max-width: 680px;
            position: relative;
            overflow: hidden;
        }

        .clock-bar::before {
            content: '';
            position: absolute;
            top: -60%;
            left: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .clock-bar::after {
            content: '';
            position: absolute;
            bottom: -60%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(46,204,113,0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .clock-time-group {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            position: relative;
            z-index: 1;
        }

        .clock-time {
            font-family: 'Share Tech Mono', monospace;
            font-size: 2.8rem;
            color: #ffffff;
            letter-spacing: 0.06em;
            line-height: 1;
        }

        .clock-ampm {
            font-family: 'Share Tech Mono', monospace;
            font-size: 1rem;
            color: #2ecc71;
            font-weight: 600;
            letter-spacing: 0.12em;
            padding-bottom: 5px;
        }

        .clock-divider {
            width: 1px;
            height: 36px;
            background: rgba(255, 255, 255, 0.15);
            position: relative;
            z-index: 1;
        }

        .clock-date-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
            position: relative;
            z-index: 1;
        }

        .clock-day {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.45);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .clock-date {
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.7);
            letter-spacing: 0.04em;
        }

        .clock-pulse {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #2ecc71;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.85); }
        }

        .pulse-label {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 0.5px;
        }

        /* ── LOGIN CONTAINER ── */
        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            min-height: 600px;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }

        /* LEFT PANEL */
        .login-left {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #ffffff;
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            align-self: stretch;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -40%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .login-left::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(46,204,113,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .brand-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            display: inline-block;
        }

        .brand-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            letter-spacing: -0.5px;
        }

        .brand-tagline {
            font-size: 1rem;
            font-weight: 300;
            opacity: 0.9;
            margin-bottom: 3rem;
            line-height: 1.6;
        }

        .feature-pills {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }

        .feature-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            font-weight: 500;
            opacity: 0.85;
        }

        .feature-pill i {
            color: #2ecc71;
            font-size: 1.2rem;
        }

        /* RIGHT PANEL */
        .login-right {
            padding: 3.5rem 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
            align-self: stretch;
        }

        .form-header {
            margin-bottom: 1.5rem;
        }

        .form-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #1b5e3f;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .form-title .accent {
            color: #2ecc71;
        }

        .form-subtitle {
            font-size: 0.95rem;
            color: #666;
            font-weight: 300;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .form-label {
            color: #1b5e3f;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: #fafafa;
            color: #1A2A4F;
            transition: all 0.3s ease;
        }

        .form-control::placeholder {
            color: #bbb;
        }

        .form-control:focus {
            outline: none;
            border-color: #2ecc71;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(46,204,113,0.1), inset 0 0 0 1px rgba(46,204,113,0.2);
        }

        .form-control.error {
            border-color: #ef4444;
        }

        .form-control.error:focus {
            box-shadow: 0 0 0 4px rgba(239,68,68,0.1), inset 0 0 0 1px rgba(239,68,68,0.2);
        }

        .password-toggle {
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 1.1rem;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: #2ecc71;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            animation: slideInDown 0.4s ease;
        }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert i {
            font-size: 1.1rem;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            margin: 0.5rem 0;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .remember-me input {
            cursor: pointer;
            accent-color: #2ecc71;
            width: 18px;
            height: 18px;
        }

        .remember-me label {
            cursor: pointer;
            color: #666;
            font-weight: 500;
        }

        .forgot-password {
            color: #2ecc71;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .forgot-password:hover {
            color: #1b5e3f;
        }

        .btn-login {
            padding: 1rem;
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(27, 94, 63, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(27, 94, 63, 0.4);
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            color: #2ecc71;
            font-weight: 500;
        }

        .security-badge i {
            color: #2ecc71;
            font-size: 0.95rem;
        }

        /* VALIDATION STATES */
        .field-error .form-control {
            border-color: #ef4444;
        }

        .field-error .form-control:focus {
            box-shadow: 0 0 0 4px rgba(239,68,68,0.1), inset 0 0 0 1px rgba(239,68,68,0.2);
        }

        .field-pristine .form-control:not(:focus) {
            border-color: #e0e0e0;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .clock-bar {
                padding: 12px 20px;
                max-width: 100%;
                border-radius: 12px;
            }

            .clock-time {
                font-size: 2rem;
            }

            .clock-pulse {
                display: none;
            }

            .login-container {
                grid-template-columns: 1fr;
                height: auto;
                min-height: 100vh;
                border-radius: 0;
            }

            .login-left {
                padding: 3rem 2rem;
                min-height: 300px;
            }

            .login-right {
                padding: 2rem;
            }

            .brand-title {
                font-size: 1.5rem;
            }

            .form-title {
                font-size: 1.5rem;
            }

            .feature-pills {
                flex-direction: row;
                gap: 1.5rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .feature-pill {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">

        <!-- ── CLOCK BAR ── -->
        <div class="clock-bar">
            <div class="clock-time-group">
                <div class="clock-time" id="clockTime">--:--:--</div>
                <div class="clock-ampm" id="clockAmPm">--</div>
            </div>
            <div class="clock-divider"></div>
            <div class="clock-date-group">
                <div class="clock-day" id="clockDay">-------</div>
                <div class="clock-date" id="clockDate">--- --, ----</div>
            </div>
            <div class="clock-pulse">
                <div class="pulse-dot"></div>
                <span class="pulse-label">Live</span>
            </div>
        </div>

        <!-- ── LOGIN CONTAINER ── -->
        <div class="login-container">

            <!-- LEFT PANEL -->
            <div class="login-left">
                <div class="brand-content">
                    <div class="brand-icon">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                    <h1 class="brand-title">GA<sup>2</sup> Pharmacy Automation System</h1>
                    <p class="brand-tagline">
                        Comprehensive patient records management with secure access and real-time updates
                    </p>
                    <div class="feature-pills">
                        <div class="feature-pill">
                            <i class="bi bi-shield-lock"></i>
                            <span>HIPAA Compliant</span>
                        </div>
                        <div class="feature-pill">
                            <i class="bi bi-database"></i>
                            <span>Centralized Data</span>
                        </div>
                        <div class="feature-pill">
                            <i class="bi bi-file-earmark-medical"></i>
                            <span>Patient Focused</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL -->
            <div class="login-right">
                <div class="form-header">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #2ecc71; font-weight: 700; letter-spacing: 1px; margin-bottom: 0.5rem;">Secure Access</div>
                    <h2 class="form-title">Sign <span class="accent">in</span> to your account</h2>
                    <p class="form-subtitle">Enter your credentials to continue</p>
                </div>

                <form action="auth.php" method="POST" id="loginForm" autocomplete="off" novalidate>
                    <?php
                    if (isset($_SESSION['error'])) {
                        echo '<div class="alert alert-error" role="alert"><i class="bi bi-exclamation-circle-fill"></i> ' . htmlspecialchars($_SESSION['error']) . '</div>';
                        unset($_SESSION['error']);
                    }
                    ?>

                    <div class="form-group field-pristine">
                        <label for="username" class="form-label">
                            <i class="bi bi-person-fill"></i> Username
                        </label>
                        <div class="input-wrapper">
                            <input
                                type="text"
                                class="form-control"
                                id="username"
                                name="username"
                                placeholder="Enter your username"
                                required
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div class="form-group field-pristine">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock-fill"></i> Password
                        </label>
                        <div class="input-wrapper">
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <div class="remember-me">
                            <input type="checkbox" id="rememberMe" name="remember_me">
                            <label for="rememberMe">Remember me</label>
                        </div>
                        <a href="#" class="forgot-password">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>
                </form>

                <div class="form-footer">
                    <div class="security-badge">
                        <i class="bi bi-lock"></i>
                        <span>Secured connection · v2.4.1</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /* ── CLOCK ── */
        const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const pad    = n => String(n).padStart(2, '0');

        function tickClock() {
            const now  = new Date();
            let   h    = now.getHours();
            const m    = now.getMinutes();
            const s    = now.getSeconds();
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;

            document.getElementById('clockTime').textContent  = `${pad(h)}:${pad(m)}:${pad(s)}`;
            document.getElementById('clockAmPm').textContent  = ampm;
            document.getElementById('clockDay').textContent   = days[now.getDay()];
            document.getElementById('clockDate').textContent  = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        }

        tickClock();
        setInterval(tickClock, 1000);

        /* ── PASSWORD TOGGLE ── */
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput  = document.querySelector('#password');
        const usernameInput  = document.querySelector('#username');
        const loginForm      = document.querySelector('#loginForm');
        const loginBtn       = document.querySelector('#loginBtn');

        togglePassword.addEventListener('click', function (e) {
            e.preventDefault();
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password'
                ? '<i class="bi bi-eye-slash"></i>'
                : '<i class="bi bi-eye"></i>';
        });

        /* ── FORM RESET ON LOAD ── */
        document.addEventListener('DOMContentLoaded', function () {
            usernameInput.value = '';
            passwordInput.value = '';
            loginForm.reset();
        });

        /* ── FIELD VALIDATION ── */
        const formGroups = document.querySelectorAll('.form-group');

        formGroups.forEach(group => {
            const input = group.querySelector('.form-control');

            input.addEventListener('focus', function () {
                group.classList.remove('field-pristine');
                group.classList.remove('field-error');
            });

            input.addEventListener('blur', function () {
                if (!this.value.trim()) {
                    group.classList.add('field-pristine');
                } else {
                    group.classList.remove('field-pristine');
                }
            });

            input.addEventListener('input', function () {
                if (this.value.trim()) {
                    this.classList.remove('error');
                    group.classList.remove('field-error');
                }
            });
        });

        /* ── FORM SUBMIT ── */
        loginForm.addEventListener('submit', function (e) {
            let isValid = true;

            formGroups.forEach(group => {
                const input = group.querySelector('.form-control');
                if (!input.value.trim()) {
                    group.classList.add('field-error');
                    input.classList.add('error');
                    isValid = false;
                } else {
                    input.classList.remove('error');
                    group.classList.remove('field-error');
                }
            });

            if (!isValid) {
                e.preventDefault();
            }

            if (isValid) {
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Signing in...';
            }
        });
    </script>
</body>
</html>