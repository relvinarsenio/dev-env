<?php
/**
 * Server Console v7 - FreeBSD edition (full redesign)
 * - Metrics read from sysctl / swapinfo / zpool (Linux /proc fallback kept)
 * - Live refresh via ?api=stats, phpinfo lazy-loaded via ?view=phpinfo
 * - No external fonts or CDN: works offline on an isolated dev box
 *
 * License: CDDL 1.0 (same as original) http://www.opensource.org/licenses/cddl1.php
 */

/* ---------- helpers ---------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sh(string $cmd): ?string {
    if (!function_exists('shell_exec')) return null;
    $o = @shell_exec($cmd . ' 2>/dev/null');
    return ($o === null || $o === false) ? null : trim($o);
}
function sysctlv(string $oid): ?string { return sh('sysctl -n ' . escapeshellarg($oid)); }

function fmtBytes($b, int $p = 1): string {
    if ($b <= 0) return '0 B';
    $u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $i = min((int)floor(log($b, 1024)), count($u) - 1);
    return round($b / pow(1024, $i), $p) . ' ' . $u[$i];
}
function fmtUptime(?int $s): string {
    if ($s === null) return 'unknown';
    $d = intdiv($s, 86400); $h = intdiv($s % 86400, 3600); $m = intdiv($s % 3600, 60);
    return ($d ? "{$d}d " : '') . "{$h}h {$m}m";
}
function pct($used, $total): ?int { return $total > 0 ? (int)round($used / $total * 100) : null; }
function level(?int $p): string {
    if ($p === null) return 'na';
    return $p >= 85 ? 'crit' : ($p >= 70 ? 'warn' : 'ok');
}
function probe(string $host, int $port, float $t = 0.25): bool {
    $c = @fsockopen($host, $port, $en, $es, $t);
    if ($c) { fclose($c); return true; }
    return false;
}

/* ---------- metrics ---------- */
function collectStats(): array {
    $isBsd = stripos(PHP_OS, 'BSD') !== false;
    $isWin = stripos(PHP_OS, 'WIN') === 0;
    $cores = 1; $memT = 0; $memU = 0; $swT = 0; $swU = 0; $up = null;

    if ($isBsd) {
        $cores = max(1, (int)sysctlv('hw.ncpu'));
        $memT  = (int)sysctlv('hw.physmem');
        $ps    = (int)(sysctlv('hw.pagesize') ?: 4096);
        // Free + inactive pages + ZFS ARC (ARC is reclaimable, so it is not "used")
        $reclaim = ((int)sysctlv('vm.stats.vm.v_free_count') + (int)sysctlv('vm.stats.vm.v_inactive_count')) * $ps
                 + (int)sysctlv('kstat.zfs.misc.arcstats.size');
        $memU = max(0, $memT - $reclaim);

        $sw = sh('swapinfo -k');
        if ($sw) {
            $l = array_values(array_filter(explode("\n", $sw)));
            if (count($l) > 1) {
                $p = preg_split('/\s+/', trim(end($l)));
                $swT = (int)($p[1] ?? 0) * 1024;
                $swU = (int)($p[2] ?? 0) * 1024;
            }
        }
        if (preg_match('/sec\s*=\s*(\d+)/', (string)sysctlv('kern.boottime'), $m)) $up = time() - (int)$m[1];
    } elseif (!$isWin) {
        if (is_readable('/proc/cpuinfo')) {
            $n = preg_match_all('/^processor/m', file_get_contents('/proc/cpuinfo'));
            if ($n > 0) $cores = $n;
        }
        if (is_readable('/proc/meminfo')) {
            $mi = file_get_contents('/proc/meminfo');
            $g = fn($k) => preg_match("/^$k:\s+(\d+)/m", $mi, $x) ? (int)$x[1] * 1024 : 0;
            $memT = $g('MemTotal'); $memU = $memT - $g('MemAvailable');
            $swT = $g('SwapTotal'); $swU = $swT - $g('SwapFree');
        }
        if (is_readable('/proc/uptime')) $up = (int)explode(' ', file_get_contents('/proc/uptime'))[0];
    }

    $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
    $cpuP = $isWin ? null : (int)min(100, round($load[0] / $cores * 100));

    $root = $isWin ? __DIR__ : '/';
    $dt = (float)@disk_total_space($root); $df = (float)@disk_free_space($root);
    $du = $dt - $df;

    $mk = fn(?int $p, string $sub) => ['pct' => $p, 'level' => level($p), 'sub' => $sub];
    return [
        'cpu'    => $mk($cpuP, sprintf('load %.2f, %d core%s', $load[0], $cores, $cores > 1 ? 's' : '')),
        'mem'    => $mk(pct($memU, $memT), $memT ? fmtBytes($memU) . ' of ' . fmtBytes($memT) : 'not available'),
        'swap'   => $mk(pct($swU, $swT), $swT ? fmtBytes($swU) . ' of ' . fmtBytes($swT) : 'no swap configured'),
        'disk'   => $mk(pct($du, $dt), $dt ? fmtBytes($du) . ' of ' . fmtBytes($dt) : 'not available'),
        'uptime' => fmtUptime($up),
        'load'   => array_map(fn($x) => round((float)$x, 2), array_slice($load, 0, 3)),
        'cores'  => $cores,
        'booted' => $up !== null ? date('d M, H:i', time() - $up) : 'unknown',
        'time'   => date('H:i:s'),
    ];
}

/* ---------- endpoints ---------- */
$clientIp  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$isPrivate = filter_var($clientIp, FILTER_VALIDATE_IP)
    && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

if (($_GET['api'] ?? '') === 'stats') {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(collectStats()); exit;
}
if (($_GET['view'] ?? '') === 'phpinfo') {
    if (!$isPrivate) { http_response_code(403); exit('phpinfo is limited to private networks.'); }
    phpinfo(); exit;
}

/* ---------- page data ---------- */
$t0         = microtime(true);
$serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$docRoot    = $_SERVER['DOCUMENT_ROOT'] ?? '';
$software   = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
$isBsd      = stripos(PHP_OS, 'BSD') !== false;
$osName     = php_uname('s');
$osVer      = $isBsd ? (sh('freebsd-version') ?: php_uname('r')) : php_uname('r');
$stats      = collectStats();

// Services: TCP probe, plus the unix sockets FreeBSD MySQL/MariaDB packages use by default
$dbHost = ini_get('mysqli.default_host') ?: '127.0.0.1';
$sock   = fn(array $paths) => (bool)array_filter($paths, 'file_exists');
$services = [
    ['Web server', $software, true, 'serving this page'],
    ['PHP', 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')', true, 'running'],
    ['MySQL / MariaDB', $dbHost . ':3306', probe($dbHost, 3306) || $sock(['/tmp/mysql.sock', '/var/run/mysql/mysql.sock']), null],
    ['Redis', '127.0.0.1:6379', probe('127.0.0.1', 6379), null],
    ['SSH', '127.0.0.1:22', probe('127.0.0.1', 22), null],
];

