<?php
/**
 * Server Console - 4xx error page (FreeBSD / nginx)
 * Same theme as index.php. Detects the real status code, echoes the request,
 * suggests matching project folders, and adapts its actions to the error.
 *
 * nginx:
 *   error_page 400 401 403 404 405 408 410 413 414 429 431 /40x.php;
 *   location = /40x.php {
 *       internal;
 *       include        fastcgi_params;
 *       fastcgi_pass   unix:/var/run/php-fpm.sock;   # match your setup
 *       fastcgi_param  SCRIPT_FILENAME $document_root/40x.php;
 *       fastcgi_param  ERROR_STATUS    $status;
 *   }
 * (Remove "internal;" temporarily if you want to preview with /40x.php?code=403)
 *
 * License: CDDL 1.0 (same as index.php) http://www.opensource.org/licenses/cddl1.php
 */

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ---------- what happened ---------- */
$codes = [
    400 => ['Bad request', 'The server could not understand this request.', 'Check the address for typos or odd characters, then try again.', 'Look for malformed headers or an oversized cookie. Log: /var/log/nginx/error.log'],
    401 => ['Sign in required', 'This page needs you to sign in first.', 'Authenticate, then come back to this address.', 'Check auth_basic / auth_request in the matching location block.'],
    403 => ['Access denied', 'The door is here, but it is locked.', 'The file exists, but you are not allowed to open it.', 'Check ownership and the execute bit on every parent folder, e.g. chown -R www:www and chmod 755 on directories. Also check for an index file.'],
    404 => ['Not found', 'Nothing lives at this address.', 'The link may be old or mistyped, or the project was moved.', 'Confirm the file exists under the root (/usr/local/www/nginx) and that try_files / location rules match.'],
    405 => ['Method not allowed', 'This address does not accept that kind of request.', 'Try opening it as a normal page instead of submitting data.', 'The location may only allow GET/HEAD, or PHP-FPM rejected the verb.'],
    408 => ['Request timed out', 'The request took too long to arrive.', 'Check your connection and try again.', 'Raise client_header_timeout / client_body_timeout if this is a slow upload.'],
    410 => ['Gone for good', 'This page used to exist and was removed on purpose.', 'There is no forwarding address. Try the dashboard instead.', 'A "return 410" rule is probably in the nginx config.'],
    413 => ['Upload too large', 'The file you sent is bigger than the server allows.', 'Try a smaller file.', 'Raise client_max_body_size in nginx and upload_max_filesize / post_max_size in php.ini.'],
    414 => ['Address too long', 'The URL is longer than the server accepts.', 'Shorten the address or remove extra parameters.', 'Raise large_client_header_buffers if the long URL is legitimate.'],
    429 => ['Slow down', 'Too many requests in a short time.', 'Wait a moment, then try again.', 'Check limit_req / limit_conn zones in the nginx config.'],
    431 => ['Headers too large', 'The request headers are bigger than the server accepts.', 'Clear cookies for this site and try again.', 'Raise large_client_header_buffers, or shrink the cookie.'],
];

$code = 0;
foreach ([$_SERVER['ERROR_STATUS'] ?? null, $_SERVER['REDIRECT_STATUS'] ?? null, http_response_code()] as $c) {
    $c = (int)$c;
    if ($c >= 400 && $c < 500) { $code = $c; break; }
}
$preview = false;
if (!$code) {
    $c = (int)($_GET['code'] ?? 0);
    $code = ($c >= 400 && $c < 500) ? $c : 404;
    $preview = true;
}
[$title, $headline, $hint, $dev] = $codes[$code] ?? ['Client error', 'Something went wrong with this request.', 'Check the address and try again.', 'See /var/log/nginx/error.log for details.'];
$tone  = in_array($code, [401, 403], true) ? 'yellow' : 'orange';
$retry = in_array($code, [408, 429], true);

/* ---------- request + server facts ---------- */
$serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$clientIp   = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$docRoot    = $_SERVER['DOCUMENT_ROOT'] ?? '';
$isPrivate  = filter_var($clientIp, FILTER_VALIDATE_IP)
    && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
$uri     = mb_substr((string)($_SERVER['REQUEST_URI'] ?? '/'), 0, 300);
$path    = rawurldecode((string)(parse_url($uri, PHP_URL_PATH) ?: '/'));
$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$referer = mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 120);
$tzOff   = (int)date('Z');

