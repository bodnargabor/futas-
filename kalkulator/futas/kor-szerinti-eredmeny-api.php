<?php
/* =========================================================================
   kor-szerinti-eredmeny-api.php  –  futas.net
   A korosztályos kalkulátor adatszolgáltatója
   -------------------------------------------------------------------------
   Nem adja ki a teljes táblázatot: egy kérésre csak azt a szeletet küldi,
   ami az aktuális számításhoz kell.

   Bemenet (GET):
     nem  = M | F
     kor  = 5…100 (egész, a verseny napján betöltött életkor)
     tav  = hivatalos táv kulcsa (pl. 10k, hm, mar)   – vagy –
     km   = egyéni táv kilométerben, 1 mérföld és 200 km között

   Kimenet (JSON):
     tav    – a kért táv: nev, km, oc (nyílt alapidő mp), fak, becsult,
              becsült távnál also/felso (a két szomszédos hivatalos táv neve)
     tavok  – a 22 hivatalos táv faktora és alapideje a megadott életkorban
     korok  – a kért táv faktora és alapideje ötévenként, a görbéhez és az
              életkoronkénti táblázathoz (plusz a megadott életkor)

   Védelem: csak ugyanarról az oldalról, egyszerű IP-alapú kéréskorláttal.
   Ez lassítja, de nem teszi lehetetlenné a táblázat letöltögetését –
   ami a böngészőben megjelenik, azt elvileg ki lehet olvasni.
   ========================================================================= */

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

const KERES_MAX   = 150;   // ennyi kérés engedélyezett…
const KERES_ABLAK = 600;   // …ennyi másodpercenként, IP-címenként

function valasz(int $kod, array $tartalom): void
{
    http_response_code($kod);
    echo json_encode($tartalom, JSON_UNESCAPED_UNICODE);
    exit;
}

/* --- Csak a saját oldalunkról ------------------------------------------ */
$fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($fetchSite !== '' && $fetchSite !== 'same-origin') {
    valasz(403, ['ok' => false, 'hiba' => 'Tiltott forrás.']);
}
$hivo = $_SERVER['HTTP_REFERER'] ?? '';
if ($hivo !== '' && parse_url($hivo, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    valasz(403, ['ok' => false, 'hiba' => 'Tiltott forrás.']);
}
if (($_SERVER['HTTP_X_KSE'] ?? '') !== '1') {
    valasz(403, ['ok' => false, 'hiba' => 'Hiányzó fejléc.']);
}

/* --- Kéréskorlát: fix időablak, IP-nként egy apró fájl a temp mappában --- */
function keresEngedelyezett(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'ismeretlen';
    $fajl = sys_get_temp_dir() . '/kse_' . md5('futasnet-kse|' . $ip);
    $most = time();
    $allapot = null;
    $h = @fopen($fajl, 'c+');
    if (!$h) {
        return true;   // ha nem írható a temp, inkább kiszolgálunk
    }
    flock($h, LOCK_EX);
    $tartalom = stream_get_contents($h);
    if ($tartalom) {
        $allapot = json_decode($tartalom, true);
    }
    if (!is_array($allapot) || ($allapot['t'] ?? 0) < $most - KERES_ABLAK) {
        $allapot = ['t' => $most, 'n' => 0];
    }
    $allapot['n']++;
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($allapot));
    flock($h, LOCK_UN);
    fclose($h);
    return $allapot['n'] <= KERES_MAX;
}
if (!keresEngedelyezett()) {
    header('Retry-After: ' . KERES_ABLAK);
    valasz(429, ['ok' => false, 'hiba' => 'Túl sok kérés rövid idő alatt. Próbáld újra néhány perc múlva.']);
}

/* --- Adatok ------------------------------------------------------------- */
$adat = require __DIR__ . '/adat/wma-road-2025.php';
if (!is_array($adat)) {
    valasz(500, ['ok' => false, 'hiba' => 'Az adatok nem érhetők el.']);
}
$tavok  = $adat['tavok'];
$korMin = $adat['korMin'];
$korMax = $adat['korMax'];

/* --- Bemenet ellenőrzése ------------------------------------------------ */
$nem = $_GET['nem'] ?? '';
if ($nem !== 'M' && $nem !== 'F') {
    valasz(400, ['ok' => false, 'hiba' => 'Hibás nem.']);
}
$kor = filter_var($_GET['kor'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => $korMin, 'max_range' => $korMax]]);
if ($kor === false) {
    valasz(400, ['ok' => false, 'hiba' => "Az életkor $korMin és $korMax év között lehet."]);
}

function faktor(array $adat, string $nem, int $kor, int $idx): float
{
    return $adat['fak'][$nem][$kor - $adat['korMin']][$idx] / 10000;
}

