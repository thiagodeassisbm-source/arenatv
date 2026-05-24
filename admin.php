<?php
// Carregar conexão com o banco de dados
require_once __DIR__ . '/config/db.php';

// Buscar estatísticas iniciais em tempo real
$totalChannels = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
$sportsChannels = $pdo->query("SELECT COUNT(*) FROM channels WHERE is_sports = 1")->fetchColumn();
$totalGames = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();

// Buscar todas as configurações
$stmt = $pdo->query("SELECT meta_key, meta_value FROM settings");
$settingsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach ($settingsList as $s) {
    $settings[$s['meta_key']] = $s['meta_value'];
}
$m3uUrl = $settings['m3u_url'] ?? '';

// Buscar Jogos Agendados Ativos para a Grade
$sqlGames = "SELECT g.*, c.name AS channel_name, c.logo AS channel_logo 
             FROM games g 
             LEFT JOIN channels c ON g.channel_id = c.id 
             WHERE g.status = 'ativo' 
             ORDER BY g.created_at DESC";
$games = $pdo->query($sqlGames)->fetchAll(PDO::FETCH_ASSOC);

// Buscar Todos os Canais Esportivos Cadastrados
$sqlChannelsList = "SELECT id, name, logo, group_name FROM channels WHERE is_sports = 1 ORDER BY name ASC";
$channelsList = $pdo->query($sqlChannelsList)->fetchAll(PDO::FETCH_ASSOC);

// Incluir o Cabeçalho Premium
require_once __DIR__ . '/includes/header.php';
?>

<!-- ==========================================
     ABAS DE CONTEÚDO (SPA STYLE)
     ========================================== -->

<!-- 1. ABA: VISÃO GERAL (OVERVIEW) -->
<section id="tab-overview" class="tab-content active">
    <!-- Grid de Estatísticas Rápidas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-server"></i>
            </div>
            <div class="stat-info">
                <h3>Total Canais</h3>
                <div class="stat-value" id="stat-total-channels"><?php echo $totalChannels; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-volleyball"></i>
            </div>
            <div class="stat-info">
                <h3>Canais Esportivos</h3>
                <div class="stat-value" id="stat-sports-channels"><?php echo $sportsChannels; ?></div>
            </div>
        </div>

        <div class="stat-card accent-red">
            <div class="stat-icon">
                <i class="fa-solid fa-ticket"></i>
            </div>
            <div class="stat-info">
                <h3>Jogos à Venda</h3>
                <div class="stat-value" id="stat-total-games"><?php echo $totalGames; ?></div>
            </div>
        </div>
    </div>

    <!-- Guia de Inicialização Rápida -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-wand-magic-sparkles"></i> Fluxo de Configuração Rápida</h2>
        </div>
        <div class="card-body" style="padding: 40px 30px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; position: relative;">
                
                <div style="text-align: center; position: relative; z-index: 1;">
                    <div style="width: 60px; height: 60px; background: rgba(124, 58, 237, 0.1); border: 2px solid var(--color-purple); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 20px; color: var(--color-purple-light); font-weight: 800;">1</div>
                    <h3 style="font-size: 16px; margin-bottom: 8px;">Importe a Lista M3U</h3>
                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; max-width: 220px; margin: 0 auto;">Insira o link ou envie o arquivo da sua lista IPTV adquirida para alimentar o sistema.</p>
                    <button onclick="switchTab('import')" class="btn btn-secondary" style="margin-top: 15px; padding: 8px 16px; font-size: 12px;"><i class="fa-solid fa-arrow-right"></i> Ir para Importação</button>
                </div>

                <div style="text-align: center; position: relative; z-index: 1;">
                    <div style="width: 60px; height: 60px; background: rgba(124, 58, 237, 0.1); border: 2px solid var(--color-purple); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 20px; color: var(--color-purple-light); font-weight: 800;">2</div>
                    <h3 style="font-size: 16px; margin-bottom: 8px;">Selecione Canais Esportivos</h3>
                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; max-width: 220px; margin: 0 auto;">Marque os canais que deseja usar. Use nosso filtro inteligente automático para agilizar.</p>
                    <button onclick="switchTab('channels')" class="btn btn-secondary" style="margin-top: 15px; padding: 8px 16px; font-size: 12px;"><i class="fa-solid fa-arrow-right"></i> Separar Canais</button>
                </div>

                <div style="text-align: center; position: relative; z-index: 1;">
                    <div style="width: 60px; height: 60px; background: rgba(239, 68, 68, 0.1); border: 2px solid var(--color-red); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 20px; color: var(--color-red); font-weight: 800;">3</div>
                    <h3 style="font-size: 16px; margin-bottom: 8px;">Cadastre e Venda</h3>
                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; max-width: 220px; margin: 0 auto;">Crie o jogo de futebol, selecione o canal esportivo vinculado e coloque para vender!</p>
                    <button onclick="switchTab('games')" class="btn btn-accent" style="margin-top: 15px; padding: 8px 16px; font-size: 12px;"><i class="fa-solid fa-bolt"></i> Criar Venda</button>
                </div>
                
            </div>
        </div>
    </div>
