<?php
require_once __DIR__ . '/config/db.php';

// Buscar Nome do Site
$stmt = $pdo->prepare("SELECT meta_value FROM settings WHERE meta_key = 'site_name'");
$stmt->execute();
$siteName = $stmt->fetchColumn() ?: 'Arena Stream';

// Buscar Jogos Agendados Ativos
$sqlGames = "SELECT g.*, c.name AS channel_name, c.logo AS channel_logo 
             FROM games g 
             LEFT JOIN channels c ON g.channel_id = c.id 
             WHERE g.status = 'ativo' 
             ORDER BY g.created_at DESC";
$games = $pdo->query($sqlGames)->fetchAll(PDO::FETCH_ASSOC);

// Buscar Canais Esportivos Cadastrados
$sqlChannels = "SELECT id, name, logo, group_name FROM channels WHERE is_sports = 1 ORDER BY name ASC";
$channels = $pdo->query($sqlChannels)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade de Programação | <?php echo htmlspecialchars($siteName); ?></title>
    
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #07040e;
            --bg-deep: #0a0615;
            --bg-card: rgba(22, 14, 43, 0.6);
            --color-purple: #7c3aed;
            --color-purple-light: #a78bfa;
            --color-red: #ef4444;
            --color-success: #10b981;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border: rgba(124, 58, 237, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: radial-gradient(circle at top, #1b0c30 0%, var(--bg-dark) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Topbar (Overlay Transparente) */
        .topbar {
            background: transparent;
            padding: 25px 8%;
            position: absolute;
            width: 100%;
            top: 0;
            left: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--color-purple), var(--color-red));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
            box-shadow: 0 0 15px rgba(124, 58, 237, 0.4);
        }

        .brand-text h2 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            background: linear-gradient(to right, #fff, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-text span {
            font-size: 10px;
            font-weight: 600;
            color: var(--color-red);
            letter-spacing: 2px;
            text-transform: uppercase;
            display: block;
            margin-top: -3px;
        }

        .btn-admin {
            background: rgba(124, 58, 237, 0.15);
            color: var(--color-purple-light);
            border: 1px solid var(--border);
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s;
        }

        .btn-admin:hover {
            background: var(--color-purple);
            color: #fff;
            box-shadow: 0 0 15px rgba(124, 58, 237, 0.3);
        }

        /* Hero Banner Premium (Full-Bleed Estilo DAZN) */
        .hero-banner {
            width: 100vw;
            margin: 0;
            padding: 220px 8% 80px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-height: 580px;
            position: relative;
            background: radial-gradient(circle at 75% 40%, rgba(30, 80, 110, 0.45) 0%, rgba(10, 6, 21, 0) 65%),
                        radial-gradient(circle at 20% 50%, rgba(124, 58, 237, 0.15) 0%, rgba(10, 6, 21, 0) 70%),
                        linear-gradient(180deg, #09121e 0%, var(--bg-dark) 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
        }
        .hero-banner-content {
            flex: none;
            width: 50%;
            max-width: 600px;
            text-align: left;
            z-index: 2;
            position: relative;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ff6b6b;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 22px;
            animation: blink 2s infinite;
        }

        .hero-banner-content h1 {
            font-size: 48px;
            font-weight: 900;
            line-height: 1.15;
            letter-spacing: -1.2px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #ffffff 40%, #c084fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-banner-content p {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.65;
            margin-bottom: 32px;
        }

        .hero-actions {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-hero-primary {
            background: #ffffff;
            color: #07040e;
            border: none;
            padding: 14px 32px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.15);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-hero-primary:hover {
            background: #f3f4f6;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 30px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.25s;
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .hero-banner-image {
            position: absolute;
            top: 0;
            right: 0;
            width: 55%;
            height: 100%;
            z-index: 1;
            display: flex;
            justify-content: flex-end;
            align-items: flex-end;
            overflow: hidden;
        }

        .hero-banner-image img {
            height: 100%;
            width: auto;
            object-fit: cover;
            border-radius: 0;
            mask-image: linear-gradient(to left, rgba(0,0,0,1) 80%, rgba(0,0,0,0) 100%);
            -webkit-mask-image: linear-gradient(to left, rgba(0,0,0,1) 80%, rgba(0,0,0,0) 100%);
            filter: drop-shadow(0 0 50px rgba(0, 0, 0, 0.5));
            animation: floatImage 6s ease-in-out infinite;
        }

        @keyframes floatImage {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0px); }
        }

        /* Carrossel de Ligas Premium */
        .leagues-container {
            width: 100%;
            margin: 15px 0 40px;
            padding: 0 8%;
            overflow-x: auto;
            scrollbar-width: none;
            box-sizing: border-box;
        }

        .leagues-container::-webkit-scrollbar {
            display: none;
        }

        .leagues-wrapper {
            display: flex;
            gap: 12px;
            white-space: nowrap;
        }

        .league-chip {
            background: rgba(22, 14, 43, 0.5);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .league-chip:hover {
            background: rgba(124, 58, 237, 0.15);
            border-color: rgba(124, 58, 237, 0.3);
            color: #fff;
        }

        .league-chip.active {
            background: var(--color-purple);
            border-color: var(--color-purple);
            color: #fff;
            box-shadow: 0 0 15px rgba(124, 58, 237, 0.35);
        }

        /* Badge de Horário Amarelo */
        .badge-time {
            background: linear-gradient(135deg, #facc15, #eab308);
            color: #000;
            font-size: 10px;
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 6px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 2px 8px rgba(234, 179, 8, 0.3);
        }

        @media (max-width: 992px) {
            .topbar {
                position: relative !important;
                background: #09121e !important;
                padding: 15px 20px !important;
            }
            
            .hero-banner {
                flex-direction: column;
                text-align: center;
                gap: 40px;
                padding: 40px 20px !important;
                width: 100% !important;
            }

            .hero-banner-content {
                max-width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .hero-banner-content h1 {
                font-size: 36px;
            }

            .hero-actions {
                justify-content: center;
            }

            .hero-banner-image {
                justify-content: center;
            }

            .hero-banner-image img {
                max-width: 340px;
                mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 75%, rgba(0,0,0,0) 100%);
                -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 75%, rgba(0,0,0,0) 100%);
            }
        }

        /* Container Tabs Toggle */
        .tabs-container {
            width: 100%;
            margin: 0 0 40px;
            padding: 0 8%;
            box-sizing: border-box;
        }

        .tab-switcher {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 35px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding-bottom: 15px;
        }

        .tab-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 700;
            padding: 8px 24px;
            cursor: pointer;
            position: relative;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn.active {
            color: #fff;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -16px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, var(--color-purple), var(--color-red));
            border-radius: 10px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.4s ease-out forwards;
        }

        /* Grid de Jogos Agendados */
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 25px;
        }

        .game-ticket {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            transition: transform 0.25s, border-color 0.25s, box-shadow 0.25s;
            position: relative;
        }

        .game-ticket:hover {
            transform: translateY(-4px);
            border-color: rgba(124, 58, 237, 0.35);
            box-shadow: 0 15px 35px rgba(124, 58, 237, 0.15);
        }

        .ticket-header {
            padding: 16px 20px;
            background: rgba(7, 4, 14, 0.4);
            border-bottom: 1px solid rgba(255,255,255,0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .broadcaster {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .broadcaster-logo {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.05);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .broadcaster-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .broadcaster-name {
            font-size: 11px;
            font-weight: 700;
            color: var(--color-purple-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-live {
            background: var(--color-red);
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 4px;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 4px;
            animation: blink 1.5s infinite;
        }

        .ticket-body {
            padding: 24px 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .match-title {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .match-desc {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .ticket-footer {
            padding: 20px;
            background: rgba(7, 4, 14, 0.4);
            border-top: 1px solid rgba(255,255,255,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .price-box {
            display: flex;
            flex-direction: column;
        }

        .price-label {
            font-size: 9px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .price-value {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
        }

        .price-value.free {
            color: var(--color-success);
        }

        .btn-ticket {
            background: linear-gradient(135deg, var(--color-purple), var(--color-purple-light));
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-ticket:hover {
            transform: scale(1.03);
            box-shadow: 0 0 15px rgba(124, 58, 237, 0.35);
        }

        .btn-ticket.buy {
            background: linear-gradient(135deg, var(--color-purple), var(--color-red));
        }

        .btn-ticket.buy:hover {
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.35);
        }

        /* Grid de Canais Cadastrados */
        .channels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }

        .channel-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            transition: all 0.25s;
        }

        .channel-card:hover {
            transform: translateY(-3px);
            border-color: rgba(124, 58, 237, 0.3);
            box-shadow: 0 12px 25px rgba(124, 58, 237, 0.1);
        }

        .channel-logo {
            width: 55px;
            height: 55px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 12px;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .channel-logo img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
        }

        .channel-logo i {
            font-size: 22px;
            color: var(--color-purple-light);
        }

        .channel-name {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .channel-group {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }

        .btn-play-channel {
            background: rgba(255,255,255,0.05);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-play-channel:hover {
            background: var(--color-purple);
            border-color: var(--color-purple);
            box-shadow: 0 0 10px rgba(124, 58, 237, 0.3);
        }

        /* Modal de Vídeo Flutuante */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(7, 4, 14, 0.85);
            backdrop-filter: blur(10px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .modal.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-content {
            background: #000;
            border-radius: 20px;
            width: 90%;
            max-width: 900px;
            aspect-ratio: 16/9;
            position: relative;
            border: 1px solid rgba(124, 58, 237, 0.25);
            box-shadow: 0 25px 50px rgba(0,0,0,0.6);
            overflow: hidden;
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .modal.active .modal-content {
            transform: scale(1);
        }

        .modal-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .btn-close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 32px;
            height: 32px;
            background: rgba(0,0,0,0.6);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-close-modal:hover {
            background: var(--color-red);
            border-color: var(--color-red);
            transform: rotate(90deg);
        }

        /* Estado Vazio */
        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 40px;
            color: rgba(124, 58, 237, 0.2);
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 13px;
        }

        /* Animações */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    
    <!-- Topbar -->
    <header class="topbar">
        <a href="index.php" class="brand">
            <img src="assets/images/logo.png" alt="Arena Esportiva" style="height: 150px; width: auto; object-fit: contain;">
        </a>
        
        <a href="admin.php" class="btn-admin">
            <i class="fa-solid fa-user-gear"></i> Painel Admin
        </a>
    </header>

    <!-- Hero Banner Premium -->
    <section class="hero-banner">
        <div class="hero-banner-content">
            <div class="hero-badge">
                <i class="fa-solid fa-bolt"></i> Cobertura Exclusiva Ao Vivo
            </div>
            <h1>Melhores Momentos e Jogos Grátis e Premium</h1>
            <p>Gols, destaques e grandes momentos da LALIGA, Brasileirão, UEFA Champions League, Premier League, Libertadores e muito mais com a melhor qualidade de transmissão.</p>
            <div class="hero-actions">
                <button onclick="scrollToGames()" class="btn-hero-primary">
                    <i class="fa-solid fa-circle-play"></i> Assistir Agora
                </button>
                <a href="admin.php" class="btn-hero-secondary">
                    <i class="fa-solid fa-user-gear"></i> Painel Admin
                </a>
            </div>
        </div>
        <div class="hero-banner-image">
            <img src="assets/images/football_stars_hero.png" alt="Craques do Futebol">
        </div>
    </section>

    <!-- Leagues Horizontal Switcher -->
    <div class="leagues-container" id="leagues-anchor">
        <div class="leagues-wrapper">
            <div class="league-chip active" onclick="filterByLeague('all')">
                <i class="fa-solid fa-trophy"></i> Todos os Jogos
            </div>
            <div class="league-chip" onclick="filterByLeague('champions')">
                <i class="fa-solid fa-star"></i> Champions League
            </div>
            <div class="league-chip" onclick="filterByLeague('brasileirao')">
                <i class="fa-solid fa-shield-halved"></i> Brasileirão
            </div>
            <div class="league-chip" onclick="filterByLeague('laliga')">
                <i class="fa-solid fa-futbol"></i> LaLiga
            </div>
            <div class="league-chip" onclick="filterByLeague('premier')">
                <i class="fa-solid fa-crown"></i> Premier League
            </div>
            <div class="league-chip" onclick="filterByLeague('libertadores')">
                <i class="fa-solid fa-earth-americas"></i> Libertadores
            </div>
        </div>
      <!-- Próximas Transmissões Cadastradas -->
    <div class="tabs-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 15px;">
            <h2 style="font-size: 22px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-calendar-days" style="color: var(--color-purple-light);"></i> Próximas Transmissões Cadastradas
            </h2>
        </div>

        <!-- Jogos Agendados -->
        <div id="tab-content-games" class="tab-content active">
            <?php if (empty($games)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    <h3>Nenhum Jogo Agendado</h3>
                    <p>Não há partidas ativas programadas para venda ou transmissão neste momento. Volte mais tarde!</p>
                </div>
            <?php else: ?>
                <div class="games-grid">
                    <?php foreach ($games as $game): ?>
                        <?php
                        // 1. Classificação de Liga do Jogo
                        $gameLeague = 'other';
                        $searchStr = mb_strtolower($game['name'] . ' ' . $game['description'], 'UTF-8');
                        if (str_contains($searchStr, 'champions') || str_contains($searchStr, 'uefa')) {
                            $gameLeague = 'champions';
                        } else if (str_contains($searchStr, 'brasileirao') || str_contains($searchStr, 'brasileirão') || str_contains($searchStr, 'série a')) {
                            $gameLeague = 'brasileirao';
                        } else if (str_contains($searchStr, 'laliga') || str_contains($searchStr, 'la liga') || str_contains($searchStr, 'espanhol')) {
                            $gameLeague = 'laliga';
                        } else if (str_contains($searchStr, 'premier') || str_contains($searchStr, 'inglês') || str_contains($searchStr, 'ingles')) {
                            $gameLeague = 'premier';
                        } else if (str_contains($searchStr, 'libertadores') || str_contains($searchStr, 'conmebol')) {
                            $gameLeague = 'libertadores';
                        }

                        // 2. Formatação Premium de Data/Hora (Estilo DAZN)
                        $badgeTime = '';
                        $formattedDate = '';
                        if (!empty($game['game_date'])) {
                            try {
                                $date = new DateTime($game['game_date']);
                                $now = new DateTime();
                                
                                // Formatar data padrão
                                $formattedDate = $date->format('d/m') . ' às ' . $date->format('H:i');
                                
                                // Diferença em dias absolutos
                                $todayStr = $now->format('Y-m-d');
                                $gameDayStr = $date->format('Y-m-d');
                                
                                if ($todayStr === $gameDayStr) {
                                    $badgeTime = 'HOJE ' . $date->format('H:i');
                                } else {
                                    $tomorrow = clone $now;
                                    $tomorrow->modify('+1 day');
                                    if ($tomorrow->format('Y-m-d') === $gameDayStr) {
                                        $badgeTime = 'AMANHÃ ' . $date->format('H:i');
                                    } else {
                                        $daysOfWeek = [
                                            0 => 'DOM',
                                            1 => 'SEG',
                                            2 => 'TER',
                                            3 => 'QUA',
                                            4 => 'QUI',
                                            5 => 'SEX',
                                            6 => 'SÁB'
                                        ];
                                        $badgeTime = $daysOfWeek[(int)$date->format('w')] . ' ' . $date->format('H:i');
                                    }
                                }
                            } catch (Exception $e) {
                                $badgeTime = 'EM BREVE';
                            }
                        } else {
                            $badgeTime = 'NO AR';
                        }
                        ?>
                        <div class="game-ticket" data-league="<?php echo $gameLeague; ?>">
                            <div class="ticket-header">
                                <div class="broadcaster">
                                    <div class="broadcaster-logo">
                                        <?php if ($game['channel_logo']): ?>
                                            <img src="<?php echo htmlspecialchars($game['channel_logo']); ?>" alt="Logo">
                                        <?php else: ?>
                                            <i class="fa-solid fa-tv" style="font-size: 11px; color: var(--color-purple-light);"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="broadcaster-name"><?php echo htmlspecialchars($game['channel_name'] ?: 'Transmissão Direta'); ?></span>
                                </div>
                                <?php if ($badgeTime === 'NO AR' || empty($game['game_date'])): ?>
                                    <div class="badge-live">
                                        <i class="fa-solid fa-circle" style="font-size: 6px;"></i> NO AR
                                    </div>
                                <?php else: ?>
                                    <div class="badge-time">
                                        <i class="fa-solid fa-clock" style="font-size: 10px; margin-right: 3px;"></i> <?php echo $badgeTime; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ticket-body">
                                <h3 class="match-title"><?php echo htmlspecialchars($game['name']); ?></h3>
                                <?php if (!empty($formattedDate)): ?>
                                    <div class="game-ticket-date" style="font-size: 11px; color: var(--color-purple-light); font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                        <i class="fa-solid fa-calendar-days"></i> <?php echo $formattedDate; ?>
                                    </div>
                                <?php endif; ?>
                                <p class="match-desc"><?php echo nl2br(htmlspecialchars($game['description'])); ?></p>
                            </div>
                            
                            <div class="ticket-footer">
                                <div class="price-box">
                                    <span class="price-label">Ingresso</span>
                                    <?php if ($game['price'] > 0): ?>
                                        <span class="price-value">R$ <?php echo number_format($game['price'], 2, ',', '.'); ?></span>
                                    <?php else: ?>
                                        <span class="price-value free">GRÁTIS</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($game['price'] > 0): ?>
                                    <a href="assistir.php?jogo=<?php echo $game['id']; ?>" class="btn-ticket buy">
                                        <i class="fa-solid fa-ticket"></i> Adquirir Acesso
                                    </a>
                                <?php else: ?>
                                    <button onclick="playMatch(<?php echo $game['id']; ?>)" class="btn-ticket">
                                        <i class="fa-solid fa-circle-play"></i> Assistir Agora
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal do Player -->
    <div id="player-modal" class="modal" onclick="closePlayerModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button class="btn-close-modal" onclick="closePlayerModal(event)">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <iframe id="player-iframe" class="modal-iframe" src=""></iframe>
        </div>
    </div>

    <script>
        // Rolar suavemente até a grade de jogos
        function scrollToGames() {
            const anchor = document.getElementById('leagues-anchor');
            if (anchor) {
                anchor.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Filtrar jogos por liga na hora (instantâneo)
        function filterByLeague(league) {
            // Atualizar o chip ativo
            document.querySelectorAll('.league-chip').forEach(chip => {
                chip.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            // Exibir/Esconder cards
            let visibleCount = 0;
            document.querySelectorAll('.game-ticket').forEach(ticket => {
                if (league === 'all') {
                    ticket.style.display = 'flex';
                    visibleCount++;
                } else {
                    const ticketLeague = ticket.getAttribute('data-league');
                    if (ticketLeague === league) {
                        ticket.style.display = 'flex';
                        visibleCount++;
                    } else {
                        ticket.style.display = 'none';
                    }
                }
            });

            // Gerenciar layout se não houver jogos para a liga selecionada
            const existingTempEmpty = document.querySelector('.temp-empty');
            if (existingTempEmpty) existingTempEmpty.remove();

            if (visibleCount === 0) {
                const contentGames = document.getElementById('tab-content-games');
                if (contentGames) {
                    const grid = contentGames.querySelector('.games-grid');
                    if (grid) grid.style.display = 'none';

                    const noMatch = document.createElement('div');
                    noMatch.className = 'empty-state temp-empty';
                    noMatch.innerHTML = `
                        <i class="fa-solid fa-calendar-xmark" style="font-size: 40px; color: rgba(124, 58, 237, 0.2); margin-bottom: 15px;"></i>
                        <h3>Nenhuma Partida Encontrada</h3>
                        <p>Não há jogos ativos cadastrados para esta categoria de liga esportiva no momento.</p>
                    `;
                    contentGames.appendChild(noMatch);
                }
            } else {
                const contentGames = document.getElementById('tab-content-games');
                if (contentGames) {
                    const grid = contentGames.querySelector('.games-grid');
                    if (grid) grid.style.display = 'grid';
                }
            }
        }

        // Abrir Jogo Grátis
        function playMatch(gameId) {
            const modal = document.getElementById('player-modal');
            const iframe = document.getElementById('player-iframe');
            
            // Carrega assistir.php direto no iframe em modo simplificado
            iframe.src = 'assistir.php?jogo=' + gameId + '&embed=1';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Fechar Modal
        function closePlayerModal(e) {
            const modal = document.getElementById('player-modal');
            const iframe = document.getElementById('player-iframe');
            
            iframe.src = ''; // Corta o áudio/vídeo imediatamente ao fechar
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    </script>
</body>
</html>