// ZFS pools (only shown when zpool exists)
$pools = [];
if ($isBsd && ($z = sh('zpool list -H -o name,size,alloc,health'))) {
    foreach (explode("\n", $z) as $line) {
        $c = explode("\t", $line);
        if (count($c) >= 4) $pools[] = ['name' => $c[0], 'size' => $c[1], 'alloc' => $c[2], 'health' => $c[3]];
    }
}

// Projects: every folder under the document root
$ignored  = ['html', 'images', 'nginx-dist', 'cgi-bin'];
$projects = [];
if (is_dir($docRoot)) {
    foreach (scandir($docRoot) as $d) {
        $p = $docRoot . '/' . $d;
        if ($d[0] === '.' || !is_dir($p) || in_array($d, $ignored, true) || strcasecmp($d, 'phpmyadmin') === 0) continue;
        $has = (bool)array_filter(['index.php', 'index.html', 'public/index.php'], fn($f) => is_file("$p/$f"));
        $projects[] = ['name' => $d, 'has_index' => $has, 'mtime' => date('d M Y', (int)filemtime($p))];
    }
}

$exts = get_loaded_extensions(); sort($exts, SORT_FLAG_CASE | SORT_STRING);
$key  = ['mysqli', 'pdo_mysql', 'mbstring', 'curl', 'json', 'gd', 'zip', 'openssl', 'intl', 'opcache', 'Zend OPcache'];

$env = [
    'PHP version'   => PHP_VERSION,
    'Web user'      => get_current_user(),
    'Your address'  => $clientIp,
    'Timezone'      => date_default_timezone_get(),
    'Memory limit'  => ini_get('memory_limit'),
    'Upload limit'  => ini_get('upload_max_filesize'),
    'Document root' => $docRoot ?: 'unknown',
];

$upCount   = count(array_filter(array_column($services, 2)));
$svcTotal  = count($services);
$upWords   = array_pad(explode(' ', $stats['uptime']), 2, '');
$tzOffset  = (int)date('Z');
$tzLabel   = 'UTC ' . date('P');
$mapLeft   = round((($tzOffset / 3600) + 12) / 24 * 100, 1);
$initial   = strtoupper(substr((string)get_current_user(), 0, 1)) ?: 'S';

$extCats = [
    'Database'           => '/^(mysqli|mysqlnd|pdo|pdo_.+|pgsql|sqlite3|dba|odbc|mongodb|oci8.*|sqlsrv|maxminddb)$/i',
    'Network & security' => '/^(curl|openssl|sockets|sodium|hash|random|ftp|ldap|snmp|soap|imap|smbclient|libsmbclient|cgi-fcgi|mcrypt)$/i',
    'Text & language'    => '/^(mbstring|intl|iconv|gettext|ctype|pcre|pspell|enchant|tidy|kolabformat)$/i',
    'Files & media'      => '/^(gd|exif|fileinfo|zip|zlib|bz2|zstd|phar|imagick|gmagick|mapscript)$/i',
    'Data & formats'     => '/^(json|xml|xmlreader|xmlwriter|simplexml|dom|xsl|libxml|yaml|bcmath|gmp|calendar)$/i',
];
$extGroups = [];
foreach ($exts as $x) {
    $c = 'Core & runtime';
    foreach ($extCats as $name => $re) { if (preg_match($re, $x)) { $c = $name; break; } }
    $extGroups[$c][] = $x;
}
$extOrder = array_values(array_filter(array_merge(['Core & runtime'], array_keys($extCats)), fn($c) => isset($extGroups[$c])));
$slug = fn($t) => trim(strtolower(preg_replace('/[^a-z]+/i', '-', $t)), '-');
$railMax   = 6;
$cpuModel = '';
if ($isBsd) $cpuModel = (string)sysctlv('hw.model');
elseif (is_readable('/proc/cpuinfo') && preg_match('/^model name\s*:\s*(.+)$/m', file_get_contents('/proc/cpuinfo'), $cm)) $cpuModel = trim($cm[1]);
$cpuModel = trim(preg_replace('/\((R|TM)\)|\s+CPU\b|\s+@\s*[\d.]+\s*GHz|\s+with\s+.*$/i', '', $cpuModel)) ?: 'unknown';
$zbytes = function ($v) { $m = ['B'=>1,'K'=>1024,'M'=>1024**2,'G'=>1024**3,'T'=>1024**4,'P'=>1024**5];
    return preg_match('/([\d.]+)\s*([BKMGTP]?)/i', (string)$v, $x) ? (float)$x[1] * $m[strtoupper($x[2] ?: 'B')] : 0; };
foreach ($pools as &$z) { $t = $zbytes($z['size']); $z['pct'] = $t > 0 ? (int)min(100, round($zbytes($z['alloc']) / $t * 100)) : 0; }
unset($z);