</section>

<!-- 2. ABA: IMPORTAR M3U -->
<section id="tab-import" class="tab-content">
    <div class="import-container">
        <!-- Esquerda: URL Form -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-link"></i> Importar via URL M3U</h2>
            </div>
            <div class="card-body">
                <form id="form-import-url" onsubmit="importM3uUrl(event)">
                    <div class="form-group">
                        <label class="form-label" for="m3u_url">Endereço (URL) da Lista M3U</label>
                        <input type="text" id="m3u_url" name="m3u_url" class="form-control" placeholder="http://servidor.com/get.php?username=...&password=..." value="<?php echo htmlspecialchars($m3uUrl); ?>" required autocomplete="off" spellcheck="false">
                        <p style="font-size: 11px; color: var(--text-secondary); margin-top: 6px;">Esta URL ficará salva localmente para atualizações rápidas posteriores.</p>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-cloud-arrow-down"></i> Baixar e Importar Lista
                    </button>
                </form>
            </div>
        </div>

        <!-- Direita: Envio de Arquivo M3U -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-file-code"></i> Enviar Arquivo M3U</h2>
            </div>
            <div class="card-body">
                <form id="form-import-file" onsubmit="importM3uFile(event)">
                    <div class="form-group">
                        <label class="form-label">Arquivo local .m3u ou .m3u8</label>
                        <div class="file-drop-area" id="drop-zone">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p style="font-size: 14px; font-weight: 600; margin-bottom: 4px;">Arraste o arquivo M3U aqui</p>
                            <p style="font-size: 12px; color: var(--text-secondary);">ou clique para selecionar do computador</p>
                            <input type="file" id="m3u_file" name="m3u_file" class="file-input" accept=".m3u,.m3u8" onchange="updateFileNameLabel(this)">
                        </div>
                        <p id="file-name-label" style="font-size: 12px; color: var(--color-success); margin-top: 8px; font-weight: 600; text-align: center; display: none;"></p>
                    </div>
                    <button type="submit" class="btn btn-secondary" style="width: 100%;" id="btn-submit-file" disabled>
                        <i class="fa-solid fa-upload"></i> Processar Arquivo Selecionado
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Feedback e Indicador de Progresso de Importação -->
    <div class="card import-status-card" id="import-status-container">
        <div class="loader-container" id="import-loader">
            <div class="spinner"></div>
            <h3 style="margin-top: 20px; font-size: 16px;">Processando Lista M3U...</h3>
            <p style="font-size: 13px; color: var(--text-secondary); margin-top: 6px; text-align: center;">Listas grandes (50 MB+) podem levar <strong>5 a 15 minutos</strong>. Mantenha o <code>iniciar.bat</code> aberto e não feche esta aba.</p>
            <div class="progress-bar-container">
                <div class="progress-bar" id="import-progress"></div>
            </div>
        </div>
        
        <div id="import-result" style="display: none; text-align: center; padding: 10px;">
            <div id="import-result-icon" style="width: 55px; height: 55px; background: rgba(16, 185, 129, 0.1); border: 1.5px solid var(--color-success); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: var(--color-success); margin: 0 auto 15px;">
                <i class="fa-solid fa-check"></i>
            </div>
            <h3 id="result-title" style="font-size: 18px; margin-bottom: 6px;">Importação Concluída!</h3>
            <p id="result-message" style="font-size: 14px; color: var(--text-secondary); margin-bottom: 20px;"></p>
            
            <div style="display: flex; gap: 15px; justify-content: center; max-width: 400px; margin: 0 auto;">
                <button onclick="switchTab('channels')" class="btn btn-primary" style="flex: 1; font-size: 13px;">
                    <i class="fa-solid fa-tv"></i> Ver Canais
                </button>
                <button onclick="resetImportForm()" class="btn btn-secondary" style="flex: 1; font-size: 13px;">
                    Importar Outro
                </button>
            </div>
        </div>
    </div>
