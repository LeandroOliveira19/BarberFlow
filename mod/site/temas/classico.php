<?php
declare(strict_types=1);
// Tema CLÁSSICO do site (estrutura visual do módulo site/ver)
if (count(get_included_files()) === 1) { header('Location: ../../../index.php'); exit; }

/**
 * Layout CLÁSSICO — BarberFlow
 * 
 * @var array $barbearia
 * @var array $config
 * @var array $servicos
 * @var array $equipa
 * @var string $telemovel
 * @var array|null $galeria
 * @var array $googleReviews
 */
require_once __DIR__ . '/../../../config/marcacoes.php';

if (!function_exists('e')) {
    function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('corHex')) {
    function corHex(mixed $v, string $padrao): string {
        return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', (string)$v) ? $v : $padrao;
    }
}
if (!function_exists('cssUrl')) {
    function cssUrl(mixed $v): string {
        $v = trim((string)$v);
        if ($v === '' || !preg_match('#^(https?://|/|\./|[A-Za-z0-9_\-]+/)#', $v)) return '';
        return str_replace(['"', '\\', "\n", "\r", '(', ')', '<', '>'], ['%22', '%5C', '', '', '%28', '%29', '%3C', '%3E'], $v);
    }
}

/* ---------- Dados normalizados ---------- */
$nome      = $barbearia['nome'];
$morada    = $barbearia['morada'] ?? '';
$slogan    = getConfig($config, 'slogan', 'Tradição, estilo e precisão em cada corte.');
$sobre     = getConfig($config, 'sobre', '');
$corPri    = corHex(getConfig($config, 'tema_cor', '#cda47c'), '#cda47c');
$corFundo  = corHex(getConfig($config, 'tema_fundo', '#0a0a0a'), '#0a0a0a');
$fonteTit  = trim(preg_replace('/[^A-Za-z0-9 ]/', '', (string)getConfig($config, 'tema_fonte', 'Cinzel'))) ?: 'Cinzel';
$fonteUrl  = str_replace(' ', '+', $fonteTit);
$heroImg   = cssUrl(getConfig($config, 'tema_hero', '')) ?: 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=1600&q=80';
$telDigits = preg_replace('/\D/', '', (string)($telemovel ?? ''));
if (strlen($telDigits) === 9) $telDigits = '351' . $telDigits;
$temWhatsapp = strlen($telDigits) >= 11;
$telExibe  = getConfig($config, 'telemovel_contacto', '');
$email     = getConfig($config, 'email_contacto', '');
$abre      = getConfig($config, 'hora_abertura', '');
$fecha     = getConfig($config, 'hora_fecho', '');
$dias      = rotuloDias(diasFuncionamento(['dias_funcionamento' => getConfig($config, 'dias_funcionamento', '1,2,3,4,5,6')]));

$instagram = getConfig($config, 'link_instagram', '');
$facebook  = getConfig($config, 'link_facebook', '');

$waBase    = 'https://wa.me/' . $telDigits;
$waMarcar  = $waBase . '?text=' . rawurlencode('Olá, gostaria de marcar um corte.');
$linkMarcar = urlModulo('marcar', 'formulario', ['slug' => $barbearia['slug']]);

$galeriaFinal = [];
if (!empty($galeria) && is_array($galeria)) {
    foreach ($galeria as $url) {
        $galeriaFinal[] = ['url' => $url, 'legenda' => 'Trabalho ' . $nome];
    }
}

$faqFinal = (!empty($faq) && is_array($faq)) ? $faq : [
    ['pergunta' => 'Preciso de fazer marcação prévia?',  'resposta' => 'Recomendamos marcação para não ter de esperar.'],
    ['pergunta' => 'Quais são os métodos de pagamento?', 'resposta' => 'Dinheiro, cartões e MB WAY.'],
];
$marquee = [$nome, 'Estilo', 'Precisão', 'Tradição'];