/* A táv leírója: hivatalos táv indexe, vagy a két szomszéd és az u arány. */
$leiro = null;
if (isset($_GET['tav'])) {
    foreach ($tavok as $i => $t) {
        if ($t['k'] === $_GET['tav']) {
            $leiro = ['idx' => $i];
            break;
        }
    }
} elseif (isset($_GET['km'])) {
    $km = filter_var(str_replace(',', '.', $_GET['km']), FILTER_VALIDATE_FLOAT);
    $kmMin = $tavok[0]['km'];
    $kmMax = $tavok[count($tavok) - 1]['km'];
    if ($km !== false && $km >= $kmMin - 0.0005 && $km <= $kmMax + 0.0005) {
        foreach ($tavok as $i => $t) {
            if (abs($t['km'] - $km) < 0.0005) {
                $leiro = ['idx' => $i];
                break;
            }
        }
        if (!$leiro) {
            $i = 0;
            while ($i < count($tavok) - 2 && $tavok[$i + 1]['km'] < $km) {
                $i++;
            }
            $a = $tavok[$i]['km'];
            $b = $tavok[$i + 1]['km'];
            $leiro = [
                'idx'   => -1,
                'km'    => $km,
                'also'  => $i,
                'felso' => $i + 1,
                'u'     => (log($km) - log($a)) / (log($b) - log($a)),
            ];
        }
    }
}
if (!$leiro) {
    valasz(400, ['ok' => false, 'hiba' => 'Hibás vagy nem támogatott táv.']);
}

/* Nyílt alapidő és faktor a leíró szerint – becsült távnál a 2025-ös
   táblázat módszerével: a faktor lineáris a táv logaritmusában, a nyílt
   alapidő log-log interpolációval. */
function tavErtek(array $adat, string $nem, int $kor, array $leiro): array
{
    if ($leiro['idx'] >= 0) {
        return [$adat['tavok'][$leiro['idx']]['oc'][$nem], faktor($adat, $nem, $kor, $leiro['idx'])];
    }
    $u = $leiro['u'];
    $ocA = $adat['tavok'][$leiro['also']]['oc'][$nem];
    $ocB = $adat['tavok'][$leiro['felso']]['oc'][$nem];
    return [
        exp(log($ocA) * (1 - $u) + log($ocB) * $u),
        faktor($adat, $nem, $kor, $leiro['also']) * (1 - $u) + faktor($adat, $nem, $kor, $leiro['felso']) * $u,
    ];
}

/* --- Válasz összeállítása ----------------------------------------------- */
[$oc, $fak] = tavErtek($adat, $nem, $kor, $leiro);
if ($leiro['idx'] >= 0) {
    $t = $tavok[$leiro['idx']];
    $tav = ['k' => $t['k'], 'nev' => $t['nev'], 'km' => $t['km'], 'becsult' => false];
} else {
    $tav = [
        'k'       => 'egyeni',
        'nev'     => str_replace('.', ',', (string) round($leiro['km'], 3)) . ' km',
        'km'      => $leiro['km'],
        'becsult' => true,
        'also'    => $tavok[$leiro['also']]['nev'],
        'felso'   => $tavok[$leiro['felso']]['nev'],
    ];
}
$tav['oc']  = round($oc, 2);
$tav['fak'] = round($fak, 6);

/* A 22 hivatalos táv ebben az életkorban */
$tavLista = [];
foreach ($tavok as $i => $t) {
    $tavLista[] = [
        'k'   => $t['k'],
        'oc'  => $t['oc'][$nem],
        'fak' => faktor($adat, $nem, $kor, $i),
    ];
}

/* Ötévenkénti életkorok a görbéhez és a táblázathoz, plusz a megadott kor.
   A görbe tartománya ugyanaz, amit a kliens rajzol. */
$korTol = max($korMin, min(20, $kor - 5));
$korIg  = min($korMax, max(95, $kor + 5));
$korLista = [];
for ($k = (int) ceil($korTol / 5) * 5; $k <= $korIg; $k += 5) {
    $korLista[] = $k;
}
foreach ([$korTol, $korIg, $kor] as $k) {
    if (!in_array($k, $korLista, true)) {
        $korLista[] = $k;
    }
}
sort($korLista);
$korok = [];
foreach ($korLista as $k) {
    [$kOc, $kFak] = tavErtek($adat, $nem, $k, $leiro);
    $korok[] = ['kor' => $k, 'oc' => round($kOc, 2), 'fak' => round($kFak, 6)];
}

valasz(200, [
    'ok'    => true,
    'nem'   => $nem,
    'kor'   => $kor,
    'tav'   => $tav,
    'tavok' => $tavLista,
    'korok' => $korok,
]);
