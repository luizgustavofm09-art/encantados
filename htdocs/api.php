<?php
// Encantados v3 - API (PHP 7.4+ com pdo_sqlite). game.db é criado/migrado automaticamente.
session_start();
header('Content-Type: application/json; charset=utf-8');
$db = new PDO('sqlite:' . __DIR__ . '/game.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_TIMEOUT, 8);
try { $db->exec('PRAGMA busy_timeout=8000'); $db->exec('PRAGMA journal_mode=WAL'); } catch (Exception $e) {}
// Migrações rodam só uma vez (antes rodavam a cada requisição e travavam o banco => jogador aparecia offline)
$MIG = (int)$db->query('PRAGMA user_version')->fetchColumn() < 6;
if ($MIG) {
$db->exec("CREATE TABLE IF NOT EXISTS players(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE COLLATE NOCASE, pass TEXT, avatar TEXT, title TEXT, trophies INTEGER DEFAULT 0, gold INTEGER DEFAULT 200, wins INTEGER DEFAULT 0, losses INTEGER DEFAULT 0, clan_id INTEGER)");
$db->exec("CREATE TABLE IF NOT EXISTS clans(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE COLLATE NOCASE, descr TEXT, leader_id INTEGER)");
foreach (["gems INTEGER DEFAULT 20", "owned TEXT", "chests TEXT", "best INTEGER DEFAULT 0", "streak INTEGER DEFAULT 0", "since INTEGER", "seen INTEGER DEFAULT 0"] as $c) { try { $db->exec("ALTER TABLE players ADD COLUMN $c"); } catch (Exception $e) {} }
}

const RANKED_MIN = 4000;
// Arenas ranqueadas: [troféus mínimos, nome]
const RANKED = [[4000, 'Liga do Cocar'], [6000, 'Liga da Pedra Pintada'], [9000, 'Liga da Lua Cheia'], [13000, 'Liga do Trovão'], [18000, 'Panteão dos Encantados']];
const EMOTES = ['e:😂', 'e:😭', 'e:😡', 'e:👍', 'e:😱', 'e:🤔', 'e:😎', 'e:🥳'];
const AVATARS = ['🧝', '🧙', '🦊', '🐺', '🌿', '🔥', '🧜‍♀️', '🪶', '🐆', '🦜', '🐍', '🦉', '🐊', '🦇', '🐗', '🐬', '🌙', '☀️', '⚡', '👹', '🧚', '🧛', '🧞', '🐉'];
const TITLES = ['Guardião da Mata', 'Curandeira do Sertão', 'Mestre das Águas', 'Caçador de Assombrações', 'Pajé das Estrelas', 'Domador de Boiúnas', 'Cria do Saci', 'Benzedeira de Respeito', 'Rei do Folclore', 'Rainha da Lua Cheia', 'Contador de Causos', 'Andarilho da Caatinga', 'Senhor dos Trovões', 'Feiticeiro do Mangue', 'Lenda Viva', 'Terror das Torres'];
// raridade: 0 comum, 1 raro, 2 épico, 3 lendário
const RARITY = ['saci'=>0,'negrinho'=>0,'uirapuru'=>0,'matinta'=>0,'caipora'=>0,'curupira'=>0,'iara'=>0,'cuia'=>0,'pisadeira'=>0,'anhanga'=>1,'mula'=>1,'boto'=>1,'boi'=>1,'capelobo'=>1,'corpo'=>1,'curacanga'=>1,'tutu'=>2,'ipupiara'=>2,'lobis'=>2,'cuca'=>2,'yacu'=>2,'jurupari'=>2,'boitata'=>3,'boiuna'=>3,'maeouro'=>3,'onca'=>3,'mapin'=>3,'tupa'=>4,'jaci'=>4,'guaraci'=>4,'romao'=>0,'ahoaho'=>0,'papafigo'=>1,'bradador'=>1,'alamoa'=>2,'quibungo'=>2,'labatut'=>3,'norato'=>3,'paimato'=>3,'ceuci'=>4,'flechas'=>0,'bolafogo'=>1,'veneno'=>1,'cura'=>1,'geada'=>2,'tornado'=>2,'raio'=>3,'oca'=>1,'totem'=>1,'ninho'=>2,'tapera'=>2,'cabriola'=>0,'tuiuiu'=>0,'fogofatuo'=>0,'caboclo'=>1,'maocabelo'=>1,'cachorropadre'=>1,'pegarrafa'=>2,'bichopapao'=>2];
const CARD_AR = ['saci'=>0,'negrinho'=>0,'uirapuru'=>0,'matinta'=>0,'caipora'=>0,'curupira'=>0,'iara'=>0,'cuia'=>1,'pisadeira'=>1,'anhanga'=>1,'mula'=>0,'boto'=>1,'boi'=>0,'capelobo'=>2,'corpo'=>3,'curacanga'=>4,'tutu'=>2,'ipupiara'=>5,'lobis'=>2,'cuca'=>3,'yacu'=>4,'jurupari'=>5,'boitata'=>5,'boiuna'=>6,'maeouro'=>6,'onca'=>7,'mapin'=>7,'tupa'=>8,'jaci'=>8,'guaraci'=>9,'romao'=>10,'ahoaho'=>10,'papafigo'=>11,'bradador'=>12,'alamoa'=>13,'quibungo'=>14,'labatut'=>15,'norato'=>16,'paimato'=>17,'ceuci'=>19,'flechas'=>0,'bolafogo'=>0,'veneno'=>1,'cura'=>2,'geada'=>3,'tornado'=>4,'raio'=>5,'oca'=>1,'totem'=>2,'ninho'=>3,'tapera'=>6,'cabriola'=>1,'tuiuiu'=>2,'fogofatuo'=>3,'caboclo'=>3,'maocabelo'=>4,'cachorropadre'=>5,'pegarrafa'=>6,'bichopapao'=>7]; // arena mínima de cada carta
const ARENA_T = [0,150,300,500,800,1200,1700,2300,3000,4000,5000,6200,7500,9000,10500,12000,14000,16000,18500,21000];
if ($MIG) {
$db->exec("CREATE TABLE IF NOT EXISTS clan_msgs(id INTEGER PRIMARY KEY AUTOINCREMENT, clan_id INTEGER, player_id INTEGER, name TEXT, avatar TEXT, text TEXT, t INTEGER)");
$db->exec("CREATE TABLE IF NOT EXISTS matches(id INTEGER PRIMARY KEY AUTOINCREMENT, host INTEGER, guest INTEGER, status TEXT, created INTEGER, ht INTEGER DEFAULT 0, gt INTEGER DEFAULT 0, hseen INTEGER, gseen INTEGER, result TEXT, invite INTEGER)");
$db->exec("CREATE TABLE IF NOT EXISTS friends(a INTEGER, b INTEGER, status TEXT, PRIMARY KEY(a,b))");
foreach (["clan_msgs" => "img TEXT", "matches" => "mode TEXT"] as $tb => $c) { try { $db->exec("ALTER TABLE $tb ADD COLUMN $c"); } catch (Exception $e) {} }
$db->exec("CREATE TABLE IF NOT EXISTS moves(id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, side INTEGER, tick INTEGER, card TEXT, x REAL, y REAL)");
try { $db->exec("CREATE INDEX IF NOT EXISTS moves_m ON moves(match_id,id)"); } catch (Exception $e) {}
$db->exec("PRAGMA user_version=6");
}
if ((int)$db->query('PRAGMA user_version')->fetchColumn() < 7) {
 foreach (["dlast TEXT", "dstreak INTEGER DEFAULT 0"] as $c) { try { $db->exec("ALTER TABLE players ADD COLUMN $c"); } catch (Exception $e) {} }
 $db->exec("PRAGMA user_version=7");
}
if ((int)$db->query('PRAGMA user_version')->fetchColumn() < 8) {
 foreach (["mday TEXT", "mbase TEXT", "mdone TEXT"] as $c) { try { $db->exec("ALTER TABLE players ADD COLUMN $c"); } catch (Exception $e) {} }
 $db->exec("PRAGMA user_version=8");
}
if ((int)$db->query('PRAGMA user_version')->fetchColumn() < 9) {
 try { $db->exec("ALTER TABLE matches ADD COLUMN code TEXT"); } catch (Exception $e) {}
 $db->exec("PRAGMA user_version=9");
}
// Tutorial: garante a coluna "tut" (verifica a coluna de verdade, não só a versão do banco)
$hasTut = false;
foreach ($db->query("PRAGMA table_info(players)") as $col) { if ($col['name'] === 'tut') $hasTut = true; }
if (!$hasTut) {
 try {
  $db->exec("ALTER TABLE players ADD COLUMN tut INTEGER DEFAULT 0");
  $db->exec("UPDATE players SET tut=1 WHERE COALESCE(wins,0)+COALESCE(losses,0)>0");
 } catch (Exception $e) { error_log('Encantados: falha ao criar coluna tut: ' . $e->getMessage()); }
}
$cols = []; foreach ($db->query("PRAGMA table_info(players)") as $col) $cols[] = $col['name'];
foreach (['cxp TEXT', 'txp INTEGER DEFAULT 0', 'adm INTEGER DEFAULT 0'] as $c) { if (!in_array(explode(' ', $c)[0], $cols)) { try { $db->exec("ALTER TABLE players ADD COLUMN $c"); } catch (Exception $e) { error_log('Encantados: coluna ' . $c . ': ' . $e->getMessage()); } } }
// Webhook de saída: se existir webhook.json {"url":"...","secret":"..."}, cada evento é enviado assinado (HMAC-SHA256)
function hook($ev, $d) {
    $f = __DIR__ . '/webhook.json'; if (!is_file($f)) return;
    $c = json_decode(file_get_contents($f), true); if (empty($c['url'])) return;
    $b = json_encode(['event' => $ev, 't' => time(), 'data' => $d], JSON_UNESCAPED_UNICODE);
    $sig = hash_hmac('sha256', $b, $c['secret'] ?? '');
    @file_get_contents($c['url'], false, stream_context_create(['http' => ['method' => 'POST', 'timeout' => 2, 'ignore_errors' => true, 'header' => "Content-Type: application/json\r\nX-Encantados-Signature: sha256=$sig\r\n", 'content' => $b]]));
}
if ((int)$db->query('PRAGMA user_version')->fetchColumn() < 10) {
 foreach (["items TEXT", "eqp TEXT"] as $c) { try { $db->exec("ALTER TABLE players ADD COLUMN $c"); } catch (Exception $e) {} }
 $db->exec("PRAGMA user_version=10");
}
const RENAME = 700;
const ITEMS = ['b:mata'=>['g',400], 'b:sertao'=>['g',450], 'b:cordel'=>['g',500], 'b:bumba'=>['g',600], 'b:rio'=>['g',650], 'b:saci'=>['g',700], 'b:ipe'=>['g',800], 'b:pampa'=>['g',900], 'b:iara'=>['d',90], 'b:boitata'=>['d',110], 'b:curupira'=>['d',130], 'b:tupa'=>['d',150], 'b:boto'=>['d',170], 'b:cuca'=>['d',200], 'm:saci'=>['g',250], 'm:curupira'=>['g',300], 'm:caipora'=>['g',350], 'm:cuia'=>['g',400], 'm:uirapuru'=>['g',450], 'm:mula'=>['g',500], 'm:boto'=>['g',550], 'm:negrinho'=>['g',600], 'm:iara'=>['d',45], 'm:boitata'=>['d',55], 'm:lobis'=>['d',65], 'm:cuca'=>['d',75], 'm:tupa'=>['d',85], 'm:jaci'=>['d',95], 'm:mapin'=>['d',105], 'm:ceuci'=>['d',120], 't:oca'=>['g',1200], 't:pedra'=>['g',1500], 't:barro'=>['g',1800], 't:vitoria'=>['g',2200], 't:boitata'=>['d',220], 't:iara'=>['d',280], 't:jaci'=>['d',340], 't:cuca'=>['d',420]]; // g = moedas (simples), d = diamantes (exclusivos)
// missões diárias: [id, texto, tipo (p=partidas, w=vitórias, d=recompensa diária), meta, moedas]
const MISSIONS = [['p3','Jogue 3 partidas','p',3,100],['w1','Vença 1 partida','w',1,80],['w3','Vença 3 partidas','w',3,200],['p6','Jogue 6 partidas','p',6,250],['dl','Pegue a recompensa diária','d',1,50]];
function missions($u) {
    $d0 = date('Y-m-d');
    $r = q("SELECT wins,losses,mday,mbase,mdone,dlast FROM players WHERE id=?", [$u])->fetch(PDO::FETCH_ASSOC);
    if ($r['mday'] !== $d0) { $b = ['w' => (int)$r['wins'], 'l' => (int)$r['losses']]; q("UPDATE players SET mday=?, mbase=?, mdone='[]' WHERE id=?", [$d0, json_encode($b), $u]); $done = []; }
    else { $b = J($r['mbase'], ['w' => 0, 'l' => 0]); $done = J($r['mdone'], []); }
    $w = (int)$r['wins'] - $b['w']; $p = $w + (int)$r['losses'] - $b['l']; $dl = $r['dlast'] === $d0 ? 1 : 0;
    $out = [];
    foreach (MISSIONS as $m) { $v = $m[2] == 'p' ? $p : ($m[2] == 'w' ? $w : $dl); $out[] = ['id' => $m[0], 't' => $m[1], 'v' => min($v, $m[3]), 'n' => $m[3], 'g' => $m[4], 'done' => in_array($m[0], $done)]; }
    return $out;
}
const ONLINE = 90; // segundos sem sinal até contar como offline
const PVP_TICKS = 5400; // 120 s + 60 s de morte súbita a 30 ticks/s
function arenaOf($t) { $a = 0; foreach (ARENA_T as $i => $x) { if ($t >= $x) $a = $i; } return $a; }
const START = ['saci','negrinho','caipora','curupira','iara','uirapuru','mula','boi','flechas'];
const PRICE = [300, 800, 2000, 5000, 12000];   // gemas (caro de propósito)
const DUP = [15, 40, 100, 300, 800];         // ouro por carta repetida
// Loja de moedas (ouro): baús, gemas e carta surpresa
const GOLD_SHOP = ['madeira'=>150, 'prata'=>450, 'ouro'=>1200, 'lendario'=>3000, 'gems10'=>500, 'gems60'=>2500, 'carta'=>900];
const CHESTS = ['madeira'=>[1,[70,25,5,0,0],2], 'prata'=>[2,[48,35,14,3,0],4], 'ouro'=>[3,[28,40,24,7,1],8], 'lendario'=>[4,[10,30,35,20,5],15]]; // cartas, pesos de raridade, máx. gemas

function out($d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function fail($m) { http_response_code(400); out(['error' => $m]); }
function q($sql, $p = []) { global $db; $s = $db->prepare($sql); $s->execute($p); return $s; }
function J($s, $d) { $v = json_decode($s ?? '', true); return is_array($v) ? $v : $d; }
function sv($id, $f, $v) { q("UPDATE players SET $f=? WHERE id=?", [json_encode(array_values($v)), $id]); }
function rr($w) { $r = mt_rand(1, array_sum($w)); foreach ($w as $i => $x) { if (($r -= $x) <= 0) return $i; } return 0; }
function me() {
    if (empty($_SESSION['uid'])) return null;
    $u = q("SELECT p.id,p.name,p.avatar,p.title,p.trophies,p.gold,p.gems,p.wins,p.losses,p.best,p.streak,p.clan_id,p.owned,p.chests,p.items,p.eqp,p.dlast,p.dstreak,p.tut,p.cxp,p.txp,p.adm,c.name AS clan
            FROM players p LEFT JOIN clans c ON c.id=p.clan_id WHERE p.id=?", [$_SESSION['uid']])->fetch(PDO::FETCH_ASSOC);
    if (!$u) return null;
    try { q("UPDATE players SET seen=? WHERE id=? AND COALESCE(seen,0)<?", [time(), $u['id'], time() - 5]); } catch (Exception $e) {}
    $u['items'] = J($u['items'], []); $e = J($u['eqp'], []); $u['banner'] = $e['b'] ?? ''; $u['tower'] = $e['t'] ?? ''; unset($u['eqp']);
    $u['freq'] = (int)q("SELECT COUNT(*) FROM friends WHERE b=? AND status='pending'", [$u['id']])->fetchColumn();
    $u['owned'] = J($u['owned'], START); $u['chests'] = J($u['chests'], []); $u['gems'] = (int)$u['gems']; $u['tut'] = (int)$u['tut']; $u['cxp'] = J($u['cxp'], []); $u['txp'] = (int)$u['txp']; $u['adm'] = (int)$u['adm']; $u['streak'] = (int)$u['streak']; $u['dstreak'] = (int)$u['dstreak']; $d0 = date('Y-m-d'); $u['dready'] = $u['dlast'] !== $d0; if ($u['dlast'] !== $d0 && $u['dlast'] !== date('Y-m-d', strtotime('-1 day'))) $u['dstreak'] = 0; $u['best'] = max((int)$u['best'], (int)$u['trophies']);
    return $u;
}
// Sorteia uma carta ainda não obtida (da arena do jogador). Comuns saem mais.
function grantCard($u) {
    $ua = arenaOf($u['trophies']); $pool = [];
    foreach (RARITY as $k => $r) if (CARD_AR[$k] <= $ua && !in_array($k, $u['owned'])) $pool[$k] = [60, 30, 12, 4, 1][$r];
    if (!$pool) return null;
    $x = mt_rand(1, array_sum($pool));
    foreach ($pool as $k => $w) { if (($x -= $w) <= 0) { $own = $u['owned']; $own[] = $k; q("UPDATE players SET owned=? WHERE id=?", [json_encode($own), $u['id']]); return $k; } }
    return null;
}
function fid($name) { return q("SELECT id FROM players WHERE name=?", [trim($name ?? '')])->fetchColumn(); }
function need() { $u = me(); if (!$u) fail('Faça login primeiro.'); return $u; }

// ADMIN X: perfil de administrador (conta do jogo). Criado na primeira requisição; troque a senha pelo jogo ou aqui.
function adm() { $u = me(); if (!$u || !$u['adm']) fail('Acesso negado.'); return $u; }
$adm = q("SELECT adm FROM players WHERE name='ADMIN X'")->fetchColumn();
if ($adm === false) {
    q("INSERT INTO players(name,pass,avatar,title,owned,chests,since,gold,gems,tut,adm) VALUES('ADMIN X',?,?,?,?,'[]',?,999999,99999,1,1)",
      [password_hash('@GL123', PASSWORD_DEFAULT), '🛡️', 'Administrador', json_encode(array_keys(RARITY)), time() * 1000]);
} elseif (!$adm) {
    q("UPDATE players SET adm=1, tut=1, pass=? WHERE name='ADMIN X'", [password_hash('@GL123', PASSWORD_DEFAULT)]);
}
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$a = $_GET['a'] ?? '';

switch ($a) {
    case 'register':
        $n = trim($in['name'] ?? ''); $p = $in['pass'] ?? '';
        if (!preg_match('/^[\p{L}\p{N}_ ]{3,16}$/u', $n)) fail('Nome: 3 a 16 letras ou números.');
        if (strlen($p) < 4) fail('Senha: mínimo de 4 caracteres.');
        $av = in_array($in['avatar'] ?? '', AVATARS) ? $in['avatar'] : AVATARS[0];
        $ti = in_array($in['title'] ?? '', TITLES) ? $in['title'] : TITLES[0];
        if (q("SELECT 1 FROM players WHERE name=?", [$n])->fetch()) fail('Esse nome já está em uso.');
        q("INSERT INTO players(name,pass,avatar,title,owned,chests,since) VALUES(?,?,?,?,?,'[]',?)", [$n, password_hash($p, PASSWORD_DEFAULT), $av, $ti, json_encode(START), time() * 1000]);
        $_SESSION['uid'] = $db->lastInsertId();
        out(['user' => me()]);

    case 'login':
        $r = q("SELECT id,pass FROM players WHERE name=?", [trim($in['name'] ?? '')])->fetch(PDO::FETCH_ASSOC);
        if (!$r || !password_verify($in['pass'] ?? '', $r['pass'])) fail('Nome ou senha incorretos.');
        $_SESSION['uid'] = $r['id'];
        out(['user' => me()]);

    case 'logout': session_destroy(); out(['ok' => 1]);
    case 'me': out(['user' => me()]);
    case 'ping': // batimento: mantém o jogador online mesmo dentro da batalha
        $u = me(); out(['ok' => $u ? 1 : 0, 'freq' => $u ? $u['freq'] : 0]);
    case 'daily': // recompensa diária sorteada + ofensiva de dias seguidos
        $u = need(); $hoje = date('Y-m-d');
        if ($u['dlast'] === $hoje) fail('Você já pegou a recompensa de hoje. Volte amanhã!');
        $s = $u['dstreak'] + 1; $b = min(7, $s); // bônus cresce até 7 dias
        $tipo = rr([45, 25, 20, 10]); $got = ''; $card = null;
        if ($tipo == 0) { $g = mt_rand(50, 120) * $b; q("UPDATE players SET gold=gold+? WHERE id=?", [$g, $u['id']]); $got = "$g moedas"; }
        elseif ($tipo == 1) { $g = mt_rand(2, 5) * $b; q("UPDATE players SET gems=gems+? WHERE id=?", [$g, $u['id']]); $got = "$g 💎"; }
        elseif ($tipo == 2 && count($u['chests']) < 4) { $k = $b >= 7 ? 'ouro' : ($b >= 3 ? 'prata' : 'madeira'); $ch = $u['chests']; $ch[] = $k; sv($u['id'], 'chests', $ch); $got = "Baú de $k"; }
        elseif ($tipo == 3 && ($card = grantCard($u))) { $got = 'Carta nova!'; }
        else { $g = 100 * $b; q("UPDATE players SET gold=gold+? WHERE id=?", [$g, $u['id']]); $got = "$g moedas"; }
        q("UPDATE players SET dlast=?, dstreak=? WHERE id=?", [$hoje, $s, $u['id']]);
        out(['user' => me(), 'got' => $got, 'card' => $card, 'streak' => $s]);
    case 'missions': $u = need(); out(['m' => missions($u['id'])]);
    case 'mission_claim':
        $u = need(); $id = $in['id'] ?? ''; $ms = missions($u['id']); $m = null;
        foreach ($ms as $x) if ($x['id'] === $id) $m = $x;
        if (!$m) fail('Missão inexistente.');
        if ($m['done']) fail('Você já pegou essa recompensa.');
        if ($m['v'] < $m['n']) fail('Missão ainda não concluída.');
        $done = J(q("SELECT mdone FROM players WHERE id=?", [$u['id']])->fetchColumn(), []); $done[] = $id;
        q("UPDATE players SET gold=gold+?, mdone=? WHERE id=?", [$m['g'], json_encode($done), $u['id']]);
        out(['user' => me(), 'm' => missions($u['id']), 'g' => $m['g']]);
    case 'gold_buy':
        $u = need(); $it = $in['item'] ?? '';
        if (!isset(GOLD_SHOP[$it])) fail('Item inexistente.');
        $p = GOLD_SHOP[$it];
        if ($u['gold'] < $p) fail('Moedas insuficientes.');
        $got = null;
        if (isset(CHESTS[$it])) {
            if (count($u['chests']) >= 4) fail('Seus 4 espaços de baú estão cheios. Abra um antes.');
            $ch = $u['chests']; $ch[] = $it; sv($u['id'], 'chests', $ch); $got = 'Baú de ' . $it;
        } elseif ($it === 'gems10' || $it === 'gems60') {
            q("UPDATE players SET gems=gems+? WHERE id=?", [$it === 'gems10' ? 10 : 60, $u['id']]); $got = ($it === 'gems10' ? 10 : 60) . ' 💎';
        } else {
            $got = grantCard($u); if (!$got) fail('Você já tem todas as cartas da sua arena!');
        }
        q("UPDATE players SET gold=gold-? WHERE id=?", [$p, $u['id']]);
        out(['user' => me(), 'got' => $got, 'card' => isset(RARITY[$got]) ? $got : null]);

    case 'tut_done':
        $u = need();
        if (!$u['tut']) q("UPDATE players SET tut=1, gold=gold+50 WHERE id=?", [$u['id']]);
        out(['user' => me()]);

    case 'admin_data':
        adm();
        $s = '%' . trim((string)($in['s'] ?? '')) . '%';
        out([
            'stats' => ['players' => (int)q("SELECT COUNT(*) FROM players")->fetchColumn(), 'clans' => (int)q("SELECT COUNT(*) FROM clans")->fetchColumn(),
                'matches' => (int)q("SELECT COUNT(*) FROM matches")->fetchColumn(), 'wins' => (int)q("SELECT COALESCE(SUM(wins),0) FROM players")->fetchColumn(),
                'losses' => (int)q("SELECT COALESCE(SUM(losses),0) FROM players")->fetchColumn()],
            'players' => q("SELECT id,name,avatar,trophies,gold,gems,wins,losses,COALESCE(tut,0) AS tut,COALESCE(txp,0) AS txp FROM players WHERE name LIKE ? ORDER BY trophies DESC, id LIMIT 60", [$s])->fetchAll(PDO::FETCH_ASSOC),
        ]);

    case 'admin_set':
        $me = adm();
        $id = (int)($in['id'] ?? 0);
        if ($op0 = (string)($in['op'] ?? '') and $op0 === 'delete' && $id === (int)$me['id']) fail('Você não pode excluir o próprio perfil admin.');
        if (!q("SELECT 1 FROM players WHERE id=?", [$id])->fetchColumn()) fail('Jogador não encontrado.');
        $op = (string)($in['op'] ?? '');
        if ($op === 'save') {
            foreach (['gold', 'gems', 'trophies', 'txp'] as $f) { if (isset($in[$f])) q("UPDATE players SET $f=? WHERE id=?", [max(0, min(1000000000, (int)$in[$f])), $id]); }
        } elseif ($op === 'tut0') { q("UPDATE players SET tut=0 WHERE id=?", [$id]);
        } elseif ($op === 'cards') { sv($id, 'owned', array_keys(RARITY));
        } elseif ($op === 'xp0') { q("UPDATE players SET cxp=?, txp=0 WHERE id=?", ['{}', $id]);
        } elseif ($op === 'delete') { q("DELETE FROM friends WHERE a=? OR b=?", [$id, $id]); q("DELETE FROM players WHERE id=?", [$id]);
        } else fail('Operação inválida.');
        out(['ok' => 1]);

    case 'ranking':
        out(['list' => q("SELECT p.name,p.avatar,p.title,p.trophies,p.wins,c.name AS clan FROM players p
            LEFT JOIN clans c ON c.id=p.clan_id ORDER BY p.trophies DESC, p.wins DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC)]);

    case 'player':
        $p = q("SELECT p.name,p.avatar,p.title,p.trophies,p.best,p.wins,p.losses,p.owned,p.eqp,p.since,c.name AS clan FROM players p LEFT JOIN clans c ON c.id=p.clan_id WHERE p.name=?", [trim($in['name'] ?? '')])->fetch(PDO::FETCH_ASSOC);
        if (!$p) fail('Jogador não encontrado.');
        $p['cards'] = count(J($p['owned'], START)); unset($p['owned']); $e = J($p['eqp'], []); $p['banner'] = $e['b'] ?? ''; unset($p['eqp']); $p['best'] = max((int)$p['best'], (int)$p['trophies']);
        out(['p' => $p]);

    case 'profile':
        $u = need();
        if (in_array($in['avatar'] ?? '', AVATARS)) q("UPDATE players SET avatar=? WHERE id=?", [$in['avatar'], $u['id']]);
        if (in_array($in['title'] ?? '', TITLES)) q("UPDATE players SET title=? WHERE id=?", [$in['title'], $u['id']]);
        out(['user' => me()]);

    case 'clans':
        out(['list' => q("SELECT c.id,c.name,c.descr,COUNT(p.id) AS members,COALESCE(SUM(p.trophies),0) AS trophies
            FROM clans c LEFT JOIN players p ON p.clan_id=c.id GROUP BY c.id ORDER BY trophies DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC)]);

    case 'clan_info':
        $u = need();
        if (!$u['clan_id']) out(['clan' => null]);
        $c = q("SELECT id,name,descr,leader_id FROM clans WHERE id=?", [$u['clan_id']])->fetch(PDO::FETCH_ASSOC);
        $c['members'] = q("SELECT id,name,avatar,trophies FROM players WHERE clan_id=? ORDER BY trophies DESC", [$u['clan_id']])->fetchAll(PDO::FETCH_ASSOC);
        out(['clan' => $c]);

    case 'clan_create':
        $u = need();
        if ($u['clan_id']) fail('Saia do clã atual antes de criar outro.');
        if ($u['gold'] < 100) fail('Criar um clã custa 100 de ouro.');
        $n = trim($in['name'] ?? ''); $d = mb_substr(trim($in['descr'] ?? ''), 0, 80);
        if (!preg_match('/^[\p{L}\p{N}_ ]{3,20}$/u', $n)) fail('Nome do clã: 3 a 20 letras ou números.');
        if (q("SELECT 1 FROM clans WHERE name=?", [$n])->fetch()) fail('Já existe um clã com esse nome.');
        q("INSERT INTO clans(name,descr,leader_id) VALUES(?,?,?)", [$n, $d, $u['id']]);
        q("UPDATE players SET clan_id=?, gold=gold-100 WHERE id=?", [$db->lastInsertId(), $u['id']]);
        out(['user' => me()]);

    case 'clan_join':
        $u = need();
        if ($u['clan_id']) fail('Você já está em um clã.');
        $id = (int)($in['id'] ?? 0);
        if (!q("SELECT 1 FROM clans WHERE id=?", [$id])->fetch()) fail('Clã não encontrado.');
        if (q("SELECT COUNT(*) FROM players WHERE clan_id=?", [$id])->fetchColumn() >= 30) fail('Clã lotado (30 membros).');
        q("UPDATE players SET clan_id=? WHERE id=?", [$id, $u['id']]);
        out(['user' => me()]);

    case 'clan_leave':
        $u = need();
        if (!$u['clan_id']) fail('Você não está em um clã.');
        q("UPDATE players SET clan_id=NULL WHERE id=?", [$u['id']]);
        $next = q("SELECT id FROM players WHERE clan_id=? ORDER BY trophies DESC LIMIT 1", [$u['clan_id']])->fetchColumn();
        if ($next) q("UPDATE clans SET leader_id=? WHERE id=? AND leader_id=?", [$next, $u['clan_id'], $u['id']]);
        else q("DELETE FROM clans WHERE id=?", [$u['clan_id']]);
        out(['user' => me()]);

    case 'battle_start':
        $u = need();
        if (!$u['tut']) fail('Conclua o tutorial primeiro.');
        $m = ($in['mode'] ?? '') === 'ranked' ? 'ranked' : 'casual';
        $d = max(0, min(2, (int)($in['diff'] ?? 1)));
        $L = max(0, min(count(RANKED) - 1, (int)($in['league'] ?? 0)));
        if ($m === 'ranked') { if ($u['trophies'] < RANKED[$L][0]) fail(RANKED[$L][1] . ' abre com ' . RANKED[$L][0] . ' troféus.'); $d = 2; }
        $_SESSION['b'] = ['mode' => $m, 'diff' => $d, 't' => time(), 'league' => $L];
        out(['ok' => 1]);

    case 'battle_end':
        $u = need();
        $b = $_SESSION['b'] ?? null; unset($_SESSION['b']);
        if (!$b || time() - $b['t'] < 15) fail('Batalha inválida.');
        $r = $in['r'] ?? ''; $rk = $b['mode'] === 'ranked'; $d = $b['diff']; $L = (int)($b['league'] ?? 0); $w = $r === 'win';
        $win = $rk ? 45 + 5 * $L : [20, 30, 40][$d]; $los = $rk ? 25 : [10, 15, 20][$d];
        $dt = $w ? $win : ($r === 'loss' ? -$los : 0);
        $g = $w ? ($rk ? 60 + 25 * $L : 30 + 15 * $d) : 8;
        $gm = $w ? ($rk ? 3 + $L : [1, 2, 3][$d]) : 0;
        $streak = $w ? $u['streak'] + 1 : 0; $sb = ($w && $streak >= 3) ? 10 * min($streak, 6) : 0; $g += $sb;
        $chest = null;
        if ($w && mt_rand(1, 100) <= ($rk ? 100 : [50, 70, 90][$d]) && count($u['chests']) < 4) {
            $x = mt_rand(1, 100);
            if ($rk) $chest = $x <= 6 + 4 * $L ? 'lendario' : ($x <= 30 + 5 * $L ? 'ouro' : ($x <= 75 ? 'prata' : 'madeira'));
            else $chest = $d === 2 ? ($x <= 4 && arenaOf($u['trophies']) >= 10 ? 'lendario' : ($x <= 18 ? 'ouro' : ($x <= 60 ? 'prata' : 'madeira'))) : ($d === 1 && $x <= 30 ? 'prata' : 'madeira');
            $ch = $u['chests']; $ch[] = $chest; sv($u['id'], 'chests', $ch);
        }
        // Carta nova: chance por vitória + garantida a cada 5 vitórias
        $card = null; $ms = $w && ($u['wins'] + 1) % 5 === 0;
        if ($w && ($ms || mt_rand(1, 100) <= ($rk ? 45 : [15, 25, 35][$d]))) { $card = grantCard($u); if (!$card) $g += 100; }
        $nt = max(0, $u['trophies'] + $dt);
        q("UPDATE players SET trophies=?, gold=gold+?, gems=gems+?, wins=wins+?, losses=losses+?, streak=?, best=MAX(COALESCE(best,0),?) WHERE id=?",
          [$nt, $g, $gm, $w ? 1 : 0, $r === 'loss' ? 1 : 0, $streak, $nt, $u['id']]);
        // XP: cartas do baralho e torre (só contra a IA)
        $xp = $w ? ($rk ? 60 + 10 * $L : [20, 30, 40][$d]) : ($r === 'loss' ? 8 : 12);
        if ($w) $xp = (int)round($xp * (1 + min(.5, .1 * max(0, $streak - 1))));
        $lv = function ($x) { $l = 1; $n = 100; while ($l < 10 && $x >= $n) { $x -= $n; $l++; $n += 60; } return $l; };
        $cx = $u['cxp']; $used = array_flip(array_map('strval', (array)($in['used'] ?? []))); $up = [];
        foreach (array_slice(array_unique(array_map('strval', (array)($in['deck'] ?? []))), 0, 8) as $id) {
            if (!in_array($id, $u['owned'], true)) continue;
            $gn = (int)round((isset($used[$id]) ? $xp : intdiv($xp, 2)) * [1, 1, 1, .85, .7][min(4, RARITY[$id] ?? 0)]);
            $o = (int)($cx[$id] ?? 0); $cx[$id] = $o + $gn;
            if ($lv($cx[$id]) > $lv($o)) $up[] = ['id' => $id, 'l' => $lv($cx[$id])];
        }
        $tx = (int)$u['txp']; $tup = $lv($tx + $xp) > $lv($tx) ? $lv($tx + $xp) : 0;
        q("UPDATE players SET cxp=?, txp=txp+? WHERE id=?", [json_encode((object)$cx), $xp, $u['id']]);
        out(['user' => me(), 'delta' => $nt - $u['trophies'], 'gold' => $g, 'gems' => $gm, 'chest' => $chest, 'streak' => $streak, 'sb' => $sb, 'card' => $card, 'milestone' => $ms, 'xp' => $xp, 'up' => $up, 'tup' => $tup]);

    case 'chest_open':
        $u = need(); $i = (int)($in['i'] ?? -1); $ch = $u['chests'];
        if (!isset($ch[$i])) fail('Baú não encontrado.');
        $t = $ch[$i]; array_splice($ch, $i, 1);
        [$n, $w, $gmax] = CHESTS[$t];
        $own = $u['owned']; $got = []; $gold = 10 * $n;
        for ($k = 0; $k < $n; $k++) {
            $rar = rr($w); $ua = arenaOf($u['trophies']);
            do { $pool = array_keys(array_filter(RARITY, fn($q, $k) => $q === $rar && CARD_AR[$k] <= $ua, ARRAY_FILTER_USE_BOTH)); } while (!$pool && $rar-- > 0);
            $id = $pool[array_rand($pool)];
            if (in_array($id, $own)) { $gold += DUP[$rar]; $got[] = ['id' => $id, 'new' => false, 'gold' => DUP[$rar]]; }
            else { $own[] = $id; $got[] = ['id' => $id, 'new' => true]; }
        }
        $gems = mt_rand(0, $gmax);
        q("UPDATE players SET owned=?, chests=?, gold=gold+?, gems=gems+? WHERE id=?", [json_encode($own), json_encode(array_values($ch)), $gold, $gems, $u['id']]);
        out(['user' => me(), 'got' => $got, 'gems' => $gems, 'gold' => $gold]);

    case 'shop_buy':
        $u = need(); $id = $in['id'] ?? '';
        if (!isset(RARITY[$id])) fail('Carta inexistente.');
        if (in_array($id, $u['owned'])) fail('Você já tem essa carta.');
        if (CARD_AR[$id] > arenaOf($u['trophies'])) fail('Essa lenda ainda não apareceu na sua arena.');
        $p = PRICE[RARITY[$id]];
        if ($u['gems'] < $p) fail('Gemas insuficientes.');
        $own = $u['owned']; $own[] = $id;
        q("UPDATE players SET owned=?, gems=gems-? WHERE id=?", [json_encode($own), $p, $u['id']]);
        out(['user' => me()]);

    case 'clan_chat':
        $u = need(); if (!$u['clan_id']) fail('Você não está em um clã.');
        $l = q("SELECT id,name,avatar,text,img,t FROM clan_msgs WHERE clan_id=? AND id>? ORDER BY id DESC LIMIT 50", [$u['clan_id'], (int)($in['since'] ?? 0)])->fetchAll(PDO::FETCH_ASSOC);
        out(['list' => array_reverse($l)]);

    case 'clan_chat_send':
        $u = need(); if (!$u['clan_id']) fail('Você não está em um clã.');
        $t = trim(mb_substr(preg_replace('/\s+/u', ' ', $in['text'] ?? ''), 0, 200));
        $img = $in['img'] ?? null;
        if ($img !== null && $img !== '') {
            if (!is_string($img) || strlen($img) > 400000 || !preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/=]+$#', $img)) fail('Foto inválida ou muito grande.');
        } else $img = null;
        if ($t === '' && !$img) fail('Mensagem vazia.');
        $last = q("SELECT t FROM clan_msgs WHERE player_id=? ORDER BY id DESC LIMIT 1", [$u['id']])->fetchColumn();
        if ($last && microtime(true) * 1000 - $last < 1500) fail('Calma! Espere um pouquinho para mandar outra.');
        q("INSERT INTO clan_msgs(clan_id,player_id,name,avatar,text,img,t) VALUES(?,?,?,?,?,?,?)", [$u['clan_id'], $u['id'], $u['name'], $u['avatar'], $t, $img, (int)(microtime(true) * 1000)]);
        q("DELETE FROM clan_msgs WHERE clan_id=? AND id NOT IN (SELECT id FROM clan_msgs WHERE clan_id=? ORDER BY id DESC LIMIT 200)", [$u['clan_id'], $u['clan_id']]);
        out(['ok' => 1]);

    // ---------- Jogador contra jogador (ao vivo, lockstep por polling) ----------
    case 'pvp_queue':
        $u = need();
        if (!$u['tut']) fail('Conclua o tutorial primeiro.'); $now = time();
        q("UPDATE matches SET status='gone' WHERE status='wait' AND hseen<?", [$now - 15]);
        q("DELETE FROM matches WHERE status='wait' AND host=?", [$u['id']]);
        $inv = trim($in['invite'] ?? ''); $mode = ($in['mode'] ?? '') === 'duo' ? 'duo' : 'pvp';
        if ($mode === 'duo' && $inv === '' && empty($in['accept'])) fail('Escolha um amigo para jogar em dupla.');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $in['code'] ?? ''));
        if ($code !== '') {
            $db->beginTransaction();
            $m = q("SELECT id,host FROM matches WHERE status='wait' AND code=? AND host<>? AND hseen>=? ORDER BY id LIMIT 1", [$code, $u['id'], $now - 15])->fetch(PDO::FETCH_ASSOC);
            if ($m) {
                q("UPDATE matches SET guest=?, status='live', gseen=?, hseen=? WHERE id=?", [$u['id'], $now, $now, $m['id']]);
                $db->commit(); hook('match.start', ['m' => (int)$m['id'], 'host' => (int)$m['host'], 'guest' => (int)$u['id'], 'room' => $code]);
                out(['m' => (int)$m['id'], 'side' => -1, 'status' => 'live', 'mode' => 'pvp']);
            }
            $db->commit();
            q("INSERT INTO matches(host,status,created,hseen,code,mode) VALUES(?,'wait',?,?,?,'pvp')", [$u['id'], $now, $now, $code]);
            out(['m' => (int)$db->lastInsertId(), 'side' => 1, 'status' => 'wait', 'room' => $code]);
        }
        if ($inv !== '') {
            $t = q("SELECT id,name,seen FROM players WHERE name=?", [$inv])->fetch(PDO::FETCH_ASSOC);
            if (!$t || $t['id'] == $u['id']) fail('Jogador não encontrado.');
            q("UPDATE matches SET status='gone' WHERE status='wait' AND host=? AND invite=?", [$u['id'], $t['id']]);
            q("INSERT INTO matches(host,status,created,hseen,invite,mode) VALUES(?,'wait',?,?,?,?)", [$u['id'], $now, $now, $t['id'], $mode]);
            out(['m' => (int)$db->lastInsertId(), 'side' => 1, 'status' => 'wait', 'mode' => $mode, 'online' => (int)$t['seen'] >= $now - ONLINE]);
        }
        $acc = (int)($in['accept'] ?? 0);
        $db->beginTransaction();
        $m = $acc ? q("SELECT id,host,mode FROM matches WHERE id=? AND status='wait' AND invite=?", [$acc, $u['id']])->fetch(PDO::FETCH_ASSOC)
                  : q("SELECT id,host,mode FROM matches WHERE status='wait' AND invite IS NULL AND host<>? ORDER BY id LIMIT 1", [$u['id']])->fetch(PDO::FETCH_ASSOC);
        if ($m) {
            q("UPDATE matches SET guest=?, status='live', gseen=?, hseen=? WHERE id=?", [$u['id'], $now, $now, $m['id']]);
            $db->commit(); hook('match.start', ['m' => (int)$m['id'], 'host' => (int)$m['host'], 'guest' => (int)$u['id']]);
            out(['m' => (int)$m['id'], 'side' => -1, 'status' => 'live', 'mode' => $m['mode'] ?: 'pvp']);
        }
        $db->commit();
        if ($acc) fail('Esse convite expirou.');
        q("INSERT INTO matches(host,status,created,hseen) VALUES(?,'wait',?,?)", [$u['id'], $now, $now]);
        out(['m' => (int)$db->lastInsertId(), 'side' => 1, 'status' => 'wait']);

    case 'pvp_invites':
        $u = need();
        out(['list' => q("SELECT m.id,m.mode,p.name,p.avatar,p.trophies FROM matches m JOIN players p ON p.id=m.host WHERE m.status='wait' AND m.invite=? AND m.hseen>=?", [$u['id'], time() - 15])->fetchAll(PDO::FETCH_ASSOC)]);

    case 'pvp_decline':
        $u = need();
        q("UPDATE matches SET status='declined' WHERE id=? AND invite=? AND status='wait'", [(int)($in['m'] ?? 0), $u['id']]);
        out(['ok' => 1]);

    case 'pvp_cancel':
        $u = need();
        q("UPDATE matches SET status='gone' WHERE id=? AND host=? AND status='wait'", [(int)($in['m'] ?? 0), $u['id']]);
        out(['ok' => 1]);

    case 'pvp_sync':
        $u = need(); $now = time();
        $m = q("SELECT * FROM matches WHERE id=?", [(int)($in['m'] ?? 0)])->fetch(PDO::FETCH_ASSOC);
        if (!$m || ($m['host'] != $u['id'] && $m['guest'] != $u['id'])) fail('Partida não encontrada.');
        $side = $m['host'] == $u['id'] ? 1 : -1; $me = $side == 1 ? 'h' : 'g'; $op = $side == 1 ? 'g' : 'h';
        if ($m['status'] === 'wait') { q("UPDATE matches SET hseen=? WHERE id=?", [$now, $m['id']]); out(['status' => 'wait']); }
        if ($m['status'] === 'live') {
            $tick = min(PVP_TICKS + 200, max((int)$m[$me . 't'], (int)($in['tick'] ?? 0)));
            $db->beginTransaction();
            foreach (array_slice($in['cmds'] ?? [], 0, 20) as $c) {
                $id = $c['card'] ?? ''; $ct = (int)($c['tick'] ?? 0);
                $okE = isset(RARITY[$id]) || in_array($id, EMOTES) || (preg_match('/^e:@(\w+)$/', $id, $mm) && in_array('m:' . $mm[1], $u['items']));
                if (!$okE || $ct <= (int)$m[$me . 't']) continue;
                q("INSERT INTO moves(match_id,side,tick,card,x,y) VALUES(?,?,?,?,?,?)", [$m['id'], $side, $ct, $id, (float)$c['x'], (float)$c['y']]);
            }
            q("UPDATE matches SET {$me}t=?, {$me}seen=? WHERE id=?", [$tick, $now, $m['id']]);
            $db->commit();
            if ($now - (int)$m[$op . 'seen'] > 20) { // oponente sumiu
                q("UPDATE matches SET status='done', result=? WHERE id=? AND status='live'", [$m['mode'] === 'duo' ? 'draw' : ($side == 1 ? 'host' : 'guest'), $m['id']]);
                $m = q("SELECT * FROM matches WHERE id=?", [$m['id']])->fetch(PDO::FETCH_ASSOC);
            }
        }
        if (!empty($in['wait']) && $m['status'] === 'live') { // comunicação síncrona: segura a resposta até o oponente avançar (máx. 3 s)
            session_write_close(); $c0 = (int)($in['ot'] ?? 0); $t0 = microtime(true);
            while (microtime(true) - $t0 < 3) {
                $o = q("SELECT {$op}t AS t, status, (SELECT COUNT(*) FROM moves WHERE match_id=? AND side=? AND id>?) AS n FROM matches WHERE id=?", [$m['id'], -$side, (int)($in['since'] ?? 0), $m['id']])->fetch(PDO::FETCH_ASSOC);
                if ((int)$o['t'] > $c0 || (int)$o['n'] > 0 || $o['status'] !== 'live') break;
                usleep(60000);
            }
        }
        $ot = (int)q("SELECT {$op}t FROM matches WHERE id=?", [$m['id']])->fetchColumn(); // lê o tick antes dos lances
        $mv = q("SELECT id,tick,card,x,y FROM moves WHERE match_id=? AND side=? AND id>? ORDER BY id", [$m['id'], -$side, (int)($in['since'] ?? 0)])->fetchAll(PDO::FETCH_ASSOC);
        $st = q("SELECT status,result FROM matches WHERE id=?", [$m['id']])->fetch(PDO::FETCH_ASSOC);
        $opp = q("SELECT name,avatar,trophies,eqp FROM players WHERE id=?", [$side == 1 ? $m['guest'] : $m['host']])->fetch(PDO::FETCH_ASSOC);
        if ($opp) { $e = J($opp['eqp'], []); $opp['tower'] = $e['t'] ?? ''; unset($opp['eqp']); }
        out(['status' => $st['status'], 'result' => $st['result'], 'otick' => $ot, 'moves' => $mv, 'opp' => $opp, 'side' => $side, 'mode' => $m['mode'] ?: 'pvp']);

    case 'pvp_end':
        $u = need();
        $m = q("SELECT * FROM matches WHERE id=?", [(int)($in['m'] ?? 0)])->fetch(PDO::FETCH_ASSOC);
        if (!$m || ($m['host'] != $u['id'] && $m['guest'] != $u['id']) || !$m['guest']) fail('Partida não encontrada.');
        $side = $m['host'] == $u['id'] ? 1 : -1; $me = $side == 1 ? 'host' : 'guest';
        $r = $in['r'] ?? ''; $duo = $m['mode'] === 'duo';
        if ($r === 'quit') $r = $duo ? 'loss' : ($side == 1 ? 'guest' : 'host');
        if (!in_array($r, $duo ? ['win', 'loss', 'draw'] : ['host', 'guest', 'draw'])) fail('Resultado inválido.');
        if ($m['status'] === 'live') { q("UPDATE matches SET status='done', result=? WHERE id=? AND status='live'", [$r, $m['id']]); }
        $m = q("SELECT * FROM matches WHERE id=?", [$m['id']])->fetch(PDO::FETCH_ASSOC);
        $paid = 'paid_' . $m['id'] . '_' . $u['id'];
        $res = $duo ? $m['result'] : ($m['result'] === 'draw' ? 'draw' : ($m['result'] === $me ? 'win' : 'loss'));
        if (!empty($_SESSION[$paid]) || q("SELECT 1 FROM moves WHERE match_id=? AND side=0 AND card=?", [$m['id'], (string)$u['id']])->fetch())
            out(['user' => me(), 'res' => $res, 'delta' => 0, 'gold' => 0]);
        q("INSERT INTO moves(match_id,side,tick,card) VALUES(?,0,0,?)", [$m['id'], (string)$u['id']]); // marca recompensa paga
        $_SESSION[$paid] = 1; hook('match.end', ['m' => (int)$m['id'], 'result' => $m['result'], 'mode' => $m['mode'] ?: 'pvp']);
        $dt = $res === 'win' ? ($duo ? 30 : 35) : ($res === 'loss' ? ($duo ? -10 : -20) : 0); $g = $res === 'win' ? ($duo ? 45 : 50) : 10; $gm = $res === 'win' ? 2 : 0;
        $card = null; if ($res === 'win' && mt_rand(1, 100) <= 30) $card = grantCard($u);
        $nt = max(0, $u['trophies'] + $dt);
        q("UPDATE players SET trophies=?, gold=gold+?, gems=gems+?, wins=wins+?, losses=losses+?, best=MAX(COALESCE(best,0),?) WHERE id=?",
          [$nt, $g, $gm, $res === 'win' ? 1 : 0, $res === 'loss' ? 1 : 0, $nt, $u['id']]);
        out(['user' => me(), 'res' => $res, 'delta' => $nt - $u['trophies'], 'gold' => $g, 'gems' => $gm, 'card' => $card]);

    // ---------- Loja de visuais, troca de nome ----------
    case 'rename':
        $u = need(); $n = trim($in['name'] ?? '');
        if (empty($in['confirm'])) fail('Confirme a troca de nome.');
        if (!preg_match('/^[\p{L}\p{N}_ ]{3,16}$/u', $n)) fail('Nome: 3 a 16 letras ou números.');
        if ($n === $u['name']) fail('Esse já é o seu nome.');
        if ($u['gold'] < RENAME) fail('Trocar de nome custa ' . RENAME . ' moedas.');
        if (q("SELECT 1 FROM players WHERE name=? AND id<>?", [$n, $u['id']])->fetch()) fail('Esse nome já está em uso.');
        try { $st = q("UPDATE players SET name=?, gold=gold-? WHERE id=? AND gold>=?", [$n, RENAME, $u['id'], RENAME]); } catch (Exception $e) { fail('Esse nome já está em uso.'); }
        if (!$st->rowCount()) fail('Moedas insuficientes.');
        out(['user' => me()]);

    case 'item_buy':
        $u = need(); $id = $in['id'] ?? '';
        if (!isset(ITEMS[$id])) fail('Item inexistente.');
        if (in_array($id, $u['items'])) fail('Você já tem esse item.');
        [$cur, $p] = ITEMS[$id]; $f = $cur === 'g' ? 'gold' : 'gems';
        if ($u[$f] < $p) fail($cur === 'g' ? 'Moedas insuficientes.' : 'Diamantes insuficientes.');
        $st = q("UPDATE players SET $f=$f-? WHERE id=? AND $f>=?", [$p, $u['id'], $p]);
        if (!$st->rowCount()) fail('Saldo insuficiente.');
        $it = $u['items']; $it[] = $id; sv($u['id'], 'items', $it);
        out(['user' => me()]);

    case 'item_equip':
        $u = need(); $s = $in['slot'] ?? ''; $id = $in['id'] ?? '';
        if (!in_array($s, ['b', 't'])) fail('Espaço inválido.');
        if ($id !== '' && (!in_array($id, $u['items']) || $id[0] !== $s)) fail('Você não tem esse item.');
        $e = J(q("SELECT eqp FROM players WHERE id=?", [$u['id']])->fetchColumn(), []); $e[$s] = $id;
        q("UPDATE players SET eqp=? WHERE id=?", [json_encode($e), $u['id']]);
        out(['user' => me()]);

    // ---------- Amigos ----------
    case 'friend_add':
        $u = need(); $t = fid($in['name'] ?? '');
        if (!$t || $t == $u['id']) fail('Jogador não encontrado.');
        $ex = q("SELECT a,b,status FROM friends WHERE (a=? AND b=?) OR (a=? AND b=?)", [$u['id'], $t, $t, $u['id']])->fetch(PDO::FETCH_ASSOC);
        if ($ex && $ex['status'] === 'ok') fail('Vocês já são amigos.');
        if ($ex && $ex['a'] == $t) { q("UPDATE friends SET status='ok' WHERE a=? AND b=?", [$t, $u['id']]); out(['ok' => 1, 'msg' => 'Agora vocês são amigos! 🎉']); }
        if ($ex) fail('Pedido já enviado. Aguarde a resposta.');
        if (q("SELECT COUNT(*) FROM friends WHERE (a=? OR b=?) AND status='ok'", [$u['id'], $u['id']])->fetchColumn() >= 100) fail('Limite de 100 amigos.');
        q("INSERT INTO friends(a,b,status) VALUES(?,?,'pending')", [$u['id'], $t]);
        out(['ok' => 1, 'msg' => 'Pedido de amizade enviado!']);

    case 'friend_accept':
        $u = need(); $t = fid($in['name'] ?? '');
        q("UPDATE friends SET status='ok' WHERE a=? AND b=? AND status='pending'", [$t, $u['id']]);
        out(['ok' => 1]);

    case 'friend_remove':
        $u = need(); $t = fid($in['name'] ?? '');
        q("DELETE FROM friends WHERE (a=? AND b=?) OR (a=? AND b=?)", [$u['id'], $t, $t, $u['id']]);
        out(['ok' => 1]);

    case 'friend_list':
        $u = need(); $on = time() - ONLINE;
        $f = q("SELECT p.name,p.avatar,p.title,p.trophies,(p.seen>=?) AS online FROM friends f JOIN players p ON p.id=(CASE WHEN f.a=? THEN f.b ELSE f.a END)
                WHERE (f.a=? OR f.b=?) AND f.status='ok' ORDER BY online DESC, p.trophies DESC", [$on, $u['id'], $u['id'], $u['id']])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($f as &$x) $x['online'] = (bool)$x['online']; unset($x);
        $in_ = q("SELECT p.name,p.avatar,p.trophies FROM friends f JOIN players p ON p.id=f.a WHERE f.b=? AND f.status='pending'", [$u['id']])->fetchAll(PDO::FETCH_ASSOC);
        $out_ = q("SELECT p.name,p.avatar FROM friends f JOIN players p ON p.id=f.b WHERE f.a=? AND f.status='pending'", [$u['id']])->fetchAll(PDO::FETCH_ASSOC);
        out(['friends' => $f, 'incoming' => $in_, 'outgoing' => $out_]);

    case 'webhook': // entrada: sistemas externos (bots, Discord, etc.) enviam eventos assinados com X-Encantados-Signature
        $cf = __DIR__ . '/webhook.json'; $c = is_file($cf) ? json_decode(file_get_contents($cf), true) : [];
        $raw = file_get_contents('php://input'); $sig = $_SERVER['HTTP_X_ENCANTADOS_SIGNATURE'] ?? '';
        if (empty($c['secret']) || !hash_equals('sha256=' . hash_hmac('sha256', $raw, $c['secret']), $sig)) { http_response_code(401); out(['error' => 'Assinatura inválida.']); }
        if (($in['event'] ?? '') === 'invite') { // {"event":"invite","data":{"from":"Ana","to":"Beto"}}
            $h = fid($in['data']['from'] ?? ''); $t = fid($in['data']['to'] ?? '');
            if (!$h || !$t || $h == $t) fail('Jogadores inválidos.');
            q("INSERT INTO matches(host,status,created,hseen,invite,mode) VALUES(?,'wait',?,?,?,'pvp')", [$h, time(), time() + 600, $t]);
            out(['ok' => 1, 'm' => (int)$db->lastInsertId()]);
        }
        out(['ok' => 1]);

    default: fail('Ação desconhecida.');
}