/* ---------- project folders: "did you mean" or "jump to" ---------- */
$skip = ['html', 'images', 'nginx-dist', 'cgi-bin'];
$dirs = [];
if ($docRoot && is_dir($docRoot)) {
    foreach (scandir($docRoot) as $d) {
        if ($d[0] === '.' || !is_dir("$docRoot/$d") || in_array($d, $skip, true)) continue;
        $dirs[] = $d;
    }
}
$seg = strtolower(mb_substr(pathinfo(explode('/', trim($path, '/'))[0], PATHINFO_FILENAME), 0, 60));
$sugg = [];
if ($seg !== '' && in_array($code, [404, 410], true)) {
    foreach ($dirs as $d) {
        $dl = strtolower($d);
        $lev = levenshtein($seg, mb_substr($dl, 0, 200));
        if ($lev <= max(2, intdiv(strlen($dl), 3)) || (strlen($seg) >= 3 && str_contains($dl, $seg))) $sugg[$d] = $lev;
    }
    asort($sugg);
    $sugg = array_slice(array_keys($sugg), 0, 3);
}
$isSugg = (bool)$sugg;
$tiles  = $isSugg ? $sugg : array_slice(array_values(array_filter($dirs, fn($d) => strcasecmp($d, 'phpmyadmin') !== 0)), 0, 6);
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
    --dark:#1c1510; --orange:#ff8f47; --yellow:#f3ee2f; --inv-bg:#17130f; --inv-fg:#fff;
    --sans: "Helvetica Neue", "Inter", "Segoe UI", system-ui, sans-serif;
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
.pill { display:inline-block; font-size:.78rem; font-weight:600; padding:.22rem .8rem; border-radius:999px; background:var(--orange); color:#2a1204; }
.pill.yellow { background:var(--yellow); }
.code { margin:.6rem 0 0; font-size:clamp(6rem,21vw,14.5rem); line-height:.88; font-weight:200; letter-spacing:-.02em; font-variant-numeric:tabular-nums; white-space:nowrap; }
.code .dim { color:var(--soft); }
h1 { margin:1.2rem 0 .5rem; font-size:clamp(1.6rem,3.2vw,2.5rem); line-height:1.1; font-weight:300; letter-spacing:-.03em; }
.hint { margin:0; color:var(--muted); max-width:34rem; }
.acts { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.6rem; }

.reqcard { position:relative; border-radius:30px; padding:1.4rem 1.5rem 1.2rem; color:#f6eee7; overflow:hidden;
    background:radial-gradient(120% 90% at 90% 100%, #6d3b20 0%, transparent 60%), radial-gradient(circle at 15% 0%, #3a2a1f 0%, #1c1510 65%); }
.reqcard.yellow { background:radial-gradient(120% 90% at 90% 100%, #6b6a14 0%, transparent 60%), radial-gradient(circle at 15% 0%, #3a2a1f 0%, #1c1510 65%); }
.reqcard::after { content:''; position:absolute; inset:0; background:radial-gradient(rgba(255,255,255,.14) 1px, transparent 1.4px) 0 0/10px 10px; -webkit-mask-image:linear-gradient(120deg, transparent 40%, #000 100%); mask-image:linear-gradient(120deg, transparent 40%, #000 100%); pointer-events:none; }
.reqcard > * { position:relative; z-index:1; }
.reqcard h2 { margin:0 0 .9rem; font-size:1.5rem; font-weight:300; letter-spacing:-.03em; }
.grid { display:grid; grid-template-columns:1fr 1fr; }
.grid > div { border-top:1px solid rgba(255,255,255,.14); padding:.7rem 0 .9rem; min-width:0; }
.grid > div:nth-child(even):not(.wide) { padding-left:1.2rem; }
.grid .wide { grid-column:1 / -1; }
.grid small { display:block; font-size:.72rem; opacity:.6; }
.grid b { font-weight:300; font-size:1.15rem; letter-spacing:-.02em; overflow-wrap:anywhere; font-variant-numeric:tabular-nums; }
.grid .path b { font-size:1rem; }

.block { margin-top:1rem; }
.block-h { display:flex; align-items:baseline; gap:.5rem; margin-bottom:1rem; }
.block-h h2 { margin:0; font-size:clamp(1.4rem,2.6vw,2rem); font-weight:300; letter-spacing:-.03em; }
.block-h span { color:var(--muted); font-size:.9rem; }
.pgrid { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:.9rem; }
.ptile { position:relative; display:flex; flex-direction:column; gap:.15rem; min-height:140px; padding:1rem 1.15rem; border-radius:26px; background:var(--glass); border:1px solid var(--glass-line); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); transition:transform .25s ease, background .25s ease, color .25s ease, box-shadow .25s ease; }
.mono { width:46px; height:46px; border-radius:50%; display:grid; place-items:center; font-size:.8rem; font-weight:600; letter-spacing:.05em; background:var(--dark); color:var(--orange); margin-bottom:auto; }
.pname { font-size:1.15rem; letter-spacing:-.02em; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pmeta { font-size:.78rem; color:var(--muted); }
.go { position:absolute; top:1rem; right:1.15rem; font-size:1.2rem; opacity:.3; transition:transform .25s ease, opacity .25s ease; }
.ptile:hover { background:var(--orange); color:#2a1204; transform:translateY(-4px); box-shadow:0 18px 34px rgba(140,70,20,.25); }
.ptile:hover .pmeta { color:rgba(42,18,4,.7); } .ptile:hover .go { opacity:1; transform:translate(3px,-3px); }

.note { margin-top:1.4rem; padding:.9rem 1.2rem; border-radius:20px; border:1.5px dashed var(--soft); color:var(--muted); font-size:.85rem; max-width:34rem; }
.note b { color:var(--ink); font-weight:600; }

.foot { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:.8rem 1.5rem; margin-top:2rem; color:var(--muted); font-size:.8rem; }
.pv { display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; }
.pv a { padding:.15rem .6rem; border-radius:999px; background:var(--glass); border:1px solid var(--glass-line); font-variant-numeric:tabular-nums; transition:background .2s ease, color .2s ease; }
.pv a:hover, .pv a.on { background:var(--orange); color:#2a1204; border-color:var(--orange); }

@media (max-width:900px) {
    .stage { grid-template-columns:1fr; gap:2rem; padding:1rem 0; }
    .wrap { padding:1rem 1rem 1.5rem; }
}
@media (max-width:480px) {
    .acts .btn { flex:1 1 100%; }
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
                <a class="btn primary" href="/">Back to dashboard</a>
                <button class="btn ghost" id="back" type="button" hidden>Go back</button>
                <?php if ($retry): ?><button class="btn ghost" id="retry" type="button">Try again</button><?php endif; ?>
            </div>

            <?php if ($isPrivate): ?>
            <div class="note"><b>For developers.</b> <?= e($dev) ?></div>
            <?php endif; ?>
        </section>

        <aside class="reqcard <?= $tone === 'yellow' ? 'yellow' : '' ?>" aria-label="Request details">
            <h2>Request</h2>
            <div class="grid">
                <div><small>Method</small><b><?= e($method) ?></b></div>
                <div><small>Server time</small><b id="stime" data-now="<?= time() ?>" data-off="<?= $tzOff ?>"><?= date('H:i:s') ?></b></div>
                <div><small>Host</small><b><?= e($serverName) ?></b></div>
                <div><small>Your address</small><b><?= e($clientIp) ?></b></div>
                <div class="wide path"><small>Path</small><b><?= e($path) ?></b></div>
                <?php if ($referer !== ''): ?><div class="wide path"><small>Came from</small><b><?= e($referer) ?></b></div><?php endif; ?>
            </div>
        </aside>
    </main>

    <?php if ($tiles): ?>
    <section class="block" aria-label="<?= $isSugg ? 'Suggestions' : 'Projects' ?>">
        <div class="block-h">
            <h2><?= $isSugg ? 'Did you mean' : 'Jump to a project' ?></h2>
            <?php if ($isSugg): ?><span>similar to &ldquo;<?= e($seg) ?>&rdquo;</span><?php endif; ?>
        </div>
        <ul class="pgrid">
            <?php foreach ($tiles as $d): ?>
            <li><a class="ptile" href="/<?= rawurlencode($d) ?>/">
                <span class="mono"><?= e(strtoupper(substr($d, 0, 3))) ?></span>
                <span class="pname"><?= e($d) ?></span>
                <span class="pmeta">/<?= e($d) ?>/</span>
                <span class="go" aria-hidden="true">&#8599;</span>
            </a></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <footer class="foot">
        <span><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'Server') ?> &middot; PHP <?= e(PHP_VERSION) ?></span>
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
    const retry = $('#retry');
    if (retry) retry.addEventListener('click', () => location.reload());
})();
</script>
</body>
</html>
