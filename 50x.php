<?php
/**
 * Server Console - 5xx error page (FreeBSD / nginx)
 * Same theme as index.php and 40x.php. Detects the real status code, shows a live
 * health snapshot, gives the visitor a reference id, and (for 502/503/504)
 * re-checks the original URL by itself and reloads once it is back.
 *
 * nginx:
 *   error_page 500 501 502 503 504 505 507 508 /50x.php;
 *   location = /50x.php {
 *       internal;
 *       include        fastcgi_params;
 *       fastcgi_pass   unix:/var/run/php-fpm.sock;   # match your setup
 *       fastcgi_param  SCRIPT_FILENAME $document_root/50x.php;
 *       fastcgi_param  ERROR_STATUS    $status;
 *   }
 * Note: if PHP-FPM itself is down, nginx cannot run this file. Keep a static
 * fallback (e.g. /50x.html) for that case.
 * (Remove "internal;" temporarily to preview with /50x.php?code=502)
 *
 * License: CDDL 1.0 (same as index.php) http://www.opensource.org/licenses/cddl1.php
 */

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ---------- what happened ---------- */
$codes = [
    500 => ['Server error', 'Something broke on our side.', 'It is not your fault. Try again in a moment.', 'Read /var/log/nginx/error.log and the PHP log. Then: php -l script.php, service php_fpm status.'],
    501 => ['Not implemented', 'The server does not support this action.', 'Try reaching this page another way.', 'The backend does not know this request method or feature.'],
    502 => ['Bad gateway', 'The server behind this one did not answer properly.', 'This is usually temporary. This page keeps checking for you.', 'Upstream is down or unreachable. Try: service php_fpm status; sockstat -l | grep php. Make sure fastcgi_pass matches the socket or port, and its permissions.'],
    503 => ['Temporarily unavailable', 'The server is busy or under maintenance.', 'Give it a moment. This page keeps checking for you.', 'Check limit_req / limit_conn, php-fpm pm.max_children, and any maintenance flag file.'],
    504 => ['Gateway timeout', 'The server behind this one took too long.', 'A slow request or a busy backend. This page keeps checking for you.', 'Raise fastcgi_read_timeout and max_execution_time, or find the slow query.'],
    505 => ['HTTP version not supported', 'The server cannot speak the protocol version you used.', 'Try again with a current browser.', 'Check the listen directives (http2 / http3) in nginx.'],
    507 => ['Out of storage', 'The server has no room left to save this.', 'Nothing you did caused this. Try again later.', 'The disk is full. Try: df -h; zfs list; and clear logs or old backups.'],
    508 => ['Loop detected', 'The server got stuck going in circles.', 'Try again later.', 'Look for a rewrite or redirect loop in the nginx config.'],
];

$code = 0;
foreach ([$_SERVER['ERROR_STATUS'] ?? null, $_SERVER['REDIRECT_STATUS'] ?? null, http_response_code()] as $c) {
    $c = (int)$c;
    if ($c >= 500 && $c < 600) { $code = $c; break; }
}
$preview = false;
if (!$code) {
    $c = (int)($_GET['code'] ?? 0);
    $code = ($c >= 500 && $c < 600) ? $c : 500;
    $preview = true;
}
[$title, $headline, $hint, $dev] = $codes[$code] ?? ['Server error', 'Something went wrong on the server.', 'Try again in a moment.', 'See /var/log/nginx/error.log for details.'];
$tone = in_array($code, [503, 504], true) ? 'yellow' : 'red';
$auto = !$preview && in_array($code, [502, 503, 504], true);

/* ---------- request facts ---------- */
$serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$clientIp   = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$isPrivate  = filter_var($clientIp, FILTER_VALIDATE_IP)
    && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
$uri    = mb_substr((string)($_SERVER['REQUEST_URI'] ?? '/'), 0, 300);
$path   = rawurldecode((string)(parse_url($uri, PHP_URL_PATH) ?: '/'));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$tzOff  = (int)date('Z');
$ref    = strtoupper(substr(md5(uniqid('', true)), 0, 8));
if (!$preview) @error_log("[50x $ref] $code $method $uri client $clientIp");

