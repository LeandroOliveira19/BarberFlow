<?php
/**
 * Layout MINIMALISTA — BarberFlow
 * 
 * @var array $barbearia
 * @var array $config
 * @var array $servicos
 * @var array $equipa
 * @var string $telemovel
 * @var array|null $galeria
 * @var array $googleReviews
 */
require_once __DIR__ . '/../../config/marcacoes.php';

if (!function_exists('e')) {
    function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('corHex')) {
    function corHex(mixed $v, string $padrao): string {
        return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', (string)$v) ? $v : $padrao;
    }
}

/* ---------- Dados normalizados ---------- */
$nome       = $barbearia['nome'];
$morada     = $barbearia['morada'] ?? '';
$slogan     = getConfig($config, 'slogan', 'Bem-vindo ao nosso estúdio de barbearia.');
$heroTitulo = getConfig($config, 'hero_titulo', 'Precisão e estilo em cada detalhe.');
$sobre      = getConfig($config, 'sobre', '');
$accent     = corHex(getConfig($config, 'tema_cor', '#dc2626'), '#dc2626');
$heroImg    = getConfig($config, 'tema_hero', '') ?: 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?auto=format&fit=crop&w=1000&q=80';
$sobreImg   = getConfig($config, 'sobre_imagem', '') ?: 'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?auto=format&fit=crop&w=900&q=80';
$telDigits  = preg_replace('/\D/', '', (string)($telemovel ?? ''));
if (strlen($telDigits) === 9) $telDigits = '351' . $telDigits;
$temWhatsapp = strlen($telDigits) >= 11;
$telExibe   = getConfig($config, 'telemovel_contacto', '');
$email      = getConfig($config, 'email_contacto', '');
$abre       = getConfig($config, 'hora_abertura', '');
$fecha      = getConfig($config, 'hora_fecho', '');
$dias       = rotuloDias(diasFuncionamento(['dias_funcionamento' => getConfig($config, 'dias_funcionamento', '1,2,3,4,5,6')]));

$instagram  = getConfig($config, 'link_instagram', '');
$facebook   = getConfig($config, 'link_facebook', '');

$waMarcar   = 'https://wa.me/' . $telDigits . '?text=' . rawurlencode('Olá, gostaria de marcar um corte.');
$linkMarcar = 'marcar.php?slug=' . rawurlencode($barbearia['slug']);
$mapaLink   = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($morada);

$galeriaFinal = [];
if (!empty($galeria) && is_array($galeria)) {
    foreach (array_slice($galeria, 0, 3) as $url) {
        $galeriaFinal[] = ['url' => $url, 'legenda' => 'Trabalho'];
    }
}

$faqFinal = (!empty($faq) && is_array($faq)) ? $faq : [
    ['pergunta' => 'Preciso de fazer marcação prévia?',  'resposta' => 'Recomendamos marcação para não ter de esperar.'],
    ['pergunta' => 'Quais são os métodos de pagamento?', 'resposta' => 'Dinheiro, cartões e MB WAY.'],
];

