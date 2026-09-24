<?php
/* =========================================================================
   kor-szerinti-eredmeny.php  –  futas.net
   Korosztályos (age grading) futóeredmény-kalkulátor
   -------------------------------------------------------------------------
   Adatforrás: Alan Jones – Tom Bernhard: Road Running Age Standards 2025
   (MaleRoadStd2025.xlsx, FemaleRoadStd2025.xlsx, 2025-07-27-es változat),
   jóváhagyta: USATF Masters Long Distance Running Council, 2025-01-10.
   https://github.com/AlanLyttonJones/Age-Grade-Tables

   Az adat az adat/wma-road-2025.php-ben van, a lap nem tartalmazza egyben.
   A számításhoz szükséges szeleteket (egy nem–életkor–táv hármashoz) a
   kor-szerinti-eredmeny-api.php szolgálja ki. Új táblázat
   esetén csak az adatfájlt kell cserélni.
   ========================================================================= */

ini_set('display_errors', '0');
date_default_timezone_set('Europe/Budapest');

/* --- OLDAL ADATAI ------------------------------------------------------- */
$oldalCim   = 'Kor szerinti futóeredmény – WMA korosztályos kalkulátor';
$oldalLeiro = 'Korosztályos futóeredmény-kalkulátor a 2025-ös WMA/USATF utcai age grading táblázat alapján: korfaktor, korosztályos százalék, korrigált idő, célidők és egyenértékű idők 22 távra 1 mérföldtől 200 km-ig.';
$oldalKulcs = 'age grading, korosztályos eredmény, korfaktor, WMA, masters futás, szenior futó, kor szerinti százalék, age grade kalkulátor';
$oldalUrl   = 'https://www.futas.net/kalkulator/futas/kor-szerinti-eredmeny.php';
$oldalMappa = 'https://www.futas.net/kalkulator/futas/';
$tema       = 'tema-terrakotta';

/* --- FEJLÉC HERO-KÉPEI --------------------------------------------------- */
$heroKepek = [
    'kor-szerinti-eredmeny.jpg',
];

/* --- ADATOK ELŐKÉSZÍTÉSE ------------------------------------------------ */

/* A 2025-ös utcai táblázat szerveroldalon él (adat/), a lapba nem kerül
   bele egyben: a böngésző a kor-szerinti-eredmeny-api.php-tól mindig csak
   az aktuális számításhoz szükséges szeletet kapja meg. Itt csak a
   távlistához, a nyílt alapidők táblázatához és a példához kell. */
$adat = @include __DIR__ . '/adat/wma-road-2025.php';
if (!is_array($adat)) {
    $adat = null;
}

/* A kliensnek csak a távok neve és hossza kell (a chipekhez). */
$tavLista = [];
if ($adat) {
    foreach ($adat['tavok'] as $tav) {
        $tavLista[] = ['k' => $tav['k'], 'nev' => $tav['nev'], 'km' => $tav['km']];
    }
}

/* Idő formázása h:mm:ss vagy m:ss alakra. */
function idoFormaz(float $mp): string
{
    $mp = (int) round($mp);
    $o = intdiv($mp, 3600);
    $p = intdiv($mp % 3600, 60);
    $s = $mp % 60;
    return $o > 0 ? sprintf('%d:%02d:%02d', $o, $p, $s) : sprintf('%d:%02d', $p, $s);
}

/* Tizedesvesszős szám. */
function szamHu(float $x, int $tized): string
{
    return number_format($x, $tized, ',', ' ');
}

/* Kidolgozott példa az oldal alján: 50 éves férfi, 10 km, 45:00. A számok
   a beágyazott táblázatból jönnek, így táblázatcserénél maguktól frissülnek. */
$pelda = null;
if ($adat) {
    foreach ($adat['tavok'] as $i => $tav) {
        if ($tav['k'] === '10k') {
            $peldaKor   = 50;
            $peldaIdo   = 45 * 60;
            $peldaOc    = $tav['oc']['M'];
            $peldaFak   = $adat['fak']['M'][$peldaKor - $adat['korMin']][$i] / 10000;
            $peldaAlap  = $peldaOc / $peldaFak;
            $pelda = [
                'kor'       => $peldaKor,
                'ido'       => idoFormaz($peldaIdo),
                'oc'        => idoFormaz($peldaOc),
                'ocMp'      => $peldaOc,
                'fak'       => szamHu($peldaFak, 4),
                'alap'      => idoFormaz($peldaAlap),
                'alapMp'    => szamHu($peldaAlap, 1),
                'szazalek'  => szamHu($peldaAlap / $peldaIdo * 100, 2),
                'korrigalt' => idoFormaz($peldaIdo * $peldaFak),
            ];
            break;
        }
    }
}

/* GYIK – ebből készül a lenyíló lista és a FAQPage JSON-LD is. */
$gyik = [
    ['Mit jelent a korosztályos százalék?',
     'Azt mutatja meg, hogy az időd hány százaléka a saját életkorodra és nemedre számított csúcsteljesítménynek. 100% azt jelenti, hogy a korosztályod legjobb valaha mért szintjén futottál; 50% azt, hogy kétszer annyi ideig tartott a táv, mint a 100%-os futónak.'],
    ['Miért kaphat egy 60 éves jobb értékelést, mint egy gyorsabb 30 éves?',
     'Mert a kalkulátor nem az abszolút időt, hanem a korosztályhoz mért teljesítményt értékeli. Egy 60 éves férfi 10 km-es korfaktora 0,81 körül van, tehát ugyanakkora teljesítményhez nagyjából 23%-kal hosszabb idő tartozik, mint csúcskorban. Ha a 60 éves futó ennél kevésbé lassult, a százaléka magasabb lesz.'],
    ['Melyik táblázat alapján számol a kalkulátor?',
     'A legfrissebb utcai táblázat alapján: Alan Jones és Tom Bernhard 2025-ös Road Running Age Standards táblázata (2025-07-27-es változat), amelyet az amerikai atlétikai szövetség masters hosszútávfutó bizottsága (USATF MLDR) 2025. január 10-én hagyott jóvá. Ez a WMA/WAVA age grading hagyomány utcai ága, ezt használják a nagy versenyeredmény-rendszerek is.'],
    ['Mi a különbség a WMA 2023-as faktorai és a 2025-ös utcai táblázat között?',
     'A WMA 2023-as kiadványa elsősorban a stadionos (pálya- és ügyességi) számokra szól, utcai futásból csak a félmaratont és a maratont tartalmazza. A 2025-ös utcai táblázat frissebb adatokon alapul, és 22 távot fed le. A két táblázat faktorai ezért néhány ezreléknyit vagy akár 1–2 százalékpontnyit is eltérhetnek, a kalkulátor az utcai versenyekre készült 2025-ös táblázatot használja.'],
    ['Bruttó vagy nettó időt írjak be?',
     'A versenyeken a hivatalos eredményt (ez sokszor a bruttó, rajtpisztolytól mért idő) szokás korosztályosan értékelni. Saját fejlődésed követéséhez a nettó (chipes) idő is teljesen jó, csak mindig ugyanazt használd.'],
    ['Használható a kalkulátor stadionban futott időkre?',
     'Tájékoztató jelleggel igen, de ez utcai táblázat. A pályán futott 5000 m és 10 000 m hivatalos értékeléséhez a WMA pályás faktorai valók, a terep-, trail- és hegyi futásra pedig egyik táblázat sem alkalmas, mert ott a pálya nehézsége nagyobb hatású, mint az életkor.'],
    ['Mi a helyzet a 30 év alatti és a gyerek futókkal?',
     'A 2025-ös táblázat 5 és 100 év között minden életkorra ad faktort. Nagyjából 19 és 29 év között a faktor 1,0 (ez a csúcskor), alatta és fölötte csökken. A gyerekek és a fiatalok faktorai kevesebb adaton alapulnak, ezért azokat óvatosabban érdemes kezelni.'],
    ['Miért csak becslés az egyéni táv?',
     'A táblázat 22 hivatalos távra ad faktort. Köztes távnál (például 7,5 km) a kalkulátor a két szomszédos táv faktorát a távolság logaritmusa szerint interpolálja – pontosan így készültek a 2025-ös táblázat köztes távjai is –, a nyílt alapidőt pedig log-log interpolációval becsli. Ez jó közelítés, de nem hivatalos érték.'],
    ['Lehet 100% feletti eredmény?',
     'Elvileg igen, ha valaki a korosztályában új csúcsot fut, vagy ha a pálya rövidebb, lejtősebb, esetleg erős hátszél segítette. Hétköznapi versenyen a 100% feletti érték szinte mindig mérési vagy beviteli hibát jelez.'],
];
$gyikLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(function ($k) {
        return [
            '@type'          => 'Question',
            'name'           => $k[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $k[1]],
        ];
    }, $gyik),
];

$ma = date('Y-m-d');

/* Az og:image mindig a hero-lista első eleme. */
$oldalKep = $oldalMappa . 'kepek/' . $heroKepek[0];

