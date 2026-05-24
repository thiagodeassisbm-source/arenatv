<?php
// Carregar conexão com o banco de dados
require_once __DIR__ . '/config/db.php';

// Buscar estatísticas iniciais em tempo real
$totalChannels = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
$sportsChannels = $pdo->query("SELECT COUNT(*) FROM channels WHERE is_sports = 1")->fetchColumn();
$totalGames = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();

// Buscar URL M3U salva
$stmt = $pdo->prepare("SELECT meta_value FROM settings WHERE meta_key = 'm3u_url'");
$stmt->execute();
$m3uUrl = $stmt->fetchColumn() ?: '';

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
                        <textarea id="game_description" name="description" class="form-control" placeholder="Ex: Campeonato Brasileiro - Rodada 10 - Sábado às 21h" required></textarea>
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

<?php
// Incluir o Rodapé Premium
require_once __DIR__ . '/includes/footer.php';
?>
