<?php
/**
 * DEV_ENV Dashboard - Cognify Executive Seamless Unified Edition
 * Localhost homepage replacement for Nginx / LEMP / LAMP Stack.
 * 100% Official Microsoft Fluent System COLOR Icons (sourced from local clone)
 * 
 * License: CDDL 1.0 (Common Development and Distribution License)
 */

declare(strict_types=1);

// Standalone phpinfo iframe view
if (isset($_GET['action']) && $_GET['action'] === 'phpinfo') {
    phpinfo();
    exit;
}

// Configurable Ignored Directories & Files
$ignored = [
    '.', '..', '.git', 'git', 'html', 'images', 'private_folder',
    'screenshots', 'index.php', '404.php', '403.php', '50x.php',
    'README.md', 'favicon.ico', 'node_modules', '.vscode', '.idea',
    'icons', 'phpmyadmin', 'scratch'
];

// Priority PHP Extensions
$priorityExtensions = ['mysqli', 'pdo_mysql', 'curl', 'gd', 'redis', 'imagick', 'mbstring', 'openssl', 'json', 'xml', 'zlib', 'intl', 'zip', 'opcache'];

/**
 * Get OS Pretty Name
 */
function getOsPrettyName(): string {
    if (@file_exists('/etc/os-release') && @is_readable('/etc/os-release')) {
        $content = @file_get_contents('/etc/os-release');
        if ($content && preg_match('/PRETTY_NAME="?([^"\n]+)"?/', $content, $m)) {
            return $m[1];
        }
    }
    return php_uname('s') . ' ' . php_uname('r');
}

/**
 * Get System Uptime
 */
function getSystemUptime(): string {
    if (!@file_exists('/proc/uptime') || !@is_readable('/proc/uptime')) {
        return 'N/A';
    }
    $content = @file_get_contents('/proc/uptime');
    if ($content === false) return 'N/A';
    $uptimeSec = (int)floatval(explode(' ', trim($content))[0]);
    
    $days  = intdiv($uptimeSec, 86400);
    $hours = intdiv($uptimeSec % 86400, 3600);
    $mins  = intdiv($uptimeSec % 3600, 60);

    $parts = [];
    if ($days > 0) $parts[] = "{$days}d";
    if ($hours > 0 || $days > 0) $parts[] = "{$hours}h";
    $parts[] = "{$mins}m";
    return implode(' ', $parts);
}

/**
 * Get System Memory Statistics
 */
function getSystemMemory(): array {
    $memory = ['total' => 0, 'free' => 0, 'used' => 0, 'cached' => 0, 'percent' => 0, 'swap_total' => 0, 'swap_free' => 0, 'php_limit' => ini_get('memory_limit') ?: 'N/A', 'available' => true];

    if (!@file_exists('/proc/meminfo') || !@is_readable('/proc/meminfo')) {
        $memory['available'] = false;
        return $memory;
    }

    $content = @file_get_contents('/proc/meminfo');
    if ($content === false) {
        $memory['available'] = false;
        return $memory;
    }

    $memData = [];
    foreach (explode("\n", $content) as $line) {
        if (empty($line)) continue;
        $parts = explode(':', $line);
        if (count($parts) === 2) {
            $memData[trim($parts[0])] = (int) preg_replace('/\D/', '', $parts[1]);
        }
    }

    $total = $memData['MemTotal'] ?? 0;
    $available = $memData['MemAvailable'] ?? ($memData['MemFree'] + ($memData['Buffers'] ?? 0) + ($memData['Cached'] ?? 0));
    $cached = ($memData['Cached'] ?? 0) + ($memData['Buffers'] ?? 0);

    if ($total > 0) {
        $used = $total - $available;
        $memory['total'] = round($total / 1024 / 1024, 2);
        $memory['free']  = round($available / 1024 / 1024, 2);
        $memory['used']  = round($used / 1024 / 1024, 2);
        $memory['cached'] = round($cached / 1024 / 1024, 2);
        $memory['percent'] = round(($used / $total) * 100, 1);
        $memory['swap_total'] = round(($memData['SwapTotal'] ?? 0) / 1024 / 1024, 2);
        $memory['swap_free']  = round(($memData['SwapFree'] ?? 0) / 1024 / 1024, 2);
    } else {
        $memory['available'] = false;
    }

    return $memory;
}

/**
 * Get System CPU Statistics
 */
function getSystemCpu(): array {
    $cpu = [
        'percent' => 0.0,
        'cores' => 1,
        'load_1m' => '0.00',
        'load_5m' => '0.00',
        'load_15m' => '0.00',
        'available' => true
    ];

    if (function_exists('sys_getloadavg')) {
        $load = @sys_getloadavg();
        if ($load !== false && isset($load[0])) {
            $cpu['load_1m']  = number_format($load[0], 2);
            $cpu['load_5m']  = number_format($load[1] ?? $load[0], 2);
            $cpu['load_15m'] = number_format($load[2] ?? $load[0], 2);
        }
    }

    if (@file_exists('/proc/cpuinfo') && @is_readable('/proc/cpuinfo')) {
        $cpuInfo = @file_get_contents('/proc/cpuinfo');
        if ($cpuInfo !== false) {
            preg_match_all('/^processor/m', $cpuInfo, $matches);
            $cpu['cores'] = max(1, count($matches[0] ?? []));
        }
    }

    if (!@file_exists('/proc/stat') || !@is_readable('/proc/stat')) {
        if ($cpu['load_1m'] !== '0.00') {
            $cpu['percent'] = min(100.0, round(((float)$cpu['load_1m'] / $cpu['cores']) * 100, 1));
        } else {
            $cpu['available'] = false;
        }
        return $cpu;
    }

    $statLine = @file_get_contents('/proc/stat');
    if ($statLine === false) {
        $cpu['available'] = false;
        return $cpu;
    }

    $lines = explode("\n", $statLine);
    $elems = preg_split('/\s+/', trim($lines[0] ?? ''));

    if (count($elems) >= 5 && $elems[0] === 'cpu') {
        $user = (int)$elems[1];
        $nice = (int)$elems[2];
        $sys  = (int)$elems[3];
        $idle = (int)$elems[4];
        $total = $user + $nice + $sys + $idle;
        $busy  = $user + $nice + $sys;

        if ($total > 0) {
            $cpu['percent'] = round(($busy / $total) * 100, 1);
        }
    } else {
        $cpu['available'] = false;
    }

    return $cpu;
}

/**
 * Get Storage Statistics
 */
function getSystemDisk(): array {
    $path = __DIR__;
    $total = @disk_total_space($path);
    $free  = @disk_free_space($path);

    if ($total === false || $free === false || $total <= 0) {
        return ['available' => false, 'total' => 0, 'free' => 0, 'used' => 0, 'percent' => 0];
    }

    $used = $total - $free;
    return [
        'available' => true,
        'total' => round($total / (1024 ** 3), 2),
        'free'  => round($free / (1024 ** 3), 2),
        'used'  => round($used / (1024 ** 3), 2),
        'percent' => round(($used / $total) * 100, 1)
    ];
}

/**
 * Probe Database Service Status
 */