</section>

<!-- 3. ABA: GERENCIAR CANAIS -->
<section id="tab-channels" class="tab-content">
    <!-- Barra de Filtros e Pesquisa -->
    <div class="filter-bar">
        <div class="filter-inputs">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="channel-search" class="form-control" placeholder="Buscar canal por nome..." oninput="filterChannels()">
            </div>

            <select id="channel-category-filter" class="form-select" onchange="filterByCategory()" style="min-width: 220px;">
                <option value="">Todas as categorias</option>
            </select>
            
            <button id="btn-filter-sports" class="btn btn-secondary" onclick="toggleSportsFilter()" style="font-size: 13px;">
                <i class="fa-solid fa-filter"></i> Apenas Esportivos
            </button>
        </div>

        <div class="filter-actions">
            <button class="btn btn-secondary" onclick="dedupeChannels()" style="font-size: 13px;" title="Remove canais repetidos (mesmo nome na mesma categoria)">
                <i class="fa-solid fa-broom"></i> Remover Duplicados
            </button>
            <button class="btn btn-accent" onclick="runAutoFilter()" style="font-size: 13px;" title="Filtra canais esportivos automaticamente usando inteligência por palavras-chave">
                <i class="fa-solid fa-robot"></i> Auto-Filtrar Esportivos
            </button>
        </div>
    </div>

    <div id="category-chips" class="category-chips"></div>

    <!-- Grid de Canais (Preenchido por JS) -->
    <div class="channel-grid" id="channels-container">
        <!-- Os canais serão renderizados dinamicamente pelo JS -->
    </div>

    <!-- Paginação -->
    <div class="pagination" id="pagination-container">
        <!-- Renderizado dinamicamente -->
    </div>
</section>