/* A fejléc háttere csak a tényleg feltöltött képek közül sorsol. */
$heroLista = [];
foreach ($heroKepek as $fajlNev) {
    $relativ = 'kepek/' . $fajlNev;
    if (is_file(__DIR__ . '/' . $relativ)) {
        $heroLista[] = $relativ;
    }
}
$heroKep = $heroLista ? $heroLista[array_rand($heroLista)] : null;

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $oldalCim; ?></title>
    <meta name="description" content="<?php echo $oldalLeiro; ?>" />
    <meta name="keywords" content="<?php echo $oldalKulcs; ?>" />
    <link rel="canonical" href="<?php echo $oldalUrl; ?>" />
    <link rel="shortcut icon" href="/futas.ico" type="image/x-icon" />

    <meta property="og:type" content="article" />
    <meta property="og:locale" content="hu_HU" />
    <meta property="og:title" content="<?php echo $oldalCim; ?>" />
    <meta property="og:description" content="<?php echo $oldalLeiro; ?>" />
    <meta property="og:url" content="<?php echo $oldalUrl; ?>" />
    <meta property="og:image" content="<?php echo $oldalKep; ?>" />
    <meta name="twitter:card" content="summary_large_image" />

    <link href="/css/resp/mchs.css"    rel="stylesheet" type="text/css" media="all" />
    <link href="/css/resp/mchs-ui.css" rel="stylesheet" type="text/css" media="all" />

    <style>
    /* ===== OLDAL-SPECIFIKUS CSS =========================================
       Csak a korosztályos kalkulátor egyedi elemei: szintskála, életkor-
       görbe (SVG), az összevetés sorának törlőgombja. Szín csak tokenből.
    ==================================================================== */

    /* Szintskála: 40–100% közötti sáv öt szinttel, felette értékjelölővel. */
    .kse-skala { position: relative; margin: 1.5rem 0 1rem; padding-top: 2.4rem; }
    .kse-skala-sav {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
        gap: 3px;
        height: 12px;
    }
    .kse-skala-sav span { background: var(--ui-accent); border-radius: 3px; }
    .kse-skala-sav span:first-child { border-radius: 999px 3px 3px 999px; opacity: .15; }
    .kse-skala-sav span:nth-child(2) { opacity: .32; }
    .kse-skala-sav span:nth-child(3) { opacity: .52; }
    .kse-skala-sav span:nth-child(4) { opacity: .76; }
    .kse-skala-sav span:last-child { border-radius: 3px 999px 999px 3px; }
    .kse-skala-jelolo {
        position: absolute;
        top: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        transform: translateX(-50%);
        pointer-events: none;
    }
    .kse-skala-jelolo .ertek {
        padding: .15rem .6rem;
        border-radius: 999px;
        background: var(--ui-accent-dk);
        color: var(--ui-on-accent);
        font-weight: 700;
        font-size: .85rem;
        line-height: 1.4;
        white-space: nowrap;
        box-shadow: var(--ui-shadow);
    }
    .kse-skala-jelolo .tu {
        width: 3px;
        height: 26px;
        margin-top: 2px;
        border-radius: 2px;
        background: var(--ui-accent-dk);
    }
    @media (prefers-reduced-motion: no-preference) {
        .kse-skala-jelolo { transition: left .35s ease; }
    }
    .kse-skala-cimkek {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
        gap: 3px;
        margin-top: .4rem;
        font-size: .78rem;
        color: var(--ui-muted);
    }
    .kse-skala-cimkek span { padding-left: .25rem; }
    .kse-skala-cimkek i { font-style: normal; }
    @media (max-width: 560px) {
        .kse-skala-cimkek { font-size: .7rem; }
        .kse-skala-cimkek i { display: none; }
    }

    /* Életkor-görbe */
    .kse-grafikon { width: 100%; height: auto; min-width: 560px; display: block; }
    .kse-grafikon .racs { stroke: var(--ui-line, currentColor); stroke-opacity: .6; stroke-width: 1; }
    .kse-grafikon .tengely { stroke: var(--ui-line-strong, currentColor); stroke-width: 1.5; }
    .kse-grafikon text { fill: var(--ui-muted); font-family: var(--ui-font); font-size: 12px; }
    .kse-grafikon .gorbe-sajat { fill: none; stroke: var(--ui-accent, currentColor); stroke-width: 3; stroke-linejoin: round; }
    .kse-grafikon .gorbe-szaz { fill: none; stroke: var(--ui-ink-soft, currentColor); stroke-width: 2; stroke-dasharray: 6 5; }
    .kse-grafikon .pont { fill: var(--ui-accent); stroke: var(--ui-surface); stroke-width: 2; }
    .kse-grafikon .pont-sajat { fill: var(--ui-accent-dk); stroke: var(--ui-surface); stroke-width: 3; }
    .kse-grafikon .sajat-felirat { fill: var(--ui-ink); font-weight: 700; }
    .kse-jel {
        display: inline-block;
        width: 22px;
        height: 0;
        margin-right: .35rem;
        vertical-align: middle;
        border-top: 3px solid var(--ui-accent, currentColor);
    }
    .kse-jel.szaz { border-top: 2px dashed var(--ui-ink-soft, currentColor); }

    /* Apró segédszöveg a mezők alatt */
    .kse-sugo { font-size: .85rem; color: var(--ui-muted); margin: .3rem 0 0; }
    .kse-sugo.hiba { color: var(--ui-bad); }
    </style>

    <script type="application/ld+json"><?php echo json_encode($gyikLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>

    <?php include_once($_SERVER['DOCUMENT_ROOT'] . "/admin/ga/analyticstracking.php"); ?>
</head>

<body class="<?php echo $tema; ?>">
<div class="container">

    <!-- ═══════════ FEJLÉC ═══════════ -->
    <header class="header">
        <?php if ($heroKep !== null): ?>
        <div class="header-kep" style="background-image:url('<?php echo htmlspecialchars($heroKep, ENT_QUOTES); ?>')"></div>
        <div class="header-fatyol"></div>
        <?php endif; ?>
        <div class="header-content">
            <a href="/"><img src="https://www.futas.net/images/futasnet-logo.gif"
                srcset="/images/futasnet-logo.gif 1x, /images/futasnet-logo.png 2x"
                alt="Futás.Net logó" class="logo" width="120" height="40" fetchpriority="high"></a>
            <h1><?php echo $oldalCim; ?></h1>
            <p>Mennyit ér az időd a korodhoz képest? Korfaktor, korosztályos százalék és csúcskori egyenérték a legfrissebb utcai táblázatból.</p>
            <div class="stat-bar">
                <span class="stat-chip"><b>22</b> táv</span>
                <span class="stat-chip"><b>5–100</b> év</span>
                <span class="stat-chip"><b>2025</b>-ös táblázat</span>
            </div>
        </div>
    </header>

    <!-- ═══════════ NAVIGÁCIÓ ═══════════ -->
    <nav class="nav-container">
        <div class="nav">
            <a href="/">Futás.Net</a>
            <a href="/kalkulator/">Kalkulátorok</a>
            <a href="/kalkulator/futas/">Futókalkulátorok</a>
            <a href="/kalkulator/futas/kor-szerinti-eredmeny.php" class="aktiv">Kor szerinti eredmény</a>
        </div>
    </nav>

    <!-- ═══════════ FŐ TARTALOM ═══════════ -->
    <main class="main">
        <section class="content">

            <!-- ═══ TARTALOM KEZDETE ═══ -->

            <p class="morzsa"><a href="/">Futás.Net</a><span>›</span><a href="/kalkulator/">Kalkulátorok</a><span>›</span><a href="/kalkulator/futas/">Futás</a><span>›</span>Kor szerinti eredmény</p>

            <p class="sec-intro">Egy 55 éves futó 45 perces tízese nem ugyanaz a teljesítmény, mint egy 25 évesé. Az <strong>age grading</strong> (korosztályos értékelés) ezt teszi összehasonlíthatóvá: megmutatja, hogy az időd hány százaléka a korodra és nemedre számított csúcsteljesítménynek, és mennyinek felelne meg csúcskorban. Add meg az adataidat, az eredmény gépelés közben frissül.</p>

            <!-- ─── BEVITEL ─── -->
            <form class="form-card" id="kseUrlap" autocomplete="off" onsubmit="return false;">
                <h2>A futásod adatai</h2>

                <div class="field-group">
                    <span class="field-label">Nem</span>
                    <div class="unit-row" id="kseNem" role="group" aria-label="Nem">
                        <button type="button" class="unit-btn on" data-nem="M" aria-pressed="true">♂ Férfi</button>
                        <button type="button" class="unit-btn" data-nem="F" aria-pressed="false">♀ Nő</button>
                    </div>
                </div>

                <div class="field-group">
                    <span class="field-label">Életkor</span>
                    <div class="tab-row" id="kseKorMod" role="group" aria-label="Életkor megadása">
                        <button type="button" class="tab on" data-mod="kor" aria-pressed="true">Életkor években</button>
                        <button type="button" class="tab" data-mod="datum" aria-pressed="false">Születési dátumból</button>
                    </div>

                    <div id="kseKorBlokk">
                        <div class="range-row">
                            <input type="range" id="kseKorCsuszka" min="5" max="100" step="1" value="45" aria-label="Életkor csúszka">
                            <div class="field-wrap">
                                <input type="number" id="kseKor" min="5" max="100" step="1" value="45" inputmode="numeric" aria-label="Életkor">
                            </div>
                            <span class="egyseg">év</span>
                        </div>
                        <p class="kse-sugo">A verseny napján betöltött életkor számít.</p>
                    </div>

                    <div id="kseDatumBlokk" hidden>
                        <div class="row-inline">
                            <div class="field-group">
                                <label class="field-label" for="kseSzul">Születési dátum</label>
                                <div class="field-wrap"><span class="field-ic">🎂</span><input type="date" id="kseSzul" max="<?php echo $ma; ?>"></div>
                            </div>
                            <div class="field-group">
                                <label class="field-label" for="kseVerseny">A verseny dátuma</label>
                                <div class="field-wrap"><span class="field-ic">🏁</span><input type="date" id="kseVerseny" value="<?php echo $ma; ?>"></div>
                            </div>
                        </div>
                        <p class="kse-sugo" id="kseDatumSugo">Add meg a születési dátumodat.</p>
                    </div>
                </div>

                <div class="field-group">
                    <span class="field-label">Táv</span>
                    <div class="chip-wrap">
                        <p class="chip-wrap-cim">Népszerű távok</p>
                        <div class="chip-row" id="kseTavNepszeru"></div>
                    </div>
                    <div class="chip-wrap">
                        <p class="chip-wrap-cim">Minden hivatalos táv</p>
                        <div class="chip-row" id="kseTavOsszes"></div>
                    </div>
                    <div class="chip-wrap">
                        <div class="chip-row">
                            <button type="button" class="chip" data-tav="egyeni"><span class="ic">✏️</span> Egyéni táv</button>
                        </div>
                    </div>
                    <div id="kseEgyeniBlokk" hidden>
                        <div class="range-row">
                            <div class="field-wrap">
                                <span class="field-ic">📏</span>
                                <input type="text" id="kseEgyeniKm" inputmode="decimal" placeholder="pl. 7,5" aria-label="Egyéni táv kilométerben">
                            </div>
                            <span class="egyseg">km</span>
                        </div>
                        <p class="kse-sugo" id="kseEgyeniSugo">1,609 és 200 km között. Köztes távnál becsült értékkel számolunk.</p>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="kseIdo">Befutási idő</label>
                    <div class="field-wrap">
                        <span class="field-ic">⏱️</span>
                        <input type="text" id="kseIdo" inputmode="numeric" placeholder="ó:pp:mm, pl. 1:52:30 vagy 48:15" aria-describedby="kseIdoSugo">
                    </div>
                    <p class="kse-sugo" id="kseIdoSugo">Formátum: <code>ó:pp:mm</code> vagy <code>pp:mm</code>. Egyetlen szám percet jelent (45 → 45:00).</p>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn btn-vonal btn-sm" id="kseLink">🔗 Link az eredményhez</button>
                    <button type="button" class="btn btn-halvany btn-sm" id="kseAlaphelyzet">↺ Alaphelyzet</button>
                </div>
            </form>

            <!-- ─── EREDMÉNY ─── -->
            <div class="loading-wrap" id="kseToltes"><div class="spinner"></div></div>
            <div class="error-box" id="kseHiba" hidden></div>
            <div id="kseUres" class="empty-box">Add meg a befutási idődet, és itt azonnal megjelenik a korosztályos értékelés.</div>

            <div id="kseReszletek" hidden aria-live="polite">

                <h2 id="kseEredmenyCim">Az eredményed</h2>
                <div id="kseFigyelmeztetes"></div>

                <div class="result-grid">
                    <div class="result-card">
                        <div class="result-label">Korosztályos teljesítmény</div>
                        <div class="result-value" id="kseSzazalek">–</div>
                        <div class="result-formula" id="kseSzint"></div>
                    </div>
                    <div class="result-card">
                        <div class="result-label">Korfaktor</div>
                        <div class="result-value" id="kseFaktor">–</div>
                        <div class="result-formula" id="kseFaktorMagy"></div>
                    </div>
                    <div class="result-card">
                        <div class="result-label">Csúcskori egyenérték (korrigált idő)</div>
                        <div class="result-value" id="kseKorrigalt">–</div>
                        <div class="result-formula">ennyit futnál 19–29 évesen, azonos szinten</div>
                    </div>
                    <div class="result-card">
                        <div class="result-label">100%-os idő a korodban</div>
                        <div class="result-value" id="kseKorAlap">–</div>
                        <div class="result-formula" id="kseKorAlapMagy"></div>
                    </div>
                </div>

                <div class="kse-skala" aria-hidden="true">
                    <div class="kse-skala-jelolo" id="kseJelolo" style="left:3%"><span class="ertek" id="kseJeloloErtek">–</span><span class="tu"></span></div>
                    <div class="kse-skala-sav"><span></span><span></span><span></span><span></span><span></span></div>
                    <div class="kse-skala-cimkek">
                        <span>40%<i> · hobbi</i></span><span>60%<i> · helyi</i></span><span>70%<i> · regionális</i></span><span>80%<i> · nemzeti</i></span><span>90%<i> · világklasszis</i></span>
                    </div>
                </div>

                <div class="fact-strip">
                    <div class="fact-mini"><b id="kseTempo">–</b> tempó</div>
                    <div class="fact-mini"><b id="kseSebesseg">–</b> sebesség</div>
                    <div class="fact-mini"><b id="kseNyilt">–</b> nyílt alapidő</div>
                    <div class="fact-mini"><b id="kseKorKiir">–</b> életkor a versenyen</div>
                    <div class="fact-mini kiemelt"><b id="kseKovetkezo">–</b> <span id="kseKovetkezoCimke">a következő szintig</span></div>
                </div>

                <div class="formula-box" id="kseKeplet"></div>

                <div class="row-inline">
                    <div class="field-group">
                        <label class="field-label" for="kseCimke">Címke az összevetéshez (nem kötelező)</label>
                        <div class="field-wrap"><span class="field-ic">🏷️</span><input type="text" id="kseCimke" maxlength="40" placeholder="pl. Anna, 2025 tavasz"></div>
                    </div>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn btn-fo" id="kseHozzaad">➕ Hozzáadás az összevetéshez</button>
                </div>

                <!-- Célidők -->
                <h2>Célidők a korodban</h2>
                <p class="muted" id="kseCelokLeiras"></p>
                <div class="row-inline">
                    <div class="field-group">
                        <label class="field-label" for="kseCelSzazalek">Saját célszázalék</label>
                        <div class="range-row">
                            <div class="field-wrap"><span class="field-ic">🎯</span><input type="text" id="kseCelSzazalek" inputmode="decimal" placeholder="pl. 72,5"></div>
                            <span class="egyseg">%</span>
                        </div>
                    </div>
                    <div class="field-group">
                        <span class="field-label">Ehhez szükséges idő</span>
                        <div class="result-value" id="kseCelIdo">–</div>
                    </div>
                </div>
                <div class="tabla-gorgeto">
                    <table class="data-table" id="kseCelok"></table>
                </div>

                <!-- Egyenértékű idők -->
                <h2>Egyenértékű idők minden távon</h2>
                <p class="muted">Ugyanazzal a korosztályos százalékkal ennyit futnál a többi hivatalos távon, a saját életkorodban. A „csúcskori egyenérték” oszlop azt mutatja, mennyinek felel meg ez az idő életkori korrekció nélkül.</p>
                <div class="tabla-gorgeto">
                    <table class="data-table" id="kseTavTabla"></table>
                </div>

                <!-- Életkor-görbe -->
                <div class="chart-box">
                    <p class="chart-title">Ugyanez a szint más életkorban</p>
                    <p class="chart-sub" id="kseGrafikonAl"></p>
                    <div class="chart-scroll" id="kseGrafikon"></div>
                    <div class="legend">
                        <span><i class="kse-jel"></i> a te szinted (azonos %)</span>
                        <span><i class="kse-jel szaz"></i> 100% – a korosztályos csúcs</span>
                    </div>
                    <p class="diagram-cap">A görbe azt mutatja, milyen idő kellene ugyanehhez a korosztályos százalékhoz az adott életkorban. A kiemelt pont a te eredményed.</p>
                </div>

                <h2>Életkoronkénti táblázat</h2>
                <p class="muted">Az utolsó oszlop azt mutatja, hány százalékot érne ugyanez az idő, ha más életkorban futnád.</p>
                <div class="tabla-gorgeto">
                    <table class="data-table" id="kseKorTabla"></table>
                </div>
            </div>

            <!-- Összevetés: csak a memóriában él, újratöltéskor törlődik -->
            <div id="kseOsszevetesBlokk" hidden>
                <h2>Összevetés</h2>
                <p class="muted">Különböző távok, életkorok vagy futók eredményei a korosztályos százalék szerint sorba rendezve. A lista csak az oldal újratöltéséig marad meg.</p>
                <div class="bar-compare" id="kseOsszevetesSav"></div>
                <div class="tabla-gorgeto">
                    <table class="data-table" id="kseOsszevetes"></table>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn btn-halvany btn-sm" id="kseOsszevetesTorles">🗑 Lista törlése</button>
                </div>
            </div>

            <!-- ═════════ MAGYARÁZAT ═════════ -->
            <h2 id="hogyan-mukodik">Hogyan működik a kalkulátor?</h2>
            <p class="sec-intro">Az életkorral a teljesítmény természetesen csökken, ráadásul nem egyenletesen: 35 év körül még alig, 60 fölött már évről évre érezhetően. Az age grading egy statisztikai alapon készült táblázatból minden életkorra, nemre és távra megmondja, mennyi volt a csúcsteljesítmény az adott korban, és ahhoz méri az idődet.</p>

            <div class="step-grid">
                <div class="step"><span class="n">1</span><h3>Életkor</h3><p>A verseny napján betöltött életkorod számít. Ha a születésnapod a verseny után van, még az előző életkorral számolunk.</p></div>
                <div class="step"><span class="n">2</span><h3>Korfaktor</h3><p>A táblázatból kikeressük a nemedhez, életkorodhoz és a távhoz tartozó faktort. Ez 0 és 1 közötti szám, 19–29 év között általában 1.</p></div>
                <div class="step"><span class="n">3</span><h3>Korosztályos alapidő</h3><p>A nyílt alapidőt (a kortól független csúcsidőt) elosztjuk a faktorral: ennyi a 100%-os idő a te korodban.</p></div>
                <div class="step"><span class="n">4</span><h3>Százalék és egyenérték</h3><p>A korosztályos alapidőt elosztjuk a te idődet, ez a százalék. Az idődet megszorozzuk a faktorral, ez a csúcskori egyenérték.</p></div>
            </div>

            <div class="formula-box">
                <p class="f-label">A képletek</p>
                <p><strong>korosztályos alapidő</strong> = nyílt alapidő ÷ korfaktor</p>
                <p><strong>korosztályos százalék</strong> = korosztályos alapidő ÷ a te időd × 100</p>
                <p><strong>korrigált (csúcskori) idő</strong> = a te időd × korfaktor</p>
                <p class="muted">A kettő ugyanazt a százalékot adja: nyílt alapidő ÷ korrigált idő × 100 = korosztályos alapidő ÷ a te időd × 100.</p>
            </div>

            <?php if ($pelda): ?>
            <div class="card">
                <h3>Kidolgozott példa: <?php echo $pelda['kor']; ?> éves férfi, 10 km, <?php echo $pelda['ido']; ?></h3>
                <dl class="spec-list">
                    <dt>Nyílt alapidő (férfi 10 km)</dt><dd><?php echo $pelda['oc']; ?> (<?php echo $pelda['ocMp']; ?> mp)</dd>
                    <dt>Korfaktor (<?php echo $pelda['kor']; ?> év, 10 km)</dt><dd><?php echo $pelda['fak']; ?></dd>
                    <dt>Korosztályos alapidő</dt><dd><?php echo $pelda['ocMp']; ?> ÷ <?php echo $pelda['fak']; ?> = <?php echo $pelda['alapMp']; ?> mp ≈ <?php echo $pelda['alap']; ?></dd>
                    <dt>Korosztályos százalék</dt><dd><?php echo $pelda['alapMp']; ?> ÷ 2700 × 100 = <strong><?php echo $pelda['szazalek']; ?>%</strong></dd>
                    <dt>Csúcskori egyenérték</dt><dd>2700 × <?php echo $pelda['fak']; ?> ≈ <strong><?php echo $pelda['korrigalt']; ?></strong></dd>
                </dl>
                <p class="muted">Vagyis ez a futó a korosztályában helyi, klubszintű teljesítményt nyújtott, és csúcskorban nagyjából <?php echo $pelda['korrigalt']; ?> alatt futotta volna le ugyanezt.</p>
            </div>
            <?php endif; ?>

            <h3>A fogalmak</h3>
            <div class="info-grid">
                <div class="info-card"><h4>Nyílt alapidő (open standard)</h4><p>A kortól független csúcsidő az adott távon, a táblázat 100%-os mércéje. Nem feltétlenül egyezik a hivatalos világcsúccsal: a legjobb eredményekre illesztett görbéből számolják, így minden távra egyforma szigorú.</p></div>
                <div class="info-card"><h4>Korfaktor (age factor)</h4><p>Megmutatja, hogy az adott életkorban a csúcsteljesítmény hányad része érhető el. 0,80 azt jelenti, hogy a korosztályos csúcsidő 1 ÷ 0,80 = 1,25-szöröse a nyílt alapidőnek.</p></div>
                <div class="info-card"><h4>Korosztályos alapidő</h4><p>A 100%-os idő a te korodban és nemedben – amit a korosztály legjobbjai el tudnak érni.</p></div>
                <div class="info-card"><h4>Korrigált idő</h4><p>Az időd „visszafiatalítva”: ennyit futnál csúcskorban, azonos korosztályos szinten. Két különböző korú futó korrigált idejét közvetlenül össze lehet hasonlítani.</p></div>
            </div>

            <h3>A szintek</h3>
            <p>A százalékhoz a nemzetközi gyakorlatban szokásos (eredetileg a WAVA-táblázatokhoz tartozó) besorolást használjuk. Ez irányadó, nem hivatalos minősítés.</p>
            <div class="tabla-gorgeto">
                <table class="data-table">
                    <thead><tr><th>Korosztályos százalék</th><th>Szint</th><th>Mit jelent?</th></tr></thead>
                    <tbody>
                        <tr><td class="num">100% felett</td><td><span class="badge ok">Világrekord-szint</span></td><td>A korosztály valaha mért legjobbjainak szintje vagy afölött.</td></tr>
                        <tr><td class="num">90–100%</td><td><span class="badge ok">Világklasszis</span></td><td>Világbajnoki érmes, a korosztály világelitje.</td></tr>
                        <tr><td class="num">80–90%</td><td><span class="badge ok">Nemzeti élmezőny</span></td><td>Országos bajnoki szint, nemzetközi versenyeken is helyezés.</td></tr>
                        <tr><td class="num">70–80%</td><td><span class="badge">Regionális szint</span></td><td>Nagyobb versenyeken korosztályos dobogó.</td></tr>
                        <tr><td class="num">60–70%</td><td><span class="badge">Helyi szint</span></td><td>Jól edzett, rendszeresen versenyző amatőr.</td></tr>
                        <tr><td class="num">60% alatt</td><td><span class="badge tomor">Hobbifutó szint</span></td><td>A legtöbb szabadidős futó itt van – és ebben semmi rossz nincs.</td></tr>
                    </tbody>
                </table>
            </div>

            <h3>Nyílt alapidők a 2025-ös táblázatban</h3>
            <p class="muted">A táblázat mind a 22 távra külön férfi és női alapidőt ad meg. Ezek a számítás 100%-os mércéi.</p>
            <div class="tabla-gorgeto">
                <table class="data-table">
                    <thead><tr><th>Táv</th><th class="num">km</th><th class="num">Férfi</th><th class="num">Nő</th></tr></thead>
                    <tbody>
                    <?php if ($adat) foreach ($adat['tavok'] as $tav): ?>
                        <tr<?php echo in_array($tav['k'], ['5k', '10k', 'hm', 'mar'], true) ? ' class="kiemelt"' : ''; ?>>
                            <td><?php echo htmlspecialchars($tav['nev']); ?></td>
                            <td class="num"><?php echo szamHu($tav['km'], 3); ?></td>
                            <td class="num"><?php echo idoFormaz($tav['oc']['M']); ?></td>
                            <td class="num"><?php echo idoFormaz($tav['oc']['F']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3>Honnan jönnek az adatok?</h3>
            <p>A kalkulátor Alan Jones és Tom Bernhard <strong>2025-ös utcai korosztályos táblázatával</strong> számol (Road Running Age Standards 2025, a fájlokban jelölt változat: 2025-07-27). Tom Bernhard minden életkorra összegyűjtötte a legjobb utcai eredményeket, Alan Jones ezekre illesztett görbéket. A táblázatot az USATF Masters Long Distance Running Council 2025. január 10-én hagyta jóvá. Ez a WMA (World Masters Athletics, korábban WAVA) age grading rendszerének utcai ága: a korábbi, 2010-es és 2015-ös kiadásokat a WMA is elfogadta. Az adatokat összevetettük Howard Grubb USATF MLDR 2025-ös kalkulátorának adataival is: mind a 4224 korfaktor és mind a 44 nyílt alapidő egyezik.</p>

            <ul class="timeline">
                <li><span class="tl-ido">1989</span><span class="tl-info">Az első WAVA-táblázatok (National Masters News) – 30 év felett, pálya és utca együtt.</span></li>
                <li><span class="tl-ido">1994</span><span class="tl-info">Frissítés, kiterjesztés a 8–19 éves korosztályra.</span></li>
                <li><span class="tl-ido">2004/2006</span><span class="tl-info">Alan Jones és Rex Harvey új, görbeillesztéses módszere (férfi 2004, női 2006) – WMA 2002 néven.</span></li>
                <li><span class="tl-ido">2010</span><span class="tl-info">A női hosszútávú faktorok szigorítása, WMA- és USATF-jóváhagyással.</span></li>
                <li><span class="tl-ido">2015</span><span class="tl-info">Frissítés Kimetto 2:02:57-es maratoni világcsúcsa után, WMA- és USATF-jóváhagyással.</span></li>
                <li><span class="tl-ido">2020</span><span class="tl-info">Új korosztályos csúcsok Tom Bernhard adatgyűjtéséből, USATF MLDR-jóváhagyás 2020. május 20-án.</span></li>
                <li class="vege"><span class="tl-ido">2025</span><span class="tl-info">A jelenleg érvényes kiadás: új interpolációs módszer a köztes távokra, jóváhagyás 2025. január 10-én. <strong>Ezzel számol a kalkulátor.</strong></span></li>
            </ul>

            <div class="lead-note">
                <p><strong>Hogyan készülnek a köztes távok?</strong> A 2025-ös táblázatban csak az 5 km, a 10 km, a félmaraton és a maraton faktorait illesztették közvetlenül az adatokra. A köztük lévő távok faktora a távolság logaritmusa szerinti lineáris interpolációval készül. 6 km-re például: u = (ln 6 − ln 5) ÷ (ln 10 − ln 5) ≈ 0,263, és F<sub>6</sub> = F<sub>5</sub> × (1 − u) + F<sub>10</sub> × u. A maratonnál hosszabb távokon a faktor megegyezik a maratonival. Az <em>egyéni táv</em> funkció ugyanezt a módszert alkalmazza a két legközelebbi hivatalos táv között, a nyílt alapidőt pedig log-log interpolációval becsli.</p>
            </div>

            <h3>Mire figyelj?</h3>
            <ul class="tips">
                <li><strong>Csak utcai futásra.</strong> Stadionos pályaszámokhoz (5000 m, 10 000 m) a WMA pályás faktorai valók; terep-, trail- és hegyi futáshoz egyik táblázat sem alkalmas.</li>
                <li><strong>Hitelesített pálya.</strong> A rövidebb, lejtős (például Boston) vagy erős hátszeles pálya felfelé torzít.</li>
                <li><strong>Életkor a verseny napján.</strong> Nem az évfolyam számít, hanem a betöltött életkor – egy születésnap is néhány tized százalékot jelenthet.</li>
                <li><strong>A táblázat nem jóslat.</strong> Az életkor-görbe azt mutatja, mi felelne meg ugyanannak a szintnek, nem azt, hogy te mennyit fogsz futni.</li>
                <li><strong>Gyerekek és fiatalok.</strong> A 19 év alattiak faktorai kevesebb adaton alapulnak, ezért csak tájékoztató jellegűek.</li>
            </ul>

            <h2>Gyakori kérdések</h2>
            <div class="faq">
                <?php foreach ($gyik as $k): ?>
                <details>
                    <summary><?php echo htmlspecialchars($k[0]); ?></summary>
                    <p><?php echo htmlspecialchars($k[1]); ?></p>
                </details>
                <?php endforeach; ?>
            </div>

            <h3>Források</h3>
            <ul>
                <li>Alan Jones – Tom Bernhard: <em>Road Running Age Standards 2025</em> (MaleRoadStd2025.xlsx, FemaleRoadStd2025.xlsx), <a href="https://github.com/AlanLyttonJones/Age-Grade-Tables" rel="noopener">github.com/AlanLyttonJones/Age-Grade-Tables</a></li>
                <li>Alan Jones: <em>Age-Grade Tables – history</em> (2025-01-13) és <em>Creating Road Running Age Tables</em></li>
                <li>Howard Grubb: <em>USATF MLDR Road age-grading calculator 2025</em>, <a href="https://howardgrubb.co.uk/athletics/mldrroad25.html" rel="noopener">howardgrubb.co.uk</a> – az adatok ellenőrzéséhez</li>
                <li>World Masters Athletics: <em>WMA Age Factors and Parameters for Scoring Combined Events and One Year Age Factors</em>, 2023 Edition, <a href="https://world-masters-athletics.org/" rel="noopener">world-masters-athletics.org</a></li>
                <li>Basile Grammaticos: <em>Scoring athletic performances for age groups</em>, New Studies in Athletics 24:3 (2009)</li>
            </ul>

            <!-- ═══ TARTALOM VÉGE ═══ -->

        </section>
    </main>

    <!-- ═══════════ ALSÓ BLOKK ═══════════ -->
    <section class="bottom-section">
        <h3>Ez is érdekelhet</h3>
        <ul>
            <li><a href="/kalkulator/futas/">Futókalkulátorok</a></li>
            <li><a href="/kalkulator/">Minden kalkulátor</a></li>
        </ul>
        <div class="card mt-1">
            <p><a href="/">Futás.Net</a></p>
            <p><a href="/"><img src="https://www.futas.net/images/futasnet-logo.gif"
                srcset="/images/futasnet-logo.gif 1x, /images/futasnet-logo.png 2x"
                alt="Futás.Net logó" class="logo" width="120" height="40" loading="lazy"></a></p>
        </div>
        <div class="ad-container">
            <?php include($_SERVER['DOCUMENT_ROOT'] . "/admin/adsense/ads-responsive.php"); ?>
        </div>
    </section>

    <!-- ═══════════ LÁBLÉC ═══════════ -->
    <footer class="footer">
        <div id="credits">
            <p>© Futás.Net &ndash; <a href="/" title="Maraton futás Budapesten">Futás</a>.Net &middot;
               <a href="/budapest/" title="Budapest">Budapest</a></p>
        </div>
    </footer>
</div>

<script src="/css/resp/mchs-ui.js" defer></script>
<script>
/* ===== OLDAL-SPECIFIKUS JS ==============================================
   Korosztályos kalkulátor. A táblázat nincs a lapban: a faktorokat és az
   alapidőket a kor-szerinti-eredmeny-api.php adja, mindig csak az aktuális
   nem–életkor–táv hármashoz tartozó szeletet. A futott idő nem megy el a
   szerverre, a százalékot és a többi eredményt itt számoljuk.

   Az állapot az URL-ben él, így az eredmény linkkel megosztható; tárolót
   (localStorage) nem használunk. A választógombokat (nem, életkor-mód,
   táv) itt kezeljük, nem a mchs-ui.js data-group mechanizmusával, mert a
   távchipek két külön sorban ülnek, mégis egyetlen választást alkotnak.
======================================================================== */
(function () {
    'use strict';

    const API = 'kor-szerinti-eredmeny-api.php';
    const TAVOK = <?php echo json_encode($tavLista, JSON_UNESCAPED_UNICODE); ?>;
    const KOR_MIN = <?php echo (int) $adat['korMin']; ?>;
    const KOR_MAX = <?php echo (int) $adat['korMax']; ?>;
    const KM_MIN = TAVOK[0].km;
    const KM_MAX = TAVOK[TAVOK.length - 1].km;
    const NEPSZERU = ['5k', '10k', 'hm', 'mar'];
    const MA = '<?php echo $ma; ?>';

    const SZINTEK = [
        { min: 100, nev: 'Világrekord-szint', badge: 'badge ok' },
        { min: 90,  nev: 'Világklasszis',     badge: 'badge ok' },
        { min: 80,  nev: 'Nemzeti élmezőny',  badge: 'badge ok' },
        { min: 70,  nev: 'Regionális szint',  badge: 'badge' },
        { min: 60,  nev: 'Helyi szint',       badge: 'badge' },
        { min: 0,   nev: 'Hobbifutó szint',   badge: 'badge tomor' }
    ];

    const allapot = {
        nem: 'M',
        korMod: 'kor',
        tav: '10k',          // TAVOK[].k vagy 'egyeni'
        osszevetes: []
    };

    const $ = id => document.getElementById(id);

    /* ---------- Formázók ---------- */

    function ketJegy(n) { return (n < 10 ? '0' : '') + n; }

    function idoFormaz(mp) {
        mp = Math.round(mp);
        const o = Math.floor(mp / 3600);
        const p = Math.floor((mp % 3600) / 60);
        const s = mp % 60;
        return o > 0 ? o + ':' + ketJegy(p) + ':' + ketJegy(s) : p + ':' + ketJegy(s);
    }

    function szam(x, tized) {
        return x.toFixed(tized).replace('.', ',');
    }

    function tempo(mp, km) {
        return idoFormaz(mp / km) + ' /km';
    }

    function html(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function tavNev(k) {
        const t = TAVOK.find(x => x.k === k);
        return t ? t.nev : k;
    }

    /* ---------- Beolvasók ---------- */

    /* „1:52:30”, „48:15”, „48.15”, „45” (perc) → másodperc; hibás → NaN; üres → null */
    function idoBeolvas(szoveg) {
        const s = szoveg.trim().replace(/[.,;\s]+/g, ':');
        if (s === '') return null;
        if (!/^\d+(:\d+){0,2}$/.test(s)) return NaN;
        const r = s.split(':').map(Number);
        let mp;
        if (r.length === 1) {
            mp = r[0] * 60;
        } else if (r.length === 2) {
            if (r[1] > 59) return NaN;
            mp = r[0] * 60 + r[1];
        } else {
            if (r[1] > 59 || r[2] > 59) return NaN;
            mp = r[0] * 3600 + r[1] * 60 + r[2];
        }
        return mp > 0 ? mp : NaN;
    }

    function tizedesBeolvas(szoveg) {
        const s = szoveg.trim().replace(',', '.');
        if (s === '') return null;
        return /^\d+(\.\d+)?$/.test(s) ? Number(s) : NaN;
    }

    /* Betöltött életkor a verseny napján (ÉÉÉÉ-HH-NN szövegekből, időzóna nélkül) */
    function korDatumbol(szul, datum) {
        if (!szul || !datum) return null;
        const a = szul.split('-').map(Number);
        const b = datum.split('-').map(Number);
        if (a.length !== 3 || b.length !== 3 || a.some(isNaN) || b.some(isNaN)) return null;
        let kor = b[0] - a[0];
        if (b[1] < a[1] || (b[1] === a[1] && b[2] < a[2])) kor--;
        return kor;
    }

    /* ---------- Szerveroldali adatszelet ---------- */

    const gyorsTar = new Map();   // kérés kulcsa → Promise; csak a lap élettartamáig

    function szeletLeker(nem, kor, tavParam) {
        const kulcs = nem + '|' + kor + '|' + tavParam;
        if (!gyorsTar.has(kulcs)) {
            const cim = API + '?nem=' + nem + '&kor=' + kor + '&' + tavParam;
            const igeret = fetch(cim, { headers: { 'X-KSE': '1' }, credentials: 'same-origin' })
                .then(v => v.json().catch(() => ({})).then(j => {
                    if (!v.ok || !j.ok) throw new Error(j.hiba || 'A szerver nem válaszolt (' + v.status + ').');
                    return j;
                }));
            igeret.catch(() => gyorsTar.delete(kulcs));   // hibát nem tárolunk
            gyorsTar.set(kulcs, igeret);
        }
        return gyorsTar.get(kulcs);
    }

    function szintKeres(szazalek) {
        return SZINTEK.find(sz => szazalek >= sz.min);
    }

    /* ---------- Bevitel olvasása ---------- */

    function aktualisKor() {
        if (allapot.korMod === 'kor') {
            const k = Number($('kseKor').value);
            if (!Number.isInteger(k) || k < KOR_MIN || k > KOR_MAX) {
                return { hiba: 'Az életkor ' + KOR_MIN + ' és ' + KOR_MAX + ' év között lehet.' };
            }
            return { kor: k };
        }
        const k = korDatumbol($('kseSzul').value, $('kseVerseny').value);
        const sugo = $('kseDatumSugo');
        if (k === null) {
            sugo.textContent = 'Add meg a születési dátumodat és a verseny napját.';
            sugo.classList.remove('hiba');
            return { hiba: '' };
        }
        if (k < KOR_MIN || k > KOR_MAX) {
            sugo.textContent = 'A verseny napján ' + k + ' éves voltál – a táblázat ' + KOR_MIN + ' és ' + KOR_MAX + ' év között érvényes.';
            sugo.classList.add('hiba');
            return { hiba: sugo.textContent };
        }
        sugo.textContent = 'A verseny napján ' + k + ' éves voltál, ezzel az életkorral számolunk.';
        sugo.classList.remove('hiba');
        return { kor: k };
    }

    /* A táv API-paramétere ('tav=10k' vagy 'km=7.5'), érvénytelen egyéni távnál null */
    function aktualisTavParam() {
        if (allapot.tav !== 'egyeni') return 'tav=' + allapot.tav;
        const km = tizedesBeolvas($('kseEgyeniKm').value);
        const sugo = $('kseEgyeniSugo');
        if (km === null) {
            sugo.textContent = 'Add meg a távot kilométerben (1,609 és 200 km között).';
            sugo.classList.remove('hiba');
            return null;
        }
        if (isNaN(km) || km < KM_MIN - 0.0005 || km > KM_MAX + 0.0005) {
            sugo.textContent = 'A táv 1,609 km (1 mérföld) és 200 km között lehet.';
            sugo.classList.add('hiba');
            return null;
        }
        sugo.classList.remove('hiba');
        return 'km=' + km;
    }

    /* ---------- Fő számítás ---------- */

    let utolso = null;     // az utolsó érvényes eredmény (összevetéshez, célszázalékhoz)
    let keresSzam = 0;     // csak a legutóbbi kérés válaszát rajzoljuk ki

    function uresAllapot(szoveg) {
        utolso = null;
        $('kseReszletek').hidden = true;
        $('kseToltes').classList.remove('lathato');
        $('kseUres').hidden = false;
        $('kseUres').textContent = szoveg;
    }

    function szamol() {
        const korAdat = aktualisKor();
        const tavParam = aktualisTavParam();
        const ido = idoBeolvas($('kseIdo').value);

        const idoSugo = $('kseIdoSugo');
        if (Number.isNaN(ido)) {
            idoSugo.textContent = 'Ezt nem tudom időként értelmezni. Példák: 1:52:30, 48:15, 45.';
            idoSugo.classList.add('hiba');
        } else if (ido) {
            idoSugo.textContent = 'Beolvasva: ' + idoFormaz(ido);
            idoSugo.classList.remove('hiba');
        } else {
            idoSugo.innerHTML = 'Formátum: <code>ó:pp:mm</code> vagy <code>pp:mm</code>. Egyetlen szám percet jelent (45 → 45:00).';
            idoSugo.classList.remove('hiba');
        }

        urlFrissit();

        if (korAdat.kor === undefined || !tavParam || !ido) {
            keresSzam++;
            uresAllapot(korAdat.hiba
                ? korAdat.hiba
                : 'Add meg a befutási idődet, és itt azonnal megjelenik a korosztályos értékelés.');
            return;
        }

        const sajatSzam = ++keresSzam;
        const nem = allapot.nem;
        const kor = korAdat.kor;
        const idozito = setTimeout(() => $('kseToltes').classList.add('lathato'), 150);

        szeletLeker(nem, kor, tavParam).then(szelet => {
            clearTimeout(idozito);
            if (sajatSzam !== keresSzam) return;
            $('kseToltes').classList.remove('lathato');
            $('kseHiba').hidden = true;
            kirajzol(szelet, ido);
        }).catch(hiba => {
            clearTimeout(idozito);
            if (sajatSzam !== keresSzam) return;
            uresAllapot('Az eredményt most nem sikerült kiszámolni.');
            $('kseHiba').hidden = false;
            $('kseHiba').textContent = hiba.message;
        });
    }

    function kirajzol(szelet, ido) {
        const nem = szelet.nem;
        const kor = szelet.kor;
        const tav = szelet.tav;
        const oc = tav.oc;
        const fak = tav.fak;
        const korAlap = oc / fak;
        const szazalek = korAlap / ido * 100;
        const korrigalt = ido * fak;
        const szint = szintKeres(szazalek);

        utolso = { nem, kor, tav, ido, oc, fak, korAlap, szazalek, korrigalt, szint, szelet };

        if (tav.becsult) {
            $('kseEgyeniSugo').textContent = 'Becsült érték a(z) ' + tav.also + ' és a(z) ' + tav.felso + ' adataiból, logaritmikus interpolációval.';
        } else if (allapot.tav === 'egyeni') {
            $('kseEgyeniSugo').textContent = 'Ez hivatalos táv (' + tav.nev + '), a táblázat pontos értékével számolunk.';
        }
        $('kseIdoSugo').textContent = 'Beolvasva: ' + idoFormaz(ido) + ' · tempó ' + tempo(ido, tav.km);

        $('kseUres').hidden = true;
        $('kseReszletek').hidden = false;

        const nemSzo = nem === 'M' ? 'férfi' : 'nő';
        $('kseEredmenyCim').textContent = 'Az eredményed: ' + kor + ' éves ' + nemSzo + ', ' + tav.nev + ', ' + idoFormaz(ido);

        /* Figyelmeztetések */
        const figy = [];
        if (tav.becsult) {
            figy.push('<div class="lead-note warn"><p><strong>Becsült érték.</strong> A(z) ' + html(tav.nev) + ' nem hivatalos táv, ezért a korfaktort és a nyílt alapidőt a két szomszédos hivatalos táv (' + html(tav.also) + ', ' + html(tav.felso) + ') adataiból interpoláltuk.</p></div>');
        }
        if (szazalek > 100) {
            figy.push('<div class="lead-note bad"><p><strong>100% feletti eredmény.</strong> Ez korosztályos világcsúcs-szint. Ellenőrizd az időt, a távot és az életkort – vagy gratulálunk!</p></div>');
        }
        if (kor < 19) {
            figy.push('<div class="lead-note"><p>A 19 év alattiak faktorai kevesebb adaton alapulnak, ezért az eredmény tájékoztató jellegű.</p></div>');
        }
        $('kseFigyelmeztetes').innerHTML = figy.join('');

        /* Eredménykártyák */
        $('kseSzazalek').innerHTML = szam(szazalek, 2) + '<span class="result-unit">%</span>';
        $('kseSzint').innerHTML = '<span class="' + szint.badge + '">' + szint.nev + '</span>';
        $('kseFaktor').textContent = szam(fak, 4);
        $('kseFaktorMagy').textContent = fak >= 0.9999
            ? 'csúcskor: nincs korrekció'
            : 'a csúcsidő ' + szam((1 / fak - 1) * 100, 1) + '%-kal hosszabb a korodban';
        $('kseKorrigalt').textContent = idoFormaz(korrigalt);
        $('kseKorAlap').textContent = idoFormaz(korAlap);
        $('kseKorAlapMagy').textContent = 'a ' + kor + ' éves ' + (nem === 'M' ? 'férfiak' : 'nők') + ' korosztályos csúcsa';

        /* Skála: 40% → 0, 100% → 100; a jelölő a sáv szélein belül marad */
        const pozicio = Math.max(0, Math.min(100, (szazalek - 40) / 60 * 100));
        $('kseJelolo').style.left = Math.max(3, Math.min(97, pozicio)) + '%';
        $('kseJeloloErtek').textContent = szam(szazalek, 1) + '%';

        /* Adatcsík */
        $('kseTempo').textContent = tempo(ido, tav.km);
        $('kseSebesseg').textContent = szam(tav.km / (ido / 3600), 2) + ' km/h';
        $('kseNyilt').textContent = idoFormaz(oc);
        $('kseKorKiir').textContent = kor + ' év';
        const kovetkezo = [60, 70, 80, 90, 100].find(h => h > szazalek);
        if (kovetkezo) {
            const kellIdo = korAlap / (kovetkezo / 100);
            $('kseKovetkezo').textContent = '−' + idoFormaz(ido - kellIdo);
            $('kseKovetkezoCimke').textContent = 'a ' + kovetkezo + '%-hoz (' + idoFormaz(kellIdo) + ')';
        } else {
            $('kseKovetkezo').textContent = '🏆';
            $('kseKovetkezoCimke').textContent = 'a legfelső szinten vagy';
        }

        /* Képlet a tényleges számokkal */
        $('kseKeplet').innerHTML =
            '<p class="f-label">A számítás a te adataiddal</p>' +
            '<p>korosztályos alapidő = ' + szam(oc, 0) + ' mp ÷ ' + szam(fak, 4) + ' = <strong>' + szam(korAlap, 1) + ' mp</strong> (' + idoFormaz(korAlap) + ')</p>' +
            '<p>korosztályos százalék = ' + szam(korAlap, 1) + ' ÷ ' + ido + ' × 100 = <strong>' + szam(szazalek, 2) + '%</strong></p>' +
            '<p>korrigált idő = ' + ido + ' mp × ' + szam(fak, 4) + ' = <strong>' + szam(korrigalt, 1) + ' mp</strong> (' + idoFormaz(korrigalt) + ')</p>';

        celTablaRajzol();
        celSzazalekSzamol();
        tavTablaRajzol();
        korTablaRajzol();
        grafikonRajzol();
    }

    /* ---------- Célidők ---------- */

    function celTablaRajzol() {
        const u = utolso;
        $('kseCelokLeiras').textContent = 'Ennyi idő kell az egyes szintekhez ' + u.kor + ' évesen, ' + u.tav.nev + ' távon (' + (u.nem === 'M' ? 'férfi' : 'nő') + ').';
        let sor = '<thead><tr><th class="num">Szint</th><th class="num">Idő</th><th class="num">Tempó</th><th class="num">Különbség a te idődhöz</th></tr></thead><tbody>';
        [100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50, 45, 40].forEach(h => {
            const t = u.korAlap / (h / 100);
            const kul = t - u.ido;
            const kiemelt = u.szazalek >= h && u.szazalek < h + 5;
            sor += '<tr' + (kiemelt ? ' class="kiemelt"' : '') + '>' +
                '<td class="num">' + h + '%</td>' +
                '<td class="num">' + idoFormaz(t) + '</td>' +
                '<td class="num">' + tempo(t, u.tav.km) + '</td>' +
                '<td class="num">' + (Math.abs(kul) < 0.5 ? '0:00' : (kul < 0 ? '−' : '+') + idoFormaz(Math.abs(kul))) + '</td></tr>';
        });
        $('kseCelok').innerHTML = sor + '</tbody>';
    }

    function celSzazalekSzamol() {
        const cel = tizedesBeolvas($('kseCelSzazalek').value);
        if (!utolso || cel === null || isNaN(cel) || cel <= 0 || cel > 120) {
            $('kseCelIdo').textContent = '–';
            return;
        }
        const t = utolso.korAlap / (cel / 100);
        $('kseCelIdo').textContent = idoFormaz(t) + ' (' + tempo(t, utolso.tav.km) + ')';
    }

    /* ---------- Egyenértékű idők minden távon ---------- */

    function tavTablaRajzol() {
        const u = utolso;
        let sor = '<thead><tr><th>Táv</th><th class="num">Egyenértékű idő</th><th class="num">Tempó</th><th class="num">Korfaktor</th><th class="num">Csúcskori egyenérték</th></tr></thead><tbody>';
        u.szelet.tavok.forEach((t, i) => {
            const ido = t.oc / t.fak / (u.szazalek / 100);
            const kiemelt = !u.tav.becsult && u.tav.k === t.k;
            sor += '<tr' + (kiemelt ? ' class="kiemelt"' : '') + '>' +
                '<td>' + tavNev(t.k) + '</td>' +
                '<td class="num">' + idoFormaz(ido) + '</td>' +
                '<td class="num">' + tempo(ido, TAVOK[i].km) + '</td>' +
                '<td class="num">' + szam(t.fak, 4) + '</td>' +
                '<td class="num">' + idoFormaz(ido * t.fak) + '</td></tr>';
        });
        $('kseTavTabla').innerHTML = sor + '</tbody>';
    }

    /* ---------- Életkoronkénti táblázat ---------- */

    function korTablaRajzol() {
        const u = utolso;
        let sor = '<thead><tr><th class="num">Életkor</th><th class="num">Korfaktor</th><th class="num">100%-os idő</th><th class="num">A te szinted (' + szam(u.szazalek, 1) + '%)</th><th class="num">A te időd ennyit érne</th></tr></thead><tbody>';
        u.szelet.korok.forEach(r => {
            if (r.kor % 5 !== 0 && r.kor !== u.kor) return;   // a görbe szélső pontjai
            const alap = r.oc / r.fak;
            sor += '<tr' + (r.kor === u.kor ? ' class="kiemelt"' : '') + '>' +
                '<td class="num">' + r.kor + ' év</td>' +
                '<td class="num">' + szam(r.fak, 4) + '</td>' +
                '<td class="num">' + idoFormaz(alap) + '</td>' +
                '<td class="num">' + idoFormaz(alap / (u.szazalek / 100)) + '</td>' +
                '<td class="num">' + szam(alap / u.ido * 100, 1) + '%</td></tr>';
        });
        $('kseKorTabla').innerHTML = sor + '</tbody>';
    }

    /* ---------- Életkor-görbe (SVG) ---------- */

    function lepesKeres(tartomany) {
        const lepesek = [5, 10, 15, 20, 30, 60, 120, 180, 300, 600, 900, 1200, 1800, 3600, 5400, 7200, 10800, 14400, 21600, 43200];
        return lepesek.find(l => tartomany / l <= 6) || 86400;
    }

    /* Sima görbe a pontokon át (Catmull–Rom → köbös Bézier) */
    function simaUtvonal(p) {
        let d = 'M' + p[0][0].toFixed(1) + ' ' + p[0][1].toFixed(1);
        for (let i = 0; i < p.length - 1; i++) {
            const p0 = p[i - 1] || p[i], p1 = p[i], p2 = p[i + 1], p3 = p[i + 2] || p2;
            const c1x = p1[0] + (p2[0] - p0[0]) / 6, c1y = p1[1] + (p2[1] - p0[1]) / 6;
            const c2x = p2[0] - (p3[0] - p1[0]) / 6, c2y = p2[1] - (p3[1] - p1[1]) / 6;
            d += ' C' + c1x.toFixed(1) + ' ' + c1y.toFixed(1) + ' ' + c2x.toFixed(1) + ' ' + c2y.toFixed(1) + ' ' + p2[0].toFixed(1) + ' ' + p2[1].toFixed(1);
        }
        return d;
    }

    function grafikonRajzol() {
        const u = utolso;
        const W = 760, H = 340, bal = 70, jobb = 20, fent = 20, lent = 44;
        const pontok = u.szelet.korok.map(r => {
            const alap = r.oc / r.fak;
            return { k: r.kor, szaz: alap, sajat: alap / (u.szazalek / 100) };
        });
        const korTol = pontok[0].k;
        const korIg = pontok[pontok.length - 1].k;

        let yMin = Math.min(...pontok.map(p => Math.min(p.szaz, p.sajat)));
        let yMax = Math.max(...pontok.map(p => Math.max(p.szaz, p.sajat)));
        const lepes = lepesKeres(yMax - yMin);
        yMin = Math.floor(yMin / lepes) * lepes;
        yMax = Math.ceil(yMax / lepes) * lepes;

        const x = k => bal + (k - korTol) / (korIg - korTol) * (W - bal - jobb);
        const y = t => fent + (1 - (t - yMin) / (yMax - yMin)) * (H - fent - lent);

        let svg = '<svg class="kse-grafikon" viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Az azonos korosztályos szinthez tartozó idő az életkor függvényében">';

        for (let t = yMin; t <= yMax + 0.1; t += lepes) {
            svg += '<line class="racs" x1="' + bal + '" x2="' + (W - jobb) + '" y1="' + y(t) + '" y2="' + y(t) + '"/>';
            svg += '<text x="' + (bal - 8) + '" y="' + (y(t) + 4) + '" text-anchor="end">' + idoFormaz(t) + '</text>';
        }
        const korLepes = korIg - korTol > 50 ? 10 : 5;
        for (let k = Math.ceil(korTol / korLepes) * korLepes; k <= korIg; k += korLepes) {
            svg += '<line class="racs" x1="' + x(k) + '" x2="' + x(k) + '" y1="' + fent + '" y2="' + (H - lent) + '"/>';
            svg += '<text x="' + x(k) + '" y="' + (H - lent + 18) + '" text-anchor="middle">' + k + '</text>';
        }
        svg += '<line class="tengely" x1="' + bal + '" x2="' + (W - jobb) + '" y1="' + (H - lent) + '" y2="' + (H - lent) + '"/>';
        svg += '<text x="' + ((W + bal) / 2) + '" y="' + (H - 6) + '" text-anchor="middle">életkor (év)</text>';

        svg += '<path class="gorbe-szaz" d="' + simaUtvonal(pontok.map(p => [x(p.k), y(p.szaz)])) + '"/>';
        svg += '<path class="gorbe-sajat" d="' + simaUtvonal(pontok.map(p => [x(p.k), y(p.sajat)])) + '"/>';

        pontok.forEach(p => {
            if (p.k % 5 === 0 && p.k !== u.kor) {
                svg += '<circle class="pont" cx="' + x(p.k) + '" cy="' + y(p.sajat) + '" r="4"><title>' + p.k + ' év: ' + idoFormaz(p.sajat) + ' (100%: ' + idoFormaz(p.szaz) + ')</title></circle>';
            }
        });

        const sx = x(u.kor), sy = y(u.ido);
        const balra = sx > W - 160;
        svg += '<circle class="pont-sajat" cx="' + sx + '" cy="' + sy + '" r="8"><title>Te: ' + u.kor + ' év, ' + idoFormaz(u.ido) + '</title></circle>';
        svg += '<text class="sajat-felirat" x="' + (sx + (balra ? -14 : 14)) + '" y="' + (sy - 12) + '" text-anchor="' + (balra ? 'end' : 'start') + '">Te: ' + idoFormaz(u.ido) + '</text>';
        svg += '</svg>';

        $('kseGrafikon').innerHTML = svg;
        $('kseGrafikonAl').textContent = u.tav.nev + ', ' + (u.nem === 'M' ? 'férfi' : 'nő') + ' – ' + szam(u.szazalek, 1) + '%-os szint, ' + korTol + '–' + korIg + ' év';
    }

    /* ---------- Összevetés ---------- */

    function osszevetesRajzol() {
        const lista = allapot.osszevetes.slice().sort((a, b) => b.szazalek - a.szazalek);
        $('kseOsszevetesBlokk').hidden = lista.length === 0;
        if (!lista.length) return;

        $('kseOsszevetesSav').innerHTML = lista.map(e =>
            '<div class="bar-row">' +
            '<span class="bar-cimke">' + html(e.cimke) + '</span>' +
            '<span class="bar-track"><span class="bar-fill" style="width:' + Math.min(100, e.szazalek).toFixed(1) + '%"></span></span>' +
            '<span class="bar-ertek">' + szam(e.szazalek, 1) + '%</span></div>'
        ).join('');

        let sor = '<thead><tr><th>Címke</th><th>Nem, kor</th><th>Táv</th><th class="num">Idő</th><th class="num">%</th><th class="num">Csúcskori egyenérték</th><th>Szint</th><th></th></tr></thead><tbody>';
        lista.forEach(e => {
            sor += '<tr><td>' + html(e.cimke) + '</td>' +
                '<td>' + (e.nem === 'M' ? 'férfi' : 'nő') + ', ' + e.kor + ' év</td>' +
                '<td>' + html(e.tav) + '</td>' +
                '<td class="num">' + idoFormaz(e.ido) + '</td>' +
                '<td class="num"><strong>' + szam(e.szazalek, 2) + '</strong></td>' +
                '<td class="num">' + idoFormaz(e.korrigalt) + '</td>' +
                '<td><span class="' + e.szint.badge + '">' + e.szint.nev + '</span></td>' +
                '<td><button type="button" class="btn btn-halvany btn-sm" data-torol="' + e.id + '" aria-label="Törlés">✕</button></td></tr>';
        });
        $('kseOsszevetes').innerHTML = sor + '</tbody>';
    }

    let kovetkezoId = 1;
    function osszevetesHozzaad() {
        if (!utolso) return;
        const u = utolso;
        if (allapot.osszevetes.length >= 20) allapot.osszevetes.shift();
        const cimke = $('kseCimke').value.trim() || ('#' + kovetkezoId);
        allapot.osszevetes.push({
            id: kovetkezoId++, cimke, nem: u.nem, kor: u.kor, tav: u.tav.nev + (u.tav.becsult ? ' (becsült)' : ''),
            ido: u.ido, szazalek: u.szazalek, korrigalt: u.korrigalt, szint: u.szint
        });
        $('kseCimke').value = '';
        osszevetesRajzol();
        $('kseOsszevetesBlokk').scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }

    /* ---------- URL-állapot ---------- */

    function urlFrissit() {
        const p = new URLSearchParams();
        p.set('nem', allapot.nem === 'M' ? 'ferfi' : 'no');
        if (allapot.korMod === 'kor') {
            p.set('kor', $('kseKor').value);
        } else {
            if ($('kseSzul').value) p.set('szul', $('kseSzul').value);
            if ($('kseVerseny').value) p.set('datum', $('kseVerseny').value);
        }
        if (allapot.tav === 'egyeni') {
            p.set('km', $('kseEgyeniKm').value.trim());
        } else {
            p.set('tav', allapot.tav);
        }
        if ($('kseIdo').value.trim()) p.set('ido', $('kseIdo').value.trim());
        try {
            history.replaceState(null, '', location.pathname + '?' + p.toString() + location.hash);
        } catch (e) { /* például file:// alatt */ }
    }

    function urlBeolvas() {
        const p = new URLSearchParams(location.search);
        if (p.get('nem') === 'no') allapot.nem = 'F';
        if (p.has('szul') || p.has('datum')) {
            allapot.korMod = 'datum';
            if (p.get('szul')) $('kseSzul').value = p.get('szul');
            if (p.get('datum')) $('kseVerseny').value = p.get('datum');
        } else if (p.has('kor')) {
            $('kseKor').value = p.get('kor');
            $('kseKorCsuszka').value = p.get('kor');
        }
        if (p.has('km')) {
            allapot.tav = 'egyeni';
            $('kseEgyeniKm').value = p.get('km');
        } else if (p.has('tav') && TAVOK.some(t => t.k === p.get('tav'))) {
            allapot.tav = p.get('tav');
        }
        if (p.has('ido')) $('kseIdo').value = p.get('ido');
    }

    /* ---------- Választógombok ---------- */

    function valasztasJelol(gombok, aktivFn) {
        gombok.forEach(g => {
            const on = aktivFn(g);
            g.classList.toggle('on', on);
            g.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function nezetFrissit() {
        valasztasJelol(document.querySelectorAll('#kseNem [data-nem]'), g => g.dataset.nem === allapot.nem);
        valasztasJelol(document.querySelectorAll('#kseKorMod [data-mod]'), g => g.dataset.mod === allapot.korMod);
        valasztasJelol(document.querySelectorAll('[data-tav]'), g => g.dataset.tav === allapot.tav);
        $('kseKorBlokk').hidden = allapot.korMod !== 'kor';
        $('kseDatumBlokk').hidden = allapot.korMod !== 'datum';
        $('kseEgyeniBlokk').hidden = allapot.tav !== 'egyeni';
    }

    function tavChipekEpit() {
        const chip = t => '<button type="button" class="chip" data-tav="' + t.k + '">' + t.nev + '</button>';
        $('kseTavNepszeru').innerHTML = TAVOK.filter(t => NEPSZERU.includes(t.k)).map(chip).join('');
        $('kseTavOsszes').innerHTML = TAVOK.filter(t => !NEPSZERU.includes(t.k)).map(chip).join('');
    }

    /* Gépelés közben ne menjen kérés minden leütésre */
    let kesleltetes = null;
    function szamolKesleltetve() {
        clearTimeout(kesleltetes);
        kesleltetes = setTimeout(szamol, 250);
    }

    /* ---------- Indítás ---------- */

    tavChipekEpit();
    urlBeolvas();
    nezetFrissit();

    document.querySelectorAll('#kseNem [data-nem]').forEach(g => g.addEventListener('click', () => {
        allapot.nem = g.dataset.nem; nezetFrissit(); szamol();
    }));
    document.querySelectorAll('#kseKorMod [data-mod]').forEach(g => g.addEventListener('click', () => {
        allapot.korMod = g.dataset.mod; nezetFrissit(); szamol();
    }));
    document.querySelectorAll('[data-tav]').forEach(g => g.addEventListener('click', () => {
        allapot.tav = g.dataset.tav; nezetFrissit(); szamol();
        if (allapot.tav === 'egyeni') $('kseEgyeniKm').focus();
    }));

    $('kseKorCsuszka').addEventListener('input', () => { $('kseKor').value = $('kseKorCsuszka').value; szamolKesleltetve(); });
    $('kseKor').addEventListener('input', () => {
        const k = Number($('kseKor').value);
        if (k >= KOR_MIN && k <= KOR_MAX) $('kseKorCsuszka').value = k;
        szamolKesleltetve();
    });
    ['kseSzul', 'kseVerseny', 'kseEgyeniKm'].forEach(id => $(id).addEventListener('input', szamolKesleltetve));
    $('kseIdo').addEventListener('input', szamol);   // az idő változása nem kér új adatot
    $('kseCelSzazalek').addEventListener('input', celSzazalekSzamol);

    $('kseHozzaad').addEventListener('click', osszevetesHozzaad);
    $('kseCimke').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); osszevetesHozzaad(); } });
    $('kseOsszevetes').addEventListener('click', e => {
        const g = e.target.closest('[data-torol]');
        if (!g) return;
        allapot.osszevetes = allapot.osszevetes.filter(x => x.id !== Number(g.dataset.torol));
        osszevetesRajzol();
    });
    $('kseOsszevetesTorles').addEventListener('click', () => { allapot.osszevetes = []; osszevetesRajzol(); });

    $('kseLink').addEventListener('click', () => {
        urlFrissit();
        const gomb = $('kseLink');
        const kesz = () => { gomb.textContent = '✓ Link kimásolva'; setTimeout(() => { gomb.textContent = '🔗 Link az eredményhez'; }, 2000); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(location.href).then(kesz, () => prompt('Másold ki a linket:', location.href));
        } else {
            prompt('Másold ki a linket:', location.href);
        }
    });

    $('kseAlaphelyzet').addEventListener('click', () => {
        allapot.nem = 'M'; allapot.korMod = 'kor'; allapot.tav = '10k';
        $('kseKor').value = 45; $('kseKorCsuszka').value = 45;
        $('kseSzul').value = ''; $('kseVerseny').value = MA;
        $('kseEgyeniKm').value = ''; $('kseIdo').value = ''; $('kseCelSzazalek').value = '';
        nezetFrissit(); szamol();
        $('kseIdo').focus();
    });

    szamol();
})();
</script>
</body>
</html>
