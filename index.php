<?php
/**
 * SYSTEM MONITOR V6.7 - GOLDEN COPY
 * - Layout: Grid & Flexbox (Responsive)
 * - Logic: Cross-Platform (Win/Linux), Secure Scanner
 * - UX: Auto Theme, Smooth Transitions
 *
 * The contents of this file are subject to the terms of the Common Development and
 * Distribution License (the "License"). You may not use this file except in
 * compliance with the License.
 *
 * You can obtain a copy of the License at http://www.opensource.org/licenses/cddl1.php
 * See the License for the specific language governing permissions and limitations
 * under the License.
 */

$serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$clientIp   = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$docRoot    = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
$phpVer     = phpversion();
$user       = get_current_user();

$osFamily = 'Linux';
$osLabel  = php_uname('s');
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $osFamily = 'Windows';
    $osLabel  = "Windows NT";
} elseif (is_readable('/etc/os-release')) {
    $osConf = parse_ini_file('/etc/os-release');
    if (isset($osConf['PRETTY_NAME'])) {
        $osLabel = trim($osConf['PRETTY_NAME'], '"');
    } elseif (isset($osConf['NAME'])) {
        $osLabel = $osConf['NAME'];
    }
}

$isPrivate = false;
if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
    if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $isPrivate = true;
    }
}

$dbHost   = ini_get('mysqli.default_host') ?: '127.0.0.1';
$dbOnline = false;
try {
    $conn = @fsockopen($dbHost, 3306, $errno, $errstr, 0.5);
    if ($conn) { $dbOnline = true; fclose($conn); }
} catch (Exception $e) {}

$cpuCount = 1;
$cpuLoadPercent = 0;
$ramTotal = 0; $ramUsed = 0; $ramPercent = 0;

if ($osFamily !== 'Windows') {
    if (is_readable('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        preg_match_all('/^processor/m', $cpuinfo, $matches);
        $cnt = count($matches[0]);
        if ($cnt > 0) $cpuCount = $cnt;
    }
    $loadAvg = sys_getloadavg();
    if ($loadAvg) {
        $cpuRaw = ($loadAvg[0] / $cpuCount) * 100;
        $cpuLoadPercent = min(100, round($cpuRaw, 1)); 
    }
    if (is_readable('/proc/meminfo')) {
        $memInfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $memInfo, $mt);
        preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $ma);
        if (isset($mt[1]) && isset($ma[1])) {
            $ramTotal = $mt[1] * 1024;
            $ramAvail = $ma[1] * 1024;
            $ramUsed  = $ramTotal - $ramAvail;
            $ramPercent = round(($ramUsed / $ramTotal) * 100);
        }
    }
}

$dt = disk_total_space(".");
$df = disk_free_space(".");
$du = $dt - $df;
$diskPercent = ($dt > 0) ? round(($du / $dt) * 100) : 0;

function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return 'N/A';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$exts = get_loaded_extensions();
sort($exts);

