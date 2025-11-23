<?php
/**
 * 404 NOT FOUND - GOLDEN COPY
 * Theme: Red/Danger
 * 
 * The contents of this file are subject to the terms of the Common Development and
 * Distribution License (the "License"). You may not use this file except in
 * compliance with the License.
 *
 * You can obtain a copy of the License at http://www.opensource.org/licenses/cddl1.php
 * See the License for the specific language governing permissions and limitations
 * under the License.
 */
http_response_code(404);
$requestedUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
$serverTime   = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 :: NOT_FOUND</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23111113'/%3E%3Cpath d='M16 4h12v24h-12z' fill='%23111113'/%3E%3Crect x='0' y='0' width='32' height='32' rx='6' stroke='%2327272a' stroke-width='2' fill='none'/%3E%3Cpath d='M8 10l8 6-8 6' stroke='%2306b6d4' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cline x1='18' y1='22' x2='26' y2='22' stroke='%2306b6d4' stroke-width='3' stroke-linecap='round'/%3E%3C/svg%3E" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #09090b; --bg-panel: #111113; --border-col: #27272a;
            --text-main: #e4e4e7; --text-muted: #71717a;
            --danger: #ef4444; --btn-hover: rgba(239, 68, 68, 0.1);
            --font-mono: 'JetBrains Mono', monospace; --font-sans: 'Inter', sans-serif;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg-body: #f4f4f5; --bg-panel: #ffffff; --border-col: #d4d4d8;
                --text-main: #18181b; --text-muted: #52525b;
                --danger: #dc2626; --btn-hover: rgba(220, 38, 38, 0.08);
            }
        }
        *, *::before, *::after { box-sizing: border-box; transition: all 0.3s ease; }
        body {
            margin: 0; background-color: var(--bg-body); color: var(--text-main); font-family: var(--font-sans);
            background-image: radial-gradient(var(--border-col) 1px, transparent 1px); background-size: 32px 32px;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .error-card {
            background: var(--bg-panel); border: 1px solid var(--danger); width: 100%; max-width: 550px; padding: 2.5rem; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .error-card::before { content: ''; position: absolute; top: -1px; left: -1px; width: 8px; height: 8px; border-top: 2px solid var(--danger); border-left: 2px solid var(--danger); }
        .error-card::after { content: ''; position: absolute; bottom: -1px; right: -1px; width: 8px; height: 8px; border-bottom: 2px solid var(--danger); border-right: 2px solid var(--danger); }
        
        .error-header { margin-bottom: 2rem; }
        .error-code { font-family: var(--font-mono); font-size: 4.5rem; font-weight: 700; color: var(--danger); line-height: 1; margin-bottom: 0.5rem; letter-spacing: -2px; }
        .error-sub { font-family: var(--font-mono); font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 0.8rem; }
        .error-sub::after { content: ''; flex: 1; height: 1px; background: var(--border-col); opacity: 0.5; }
        .error-msg { color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem; font-size: 0.95rem; }
        
        .debug-panel { background: rgba(0,0,0,0.03); border: 1px dashed var(--border-col); padding: 1rem; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted); margin-bottom: 2rem; }
        .debug-row { display: flex; justify-content: space-between; margin-bottom: 0.4rem; }
        .debug-val { color: var(--danger); word-break: break-all; text-align: right; }
        
        .btn-group { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn { display: flex; align-items: center; justify-content: center; padding: 1rem; background: transparent; border: 1px solid var(--border-col); color: var(--text-main); text-decoration: none; font-family: var(--font-mono); font-size: 0.85rem; text-transform: uppercase; cursor: pointer; transition: all 0.2s; letter-spacing: 0.5px; }
        .btn:hover { border-color: var(--danger); color: var(--danger); background: var(--btn-hover); font-weight: bold; }
        
        .blink { animation: blinker 1.5s linear infinite; } @keyframes blinker { 50% { opacity: 0; } }
        @media (max-width: 500px) { .btn-group { grid-template-columns: 1fr; } .error-code { font-size: 3.5rem; } }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-header">
            <div class="error-code">404</div>
            <div class="error-sub"><span class="blink">■</span> PATH_UNREACHABLE</div>
        </div>
        <p class="error-msg">The requested resource could not be located on this server. The signal was lost in the void.</p>
        <div class="debug-panel">
            <div class="debug-row"><span>STATUS:</span><span class="debug-val">MISSING</span></div>
            <div class="debug-row"><span>TIME:</span><span><?php echo $serverTime; ?></span></div>
            <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--border-col);"><span style="display:block; margin-bottom:0.25rem;">REQUESTED_URI:</span><span class="debug-val"><?php echo $requestedUri; ?></span></div>
        </div>
        <div class="btn-group">
            <a href="/" class="btn">Return to Base</a>
            <button onclick="window.location.reload()" class="btn">Retry Signal</button>
        </div>
    </div>
</body>
</html>