/* ---------- health snapshot (cheap, defensive) ---------- */
function sh(string $cmd): ?string {
    if (!function_exists('shell_exec')) return null;
    $o = @shell_exec($cmd . ' 2>/dev/null');
    return ($o === null || $o === false) ? null : trim($o);
}
function sysctlv(string $oid): ?string { return sh('sysctl -n ' . escapeshellarg($oid)); }
function fmtBytes($b): string {
    if ($b <= 0) return '0 B';
    $u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $i = min((int)floor(log($b, 1024)), count($u) - 1);
    return round($b / pow(1024, $i), 1) . ' ' . $u[$i];
}
function pct($u, $t): ?int { return $t > 0 ? (int)round($u / $t * 100) : null; }
function lvl(?int $p): string { return $p === null ? 'na' : ($p >= 85 ? 'crit' : ($p >= 70 ? 'warn' : 'ok')); }
function probe(string $host, int $port, float $t = 0.25): bool {
    $c = @fsockopen($host, $port, $en, $es, $t);
    if ($c) { fclose($c); return true; }
    return false;
}

$bsd = stripos(PHP_OS, 'BSD') !== false;
$win = stripos(PHP_OS, 'WIN') === 0;
$cores = 1; $memT = 0; $memU = 0;
if ($bsd) {
    $cores = max(1, (int)sysctlv('hw.ncpu'));
    $memT  = (int)sysctlv('hw.physmem');
    $ps    = (int)(sysctlv('hw.pagesize') ?: 4096);
    $rec   = ((int)sysctlv('vm.stats.vm.v_free_count') + (int)sysctlv('vm.stats.vm.v_inactive_count')) * $ps + (int)sysctlv('kstat.zfs.misc.arcstats.size');
    $memU  = max(0, $memT - $rec);
} elseif (!$win) {
    if (is_readable('/proc/cpuinfo')) { $n = preg_match_all('/^processor/m', file_get_contents('/proc/cpuinfo')); if ($n > 0) $cores = $n; }
    if (is_readable('/proc/meminfo')) {
        $mi = file_get_contents('/proc/meminfo');
        $g = fn($k) => preg_match("/^$k:\s+(\d+)/m", $mi, $x) ? (int)$x[1] * 1024 : 0;
        $memT = $g('MemTotal'); $memU = $memT - $g('MemAvailable');
    }
}
$load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
$root = $win ? __DIR__ : '/';
$dt = (float)@disk_total_space($root); $df = (float)@disk_free_space($root);
$meters = [
    ['Processor', $win ? null : (int)min(100, round($load[0] / $cores * 100)), sprintf('load %.2f, %d core%s', $load[0], $cores, $cores > 1 ? 's' : '')],
    ['Memory', pct($memU, $memT), $memT ? fmtBytes($memU) . ' of ' . fmtBytes($memT) : 'not available'],
    ['Disk', pct($dt - $df, $dt), $dt ? fmtBytes($dt - $df) . ' of ' . fmtBytes($dt) : 'not available'],
];
$dbHost = ini_get('mysqli.default_host') ?: '127.0.0.1';
$svcs = [
    ['Web', true], ['PHP', true],
    ['MySQL', probe($dbHost, 3306) || file_exists('/tmp/mysql.sock') || file_exists('/var/run/mysql/mysql.sock')],
    ['PostgreSQL', probe('127.0.0.1', 5432) || file_exists('/tmp/.s.PGSQL.5432')],
    ['Redis', probe('127.0.0.1', 6379)],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $code ?> <?= e($title) ?> - <?= e($serverName) ?></title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<style>
:root {
    --bg: linear-gradient(120deg, #f4eee3 0%, #f8e2d2 45%, #f5c6ab 100%);
    --ink:#17130f; --muted:#8c7f76; --soft:#b9aca2; --glass:rgba(255,255,255,.5); --glass-line:rgba(255,255,255,.75);
    --dark:#1c1510; --orange:#ff8f47; --yellow:#f3ee2f; --crit:#e5443a; --inv-bg:#17130f; --inv-fg:#fff;
    --sans: "Helvetica Neue", "Inter", "Segoe UI", system-ui, sans-serif;
    --mono: ui-monospace, "SF Mono", Menlo, Consolas, "DejaVu Sans Mono", monospace;
}
@media (prefers-color-scheme: dark) {
    :root { --bg: linear-gradient(120deg, #17110d 0%, #2a1a12 55%, #40251a 100%);
        --ink:#f6eee7; --muted:#a29488; --soft:#6b5e54; --glass:rgba(255,255,255,.07); --glass-line:rgba(255,255,255,.12); --inv-bg:#f6eee7; --inv-fg:#17130f; }
}
* { box-sizing:border-box; }
html { color-scheme:light dark; }
body { margin:0; min-height:100vh; background:var(--bg) fixed; color:var(--ink); font:15px/1.45 var(--sans); display:flex; flex-direction:column; }
a { color:inherit; text-decoration:none; }
button { font:inherit; color:inherit; }
[hidden] { display:none !important; }
:focus-visible { outline:2px solid var(--ink); outline-offset:3px; }
.wrap { width:100%; max-width:1180px; margin:0 auto; padding:1.5rem 2rem 2rem; flex:1; display:flex; flex-direction:column; }

.top { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
.brand { display:flex; align-items:center; gap:.5rem; font-weight:500; font-size:.95rem; }
.brand i { width:26px; height:26px; border-radius:50%; background:var(--ink); color:var(--orange); display:grid; place-items:center; }
.btn { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; padding:.72rem 1.3rem; border-radius:999px; background:var(--inv-bg); color:var(--inv-fg); border:1px solid transparent; font-weight:600; font-size:.92rem; cursor:pointer;
    transition:transform .25s ease, background .25s ease, color .25s ease, border-color .25s ease; }
.btn:hover { background:var(--orange); color:#2a1204; transform:translateY(-2px); }
.btn.primary { background:var(--orange); color:#2a1204; }
.btn.primary:hover { background:var(--yellow); }
.btn.ghost { background:var(--glass); color:var(--ink); border-color:var(--glass-line); }
.btn.ghost:hover { background:var(--orange); color:#2a1204; border-color:var(--orange); }

.stage { flex:1; display:grid; grid-template-columns:1.1fr .9fr; gap:3rem; align-items:center; padding:2rem 0; }
.pill { display:inline-block; font-size:.78rem; font-weight:600; padding:.22rem .8rem; border-radius:999px; background:var(--crit); color:#fff; }
.pill.yellow { background:var(--yellow); color:#2a1204; }
.code { margin:.6rem 0 0; font-size:clamp(6rem,21vw,14.5rem); line-height:.88; font-weight:200; letter-spacing:-.02em; font-variant-numeric:tabular-nums; white-space:nowrap; }
.code .dim { color:var(--soft); }
h1 { margin:1.2rem 0 .5rem; font-size:clamp(1.6rem,3.2vw,2.5rem); line-height:1.1; font-weight:300; letter-spacing:-.03em; }
.hint { margin:0; color:var(--muted); max-width:34rem; }
.acts { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.6rem; }

.auto { margin-top:1.3rem; max-width:34rem; padding:.85rem 1.1rem 1rem; border-radius:20px; background:var(--glass); border:1px solid var(--glass-line); }
.auto-row { display:flex; justify-content:space-between; align-items:center; gap:1rem; font-size:.88rem; }
.auto-row button { border:0; background:transparent; font-weight:600; font-size:.85rem; cursor:pointer; padding:.1rem .3rem; border-radius:6px; }
.auto-row button:hover { color:var(--orange); }
.abar { height:4px; border-radius:4px; background:rgba(0,0,0,.08); margin-top:.7rem; overflow:hidden; }
.abar i { display:block; height:100%; width:0; background:var(--orange); border-radius:4px; transition:width 1s linear; }
.note { margin-top:1.2rem; padding:.9rem 1.2rem; border-radius:20px; border:1.5px dashed var(--soft); color:var(--muted); font-size:.85rem; max-width:34rem; }
.note b { color:var(--ink); font-weight:600; }

.card { position:relative; border-radius:30px; padding:1.4rem 1.5rem 1.2rem; color:#f6eee7; overflow:hidden;
    background:radial-gradient(120% 90% at 90% 100%, #7a2a20 0%, transparent 60%), radial-gradient(circle at 15% 0%, #3a2a1f 0%, #1c1510 65%); }
.card.yellow { background:radial-gradient(120% 90% at 90% 100%, #6b6a14 0%, transparent 60%), radial-gradient(circle at 15% 0%, #3a2a1f 0%, #1c1510 65%); }
.card::after { content:''; position:absolute; inset:0; background:radial-gradient(rgba(255,255,255,.14) 1px, transparent 1.4px) 0 0/10px 10px; -webkit-mask-image:linear-gradient(120deg, transparent 40%, #000 100%); mask-image:linear-gradient(120deg, transparent 40%, #000 100%); pointer-events:none; }
.card > * { position:relative; z-index:1; }
.card h2 { margin:0 0 1rem; font-size:1.5rem; font-weight:300; letter-spacing:-.03em; }
.meters { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; }
.mt { display:flex; justify-content:space-between; align-items:baseline; font-size:.82rem; }
.mt b { font-weight:300; font-size:1.5rem; letter-spacing:-.03em; font-variant-numeric:tabular-nums; }
.mbar { height:5px; border-radius:5px; background:rgba(255,255,255,.14); margin:.45rem 0 .35rem; overflow:hidden; }
.mbar i { display:block; height:100%; border-radius:5px; background:var(--orange); }
.meter[data-level="warn"] .mbar i { background:#f0b323; } .meter[data-level="crit"] .mbar i { background:#ff6b5e; } .meter[data-level="na"] .mbar i { background:var(--soft); }
.meter small { font-size:.7rem; opacity:.6; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.svcs { display:flex; flex-wrap:wrap; gap:.4rem; margin:1.1rem 0 .4rem; padding-top:.95rem; border-top:1px solid rgba(255,255,255,.14); }
.svc { display:inline-flex; align-items:center; gap:.45rem; padding:.2rem .7rem; border-radius:999px; background:rgba(255,255,255,.1); font-size:.78rem; }
.svc i { width:7px; height:7px; border-radius:50%; background:#3fd28f; }
.svc.off { opacity:.55; } .svc.off i { background:var(--soft); }
.grid { display:grid; grid-template-columns:1fr 1fr; }
.grid > div { border-top:1px solid rgba(255,255,255,.14); padding:.7rem 0 .9rem; min-width:0; }
.grid > div:nth-child(even):not(.wide) { padding-left:1.2rem; }
.grid .wide { grid-column:1 / -1; }
.grid small { display:block; font-size:.72rem; opacity:.6; }
.grid b { font-weight:300; font-size:1.15rem; letter-spacing:-.02em; overflow-wrap:anywhere; font-variant-numeric:tabular-nums; }
.grid .path b { font-size:1rem; }
.grid .refid b { font-family:var(--mono); font-size:1rem; letter-spacing:.06em; }

.foot { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:.8rem 1.5rem; margin-top:2rem; color:var(--muted); font-size:.8rem; }
.pv { display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; }
.pv a { padding:.15rem .6rem; border-radius:999px; background:var(--glass); border:1px solid var(--glass-line); font-variant-numeric:tabular-nums; transition:background .2s ease, color .2s ease; }
.pv a:hover, .pv a.on { background:var(--orange); color:#2a1204; border-color:var(--orange); }
code { font-family:var(--mono); font-size:.92em; }

@media (max-width:900px) {
    .stage { grid-template-columns:1fr; gap:2rem; padding:1rem 0; }
    .wrap { padding:1rem 1rem 1.5rem; }
}
@media (max-width:520px) {
    .acts .btn { flex:1 1 100%; }
    .meters { grid-template-columns:1fr; }
    .grid { grid-template-columns:1fr; }
    .grid > div:nth-child(even):not(.wide) { padding-left:0; }
}
@media (prefers-reduced-motion: reduce) { * { transition:none !important; } }
</style>
</head>
<body>
<div class="wrap">

    <header class="top">
        <a class="brand" href="/"><i><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 3L5 14h6l-1 7 8-11h-6z"/></svg></i> Server Console</a>
        <a class="btn ghost" href="/">Dashboard</a>
    </header>

    <main class="stage">
        <section>
            <span class="pill <?= $tone === 'yellow' ? 'yellow' : '' ?>"><?= e($title) ?></span>
            <div class="code" aria-label="Error <?= $code ?>"><?= substr((string)$code, 0, 2) ?><span class="dim"><?= substr((string)$code, 2) ?></span></div>
            <h1><?= e($headline) ?></h1>
            <p class="hint"><?= e($hint) ?></p>

            <div class="acts">
                <button class="btn primary" id="retry" type="button">Try again</button>
                <a class="btn ghost" href="/">Back to dashboard</a>
                <button class="btn ghost" id="back" type="button" hidden>Go back</button>
            </div>

            <?php if ($auto): ?>
            <div class="auto" id="auto" aria-live="polite">
                <div class="auto-row"><span id="atxt">Checking again in 5s</span><button id="atog" type="button">Pause</button></div>
                <div class="abar"><i id="abar"></i></div>
            </div>
            <?php endif; ?>

            <?php if ($isPrivate): ?>
            <div class="note"><b>For developers.</b> <?= e($dev) ?></div>
            <?php endif; ?>
        </section>

        <aside class="card <?= $tone === 'yellow' ? 'yellow' : '' ?>" aria-label="Server health">
            <h2>Server health</h2>
            <div class="meters">
                <?php foreach ($meters as [$label, $p, $sub]): ?>
                <div class="meter" data-level="<?= lvl($p) ?>">
                    <div class="mt"><span><?= e($label) ?></span><b><?= $p === null ? '–' : $p . '%' ?></b></div>
                    <div class="mbar"><i style="width:<?= (int)$p ?>%"></i></div>
                    <small title="<?= e($sub) ?>"><?= e($sub) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="svcs" aria-label="Services">
                <?php foreach ($svcs as [$n, $up]): ?><span class="svc <?= $up ? '' : 'off' ?>"><i></i><?= e($n) ?></span><?php endforeach; ?>
            </div>
            <div class="grid">
                <div class="refid"><small>Reference</small><b><?= e($ref) ?></b></div>
                <div><small>Server time</small><b id="stime" data-now="<?= time() ?>" data-off="<?= $tzOff ?>"><?= date('H:i:s') ?></b></div>
                <div class="wide path"><small><?= e($method) ?> &middot; <?= e($serverName) ?></small><b><?= e($path) ?></b></div>
            </div>
        </aside>
    </main>

    <footer class="foot">
        <span><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'Server') ?> &middot; PHP <?= e(PHP_VERSION) ?><?php if (!$preview): ?> &middot; quote reference <code><?= e($ref) ?></code> when reporting this<?php endif; ?></span>
        <?php if ($isPrivate && $preview): ?>
        <span class="pv" aria-label="Preview other errors">Preview
            <?php foreach (array_keys($codes) as $k): ?><a class="<?= $k === $code ? 'on' : '' ?>" href="?code=<?= $k ?>"><?= $k ?></a><?php endforeach; ?>
        </span>
        <?php endif; ?>
    </footer>
</div>

<script>
(() => {
    const $ = s => document.querySelector(s);

    const t = $('#stime');
    if (t) {
        const base = t.dataset.now * 1000 - Date.now(), off = t.dataset.off * 1000, p = n => String(n).padStart(2, '0');
        const tick = () => { const d = new Date(Date.now() + base + off); t.textContent = `${p(d.getUTCHours())}:${p(d.getUTCMinutes())}:${p(d.getUTCSeconds())}`; };
        tick(); setInterval(tick, 1000);
    }

    const back = $('#back');
    if (back) { if (history.length > 1) back.hidden = false; back.addEventListener('click', () => history.back()); }
    $('#retry').addEventListener('click', () => location.reload());

    /* 502 / 503 / 504: keep checking the original URL, reload when it answers again */
    const box = $('#auto');
    if (box) {
        let step = 5, left = 5, wait = 5, paused = false, busy = false;
        const txt = $('#atxt'), bar = $('#abar'), tog = $('#atog');
        const paint = () => {
            txt.textContent = busy ? 'Checking now...' : paused ? 'Auto-check paused' : `Checking again in ${left}s`;
            bar.style.width = (busy || paused) ? '0' : (100 * (1 - left / wait)) + '%';
            tog.textContent = paused ? 'Resume' : 'Pause';
        };
        const check = async () => {
            busy = true; paint();
            try {
                const r = await fetch(location.pathname + location.search, { method: 'HEAD', cache: 'no-store', redirect: 'manual' });
                if (r.type === 'opaqueredirect' || (r.status > 0 && r.status < 500)) { txt.textContent = 'Back online, reloading'; location.reload(); return; }
            } catch (_) {}
            busy = false; step = Math.min(step + 3, 15); wait = left = step; paint();
        };
        setInterval(() => { if (paused || busy) return; left--; left <= 0 ? check() : paint(); }, 1000);
        tog.addEventListener('click', () => { paused = !paused; paint(); });
        paint();
    }
})();
</script>
</body>
</html>
