<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | School Attendance System</title>

    <style>
        :root {
            --bg: #f4f6fb;
            --surface: #ffffff;
            --border: #e2e6ef;
            --text: #1c2333;
            --muted: #6b7489;
            --primary: #3b5bdb;
            --primary-soft: #e7ecff;
            --error-bg: #fde7ec;
            --error-text: #c92a45;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        button,
        input {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
        }

        .login-card {
            width: min(960px, 100%);
            min-height: 560px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(28, 35, 51, 0.09);
        }

        /* LEFT SIDE: LOGIN FORM */

        .login-form {
            padding: 52px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* DARK MODE / LIGHT MODE BUTTON */

        .theme-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
        }

        .theme-button {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface);
            color: var(--text);
            font-size: 0.82rem;
            font-weight: 600;
            transition: background 0.2s, color 0.2s;
        }

        .theme-button:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 38px;
        }

        .brand-icon {
            width: 46px;
            height: 46px;
            flex: none;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 6px 15px rgba(59, 91, 219, 0.22);
        }

        .brand-icon svg {
            width: 26px;
            height: 26px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-name {
            font-size: 0.95rem;
            font-weight: 750;
            line-height: 1.3;
        }

        .brand-caption {
            color: var(--muted);
            font-size: 0.78rem;
            margin-top: 3px;
        }

        .login-form h1 {
            font-size: 1.9rem;
            letter-spacing: -0.7px;
            margin-bottom: 7px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 0.94rem;
            margin-bottom: 28px;
        }

        .error {
            padding: 12px 14px;
            margin-bottom: 20px;
            border: 1px solid #f8c9d2;
            border-radius: 10px;
            background: var(--error-bg);
            color: var(--error-text);
            font-size: 0.88rem;
            overflow-wrap: anywhere;
        }

        .field {
            margin-bottom: 19px;
        }

        .field label {
            display: block;
            font-size: 0.87rem;
            font-weight: 650;
            margin-bottom: 8px;
        }

        .input-wrap {
            position: relative;
        }

        .input {
            width: 100%;
            min-width: 0;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            color: var(--text);
            font-size: 0.94rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input::placeholder {
            color: #a0a8b8;
        }

        .input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }

        .password-input {
            padding-right: 68px;
        }

        .toggle-pass {
            position: absolute;
            top: 50%;
            right: 13px;
            transform: translateY(-50%);
            padding: 4px;
            border: 0;
            background: transparent;
            color: var(--primary);
            font-size: 0.82rem;
            font-weight: 650;
        }

        .toggle-pass:hover {
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            margin-top: 5px;
            padding: 13px 16px;
            border: 0;
            border-radius: 10px;
            background: var(--primary);
            color: #ffffff;
            font-size: 0.96rem;
            font-weight: 650;
            box-shadow: 0 5px 12px rgba(59, 91, 219, 0.18);
            transition: background 0.2s, transform 0.2s;
        }

        .btn:hover {
            background: #304bc0;
            transform: translateY(-1px);
        }

        .btn:focus-visible,
        .toggle-pass:focus-visible,
        .theme-button:focus-visible {
            outline: 3px solid #a5b4fc;
            outline-offset: 3px;
        }

        .footer {
            margin-top: 28px;
            color: var(--muted);
            text-align: center;
            font-size: 0.77rem;
        }

        /* RIGHT SIDE: DECORATIVE PANEL */

        .login-art {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 42px;
            overflow: hidden;
            background:
                radial-gradient(circle at 85% 15%, rgba(255,255,255,.85), transparent 28%),
                linear-gradient(145deg, #e7ecff 0%, #dce5ff 50%, #eef2ff 100%);
        }

        .art-circle {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(59, 91, 219, 0.13);
        }

        .art-circle.one {
            width: 330px;
            height: 330px;
            top: -120px;
            right: -100px;
        }

        .art-circle.two {
            width: 250px;
            height: 250px;
            bottom: -100px;
            left: -80px;
        }

        .illustration {
            position: relative;
            z-index: 1;
            width: min(290px, 100%);
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.62);
            box-shadow: 0 20px 45px rgba(59, 91, 219, 0.10);
        }

        .illustration svg {
            width: 78%;
            height: 78%;
        }

        .art-copy {
            position: relative;
            z-index: 1;
            max-width: 330px;
            margin-top: 30px;
            text-align: center;
        }

        .art-copy h2 {
            margin-bottom: 10px;
            color: #253b91;
            font-size: 1.45rem;
            letter-spacing: -0.4px;
        }

        .art-copy p {
            color: #586aab;
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .art-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 19px;
            padding: 8px 13px;
            border: 1px solid rgba(59, 91, 219, 0.12);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            color: #304bc0;
            font-size: 0.78rem;
            font-weight: 650;
        }

        .art-tag span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2f9e62;
        }

        /* DARK MODE COLORS */

        body.dark {
            --bg: #101827;
            --surface: #1b2638;
            --border: #37445a;
            --text: #f1f5f9;
            --muted: #a7b3c6;
            --primary: #5275f5;
            --primary-soft: #26365c;
            --error-bg: #451f27;
            --error-text: #fecaca;
        }

        body.dark .login-art {
            background:
                radial-gradient(circle at 85% 15%, rgba(82,117,245,.13), transparent 28%),
                linear-gradient(145deg, #202d49, #26365c, #1b2638);
        }

        body.dark .art-circle {
            border-color: rgba(165, 180, 252, 0.15);
        }

        body.dark .art-copy h2 {
            color: #dbe5ff;
        }

        body.dark .art-copy p {
            color: #c1cfff;
        }

        body.dark .art-tag {
            background: #1b2638;
            color: #dbe5ff;
            border-color: #37445a;
        }

        body.dark .illustration {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
        }

        body.dark .input::placeholder {
            color: #a7b3c6;
        }

        body.dark .btn:hover {
            background: #6685ff;
        }

        @media (max-width: 760px) {
            .login-page {
                padding: 18px;
            }

            .login-card {
                max-width: 480px;
                min-height: auto;
                grid-template-columns: 1fr;
            }

            .login-form {
                padding: 36px 28px;
            }

            .brand {
                margin-bottom: 30px;
            }

            .login-art {
                display: none;
            }
        }

        @media (max-width: 380px) {
            .login-form {
                padding: 28px 20px;
            }

            .login-form h1 {
                font-size: 1.65rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="login-page">
        <main class="login-card">

            <section class="login-form">

                <div class="theme-row">
                    <button
                        type="button"
                        class="theme-button"
                        id="themeToggle"
                    >🌙 Dark Mode</button>
                </div>

                <div class="brand">
                    <div class="brand-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                            <path d="M8 8h8M8 12h8M8 16h4"></path>
                            <path d="m15 16 1.5 1.5L20 14"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="brand-name">School Attendance</div>
                        <div class="brand-caption">Attendance Management System</div>
                    </div>
                </div>

                <h1>Welcome back!</h1>
                <p class="subtitle">Log in to continue to your account.</p>

                <?php if (!empty($error)): ?>
                    <div class="error" role="alert">
                        <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form action="<?= site_url('login') ?>" method="POST">
                    <div class="field">
                        <label for="email">Email Address</label>
                        <input
                            class="input"
                            type="email"
                            id="email"
                            name="email"
                            autocomplete="username"
                            placeholder="Enter your email address"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <input
                                class="input password-input"
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                required
                            >
                            <button
                                type="button"
                                class="toggle-pass"
                                id="togglePassword"
                                aria-controls="password"
                                aria-label="Show password"
                            >Show</button>
                        </div>
                    </div>

                    <button class="btn" type="submit">Sign In</button>
                </form>

                <div class="footer">
                    &copy; <?= date('Y') ?> School Attendance System
                </div>
            </section>

            <aside class="login-art" aria-label="School attendance illustration">
                <div class="art-circle one"></div>
                <div class="art-circle two"></div>

                <div class="illustration">
                    <svg viewBox="0 0 280 280" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="67" y="43" width="146" height="190" rx="17" fill="#FFFFFF" stroke="#A8B9FF" stroke-width="3"/>
                        <rect x="105" y="31" width="70" height="27" rx="9" fill="#3B5BDB"/>
                        <rect x="119" y="39" width="42" height="10" rx="5" fill="#FFFFFF" opacity=".95"/>

                        <rect x="88" y="83" width="22" height="22" rx="6" fill="#E3F6EC"/>
                        <path d="M94 94L99 99L105 90" stroke="#2F9E62" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="121" y="87" width="64" height="7" rx="3.5" fill="#C8D2EE"/>
                        <rect x="121" y="98" width="43" height="5" rx="2.5" fill="#E1E6F3"/>

                        <rect x="88" y="125" width="22" height="22" rx="6" fill="#E3F6EC"/>
                        <path d="M94 136L99 141L105 132" stroke="#2F9E62" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="121" y="129" width="72" height="7" rx="3.5" fill="#C8D2EE"/>
                        <rect x="121" y="140" width="49" height="5" rx="2.5" fill="#E1E6F3"/>

                        <rect x="88" y="167" width="22" height="22" rx="6" fill="#FFF4D9"/>
                        <circle cx="99" cy="178" r="5" stroke="#E8A317" stroke-width="2.5"/>
                        <rect x="121" y="171" width="61" height="7" rx="3.5" fill="#C8D2EE"/>
                        <rect x="121" y="182" width="39" height="5" rx="2.5" fill="#E1E6F3"/>

                        <circle cx="196" cy="194" r="29" fill="#3B5BDB"/>
                        <circle cx="196" cy="194" r="21" stroke="#FFFFFF" stroke-width="1.5" opacity=".35"/>
                        <path d="M184 194L192 202L209 185" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>

                        <path d="M51 84L55 95L66 99L55 103L51 114L47 103L36 99L47 95L51 84Z" fill="#8DA3FF"/>
                        <path d="M218 94L221 102L229 105L221 108L218 116L215 108L207 105L215 102L218 94Z" fill="#8DA3FF"/>
                        <circle cx="61" cy="177" r="5" fill="#A8B9FF"/>
                        <circle cx="225" cy="145" r="4" fill="#A8B9FF"/>
                    </svg>
                </div>

                <div class="art-copy">
                    <h2>Attendance made simple.</h2>
                    <p>Keep track of student attendance and stay organized in one convenient system.</p>
                    <div class="art-tag"><span></span> Organized. Accurate. Efficient.</div>
                </div>
            </aside>

        </main>
    </div>

    <script>
        /* SHOW / HIDE PASSWORD */

        const password = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function () {
            const isPasswordHidden = password.type === 'password';

            password.type = isPasswordHidden ? 'text' : 'password';
            togglePassword.textContent = isPasswordHidden ? 'Hide' : 'Show';

            togglePassword.setAttribute(
                'aria-label',
                isPasswordHidden ? 'Hide password' : 'Show password'
            );
        });

        /* DARK MODE / LIGHT MODE */

        const themeToggle = document.getElementById('themeToggle');

        try {
            if (localStorage.getItem('attendance-theme') === 'dark') {
                document.body.classList.add('dark');
            }
        } catch (error) {}

        function updateThemeButton() {
            themeToggle.textContent =
                document.body.classList.contains('dark')
                    ? '☀️ Light Mode'
                    : '🌙 Dark Mode';
        }

        updateThemeButton();

        themeToggle.addEventListener('click', function () {
            document.body.classList.toggle('dark');

            try {
                localStorage.setItem(
                    'attendance-theme',
                    document.body.classList.contains('dark')
                        ? 'dark'
                        : 'light'
                );
            } catch (error) {}

            updateThemeButton();
        });
    </script>
</body>
</html>