<!-- 4. ABA: CADASTRAR/VENDER JOGOS -->
<section id="tab-games" class="tab-content">
    <div class="games-layout">
        <!-- Painel Esquerdo: Formulário de Cadastro de Jogo -->
        <div class="card">
            <div class="card-header">
                <h2 id="form-game-title"><i class="fa-solid fa-circle-plus"></i> Novo Jogo para Venda</h2>
            </div>
            <div class="card-body">
                <form id="form-game" onsubmit="saveGame(event)">
                    <!-- ID Oculto para Edição -->
                    <input type="hidden" id="game_id" name="id" value="">

                    <div class="form-group">
                        <label class="form-label" for="game_name">Nome do Jogo *</label>
                        <input type="text" id="game_name" name="name" class="form-control" placeholder="Ex: Flamengo vs Vasco" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="game_description">Descrição / Informações *</label>
                        <textarea id="game_description" name="description" class="form-control" placeholder="Ex: Campeonato Brasileiro - Rodada 10" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="game_date">Data e Hora do Jogo *</label>
                        <input type="datetime-local" id="game_date" name="game_date" class="form-control" required>
                        <p style="font-size: 11px; color: var(--text-secondary); margin-top: 6px;">Escolha o dia e o horário em que o jogo ocorrerá.</p>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="transmission_start">Liberar Transmissão *</label>
                            <input type="datetime-local" id="transmission_start" name="transmission_start" class="form-control" required>
                            <p style="font-size: 10px; color: var(--text-secondary); margin-top: 4px;">Horário em que o play fica ativo.</p>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="transmission_end">Bloquear Transmissão *</label>
                            <input type="datetime-local" id="transmission_end" name="transmission_end" class="form-control" required>
                            <p style="font-size: 10px; color: var(--text-secondary); margin-top: 4px;">Horário em que o player bloqueia.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="game_channel_id">Canal Esportivo Vinculado *</label>
                        <select id="game_channel_id" name="channel_id" class="form-select" required>
                            <option value="">Carregando canais esportivos...</option>
                        </select>
                        <p style="font-size: 11px; color: var(--text-secondary); margin-top: 6px;">Esta lista exibe apenas os canais que você separou como <strong>Esportivo</strong> na aba anterior.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="game_price">Preço da Venda (R$)</label>
                        <input type="text" id="game_price" name="price" class="form-control" placeholder="Ex: 10,00" value="0,00">
                        <p style="font-size: 11px; color: var(--text-secondary); margin-top: 6px;">Digite zero (0,00) caso queira disponibilizar o canal gratuitamente.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="game_external_link">Link de Pagamento ou Checkout</label>
                        <input type="url" id="game_external_link" name="external_link" class="form-control" placeholder="Ex: https://mpago.la/checkout...">
                        <p style="font-size: 11px; color: var(--text-secondary); margin-top: 6px;">Link do gateway de pagamento (Mercado Pago, Stripe, etc.) para o cliente comprar o jogo.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="game_status">Status</label>
                        <select id="game_status" name="status" class="form-select">
                            <option value="ativo" selected>Disponível / Ativo</option>
                            <option value="inativo">Pausado / Inativo</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-accent" style="flex: 1;">
                            <i class="fa-solid fa-save"></i> <span id="btn-save-text">Colocar para Venda</span>
                        </button>
                        <button type="button" id="btn-cancel-edit" class="btn btn-secondary" onclick="resetGameForm()" style="display: none;">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Painel Direito: Jogos já Cadastrados -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 18px; font-weight: 700; color: #fff;"><i class="fa-solid fa-rectangle-list"></i> Jogos Cadastrados</h2>
                <span style="font-size: 12px; background: rgba(239, 68, 68, 0.1); color: var(--color-red); padding: 4px 10px; border-radius: 20px; font-weight: 700; border: 1px solid rgba(239, 68, 68, 0.2);">AO VIVO</span>
            </div>
            
            <div class="games-list-wrapper" id="games-list-container">
                <!-- Os ingressos de jogos serão renderizados dinamicamente pelo JS -->
            </div>
        </div>
    </div>
</section>