$projects = [];
$ignored = ['.', '..', 'html', 'images', '50x.php', '404.php', '403.php', 'index.php', 'error_test.php'];
if (is_dir($docRoot)) {
    $dirs = scandir($docRoot);
    foreach ($dirs as $dir) {
        if ($dir !== '.' && $dir !== '..' && is_dir($docRoot . '/' . $dir) && !in_array($dir, $ignored)) {
            $projects[] = $dir;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEV :: <?php echo htmlspecialchars($serverName); ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23111113'/%3E%3Cpath d='M16 4h12v24h-12z' fill='%23111113'/%3E%3Crect x='0' y='0' width='32' height='32' rx='6' stroke='%2327272a' stroke-width='2' fill='none'/%3E%3Cpath d='M8 10l8 6-8 6' stroke='%2306b6d4' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cline x1='18' y1='22' x2='26' y2='22' stroke='%2306b6d4' stroke-width='3' stroke-linecap='round'/%3E%3C/svg%3E" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-body: #09090b; --bg-panel: #111113; --border-col: #27272a;
            --text-main: #e4e4e7; --text-muted: #71717a; --grid-col: #27272a;
            --accent: #06b6d4; --success: #10b981; --danger: #ef4444; --warn: #f59e0b;
            --bar-bg: #27272a; --btn-hover: rgba(6, 182, 212, 0.1);
            --font-mono: 'JetBrains Mono', monospace; --font-sans: 'Inter', sans-serif;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg-body: #f4f4f5; --bg-panel: #ffffff; --border-col: #d4d4d8;
                --text-main: #18181b; --text-muted: #52525b; --grid-col: #e4e4e7;
                --accent: #0891b2; --bar-bg: #e4e4e7; --btn-hover: rgba(8, 145, 178, 0.08);
            }
        }

        *, *::before, *::after { box-sizing: border-box; transition: all 0.3s ease; }

        body {
            margin: 0; background-color: var(--bg-body); color: var(--text-main); font-family: var(--font-sans);
            background-image: radial-gradient(var(--grid-col) 1px, transparent 1px); background-size: 32px 32px;
            min-height: 100vh; display: flex; justify-content: center; padding: 2rem;
        }
        .dashboard-grid { width: 100%; max-width: 1200px; display: grid; grid-template-columns: 320px 1fr; gap: 2rem; align-items: start; }

        .card {
            background: var(--bg-panel); border: 1px solid var(--border-col); padding: 1.5rem; position: relative;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); display: flex; flex-direction: column;
        }
        .card::before { content: ''; position: absolute; top: 0; left: 0; width: 6px; height: 6px; border-top: 2px solid var(--accent); border-left: 2px solid var(--accent); }
        .card::after { content: ''; position: absolute; bottom: 0; right: 0; width: 6px; height: 6px; border-bottom: 2px solid var(--accent); border-right: 2px solid var(--accent); }
        
        h1, h2, h3 { margin: 0; font-weight: normal; }
        .font-mono { font-family: var(--font-mono); }
        .text-accent { color: var(--accent); }
        .text-muted { color: var(--text-muted); }
        
        .brand { margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-col); padding-bottom: 1rem; }
        .brand h1 { font-size: 1.25rem; font-weight: 700; text-transform: uppercase; letter-spacing: -0.5px; }
        
        .sys-meta { display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem; }
        .sys-row { display: flex; justify-content: space-between; align-items: center; }
        .sys-row span:first-child { color: var(--text-muted); font-family: var(--font-mono); }
        
        .metrics-group { display: flex; flex-direction: column; gap: 1.25rem; margin-top: 2rem; }
        .metric-label { display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.85rem; font-family: var(--font-mono); }
        .bar-bg { height: 6px; background: var(--bar-bg); width: 100%; overflow: hidden; }
        .bar-fill { height: 100%; background: var(--accent); transition: width 0.5s ease; }
        .fill-high { background: var(--warn); } .fill-crit { background: var(--danger); }

        .section-header {
            font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 1.5px; margin: 2rem 0 1rem 0;
            display: flex; align-items: center; gap: 1rem;
        }
        .section-header:first-of-type { margin-top: 0; }
        .section-header::after { content: ''; flex: 1; height: 1px; background: var(--border-col); }

        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .status-card { display: grid !important; grid-template-columns: 1fr auto; align-items: center; gap: 1rem; text-align: left !important; }
        .status-dot { width: 12px; height: 12px; border-radius: 50%; position: relative; }
        .status-dot.active { background: var(--success); box-shadow: 0 0 8px var(--success); }
        .status-dot.inactive { background: var(--danger); box-shadow: 0 0 8px var(--danger); }
        @keyframes pulseAlive { 0%, 100% { opacity: 1; box-shadow: 0 0 5px var(--success); } 50% { opacity: 0.5; box-shadow: 0 0 0 var(--success); } }
        .status-alive { animation: pulseAlive 2s infinite ease-in-out; display: inline-block; width:8px; height:8px; background:var(--success); border-radius:50%; margin-right:8px; }
        .status-dot.active::after { content: ''; position: absolute; inset: -3px; border-radius: 50%; background: var(--success); opacity: 0.3; animation: pulseAlive 1.5s infinite; }

        .btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn { display: flex; align-items: center; justify-content: center; padding: 1.25rem; background: transparent; border: 1px solid var(--border-col); color: var(--text-main); text-decoration: none; font-family: var(--font-mono); font-size: 0.9rem; cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn:hover { background: var(--btn-hover); border-color: var(--accent); color: var(--accent); }
        .btn.disabled { opacity: 0.5; cursor: not-allowed; }

        .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .project-card { text-decoration: none; color: inherit; padding: 1.25rem; background: var(--bg-panel); border: 1px solid var(--border-col); transition: transform 0.2s ease, border-color 0.2s ease; display: flex; flex-direction: column; gap: 0.5rem; }
        .project-card:hover { transform: translateY(-3px); border-color: var(--accent); }
        .project-name { font-weight: 600; font-size: 1rem; }
        .project-path { font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono); }
        .empty-state { grid-column: 1 / -1; padding: 3rem; text-align: center; border: 1px dashed var(--border-col); color: var(--text-muted); }

        .tags-container { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .tag { font-size: 0.75rem; padding: 0.3rem 0.6rem; background: var(--bg-panel); border: 1px solid var(--border-col); color: var(--text-muted); font-family: var(--font-mono); }
        .tag.highlight { border-color: var(--accent); color: var(--accent); }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); z-index: 99; display: none; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: var(--bg-panel); border: 1px solid var(--border-col); width: 90%; max-width: 900px; height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0,0,0,0.5); color: var(--text-main); }
        .modal-header { padding: 1rem; border-bottom: 1px solid var(--border-col); display: flex; justify-content: space-between; align-items: center; }
        .modal-content { overflow: auto; padding: 0; flex: 1; }
        .modal-content table { width: 100%; border-collapse: collapse; }
        .modal-content td, .modal-content th { border: 1px solid var(--border-col); padding: 8px; font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted); }
        .modal-content th { background: var(--bg-body); color: var(--accent); text-align: left; }
        .modal-content h1, .modal-content h2 { display: none; }

        @media (max-width: 768px) { body { padding: 1rem; } .dashboard-grid, .btn-grid, .services-grid { grid-template-columns: 1fr; } .status-card { grid-template-columns: 1fr auto; } }
    </style>
