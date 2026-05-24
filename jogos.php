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

        /* Topbar */
        .topbar {
            background: rgba(14, 8, 34, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
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

        /* Hero Header */
        .hero {
            text-align: center;
            max-width: 800px;
            margin: 50px auto 30px;
            padding: 0 20px;
        }

        .hero h1 {
            font-size: 38px;
            font-weight: 900;
            letter-spacing: -0.5px;
            margin-bottom: 12px;
            background: linear-gradient(to right, #fff, var(--color-purple-light), #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Container Tabs Toggle */
        .tabs-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto 40px;
            padding: 0 20px;
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
            <div class="logo-icon">
                <i class="fa-solid fa-circle-play"></i>
            </div>
            <div class="brand-text">
                <h2>ARENA</h2>
                <span>STREAM</span>
            </div>
        </a>
        
        <a href="index.php" class="btn-admin">
            <i class="fa-solid fa-user-gear"></i> Painel Admin
        </a>
    </header>

    <!-- Hero Header -->
    <section class="hero">
        <h1>Grade de Transmissões Esportivas</h1>
        <p>Acompanhe todos os confrontos em tempo real. Escolha a sua partida ou sintonize o canal esportivo correspondente para vivenciar a melhor cobertura ao vivo da internet.</p>
    </section>

    <!-- Tabs Toggle -->
    <div class="tabs-container">
        <div class="tab-switcher">
            <button class="tab-btn active" onclick="switchTab('games')">
                <i class="fa-solid fa-ticket"></i> Jogos Agendados
            </button>
            <button class="tab-btn" onclick="switchTab('channels')">
                <i class="fa-solid fa-tv"></i> Grade de Canais (<?php echo count($channels); ?>)
            </button>
        </div>

        <!-- ABA 1: Jogos Agendados -->
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
                        <div class="game-ticket">
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
                                <div class="badge-live">
                                    <i class="fa-solid fa-circle" style="font-size: 6px;"></i> NO AR
                                </div>
                            </div>
                            
                            <div class="ticket-body">
                                <h3 class="match-title"><?php echo htmlspecialchars($game['name']); ?></h3>
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

        <!-- ABA 2: Canais Cadastrados -->
        <div id="tab-content-channels" class="tab-content">
            <?php if (empty($channels)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-tv"></i>
                    <h3>Nenhum Canal Esportivo</h3>
                    <p>Nenhum canal da sua lista M3U foi marcado como esportivo. Acesse o Painel Admin para configurar!</p>
                </div>
            <?php else: ?>
                <div class="channels-grid">
                    <?php foreach ($channels as $channel): ?>
                        <div class="channel-card">
                            <div class="channel-logo">
                                <?php if ($channel['logo']): ?>
                                    <img src="<?php echo htmlspecialchars($channel['logo']); ?>" alt="Logo">
                                <?php else: ?>
                                    <i class="fa-solid fa-tv"></i>
                                <?php endif; ?>
                            </div>
                            <h4 class="channel-name"><?php echo htmlspecialchars($channel['name']); ?></h4>
                            <span class="channel-group"><?php echo htmlspecialchars($channel['group_name'] ?: 'Esportes'); ?></span>
                            
                            <button onclick="playChannel(<?php echo $channel['id']; ?>)" class="btn-play-channel">
                                <i class="fa-solid fa-play"></i> Sintonizar Sinal
                            </button>
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
        // Alternar Abas (SPA)
        function switchTab(tabId) {
            // Remover ativo das abas e botões
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Ativar a selecionada
            if (tabId === 'games') {
                document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
                document.getElementById('tab-content-games').classList.add('active');
            } else {
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
                document.getElementById('tab-content-channels').classList.add('active');
            }
        }

        // Abrir Jogo Grátis
        function playMatch(gameId) {
            const modal = document.getElementById('player-modal');
            const iframe = document.getElementById('player-iframe');
            
            // Carrega assistir.php direto no iframe em modo simplificado
            iframe.src = 'assistir.php?jogo=' + gameId;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Abrir Canal Esportivo
        function playChannel(channelId) {
            const modal = document.getElementById('player-modal');
            const iframe = document.getElementById('player-iframe');
            
            // Carrega player_embed.php direto no iframe do modal
            iframe.src = 'player_embed.php?id=' + channelId;
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