<!-- 5. ABA: GRADE E CALENDÁRIO -->
<section id="tab-calendar" class="tab-content">
    <style>
        /* Estilos locais premium para o Calendário Integrado */
        .tabs-container-integrated {
            width: 100%;
            margin-bottom: 30px;
        }

        .tab-switcher-integrated {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding-bottom: 15px;
        }

        .tab-btn-integrated {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 700;
            padding: 8px 20px;
            cursor: pointer;
            position: relative;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn-integrated.active {
            color: #fff;
        }

        .tab-btn-integrated.active::after {
            content: '';
            position: absolute;
            bottom: -16px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, var(--color-purple), var(--color-red));
            border-radius: 10px;
        }

        .sub-tab-content {
            display: none;
        }

        .sub-tab-content.active {
            display: block;
            animation: fadeInIntegrated 0.4s ease-out forwards;
        }

        /* Grid de Jogos Agendados */
        .games-grid-integrated {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .game-ticket-integrated {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            transition: transform 0.25s, border-color 0.25s, box-shadow 0.25s;
            position: relative;
        }

        .game-ticket-integrated:hover {
            transform: translateY(-4px);
            border-color: rgba(124, 58, 237, 0.35);
            box-shadow: 0 12px 25px rgba(124, 58, 237, 0.15);
        }

        .ticket-header-integrated {
            padding: 12px 18px;
            background: rgba(7, 4, 14, 0.4);
            border-bottom: 1px solid rgba(255,255,255,0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .broadcaster-integrated {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .broadcaster-logo-integrated {
            width: 26px;
            height: 26px;
            background: rgba(255,255,255,0.05);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .broadcaster-logo-integrated img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .broadcaster-name-integrated {
            font-size: 10px;
            font-weight: 700;
            color: var(--color-purple-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-live-integrated {
            background: var(--color-red);
            color: #fff;
            font-size: 8px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
            animation: blinkIntegrated 1.5s infinite;
        }

        .ticket-body-integrated {
            padding: 20px 18px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .match-title-integrated {
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .match-desc-integrated {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .ticket-footer-integrated {
            padding: 16px 18px;
            background: rgba(7, 4, 14, 0.4);
            border-top: 1px solid rgba(255,255,255,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .price-box-integrated {
            display: flex;
            flex-direction: column;
        }

        .price-label-integrated {
            font-size: 8px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .price-value-integrated {
            font-size: 16px;
            font-weight: 800;
            color: #fff;
        }

        .price-value-integrated.free {
            color: var(--color-success);
        }

        .btn-ticket-integrated {
            background: linear-gradient(135deg, var(--color-purple), var(--color-purple-light));
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-ticket-integrated:hover {
            transform: scale(1.03);
            box-shadow: 0 0 12px rgba(124, 58, 237, 0.35);
        }

        .btn-ticket-integrated.buy {
            background: linear-gradient(135deg, var(--color-purple), var(--color-red));
        }

        .btn-ticket-integrated.buy:hover {
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.35);
        }

        /* Grid de Canais Cadastrados */
        .channels-grid-integrated {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
        }

        .channel-card-integrated {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            transition: all 0.25s;
        }

        .channel-card-integrated:hover {
            transform: translateY(-3px);
            border-color: rgba(124, 58, 237, 0.3);
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.1);
        }

        .channel-logo-integrated {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 10px;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .channel-logo-integrated img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
        }

        .channel-logo-integrated i {
            font-size: 18px;
            color: var(--color-purple-light);
        }

        .channel-name-integrated {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .channel-group-integrated {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .btn-play-channel-integrated {
            background: rgba(255,255,255,0.05);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 11px;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .btn-play-channel-integrated:hover {
            background: var(--color-purple);
            border-color: var(--color-purple);
            box-shadow: 0 0 8px rgba(124, 58, 237, 0.3);
        }

        /* Modal de Vídeo Flutuante */
        .modal-integrated {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(7, 4, 14, 0.85);
            backdrop-filter: blur(10px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .modal-integrated.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-content-integrated {
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

        .modal-integrated.active .modal-content-integrated {
            transform: scale(1);
        }

        .modal-iframe-integrated {
            width: 100%;
            height: 100%;
            border: none;
        }

        .btn-close-modal-integrated {
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

        .btn-close-modal-integrated:hover {
            background: var(--color-red);
            border-color: var(--color-red);
            transform: rotate(90deg);
        }

        @keyframes fadeInIntegrated {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes blinkIntegrated {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>

    <div class="tabs-container-integrated">
        <div class="tab-switcher-integrated">
            <button class="tab-btn-integrated active" onclick="switchCalendarSubTab('games')">
                <i class="fa-solid fa-ticket"></i> Jogos Agendados
            </button>
            <button class="tab-btn-integrated" onclick="switchCalendarSubTab('channels')">
                <i class="fa-solid fa-tv"></i> Grade de Canais (<span id="integrated-channels-count">0</span>)
            </button>
        </div>

        <!-- SUBABA 1: Jogos Agendados -->
        <div id="subtab-content-games" class="sub-tab-content active">
            <div class="games-grid-integrated" id="integrated-games-container">
                <!-- Renderizado dinamicamente por JS -->
            </div>
        </div>

        <!-- SUBABA 2: Grade de Canais -->
        <div id="subtab-content-channels" class="sub-tab-content">
            <div class="channels-grid-integrated" id="integrated-channels-container">
                <!-- Renderizado dinamicamente por JS -->
            </div>
        </div>
    </div>

    <!-- Modal Integrado de Vídeo -->
    <div id="player-modal-integrated" class="modal-integrated" onclick="closePlayerModalIntegrated(event)">
        <div class="modal-content-integrated" onclick="event.stopPropagation()">
            <button class="btn-close-modal-integrated" onclick="closePlayerModalIntegrated(event)">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <iframe id="player-iframe-integrated" class="modal-iframe-integrated" src=""></iframe>
        </div>
    </div>

    <script>
        // Alternar sub-abas do calendário
        function switchCalendarSubTab(subTabId) {
            document.querySelectorAll('.tab-btn-integrated').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.sub-tab-content').forEach(content => content.classList.remove('active'));
            
            if (subTabId === 'games') {
                document.querySelector('.tab-btn-integrated:nth-child(1)').classList.add('active');
                document.getElementById('subtab-content-games').classList.add('active');
            } else {
                document.querySelector('.tab-btn-integrated:nth-child(2)').classList.add('active');
                document.getElementById('subtab-content-channels').classList.add('active');
            }
        }

        // Assistir Jogo Grátis
        function playMatchIntegrated(gameId) {
            const modal = document.getElementById('player-modal-integrated');
            const iframe = document.getElementById('player-iframe-integrated');
            iframe.src = 'assistir.php?jogo=' + gameId;
            modal.classList.add('active');
        }

        // Assistir Canal Direto
        function playChannelIntegrated(channelId) {
            const modal = document.getElementById('player-modal-integrated');
            const iframe = document.getElementById('player-iframe-integrated');
            iframe.src = 'player_embed.php?id=' + channelId;
            modal.classList.add('active');
        }

        // Fechar Modal
        function closePlayerModalIntegrated(e) {
            const modal = document.getElementById('player-modal-integrated');
            const iframe = document.getElementById('player-iframe-integrated');
            iframe.src = '';
            modal.classList.remove('active');
        }
    </script>
</section>

<!-- 6. ABA: CONFIGURAÇÕES -->
<section id="tab-settings" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-gears"></i> Configurações do Sistema</h2>
        </div>
        <div class="card-body">
            <form id="form-settings" onsubmit="saveSettings(event)" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label" for="setting_site_name">Nome da Plataforma</label>
                    <input type="text" id="setting_site_name" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Arena Stream'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Imagem de Encerramento da Transmissão (Tela Bloqueada)</label>
                    
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px;">
                        <div style="width: 160px; height: 90px; background: #000; border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden;" id="preview-offline-container">
                            <?php 
                            $offlineImg = $settings['offline_image'] ?? 'assets/images/transmission_ended.png';
                            if ($offlineImg !== '' && file_exists(__DIR__ . '/' . $offlineImg)): 
                            ?>
                                <img src="<?php echo $offlineImg; ?>?t=<?php echo time(); ?>" style="width: 100%; height: 100%; object-fit: cover;" id="preview-offline-img">
                            <?php else: ?>
                                <span style="font-size: 11px; color: var(--text-secondary);" id="preview-offline-placeholder">Sem Imagem</span>
                            <?php endif; ?>
                        </div>
                        <div style="flex-grow: 1;">
                            <input type="file" id="input_offline_image" name="offline_image" class="form-control" accept="image/*" onchange="previewOfflineImage(this)">
                            <p style="font-size: 11px; color: var(--text-secondary); margin-top: 6px;">Esta imagem será exibida para o usuário caso a transmissão já tenha encerrado. Recomendamos tamanho 1280x720 (16:9).</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-accent" style="margin-top: 15px;">
                    <i class="fa-solid fa-save"></i> Salvar Configurações
                </button>
            </form>
        </div>
    </div>

    <script>
        function previewOfflineImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var container = document.getElementById('preview-offline-container');
                    container.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;" id="preview-offline-img">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function saveSettings(e) {
            e.preventDefault();
            const form = document.getElementById('form-settings');
            const formData = new FormData(form);
            
            showToast('Salvando configurações...', 'info');
            
            fetch('ajax/save_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.error || 'Erro ao salvar configurações.', 'error');
                }
            })
            .catch(err => {
                showToast('Erro de rede ao salvar configurações.', 'error');
                console.error(err);
            });
        }
    </script>
</section>

<?php
// Incluir o Rodapé Premium
require_once __DIR__ . '/includes/footer.php';
?>