</head>
<body>

<div class="dashboard-grid">
    <aside>
        <div class="card" style="height: 100%;">
            <div class="brand">
                <h1 class="font-mono text-accent">DEV_ENV_01</h1>
                <div style="font-size: 0.8rem; margin-top: 0.5rem; color: var(--success); display: flex; align-items: center;">
                    <span class="status-alive"></span> SYSTEM OPERATIONAL
                </div>
            </div>
            <div class="sys-meta">
                <div class="sys-row"><span>HOST</span> <strong><?php echo htmlspecialchars($serverName); ?></strong></div>
                <div class="sys-row"><span>OS</span> <strong><?php echo htmlspecialchars($osLabel); ?></strong></div>
                <div class="sys-row"><span>IP</span> <strong><?php echo htmlspecialchars($clientIp); ?></strong></div>
                <div class="sys-row"><span>USER</span> <strong><?php echo htmlspecialchars($user); ?></strong></div>
            </div>
            <div class="metrics-group">
                <div>
                    <div class="metric-label"><span>CPU (<?php echo ($osFamily==='Windows')?'N/A':$cpuCount; ?> Core)</span><span class="<?php echo $cpuLoadPercent > 80 ? 'text-accent' : ''; ?>"><?php echo ($osFamily==='Windows')?'N/A':$cpuLoadPercent.'%'; ?></span></div>
                    <div class="bar-bg"><div class="bar-fill <?php echo $cpuLoadPercent > 85 ? 'fill-crit' : 'fill-ok'; ?>" style="width: <?php echo $cpuLoadPercent; ?>%"></div></div>
                </div>
                <div>
                    <div class="metric-label"><span>RAM</span><span><?php echo ($osFamily==='Windows')?'N/A':$ramPercent.'%'; ?></span></div>
                    <div class="bar-bg"><div class="bar-fill <?php echo $ramPercent > 85 ? 'fill-high' : 'fill-ok'; ?>" style="width: <?php echo $ramPercent; ?>%"></div></div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:6px; font-family:var(--font-mono);"><?php echo ($osFamily==='Windows')?'N/A':formatBytes($ramUsed) . ' / ' . formatBytes($ramTotal); ?></div>
                </div>
                <div>
                    <div class="metric-label"><span>DISK (ROOT)</span><span><?php echo $diskPercent; ?>%</span></div>
                    <div class="bar-bg"><div class="bar-fill" style="width: <?php echo $diskPercent; ?>%"></div></div>
                </div>
            </div>
        </div>
    </aside>

    <main>
        <div class="section-header">DETECTED PROJECTS [<?php echo count($projects); ?>]</div>
        <div class="project-grid">
            <?php if (count($projects) > 0): ?>
                <?php foreach ($projects as $proj): ?>
                <a href="/<?php echo htmlspecialchars($proj); ?>" class="project-card">
                    <div class="project-name"><?php echo htmlspecialchars($proj); ?></div>
                    <div class="project-path">/<?php echo htmlspecialchars($proj); ?></div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><div style="font-size: 2rem; margin-bottom: 0.5rem;">📂</div>No projects found.<br><small>Create a folder in <code><?php echo htmlspecialchars($docRoot); ?></code> to start.</small></div>
            <?php endif; ?>
        </div>

        <div class="section-header">SERVICES STATUS</div>
        <div class="services-grid">
            <div class="card status-card">
                <div class="status-content">
                    <h3 class="font-mono" style="font-weight: bold; margin-bottom: 0.3rem;">PHP-FPM</h3>
                    <div class="text-muted" style="font-size: 0.9rem;">v<?php echo $phpVer; ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;"><?php echo htmlspecialchars($docRoot); ?></div>
                </div>
                <div class="status-dot active"></div>
            </div>

            <div class="card status-card">
                <div class="status-content">
                    <h3 class="font-mono" style="font-weight: bold; margin-bottom: 0.3rem;">DATABASE</h3>
                    <div class="text-muted" style="font-size: 0.9rem;">MariaDB / MySQL</div>
                    <div style="font-size: 0.75rem; margin-top: 0.5rem; font-family: var(--font-mono);">
                        <?php if($dbOnline): ?><span style="color: var(--success);">CONNECTION ESTABLISHED</span><?php else: ?><span style="color: var(--danger);">CONNECTION FAILED</span><?php endif; ?>
                    </div>
                </div>
                <div class="status-dot <?php echo $dbOnline ? 'active' : 'inactive'; ?>"></div>
            </div>
        </div>

        <div class="section-header">QUICK ACTIONS</div>
        <div class="card actions-card">
            <div class="btn-grid">
                <a href="/phpmyadmin" target="_blank" class="btn">Launch phpMyAdmin</a>
                <?php if($isPrivate): ?><button id="openInfoBtn" class="btn">View PHP Info</button><?php else: ?><button class="btn disabled">Locked</button><?php endif; ?>
            </div>
        </div>

        <div class="section-header">LOADED MODULES [<?php echo count($exts); ?>]</div>
        <div class="card modules-card">
            <div class="tags-container">
                <?php $priority = ['mysqli', 'mbstring', 'curl', 'json', 'gd', 'zip', 'openssl', 'pdo_mysql']; foreach($exts as $ext): $isPriority = in_array($ext, $priority); ?>
                    <div class="tag <?php echo $isPriority ? 'highlight' : ''; ?>"><?php echo $ext; ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<?php if($isPrivate): ?>
<div id="infoModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header"><h3 class="font-mono text-accent">PHP CONFIGURATION</h3><button id="closeInfoBtn" style="background:none; border:none; color:var(--danger); cursor:pointer; font-weight:bold;">[ESC]</button></div>
        <div class="modal-content"><?php ob_start(); phpinfo(); $pinfo = ob_get_clean(); $pinfo = preg_replace('%^.*<body[^>]*>(.*)</body>.*$%msi', '$1', $pinfo); echo $pinfo; ?></div>
    </div>
</div>
<script>
    const modal = document.getElementById('infoModal'), openBtn = document.getElementById('openInfoBtn'), closeBtn = document.getElementById('closeInfoBtn');
    if (openBtn) {
        const toggleModal = (show) => { modal.classList.toggle('open', show); document.body.style.overflow = show ? 'hidden' : ''; };
        openBtn.addEventListener('click', () => toggleModal(true)); closeBtn.addEventListener('click', () => toggleModal(false));
        modal.addEventListener('click', (e) => { if (e.target === modal) toggleModal(false); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') toggleModal(false); });
    }
</script>
<?php endif; ?>
</body>
</html>