$ldJson = [
    '@context'  => 'https://schema.org',
    '@type'     => 'HairSalon',
    'name'      => $nome,
    'address'   => $morada,
    'telephone' => $telExibe,
    'image'     => $heroImg,
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($nome) ?> — Estúdio de Barbearia</title>
    <meta name="description" content="<?= e($slogan) ?> <?= e($morada) ?>">
    <meta name="theme-color" content="#faf9f6">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($nome) ?>">
    <meta property="og:description" content="<?= e($slogan) ?>">
    <meta property="og:image" content="<?= e($heroImg) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✂️</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <script type="application/ld+json"><?= json_encode($ldJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
    <noscript><style>.reveal { opacity: 1 !important; transform: none !important; }</style></noscript>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #faf9f6;
            --paper: #ffffff;
            --text: #141414;
            --muted: #636363;
            --accent: <?= e($accent) ?>;
            --border: #e6e4df;
            --serif: 'Playfair Display', Georgia, serif;
            --sans: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        html { scroll-behavior: smooth; scroll-padding-top: 90px; }
        body { font-family: var(--sans); background: var(--bg); color: var(--text); line-height: 1.65; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; display: block; }
        ::selection { background: var(--accent); color: #fff; }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

        .reveal { opacity: 0; transform: translateY(24px); transition: opacity 0.9s ease, transform 0.9s ease; }
        .reveal.active { opacity: 1; transform: none; }

        nav { position: fixed; top: 0; left: 0; width: 100%; padding: 1.3rem 5%; display: flex; justify-content: space-between; align-items: center; z-index: 1000; background: rgba(250, 249, 246, 0.92); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); }
        .logo { font-family: var(--serif); font-size: 1.6rem; font-weight: 600; color: var(--text); text-decoration: none; }
        .nav-right { display: flex; align-items: center; gap: 2.2rem; }
        .nav-links { display: flex; gap: 2.2rem; }
        .nav-links a { color: var(--text); text-decoration: none; font-size: 0.92rem; transition: color 0.3s; }
        .nav-links a:hover { color: var(--accent); }
        .nav-cta { background: var(--text); color: var(--bg); padding: 0.6rem 1.4rem; text-decoration: none; font-size: 0.88rem; font-weight: 500; transition: background 0.3s; }
        .nav-cta:hover { background: var(--accent); }
        .nav-toggle { display: none; background: none; border: 1px solid var(--border); width: 44px; height: 44px; cursor: pointer; color: var(--text); align-items: center; justify-content: center; }
        .nav-toggle svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }

        .hero { min-height: 100svh; padding: 9rem 5% 4rem; display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 4rem; align-items: center; max-width: 1300px; margin: 0 auto; }
        .hero h1 { font-family: var(--serif); font-size: clamp(2.7rem, 6vw, 5rem); font-weight: 400; line-height: 1.08; letter-spacing: -0.01em; margin-bottom: 1.5rem; }
        .hero p { font-size: 1.1rem; color: var(--muted); font-weight: 300; max-width: 480px; margin-bottom: 2.5rem; }
        .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 1rem 2.3rem; font-size: 0.9rem; font-weight: 500; text-decoration: none; transition: all 0.3s; border: 1px solid var(--text); color: var(--text); background: transparent; }
        .btn:hover { background: var(--text); color: var(--bg); }
        .btn-solid { background: var(--text); color: var(--bg); }
        .btn-solid:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
        .hero-img img { width: 100%; height: min(72vh, 620px); object-fit: cover; border-radius: 240px 240px 0 0; }

        .sobre-section { padding: 7rem 5%; background: var(--paper); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .sobre-grid { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 5rem; align-items: center; }
        .sobre-texto h2 { font-family: var(--serif); font-size: clamp(2rem, 4vw, 2.7rem); font-weight: 400; margin-bottom: 1.5rem; }
        .sobre-texto p { color: var(--muted); font-size: 1.08rem; font-weight: 300; max-width: 52ch; }
        .sobre-imagem img { width: 100%; height: 500px; object-fit: cover; filter: grayscale(100%); transition: filter 0.6s; }
        .sobre-imagem:hover img { filter: grayscale(0%); }

        .servicos-section { padding: 7rem 5%; max-width: 960px; margin: 0 auto; }
        h2.titulo { font-family: var(--serif); font-size: clamp(2rem, 4vw, 2.7rem); font-weight: 400; text-align: center; margin-bottom: 3.5rem; }
        .servico-item { display: flex; justify-content: space-between; align-items: baseline; gap: 1.5rem; padding: 1.6rem 0; border-bottom: 1px solid var(--border); transition: padding-left 0.3s; }
        .servico-item:first-child { border-top: 1px solid var(--border); }
        .servico-item:hover { padding-left: 0.8rem; }
        .servico-info h3 { font-size: 1.15rem; font-weight: 500; margin-bottom: 0.2rem; }
        .servico-info p { font-size: 0.92rem; color: var(--muted); max-width: 52ch; }
        .servico-duracao { white-space: nowrap; }
        .servico-item > .servico-info:first-child { flex: 1; }
        .servico-preco { font-family: var(--serif); font-size: 1.5rem; color: var(--accent); white-space: nowrap; }
        .servico-marcar { color: var(--text); text-decoration: none; border-bottom: 1px solid var(--accent); padding-bottom: 0.1rem; font-size: 0.9rem; white-space: nowrap; transition: color 0.3s; }
        .servico-marcar:hover { color: var(--accent); }

        .galeria-section { display: grid; grid-template-columns: repeat(3, 1fr); }
        .galeria-item { height: 420px; overflow: hidden; }
        .galeria-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.8s ease; }
        .galeria-item:hover img { transform: scale(1.04); }

        .equipa-section { padding: 7rem 5%; max-width: 1200px; margin: 0 auto; text-align: center; }
        .grid-equipa { display: flex; justify-content: center; gap: 3rem; flex-wrap: wrap; }
        .membro { width: 250px; display: flex; flex-direction: column; align-items: center; }
        .membro .foto { width: 180px; height: 220px; object-fit: cover; filter: grayscale(100%); transition: filter 0.5s; margin-bottom: 1.2rem; }
        .membro:hover .foto { filter: grayscale(0%); }
        .membro .foto-ini { display: flex; align-items: center; justify-content: center; background: #efede7; font-family: var(--serif); font-size: 4rem; color: var(--accent); filter: none; }
        .membro h4 { font-family: var(--serif); font-size: 1.2rem; font-weight: 600; margin-bottom: 0.2rem; }
        .membro .esp { font-size: 0.88rem; color: var(--accent); margin-bottom: 0.5rem; }
        .membro .insta-link { display: inline-flex; align-items: center; gap: 0.3rem; color: var(--muted); text-decoration: none; font-size: 0.85rem; transition: color 0.3s; border-bottom: 1px solid transparent; margin-top: auto; }
        .membro .insta-link:hover { color: var(--accent); border-bottom-color: var(--accent); }

        .testemunhos-section { padding: 7rem 5%; background: var(--paper); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .testemunhos-inner { max-width: 1200px; margin: 0 auto; }
        .grid-testemunhos { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 3rem; }
        .testemunho p { font-family: var(--serif); font-size: 1.35rem; line-height: 1.45; margin-bottom: 1rem; }
        .testemunho cite { font-style: normal; font-size: 0.9rem; color: var(--muted); }

        .faq-section { padding: 7rem 5%; max-width: 800px; margin: 0 auto; }
        .faq-item { border-bottom: 1px solid var(--border); }
        .faq-item:first-of-type { border-top: 1px solid var(--border); }
        .faq-item summary { padding: 1.4rem 0; cursor: pointer; list-style: none; display: flex; justify-content: space-between; gap: 1rem; font-weight: 500; font-size: 1.05rem; }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item summary::after { content: '+'; color: var(--accent); font-size: 1.5rem; line-height: 1; transition: transform 0.3s; }
        .faq-item[open] summary::after { transform: rotate(45deg); }
        .faq-item p { padding: 0 0 1.5rem; color: var(--muted); max-width: 60ch; }

        footer { background: #141414; color: #fff; padding: 5.5rem 5% 2rem; }
        .footer-content { max-width: 760px; margin: 0 auto 3.5rem; text-align: center; }
        .footer-content h2 { font-family: var(--serif); font-size: clamp(2rem, 4vw, 2.7rem); font-weight: 400; margin-bottom: 1.5rem; }
        .footer-content p { color: #a8a8a8; font-size: 1.05rem; margin-bottom: 0.5rem; }
        .footer-links { display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap; margin-top: 2rem; }
        .footer-links a { color: #fff; text-decoration: none; font-size: 1.05rem; border-bottom: 1px solid var(--accent); padding-bottom: 0.2rem; transition: color 0.3s; }
        .footer-links a:hover { color: var(--accent); }
        .footer-bottom { max-width: 1200px; margin: 0 auto; border-top: 1px solid rgba(255,255,255,0.12); padding-top: 2rem; color: #8a8a8a; font-size: 0.82rem; display: flex; justify-content: space-between; gap: 1rem; }

        .mobile-cta { display: none; position: fixed; bottom: 0; left: 0; width: 100%; background: var(--text); color: var(--bg); text-align: center; padding: 1rem; font-weight: 500; z-index: 1000; text-decoration: none; }

        @media (max-width: 900px) {
            .nav-toggle { display: flex; }
            .nav-cta { display: none; }
            .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; flex-direction: column; gap: 1.2rem; background: var(--bg); padding: 1.5rem 5% 2rem; border-bottom: 1px solid var(--border); }
            nav.open .nav-links { display: flex; }
            .hero { grid-template-columns: 1fr; gap: 2.5rem; padding-top: 7rem; min-height: auto; }
            .hero-img { order: -1; }
            .hero-img img { height: 340px; border-radius: 170px 170px 0 0; }
            .sobre-section, .servicos-section, .equipa-section, .testemunhos-section, .faq-section { padding-top: 4.5rem; padding-bottom: 4.5rem; }
            .sobre-grid { grid-template-columns: 1fr; gap: 2.5rem; }
            .sobre-imagem img { height: 340px; }
            .galeria-section { grid-template-columns: 1fr; }
            .galeria-item { height: 300px; }
            .servico-item { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .footer-bottom { flex-direction: column; text-align: center; }
            .mobile-cta { display: block; }
            footer { padding-bottom: 6rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
            .reveal { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>

    <nav id="navbar">
        <a href="#" class="logo"><?= e($nome) ?></a>
        <div class="nav-right">
            <div class="nav-links" id="nav-links">
                <?php if ($sobre): ?><a href="#sobre">Sobre</a><?php endif; ?>
                <a href="#servicos">Serviços</a>
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
        <div class="hero-text">
            <h1><?= e($heroTitulo) ?></h1>
            <p><?= e($slogan) ?></p>
            <div class="hero-actions">
                <a href="<?= e($linkMarcar) ?>" class="btn btn-solid">Marcar</a>
                <a href="#servicos" class="btn">Ver tabela de serviços</a>
            </div>
        </div>
        <div class="hero-img">
            <img src="<?= e($heroImg) ?>" alt="Interior da barbearia <?= e($nome) ?>">
        </div>
    </header>

    <?php if ($sobre): ?>
    <section id="sobre" class="sobre-section reveal">
        <div class="sobre-grid">
            <div class="sobre-texto">
                <h2>A nossa filosofia</h2>
                <p><?= nl2br(e($sobre)) ?></p>
            </div>
            <div class="sobre-imagem">
                <img src="<?= e($sobreImg) ?>" alt="Ambiente da barbearia" loading="lazy">
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section id="servicos" class="servicos-section reveal">
        <h2 class="titulo">Tabela de serviços</h2>
        <div class="lista-servicos">
            <?php foreach ($servicos as $s): ?>
                <div class="servico-item">
                    <div class="servico-info">
                        <h3><?= e($s['nome']) ?></h3>
                        <p><?= e($s['descricao'] ?? '') ?></p>
                    </div>
                    <div class="servico-info servico-duracao"><p><?= e($s['duracao_minutos']) ?> min</p></div>
                    <div class="servico-preco"><?= number_format((float)$s['preco'], 2, ',', '') ?> €</div>
                    <a class="servico-marcar" href="<?= e($linkMarcar . '&servico=' . (int)$s['id']) ?>" aria-label="Marcar <?= e($s['nome']) ?>">Marcar</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="galeria-section reveal" aria-label="Galeria">
        <?php foreach ($galeriaFinal as $img): ?>
            <div class="galeria-item"><img src="<?= e($img['url']) ?>" alt="<?= e($img['legenda'] ?? 'Trabalho de ' . $nome) ?>" loading="lazy"></div>
        <?php endforeach; ?>
    </section>

    <!-- SECÇÃO DA EQUIPA ATUALIZADA -->
    <?php if (count($equipa) > 0): ?>
    <section id="equipa" class="equipa-section reveal">
        <h2 class="titulo">A equipa</h2>
        <div class="grid-equipa">
            <?php foreach ($equipa as $b): 
                $foto = $b['foto_url'] ?? '';
                if ($foto && !str_starts_with($foto, 'http')) {
                    $foto = '../' . ltrim($foto, '/'); 
                }
            ?>
                <div class="membro">
                    <?php if ($foto): ?>
                        <img class="foto" src="<?= e($foto) ?>" alt="<?= e($b['nome']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="foto foto-ini" role="img" aria-label="<?= e($b['nome']) ?>"><?= e(mb_strtoupper(mb_substr($b['nome'], 0, 1))) ?></div>
                    <?php endif; ?>
                    
                    <h4><?= e($b['nome']) ?></h4>
                    <p class="esp"><?= e($b['especialidades'] ?? 'Barbeiro') ?></p>
                    
                    <!-- BIOGRAFIA CURTA (Limitada a 3 linhas) -->
                    <?php if (!empty($b['bio'])): ?>
                        <p style="font-size: 0.85rem; color: var(--muted); margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; text-align: center;">
                            <?= nl2br(e($b['bio'])) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($b['instagram'])): ?>
                        <a href="<?= e($b['instagram']) ?>" target="_blank" rel="noopener" class="insta-link">
                            @Instagram
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- REVIEWS DINÂMICAS DO GOOGLE -->
    <section class="testemunhos-section reveal">
        <div class="testemunhos-inner">
            <h2 class="titulo">O que dizem os clientes</h2>
            <div class="grid-testemunhos">
                <?php if (!empty($googleReviews)): ?>
                    <?php foreach (array_slice($googleReviews, 0, 3) as $review): 
                        $rating = (int)($review['rating'] ?? 5);
                        $autor = $review['author_name'] ?? 'Cliente';
                        $texto = $review['text'] ?? '(Sem comentário)';
                    ?>
                        <figure class="testemunho">
                            <blockquote><p>“<?= e($texto) ?>”</p></blockquote>
                            <figcaption style="display:flex; justify-content:space-between; align-items:center; margin-top: 1rem;">
                                <cite><?= e($autor) ?></cite>
                                <span style="color: var(--accent); font-size: 1.1rem; letter-spacing: 2px;">
                                    <?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?>
                                </span>
                            </figcaption>
                        </figure>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#9ca3af; padding:2rem; width: 100%;">As avaliações do Google estarão disponíveis em breve.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="faq" class="faq-section reveal">
        <h2 class="titulo">Perguntas frequentes</h2>
        <?php foreach ($faqFinal as $f): ?>
            <details class="faq-item"><summary><?= e($f['pergunta']) ?></summary><p><?= e($f['resposta']) ?></p></details>
        <?php endforeach; ?>
    </section>

    <footer id="contactos">
        <div class="footer-content reveal">
            <h2>Visite-nos</h2>
            <p><?= e($morada) ?></p>
            <p><?= e($dias) ?> · <?= e($abre) ?> — <?= e($fecha) ?></p>
            <div class="footer-links">
                <?php if ($telExibe): ?><a href="tel:<?= e($telemovel ?? $telExibe) ?>"><?= e($telExibe) ?></a><?php endif; ?>
                <?php if ($email): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
                <a href="<?= e($linkMarcar) ?>">Marcar online</a>
                <?php if ($temWhatsapp): ?><a href="<?= e($waMarcar) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                <a href="<?= e($mapaLink) ?>" target="_blank" rel="noopener">Ver no mapa</a>
                <?php if ($instagram): ?><a href="<?= e($instagram) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
                <?php if ($facebook): ?><a href="<?= e($facebook) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e($nome) ?></p>
            <p>Powered by BarberFlow</p>
        </div>
    </footer>

    <a href="<?= e($linkMarcar) ?>" class="mobile-cta">Marcar</a>

    <script>
        const navbar = document.getElementById('navbar');
        const toggle = document.getElementById('nav-toggle');
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
</body>
</html>