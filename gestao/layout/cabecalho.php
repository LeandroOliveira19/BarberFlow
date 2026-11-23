<?php
// Estrutura da gestão: <head> e abertura do <body> (incluído por gestao/index.php)
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarberFlow — Gestão</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* CSS GLOBAL DO PAINEL — visual "tipo Apple" em dourado e preto */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --fundo: #000;
            --superficie: #0e0e0e;
            --superficie-2: #161617;
            --texto: #f5f5f7;
            --texto-2: #86868b;
            --linha: rgba(255,255,255,0.08);
            --gold: #cda47c;
            --raio: 28px;
            --raio-campo: 14px;
            /* nomes antigos, usados pelos módulos */
            --card: var(--superficie-2);
            --border: var(--linha);
            --muted: var(--texto-2);
        }
        body { font-family: 'Inter', system-ui, sans-serif; letter-spacing: -0.011em; -webkit-font-smoothing: antialiased; background: var(--fundo); color: var(--texto); display: flex; min-height: 100vh; }

        /* Menu lateral: grupos com ícones, contadores e o utilizador no fundo (layout/menu.php) */
        .sidebar { width: 250px; flex: none; position: sticky; top: 0; height: 100vh; overflow-y: auto; background: rgba(14,14,14,0.85); -webkit-backdrop-filter: blur(20px); backdrop-filter: blur(20px); border-right: 1px solid var(--linha); padding: 1.4rem 0.8rem 0; display: flex; flex-direction: column; }
        .logo { font-family: 'Cinzel', serif; font-size: 1.3rem; color: var(--gold); text-decoration: none; padding: 0.2rem 0.8rem 0.6rem; }
        .sidebar nav { display: flex; flex-direction: column; gap: 0.1rem; }
        .menu-grupo { font-size: 0.68rem; font-weight: 600; color: var(--texto-2); text-transform: uppercase; letter-spacing: 0.06em; padding: 1.1rem 0.8rem 0.35rem; }
        .sidebar nav a { display: flex; align-items: center; gap: 0.7rem; color: var(--texto); opacity: 0.78; text-decoration: none; padding: 0.5rem 0.8rem; border-radius: 10px; transition: background 0.2s, opacity 0.2s; font-size: 0.9rem; }
        .sidebar nav a:hover { background: rgba(255,255,255,0.05); opacity: 1; }
        .sidebar nav a.active { background: rgba(205,164,124,0.14); color: var(--gold); opacity: 1; }
        .menu-ico { width: 18px; height: 18px; flex: none; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
        .contador { margin-left: auto; background: var(--gold); color: #000; font-size: 0.7rem; font-weight: 600; border-radius: 999px; padding: 0.05rem 0.45rem; }
        .menu-eu { margin-top: auto; position: sticky; bottom: 0; display: flex; align-items: center; gap: 0.7rem; padding: 0.9rem 0.6rem; border-top: 1px solid var(--linha); background: rgba(14,14,14,0.95); }
        .menu-avatar { width: 34px; height: 34px; border-radius: 50%; flex: none; object-fit: cover; display: grid; place-items: center; background: linear-gradient(135deg, var(--gold), #7a5a37); color: #000; font-weight: 600; font-size: 0.8rem; }
        .menu-eu-txt { min-width: 0; }
        .menu-eu-txt b { display: block; font-size: 0.85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .menu-eu-txt small { display: block; color: var(--texto-2); font-size: 0.74rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .menu-sair { margin-left: auto; color: var(--texto-2); padding: 0.4rem; border-radius: 999px; display: grid; place-items: center; transition: color 0.2s, background 0.2s; }
        .menu-sair:hover { color: #f87171; background: rgba(248,113,113,0.1); }

        /* Cabeçalho fixo de vidro fosco (gestao/index.php) */
        .area { flex: 1; min-width: 0; }
        .topo { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; gap: 1rem; padding: 0.85rem 3rem; background: rgba(0,0,0,0.72); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); border-bottom: 1px solid var(--linha); }
        .topo-caminho { color: var(--texto-2); font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .topo-caminho b { color: var(--texto); font-weight: 500; }
        .topo-pilula { margin-left: auto; color: var(--texto); background: var(--superficie-2); border: 1px solid var(--linha); border-radius: 999px; padding: 0.4rem 0.95rem; font-size: 0.82rem; font-weight: 500; text-decoration: none; white-space: nowrap; transition: border-color 0.2s; }
        .topo-pilula:hover { border-color: var(--gold); color: var(--gold); }

        /* Layout Principal: título grande e apertado */
        .main-content { padding: 2.4rem 3rem 4rem; min-width: 0; max-width: 1400px; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; gap: 1rem; }
        .header h1 { font-size: 2.4rem; font-weight: 700; letter-spacing: -0.035em; line-height: 1.08; color: var(--texto); }
        .header p { color: var(--texto-2); margin-top: 0.4rem; }

        /* Controlo segmentado (filtros: pedidos, stock, agenda) */
        .segmentado { display: inline-flex; flex-wrap: wrap; background: #1d1d1f; border-radius: 10px; padding: 3px; gap: 2px; }
        .segmentado a { color: var(--texto-2); text-decoration: none; font-size: 0.82rem; font-weight: 500; padding: 0.4rem 0.9rem; border-radius: 8px; transition: color 0.2s, background 0.2s; white-space: nowrap; }
        .segmentado a:hover { color: var(--texto); }
        .segmentado a.on { background: #3a3a3c; color: var(--texto); box-shadow: 0 1px 2px rgba(0,0,0,0.4); }

        /* Lista agrupada tipo iOS (dashboard; as tabelas seguem o mesmo aspeto) */
        .lista { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: 20px; overflow: hidden; }
        .lista-linha { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1.2rem; position: relative; color: var(--texto); text-decoration: none; transition: background 0.2s; }
        .lista-linha + .lista-linha::before { content: ''; position: absolute; top: 0; left: 1.2rem; right: 0; height: 1px; background: var(--linha); }
        a.lista-linha:hover { background: rgba(255,255,255,0.03); }
        .lista-linha .txt { min-width: 0; }
        .lista-linha .txt b { display: block; font-weight: 500; font-size: 0.93rem; }
        .lista-linha .txt small { color: var(--texto-2); font-size: 0.8rem; }
        .lista-linha .dir { margin-left: auto; display: flex; align-items: center; gap: 0.8rem; color: var(--texto-2); font-size: 0.85rem; flex: none; }
        .lista-vazia { color: var(--texto-2); font-size: 0.9rem; padding: 1.1rem 1.2rem; }

        /* CSS DO DASHBOARD (Estatísticas) */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
        .stat-card { background: var(--superficie-2); padding: 1.6rem 1.8rem; border-radius: var(--raio); border: 1px solid var(--linha); }
        .stat-card h3 { font-size: 0.82rem; font-weight: 500; color: var(--texto-2); margin-bottom: 0.3rem; }
        .stat-card .number { font-size: 2.6rem; font-weight: 600; letter-spacing: -0.04em; color: var(--texto); }
        .welcome-box { background: linear-gradient(var(--superficie-2), var(--fundo)); padding: 3rem; border-radius: var(--raio); border: 1px solid var(--linha); text-align: center; }
        .welcome-box h2 { font-family: 'Cinzel', serif; color: var(--gold); margin-bottom: 1rem; }
        .welcome-box p { color: var(--texto-2); max-width: 600px; margin: 0 auto; }

        /* Tabelas Partilhadas */
        .table-container { background: var(--superficie-2); border-radius: 20px; border: 1px solid var(--linha); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: transparent; padding: 0.9rem 1.2rem 0.6rem; color: var(--texto-2); font-size: 0.75rem; font-weight: 500; border-bottom: 1px solid var(--linha); }
        td { padding: 0.85rem 1.2rem; border-bottom: 1px solid var(--linha); }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background 0.2s; }
        tbody tr:hover { background: rgba(255,255,255,0.03); }

        /* Formulários Partilhados */
        .input-group { margin-bottom: 1rem; }
        .input-group label { display: block; font-size: 0.85rem; color: var(--texto-2); margin-bottom: 0.35rem; }
        .input-group input, .input-group textarea, .input-group select { width: 100%; background: var(--fundo); border: 1px solid rgba(255,255,255,0.12); color: var(--texto); padding: 0.7rem 0.9rem; border-radius: var(--raio-campo); font: inherit; }
        .input-group input:focus, .input-group textarea:focus, .input-group select:focus { outline: none; border-color: var(--gold); }
        .btn-submit { width: 100%; background: var(--gold); color: #000; border: none; padding: 0.8rem; border-radius: 999px; font: inherit; font-weight: 500; cursor: pointer; margin-top: 1rem; transition: background 0.3s; }
        .btn-submit:hover { background: #e0be9a; }

        /* Botões de Ação */
        .btn-acao { text-decoration: none; color: var(--texto); background: var(--superficie); border: 1px solid rgba(255,255,255,0.12); padding: 0.4rem 0.95rem; border-radius: 999px; font: inherit; font-size: 0.85rem; cursor: pointer; transition: border-color 0.3s, color 0.3s; display: inline-block; }
        .btn-acao:hover { border-color: var(--gold); color: var(--gold); }
        .btn-perigo:hover { border-color: #f87171; color: #f87171; }

        /* Selos de estado */
        .badge { padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge.ok { color: #4ade80; background: rgba(34, 197, 94, 0.15); }
        .badge.low { color: #f87171; background: rgba(239, 68, 68, 0.15); }
        .badge.pendente { color: #fbbf24; background: rgba(251, 191, 36, 0.15); }

        /* Cards que abrem ao clicar (stock, bancadas, pedidos) */
        .grupo-cards { margin-bottom: 1.75rem; }
        .grupo-cards-titulo { display: flex; align-items: baseline; gap: 0.6rem; font-size: 1.1rem; font-weight: 600; letter-spacing: -0.02em; color: var(--texto); margin-bottom: 0.75rem; padding-bottom: 0.4rem; border-bottom: 1px solid var(--border); }
        .grupo-cards-titulo .n { font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--muted); }
        .grupo-cards-titulo .n .falta { color: #f87171; }
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 0.75rem; align-items: start; }
        .item-card { background: var(--card); border: 1px solid var(--border); border-left: 3px solid #4ade80; border-radius: 20px; }
        .item-card.falta { border-left-color: #f87171; }
        .item-card[open] { border-color: rgba(205,164,124,0.45); }
        .item-card summary { list-style: none; cursor: pointer; padding: 0.9rem 1.1rem; display: flex; flex-direction: column; gap: 0.4rem; user-select: none; }
        .item-card summary::-webkit-details-marker { display: none; }
        .item-card summary:hover .item-nome { color: var(--gold); }
        .item-nome { font-weight: 500; color: var(--texto); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: 0.2s; }
        .item-resumo { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; color: var(--muted); font-size: 0.85rem; }
        .item-resumo .selos { display: flex; gap: 0.3rem; flex-wrap: wrap; justify-content: flex-end; }
        .item-corpo { padding: 0.2rem 1.1rem 1.1rem; border-top: 1px solid var(--border); }
        .item-dados { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 0.6rem; margin: 0.8rem 0; }
        .item-dados dt { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .item-dados dd { color: var(--texto); font-size: 0.95rem; }
        .item-acoes { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .sem-cards { color: var(--muted); text-align: center; padding: 3rem 1rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--raio); }

        /* Alertas */
        .alerta { padding: 1rem; border-radius: var(--raio-campo); margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alerta.sucesso { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .alerta.erro { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* Responsivo */
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: static; padding: 0.7rem 1rem; flex-direction: row; align-items: center; gap: 0.8rem; overflow-x: auto; overflow-y: hidden; border-right: none; border-bottom: 1px solid var(--linha); }
            .logo { padding: 0; font-size: 1.1rem; }
            .sidebar nav { flex-direction: row; }
            .menu-grupo { display: none; }
            .sidebar nav a { white-space: nowrap; padding: 0.45rem 0.75rem; }
            .menu-eu { margin: 0 0 0 auto; position: static; border-top: none; padding: 0; background: none; }
            .menu-eu-txt { display: none; }
            .topo { padding: 0.75rem 1.2rem; }
            .main-content { padding: 1.5rem 1.2rem 3rem; }
            .header h1 { font-size: 1.9rem; }
        }
        /* Mensagens do index.php (flash) */
        .flashes { position: fixed; top: 5rem; right: 1rem; z-index: 1000; display: flex; flex-direction: column; gap: 0.5rem; max-width: 360px; }
        .flash { padding: 0.8rem 1rem; border-radius: var(--raio-campo); border: 1px solid; background: var(--card); font-size: 0.9rem; }
        .flash-sucesso { border-color: #4ade80; color: #4ade80; }
        .flash-erro { border-color: #f87171; color: #f87171; }
        .flash-aviso { border-color: #fbbf24; color: #fbbf24; }
    </style>
</head>
<body>

