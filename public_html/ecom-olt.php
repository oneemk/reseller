<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session_name']);
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']);
    session_start();
}
try {
    $db = new PDO(
        'mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset='.$config['db']['charset'],
        $config['db']['user'], $config['db']['pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) { http_response_code(500); exit('Database connection failed.'); }

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function require_login(): array { if (empty($_SESSION['user'])) { header('Location: index.php?page=login'); exit; } return $_SESSION['user']; }
function csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid CSRF token'); } }
function get_olt(PDO $db, int $id): array {
    $q=$db->prepare('SELECT * FROM olts WHERE id=? AND active=1'); $q->execute([$id]); $o=$q->fetch();
    if (!$o) throw new RuntimeException('OLT not found'); return $o;
}
function allowed(PDO $db, array $u, int $id, bool $manage=false): bool {
    if ($u['role']==='super_admin') return true;
    $q=$db->prepare("SELECT 1 FROM resource_permissions WHERE reseller_id=? AND resource_type='olt' AND resource_id=? AND can_view=1 AND ".($manage?'can_manage=1':'1=1'));
    $q->execute([$u['id'],$id]); return (bool)$q->fetchColumn();
}
function olt_url(array $o, string $module, array $extra=[]): string {
    $scheme = in_array(($o['web_scheme'] ?? 'http'), ['http','https'], true) ? $o['web_scheme'] : 'http';
    $host = trim((string)$o['host']);
    if (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[A-Za-z0-9.-]+$/', $host)) throw new RuntimeException('Invalid OLT host.');
    $port=(int)($o['web_port'] ?? 9092); if ($port<1 || $port>65535) throw new RuntimeException('Invalid OLT web port.');
    $path=$o['web_base_path'] ?? '/cgi-bin/h.cgi'; if ($path==='' || $path[0]!=='/') $path='/cgi-bin/h.cgi';
    $query=array_merge(['module'=>$module],$extra);
    return $scheme.'://'.$host.':'.$port.$path.'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
}
function http_get(string $url): array {
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Accept: application/json, text/plain, */*','Cache-Control: no-cache'],CURLOPT_USERAGENT=>'ISP-Reseller-ECOM-Monitor/1.0']);
    $body=curl_exec($ch); $errno=curl_errno($ch); $error=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body===false || $errno) throw new RuntimeException('OLT HTTP request failed: '.$error);
    if ($code<200 || $code>=300) throw new RuntimeException('OLT returned HTTP '.$code.'.');
    $json=json_decode($body,true);
    return ['status'=>$code,'raw'=>(string)$body,'json'=>$json];
}
function ssh_exec(array $o,string $command): string {
    $command=trim($command); if ($command==='') throw new RuntimeException('ONU reboot command is not configured.');
    $host=preg_replace('/[^A-Za-z0-9.:-]/','',(string)$o['host']); $usr=preg_replace('/[^A-Za-z0-9_.-]/','',(string)$o['username']);
    $ask=tempnam(sys_get_temp_dir(),'ask'); file_put_contents($ask,"#!/bin/sh\nprintf '%s\\n' ".escapeshellarg(base64_decode((string)$o['password_enc'],true) ?: '')); chmod($ask,0700);
    putenv('SSH_ASKPASS='.$ask); putenv('SSH_ASKPASS_REQUIRE=force'); putenv('DISPLAY=:0');
    $cmd='setsid ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=8 -p '.escapeshellarg((string)($o['port'] ?: 22)).' '.escapeshellarg($usr.'@'.$host).' '.escapeshellarg($command).' 2>&1';
    $out=shell_exec($cmd); @unlink($ask); putenv('SSH_ASKPASS'); putenv('SSH_ASKPASS_REQUIRE'); return trim((string)$out);
}

$u=require_login();
$id=(int)($_GET['olt'] ?? $_POST['olt'] ?? 0); if ($id<1 || !allowed($db,$u,$id)) { http_response_code(403); exit('Forbidden'); }
$olt=get_olt($db,$id); $flash=''; $result=null; $module='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf();
    try {
        $action=$_POST['action'] ?? '';
        if ($action==='reboot') {
            if (!allowed($db,$u,$id,true)) throw new RuntimeException('No manage permission.');
            $result=['type'=>'text','title'=>'ONU reboot command output','data'=>ssh_exec($olt,(string)$olt['reboot_command'])];
        } else {
            $known=['alarm'=>'sys_alarm_active','auth'=>'port_onu_auth_statitics'];
            $module=$known[$action] ?? trim((string)($_POST['module'] ?? ''));
            if ($module==='') throw new RuntimeException('Select an OLT API module.');
            $extra=[];
            if ($module==='sys_alarm_active') { $extra=['PageSize'=>min(100,(int)($_POST['page_size'] ?? 20)),'PageNumber'=>max(1,(int)($_POST['page_number'] ?? 1))]; }
            $result=['type'=>'http','title'=>$module,'data'=>http_get(olt_url($olt,$module,$extra))];
        }
    } catch (Throwable $e) { $flash=$e->getMessage(); }
}

function pretty($data): string {
    if (is_array($data)) return (string)json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $decoded=json_decode((string)$data,true); if ($decoded!==null) return (string)json_encode($decoded,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return (string)$data;
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($olt['name'])?> — ECOM OLT Monitor</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><style>body{background:#f4f6fa}.card{border:0;box-shadow:0 2px 14px #0001}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;white-space:pre-wrap;word-break:break-word}.dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:#22c55e;margin-right:7px}</style></head>
<body><div class="container-fluid p-3 p-lg-4"><div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3"><div><a href="index.php?page=olts" class="text-decoration-none">← ECOM OLT</a><h2 class="mt-2 mb-1"><?=e($olt['name'])?></h2><div class="text-muted"><span class="dot"></span><?=e($olt['vendor'])?> · <?=e($olt['host'])?>:<?=e($olt['web_port'] ?? 9092)?></div></div><a class="btn btn-outline-secondary" href="index.php">Dashboard</a></div>
<?php if($flash):?><div class="alert alert-danger"><?=e($flash)?></div><?php endif;?>
<div class="row g-3"><div class="col-lg-4"><div class="card p-3 h-100"><h5>ECOM Web API</h5><p class="small text-muted">The monitor runs requests server-side, so the browser does not need direct access to the OLT port.</p><form method="post" class="d-grid gap-2"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="olt" value="<?=$id?>"><button class="btn btn-primary" name="action" value="alarm">Active alarms</button><button class="btn btn-outline-primary" name="action" value="auth">ONU authentication statistics</button><input class="form-control" name="module" placeholder="Other ECOM module name"><button class="btn btn-outline-dark" name="action" value="custom">Run module</button></form></div></div>
<div class="col-lg-8"><div class="card p-3 h-100"><h5>ONU / Laser monitor</h5><p class="small text-muted mb-2">The supplied ECOM page confirms the optical-power CSS asset, but the exact CGI module for optical power was not included. Enter the module name when known; it will be queried through the same ECOM API.</p><form method="post" class="row g-2"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="olt" value="<?=$id?>"><div class="col-md-8"><input class="form-control" name="module" value="<?=e($_POST['module'] ?? '')?>" placeholder="e.g. exact optical-power CGI module"></div><div class="col-md-4"><button class="btn btn-outline-primary w-100" name="action" value="custom">Fetch optical data</button></div></form><hr><div class="small text-muted">Known endpoint: <code>port_onu_auth_statitics</code></div></div></div>
<div class="col-lg-4"><div class="card p-3"><h5>ONU Reboot</h5><p class="small text-muted">Uses the configured ECOM SSH reboot command from the main OLT configuration.</p><?php if(allowed($db,$u,$id,true)):?><form method="post" onsubmit="return confirm('Run the configured ONU reboot command?')"><input type="hidden" name="csrf" value="<?=e(token())?>"><input type="hidden" name="olt" value="<?=$id?>"><button class="btn btn-danger" name="action" value="reboot">Run ONU Reboot</button></form><?php else:?><span class="badge text-bg-secondary">View only</span><?php endif;?></div></div>
<?php if($result):?><div class="col-lg-8"><div class="card p-3"><h5><?=e($result['title'])?></h5><?php if($result['type']==='http'):?><div class="mb-2"><span class="badge text-bg-success">HTTP <?=e($result['data']['status'])?></span></div><pre class="mono mb-0"><?=e(pretty($result['data']['json'] ?? $result['data']['raw']))?></pre><?php else:?><pre class="mono mb-0"><?=e($result['data'])?></pre><?php endif;?></div></div><?php endif;?></div></div></body></html>
