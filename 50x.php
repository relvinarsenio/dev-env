<?php
/**
 * SERVER ERROR (50x) - Cognify Executive Theme
 */
$status = $_SERVER['REDIRECT_STATUS'] ?? 500;
if (intval($status) < 400) { $status = 500; }

$codes = [
    500 => ['INTERNAL SERVER ERROR', 'The system encountered an unexpected internal server failure.'],
    502 => ['BAD GATEWAY', 'Upstream gateway server sent an invalid or corrupt response.'],
    503 => ['SERVICE UNAVAILABLE', 'System is temporarily under maintenance or overloaded.'],
    504 => ['GATEWAY TIMEOUT', 'Upstream gateway server failed to respond within time limit.'],
];
if (!array_key_exists($status, $codes)) { $status = 500; $codes[500][0] = 'UNKNOWN SERVER ERROR'; }
$title = $codes[$status][0];
$desc  = $codes[$status][1];
http_response_code($status);
$serverTime = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $status; ?> :: <?php echo $title; ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cdefs%3E%3ClinearGradient id='favGrad' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' stop-color='%2300f2fe'/%3E%3Cstop offset='100%25' stop-color='%234facfe'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='32' height='32' rx='9' fill='%230f1117'/%3E%3Cpath d='M16 5L26.3923 11V23L16 29L5.6077 23V11L16 5Z' stroke='url(%23favGrad)' stroke-width='2.5' fill='none' stroke-linejoin='round'/%3E%3Cpath d='M16 11L21.5 14.175V20.5L16 23.675L10.5 20.5V14.175L16 11Z' fill='url(%23favGrad)' opacity='0.3'/%3E%3Ccircle cx='16' cy='17' r='2.5' fill='%23ffffff'/%3E%3C/svg%3E" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --page-bg: #101118;
            --card-bg: #171922;
            --card-border: rgba(255, 255, 255, 0.08);
            --subtle-bg: rgba(255, 255, 255, 0.04);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-orange: #f97316;
            --brand-blue: #38bdf8;
            --radius-card: 24px;
            --radius-pill: 9999px;
            --shadow-card: 0 20px 40px rgba(0, 0, 0, 0.35);
        }

        .theme-light {
            --page-bg: #f3f6fc;
            --card-bg: #ffffff;
            --card-border: rgba(0, 0, 0, 0.06);
            --subtle-bg: rgba(0, 0, 0, 0.03);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --accent-orange: #ea580c;
            --brand-blue: #0284c7;
            --shadow-card: 0 16px 36px rgba(0, 0, 0, 0.06);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-sans);
            background-color: var(--page-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        .error-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-card);
            padding: 40px;
            width: 100%;
            max-width: 540px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            gap: 24px;
            position: relative;
            overflow: hidden;
        }

        .error-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f97316, #ef4444);
        }

        .brand-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-primary);
            text-decoration: none;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            font-size: 0.75rem;
            font-weight: 700;
            background: rgba(249, 115, 22, 0.12);
            color: var(--accent-orange);
            font-family: var(--font-mono);
        }

        .error-body {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .error-code {
            font-family: var(--font-mono);
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #f97316 0%, #ef4444 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -3px;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .error-desc {
            font-size: 0.92rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .debug-panel {
            background: var(--subtle-bg);
            border-radius: 14px;
            padding: 16px;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .debug-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .debug-key { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }
        .debug-val { color: var(--text-primary); font-weight: 600; word-break: break-all; }

        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
        }

        .btn-pill {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: var(--radius-pill);
            font-family: var(--font-sans);
            font-size: 0.88rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--text-primary);
            color: var(--page-bg);
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--subtle-bg);
            color: var(--text-primary);
            border: 1px solid var(--card-border);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.08);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="brand-header-row">
            <a href="/" class="brand-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="24" height="24" rx="7" fill="#38BDF8"/>
                <path d="M7 12L10.5 15.5L17 8.5" stroke="#101118" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                DevEnv Studio
            </a>
            <span class="badge-pill"><?php echo $status; ?> SERVER_ERROR</span>
        </div>

        <div class="error-body">
            <div class="error-code"><?php echo $status; ?></div>
            <h1 class="error-title"><?php echo $title; ?></h1>
            <p class="error-desc"><?php echo $desc; ?></p>
        </div>

        <div class="debug-panel">
            <div class="debug-row">
                <span class="debug-key">Status State</span>
                <span class="debug-val" style="color: var(--accent-orange);">HTTP <?php echo $status; ?></span>
            </div>
            <div class="debug-row">
                <span class="debug-key">Timestamp</span>
                <span class="debug-val"><?php echo $serverTime; ?></span>
            </div>
        </div>

        <div class="btn-group">
            <a href="/" class="btn-pill btn-primary">← Dashboard</a>
            <button onclick="window.location.reload()" class="btn-pill btn-secondary">↻ Retry</button>
        </div>
    </div>

    <script>
        const savedTheme = localStorage.getItem('dev_env_theme') || 'dark';
        if (savedTheme === 'light') {
            document.documentElement.classList.add('theme-light');
        }
    </script>
</body>
</html>
