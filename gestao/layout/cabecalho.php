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
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Outfit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        /* CSS GLOBAL DO PAINEL */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { 
            --gold: #cda47c; 
            --card: #121212; 
            --border: rgba(255,255,255,0.08); 
            --muted: #9ca3af; 
        }
        body { font-family: 'Outfit', sans-serif; background: #0a0a0a; color: #f8f9fa; display: flex; min-height: 100vh; }
        
        /* Sidebar (Menu) */
        .sidebar { width: 260px; background: var(--card); border-right: 1px solid var(--border); padding: 2rem; display: flex; flex-direction: column; flex: none; }
        .logo { font-family: 'Cinzel', serif; font-size: 1.5rem; color: var(--gold); text-decoration: none; margin-bottom: 3rem; text-align: center; }
        .sidebar nav { display: flex; flex-direction: column; gap: 0.5rem; }
        .sidebar nav a { color: var(--muted); text-decoration: none; padding: 0.8rem 1rem; border-radius: 6px; transition: 0.3s; font-size: 0.95rem; }
        .sidebar nav a:hover, .sidebar nav a.active { background: rgba(205,164,124,0.1); color: var(--gold); }

        /* Layout Principal */
        .main-content { flex: 1; padding: 3rem; min-width: 0; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header h1 { font-family: 'Cinzel', serif; font-size: 2rem; color: #fff; }
        .header p { color: var(--muted); margin-top: 0.4rem; }
        .btn-logout { color: #f87171; text-decoration: none; font-size: 0.9rem; border: 1px solid rgba(248,113,113,0.3); padding: 0.5rem 1rem; border-radius: 6px; transition: 0.3s; }
        .btn-logout:hover { background: rgba(248,113,113,0.1); }

        /* CSS DO DASHBOARD (Estatísticas) */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
        .stat-card { background: var(--card); padding: 2rem; border-radius: 12px; border: 1px solid var(--border); border-left: 4px solid var(--gold); }
        .stat-card h3 { font-size: 0.9rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem; }
        .stat-card .number { font-size: 2.5rem; font-weight: 600; color: #fff; }
        .welcome-box { background: linear-gradient(135deg, #1e1e1e, #121212); padding: 3rem; border-radius: 12px; border: 1px solid var(--border); text-align: center; }
        .welcome-box h2 { font-family: 'Cinzel', serif; color: var(--gold); margin-bottom: 1rem; }
        .welcome-box p { color: var(--muted); max-width: 600px; margin: 0 auto; }

        /* Tabelas Partilhadas */
        .table-container { background: var(--card); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #1a1a1a; padding: 1rem; color: var(--muted); font-size: 0.85rem; text-transform: uppercase; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }

        /* Formulários Partilhados */
        .input-group { margin-bottom: 1rem; }
        .input-group label { display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 0.3rem; }
        .input-group input, .input-group textarea, .input-group select { width: 100%; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 0.6rem; border-radius: 6px; font-family: inherit; }
        .input-group input:focus, .input-group select:focus { outline: none; border-color: var(--gold); }
        .btn-submit { width: 100%; background: var(--gold); color: #0a0a0a; border: none; padding: 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 1rem; transition: 0.3s; }
        .btn-submit:hover { background: #e0be9a; }

        /* Botões de Ação */
        .btn-acao { text-decoration: none; color: #fff; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.85rem; cursor: pointer; transition: 0.3s; display: inline-block; }
        .btn-acao:hover { border-color: var(--gold); color: var(--gold); }
        .btn-perigo:hover { border-color: #f87171; color: #f87171; }

        /* Selos de estado */
        .badge { padding: 0.22rem 0.55rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge.ok { color: #4ade80; background: rgba(34, 197, 94, 0.15); }
        .badge.low { color: #f87171; background: rgba(239, 68, 68, 0.15); }
        .badge.pendente { color: #fbbf24; background: rgba(251, 191, 36, 0.15); }

        /* Cards que abrem ao clicar (stock, bancadas, pedidos) */
        .grupo-cards { margin-bottom: 1.75rem; }
        .grupo-cards-titulo { display: flex; align-items: baseline; gap: 0.6rem; font-family: 'Cinzel', serif; font-size: 1rem; color: var(--gold); margin-bottom: 0.75rem; padding-bottom: 0.4rem; border-bottom: 1px solid var(--border); }
        .grupo-cards-titulo .n { font-family: 'Outfit', sans-serif; font-size: 0.8rem; color: var(--muted); }
        .grupo-cards-titulo .n .falta { color: #f87171; }
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 0.75rem; align-items: start; }
        .item-card { background: var(--card); border: 1px solid var(--border); border-left: 3px solid #4ade80; border-radius: 10px; }
        .item-card.falta { border-left-color: #f87171; }
        .item-card[open] { border-color: rgba(205,164,124,0.45); }
        .item-card summary { list-style: none; cursor: pointer; padding: 0.8rem 1rem; display: flex; flex-direction: column; gap: 0.4rem; user-select: none; }
        .item-card summary::-webkit-details-marker { display: none; }
        .item-card summary:hover .item-nome { color: var(--gold); }
        .item-nome { font-weight: 500; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: 0.2s; }
        .item-resumo { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; color: var(--muted); font-size: 0.85rem; }
        .item-resumo .selos { display: flex; gap: 0.3rem; flex-wrap: wrap; justify-content: flex-end; }
        .item-corpo { padding: 0.2rem 1rem 1rem; border-top: 1px solid var(--border); }
        .item-dados { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 0.6rem; margin: 0.8rem 0; }
        .item-dados dt { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .item-dados dd { color: #fff; font-size: 0.95rem; }
        .item-acoes { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .sem-cards { color: var(--muted); text-align: center; padding: 3rem 1rem; background: var(--card); border: 1px solid var(--border); border-radius: 12px; }

        /* Alertas */
        .alerta { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alerta.sucesso { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .alerta.erro { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* Responsivo */
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; padding: 1rem 1.2rem; flex-direction: row; align-items: center; gap: 1rem; overflow-x: auto; }
            .logo { margin: 0; font-size: 1.2rem; }
            .sidebar nav { flex-direction: row; }
            .sidebar nav a { white-space: nowrap; padding: 0.5rem 0.8rem; }
            .main-content { padding: 1.5rem 1.2rem; }
        }
        /* Mensagens do index.php (flash) */
        .flashes { position: fixed; top: 5rem; right: 1rem; z-index: 1000; display: flex; flex-direction: column; gap: 0.5rem; max-width: 360px; }
        .flash { padding: 0.8rem 1rem; border-radius: 8px; border: 1px solid; background: var(--card); font-size: 0.9rem; }
        .flash-sucesso { border-color: #4ade80; color: #4ade80; }
        .flash-erro { border-color: #f87171; color: #f87171; }
        .flash-aviso { border-color: #fbbf24; color: #fbbf24; }
    </style>
</head>
<body>