function probeDatabaseStatus(): array {
    $result = ['connected' => false, 'driver' => 'None', 'version' => 'N/A', 'message' => 'Not Connected'];

    if (extension_loaded('mysqli')) {
        $result['driver'] = 'MySQLi';
        $host = ini_get('mysqli.default_host') ?: '127.0.0.1';
        $user = ini_get('mysqli.default_user') ?: 'root';
        $pass = ini_get('mysqli.default_pw')   ?: '';
        $port = (int)(ini_get('mysqli.default_port') ?: 3306);

        mysqli_report(MYSQLI_REPORT_OFF);
        $link = @mysqli_connect($host, $user, $pass, '', $port);
        if ($link) {
            $result['connected'] = true;
            $result['version'] = mysqli_get_server_info($link);
            $result['message'] = 'Active & Ready';
            mysqli_close($link);
            return $result;
        } else {
            $result['message'] = mysqli_connect_error() ?: 'Connection Refused';
        }
    }

    if (!$result['connected'] && extension_loaded('pdo_mysql')) {
        $result['driver'] = 'PDO_MySQL';
        try {
            $host = '127.0.0.1';
            $port = 3306;
            $pdo = new PDO("mysql:host={$host};port={$port}", 'root', '', [
                PDO::ATTR_TIMEOUT => 1,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
            ]);
            $result['connected'] = true;
            $result['version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $result['message'] = 'Active & Ready';
            return $result;
        } catch (Throwable $e) {
            $result['message'] = 'Offline / Credentials Required';
        }
    }

    return $result;
}

/**
 * Check phpMyAdmin Existence
 */
function checkPhpMyAdmin(): array {
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'phpmyadmin';
    $exists = is_dir($path);
    return [
        'available' => $exists,
        'url' => $exists ? './phpmyadmin/' : '/phpmyadmin/'
    ];
}

/**
 * Scan Root Directory for Projects
 */
function scanProjects(string $rootPath, array $ignored): array {
    $projects = [];
    if (!is_dir($rootPath)) return $projects;

    $items = @scandir($rootPath);
    if ($items === false) return $projects;

    foreach ($items as $item) {
        if (in_array($item, $ignored, true)) continue;
        if (str_starts_with($item, '.')) continue;

        $fullPath = $rootPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            $mtime = filemtime($fullPath);
            $hasPublic = is_dir($fullPath . DIRECTORY_SEPARATOR . 'public');
            $hasIndexHtml = file_exists($fullPath . DIRECTORY_SEPARATOR . 'index.html');
            $hasIndexPhp  = file_exists($fullPath . DIRECTORY_SEPARATOR . 'index.php');

            $entryUrl = './' . urlencode($item) . '/';
            if ($hasPublic) {
                $entryUrl = './' . urlencode($item) . '/public/';
            }

            $childCount = 0;
            $subItems = @scandir($fullPath);
            if ($subItems !== false) {
                $childCount = max(0, count($subItems) - 2);
            }

            $projects[] = [
                'name'         => $item,
                'path'         => $entryUrl,
                'mtime'        => $mtime,
                'mtime_formatted' => $mtime ? date('M j, Y H:i', $mtime) : 'Unknown',
                'has_public'   => $hasPublic,
                'has_index'    => ($hasIndexHtml || $hasIndexPhp),
                'child_count'  => $childCount
            ];
        }
    }

    usort($projects, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $projects;
}

// Standalone Reactive Telemetry JSON Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'telemetry') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode([
        'cpu'    => getSystemCpu(),
        'mem'    => getSystemMemory(),
        'disk'   => getSystemDisk(),
        'uptime' => getSystemUptime(),
        'db'     => probeDatabaseStatus(),
        'timestamp' => date('H:i:s')
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Web Root Directory Location
$webRootRealPath = realpath(__DIR__) ?: __DIR__;

// Fetch Initial Diagnostics
$memStats  = getSystemMemory();
$cpuStats  = getSystemCpu();
$diskStats = getSystemDisk();
$dbStatus  = probeDatabaseStatus();
$pmaStatus = checkPhpMyAdmin();
$projects  = scanProjects(__DIR__, $ignored);

$publicAppCount = 0;
$indexAppCount  = 0;
$latestProject  = count($projects) > 0 ? $projects[0]['name'] : null;

foreach ($projects as $p) {
    if (!empty($p['has_public'])) $publicAppCount++;
    else if (!empty($p['has_index'])) $indexAppCount++;
}

$osName      = getOsPrettyName();
$uptimeStr   = getSystemUptime();
$arch        = php_uname('m');
$sapiName    = php_sapi_name();
$memoryLimit = ini_get('memory_limit') ?: 'N/A';
$uploadMax   = ini_get('upload_max_filesize') ?: 'N/A';
$serverAddr  = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? gethostbyname(gethostname());

// Loaded PHP Extensions
$loadedExtensions = get_loaded_extensions();
natcasesort($loadedExtensions);

$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx / LEMP Stack';
$serverHostName = gethostname() ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
$phpVersion     = PHP_VERSION;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEV_ENV :: Web Workstation</title>
    
    <!-- Favicon SVG (Creative Electric Isometric Prism) -->
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cdefs%3E%3ClinearGradient id='favGrad' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' stop-color='%2300f2fe'/%3E%3Cstop offset='100%25' stop-color='%234facfe'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='32' height='32' rx='9' fill='%230f1117'/%3E%3Cpath d='M16 5L26.3923 11V23L16 29L5.6077 23V11L16 5Z' stroke='url(%23favGrad)' stroke-width='2.5' fill='none' stroke-linejoin='round'/%3E%3Cpath d='M16 11L21.5 14.175V20.5L16 23.675L10.5 20.5V14.175L16 11Z' fill='url(%23favGrad)' opacity='0.3'/%3E%3Ccircle cx='16' cy='17' r='2.5' fill='%23ffffff'/%3E%3C/svg%3E" type="image/svg+xml">
    
    <!-- Google Fonts: Inter & Cascadia Code -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cascadia+Code:wght@400;600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ==========================================================================
           Seamless Unified Cognify Design System Tokens
           ========================================================================== */
        :root {
            --page-bg: #f3f6fc;
            --card-bg: #ffffff;
            --card-hover: #f8fafc;
            --subtle-bg: #eaeff6;
            
            --pill-bg: #eaeff6;
            --pill-active-bg: #0f172a;
            --pill-active-text: #ffffff;

            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;

            --brand-blue: #0f6cbd;
            --brand-light: rgba(15, 108, 189, 0.08);

            --badge-green-bg: #e6f7ed;
            --badge-green-fg: #107c41;
            --badge-blue-bg: #e0f2fe;
            --badge-blue-fg: #0284c7;
            --badge-purple-bg: #f3e8ff;
            --badge-purple-fg: #9333ea;

            --radius-card: 20px;
            --radius-pill: 9999px;
            --radius-sm: 10px;

            --shadow-card: 0 4px 16px rgba(0, 0, 0, 0.04);
            --shadow-float: 0 12px 32px rgba(0, 0, 0, 0.08);

            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'Cascadia Code', 'Consolas', monospace;
        }

        [data-theme="dark"] {
            --page-bg: #101118;
            --card-bg: #1a1c27;
            --card-hover: #222534;
            --subtle-bg: #222534;
            
            --pill-bg: #222534;
            --pill-active-bg: #479ef5;
            --pill-active-text: #0f1015;

            --text-primary: #f8fafc;
            --text-secondary: #a0aec0;
            --text-muted: #64748b;

            --brand-blue: #479ef5;
            --brand-light: rgba(71, 158, 245, 0.12);

            --badge-green-bg: rgba(54, 192, 115, 0.16);
            --badge-green-fg: #36c073;
            --badge-blue-bg: rgba(56, 189, 248, 0.16);
            --badge-blue-fg: #38bdf8;
            --badge-purple-bg: rgba(192, 132, 252, 0.16);
            --badge-purple-fg: #c084fc;

            --shadow-card: 0 6px 20px rgba(0, 0, 0, 0.35);
            --shadow-float: 0 16px 40px rgba(0, 0, 0, 0.5);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            transition: background-color 0.25s ease, color 0.2s ease, transform 0.25s ease, box-shadow 0.25s ease;
        }

        @keyframes pageEntrance {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--page-bg);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px 32px;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 18px;
            animation: pageEntrance 0.35s cubic-bezier(0.16, 1, 0.3, 1) ease-out;
            -webkit-font-smoothing: antialiased;
        }

        /* --------------------------------------------------------------------------
           1. Top Navigation Bar (Seamless Cognify Navigation)
           -------------------------------------------------------------------------- */
        .nav-header-row {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            width: 100%;
        }

        .brand-logo-area {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }

        .brand-icon-bubble {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--page-bg);
            flex-shrink: 0;
            box-shadow: var(--shadow-card);
        }

        .brand-icon-bubble svg {
            width: 24px;
            height: 24px;
        }

        .brand-title-text {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text-primary);
        }

        /* Center Nav Pill Tabs */
        .nav-pills-group {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--card-bg);
            padding: 6px;
            border-radius: var(--radius-pill);
            box-shadow: var(--shadow-card);
            z-index: 2;
        }

        .nav-pill-btn {
            padding: 9px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: var(--radius-pill);
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .nav-pill-btn:hover {
            color: var(--text-primary);
        }

        .nav-pill-btn.active {
            background: var(--pill-active-bg);
            color: var(--pill-active-text);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Right User / Action Controls */
        .nav-controls-right {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 2;
        }

        .icon-btn-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--card-bg);
            border: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-card);
        }

        .icon-btn-circle:hover {
            background: var(--card-hover);
            transform: scale(1.05);
        }

        .icon-btn-circle .icon-sun,
        .icon-btn-circle .icon-moon { display: flex; align-items: center; justify-content: center; }

        [data-theme="dark"] .themeToggleBtn .icon-sun { display: none !important; }
        [data-theme="dark"] .themeToggleBtn .icon-moon { display: flex !important; }

        [data-theme="light"] .themeToggleBtn .icon-sun { display: flex !important; }
        [data-theme="light"] .themeToggleBtn .icon-moon { display: none !important; }

        /* --------------------------------------------------------------------------
           2. Hero Overview Header Row
           -------------------------------------------------------------------------- */
        .hero-overview-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .hero-overview-row h1 {
            font-size: 1.95rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text-primary);
        }

        .web-root-action-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--pill-active-bg);
            color: var(--pill-active-text);
            padding: 10px 22px;
            border-radius: var(--radius-pill);
            font-size: 0.85rem;
            font-weight: 700;
            font-family: var(--font-mono);
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
        }

        .web-root-action-pill:hover {
            transform: translateY(-2px);
        }

        /* --------------------------------------------------------------------------
           3. Top 3 KPI Summary Cards Grid (Clean Uniform Cognify Style)
           -------------------------------------------------------------------------- */
        .kpi-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .kpi-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 22px 26px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
            min-height: 155px;
        }

        .kpi-card:hover {
            background: var(--card-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-float);
        }

        .kpi-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .kpi-head-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .kpi-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .badge-green { background: var(--badge-green-bg); color: var(--badge-green-fg); }
        .badge-blue  { background: var(--badge-blue-bg); color: var(--badge-blue-fg); }
        .badge-purple { background: var(--badge-purple-bg); color: var(--badge-purple-fg); }

        .kpi-icon-badge svg { width: 20px; height: 20px; }

        .kpi-card-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .kpi-trend-pill {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .trend-up { background: var(--badge-green-bg); color: var(--badge-green-fg); }
        .trend-blue { background: var(--badge-blue-bg); color: var(--badge-blue-fg); }
        .trend-purple { background: var(--badge-purple-bg); color: var(--badge-purple-fg); }

        .kpi-stat-value {
            font-size: 2.1rem;
            font-weight: 800;
            font-family: var(--font-mono);
            color: var(--text-primary);
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .kpi-mini-chips-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .kpi-mini-chip {
            font-size: 0.74rem;
            font-family: var(--font-mono);
            font-weight: 600;
            padding: 3px 9px;
            border-radius: var(--radius-pill);
            background: var(--subtle-bg);
            color: var(--text-secondary);
        }

        .kpi-progress-track {
            height: 6px;
            background: var(--subtle-bg);
            border-radius: var(--radius-pill);
            overflow: hidden;
            width: 100%;
        }

        .kpi-progress-fill {
            height: 100%;
            border-radius: var(--radius-pill);
            transition: width 0.5s ease;
        }

        .fill-green { background: linear-gradient(90deg, #107c41, #36c073); }
        .fill-blue  { background: linear-gradient(90deg, #0f6cbd, #38bdf8); }
        .fill-purple { background: linear-gradient(90deg, #9333ea, #c084fc); }

        .kpi-sub-text {
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .kpi-sub-val {
            font-family: var(--font-mono);
            font-weight: 700;
            color: var(--text-primary);
        }

        /* --------------------------------------------------------------------------
           4. Main Bottom Studio Grid (2-Column Split)
           -------------------------------------------------------------------------- */
        .bottom-studio-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 18px;
        }

        .studio-card-panel {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .panel-head-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .panel-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box-pill {
            position: relative;
            max-width: 320px;
            width: 100%;
        }

        .search-box-pill input {
            width: 100%;
            padding: 9px 16px 9px 40px;
            min-height: 40px;
            font-family: var(--font-sans);
            font-size: 0.85rem;
            background: var(--subtle-bg);
            border: none;
            border-radius: var(--radius-pill);
            color: var(--text-primary);
            outline: none;
        }

        .search-box-pill input:focus {
            box-shadow: 0 0 0 2px var(--brand-blue);
        }

        .search-icon-svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }

        /* Projects Grid */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .project-card {
            background: var(--subtle-bg);
            border-radius: var(--radius-sm);
            padding: 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-decoration: none;
            color: inherit;
            min-height: 120px;
        }

        .project-card:hover {
            background: var(--card-hover);
            transform: translateY(-3px);
            box-shadow: var(--shadow-float);
        }

        .project-card-top {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }

        .folder-svg-wrap {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .project-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
            word-break: break-word;
        }

        .project-date {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .project-card-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .type-tag {
            font-size: 0.72rem;
            font-family: var(--font-mono);
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            background: var(--card-bg);
            color: var(--text-secondary);
        }

        .launch-link {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--brand-blue);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .empty-projects-view {
            grid-column: 1 / -1;
            padding: 48px;
            text-align: center;
            background: var(--subtle-bg);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
        }

        /* Right Column: Environment & System Specs */
        .spec-list-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .spec-item-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--subtle-bg);
            border-radius: var(--radius-sm);
            font-size: 0.86rem;
        }

        .spec-item-key { color: var(--text-secondary); }
        .spec-item-val { font-family: var(--font-mono); font-weight: 700; color: var(--text-primary); }

        .progress-bar-track {
            height: 6px;
            background: var(--subtle-bg);
            border-radius: var(--radius-pill);
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: var(--radius-pill);
            background: linear-gradient(90deg, var(--brand-blue), #36dff1);
            transition: width 0.5s ease;
        }

        /* --------------------------------------------------------------------------
           5. Dialog Modals
           -------------------------------------------------------------------------- */
        .modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            padding: 20px;
            transition: opacity 0.25s ease;
        }

        .modal-backdrop.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-window {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            width: 100%;
            max-width: 800px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-float);
            overflow: hidden;
        }

        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--subtle-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .modal-iframe {
            width: 100%;
            height: 100%;
            min-height: 550px;
            border: none;
            background: #ffffff;
        }

        .ext-search-input {
            width: 100%;
            padding: 10px 16px;
            font-family: var(--font-sans);
            font-size: 0.875rem;
            background: var(--subtle-bg);
            border: none;
            border-radius: var(--radius-pill);
            color: var(--text-primary);
            outline: none;
        }

        .ext-pill-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            max-height: 380px;
            overflow-y: auto;
        }

        .ext-pill {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            background: var(--subtle-bg);
            color: var(--text-secondary);
        }

        .ext-pill.active {
            background: var(--brand-light);
            color: var(--brand-blue);
            font-weight: 700;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .nav-header-row {
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-start;
                gap: 14px;
                min-height: auto;
            }

            .brand-logo-area {
                position: static;
                transform: none;
            }

            .nav-controls-right {
                position: absolute;
                top: 0;
                right: 0;
                transform: none;
            }

            .nav-pills-group {
                width: 100%;
                overflow-x: auto;
                white-space: nowrap;
                scrollbar-width: none;
                -ms-overflow-style: none;
                padding: 6px;
                justify-content: flex-start;
            }

            .nav-pills-group::-webkit-scrollbar {
                display: none;
            }

            .nav-pill-btn {
                padding: 8px 16px;
                font-size: 0.82rem;
                flex-shrink: 0;
            }

            .kpi-cards-grid { grid-template-columns: 1fr; }
            .bottom-studio-grid { grid-template-columns: 1fr; }
            body { padding: 16px; gap: 16px; }
        }

        @media (max-width: 600px) {
            .brand-title-text { font-size: 1.2rem; }
            .brand-icon-bubble { width: 36px; height: 36px; }
            .brand-icon-bubble svg { width: 20px; height: 20px; }
            .icon-btn-circle { width: 36px; height: 36px; }
            .nav-pill-btn { padding: 7px 14px; font-size: 0.78rem; }
        }
    </style>
</head>
<body>

    <!-- 1. Top Navigation Bar (Cognify Pill Nav Bar) -->
    <header class="nav-header-row">
        <a href="./" class="brand-logo-area">
            <div class="brand-icon-bubble">
                <!-- Laptop Color SVG -->
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.25391 16L12 15.4688L17.7539 16H17.8187C17.9386 16 18.0575 16.0216 18.1698 16.0637L21.2949 17.2356C21.7181 17.3943 21.9988 17.7983 22 18.25H2C2 17.7989 2.27984 17.3951 2.70225 17.2367L5.83021 16.0637C5.94249 16.0216 6.06142 16 6.18133 16H6.25391Z" fill="url(#nav_laptop_p0)"/>
                <path d="M2.75 17.5C2.33579 17.5 2 17.8358 2 18.25C2 18.6642 2.33579 19 2.75 19H21.25C21.6642 19 22 18.6642 22 18.25C22 17.5 21.6642 17.5 21.25 17.5H2.75Z" fill="url(#nav_laptop_p1)"/>
                <path d="M4 6.25C4 5.00736 5.00736 4 6.25 4H17.75C18.9926 4 20 5.00736 20 6.25V13.75C20 14.9926 18.9926 16 17.75 16H6.25C5.00736 16 4 14.9926 4 13.75V6.25Z" fill="url(#nav_laptop_p2)"/>
                <defs>
                <linearGradient id="nav_laptop_p0" x1="22" y1="18.9122" x2="20.7655" y2="12.2542" gradientUnits="userSpaceOnUse"><stop stop-color="#63686E"/><stop offset="1" stop-color="#889096"/></linearGradient>
                <linearGradient id="nav_laptop_p1" x1="22" y1="18.8929" x2="21.7922" y2="16.1227" gradientUnits="userSpaceOnUse"><stop stop-color="#889096"/><stop offset="1" stop-color="#AAB3BD"/></linearGradient>
                <linearGradient id="nav_laptop_p2" x1="15.2" y1="4" x2="15.9497" y2="17.7217" gradientUnits="userSpaceOnUse"><stop stop-color="#6CE0FF"/><stop offset="1" stop-color="#4894FE"/></linearGradient>
                </defs>
                </svg>
            </div>
            <span class="brand-title-text">DEV_ENV</span>
        </a>

        <!-- Center Navigation Pills -->
        <nav class="nav-pills-group">
            <a href="./" class="nav-pill-btn active">Dashboard</a>
            <a href="<?php echo htmlspecialchars($pmaStatus['url']); ?>" class="nav-pill-btn">phpMyAdmin</a>
            <button onclick="openExtensionsModal()" class="nav-pill-btn">
                PHP Extensions (<?php echo count($loadedExtensions); ?>)
            </button>
            <button onclick="openSystemModal()" class="nav-pill-btn">System Specs</button>
            <button onclick="openPhpInfoModal()" class="nav-pill-btn">PHP Info</button>
        </nav>

        <!-- Right Theme Control -->
        <div class="nav-controls-right">
            <button class="icon-btn-circle themeToggleBtn" title="Toggle Theme (Light / Dark)">
                <span class="icon-sun">
                    <!-- Weather Sunny Low Color SVG -->
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.2929 13.5C17.4278 13.0232 17.5 12.52 17.5 12C17.5 8.96243 15.0376 6.5 12 6.5C8.96243 6.5 6.5 8.96243 6.5 12C6.5 12.52 6.57217 13.0232 6.70706 13.5H17.2929Z" fill="url(#nav_sun_p0)"/>
                    <path d="M2 13.75C2 13.3358 2.33579 13 2.75 13H21.25C21.6642 13 22 13.3358 22 13.75C22 14.1642 21.6642 14.5 21.25 14.5H2.75C2.33579 14.5 2 14.1642 2 13.75Z" fill="url(#nav_sun_p1)"/>
                    <path d="M6 16.75C6 16.3358 6.33579 16 6.75 16H17.25C17.6642 16 18 16.3358 18 16.75C18 17.1642 17.6642 17.5 17.25 17.5H6.75C6.33579 17.5 6 17.1642 6 17.75Z" fill="url(#nav_sun_p2)"/>
                    <path d="M10 19.75C10 19.3358 10.3358 19 10.75 19H13.25C13.6642 19 14 19.3358 14 19.75C14 20.1642 13.6642 20.5 13.25 20.5H10.75C10.3358 20.5 10 20.1642 10 19.75Z" fill="url(#nav_sun_p3)"/>
                    <path d="M12 2C12.4142 2 12.75 2.33579 12.75 2.75V4.5C12.75 4.91421 12.4142 5.25 12 5.25C11.5858 5.25 11.25 4.91421 11.25 4.5V2.75C11.25 2.33579 11.5858 2 12 2Z" fill="url(#nav_sun_p4)"/>
                    <path d="M4.71967 4.71967C5.01256 4.42678 5.48744 4.42678 5.78033 4.71967L7.03033 5.96967C7.32322 6.26256 7.32322 6.73744 7.03033 7.03033C6.73744 7.32322 6.26256 7.32322 5.96967 7.03033L4.71967 5.78033C4.42678 5.48744 4.42678 5.01256 4.71967 4.71967Z" fill="url(#nav_sun_p5)"/>
                    <path d="M18.2197 4.71967C18.5126 4.42678 18.9874 4.42678 19.2803 4.71967C19.5732 5.01256 19.5732 5.48744 19.2803 5.78033L18.0303 7.03033C17.7374 7.32322 17.2626 7.32322 16.9697 7.03033C16.6768 6.73744 16.6768 6.26256 16.9697 5.96967L18.2197 4.71967Z" fill="url(#nav_sun_p6)"/>
                    <defs>
                    <linearGradient id="nav_sun_p0" x1="10.3101" y1="6.11677" x2="14.0635" y2="14.2517" gradientUnits="userSpaceOnUse"><stop stop-color="#FFE580"/><stop offset="0.27282" stop-color="#FFBF70"/><stop offset="0.57" stop-color="#FFA342"/><stop offset="0.96" stop-color="#FA6673"/></linearGradient>
                    <linearGradient id="nav_sun_p1" x1="0.531215" y1="13" x2="22" y2="13" gradientUnits="userSpaceOnUse"><stop offset="0.0606792" stop-color="#0FAFFF"/><stop offset="1" stop-color="#0094F0"/></linearGradient>
                    <linearGradient id="nav_sun_p2" x1="0.531215" y1="13" x2="22" y2="13" gradientUnits="userSpaceOnUse"><stop offset="0.0606792" stop-color="#0FAFFF"/><stop offset="1" stop-color="#0094F0"/></linearGradient>
                    <linearGradient id="nav_sun_p3" x1="0.531215" y1="13" x2="22" y2="13" gradientUnits="userSpaceOnUse"><stop offset="0.0606792" stop-color="#0FAFFF"/><stop offset="1" stop-color="#0094F0"/></linearGradient>
                    <linearGradient id="nav_sun_p4" x1="9.69562" y1="1.71257" x2="12.663" y2="12.6528" gradientUnits="userSpaceOnUse"><stop stop-color="#FFE580"/><stop offset="0.27282" stop-color="#FFBF70"/><stop offset="0.57" stop-color="#FFA342"/><stop offset="0.815371" stop-color="#FA6673"/></linearGradient>
                    <linearGradient id="nav_sun_p5" x1="9.69562" y1="1.71257" x2="12.663" y2="12.6528" gradientUnits="userSpaceOnUse"><stop stop-color="#FFE580"/><stop offset="0.27282" stop-color="#FFBF70"/><stop offset="0.57" stop-color="#FFA342"/><stop offset="0.815371" stop-color="#FA6673"/></linearGradient>
                    <linearGradient id="nav_sun_p6" x1="9.69562" y1="1.71257" x2="12.663" y2="12.6528" gradientUnits="userSpaceOnUse"><stop stop-color="#FFE580"/><stop offset="0.27282" stop-color="#FFBF70"/><stop offset="0.57" stop-color="#FFA342"/><stop offset="0.815371" stop-color="#FA6673"/></linearGradient>
                    </defs>
                    </svg>
                </span>
                <span class="icon-moon">
                    <!-- Weather Moon SVG -->
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20.0258 17.0014C17.2639 21.7851 11.1471 23.4241 6.3634 20.6622C5.06068 19.9101 3.964 18.8926 3.12872 17.6797C2.84945 17.2741 3.0301 16.7141 3.49369 16.5482C7.26112 15.1997 9.27892 13.6372 10.4498 11.4021C11.6825 9.04908 12.001 6.47162 11.1387 2.93862C11.0195 2.45008 11.4053 1.98492 11.9075 2.01186C13.4645 2.09539 14.9856 2.54263 16.3649 3.33903C21.1486 6.10088 22.7876 12.2177 20.0258 17.0014Z" fill="#F0B429"/>
                    </svg>
                </span>
            </button>
        </div>
    </header>

    <!-- 2. Hero Overview Header Row -->
    <div class="hero-overview-row">
        <h1>Dev Workstation Overview</h1>
    </div>

    <!-- 3. Top 3 KPI Summary Cards Row (Clean Uniform Cognify Edition) -->
    <section class="kpi-cards-grid">
        
        <!-- KPI 1: Active Projects Breakdown -->
        <div class="kpi-card">
            <div class="kpi-card-head">
                <div class="kpi-head-left">
                    <div class="kpi-icon-badge badge-green">
                        <!-- Document Folder Color SVG -->
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 6.25C8 5.00736 9.00736 4 10.25 4H17.75C18.9926 4 20 5.00736 20 6.25V14.75C20 15.9926 18.9926 17 17.75 17H10.25C9.00736 17 8 15.9926 8 14.75V6.25Z" fill="url(#kpi_docf_p0)"/>
                        <path d="M4 4.25C4 3.00736 5.00736 2 6.25 2H15.25C16.4926 2 17.5 3.00736 17.5 4.25V14.75C17.5 15.9926 16.4926 17 15.25 17H6.25C5.00736 17 4 15.9926 4 14.75V4.25Z" fill="url(#kpi_docf_p2)"/>
                        <path d="M5.25 8C4.00736 8 3 9.00736 3 10.25V18.75C3 20.5449 4.45507 22 6.25 22H17.75C19.5449 22 21 20.5449 21 18.75V17.25C21 16.0074 19.9926 15 18.75 15H15.9041C15.6956 15 15.4964 14.9132 15.3545 14.7603L9.74468 8.71899C9.31895 8.26051 8.72155 8 8.0959 8H5.25Z" fill="url(#kpi_docf_p3)"/>
                        <defs>
                        <linearGradient id="kpi_docf_p0" x1="21.8" y1="19.5" x2="23.6385" y2="5.77313" gradientUnits="userSpaceOnUse"><stop stop-color="#BB45EA"/><stop offset="1" stop-color="#9C6CFE"/></linearGradient>
                        <radialGradient id="kpi_docf_p2" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(8.05 14) rotate(-52.6548) scale(14.4655 23.3167)"><stop offset="0.228038" stop-color="#2764E7"/><stop offset="0.684836" stop-color="#5CD1FF"/><stop offset="1" stop-color="#6CE0FF"/></radialGradient>
                        <linearGradient id="kpi_docf_p3" x1="6.85714" y1="8" x2="6.85714" y2="27.0909" gradientUnits="userSpaceOnUse"><stop offset="0.240824" stop-color="#FFD638"/><stop offset="0.637257" stop-color="#FAB500"/><stop offset="0.98516" stop-color="#CA6407"/></linearGradient>
                        </defs>
                        </svg>
                    </div>
                    <span class="kpi-card-title">Active Projects</span>
                </div>
                <span class="kpi-trend-pill trend-up">Scan Active</span>
            </div>

            <div class="kpi-stat-value">
                <span><?php echo count($projects); ?></span>
                <div class="kpi-mini-chips-row">
                    <span class="kpi-mini-chip">/public: <?php echo $publicAppCount; ?></span>
                    <span class="kpi-mini-chip">index: <?php echo $indexAppCount; ?></span>
                    <span class="kpi-mini-chip">raw: <?php echo count($projects) - ($publicAppCount + $indexAppCount); ?></span>
                </div>
            </div>

            <div class="kpi-sub-text">
                <span>Discovered in Web Root</span>
                <span class="kpi-sub-val">Auto-scanned</span>
            </div>
        </div>

        <!-- KPI 2: Live CPU Telemetry & Load Averages -->
        <div class="kpi-card">
            <div class="kpi-card-head">
                <div class="kpi-head-left">
                    <div class="kpi-icon-badge badge-blue">
                        <!-- Gauge Color SVG -->
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z" fill="url(#kpi_gauge_p0)"/>
                        <path d="M7.93414 16.0659C8.22703 16.3588 8.22703 16.8336 7.93414 17.1265C7.64124 17.4194 7.16637 17.4194 6.87348 17.1265C4.04217 14.2952 4.04217 9.70478 6.87348 6.87348C8.71833 5.02862 11.3099 4.38674 13.6724 4.94459C14.0755 5.03978 14.3251 5.44375 14.2299 5.84688C14.1347 6.25 13.7308 6.49964 13.3276 6.40444C11.45 5.96107 9.39622 6.47205 7.93414 7.93414C5.68862 10.1797 5.68862 13.8204 7.93414 16.0659ZM17.8879 9.14151C18.2789 9.00477 18.7067 9.21089 18.8435 9.60189C19.7333 12.1463 19.1624 15.0907 17.1265 17.1265C16.8336 17.4194 16.3588 17.4194 16.0659 17.1265C15.773 16.8336 15.773 16.3588 16.0659 16.0659C17.6791 14.4526 18.1344 12.1183 17.4276 10.097C17.2908 9.70605 17.497 9.27824 17.8879 9.14151Z" fill="url(#kpi_gauge_p1)"/>
                        <path d="M15.8793 6.66724C16.1063 6.47289 16.4392 6.46645 16.6736 6.65188C16.9079 6.83731 16.9782 7.1627 16.8413 7.42835L16.7121 7.67855C16.6297 7.83794 16.5114 8.06617 16.3683 8.34171C16.082 8.89271 15.6956 9.63332 15.2957 10.3912C14.896 11.1485 14.4816 11.9253 14.1396 12.5478C13.9688 12.8588 13.8144 13.1343 13.688 13.3509C13.5705 13.5523 13.455 13.742 13.369 13.8507C12.7265 14.6628 11.5473 14.8003 10.7352 14.1579C9.92306 13.5154 9.78556 12.3362 10.428 11.524C10.514 11.4154 10.6721 11.2593 10.8411 11.0985C11.0228 10.9257 11.2554 10.7121 11.5187 10.4743C12.0459 9.99823 12.7065 9.41624 13.3516 8.85308C13.9971 8.28953 14.6289 7.74314 15.0993 7.33775C15.3345 7.13502 15.5294 6.96748 15.6656 6.85058L15.8793 6.66724Z" fill="url(#kpi_gauge_p4)"/>
                        <defs>
                        <linearGradient id="kpi_gauge_p0" x1="9.14286" y1="2" x2="16.8054" y2="20.9218" gradientUnits="userSpaceOnUse"><stop stop-color="#F4F4F4"/><stop offset="1" stop-color="#CBCBCB"/></linearGradient>
                        <radialGradient id="kpi_gauge_p1" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(4.46 17.317) scale(15.6599 23.6791)"><stop stop-color="#42B870"/><stop offset="0.501441" stop-color="#FF921F"/><stop offset="1" stop-color="#E82C41"/></radialGradient>
                        <linearGradient id="kpi_gauge_p4" x1="10.0234" y1="6.51703" x2="15.1733" y2="13.1398" gradientUnits="userSpaceOnUse"><stop stop-color="#0FAFFF"/><stop offset="1" stop-color="#2764E7"/></linearGradient>
                        </defs>
                        </svg>
                    </div>
                    <span class="kpi-card-title">CPU Utilization</span>
                </div>
                <span class="kpi-trend-pill trend-blue" id="liveCpuStatusBadge">Optimal</span>
            </div>

            <div class="kpi-stat-value">
                <span id="liveCpuVal"><?php echo $cpuStats['available'] ? $cpuStats['percent'] . '%' : 'N/A'; ?></span>
                <span class="kpi-mini-chip"><?php echo $cpuStats['cores']; ?> Cores</span>
            </div>

            <div class="kpi-progress-track">
                <div class="kpi-progress-fill fill-blue" id="liveCpuBar" style="width: <?php echo min(100, $cpuStats['percent']); ?>%;"></div>
            </div>

            <div class="kpi-sub-text">
                <span>Load Avg (1/5/15m)</span>
                <span class="kpi-sub-val">
                    <span id="liveLoad1m"><?php echo $cpuStats['load_1m']; ?></span> • 
                    <span id="liveLoad5m"><?php echo $cpuStats['load_5m']; ?></span> • 
                    <span id="liveLoad15m"><?php echo $cpuStats['load_15m']; ?></span>
                </span>
            </div>
        </div>

        <!-- KPI 3: Memory & Capacity Breakdown -->
        <div class="kpi-card">
            <div class="kpi-card-head">
                <div class="kpi-head-left">
                    <div class="kpi-icon-badge badge-purple">
                        <!-- Book Database Color SVG -->
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 19H20.2812C20.2812 19 20 19.5 20 20C20 20.5 20.2812 21 20.2812 21H6C5.44772 21 5 20.5523 5 20V19Z" fill="url(#kpi_book_p0)"/>
                        <path d="M4 4.5C4 3.11929 5.11929 2 6.5 2H18C19.3807 2 20.5 3.11929 20.5 4.5V18.75C20.5 19.1642 20.1642 19.5 19.75 19.5H5.5C5.5 20.0523 5.94772 20.5 6.5 20.5H19.75C20.1642 20.5 20.5 20.8358 20.5 21.25C20.5 21.6642 20.1642 22 19.75 22H6.5C5.11929 22 4 20.8807 4 19.5V4.5Z" fill="url(#kpi_book_p1)"/>
                        <path d="M7 6C7 5.44772 7.44772 5 8 5H16C16.5523 5 17 5.44772 17 6V8C17 8.55228 16.5523 9 16 9H8C7.44772 9 7 8.55228 7 8V6Z" fill="url(#kpi_book_p2)"/>
                        <path d="M19.5 15.8965C20.0293 15.8219 20.5336 15.708 21 15.5585C21.3509 15.446 21.6803 15.3134 21.9827 15.1622C22.3368 14.9852 22.6902 14.7618 23 14.4917V20.5C23 21.8807 20.7614 23 18 23C15.9497 23 14.1876 22.383 13.416 21.5C13.1484 21.1938 13 20.8556 13 20.5V14.4917C13.3098 14.7618 13.6632 14.9852 14.0173 15.1622C15.0882 15.6976 16.4977 16 18 16C18.5141 16 19.0174 15.9646 19.5 15.8965Z" fill="url(#kpi_book_p5)"/>
                        <path d="M19.5 12.1144C20.0464 12.2003 20.5523 12.3317 21 12.4998C22.2144 12.9559 23 13.6821 23 14.5C23 15.3179 22.2144 16.0441 21 16.5002C20.5523 16.6683 20.0464 16.7997 19.5 16.8855C19.0265 16.9599 18.5226 17 18 17C15.2386 17 13 15.8807 13 14.5C13 13.1193 15.2386 12 18 12C18.5226 12 19.0265 12.0401 19.5 12.1144Z" fill="url(#kpi_book_p6)"/>
                        <defs>
                        <linearGradient id="kpi_book_p0" x1="12.1739" y1="20.4" x2="12.1739" y2="18" gradientUnits="userSpaceOnUse"><stop stop-color="#9DEAFF"/><stop offset="0.715705" stop-color="#58AAFE"/></linearGradient>
                        <linearGradient id="kpi_book_p1" x1="9.69295" y1="5.74211" x2="12.6806" y2="27.308" gradientUnits="userSpaceOnUse"><stop stop-color="#20AC9D"/><stop offset="1" stop-color="#2052CB"/></linearGradient>
                        <linearGradient id="kpi_book_p2" x1="10.4487" y1="4.31375" x2="15.0534" y2="11.4776" gradientUnits="userSpaceOnUse"><stop stop-color="#9FF0F9"/><stop offset="1" stop-color="#6CE0FF"/></linearGradient>
                        <linearGradient id="kpi_book_p5" x1="15.3608" y1="12.5749" x2="21.1933" y2="22.0723" gradientUnits="userSpaceOnUse"><stop stop-color="#3BD5FF"/><stop offset="1" stop-color="#4894FE"/></linearGradient>
                        <linearGradient id="kpi_book_p6" x1="21.3333" y1="17.5" x2="15.2222" y2="12" gradientUnits="userSpaceOnUse"><stop stop-color="#66C0FF"/><stop offset="1" stop-color="#C8F3FF"/></linearGradient>
                        </defs>
                        </svg>
                    </div>
                    <span class="kpi-card-title">Memory (RAM)</span>
                </div>
                <span class="kpi-trend-pill trend-purple">PHP Max: <?php echo htmlspecialchars($memStats['php_limit']); ?></span>
            </div>

            <div class="kpi-stat-value">
                <span id="liveMemVal"><?php echo $memStats['available'] ? $memStats['percent'] . '%' : 'N/A'; ?></span>
                <span class="kpi-mini-chip">Total <?php echo $memStats['total']; ?> GB</span>
            </div>

            <div class="kpi-progress-track">
                <div class="kpi-progress-fill fill-purple" id="liveMemBar" style="width: <?php echo min(100, $memStats['percent']); ?>%;"></div>
            </div>

            <div class="kpi-sub-text">
                <span>Used: <span id="liveMemUsed"><?php echo $memStats['used']; ?></span> GB</span>
                <span>Free: <span id="liveMemFree"><?php echo $memStats['free']; ?></span> GB</span>
            </div>
        </div>

    </section>

    <!-- 4. Main Bottom Studio Grid (2-Column Split) -->
    <section class="bottom-studio-grid">
        
        <!-- Left Wide Column: Projects Directory Explorer -->
        <div class="studio-card-panel">
            <div class="panel-head-line">
                <div class="panel-title">
                    <!-- Document Folder Color SVG -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 6.25C8 5.00736 9.00736 4 10.25 4H17.75C18.9926 4 20 5.00736 20 6.25V14.75C20 15.9926 18.9926 17 17.75 17H10.25C9.00736 17 8 15.9926 8 14.75V6.25Z" fill="url(#p_docf_p0)"/>
                    <path d="M4 4.25C4 3.00736 5.00736 2 6.25 2H15.25C16.4926 2 17.5 3.00736 17.5 4.25V14.75C17.5 15.9926 16.4926 17 15.25 17H6.25C5.00736 17 4 15.9926 4 14.75V4.25Z" fill="url(#p_docf_p2)"/>
                    <path d="M5.25 8C4.00736 8 3 9.00736 3 10.25V18.75C3 20.5449 4.45507 22 6.25 22H17.75C19.5449 22 21 20.5449 21 18.75V17.25C21 16.0074 19.9926 15 18.75 15H15.9041C15.6956 15 15.4964 14.9132 15.3545 14.7603L9.74468 8.71899C9.31895 8.26051 8.72155 8 8.0959 8H5.25Z" fill="url(#p_docf_p3)"/>
                    <defs>
                    <linearGradient id="p_docf_p0" x1="21.8" y1="19.5" x2="23.6385" y2="5.77313" gradientUnits="userSpaceOnUse"><stop stop-color="#BB45EA"/><stop offset="1" stop-color="#9C6CFE"/></linearGradient>
                    <radialGradient id="p_docf_p2" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(8.05 14) rotate(-52.6548) scale(14.4655 23.3167)"><stop offset="0.228038" stop-color="#2764E7"/><stop offset="0.684836" stop-color="#5CD1FF"/><stop offset="1" stop-color="#6CE0FF"/></radialGradient>
                    <linearGradient id="p_docf_p3" x1="6.85714" y1="8" x2="6.85714" y2="27.0909" gradientUnits="userSpaceOnUse"><stop offset="0.240824" stop-color="#FFD638"/><stop offset="0.637257" stop-color="#FAB500"/><stop offset="0.98516" stop-color="#CA6407"/></linearGradient>
                    </defs>
                    </svg>
                    Projects Explorer (<span id="projectCountDisplay"><?php echo count($projects); ?></span>)
                </div>

                <div class="search-box-pill">
                    <!-- Search Visual Color SVG -->
                    <div class="search-icon-svg">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M4.5 4C4.22386 4 4 4.22386 4 4.5V8C4 8.55228 3.55228 9 3 9C2.44772 9 2 8.55228 2 8V4.5C2 3.11929 3.11929 2 4.5 2H8C8.55228 2 9 2.44772 9 3C9 3.55228 8.55228 4 8 4H4.5ZM15 3C15 2.44772 15.4477 2 16 2H19.5C20.8807 2 22 3.11929 22 4.5V8C22 8.55228 21.5523 9 21 9C20.4477 9 20 8.55228 20 8V4.5C20 4.22386 19.7761 4 19.5 4H16C15.4477 4 15 3.55228 15 3ZM3 15C3.55228 15 4 15.4477 4 16V19.5C4 19.7761 4.22386 20 4.5 20H8C8.55228 20 9 20.4477 9 21C9 21.5523 8.55228 22 8 22H4.5C3.11929 22 2 20.8807 2 19.5V16C2 15.4477 2.44772 15 3 15ZM21 15C21.5523 15 22 15.4477 22 16V19.5C22 20.8807 20.8807 22 19.5 22H16C15.4477 22 15 21.5523 15 21C15 20.4477 15.4477 20 16 20H19.5C19.7761 20 20 19.7761 20 19.5V16C20 15.4477 20.4477 15 21 15Z" fill="url(#p_search_p0)"/>
                        <defs><linearGradient id="p_search_p0" x1="1.21032" y1="0.325396" x2="16.8929" y2="25.6587" gradientUnits="userSpaceOnUse"><stop offset="0.0152387" stop-color="#3DCBFF"/><stop offset="1" stop-color="#0094F0"/></linearGradient></defs>
                        </svg>
                    </div>
                    <input type="text" id="projectSearchInput" placeholder="Search projects directory..." autocomplete="off">
                </div>
            </div>

            <div class="projects-grid" id="projectsGrid">
                <?php if (empty($projects)): ?>
                    <div class="empty-projects-view">
                        <!-- Document Folder Color SVG -->
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 12px;">
                        <path d="M8 6.25C8 5.00736 9.00736 4 10.25 4H17.75C18.9926 4 20 5.00736 20 6.25V14.75C20 15.9926 18.9926 17 17.75 17H10.25C9.00736 17 8 15.9926 8 14.75V6.25Z" fill="url(#emp_docf_p0)"/>
                        <path d="M4 4.25C4 3.00736 5.00736 2 6.25 2H15.25C16.4926 2 17.5 3.00736 17.5 4.25V14.75C17.5 15.9926 16.4926 17 15.25 17H6.25C5.00736 17 4 15.9926 4 14.75V4.25Z" fill="url(#emp_docf_p2)"/>
                        <path d="M5.25 8C4.00736 8 3 9.00736 3 10.25V18.75C3 20.5449 4.45507 22 6.25 22H17.75C19.5449 22 21 20.5449 21 18.75V17.25C21 16.0074 19.9926 15 18.75 15H15.9041C15.6956 15 15.4964 14.9132 15.3545 14.7603L9.74468 8.71899C9.31895 8.26051 8.72155 8 8.0959 8H5.25Z" fill="url(#emp_docf_p3)"/>
                        <defs>
                        <linearGradient id="emp_docf_p0" x1="21.8" y1="19.5" x2="23.6385" y2="5.77313" gradientUnits="userSpaceOnUse"><stop stop-color="#BB45EA"/><stop offset="1" stop-color="#9C6CFE"/></linearGradient>
                        <radialGradient id="emp_docf_p2" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(8.05 14) rotate(-52.6548) scale(14.4655 23.3167)"><stop offset="0.228038" stop-color="#2764E7"/><stop offset="0.684836" stop-color="#5CD1FF"/><stop offset="1" stop-color="#6CE0FF"/></radialGradient>
                        <linearGradient id="emp_docf_p3" x1="6.85714" y1="8" x2="6.85714" y2="27.0909" gradientUnits="userSpaceOnUse"><stop offset="0.240824" stop-color="#FFD638"/><stop offset="0.637257" stop-color="#FAB500"/><stop offset="0.98516" stop-color="#CA6407"/></linearGradient>
                        </defs>
                        </svg>
                        <p style="font-weight: 700; font-size: 1.05rem;">No projects discovered</p>
                        <p style="font-size: 0.85rem; margin-top: 4px; color: var(--text-muted);">Create a new folder in <code><?php echo htmlspecialchars($webRootRealPath); ?></code> to automatically list it here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <a href="<?php echo htmlspecialchars($proj['path']); ?>" class="project-card" data-name="<?php echo htmlspecialchars(strtolower($proj['name'])); ?>">
                            <div class="project-card-top">
                                <div class="folder-svg-wrap">
                                    <!-- Document Folder Color SVG -->
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 6.25C8 5.00736 9.00736 4 10.25 4H17.75C18.9926 4 20 5.00736 20 6.25V14.75C20 15.9926 18.9926 17 17.75 17H10.25C9.00736 17 8 15.9926 8 14.75V6.25Z" fill="url(#card_docf_p0)"/>
                                    <path d="M4 4.25C4 3.00736 5.00736 2 6.25 2H15.25C16.4926 2 17.5 3.00736 17.5 4.25V14.75C17.5 15.9926 16.4926 17 15.25 17H6.25C5.00736 17 4 15.9926 4 14.75V4.25Z" fill="url(#card_docf_p2)"/>
                                    <path d="M5.25 8C4.00736 8 3 9.00736 3 10.25V18.75C3 20.5449 4.45507 22 6.25 22H17.75C19.5449 22 21 20.5449 21 18.75V17.25C21 16.0074 19.9926 15 18.75 15H15.9041C15.6956 15 15.4964 14.9132 15.3545 14.7603L9.74468 8.71899C9.31895 8.26051 8.72155 8 8.0959 8H5.25Z" fill="url(#card_docf_p3)"/>
                                    <defs>
                                    <linearGradient id="card_docf_p0" x1="21.8" y1="19.5" x2="23.6385" y2="5.77313" gradientUnits="userSpaceOnUse"><stop stop-color="#BB45EA"/><stop offset="1" stop-color="#9C6CFE"/></linearGradient>
                                    <radialGradient id="card_docf_p2" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(8.05 14) rotate(-52.6548) scale(14.4655 23.3167)"><stop offset="0.228038" stop-color="#2764E7"/><stop offset="0.684836" stop-color="#5CD1FF"/><stop offset="1" stop-color="#6CE0FF"/></radialGradient>
                                    <linearGradient id="card_docf_p3" x1="6.85714" y1="8" x2="6.85714" y2="27.0909" gradientUnits="userSpaceOnUse"><stop offset="0.240824" stop-color="#FFD638"/><stop offset="0.637257" stop-color="#FAB500"/><stop offset="0.98516" stop-color="#CA6407"/></linearGradient>
                                    </defs>
                                    </svg>
                                </div>
                                <div>
                                    <div class="project-title"><?php echo htmlspecialchars($proj['name']); ?></div>
                                    <div class="project-date"><?php echo htmlspecialchars($proj['mtime_formatted']); ?></div>
                                </div>
                            </div>
                            <div class="project-card-foot">
                                <span class="type-tag">
                                    <?php echo $proj['has_public'] ? '/public' : ($proj['has_index'] ? 'index' : $proj['child_count'] . ' items'); ?>
                                </span>
                                <span class="launch-link">
                                    Open
                                    <!-- Link Color SVG -->
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.5 12C2.5 9.23858 4.73858 7 7.5 7H9.75C10.1642 7 10.5 7.33579 10.5 7.75C10.5 8.16421 10.1642 8.5 9.75 8.5H7.5C5.567 8.5 4 10.067 4 12C4 13.933 5.567 15.5 7.5 15.5H9.75C10.1642 15.5 10.5 15.8358 10.5 16.25C10.5 16.6642 10.1642 17 9.75 17H7.5C4.73858 17 2.5 14.7614 2.5 12ZM21.5 12C21.5 9.23858 19.2614 7 16.5 7H14.25C13.8358 7 13.5 7.33579 13.5 7.75C13.5 8.16421 13.8358 8.5 14.25 8.5H16.5C18.433 8.5 20 10.067 20 12C20 13.933 18.433 15.5 16.5 15.5H14.25C13.8358 15.5 13.5 15.8358 13.5 16.25C13.5 16.6642 13.8358 17 14.25 17H16.5C19.2614 17 21.5 14.7614 21.5 12ZM7.75 11.25C7.33579 11.25 7 11.5858 7 12C7 12.4142 7.33579 12.75 7.75 12.75H16.25C16.6642 12.75 17 12.4142 17 12C17 11.5858 16.6642 11.25 16.25 11.25H7.75Z" fill="url(#p_link_p0)"/>
                                    <defs><linearGradient id="p_link_p0" x1="-4.25" y1="2.24219" x2="3.8647" y2="27.9992" gradientUnits="userSpaceOnUse"><stop stop-color="#36DFF1"/><stop offset="1" stop-color="#2764E7"/></linearGradient></defs>
                                    </svg>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Environment & System Specs -->
        <div class="studio-card-panel">
            <div class="panel-title" style="margin-bottom: 4px;">
                <!-- Question Circle Color SVG -->
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2Z" fill="url(#p_info_p0)"/>
                <path d="M12.125 16C12.5392 16 12.875 16.3358 12.875 16.75C12.875 17.1642 12.5392 17.5 12.125 17.5C11.7108 17.5 11.375 17.1642 11.375 16.75C11.375 16.3358 11.7108 16 12.125 16ZM12.125 6.5C13.8074 6.5 15.25 7.94103 15.25 9.62441C15.251 10.6846 14.8657 11.3596 13.9108 12.3886L13.4033 12.9319C12.8921 13.5014 12.6897 13.8626 12.6704 14.2897L12.669 14.3705L12.6661 14.4345L12.6571 14.4966C12.6009 14.7818 12.3505 14.9978 12.0484 15C11.7032 15.0025 11.4214 14.7247 11.419 14.3795C11.4126 13.4851 11.7574 12.8734 12.573 11.9869L13.0867 11.4381C13.7656 10.6919 14.0006 10.2515 14 9.625C14 8.6317 13.1173 7.75 12.125 7.75C11.1654 7.75 10.3071 8.5768 10.2527 9.52936L10.2467 9.6889C10.2148 10.004 9.94862 10.25 9.625 10.25C9.27982 10.25 9 9.97016 9 9.625C9 7.94231 10.4432 6.5 12.125 6.5Z" fill="url(#p_info_p1)"/>
                <defs>
                <linearGradient id="p_info_p0" x1="2" y1="2" x2="22" y2="22" gradientUnits="userSpaceOnUse"><stop stop-color="#0FAFFF"/><stop offset="1" stop-color="#2764E7"/></linearGradient>
                <linearGradient id="p_info_p1" x1="9.54668" y1="6.69317" x2="12.6143" y2="17.9289" gradientUnits="userSpaceOnUse"><stop stop-color="#FDFDFD"/><stop offset="1" stop-color="#CCEAFF"/></linearGradient>
                </defs>
                </svg>
                Environment Specs
            </div>

            <div class="spec-list-group">
                <div class="spec-item-box">
                    <span class="spec-item-key">OS Distro</span>
                    <span class="spec-item-val"><?php echo htmlspecialchars($osName); ?></span>
                </div>
                <div class="spec-item-box">
                    <span class="spec-item-key">Local IP Address</span>
                    <span class="spec-item-val"><?php echo htmlspecialchars($serverAddr); ?></span>
                </div>
                <div class="spec-item-box">
                    <span class="spec-item-key">Web Server</span>
                    <span class="spec-item-val"><?php echo htmlspecialchars($serverSoftware); ?></span>
                </div>
                <div class="spec-item-box">
                    <span class="spec-item-key">Web Root</span>
                    <span class="spec-item-val" style="font-size:0.8rem; word-break:break-all;"><?php echo htmlspecialchars($webRootRealPath); ?></span>
                </div>
                <div class="spec-item-box">
                    <span class="spec-item-key">PHP Engine</span>
                    <span class="spec-item-val">v<?php echo htmlspecialchars($phpVersion); ?> (<?php echo htmlspecialchars($sapiName); ?>)</span>
                </div>
                <div class="spec-item-box">
                    <span class="spec-item-key">MySQL Database</span>
                    <span class="spec-item-val" id="panelLiveDb">
                        <span style="color:<?php echo $dbStatus['connected'] ? '#36c073' : '#f0b429'; ?>;">
                            <?php echo $dbStatus['connected'] ? 'Connected (' . htmlspecialchars($dbStatus['driver']) . ')' : 'Offline'; ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

    </section>

    <!-- Fluent 2 Dialog Modals -->
    <!-- Extensions Modal -->
    <div class="modal-backdrop" id="extensionsModal" onclick="closeExtensionsModal()">
        <div class="modal-window" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 style="font-size: 1.1rem; font-weight: 700; display:flex; align-items:center; gap:10px;">
                    <!-- Code Color SVG -->
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8.08553 18.6109L14.0817 4.60641C14.2991 4.09871 14.8868 3.86335 15.3946 4.08073C15.866 4.28258 16.1026 4.80382 15.96 5.28375L15.9202 5.39361L9.92409 19.3981C9.70671 19.9058 9.11892 20.1412 8.61121 19.9238C8.13977 19.722 7.90316 19.2007 8.04581 18.7208L8.08553 18.6109L14.0817 4.60641L8.08553 18.6109ZM2.29289 11.2929L6.29289 7.29291C6.68342 6.90238 7.31658 6.90238 7.70711 7.29291C8.06759 7.65339 8.09532 8.22062 7.7903 8.61291L7.70711 8.70712L4.41421 12L7.70711 15.2929C8.09763 15.6834 8.09763 16.3166 7.70711 16.7071C7.34662 17.0676 6.77939 17.0953 6.3871 16.7903L6.29289 16.7071L2.29289 12.7071C1.93241 12.3466 1.90468 11.7794 2.2097 11.3871L2.29289 11.2929L6.29289 7.29291L2.29289 11.2929ZM16.2921 7.29167C16.6526 6.9312 17.2198 6.9035 17.6121 7.20854L17.7063 7.29173L21.7071 11.2929C22.0678 11.6536 22.0953 12.2211 21.7899 12.6134L21.7066 12.7076L17.7058 16.7031C17.315 17.0934 16.6818 17.093 16.2916 16.7022C15.9313 16.3415 15.904 15.7742 16.2093 15.3821L16.2925 15.288L19.5854 11.9995L16.292 8.70588C15.9015 8.31534 15.9015 7.68217 16.2921 7.29167Z" fill="url(#m_code_p0)"/>
                    <defs><linearGradient id="m_code_p0" x1="2.58824" y1="2.93305" x2="20.693" y2="22.3089" gradientUnits="userSpaceOnUse"><stop stop-color="#C76EFB"/><stop offset="1" stop-color="#8B52F4"/></linearGradient></defs>
                    </svg>
                    Loaded PHP Extensions (<?php echo count($loadedExtensions); ?>)
                </h3>
                <button class="nav-pill-btn" onclick="closeExtensionsModal()">Close</button>
            </div>
            <div class="modal-body">
                <input type="text" id="extSearchInput" class="ext-search-input" placeholder="Filter extensions..." autocomplete="off">
                <div class="ext-pill-grid" id="extGridModal">
                    <?php foreach ($loadedExtensions as $ext): ?>
                        <?php $isPriority = in_array(strtolower($ext), $priorityExtensions, true); ?>
                        <span class="ext-pill <?php echo $isPriority ? 'active' : ''; ?>" data-ext="<?php echo htmlspecialchars(strtolower($ext)); ?>">
                            <?php echo htmlspecialchars($ext); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- System Specs Modal -->
    <div class="modal-backdrop" id="systemModal" onclick="closeSystemModal()">
        <div class="modal-window" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 style="font-size: 1.1rem; font-weight: 700; display:flex; align-items:center; gap:10px;">
                    <!-- Question Circle Color SVG -->
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2Z" fill="url(#m_info_p0)"/>
                    <path d="M12.125 16C12.5392 16 12.875 16.3358 12.875 16.75C12.875 17.1642 12.5392 17.5 12.125 17.5C11.7108 17.5 11.375 17.1642 11.375 16.75C11.375 16.3358 11.7108 16 12.125 16ZM12.125 6.5C13.8074 6.5 15.25 7.94103 15.25 9.62441C15.251 10.6846 14.8657 11.3596 13.9108 12.3886L13.4033 12.9319C12.8921 13.5014 12.6897 13.8626 12.6704 14.2897L12.669 14.3705L12.6661 14.4345L12.6571 14.4966C12.6009 14.7818 12.3505 14.9978 12.0484 15C11.7032 15.0025 11.4214 14.7247 11.419 14.3795C11.4126 13.4851 11.7574 12.8734 12.573 11.9869L13.0867 11.4381C13.7656 10.6919 14.0006 10.2515 14 9.625C14 8.6317 13.1173 7.75 12.125 7.75C11.1654 7.75 10.3071 8.5768 10.2527 9.52936L10.2467 9.6889C10.2148 10.004 9.94862 10.25 9.625 10.25C9.27982 10.25 9 9.97016 9 9.625C9 7.94231 10.4432 6.5 12.125 6.5Z" fill="url(#m_info_p1)"/>
                    <defs>
                    <linearGradient id="m_info_p0" x1="2" y1="2" x2="22" y2="22" gradientUnits="userSpaceOnUse"><stop stop-color="#0FAFFF"/><stop offset="1" stop-color="#2764E7"/></linearGradient>
                    <linearGradient id="m_info_p1" x1="9.54668" y1="6.69317" x2="12.6143" y2="17.9289" gradientUnits="userSpaceOnUse"><stop stop-color="#FDFDFD"/><stop offset="1" stop-color="#CCEAFF"/></linearGradient>
                    </defs>
                    </svg>
                    System Specifications
                </h3>
                <button class="nav-pill-btn" onclick="closeSystemModal()">Close</button>
            </div>
            <div class="modal-body">
                <div class="spec-list-group">
                    <div class="spec-item-box"><span class="spec-item-key">Web Root Directory</span><span class="spec-item-val"><?php echo htmlspecialchars($webRootRealPath); ?></span></div>
                    <div class="spec-item-box"><span class="spec-item-key">OS Distro</span><span class="spec-item-val"><?php echo htmlspecialchars($osName); ?></span></div>
                    <div class="spec-item-box"><span class="spec-item-key">Architecture</span><span class="spec-item-val"><?php echo htmlspecialchars($arch); ?></span></div>
                    <div class="spec-item-box"><span class="spec-item-key">System Uptime</span><span class="spec-item-val" id="modalLiveUptime"><?php echo htmlspecialchars($uptimeStr); ?></span></div>
                    <div class="spec-item-box"><span class="spec-item-key">Server Software</span><span class="spec-item-val"><?php echo htmlspecialchars($serverSoftware); ?></span></div>
                    <div class="spec-item-box"><span class="spec-item-key">Local IP Address</span><span class="spec-item-val"><?php echo htmlspecialchars($serverAddr); ?></span></div>
                    <div class="spec-item-box"><span class="spec-item-key">PHP Engine</span><span class="spec-item-val">v<?php echo htmlspecialchars($phpVersion); ?> (<?php echo htmlspecialchars($sapiName); ?>)</span></div>
                    <div class="spec-item-box"><span class="spec-item-key">Memory Limit</span><span class="spec-item-val"><?php echo htmlspecialchars($memoryLimit); ?></span></div>
                    <div class="spec-item-box"><span class="spec-item-key">Upload Max Size</span><span class="spec-item-val"><?php echo htmlspecialchars($uploadMax); ?></span></div>
                    <div class="spec-item-box"><span class="spec-item-key">MySQL Connection</span><span class="spec-item-val" id="modalLiveDbStatus"><?php echo $dbStatus['connected'] ? 'Connected (' . htmlspecialchars($dbStatus['driver']) . ' v' . htmlspecialchars($dbStatus['version']) . ')' : 'Offline / Idle'; ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- phpinfo Modal -->
    <div class="modal-backdrop" id="phpInfoModal" onclick="closePhpInfoModal()">
        <div class="modal-window" style="max-width: 1000px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 style="font-size: 1.1rem; font-weight: 700;">PHP System Diagnostics (phpinfo)</h3>
                <button class="nav-pill-btn" onclick="closePhpInfoModal()">Close</button>
            </div>
            <iframe class="modal-iframe" id="phpInfoIframe" data-src="./index.php?action=phpinfo" src="about:blank"></iframe>
        </div>
    </div>

    <script>
        // Theme Management
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('fluent_theme', theme);
        }

        const savedTheme = localStorage.getItem('fluent_theme') || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
        setTheme(savedTheme);

        document.querySelectorAll('.themeToggleBtn').forEach(btn => {
            btn.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                setTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });
        });

        // Real-Time Projects Search Filter
        const searchInput = document.getElementById('projectSearchInput');
        const projectCards = document.querySelectorAll('.project-card');
        const countDisplay = document.getElementById('projectCountDisplay');

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                let visibleCount = 0;

                projectCards.forEach(card => {
                    const name = card.getAttribute('data-name') || '';
                    if (name.includes(query)) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (countDisplay) {
                    countDisplay.textContent = visibleCount;
                }
            });
        }

        // Real-Time Extensions Filter
        const extSearchInput = document.getElementById('extSearchInput');
        const extPills = document.querySelectorAll('#extGridModal .ext-pill');

        if (extSearchInput) {
            extSearchInput.addEventListener('input', (e) => {
                const q = e.target.value.toLowerCase().trim();
                extPills.forEach(pill => {
                    const extName = pill.getAttribute('data-ext') || '';
                    pill.style.display = extName.includes(q) ? 'inline-block' : 'none';
                });
            });
        }

        // Live Reactive Telemetry Polling
        async function fetchTelemetry() {
            try {
                const res = await fetch('./index.php?action=telemetry');
                if (!res.ok) return;
                const data = await res.json();

                if (data.cpu && data.cpu.available) {
                    const cpuVal = document.getElementById('liveCpuVal');
                    const cpuBar = document.getElementById('liveCpuBar');
                    const load1m = document.getElementById('liveLoad1m');
                    const load5m = document.getElementById('liveLoad5m');
                    const load15m = document.getElementById('liveLoad15m');
                    const cpuBadge = document.getElementById('liveCpuStatusBadge');

                    if (cpuVal) cpuVal.textContent = data.cpu.percent + '%';
                    if (cpuBar) cpuBar.style.width = Math.min(100, data.cpu.percent) + '%';
                    if (load1m) load1m.textContent = data.cpu.load_1m || '0.00';
                    if (load5m) load5m.textContent = data.cpu.load_5m || '0.00';
                    if (load15m) load15m.textContent = data.cpu.load_15m || '0.00';

                    if (cpuBadge) {
                        const pct = data.cpu.percent;
                        if (pct > 80) {
                            cpuBadge.className = 'kpi-trend-pill';
                            cpuBadge.style.background = 'rgba(232, 44, 65, 0.16)';
                            cpuBadge.style.color = '#e82c41';
                            cpuBadge.textContent = 'High Load';
                        } else if (pct > 50) {
                            cpuBadge.className = 'kpi-trend-pill trend-purple';
                            cpuBadge.textContent = 'Moderate';
                        } else {
                            cpuBadge.className = 'kpi-trend-pill trend-blue';
                            cpuBadge.textContent = 'Optimal';
                        }
                    }
                }

                if (data.mem && data.mem.available) {
                    const memVal = document.getElementById('liveMemVal');
                    const memBar = document.getElementById('liveMemBar');
                    const memUsed = document.getElementById('liveMemUsed');
                    const memFree = document.getElementById('liveMemFree');

                    if (memVal) memVal.textContent = data.mem.percent + '%';
                    if (memBar) memBar.style.width = Math.min(100, data.mem.percent) + '%';
                    if (memUsed) memUsed.textContent = data.mem.used;
                    if (memFree) memFree.textContent = data.mem.free;
                }

                if (data.disk && data.disk.available) {
                    const diskFree = document.getElementById('panelLiveDiskFree');
                    if (diskFree) diskFree.textContent = data.disk.free + ' GB';
                }

                if (data.uptime && data.uptime !== 'N/A') {
                    const modalUptime = document.getElementById('modalLiveUptime');
                    const panelUptime = document.getElementById('panelLiveUptime');
                    if (modalUptime) modalUptime.textContent = data.uptime;
                    if (panelUptime) panelUptime.textContent = data.uptime;
                }

                if (data.db) {
                    const modalDb = document.getElementById('modalLiveDbStatus');
                    const panelDb = document.getElementById('panelLiveDb');
                    if (modalDb) {
                        modalDb.textContent = data.db.connected ? `Connected (${data.db.driver} v${data.db.version})` : 'Offline / Idle';
                    }
                    if (panelDb) {
                        panelDb.innerHTML = `<span style="color:${data.db.connected ? '#36c073' : '#f0b429'};">${data.db.connected ? 'Connected' : 'Offline'}</span>`;
                    }
                }
            } catch (err) {
                console.warn('Telemetry poll warning:', err);
            }
        }

        setInterval(fetchTelemetry, 6000);

        // Modal Controls
        function openExtensionsModal() {
            document.getElementById('extensionsModal').classList.add('active');
        }
        function closeExtensionsModal() {
            document.getElementById('extensionsModal').classList.remove('active');
        }

        function openSystemModal() {
            document.getElementById('systemModal').classList.add('active');
        }
        function closeSystemModal() {
            document.getElementById('systemModal').classList.remove('active');
        }

        function openPhpInfoModal() {
            const modal = document.getElementById('phpInfoModal');
            const iframe = document.getElementById('phpInfoIframe');
            if (iframe && !iframe.src.includes('action=phpinfo')) {
                iframe.src = iframe.getAttribute('data-src');
            }
            modal.classList.add('active');
        }
        function closePhpInfoModal() {
            document.getElementById('phpInfoModal').classList.remove('active');
        }
    </script>
</body>
</html>
