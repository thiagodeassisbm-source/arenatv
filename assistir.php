<?php
require_once __DIR__ . '/config/db.php';

// Validar ID do jogo
$gameId = isset($_GET['jogo']) ? intval($_GET['jogo']) : 0;
$game = null;

if ($gameId > 0) {
    $stmt = $pdo->prepare("SELECT g.*, c.name AS channel_name, c.logo AS channel_logo, c.url AS stream_url 
                            FROM games g 
                            LEFT JOIN channels c ON g.channel_id = c.id 
                            WHERE g.id = ? AND g.status = 'ativo'");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $game ? htmlspecialchars($game['name']) . " | Arena Stream" : "Jogo Não Encontrado | Arena Stream"; ?></title>
    
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: radial-gradient(circle at center, #1b0c30 0%, var(--bg-dark) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Topbar */
        .topbar {
            background: rgba(14, 8, 34, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(124, 58, 237, 0.15);
            padding: 15px 30px;
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
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--color-purple), var(--color-red));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 16px;
            box-shadow: 0 0 10px rgba(124, 58, 237, 0.3);
        }

        .brand-text h2 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            background: linear-gradient(to right, #fff, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-text span {
            font-size: 9px;
            font-weight: 600;
            color: var(--color-red);
            letter-spacing: 2px;
            text-transform: uppercase;
            display: block;
            margin-top: -3px;
        }

        .badge-live {
            background: var(--color-red);
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            animation: blink 1.5s infinite;
        }

        /* Container Principal */
        .viewer-container {
            max-width: 1400px;
            width: 100%;
            margin: 30px auto;
            padding: 0 20px;
            flex-grow: 1;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }

        @media (max-width: 1024px) {
            .viewer-container {
                grid-template-columns: 1fr;
            }
        }

        /* Player de Vídeo e Transmissão */
        .player-wrapper {
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(124, 58, 237, 0.2);
            position: relative;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            aspect-ratio: 16/9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-player {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Overlay de Compra (Se o jogo for pago e necessitar checkout) */
        .paywall-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(14, 8, 34, 0.95) 0%, rgba(7, 4, 14, 0.98) 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
            z-index: 10;
        }

        .ticket-box {
            background: rgba(22, 14, 43, 0.6);
            border: 2px dashed rgba(239, 68, 68, 0.4);
            border-radius: 24px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            position: relative;
        }

        .ticket-box::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #000;
        }

        .ticket-box::after {
            content: '';
            position: absolute;
            right: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #000;
        }

        .ticket-channel-logo {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .ticket-channel-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .ticket-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff, var(--color-purple-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .ticket-desc {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .ticket-price {
            font-size: 32px;
            font-weight: 900;
            color: var(--color-red);
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
            margin-bottom: 24px;
        }

        .btn-buy {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, var(--color-purple), var(--color-red));
            color: #fff;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            width: 100%;
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-buy:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.6);
        }

        .payment-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 10px;
        }

        /* Detalhes do Jogo */
        .game-details-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(124, 58, 237, 0.15);
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
        }

        .game-header-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .game-info-channel-logo {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .game-info-channel-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .game-info-channel-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--color-purple-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .game-info-title {
            font-size: 26px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
        }

        .game-info-desc {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Barra Lateral: Chat / Fan Zone */
        .chat-sidebar {
            background: rgba(14, 8, 34, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(124, 58, 237, 0.15);
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            height: 600px;
            overflow: hidden;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid rgba(124, 58, 237, 0.15);
            background: rgba(7, 4, 14, 0.4);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header h3 i {
            color: var(--color-red);
        }

        .viewer-count {
            font-size: 11px;
            color: var(--color-success);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .chat-messages {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .chat-message {
            font-size: 13.5px;
            line-height: 1.4;
            animation: msgFadeIn 0.3s ease-out forwards;
        }

        .chat-username {
            font-weight: 700;
            margin-right: 5px;
            cursor: pointer;
        }

        .chat-text {
            color: #e5e7eb;
        }

        .chat-input-area {
            padding: 15px 20px;
            border-top: 1px solid rgba(124, 58, 237, 0.15);
            background: rgba(7, 4, 14, 0.4);
            display: flex;
            gap: 10px;
        }

        .chat-input {
            flex-grow: 1;
            background: rgba(11, 8, 19, 0.8);
            border: 1px solid rgba(124, 58, 237, 0.2);
            border-radius: 10px;
            padding: 10px 15px;
            color: #fff;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }

        .chat-input:focus {
            border-color: var(--color-purple);
        }

        .btn-send {
            width: 38px;
            height: 38px;
            background: var(--color-purple);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .btn-send:hover {
            background: #9333ea;
        }

        /* Estado Vazio ou Erro */
        .error-container {
            max-width: 500px;
            width: 100%;
            margin: auto;
            padding: 40px;
            text-align: center;
            background: var(--bg-card);
            border-radius: 24px;
            border: 1px solid rgba(124, 58, 237, 0.2);
            box-shadow: var(--shadow-premium);
        }

        .error-container i {
            font-size: 60px;
            color: var(--color-red);
            margin-bottom: 20px;
        }

        .error-container h2 {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .error-container p {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 25px;
        }

        /* Animações */
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @keyframes msgFadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Estilos Inteligentes de Incorporação (Embed) */
        <?php if (isset($_GET['embed']) && $_GET['embed'] == '1'): ?>
        .topbar, .game-details-card, .chat-sidebar {
            display: none !important;
        }
        body {
            background: #000 !important;
            overflow: hidden !important;
        }
        .viewer-container {
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
            width: 100vw !important;
            height: 100vh !important;
        }
        .viewer-container > div {
            width: 100% !important;
            height: 100% !important;
        }
        .player-wrapper {
            width: 100vw !important;
            height: 100vh !important;
            border: none !important;
            border-radius: 0 !important;
            aspect-ratio: auto !important;
            box-shadow: none !important;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    
    <!-- Topbar -->
    <header class="topbar">
        <a href="index.php" class="brand">
            <img src="assets/images/logo.png" alt="Arena Esportiva" style="height: 38px; width: auto; object-fit: contain;">
        </a>
        
        <?php if ($game): ?>
            <div class="badge-live">
                <i class="fa-solid fa-circle"></i> AO VIVO
            </div>
        <?php endif; ?>
    </header>

    <?php if (!$game): ?>
        <!-- Tela de Erro: Jogo Não Encontrado -->
        <div class="error-container">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <h2>Jogo Não Disponível</h2>
            <p>O link que você acessou está incorreto, o jogo não foi ativado para transmissão pelo administrador ou já foi encerrado.</p>
            <a href="index.php" class="btn-buy" style="background: var(--color-purple); text-decoration: none; width: auto; font-size: 14px; display: inline-flex;">
                Voltar ao Painel
            </a>
        </div>
    <?php else: ?>
        <!-- Visualizador Principal do Jogo -->
        <div class="viewer-container">
            <!-- Coluna Esquerda: Player e Detalhes -->
            <div>
                <div class="player-wrapper">
                    <?php if ($game['price'] > 0): ?>
                        <!-- Bloqueio de Pagamento (Paywall) -->
                        <div class="paywall-overlay">
                            <div class="ticket-box">
                                <div class="ticket-channel-logo">
                                    <?php if ($game['channel_logo']): ?>
                                        <img src="<?php echo htmlspecialchars($game['channel_logo']); ?>" alt="Canal">
                                    <?php else: ?>
                                        <i class="fa-solid fa-tv" style="font-size: 20px; color: var(--color-purple-light);"></i>
                                    <?php endif; ?>
                                </div>
                                <h3 class="ticket-title"><?php echo htmlspecialchars($game['name']); ?></h3>
                                <p class="ticket-desc"><?php echo htmlspecialchars($game['description']); ?></p>
                                
                                <div class="ticket-price">
                                    R$ <?php echo number_format($game['price'], 2, ',', '.'); ?>
                                </div>
                                
                                <a href="<?php echo htmlspecialchars($game['external_link'] ?: '#'); ?>" target="_blank" class="btn-buy">
                                    <i class="fa-solid fa-ticket"></i> Adquirir Acesso Instantâneo
                                </a>
                                <p class="payment-hint">Acesso liberado imediatamente após a confirmação do pagamento.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Transmissão HLS/MPEGTS Real-Time Integrada (100% Sincronizada e Funcional) -->
                        <iframe src="player_embed.php?id=<?php echo (int)$game['channel_id']; ?>" style="width: 100%; height: 100%; border: none; background: #000; display: block;" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                    <?php endif; ?>
                </div>

                <!-- Detalhes do Jogo Card -->
                <div class="game-details-card">
                    <div class="game-header-info">
                        <div class="game-info-channel-logo">
                            <?php if ($game['channel_logo']): ?>
                                <img src="<?php echo htmlspecialchars($game['channel_logo']); ?>" alt="Canal">
                            <?php else: ?>
                                <i class="fa-solid fa-tv" style="color: var(--color-purple-light);"></i>
                            <?php endif; ?>
                        </div>
                        <span class="game-info-channel-name"><?php echo htmlspecialchars($game['channel_name']); ?></span>
                    </div>
                    <h1 class="game-info-title"><?php echo htmlspecialchars($game['name']); ?></h1>
                    <p class="game-info-desc"><?php echo nl2br(htmlspecialchars($game['description'])); ?></p>
                </div>
            </div>

            <!-- Coluna Direita: Bate-papo Simulador -->
            <aside class="chat-sidebar">
                <div class="chat-header">
                    <h3><i class="fa-solid fa-comments"></i> Bate-Papo da Torcida</h3>
                    <div class="viewer-count">
                        <i class="fa-solid fa-eye"></i> <span id="chat-count">1.458</span> assistindo
                    </div>
                </div>

                <div class="chat-messages" id="chat-messages-box">
                    <div class="chat-message">
                        <span class="chat-username" style="color: #60a5fa;">Renato_FC:</span>
                        <span class="chat-text">Bora timeee! Hoje é vitória com certeza! 🔥⚽</span>
                    </div>
                    <div class="chat-message">
                        <span class="chat-username" style="color: #f87171;">Marcos_Vasco:</span>
                        <span class="chat-text">Vasco vai atropelar hoje! Quem concorda curte aí!</span>
                    </div>
                    <div class="chat-message">
                        <span class="chat-username" style="color: #fbbf24;">CarlosEsportes:</span>
                        <span class="chat-text">Qualidade de imagem tá impecável! Valeu cada centavo. 👏🏆</span>
                    </div>
                </div>

                <div class="chat-input-area">
                    <input type="text" id="chat-input-text" class="chat-input" placeholder="Envie sua mensagem..." onkeydown="if(event.key === 'Enter') sendChatMessage()">
                    <button class="btn-send" onclick="sendChatMessage()">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </aside>
        </div>

        <!-- Script de Chat Dinâmico Simulador -->
        <script>
            // Simulador de Mensagens de Outros Usuários
            const randomUsernames = [
                { name: 'Ana_Silva', color: '#f472b6' },
                { name: 'Joao_Gamer', color: '#34d399' },
                { name: 'FutebolNews', color: '#fb7185' },
                { name: 'Rodolfo_M', color: '#a78bfa' },
                { name: 'BrunoFla', color: '#f87171' },
                { name: 'Carioca_021', color: '#38bdf8' }
            ];

            const randomMessages = [
                'Goooool!!!! Mentira, quase! 😂',
                'Juiz tá roubando demais, meu deus do céu!',
                'Que jogo tenso! Passa nem sinal de wifi kkkkk',
                'O stream tá super liso, nota 10!',
                'Cadê o cartão pro zagueiro?! 😡',
                'Vamos virar esse jogo, eu acredito!',
                'Isso não foi falta nunca!',
                'Que jogaço meus amigos, nível Champions League!'
            ];

            const chatBox = document.getElementById('chat-messages-box');
            const chatCount = document.getElementById('chat-count');

            // Adicionar novas mensagens aleatórias periodicamente para simular live chat
            setInterval(() => {
                const user = randomUsernames[Math.floor(Math.random() * randomUsernames.length)];
                const text = randomMessages[Math.floor(Math.random() * randomMessages.length)];
                
                appendMessage(user.name, user.color, text);
                
                // Variar quantidade de visualizadores levemente
                let current = parseInt(chatCount.textContent.replace('.', ''));
                current += Math.floor(Math.random() * 11) - 5;
                chatCount.textContent = current.toLocaleString('pt-BR');
            }, 4500);

            // Envia mensagem do usuário
            function sendChatMessage() {
                const input = document.getElementById('chat-input-text');
                const text = input.value.trim();
                
                if (text === '') return;
                
                appendMessage('Você', '#7c3aed', text);
                input.value = '';
                
                // Responder com simulação após 1.5s
                setTimeout(() => {
                    const user = randomUsernames[Math.floor(Math.random() * randomUsernames.length)];
                    const responses = [
                        'Concordo plenamente!',
                        'Eita, será?! 😂',
                        'Boa! Mandou a braba.',
                        'Kkkkkkk bem isso',
                        'Disse tudo mano!'
                    ];
                    appendMessage(user.name, user.color, responses[Math.floor(Math.random() * responses.length)]);
                }, 1500);
            }

            function appendMessage(username, color, text) {
                const msg = document.createElement('div');
                msg.className = 'chat-message';
                msg.innerHTML = `
                    <span class="chat-username" style="color: ${color};">${username}:</span>
                    <span class="chat-text">${escapeHtml(text)}</span>
                `;
                
                chatBox.appendChild(msg);
                chatBox.scrollTop = chatBox.scrollHeight;
                
                // Limitar para manter as últimas 40 mensagens para performance
                if (chatBox.children.length > 40) {
                    chatBox.children[0].remove();
                }
            }

            function escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
        </script>
    <?php endif; ?>

</body>
</html>