function metric(string $id, string $label, array $s): void { ?>
    <div class="tile" data-tile="<?= $id ?>" data-level="<?= e($s['level']) ?>">
        <div class="tile-top"><span class="tile-label"><?= e($label) ?></span></div>
        <div class="tile-num"><span data-val="<?= $id ?>"><?= $s['pct'] === null ? '–' : $s['pct'] ?></span><small>%</small></div>
        <span class="pill" data-sub="<?= $id ?>"><?= e($s['sub']) ?></span>
        <div class="bar"><i style="width:<?= (int)$s['pct'] ?>%"></i></div>
    </div>
<?php }
$ico = [
    'home'  => '<path d="M4 11l8-7 8 7v8a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.2 3 14.8 0 18M12 3c-3 3.2-3 14.8 0 18"/>',
    'star'  => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
    'gear'  => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
    'db'    => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3"/>',
    'cal'   => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
    'grid'  => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
    'bolt'  => '<path d="M13 3L5 14h6l-1 7 8-11h-6z"/>',
];
$svg = fn(string $k, int $s = 18) => '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ico[$k] . '</svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($serverName) ?> - Server console</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<style>
:root {
    --bg: linear-gradient(120deg, #f4eee3 0%, #f8e2d2 45%, #f5c6ab 100%);
    --ink:#17130f; --muted:#8c7f76; --soft:#b9aca2; --glass:rgba(255,255,255,.5); --glass-strong:rgba(255,251,247,.88); --glass-line:rgba(255,255,255,.75);
    --dark:#1c1510; --orange:#ff8f47; --yellow:#f3ee2f; --warn:#f0b323; --crit:#e5443a;
    --sans: "Helvetica Neue", "Inter", "Segoe UI", system-ui, sans-serif;
}
@media (prefers-color-scheme: dark) {
    :root {
        --bg: linear-gradient(120deg, #17110d 0%, #2a1a12 55%, #40251a 100%);
        --ink:#f6eee7; --muted:#a29488; --soft:#6b5e54; --glass:rgba(255,255,255,.07); --glass-strong:rgba(34,25,20,.9); --glass-line:rgba(255,255,255,.12);
    }
}
* { box-sizing: border-box; }
html { color-scheme: light dark; }
body { margin:0; min-height:100vh; background:var(--bg) fixed; color:var(--ink); font:15px/1.45 var(--sans); }
a { color:inherit; text-decoration:none; }
button { font:inherit; color:inherit; }
:focus-visible { outline:2px solid var(--ink); outline-offset:3px; }
.wrap { max-width:1320px; margin:0 auto; padding:1.5rem 2rem 3rem; }

.glass { background:var(--glass); border:1px solid var(--glass-line); border-radius:28px; backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); }
.circle { width:44px; height:44px; border-radius:50%; display:grid; place-items:center; flex:none; background:var(--glass); border:1px solid var(--glass-line); cursor:pointer; }
button.circle[disabled] { opacity:.4; cursor:not-allowed; }

/* top bar */
.top { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:1rem; }
.brand { display:flex; align-items:center; gap:.5rem; font-weight:500; font-size:.95rem; }
.brand i { width:26px; height:26px; border-radius:50%; background:var(--ink); color:var(--orange); display:grid; place-items:center; font-style:normal; }
.nav { display:flex; align-items:center; gap:.5rem; }
.nav .active { background:var(--dark); color:#fff; padding:.75rem 1.3rem; border-radius:999px; display:flex; gap:.5rem; align-items:center; font-weight:500; }
.top-r { display:flex; justify-content:flex-end; gap:.6rem; align-items:center; }
.avatar { background:linear-gradient(135deg, var(--yellow), var(--orange)); color:var(--dark); font-weight:700; border:0; cursor:default; }

/* layout */
.stage { display:grid; grid-template-columns:minmax(300px,390px) 1fr 72px; gap:2rem; margin-top:1.5rem; }
.headline { margin:.5rem 0 1.2rem; font-size:clamp(1.6rem,2.6vw,2.2rem); line-height:1.05; font-weight:400; letter-spacing:-.03em; }
.headline span { color:var(--soft); display:block; }

/* dark hero card */
.hero { position:relative; min-height:270px; border-radius:30px; padding:1.4rem 1.5rem; color:#f6eee7; overflow:hidden;
    background:radial-gradient(120% 90% at 85% 100%, #6d3b20 0%, transparent 60%), radial-gradient(circle at 20% 0%, #3a2a1f 0%, #1c1510 65%); }
.hero::after { content:''; position:absolute; inset:0; background:radial-gradient(rgba(255,255,255,.16) 1px, transparent 1.4px) 0 0/10px 10px; -webkit-mask-image:linear-gradient(120deg, transparent 35%, #000 100%); mask-image:linear-gradient(120deg, transparent 35%, #000 100%); pointer-events:none; }
.hero > * { position:relative; z-index:1; }
.hero-label { font-size:.9rem; opacity:.85; }
.hero-num { margin:.4rem 0 .9rem; font-size:clamp(3.6rem,6vw,5rem); line-height:.88; font-weight:200; letter-spacing:-.03em; }
.pill { display:inline-block; background:var(--orange); color:#2a1204; font-size:.72rem; font-weight:600; padding:.18rem .6rem; border-radius:999px; }

.hero-main { display:grid; grid-template-columns:1fr auto; gap:1rem 1.5rem; }
.hero-l { min-width:0; }
.hero-r { display:flex; flex-direction:column; align-items:flex-end; min-width:0; max-width:11.5rem; }
.hero-r > div { flex:1; display:flex; flex-direction:column; }
.la { flex:1; display:flex; gap:.95rem; justify-content:flex-end; }
.la-track { width:14px; flex:1; min-height:88px; border-radius:8px; background:rgba(255,255,255,.12); display:flex; align-items:flex-end; overflow:hidden; }
.facts { margin:1.1rem 0 0; padding-top:.95rem; border-top:1px solid rgba(255,255,255,.14); display:flex; justify-content:space-between; gap:1rem; }
.facts > div { min-width:0; }
.facts > div:nth-child(2) { flex:1; text-align:center; }
.facts > div:last-child { text-align:right; }
.facts dt { font-size:.68rem; opacity:.55; }
.facts dd { margin:0; font-size:.88rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.la-title { font-size:.72rem; opacity:.6; text-align:right; margin:.1rem 0 .7rem; }
.la { gap:1.15rem; }
.la-col { display:flex; flex-direction:column; align-items:center; gap:.3rem; font-variant-numeric:tabular-nums; }
.la-track { position:relative; width:18px; min-height:110px; }
.la-track i { position:absolute; left:0; right:0; bottom:0; border-radius:8px; background:linear-gradient(to top, var(--orange), #ffc27a); transition:height .6s ease; }
.la-col b { font-weight:400; font-size:.85rem; }
.la-col small { font-size:.68rem; opacity:.55; }
.hero-pools { margin-top:1.1rem; padding-top:.95rem; border-top:1px solid rgba(255,255,255,.14); display:grid; gap:.9rem; }
.hp-top { display:flex; justify-content:space-between; gap:1rem; font-size:.88rem; }
.hp-top span:last-child { opacity:.7; font-variant-numeric:tabular-nums; }
.hbar { height:6px; border-radius:6px; background:rgba(255,255,255,.14); margin:.5rem 0 .4rem; overflow:hidden; }
.hbar i { display:block; height:100%; background:var(--orange); border-radius:6px; transition:width .6s ease; }
.hp-meta { display:flex; justify-content:space-between; font-size:.72rem; opacity:.7; }
.hd { display:inline-block; width:7px; height:7px; border-radius:50%; background:#3fd28f; margin-right:.4rem; }
.hd.bad { background:var(--crit); }
/* metric tiles */
.tiles { display:grid; grid-template-columns:1fr 1fr; gap:.9rem; margin-top:.9rem; }
.tile { padding:1rem 1.1rem .9rem; border-radius:24px; background:var(--glass); border:1px solid var(--glass-line); position:relative; }
.tile-top { display:flex; justify-content:space-between; align-items:center; font-size:.85rem; }
.tile-num { font-size:2.5rem; font-weight:300; letter-spacing:-.025em; line-height:1.2; margin:.35rem 0 .3rem; font-variant-numeric:tabular-nums; }
.tile-num small { font-size:1rem; color:var(--muted); margin-left:2px; }
.tile .pill { max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tile[data-level="warn"] .pill { background:var(--warn); }
.tile[data-level="crit"] .pill { background:var(--crit); color:#fff; }
.tile[data-level="na"] .pill { background:var(--soft); color:#fff; }
.bar { height:3px; border-radius:3px; background:rgba(0,0,0,.08); margin-top:.7rem; overflow:hidden; }
.bar i { display:block; height:100%; background:var(--ink); border-radius:3px; transition:width .6s ease; }

/* center: clock + zone map + cards */
.center { display:flex; flex-direction:column; min-width:0; }
.zone { display:flex; align-items:center; gap:.9rem; color:var(--muted); font-size:.95rem; }
.zone b { color:var(--ink); font-weight:500; }
.clock { margin:.3rem 0 0; font-size:clamp(3.4rem,10.5vw,9rem); line-height:1; font-weight:200; letter-spacing:-.02em; font-variant-numeric:tabular-nums; white-space:nowrap; }
.clock .sec { color:var(--soft); }
.cards { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.card { position:relative; border-radius:30px; padding:1.2rem 1.4rem; min-height:170px; display:flex; flex-direction:column; justify-content:space-between; color:#1a0f06; }
.card-label { font-size:.95rem; font-weight:500; }
.today { background:var(--orange); }
.today .date { font-size:clamp(1.7rem,3vw,2.5rem); font-weight:300; letter-spacing:-.03em; line-height:1.1; }
.today .foot, .live-card .foot { display:flex; justify-content:space-between; align-items:flex-end; font-size:.8rem; }
.today .wk, .live-card .note { background:rgba(255,255,255,.28); padding:.15rem .6rem; border-radius:999px; }
.live-card { background:var(--yellow); }
.live-card .pair { display:flex; justify-content:space-between; align-items:baseline; font-size:clamp(1.9rem,3.4vw,2.8rem); font-weight:300; letter-spacing:-.03em; }
.live-card .pair em { font-style:normal; }

/* project rail */
.rail { display:flex; flex-direction:column; align-items:center; gap:.8rem; padding-top:5rem; }
.rail a { width:50px; height:50px; border-radius:50%; display:grid; place-items:center; font-size:.75rem; font-weight:500; background:var(--glass); border:1px solid var(--glass-line); }
.rail a.first { width:72px; height:72px; font-size:1.05rem; background:var(--glass); box-shadow:0 8px 24px rgba(120,60,20,.15); }
.rail .cap { font-size:.7rem; color:var(--muted); }

/* detail sections */
.more { display:grid; grid-template-columns:1.2fr 1fr; gap:1rem; margin-top:2.5rem; align-items:start; }
.stack { display:grid; gap:1rem; }
.panel { overflow:hidden; }
.panel-h { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1.1rem 1.4rem; border-bottom:1px solid var(--glass-line); }
.panel-h h2 { margin:0; font-size:1.05rem; font-weight:500; letter-spacing:-.01em; }
.panel-h .count { color:var(--muted); margin-left:.4rem; }
.acts { display:flex; gap:.5rem; }
.btn { display:inline-flex; align-items:center; padding:.5rem 1rem; border-radius:999px; font-size:.85rem; font-weight:500; border:0; cursor:pointer; }
.btn.ghost { background:transparent; color:var(--ink); border:1px solid var(--glass-line); }
.btn[disabled] { opacity:.4; cursor:not-allowed; }
.search { border:1px solid var(--glass-line); background:var(--glass); color:var(--ink); border-radius:999px; padding:.4rem .9rem; font:inherit; font-size:.85rem; width:11rem; max-width:45%; }
.dot { width:10px; height:10px; border-radius:50%; background:var(--soft); flex:none; }
.dot.up { background:#1fae6b; box-shadow:0 0 0 4px rgba(31,174,107,.2); }
.ok { color:#1a9160; font-weight:600; }
.tag { font-size:.75rem; padding:.1rem .55rem; border-radius:999px; background:rgba(0,0,0,.06); color:var(--muted); }
.empty { padding:1.6rem 1.4rem; color:var(--muted); }
.foot-bar { margin-top:1.5rem; display:flex; justify-content:space-between; flex-wrap:wrap; gap:.5rem; color:var(--muted); font-size:.8rem; }
.live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#1fae6b; margin-right:.4rem; }
.off .live-dot { background:var(--crit); }


/* ---- fixes: solid colours for inverted/hover states (gradient vars are not valid colours) ---- */
:root { --inv-bg:#17130f; --inv-fg:#fff; }
@media (prefers-color-scheme: dark) { :root { --inv-bg:#f6eee7; --inv-fg:#17130f; } .stat { color:#5be0a5; } }
.btn { background:var(--inv-bg); color:var(--inv-fg); }
.circle, .rail a, .btn, .tile, .card, .ptile, .stile, .mono, .go {
    transition:transform .25s ease, background .25s ease, color .25s ease, box-shadow .25s ease, border-color .25s ease, opacity .25s ease; }
.circle:hover, .rail a:hover, .btn:hover { background:var(--orange); color:#2a1204; border-color:var(--orange); }
.circle:hover, .btn:hover { transform:translateY(-2px); }
.rail a:hover { transform:scale(1.1); }
.circle.avatar:hover { background:linear-gradient(135deg, var(--yellow), var(--orange)); color:var(--dark); transform:none; }
button.circle[disabled]:hover { background:var(--glass); color:inherit; transform:none; }
.nav .active:hover { background:#000; }
.tile:hover, .stile:not(.off):hover { transform:translateY(-3px); box-shadow:0 14px 30px rgba(140,70,20,.16); }
.card:hover { transform:translateY(-3px); box-shadow:0 18px 34px rgba(140,70,20,.2); }

/* ---- system pulse chart (replaces dotted map) ---- */
.pulse { flex:1; min-height:250px; margin:.6rem 0 1rem; padding:1.1rem 1.3rem 1rem; display:flex; flex-direction:column; }
.pulse-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; }
.pulse-head .k { font-weight:500; } .pulse-head .sub { display:block; font-size:.78rem; color:var(--muted); }
.legend { display:flex; gap:1.1rem; font-size:.85rem; }
.lg { display:flex; align-items:center; gap:.4rem; color:var(--muted); }
.lg i { width:10px; height:10px; border-radius:50%; } .lg.cpu i { background:var(--ink); } .lg.mem i { background:var(--orange); }
.lg b { color:var(--ink); font-weight:500; font-variant-numeric:tabular-nums; }
.plot { position:relative; flex:1; min-height:150px; margin-top:.8rem; padding-left:2rem; cursor:crosshair; }
.plot svg { position:absolute; left:2rem; top:0; width:calc(100% - 2rem); height:100%; overflow:visible; }
.plot path { vector-effect:non-scaling-stroke; }
.ylab { position:absolute; left:0; font-size:.68rem; color:var(--muted); transform:translateY(-50%); }
.cross { position:absolute; top:0; bottom:0; width:1px; background:var(--ink); opacity:.45; display:none; pointer-events:none; }
.tip { position:absolute; top:-.2rem; display:none; pointer-events:none; background:var(--dark); color:#fff; font-size:.75rem; padding:.4rem .7rem; border-radius:12px; white-space:nowrap; transform:translate(-50%,-100%); z-index:2; }
.pulse-foot { display:flex; justify-content:space-between; font-size:.72rem; color:var(--muted); margin-top:.5rem; padding-left:2rem; }

/* ---- lower sections ---- */
.block { margin-top:3rem; }
.block-h { display:flex; align-items:baseline; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
.block-h h2 { margin:0; font-size:clamp(1.6rem,2.8vw,2.2rem); font-weight:300; letter-spacing:-.03em; }
.block-h h2 em { font-style:normal; color:var(--soft); font-size:.5em; vertical-align:super; margin-left:.35rem; }
.pgrid { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:.9rem; }
.ptile { position:relative; display:flex; flex-direction:column; gap:.15rem; min-height:172px; padding:1.1rem 1.2rem; border-radius:26px; background:var(--glass); border:1px solid var(--glass-line); backdrop-filter:blur(14px); overflow:hidden; }
.mono { width:54px; height:54px; border-radius:50%; display:grid; place-items:center; font-size:.85rem; font-weight:600; letter-spacing:.05em; background:var(--dark); color:var(--orange); margin-bottom:auto; }
.pname { font-size:1.25rem; letter-spacing:-.02em; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pmeta { font-size:.8rem; color:var(--muted); }
.go { position:absolute; top:1.1rem; right:1.2rem; font-size:1.3rem; opacity:.3; }
.ptile:hover { background:var(--orange); border-color:var(--orange); color:#2a1204; transform:translateY(-4px); box-shadow:0 18px 34px rgba(140,70,20,.25); }
.ptile:hover .pmeta { color:rgba(42,18,4,.7); } .ptile:hover .go { opacity:1; transform:translate(3px,-3px); }
.lower { display:grid; grid-template-columns:1.5fr 1fr; gap:2rem; align-items:start; }
.lower .block { margin-top:3rem; }
.sgrid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:.9rem; }
.stile { border-radius:26px; padding:1rem 1.15rem; background:var(--glass); border:1px solid var(--glass-line); display:flex; flex-direction:column; }
.stile.off { background:transparent; border:1.5px dashed var(--soft); opacity:.72; }
.stile-top { display:flex; justify-content:space-between; align-items:center; font-size:.9rem; }
.stat { font-size:.72rem; font-weight:600; padding:.12rem .55rem; border-radius:999px; background:rgba(31,174,107,.16); color:#17845a; }
.off .stat { background:rgba(0,0,0,.06); color:var(--muted); }
.port { font-size:2.7rem; font-weight:200; letter-spacing:-.03em; line-height:1.15; font-variant-numeric:tabular-nums; margin-top:.4rem; }
.smeta { font-size:.78rem; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.envcard { border-radius:30px; padding:1.4rem 1.5rem; color:#f6eee7;
    background:radial-gradient(120% 90% at 90% 100%, #6d3b20 0%, transparent 60%), radial-gradient(circle at 15% 0%, #3a2a1f 0%, #1c1510 65%); }
.envcard h2 { margin:0 0 1rem; font-size:1.6rem; font-weight:300; letter-spacing:-.03em; }
.envacts { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.3rem; }
.envgrid { display:grid; grid-template-columns:1fr 1fr; gap:0; }
.envgrid > div { border-top:1px solid rgba(255,255,255,.14); padding:.75rem 0 1rem; min-width:0; }
.envgrid > div:nth-child(even):not(.wide) { padding-left:1.4rem; }
.envgrid small { display:block; font-size:.72rem; opacity:.6; }
.envgrid b { font-weight:300; font-size:1.2rem; letter-spacing:-.02em; overflow-wrap:anywhere; }
.envgrid .wide { grid-column:1 / -1; }
.envcard .btn:not(.ghost) { background:var(--orange); color:#2a1204; }
.envcard .btn:not(.ghost):hover { background:var(--yellow); }
.envcard .btn.ghost { color:#f6eee7; border-color:rgba(255,255,255,.28); }
.envcard .btn.ghost:hover { background:var(--orange); color:#2a1204; }
@media (max-width:1100px) { .lower { grid-template-columns:1fr; } }

[hidden] { display:none !important; }
.navpill { display:inline-flex; align-items:center; gap:.5rem; padding:.72rem 1.1rem; border-radius:999px; background:var(--glass); border:1px solid var(--glass-line); color:var(--ink); font-weight:500; cursor:pointer; transition:transform .25s ease, background .25s ease, color .25s ease, border-color .25s ease; }
.navpill em { font-style:normal; font-size:.8rem; color:var(--muted); font-variant-numeric:tabular-nums; }
.navpill:hover { background:var(--orange); border-color:var(--orange); color:#2a1204; transform:translateY(-2px); } .navpill:hover em { color:rgba(42,18,4,.7); }

.xdlg { width:min(940px,94vw); height:min(640px,88vh); border:0; border-radius:32px; background:#fbf3ec; color:#17130f; box-shadow:0 40px 90px rgba(40,15,0,.45); }
.xdlg[open] { display:flex; flex-direction:row; animation:pop .28s cubic-bezier(.2,.8,.2,1); }
@keyframes pop { from { opacity:0; transform:translateY(14px) scale(.98); } }
.x-side { position:relative; width:270px; flex:none; padding:1.7rem 1.5rem 1.5rem; display:flex; flex-direction:column; gap:1.5rem; color:#f6eee7; overflow:hidden;
    background:radial-gradient(120% 80% at 90% 100%, #6d3b20 0%, transparent 60%), radial-gradient(circle at 15% 0%, #3a2a1f 0%, #1c1510 65%); }
.x-side::after { content:''; position:absolute; inset:0; background:radial-gradient(rgba(255,255,255,.14) 1px, transparent 1.4px) 0 0/10px 10px; -webkit-mask-image:linear-gradient(160deg, transparent 40%, #000 100%); mask-image:linear-gradient(160deg, transparent 40%, #000 100%); pointer-events:none; }
.x-side > * { position:relative; z-index:1; }
.x-kicker { font-size:.8rem; opacity:.65; }
.x-big { font-size:6.4rem; font-weight:200; letter-spacing:-.04em; line-height:.9; margin-top:.5rem; font-variant-numeric:tabular-nums; }
.x-cap { opacity:.7; font-size:.9rem; margin-top:.4rem; }
.x-cats { display:flex; flex-direction:column; gap:.15rem; }
.x-cats button { display:flex; justify-content:space-between; align-items:center; gap:.5rem; padding:.55rem .85rem; border-radius:999px; background:transparent; border:0; color:inherit; font:inherit; font-size:.9rem; text-align:left; cursor:pointer; opacity:.8; transition:background .2s ease, color .2s ease, opacity .2s ease; }
.x-cats button em { font-style:normal; font-size:.75rem; opacity:.6; font-variant-numeric:tabular-nums; }
.x-cats button:hover { background:rgba(255,255,255,.1); opacity:1; }
.x-cats button.on { background:var(--orange); color:#2a1204; opacity:1; font-weight:600; }
.x-legend { margin-top:auto; display:flex; align-items:center; gap:.55rem; font-size:.75rem; opacity:.7; }
.x-legend i { width:10px; height:10px; border-radius:50%; background:var(--orange); }
.x-main { flex:1; min-width:0; display:flex; flex-direction:column; }
.x-bar { display:flex; gap:.7rem; align-items:center; padding:1.25rem 1.5rem 1rem; }
.x-search { flex:1; min-width:0; border:1px solid rgba(23,19,15,.12); background:#fff; color:#17130f; border-radius:999px; padding:.7rem 1.2rem; font:inherit; font-size:.92rem; }
.x-search:focus { outline:2px solid var(--orange); border-color:transparent; }
.x-close { display:grid; place-items:center; padding:0; width:44px; height:44px; flex:none; border-radius:50%; border:0; background:rgba(23,19,15,.07); color:#17130f; font-size:1.5rem; line-height:1; cursor:pointer; transition:background .2s ease, transform .2s ease; }
.x-close:hover { background:var(--orange); transform:rotate(90deg); }
.x-scroll { flex:1; overflow:auto; padding:.4rem 1.5rem 1.6rem; }
.x-group { margin-bottom:1.6rem; }
.x-group h3 { margin:0 0 .75rem; font-size:1.4rem; font-weight:300; letter-spacing:-.025em; }
.x-group h3 em { font-style:normal; font-size:.5em; color:#a89b91; vertical-align:super; margin-left:.35rem; }
.x-chips { display:flex; flex-wrap:wrap; gap:.45rem; }
.xc { display:inline-flex; align-items:baseline; gap:.4rem; padding:.42rem .9rem; border-radius:14px; background:#fff; border:1px solid rgba(23,19,15,.08); font-size:.85rem; transition:transform .2s ease, background .2s ease, color .2s ease; }
.xc small { font-size:.68rem; color:#a89b91; }
.xc:hover { background:#17130f; color:#fff; transform:translateY(-2px); } .xc:hover small { color:#ffb680; }
.xc.key { background:var(--orange); border-color:transparent; color:#2a1204; font-weight:600; }
.xc.key:hover { background:#17130f; color:var(--orange); }
.empty.hug { width:fit-content; max-width:100%; border-radius:22px; padding:1.1rem 1.5rem; }
.x-empty { padding:3rem 0; text-align:center; color:#8c7f76; }
.x-main, .x-scroll { min-height:0; }
.x-scroll { overscroll-behavior:contain; }
.lbl { display:none; }
html:has(dialog[open]) { overflow:hidden; }
@keyframes sheet { from { transform:translateY(40px); opacity:0; } }
@media (max-width:700px) {
    .xdlg { width:100vw; max-width:100vw; height:92vh; height:92dvh; max-height:none; margin:auto 0 0; border-radius:28px 28px 0 0; }
    .xdlg[open] { flex-direction:column; animation:sheet .3s cubic-bezier(.2,.8,.2,1); }
    .x-side { width:auto; padding:1rem 1.2rem .8rem; gap:.8rem; }
    .x-side > div:first-child { display:flex; align-items:baseline; gap:.6rem; }
    .x-big { font-size:2.6rem; margin:0; } .x-cap { margin:0; } .x-kicker { order:3; margin-left:auto; }
    .x-legend { display:none; }
    .x-cats { flex-direction:row; overflow-x:auto; gap:.3rem; min-width:0; scrollbar-width:none; }
    .x-cats::-webkit-scrollbar { display:none; }
    .x-cats button { white-space:nowrap; flex:none; }
    .x-bar { padding:.9rem 1rem .7rem; } .x-scroll { padding:.2rem 1rem 1.4rem; }
    .x-group h3 { font-size:1.2rem; }
}

dialog { width:min(1000px,94vw); height:86vh; padding:0; border:1px solid var(--glass-line); border-radius:28px; background:#fbf3ec; color:#17130f; overflow:hidden; }
dialog[open] { display:flex; flex-direction:column; }
dialog::backdrop { background:rgba(20,10,4,.55); backdrop-filter:blur(4px); }
dialog iframe { flex:1; border:0; background:#fff; }

@media (max-width:1100px) { .stage { grid-template-columns:minmax(280px,360px) 1fr; } .rail { grid-column:1 / -1; flex-direction:row; flex-wrap:wrap; padding-top:0; } .rail a.first { width:56px; height:56px; font-size:.85rem; } }
@media (max-width:820px) {
    .wrap { padding:1rem 1rem 2rem; }
    .top { grid-template-columns:1fr auto; } .nav { position:fixed; left:.75rem; right:.75rem; bottom:calc(.75rem + env(safe-area-inset-bottom, 0px)); z-index:30; gap:.2rem; padding:.35rem; border-radius:999px; background:var(--glass-strong); border:1px solid var(--glass-line); backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px); box-shadow:0 10px 30px rgba(90,40,10,.18); }
    .nav > * { flex:1; min-width:0; width:auto; height:auto; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.2rem; padding:.55rem .2rem; border-radius:999px; border:0; background:transparent; font-size:.68rem; font-weight:500; line-height:1.1; white-space:nowrap; }
    .nav .active { background:var(--dark); color:#fff; }
    .nav .lbl { display:block; }
    .nav .navpill em { display:none; }
    .wrap { padding-bottom:6.5rem; }
    .stage, .more { grid-template-columns:1fr; }
    .center { order:-1; } .map { min-height:160px; }
    .cards { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<div class="wrap">

    <header class="top">
        <div class="brand"><i><?= $svg('bolt', 14) ?></i> Server Console</div>
        <nav class="nav" aria-label="Sections">
            <a class="active" href="#top"><?= $svg('home') ?> Dashboard</a>
            <a class="circle" href="#services" aria-label="Services" title="Services"><?= $svg('globe') ?><span class="lbl">Services</span></a>
            <a class="circle" href="#projects" aria-label="Projects" title="Projects"><?= $svg('star') ?><span class="lbl">Projects</span></a>
            <button class="navpill" type="button" data-open-ext><?= $svg('grid', 16) ?> Extensions <em><?= count($exts) ?></em></button>
        </nav>
        <div class="top-r">
            <span class="circle avatar" title="Running as <?= e(get_current_user()) ?>"><?= e($initial) ?></span>
        </div>
    </header>

    <main class="stage" id="top">
        <section aria-label="System status">
            <h1 class="headline">Keep track of your server<span>around the clock.</span></h1>

            <div class="hero">
                <div class="hero-main">
                <div class="hero-l"><div class="hero-label">Uptime</div>
                <div class="hero-num"><div data-up="0"><?= e($upWords[0]) ?></div><div data-up="1"><?= e($upWords[1]) ?></div></div>
                <span class="pill"><?= e($osVer) ?></span></div>
                <div class="hero-r">
                    <div>
                        <div class="la-title">Load average</div>
                        <div class="la" role="img" aria-label="Load average over 1, 5 and 15 minutes">
                            <?php foreach ($stats['load'] as $i => $lv): ?>
                            <div class="la-col"><div class="la-track"><i style="height:<?= max(8, round(sqrt(min(1, $lv / max(1, $stats['cores']))) * 100)) ?>%"></i></div><b><?= number_format($lv, 2) ?></b><small><?= ['1m', '5m', '15m'][$i] ?></small></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
                <dl class="facts">
                        <div><dt>System</dt><dd><?= e($osName) ?></dd></div>
                        <div><dt>Processor</dt><dd title="<?= e($cpuModel) ?>"><?= e($cpuModel) ?></dd></div>
                        <div><dt>Cores</dt><dd><?= (int)$stats['cores'] ?> &times; <?= e(php_uname('m')) ?></dd></div>
                    </dl>
                <?php if ($pools): ?>
                <div class="hero-pools">
                    <?php foreach ($pools as $z): ?>
                    <div class="hp">
                        <div class="hp-top"><span><?= e($z['name']) ?></span><span><?= e($z['alloc']) ?> / <?= e($z['size']) ?></span></div>
                        <div class="hbar"><i style="width:<?= $z['pct'] ?>%"></i></div>
                        <div class="hp-meta"><span><i class="hd <?= $z['health'] === 'ONLINE' ? '' : 'bad' ?>"></i><?= e(strtolower($z['health'])) ?></span><span><?= $z['pct'] ?>% used</span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>

            <div class="tiles">
                <?php metric('cpu', 'Processor', $stats['cpu']); metric('mem', 'Memory', $stats['mem']); metric('swap', 'Swap', $stats['swap']); metric('disk', 'Disk /', $stats['disk']); ?>
            </div>
        </section>

        <section class="center" aria-label="Server time">
            <div class="zone"><span><b><?= e($serverName) ?></b> &#9662;</span><span><?= e(date_default_timezone_get()) ?></span><span class="tag"><?= e($tzLabel) ?></span></div>
            <div class="clock" id="clock" data-now="<?= time() ?>" data-off="<?= $tzOffset ?>"><?= date('H:i') ?><span class="sec">:<?= date('s') ?></span></div>

            <div class="glass pulse" aria-label="Live processor and memory usage">
                <div class="pulse-head">
                    <div><span class="k">System pulse</span><span class="sub">Live, sampled every 3 seconds</span></div>
                    <div class="legend">
                        <span class="lg cpu"><i></i>Processor <b id="lgCpu">–</b></span>
                        <span class="lg mem"><i></i>Memory <b id="lgMem">–</b></span>
                    </div>
                </div>
                <div class="plot" id="plot">
                    <?php foreach ([100, 75, 50, 25, 0] as $i => $v): ?><span class="ylab" style="top:<?= $i * 25 ?>%"><?= $v ?></span><?php endforeach; ?>
                    <svg viewBox="0 0 600 200" preserveAspectRatio="none" aria-hidden="true">
                        <defs><linearGradient id="gMem" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff8f47" stop-opacity=".45"/><stop offset="1" stop-color="#ff8f47" stop-opacity="0"/></linearGradient></defs>
                        <?php foreach ([0, 50, 100, 150, 200] as $y): ?><path d="M0 <?= $y ?> H600" stroke="currentColor" stroke-opacity=".14" stroke-dasharray="3 5" fill="none"/><?php endforeach; ?>
                        <path id="arMem" fill="url(#gMem)"/>
                        <path id="lnMem" fill="none" stroke="#ff8f47" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
                        <path id="lnCpu" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
                    </svg>
                    <div class="cross" id="cross"></div><div class="tip" id="tip"></div>
                </div>
                <div class="pulse-foot"><span>Since page opened</span><span>Now</span></div>
            </div>

            <div class="cards">
                <div class="card today">
                    <div class="card-label">Today</div>
                    <div class="date"><?= date('D, F j') ?></div>
                    <div class="foot"><span class="wk">Week <?= (int)date('W') ?></span><span><?= date('Y') ?></span></div>
                </div>
                <div class="card live-card">
                    <div class="card-label">Services</div>
                    <div class="pair"><em><?= str_pad((string)$upCount, 2, '0', STR_PAD_LEFT) ?></em><em><?= str_pad((string)$svcTotal, 2, '0', STR_PAD_LEFT) ?></em></div>
                    <div class="foot"><span class="note"><?= $upCount === $svcTotal ? 'All running' : ($svcTotal - $upCount) . ' not running' ?></span><span>running / checked</span></div>
                </div>
            </div>
        </section>

        <aside class="rail" aria-label="Projects">
            <?php foreach (array_slice($projects, 0, $railMax) as $i => $p): ?>
                <a class="<?= $i === 0 ? 'first' : '' ?>" href="/<?= rawurlencode($p['name']) ?>/" title="<?= e($p['name']) ?>"><?= e(strtoupper(substr($p['name'], 0, 3))) ?></a>
            <?php endforeach; ?>
            <?php if (count($projects) > $railMax): ?><a href="#projects" title="All projects">+<?= count($projects) - $railMax ?></a><?php endif; ?>
            <?php if (!$projects): ?><span class="cap">No projects</span><?php endif; ?>
        </aside>
    </main>
    <section class="block" id="projects">
        <div class="block-h">
            <h2>Projects<em><?= count($projects) ?></em></h2>
            <?php if (count($projects) > 6): ?><input class="search" type="search" placeholder="Filter projects" data-filter="#projectList"><?php endif; ?>
        </div>
        <?php if ($projects): ?>
        <ul class="pgrid" id="projectList">
            <?php foreach ($projects as $p): ?>
            <li><a class="ptile" href="/<?= rawurlencode($p['name']) ?>/">
                <span class="mono"><?= e(strtoupper(substr($p['name'], 0, 3))) ?></span>
                <span class="pname"><?= e($p['name']) ?></span>
                <span class="pmeta"><?= $p['has_index'] ? 'Changed ' . e($p['mtime']) : 'No index file' ?></span>
                <span class="go" aria-hidden="true">&#8599;</span>
            </a></li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="glass empty hug">No projects yet. Create a folder in <code><?= e($docRoot) ?></code> and it will show up here.</div>
        <?php endif; ?>
    </section>

    <div class="lower">
        <section class="block" id="services">
            <div class="block-h"><h2>Services<em><?= $upCount ?>/<?= $svcTotal ?></em></h2></div>
            <div class="sgrid">
                <?php foreach ($services as [$name, $meta, $up, $note]):
                    if ($name === 'Web server') $big = $_SERVER['SERVER_PORT'] ?? '80';
                    elseif ($name === 'PHP') $big = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
                    else $big = preg_match('/:(\d+)$/', $meta, $pm) ? $pm[1] : '–';
                ?>
                <div class="stile <?= $up ? '' : 'off' ?>">
                    <div class="stile-top"><span><?= e($name) ?></span><span class="stat"><?= $up ? 'on' : 'off' ?></span></div>
                    <div class="port"><?= e($big) ?></div>
                    <div class="smeta"><?= e($meta) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div id="env">
            <section class="block">
                <div class="envcard">
                    <h2>Environment</h2>
                    <div class="envacts">
                        <a class="btn" href="/phpmyadmin" target="_blank" rel="noopener">phpMyAdmin</a>
                        <?php if ($isPrivate): ?><button class="btn ghost" type="button" data-open-info>PHP info</button><?php else: ?><button class="btn ghost" type="button" disabled title="Limited to private networks">PHP info</button><?php endif; ?>
                    </div>
                    <div class="envgrid">
                        <?php foreach ($env as $k => $v): ?><div class="<?= $k === 'Document root' ? 'wide' : '' ?>"><small><?= e($k) ?></small><b><?= e($v) ?></b></div><?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <footer class="foot-bar">
        <span id="live"><i class="live-dot"></i>Live, updated <b id="stamp"><?= e($stats['time']) ?></b></span>
        <span>Page built in <?= round((microtime(true) - $t0) * 1000) ?> ms</span>
    </footer>
</div>

<dialog class="xdlg" id="extDlg" aria-label="PHP extensions">
    <aside class="x-side">
        <div>
            <div class="x-kicker">PHP <?= e(PHP_VERSION) ?></div>
            <div class="x-big"><?= count($exts) ?></div>
            <div class="x-cap">extensions loaded</div>
        </div>
        <nav class="x-cats" aria-label="Categories">
            <button type="button" data-cat="all" class="on"><span>All</span><em><?= count($exts) ?></em></button>
            <?php foreach ($extOrder as $c): ?><button type="button" data-cat="<?= e($slug($c)) ?>"><span><?= e($c) ?></span><em><?= count($extGroups[$c]) ?></em></button><?php endforeach; ?>
        </nav>
        <div class="x-legend"><i></i>Common in web apps</div>
    </aside>
    <div class="x-main">
        <div class="x-bar">
            <input class="x-search" type="search" placeholder="Search <?= count($exts) ?> extensions" aria-label="Search extensions">
            <button class="x-close" type="button" aria-label="Close"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
        </div>
        <div class="x-scroll">
            <?php foreach ($extOrder as $c): ?>
            <section class="x-group" data-cat="<?= e($slug($c)) ?>">
                <h3><?= e($c) ?><em><?= count($extGroups[$c]) ?></em></h3>
                <div class="x-chips">
                    <?php foreach ($extGroups[$c] as $x): $v = phpversion($x); ?>
                    <span class="xc <?= in_array($x, $key, true) ? 'key' : '' ?>" data-name="<?= e(strtolower($x)) ?>"><?= e($x) ?><?php if ($v && $v !== PHP_VERSION): ?><small><?= e($v) ?></small><?php endif; ?></span>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
            <div class="x-empty" hidden>No extension matches that search.</div>
        </div>
    </div>
</dialog>

<?php if ($isPrivate): ?>
<dialog id="infoDlg" aria-label="PHP configuration">
    <div class="panel-h"><h2>PHP configuration</h2><button class="btn" id="infoClose" type="button">Close</button></div>
    <iframe title="phpinfo"></iframe>
</dialog>
<?php endif; ?>

<script>
(() => {
    const $ = (s, r = document) => r.querySelector(s);

    /* clock: server time in the server's own timezone */
    const clock = $('#clock'), base = clock.dataset.now * 1000 - Date.now(), off = clock.dataset.off * 1000;
    const p2 = n => String(n).padStart(2, '0');
    const tick = () => {
        const d = new Date(Date.now() + base + off);
        clock.innerHTML = `${p2(d.getUTCHours())}:${p2(d.getUTCMinutes())}<span class="sec">:${p2(d.getUTCSeconds())}</span>`;
    };
    tick(); setInterval(tick, 500);

    /* live metrics every 3s, paused while the tab is hidden */
    async function poll() {
        if (document.hidden) return;
        try {
            const d = await (await fetch('?api=stats', { cache: 'no-store' })).json();
            for (const k of ['cpu', 'mem', 'swap', 'disk']) {
                const t = $(`[data-tile="${k}"]`), s = d[k];
                t.dataset.level = s.level;
                $('.bar i', t).style.width = (s.pct ?? 0) + '%';
                $(`[data-val="${k}"]`, t).textContent = s.pct ?? '–';
                $(`[data-sub="${k}"]`, t).textContent = s.sub;
            }
            const w = d.uptime.split(' ');
            document.querySelectorAll('.la-col').forEach((c, i) => {
                $('i', c).style.height = Math.max(8, Math.sqrt(Math.min(1, d.load[i] / Math.max(1, d.cores))) * 100) + '%';
                $('b', c).textContent = d.load[i].toFixed(2);
            });
            $('[data-up="0"]').textContent = w[0] || ''; $('[data-up="1"]').textContent = w[1] || '';
            $('#stamp').textContent = d.time; push(d);
            $('#live').classList.remove('off');
        } catch (_) { $('#live').classList.add('off'); }
    }
    const N = 100, H = { cpu: [], mem: [] };
    const plot = $('#plot'), cross = $('#cross'), tip = $('#tip');
    const pts = a => a.map((v, i) => `${(i * 600 / (N - 1)).toFixed(1)} ${(200 - v * 2).toFixed(1)}`);
    const path = a => a.length > 1 ? 'M' + a.join(' L') : '';
    function push(d) {
        H.cpu.push(d.cpu.pct ?? 0); H.mem.push(d.mem.pct ?? 0);
        if (H.cpu.length > N) { H.cpu.shift(); H.mem.shift(); }
        const m = pts(H.mem);
        $('#lnCpu').setAttribute('d', path(pts(H.cpu)));
        $('#lnMem').setAttribute('d', path(m));
        $('#arMem').setAttribute('d', m.length > 1 ? path(m) + ` L${((m.length - 1) * 600 / (N - 1)).toFixed(1)} 200 L0 200 Z` : '');
        $('#lgCpu').textContent = (d.cpu.pct ?? '–') + '%'; $('#lgMem').textContent = (d.mem.pct ?? '–') + '%';
    }
    plot.addEventListener('mousemove', e => {
        const n = H.cpu.length; if (n < 2) return;
        const r = plot.getBoundingClientRect(), pad = parseFloat(getComputedStyle(plot).paddingLeft), w = r.width - pad;
        const i = Math.min(n - 1, Math.round(Math.min(1, Math.max(0, (e.clientX - r.left - pad) / w)) * (N - 1)));
        const x = pad + i / (N - 1) * w;
        cross.style.display = tip.style.display = 'block'; cross.style.left = x + 'px';
        tip.style.left = Math.min(r.width - 70, Math.max(70, x)) + 'px';
        tip.innerHTML = `Processor ${H.cpu[i]}% &nbsp; Memory ${H.mem[i]}%<br><span style="opacity:.6">${(n - 1 - i) * 3}s ago</span>`;
    });
    plot.addEventListener('mouseleave', () => { cross.style.display = tip.style.display = 'none'; });
    setInterval(poll, 3000); poll();
    document.addEventListener('visibilitychange', poll);

    document.querySelectorAll('[data-filter]').forEach(inp => inp.addEventListener('input', () => {
        const q = inp.value.trim().toLowerCase();
        document.querySelectorAll(inp.dataset.filter + ' li').forEach(li => li.hidden = q && !li.textContent.toLowerCase().includes(q));
    }));

    /* extensions modal */
    const xd = $('#extDlg');
    if (xd) {
        let cat = 'all';
        const groups = [...xd.querySelectorAll('.x-group')], search = $('.x-search', xd), empty = $('.x-empty', xd), catBtns = [...xd.querySelectorAll('.x-cats button')];
        const apply = () => {
            const q = search.value.trim().toLowerCase(); let any = false;
            groups.forEach(g => {
                let shown = 0;
                g.querySelectorAll('.xc').forEach(c => { const ok = !q || c.dataset.name.includes(q); c.hidden = !ok; if (ok) shown++; });
                const show = shown > 0 && (cat === 'all' || g.dataset.cat === cat);
                g.hidden = !show; if (show) any = true;
            });
            empty.hidden = any;
        };
        catBtns.forEach(b => b.addEventListener('click', () => { cat = b.dataset.cat; catBtns.forEach(x => x.classList.toggle('on', x === b)); apply(); $('.x-scroll', xd).scrollTop = 0; }));
        search.addEventListener('input', apply);
        document.querySelectorAll('[data-open-ext]').forEach(b => b.addEventListener('click', () => { search.value = ''; apply(); xd.showModal(); if (matchMedia('(pointer:fine)').matches) search.focus(); }));
        $('.x-close', xd).addEventListener('click', () => xd.close());
        xd.addEventListener('click', e => { if (e.target === xd) xd.close(); });
    }

    /* phpinfo loads only when opened */
    const dlg = $('#infoDlg');
    if (dlg) {
        const open = () => { const f = $('iframe', dlg); if (!f.src) f.src = '?view=phpinfo'; dlg.showModal(); };
        document.querySelectorAll('[data-open-info]').forEach(b => b.addEventListener('click', open));
        $('#infoClose').addEventListener('click', () => dlg.close());
        dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
    }
})();
</script>
</body>
</html>