$ldJson = [
    '@context'  => 'https://schema.org',
    '@type'     => 'HairSalon',
    'name'      => $nome,
    'address'   => $morada,
    'telephone' => $telExibe,
    'image'     => getConfig($config, 'tema_hero', ''),
];
$iconPin   = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>';
$iconPhone = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1z"/></svg>';
$iconClock = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 5v5.2l4 2.4-.8 1.3L11 13V7h2z"/></svg>';
$iconMail  = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1zm9 7.2L4.5 7v.1L12 12.3 19.5 7.1V7L12 12.2z"/></svg>';
?>
<?php ob_start(); /* conteúdo do <head> desta página: o index.php coloca-o na estrutura */ ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($nome) ?> — Barbearia Premium</title>
    <meta name="description" content="<?= e($slogan) ?> <?= e($morada) ?>">
    <meta name="theme-color" content="<?= e($corFundo) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($nome) ?>">
    <meta property="og:description" content="<?= e($slogan) ?>">
    <?php if (getConfig($config, 'tema_hero', '')): ?><meta property="og:image" content="<?= e(getConfig($config, 'tema_hero', '')) ?>"><?php endif; ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✂️</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= e($fonteUrl) ?>:wght@400;600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script type="application/ld+json"><?= json_encode($ldJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
    <noscript><style>.reveal { opacity: 1 !important; transform: none !important; }</style></noscript>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: <?= e($corFundo) ?>;
            --surface: rgba(255, 255, 255, 0.035);
            --surface-light: rgba(255, 255, 255, 0.08);
            --primary: <?= e($corPri) ?>;
            --text: #f4f1ec;
            --text-muted: #a1a1a6;
            --border: rgba(255, 255, 255, 0.09);
            --font-heading: '<?= e($fonteTit) ?>', Georgia, serif;
            --font-body: 'Outfit', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        html { scroll-behavior: smooth; scroll-padding-top: 80px; }
        body { font-family: var(--font-body); background: var(--bg); color: var(--text); line-height: 1.7; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; display: block; }
        ::selection { background: var(--primary); color: var(--bg); }
        :focus-visible { outline: 2px solid var(--primary); outline-offset: 3px; }
        .ico { width: 18px; height: 18px; fill: var(--primary); flex: none; }

        .reveal { opacity: 0; transform: translateY(28px); transition: opacity 0.8s ease, transform 0.8s ease; transition-delay: calc(var(--i, 0) * 120ms); }
        .reveal.active { opacity: 1; transform: none; }

        nav { position: fixed; top: 0; left: 0; width: 100%; padding: 1.4rem 5%; display: flex; justify-content: space-between; align-items: center; z-index: 1000; transition: background 0.4s, padding 0.4s, border-color 0.4s; border-bottom: 1px solid transparent; }
        nav.scrolled, nav.open { background: rgba(10, 10, 10, 0.92); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); padding: 0.9rem 5%; border-bottom-color: var(--border); }
        .logo { font-family: var(--font-heading); font-size: 1.5rem; font-weight: 700; color: var(--primary); text-decoration: none; letter-spacing: 0.08em; }
        .nav-right { display: flex; align-items: center; gap: 2rem; }
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { color: var(--text); text-decoration: none; font-size: 0.92rem; position: relative; transition: color 0.3s; }
        .nav-links a::after { content: ''; position: absolute; left: 0; bottom: -4px; width: 100%; height: 1px; background: var(--primary); transform: scaleX(0); transform-origin: left; transition: transform 0.3s; }
        .nav-links a:hover { color: var(--primary); }
        .nav-links a:hover::after { transform: scaleX(1); }
        .nav-cta { background: var(--primary); color: var(--bg); text-decoration: none; padding: 0.65rem 1.4rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; border: 1px solid var(--primary); transition: background 0.3s, color 0.3s; }
        .nav-cta:hover { background: transparent; color: var(--primary); }
        .nav-toggle { display: none; background: none; border: 1px solid var(--border); color: var(--text); width: 44px; height: 44px; border-radius: 8px; cursor: pointer; align-items: center; justify-content: center; }
        .nav-toggle svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }

        .hero {
            min-height: 100svh; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 7rem 5% 5rem;
            background:
                radial-gradient(ellipse at center, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0.75) 100%),
                linear-gradient(to bottom, rgba(0,0,0,0.45) 0%, var(--bg) 100%),
                url("<?= $heroImg ?>") center / cover no-repeat;
        }
        .hero-tag { display: inline-flex; align-items: center; gap: 1rem; font-size: 0.85rem; color: var(--primary); margin-bottom: 1.8rem; letter-spacing: 0.12em; }
        .hero-tag::before, .hero-tag::after { content: ''; width: 48px; height: 1px; background: var(--primary); opacity: 0.6; }
        .hero h1 { font-family: var(--font-heading); font-size: clamp(2.8rem, 8vw, 6rem); letter-spacing: 0.04em; line-height: 1.05; margin-bottom: 1.2rem; text-shadow: 0 4px 30px rgba(0,0,0,0.6); }
        .hero p { font-size: 1.1rem; color: #cfcfd3; max-width: 560px; margin-bottom: 2.5rem; font-weight: 300; }
        .hero-buttons { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
        .btn-cta, .btn-cta-outline { padding: 1rem 2.4rem; font-size: 0.88rem; font-weight: 600; text-decoration: none; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.08em; border: 2px solid var(--primary); transition: all 0.3s ease; display: inline-block; }
        .btn-cta { background: var(--primary); color: var(--bg); }
        .btn-cta:hover { background: transparent; color: var(--primary); }
        .btn-cta-outline { background: rgba(0,0,0,0.25); color: var(--primary); }
        .btn-cta-outline:hover { background: var(--primary); color: var(--bg); }

        section { padding: 6rem 5%; max-width: 1300px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 3.5rem; }
        .section-header h2 { font-family: var(--font-heading); font-size: clamp(1.9rem, 4vw, 2.6rem); color: var(--primary); margin-bottom: 1rem; }
        .section-header .line { width: 60px; height: 2px; background: var(--primary); margin: 0 auto; display: block; opacity: 0.5; }
        .sobre-content { text-align: center; max-width: 760px; margin: 0 auto; font-size: 1.15rem; color: var(--text-muted); font-weight: 300; }

        .grid-servicos { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.8rem; }
        .card-servico { position: relative; overflow: hidden; display: flex; flex-direction: column; background: linear-gradient(145deg, rgba(255,255,255,0.055), rgba(255,255,255,0.01)); padding: 2.2rem 2rem; border-radius: 12px; border: 1px solid var(--border); transition: opacity 0.8s ease, transform 0.4s ease, border-color 0.4s, box-shadow 0.4s; }
        .card-servico::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, var(--primary), transparent); transform: scaleX(0); transition: transform 0.5s ease; }
        .card-servico.active:hover { transform: translateY(-6px); border-color: rgba(255,255,255,0.18); box-shadow: 0 20px 40px rgba(0,0,0,0.45); }
        .card-servico:hover::before { transform: scaleX(1); }
        .card-servico h3 { font-family: var(--font-heading); font-size: 1.25rem; margin-bottom: 0.4rem; }
        .card-servico .duracao { font-size: 0.82rem; color: var(--primary); letter-spacing: 0.04em; margin-bottom: 1rem; }
        .card-servico .desc { color: var(--text-muted); font-size: 0.97rem; flex: 1; }
        .card-rodape { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.2rem; }
        .card-servico .preco { font-family: var(--font-heading); font-size: 1.9rem; color: var(--primary); }
        .card-marcar { border: 1px solid var(--primary); color: var(--primary); text-decoration: none; padding: 0.5rem 1.3rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; transition: background 0.3s, color 0.3s; }
        .card-marcar:hover { background: var(--primary); color: var(--bg); }

        .marquee-container { background: var(--primary); color: var(--bg); padding: 1.3rem 0; overflow: hidden; white-space: nowrap; display: flex; }
        .marquee-group { display: flex; flex-shrink: 0; min-width: 100%; justify-content: space-around; gap: 2rem; padding-right: 2rem; animation: scroll-left 24s linear infinite !important; font-family: var(--font-heading); font-size: 1.4rem; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; }
        @keyframes scroll-left { to { transform: translateX(-100%); } }

        #galeria { max-width: 100%; padding: 5rem 0 4rem; }
        .galeria-wrapper { padding: 0 5%; }
        .galeria-scroll { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 1.5rem; padding-bottom: 1.5rem; scrollbar-width: thin; scrollbar-color: var(--primary) var(--surface); }
        .galeria-scroll::-webkit-scrollbar { height: 6px; }
        .galeria-scroll::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        .galeria-item { position: relative; flex: 0 0 min(85%, 560px); height: 420px; scroll-snap-align: center; border-radius: 12px; overflow: hidden; }
        .galeria-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.8s ease; }
        .galeria-item:hover img { transform: scale(1.04); }
        .galeria-item figcaption { position: absolute; inset: auto 0 0 0; padding: 2.5rem 1.5rem 1.2rem; background: linear-gradient(transparent, rgba(0,0,0,0.75)); font-size: 0.95rem; }

        .grid-equipa { display: flex; justify-content: center; gap: 2.5rem; flex-wrap: wrap; }
        .membro { text-align: center; width: 250px; display: flex; flex-direction: column; align-items: center; background: rgba(255,255,255,0.02); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border); transition: border-color 0.3s; }
        .membro:hover { border-color: var(--primary); }
        .avatar-img { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 3px solid var(--surface-light); margin: 0 auto 1.2rem; transition: border-color 0.4s, filter 0.4s; filter: grayscale(100%); }
        .avatar-ini { display: flex; align-items: center; justify-content: center; background: var(--surface-light); font-family: var(--font-heading); font-size: 3.2rem; color: var(--primary); filter: none; }
        .membro:hover .avatar-img { border-color: var(--primary); filter: grayscale(0%); }
        .membro h4 { font-family: var(--font-heading); font-size: 1.2rem; margin-bottom: 0.2rem; }
        .membro .esp { font-size: 0.85rem; color: var(--primary); font-weight: 500; margin-bottom: 0.8rem; }
        .membro .insta-link { display: inline-flex; align-items: center; gap: 0.4rem; color: var(--text-muted); text-decoration: none; font-size: 0.85rem; transition: color 0.3s; margin-top: auto; padding-top: 1rem; }
        .membro .insta-link:hover { color: var(--primary); }

        .bg-surface { background: var(--surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .testemunhos-scroll { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 1.5rem; padding-bottom: 2rem; scrollbar-width: thin; scrollbar-color: var(--primary) var(--surface); }
        .testemunhos-scroll::-webkit-scrollbar { height: 6px; }
        .testemunhos-scroll::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        .testemunho { flex: 0 0 min(85%, 350px); scroll-snap-align: center; background: var(--bg); padding: 2.5rem 2rem 2rem; border-radius: 12px; border: 1px solid var(--border); position: relative; display: flex; flex-direction: column; justify-content: space-between; }
        .testemunho .quote { font-size: 5rem; line-height: 1; font-family: var(--font-heading); color: var(--primary); opacity: 0.25; position: absolute; top: 12px; left: 22px; }
        .testemunho p { position: relative; font-size: 1.05rem; font-style: italic; color: #cfcfd3; margin-bottom: 1.5rem; padding-top: 1.2rem; flex: 1; }
        .testemunho h5 { color: var(--primary); font-weight: 500; font-size: 0.95rem; display: flex; justify-content: space-between; align-items: center; }

        .faq-grid { max-width: 800px; margin: 0 auto; }
        .faq-item { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 0.9rem; transition: border-color 0.3s; }
        .faq-item[open] { border-color: var(--primary); }
        .faq-item summary { padding: 1.3rem 1.5rem; cursor: pointer; font-weight: 500; list-style: none; display: flex; justify-content: space-between; gap: 1rem; font-size: 1.03rem; }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item summary::after { content: '+'; color: var(--primary); font-size: 1.5rem; line-height: 1; transition: transform 0.3s; }
        .faq-item[open] summary::after { transform: rotate(45deg); }
        .faq-item p { padding: 1rem 1.5rem 1.4rem; color: var(--text-muted); border-top: 1px solid var(--border); }

        .contactos-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
        .contacto-linha { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1.8rem; }
        .contacto-linha .ico { margin-top: 0.4rem; }
        .contacto-linha h3 { font-family: var(--font-heading); color: var(--primary); font-size: 1.1rem; margin-bottom: 0.2rem; }
        .contacto-linha p, .contacto-linha a { color: var(--text-muted); text-decoration: none; }
        .contacto-linha a:hover { color: var(--primary); }
        .mapa-container { border-radius: 12px; overflow: hidden; border: 1px solid var(--border); height: 400px; }
        .mapa-container iframe { width: 100%; height: 100%; border: 0; filter: invert(90%) hue-rotate(180deg) grayscale(20%); }

        footer { background: #050505; border-top: 1px solid var(--border); padding: 5rem 5% 2rem; }
        .footer-grid { max-width: 1300px; margin: 0 auto 3.5rem; display: grid; grid-template-columns: 2fr 1fr 1.4fr 1fr; gap: 3rem; }
        .footer-brand h3 { font-family: var(--font-heading); color: var(--primary); font-size: 1.7rem; margin-bottom: 1rem; }
        .footer-brand p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; max-width: 300px; }
        .social-icons { display: flex; gap: 1rem; }
        .social-icons a { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 50%; background: var(--surface); transition: background 0.3s; }
        .social-icons a:hover { background: var(--primary); }
        .social-icons svg { width: 20px; height: 20px; fill: var(--text-muted); transition: fill 0.3s; }
        .social-icons a:hover svg { fill: var(--bg); }
        .footer-col h4 { font-family: var(--font-heading); color: var(--text); font-size: 1.05rem; margin-bottom: 1.3rem; }
        .footer-col a, .footer-col p { display: flex; align-items: flex-start; gap: 0.6rem; color: var(--text-muted); text-decoration: none; margin-bottom: 0.8rem; font-size: 0.95rem; transition: color 0.3s; }
        .footer-col a:hover { color: var(--primary); }
        .footer-col .ico { width: 16px; height: 16px; margin-top: 0.35rem; }
        .footer-bottom { max-width: 1300px; margin: 0 auto; padding-top: 2rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 1rem; color: var(--text-muted); font-size: 0.85rem; }
        .footer-bottom a { color: var(--text-muted); text-decoration: none; margin-left: 1.5rem; transition: color 0.3s; }
        .footer-bottom a:hover { color: var(--primary); }

        .mobile-cta { display: none; position: fixed; bottom: 0; left: 0; width: 100%; background: var(--primary); color: var(--bg); text-align: center; padding: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; z-index: 1000; text-decoration: none; }
        .fab-whatsapp { position: fixed; bottom: 28px; right: 28px; background: #25D366; color: #fff; width: 58px; height: 58px; border-radius: 50%; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4); z-index: 999; transition: transform 0.3s; }
        .fab-whatsapp:hover { transform: scale(1.08); }
        .fab-whatsapp svg { width: 32px; height: 32px; fill: currentColor; }

        @media (max-width: 900px) {
            .nav-toggle { display: flex; }
            .nav-cta { display: none; }
            .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; flex-direction: column; align-items: flex-start; gap: 1.2rem; background: rgba(10,10,10,0.97); padding: 1.5rem 5% 2rem; border-bottom: 1px solid var(--border); }
            nav.open .nav-links { display: flex; }
            section { padding: 4.5rem 5%; }
            .contactos-wrapper { grid-template-columns: 1fr; gap: 2.5rem; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .footer-bottom { flex-direction: column; text-align: center; }
            .footer-bottom a { margin: 0 0.75rem; }
            .mobile-cta { display: block; }
            .fab-whatsapp { bottom: 80px; right: 20px; }
            footer { padding-bottom: 6rem; }
        }
        @media (max-width: 520px) { .footer-grid { grid-template-columns: 1fr; } .galeria-item { height: 320px; } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
            .reveal { opacity: 1; transform: none; }
        }
    </style>
<?php $CABECALHO = (string)ob_get_clean(); ?>

    <nav id="navbar">
        <a href="#" class="logo"><?= e($nome) ?></a>
        <div class="nav-right">
            <div class="nav-links" id="nav-links">
                <?php if ($sobre): ?><a href="#sobre">Sobre</a><?php endif; ?>
                <a href="#servicos">Serviços</a>
                <a href="#galeria">Galeria</a>
                <?php if (count($equipa) > 0): ?><a href="#equipa">Equipa</a><?php endif; ?>
                <a href="#faq">FAQ</a>
                <a href="#contactos">Contactos</a>
            </div>
            <a href="<?= e($linkMarcar) ?>" class="nav-cta">Marcar</a>
            <button class="nav-toggle" id="nav-toggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="nav-links">
                <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </nav>

    <header class="hero">
        <span class="hero-tag">Barbearia Premium</span>
        <h1><?= e($nome) ?></h1>
        <p><?= e($slogan) ?></p>
        <div class="hero-buttons">
            <a href="#servicos" class="btn-cta-outline">Ver serviços</a>
            <a href="<?= e($linkMarcar) ?>" class="btn-cta">Marcar agora</a>
        </div>
    </header>

    <?php if ($sobre): ?>
    <section id="sobre" class="reveal">
        <div class="section-header"><h2>A Nossa História</h2><span class="line"></span></div>
        <div class="sobre-content"><p><?= nl2br(e($sobre)) ?></p></div>
    </section>
    <?php endif; ?>

    <section id="servicos">
        <div class="section-header reveal"><h2>Serviços Premium</h2><span class="line"></span></div>
        <div class="grid-servicos">
            <?php foreach ($servicos as $i => $s): ?>
                <article class="card-servico reveal" style="--i: <?= (int)$i % 6 ?>">
                    <h3><?= e($s['nome']) ?></h3>
                    <p class="duracao">Duração: <?= e($s['duracao_minutos']) ?> min</p>
                    <p class="desc"><?= e($s['descricao'] ?? '') ?></p>
                    <div class="card-rodape">
                        <div class="preco"><?= number_format((float)$s['preco'], 2, ',', '') ?> €</div>
                        <a class="card-marcar" href="<?= e($linkMarcar . '&servico=' . (int)$s['id']) ?>" aria-label="Marcar <?= e($s['nome']) ?>">Marcar</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="marquee-container" aria-hidden="true">
        <?php for ($g = 0; $g < 2; $g++): ?>
            <div class="marquee-group">
                <?php foreach ($marquee as $palavra): ?><span><?= e($palavra) ?></span><span>•</span><?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>

    <section id="galeria">
        <div class="section-header reveal"><h2>O Nosso Trabalho</h2><span class="line"></span></div>
        <div class="galeria-wrapper reveal">
            <div class="galeria-scroll">
                <?php foreach ($galeriaFinal as $img): ?>
                    <figure class="galeria-item">
                        <img src="<?= e($img['url']) ?>" alt="<?= e($img['legenda'] ?? 'Trabalho de ' . $nome) ?>" loading="lazy">
                        <?php if (!empty($img['legenda'])): ?><figcaption><?= e($img['legenda']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SECÇÃO DA EQUIPA ATUALIZADA -->
    <?php if (count($equipa) > 0): ?>
    <section id="equipa" class="reveal">
        <div class="section-header"><h2>Mestres Barbeiros</h2><span class="line"></span></div>
        <div class="grid-equipa">
            <?php foreach ($equipa as $b): ?>
                <div class="membro">
                    <?php 
                    $foto = $b['foto_url'] ?? '';
                    if ($foto && !str_starts_with($foto, 'http')) {
                        $foto = '../' . ltrim($foto, '/'); 
                    }
                    ?>
                    <?php if ($foto): ?>
                        <img src="<?= e($foto) ?>" alt="<?= e($b['nome']) ?>" class="avatar-img" loading="lazy">
                    <?php else: ?>
                        <div class="avatar-img avatar-ini" role="img" aria-label="<?= e($b['nome']) ?>"><?= e(mb_strtoupper(mb_substr($b['nome'], 0, 1))) ?></div>
                    <?php endif; ?>
                    
                    <h4><?= e($b['nome']) ?></h4>
                    <p class="esp"><?= e($b['especialidades'] ?? 'Barbeiro Profissional') ?></p>
                    
                    <!-- BIOGRAFIA CURTA (Limitada a 3 linhas) -->
                    <?php if (!empty($b['bio'])): ?>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5;">
                            <?= nl2br(e($b['bio'])) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($b['instagram'])): ?>
                        <a href="<?= e($b['instagram']) ?>" target="_blank" rel="noopener" class="insta-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            Instagram
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- REVIEWS DINÂMICAS EM SLIDER -->
    <div class="bg-surface">
        <section class="reveal" style="max-width: 100%; padding-left: 0; padding-right: 0;">
            <div class="section-header"><h2>O Que Dizem</h2><span class="line"></span></div>
            <div class="galeria-wrapper reveal">
                <div class="testemunhos-scroll">
                    <?php if (!empty($googleReviews)): ?>
                        <?php foreach ($googleReviews as $review): 
                            $rating = (int)($review['rating'] ?? 5);
                            $autor = $review['author_name'] ?? 'Cliente';
                            $texto = $review['text'] ?? '(Sem comentário)';
                        ?>
                            <blockquote class="testemunho">
                                <span class="quote" aria-hidden="true">“</span>
                                <p><?= e($texto) ?></p>
                                <h5>
                                    <span>— <?= e($autor) ?></span>
                                    <span style="color: var(--primary); font-size: 1.2rem; letter-spacing: 2px;">
                                        <?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?>
                                    </span>
                                </h5>
                            </blockquote>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align:center; color:#9ca3af; padding:2rem; width: 100%;">As avaliações do Google estarão disponíveis em breve.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <section id="faq" class="reveal">
        <div class="section-header"><h2>Perguntas Frequentes</h2><span class="line"></span></div>
        <div class="faq-grid">
            <?php foreach ($faqFinal as $f): ?>
                <details class="faq-item"><summary><?= e($f['pergunta']) ?></summary><p><?= e($f['resposta']) ?></p></details>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="contactos" class="reveal">
        <div class="section-header"><h2>Visite-nos</h2><span class="line"></span></div>
        <div class="contactos-wrapper">
            <div class="contacto-info">
                <a href="<?= e($linkMarcar) ?>" class="btn-cta" style="margin-bottom:2rem">Marcar online</a>
                <div class="contacto-linha"><?= $iconPin ?><div><h3>Localização</h3><p><?= e($morada) ?></p></div></div>
                <div class="contacto-linha"><?= $iconClock ?><div><h3>Horário</h3><p><?= e($dias) ?><br><?= e($abre) ?> — <?= e($fecha) ?></p></div></div>
                <div class="contacto-linha"><?= $iconPhone ?><div><h3>Contactos</h3>
                    <p><?php if ($telExibe): ?><a href="tel:<?= e($telemovel ?? $telExibe) ?>"><?= e($telExibe) ?></a><br><?php endif; ?>
                    <?php if ($email): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?></p></div></div>
            </div>
            <div class="mapa-container">
                <iframe title="Mapa: <?= e($nome) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=<?= urlencode($morada) ?>&amp;t=&amp;z=15&amp;ie=UTF8&amp;iwloc=&amp;output=embed"></iframe>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-grid">
            <div class="footer-brand">
                <h3><?= e($nome) ?></h3>
                <p>A sua barbearia de eleição. Tradição, estilo e precisão em cada corte.</p>
                <?php if ($instagram || $facebook): ?>
                <div class="social-icons">
                    <?php if ($instagram): ?>
                    <a href="<?= e($instagram) ?>" target="_blank" rel="noopener" aria-label="Instagram">
                        <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if ($facebook): ?>
                    <a href="<?= e($facebook) ?>" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="footer-col"><h4>Navegação</h4><a href="#servicos">Serviços</a><a href="#galeria">Galeria</a><a href="#faq">FAQ</a><a href="#contactos">Contactos</a></div>
            <div class="footer-col"><h4>Contactos</h4>
                <p><?= $iconPin ?><span><?= e($morada) ?></span></p>
                <?php if ($telExibe): ?><p><?= $iconPhone ?><span><?= e($telExibe) ?></span></p><?php endif; ?>
                <?php if ($email): ?><p><?= $iconMail ?><span><?= e($email) ?></span></p><?php endif; ?>
            </div>
            <div class="footer-col"><h4>Horário</h4><p><?= $iconClock ?><span><?= e($dias) ?><br><?= e($abre) ?> — <?= e($fecha) ?></span></p></div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e($nome) ?> · Powered by BarberFlow</p>
            <div><a href="#">Termos</a><a href="#">Privacidade</a></div>
        </div>
    </footer>

    <a href="<?= e($linkMarcar) ?>" class="mobile-cta">Marcar agora</a>
    <?php if ($temWhatsapp): ?>
    <a href="<?= e($waBase) ?>" class="fab-whatsapp" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
        <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
    </a>
    <?php endif; ?>

    <script>
        const navbar = document.getElementById('navbar');
        const toggle = document.getElementById('nav-toggle');
        const onScroll = () => navbar.classList.toggle('scrolled', window.scrollY > 50);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        toggle.addEventListener('click', () => {
            const aberto = navbar.classList.toggle('open');
            toggle.setAttribute('aria-expanded', aberto);
        });
        document.querySelectorAll('#nav-links a').forEach(a => a.addEventListener('click', () => {
            navbar.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }));

        const revealEls = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window) {
            const obs = new IntersectionObserver((entries, o) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) { entry.target.classList.add('active'); o.unobserve(entry.target); }
                });
            }, { threshold: 0.12 });
            revealEls.forEach(el => obs.observe(el));
        } else {
            revealEls.forEach(el => el.classList.add('active'));
        }
    </script>
