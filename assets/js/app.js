/* ==========================================================================
   FRONTEND JS - ARENA STREAM (M3U IMPORT, CHANNEL MANAGER & GAMES)
   ========================================================================== */

// Estado Global da Aplicação
const appState = {
    currentTab: 'overview',
    channels: {
        page: 1,
        search: '',
        sportsOnly: 0,
        category: '',
        totalPages: 1,
        categories: []
    },
    games: [],
    editingGameId: null
};

// Ao Carregar o Documento
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initDragAndDrop();
    
    // Carregar dados iniciais
    fetchCategories();
    fetchChannels(1);
    fetchGames();
    fetchSportsChannelsForDropdown();
});

/* ==========================================
   SISTEMA DE ABAS (SPA STYLE)
   ========================================== */
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const tabId = item.getAttribute('data-tab');
            if (!tabId) return; // Ignora e permite navegação normal para links sem data-tab (ex: index.php)
            e.preventDefault();
            switchTab(tabId);
        });
    });

    // Tratar cliques em botões de atalho
    window.switchTab = function(tabId) {
        // Remover classe ativa de todas as abas no menu
        navItems.forEach(nav => nav.classList.remove('active'));
        
        // Adicionar classe ativa no menu correspondente
        const activeNav = document.querySelector(`.nav-item[data-tab="${tabId}"]`);
        if (activeNav) activeNav.classList.add('active');

        // Alternar visualização das seções
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.classList.remove('active'));
        
        const activeContent = document.getElementById(`tab-${tabId}`);
        if (activeContent) activeContent.classList.add('active');

        // Atualizar título da página
        updateHeaderTitle(tabId);
        
        // Ações extras ao abrir determinadas abas
        if (tabId === 'channels') {
            fetchCategories();
            fetchChannels(1);
        } else if (tabId === 'games') {
            fetchGames();
            fetchSportsChannelsForDropdown();
        } else if (tabId === 'calendar') {
            fetchIntegratedCalendar();
        }

        appState.currentTab = tabId;
        window.location.hash = tabId;
    };

    // Suporte ao hash da URL
    const hash = window.location.hash.substring(1);
    if (hash && ['overview', 'import', 'channels', 'games', 'calendar'].includes(hash)) {
        switchTab(hash);
    }
}

function updateHeaderTitle(tabId) {
    const titleEl = document.getElementById('page-title-display');
    const subtitleEl = document.getElementById('page-subtitle-display');

    const meta = {
        overview: {
            title: 'Painel Geral',
            subtitle: 'Visão geral do seu sistema de streaming esportivo.'
        },
        import: {
            title: 'Importar M3U',
            subtitle: 'Alimente o seu sistema com sua lista de canais IPTV.'
        },
        channels: {
            title: 'Gerenciar Canais',
            subtitle: 'Filtre e separe seus canais esportivos para a venda.'
        },
        games: {
            title: 'Vender Jogos',
            subtitle: 'Crie ingressos virtuais vinculados aos canais esportivos.'
        },
        calendar: {
            title: 'Grade de Programação',
            subtitle: 'Grade de canais esportivos e calendário de partidas ativas.'
        }
    };

    if (meta[tabId]) {
        titleEl.textContent = meta[tabId].title;
        subtitleEl.textContent = meta[tabId].subtitle;
    }
}

/* ==========================================
   IMPORTAÇÃO DA LISTA M3U
   ========================================== */
function initDragAndDrop() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('m3u_file');
    
    if (!dropZone) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        }, false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileNameLabel(fileInput);
        }
    });
}

function updateFileNameLabel(input) {
    const label = document.getElementById('file-name-label');
    const submitBtn = document.getElementById('btn-submit-file');
    
    if (input.files && input.files.length > 0) {
        label.textContent = `Arquivo selecionado: ${input.files[0].name}`;
        label.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-secondary');
        submitBtn.classList.add('btn-primary');
    } else {
        label.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.classList.remove('btn-primary');
        submitBtn.classList.add('btn-secondary');
    }
}

// Resetar formulário de importação para novos envios
window.resetImportForm = function() {
    document.getElementById('form-import-url').reset();
    document.getElementById('form-import-file').reset();
    
    const label = document.getElementById('file-name-label');
    if (label) label.style.display = 'none';
    
    const submitBtn = document.getElementById('btn-submit-file');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-secondary');
        submitBtn.classList.remove('btn-primary');
    }

    document.getElementById('import-status-container').style.display = 'none';
};

// Importação por URL M3U
window.importM3uUrl = function(e) {
    e.preventDefault();
    const urlInput = document.getElementById('m3u_url');
    const url = urlInput.value.trim();
    if (!url) {
        showToast('Cole a URL da lista M3U.', 'error');
        return;
    }

    sendImportRequestJson({ m3u_url: url });
};

// Importação por Upload de Arquivo
window.importM3uFile = function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('m3u_file');
    if (fileInput.files.length === 0) return;

    const formData = new FormData();
    formData.append('m3u_file', fileInput.files[0]);

    sendImportRequest(formData);
};

function sendImportRequestJson(payload) {
    sendImportRequest(null, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
}

// Envia a requisição AJAX para importação
function sendImportRequest(formDataOrBody, fetchOptions) {
    const statusContainer = document.getElementById('import-status-container');
    const loader = document.getElementById('import-loader');
    const resultDiv = document.getElementById('import-result');
    const progressBar = document.getElementById('import-progress');
    
    statusContainer.style.display = 'block';
    loader.style.display = 'flex';
    resultDiv.style.display = 'none';
    progressBar.style.width = '20%';

    // Simulação visual de progresso antes de bater na api (lista pode ser gigante)
    let progress = 20;
    const progressInterval = setInterval(() => {
        if (progress < 90) {
            progress += 5;
            progressBar.style.width = `${progress}%`;
        }
    }, 200);

    const options = fetchOptions || { method: 'POST', body: formDataOrBody };
    if (!options.method) options.method = 'POST';

    const statusText = document.querySelector('#import-loader p');
    if (statusText) {
        statusText.textContent = 'Baixando e processando a lista… listas grandes podem levar 5 a 15 minutos. Não feche esta página.';
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 900000);

    if (!options.signal) {
        options.signal = controller.signal;
    }

    fetch('ajax/import_m3u.php', options)
    .then(async (res) => {
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error(text.substring(0, 200) || `Resposta inválida do servidor (HTTP ${res.status}).`);
        }
        if (!res.ok && data && data.error) {
            throw new Error(data.error);
        }
        return data;
    })
    .then(data => {
        clearTimeout(timeoutId);
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        
        setTimeout(() => {
            loader.style.display = 'none';
            const resultIcon = document.getElementById('import-result-icon');
            if (data.success) {
                resultDiv.style.display = 'block';
                if (resultIcon) {
                    resultIcon.style.borderColor = 'var(--color-success)';
                    resultIcon.style.color = 'var(--color-success)';
                    resultIcon.style.background = 'rgba(16, 185, 129, 0.1)';
                    resultIcon.innerHTML = '<i class="fa-solid fa-check"></i>';
                }
                document.getElementById('result-title').textContent = 'Lista Importada!';
                document.getElementById('result-title').style.color = 'var(--color-success)';
                document.getElementById('result-message').innerHTML = `
                    Sua lista IPTV foi importada e atualizada com sucesso.<br>
                    <strong>Canais únicos salvos:</strong> ${data.stats.total_channels}<br>
                    <strong>Esportivos detectados:</strong> ${data.stats.sports_channels}<br>
                    ${data.stats.removed_duplicates ? `<strong>Duplicados removidos:</strong> ${data.stats.removed_duplicates}` : ''}
                `;
                
                // Atualizar estatísticas no topo da tela
                updateStatsCard('stat-total-channels', data.stats.total_channels);
                updateStatsCard('stat-sports-channels', data.stats.sports_channels);
                fetchCategories();

                showToast('Lista M3U importada com sucesso!', 'success');
            } else {
                resultDiv.style.display = 'block';
                if (resultIcon) {
                    resultIcon.style.borderColor = 'var(--color-red)';
                    resultIcon.style.color = 'var(--color-red)';
                    resultIcon.style.background = 'rgba(239, 68, 68, 0.1)';
                    resultIcon.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                }
                document.getElementById('result-title').textContent = 'Falha na Importação';
                document.getElementById('result-title').style.color = 'var(--color-red)';
                document.getElementById('result-message').textContent = data.error || 'Ocorreu um erro desconhecido.';
                showToast('Erro ao processar lista M3U.', 'error');
            }
        }, 500);
    })
    .catch(err => {
        clearTimeout(timeoutId);
        clearInterval(progressInterval);
        loader.style.display = 'none';
        resultDiv.style.display = 'block';
        const resultIcon = document.getElementById('import-result-icon');
        if (resultIcon) {
            resultIcon.style.borderColor = 'var(--color-red)';
            resultIcon.style.color = 'var(--color-red)';
            resultIcon.style.background = 'rgba(239, 68, 68, 0.1)';
            resultIcon.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        }
        document.getElementById('result-title').textContent = 'Falha na Importação';
        document.getElementById('result-title').style.color = 'var(--color-red)';
        let msg = err && err.message ? err.message : 'Erro desconhecido.';
        if (err && err.name === 'AbortError') {
            msg = 'A importação demorou demais (timeout). Tente de novo ou use um arquivo .m3u menor.';
        }
        if (msg.includes('Failed to fetch') || msg.includes('NetworkError')) {
            msg = 'Conexão interrompida. Deixe o iniciar.bat aberto e aguarde — listas grandes levam vários minutos.';
        }
        document.getElementById('result-message').textContent = msg;
        showToast('Erro na importação.', 'error');
    });
}

function updateStatsCard(elementId, value) {
    const el = document.getElementById(elementId);
    if (el) {
        el.textContent = value;
    }
}

/* ==========================================
   GERENCIAMENTO E FILTRAGEM DE CANAIS
   ========================================== */
window.fetchChannels = function(page = 1) {
    appState.channels.page = page;
    const container = document.getElementById('channels-container');
    const paginationContainer = document.getElementById('pagination-container');
    
    if (!container) return;

    // Loader animado
    container.innerHTML = `
        <div class="empty-state">
            <div class="spinner" style="margin: 0 auto 15px;"></div>
            <h3>Carregando Canais</h3>
            <p>Buscando canais no banco de dados...</p>
        </div>
    `;

    const groupParam = appState.channels.category ? `&group=${encodeURIComponent(appState.channels.category)}` : '';
    const searchUrl = `ajax/get_channels.php?page=${page}&search=${encodeURIComponent(appState.channels.search)}&sports_only=${appState.channels.sportsOnly}${groupParam}`;

    fetch(searchUrl)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            appState.channels.totalPages = data.pagination.total_pages;
            renderChannelsList(data.channels);
            renderPagination(data.pagination);
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-triangle-exclamation" style="color: var(--color-red);"></i>
                    <h3>Erro ao Carregar</h3>
                    <p>${data.error || 'Não foi possível listar os canais.'}</p>
                </div>
            `;
        }
    })
    .catch(() => {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-wifi" style="color: var(--color-red);"></i>
                <h3>Erro de Conexão</h3>
                <p>Não foi possível conectar ao servidor.</p>
            </div>
        `;
    });
};

function renderChannelCard(chan) {
    const card = document.createElement('div');
    card.className = `channel-card ${chan.is_sports == 1 ? 'is-sports-active' : ''}`;
    card.id = `channel-card-${chan.id}`;

    const logoHtml = chan.logo ?
        `<img src="${chan.logo}" alt="" onerror="handleLogoError(this)">` :
        `<div class="channel-logo-placeholder"><i class="fa-solid fa-tv"></i></div>`;

    const groupLabel = chan.group_name && chan.group_name.trim() !== '' ? chan.group_name : 'Sem Categoria';

    card.innerHTML = `
        <div class="channel-logo-wrapper">${logoHtml}</div>
        <div class="channel-info-wrapper">
            <div class="channel-name" title="${escapeHtml(chan.name)}">${escapeHtml(chan.name)}</div>
            <div class="channel-group" title="${escapeHtml(groupLabel)}">${escapeHtml(groupLabel)}</div>
        </div>
        <div class="channel-actions">
            <label class="switch-control" title="Marcar como esportivo">
                <input type="checkbox" ${chan.is_sports == 1 ? 'checked' : ''} onchange="toggleChannelSports(${chan.id})">
                <span class="switch-slider"></span>
            </label>
            <button type="button" class="btn-channel-play" title="Testar transmissão">
                <i class="fa-solid fa-play"></i>
            </button>
        </div>
    `;

    const playBtn = card.querySelector('.btn-channel-play');
    if (playBtn) {
        playBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openChannelPlayer(chan);
        });
    }

    return card;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

window.closeChannelPlayer = function() {
    const modal = document.getElementById('channel-player-modal');
    const frame = document.getElementById('channel-player-frame');

    if (frame) {
        frame.src = 'about:blank';
    }
    if (modal) {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    }
};

window.openChannelPlayer = function(chan) {
    const modal = document.getElementById('channel-player-modal');
    const frame = document.getElementById('channel-player-frame');
    const title = document.getElementById('channel-player-title');
    const status = document.getElementById('channel-player-status');
    const urlEl = document.getElementById('channel-player-url');

    if (!modal || !frame || !chan || !chan.id) {
        showToast('Canal inválido.', 'error');
        return;
    }

    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    title.textContent = chan.name || 'Canal';
    status.textContent = 'Sintonizando...';
    status.style.color = '';
    urlEl.textContent = chan.url || '';

    frame.src = `player_embed.php?id=${encodeURIComponent(chan.id)}&t=${Date.now()}`;

    const syncStatus = () => {
        try {
            const doc = frame.contentDocument;
            if (!doc) return;
            const err = doc.querySelector('.player-error');
            const st = doc.querySelector('.player-status');
            
            if (!st && !err) {
                status.textContent = 'Sintonizando...';
                status.style.color = '';
                return;
            }
            if (err && err.offsetParent !== null) {
                status.textContent = err.textContent.trim() || 'Não foi possível sintonizar.';
                status.style.color = 'var(--color-red)';
                return;
            }
            if (st && st.style.display !== 'none') {
                status.textContent = st.textContent.trim() || 'Sintonizando...';
                status.style.color = '';
                return;
            }
            status.textContent = 'Reproduzindo';
            status.style.color = 'var(--color-success)';
        } catch (e) { /* ignore */ }
    };

    frame.onload = syncStatus;
    const statusPoll = setInterval(() => {
        if (!modal.classList.contains('active')) {
            clearInterval(statusPoll);
            return;
        }
        syncStatus();
    }, 800);
};

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeChannelPlayer();
});

function renderChannelsList(channelsList) {
    const container = document.getElementById('channels-container');
    if (channelsList.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-folder-open"></i>
                <h3>Nenhum Canal Encontrado</h3>
                <p>Importe uma lista M3U ou ajuste seus filtros de busca para encontrar canais.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = '';

    // Agrupar visualmente por categoria na página atual
    const groups = {};
    channelsList.forEach(chan => {
        const g = chan.group_name && chan.group_name.trim() !== '' ? chan.group_name : 'Sem Categoria';
        if (!groups[g]) groups[g] = [];
        groups[g].push(chan);
    });

    const sortedGroupNames = Object.keys(groups).sort((a, b) => a.localeCompare(b, 'pt-BR'));

    sortedGroupNames.forEach(groupName => {
        const section = document.createElement('div');
        section.className = 'channel-category-section';

        const header = document.createElement('div');
        header.className = 'channel-category-header';
        header.innerHTML = `
            <span class="channel-category-title"><i class="fa-solid fa-folder"></i> ${groupName}</span>
            <span class="channel-category-count">${groups[groupName].length} canal(is)</span>
        `;
        section.appendChild(header);

        const grid = document.createElement('div');
        grid.className = 'channel-grid-inner';
        groups[groupName].forEach(chan => grid.appendChild(renderChannelCard(chan)));
        section.appendChild(grid);

        container.appendChild(section);
    });
}

window.fetchCategories = function() {
    const select = document.getElementById('channel-category-filter');
    const chips = document.getElementById('category-chips');
    if (!select) return;

    const params = new URLSearchParams({
        search: appState.channels.search,
        sports_only: appState.channels.sportsOnly
    });

    fetch(`ajax/get_categories.php?${params}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            appState.channels.categories = data.categories;
            const current = appState.channels.category;

            select.innerHTML = '<option value="">Todas as categorias</option>';
            data.categories.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat.category_name;
                opt.textContent = `${cat.category_name} (${cat.total})`;
                if (current === cat.category_name) opt.selected = true;
                select.appendChild(opt);
            });

            if (chips) {
                chips.innerHTML = '';
                const allChip = document.createElement('button');
                allChip.type = 'button';
                allChip.className = `category-chip ${current === '' ? 'active' : ''}`;
                allChip.textContent = 'Todas';
                allChip.onclick = () => {
                    appState.channels.category = '';
                    select.value = '';
                    fetchChannels(1);
                    renderCategoryChips();
                };
                chips.appendChild(allChip);

                data.categories.slice(0, 24).forEach(cat => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `category-chip ${current === cat.category_name ? 'active' : ''}`;
                    btn.textContent = `${cat.category_name} (${cat.total})`;
                    btn.onclick = () => {
                        appState.channels.category = cat.category_name;
                        select.value = cat.category_name;
                        fetchChannels(1);
                        renderCategoryChips();
                    };
                    chips.appendChild(btn);
                });
            }
        })
        .catch(() => {});
};

function renderCategoryChips() {
    const chips = document.getElementById('category-chips');
    if (!chips) return;
    chips.querySelectorAll('.category-chip').forEach(btn => {
        const label = btn.textContent.replace(/\s\(\d+\)$/, '').trim();
        if (label === 'Todas') {
            btn.classList.toggle('active', appState.channels.category === '');
        } else {
            btn.classList.toggle('active', appState.channels.category === label || btn.textContent.startsWith(appState.channels.category));
        }
    });
}

window.filterByCategory = function() {
    const select = document.getElementById('channel-category-filter');
    appState.channels.category = select ? select.value : '';
    renderCategoryChips();
    fetchChannels(1);
};

window.dedupeChannels = function() {
    if (!confirm('Remover canais duplicados (mesmo nome na mesma categoria)?')) return;

    showToast('Removendo duplicados...', 'info');
    fetch('ajax/dedupe_channels.php', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                updateStatsCard('stat-total-channels', data.stats.after);
                fetchCategories();
                fetchChannels(1);
            } else {
                showToast(data.error || 'Erro ao remover duplicados.', 'error');
            }
        })
        .catch(() => showToast('Erro de conexão.', 'error'));
};

window.handleLogoError = function(img) {
    const parent = img.parentElement;
    parent.innerHTML = `<div class="channel-logo-placeholder"><i class="fa-solid fa-tv"></i></div>`;
};

// Alterna o status do canal como esportivo (individual)
window.toggleChannelSports = function(id) {
    const formData = new FormData();
    formData.append('id', id);

    fetch('ajax/toggle_channel.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById(`channel-card-${id}`);
            if (card) {
                if (data.is_sports == 1) {
                    card.classList.add('is-sports-active');
                } else {
                    card.classList.remove('is-sports-active');
                }
            }
            
            // Atualizar contadores
            const sportsVal = document.getElementById('stat-sports-channels');
            if (sportsVal) {
                let current = parseInt(sportsVal.textContent);
                sportsVal.textContent = data.is_sports ? current + 1 : current - 1;
            }

            showToast(data.message, 'success');
        } else {
            showToast(data.error || 'Erro ao alterar canal.', 'error');
        }
    })
    .catch(() => {
        showToast('Erro ao enviar requisição.', 'error');
    });
};

// Aciona o Filtro Inteligente via Robô/Keywords
window.runAutoFilter = function() {
    showToast('Executando filtro inteligente de canais...', 'info');

    fetch('ajax/auto_filter.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            
            // Atualizar estatísticas e recarregar lista
            updateStatsCard('stat-sports-channels', data.stats.sports_channels);
            fetchCategories();
            fetchChannels(1);
        } else {
            showToast(data.error || 'Erro ao aplicar autofiltro.', 'error');
        }
    })
    .catch(() => {
        showToast('Erro de conexão ao rodar filtro automático.', 'error');
    });
};

// Filtro de Busca por Input
let searchTimeout;
window.filterChannels = function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const query = document.getElementById('channel-search').value;
        appState.channels.search = query;
        fetchCategories();
        fetchChannels(1);
    }, 300);
};

// Alternar Filtro "Apenas Esportivos"
window.toggleSportsFilter = function() {
    const btn = document.getElementById('btn-filter-sports');
    if (appState.channels.sportsOnly === 0) {
        appState.channels.sportsOnly = 1;
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-primary');
        btn.innerHTML = `<i class="fa-solid fa-filter-circle-xmark"></i> Todos Canais`;
    } else {
        appState.channels.sportsOnly = 0;
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
        btn.innerHTML = `<i class="fa-solid fa-filter"></i> Apenas Esportivos`;
    }
    fetchCategories();
    fetchChannels(1);
};

// Paginação HTML Render
function renderPagination(pagination) {
    const container = document.getElementById('pagination-container');
    if (!container) return;

    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = '';

    // Botão Anterior
    const prevBtn = document.createElement('button');
    prevBtn.className = `page-btn ${pagination.current_page === 1 ? 'disabled' : ''}`;
    prevBtn.innerHTML = `<i class="fa-solid fa-chevron-left"></i>`;
    prevBtn.onclick = () => fetchChannels(pagination.current_page - 1);
    container.appendChild(prevBtn);

    // Exibir páginas inteligentes (ex: max 5)
    let startPage = Math.max(1, pagination.current_page - 2);
    let endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `page-btn ${pagination.current_page === i ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.onclick = () => fetchChannels(i);
        container.appendChild(pageBtn);
    }

    // Botão Próximo
    const nextBtn = document.createElement('button');
    nextBtn.className = `page-btn ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}`;
    nextBtn.innerHTML = `<i class="fa-solid fa-chevron-right"></i>`;
    nextBtn.onclick = () => fetchChannels(pagination.current_page + 1);
    container.appendChild(nextBtn);
}


/* ==========================================
   CADASTRO E VENDA DE JOGOS
   ========================================== */
window.fetchGames = function() {
    const container = document.getElementById('games-list-container');
    if (!container) return;

    container.innerHTML = `
        <div class="empty-state" style="padding: 30px 20px;">
            <div class="spinner" style="margin: 0 auto 10px; width: 40px; height: 40px;"></div>
            <h3>Carregando Jogos à Venda</h3>
        </div>
    `;

    fetch('ajax/get_games.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            appState.games = data.games;
            renderGamesList(data.games);
            // Atualizar contador de vendas
            updateStatsCard('stat-total-games', data.games.length);
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <p style="color: var(--color-red);">${data.error || 'Erro ao carregar jogos.'}</p>
                </div>
            `;
        }
    })
    .catch(() => {
        container.innerHTML = `
            <div class="empty-state">
                <p style="color: var(--color-red);">Erro ao comunicar com o servidor.</p>
            </div>
        `;
    });
};

function formatDateTime(isoString) {
    if (!isoString) return '';
    try {
        const d = new Date(isoString.replace(' ', 'T'));
        if (isNaN(d.getTime())) return isoString;
        const pad = (n) => String(n).padStart(2, '0');
        return `${pad(d.getDate())}/${pad(d.getMonth()+1)} às ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch (e) {
        return isoString;
    }
}

function renderGamesList(gamesList) {
    const container = document.getElementById('games-list-container');
    if (!container) return;

    if (gamesList.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-ticket" style="font-size: 40px;"></i>
                <h3>Nenhum Jogo Cadastrado</h3>
                <p>Cadastre jogos de futebol vinculados aos seus canais esportivos para colocá-los à venda.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = '';
    gamesList.forEach(game => {
        const ticket = document.createElement('div');
        ticket.className = 'game-ticket';
        
        // Tratar imagem com placeholder se falhar
        const logoHtml = game.channel_logo ? 
            `<img src="${game.channel_logo}" alt="${game.channel_name}" onerror="handleLogoError(this)">` : 
            `<i class="fa-solid fa-tv" style="font-size: 14px; color: var(--color-purple-light);"></i>`;

        // Formatar preço
        const formattedPrice = game.price > 0 ? 
            `R$ ${parseFloat(game.price).toFixed(2).replace('.', ',')}` : 
            'GRÁTIS';

        const dateHtml = game.game_date ? 
            `<div class="game-ticket-date" style="font-size: 11px; color: var(--color-purple-light); font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-calendar-days"></i> ${formatDateTime(game.game_date)}</div>` : 
            '';

        ticket.innerHTML = `
            <div class="game-ticket-main">
                <div class="game-ticket-channel">
                    <div class="game-channel-logo">
                        ${logoHtml}
                    </div>
                    <span class="game-channel-name">${game.channel_name || 'Canal Desconhecido'}</span>
                </div>
                <div class="game-ticket-title" title="${game.name}">${game.name}</div>
                ${dateHtml}
                <div class="game-ticket-desc">${game.description}</div>
                <div class="game-ticket-footer">
                    <span class="game-price-tag">${formattedPrice}</span>
                    <span class="game-status-badge" style="background: ${game.status === 'ativo' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'}; color: ${game.status === 'ativo' ? 'var(--color-success)' : 'var(--color-red)'}; border-color: ${game.status === 'ativo' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)'};">
                        ${game.status}
                    </span>
                </div>
            </div>
            
            <div class="game-ticket-divider">
                <div class="ticket-notch top"></div>
                <div class="ticket-line"></div>
                <div class="ticket-notch bottom"></div>
            </div>

            <div class="game-ticket-actions">
                <button class="ticket-action-btn edit" onclick="editGame(${game.id})" title="Editar Jogo">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="ticket-action-btn link" onclick="copyStreamLink(${game.id})" title="Copiar Link de Venda / Transmissão">
                    <i class="fa-solid fa-copy"></i>
                </button>
                <button class="ticket-action-btn delete" onclick="deleteGame(${game.id})" title="Excluir Jogo">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
        `;

        container.appendChild(ticket);
    });
}

// Preenche o campo <select> de canais esportivos
window.fetchSportsChannelsForDropdown = function() {
    const dropdown = document.getElementById('game_channel_id');
    if (!dropdown) return;

    dropdown.innerHTML = '<option value="">Carregando canais esportivos...</option>';

    fetch('ajax/get_channels.php?sports_only=1&limit=1000')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            dropdown.innerHTML = '';
            
            if (data.channels.length === 0) {
                dropdown.innerHTML = '<option value="">Nenhum canal esportivo cadastrado. Vá em "Gerenciar Canais".</option>';
                return;
            }

            dropdown.innerHTML = '<option value="">-- Selecione o Canal Esportivo --</option>';
            data.channels.forEach(chan => {
                const opt = document.createElement('option');
                opt.value = chan.id;
                opt.textContent = `${chan.name} (${chan.group_name || 'Sem Grupo'})`;
                dropdown.appendChild(opt);
            });

            // Se estivermos editando um jogo, selecionar o canal correto após carregar
            if (appState.editingGameId !== null) {
                const game = appState.games.find(g => g.id == appState.editingGameId);
                if (game) {
                    dropdown.value = game.channel_id;
                }
            }
        } else {
            dropdown.innerHTML = '<option value="">Erro ao carregar canais.</option>';
        }
    })
    .catch(() => {
        dropdown.innerHTML = '<option value="">Erro de conexão.</option>';
    });
};

// Cadastra ou Salva Jogo
window.saveGame = function(e) {
    e.preventDefault();
    const form = document.getElementById('form-game');
    const formData = new FormData(form);

    fetch('ajax/save_game.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            resetGameForm();
            fetchGames();
        } else {
            showToast(data.error || 'Erro ao salvar jogo.', 'error');
        }
    })
    .catch(() => {
        showToast('Erro ao conectar ao servidor para salvar.', 'error');
    });
};

// Abre um jogo para edição no formulário
window.editGame = function(id) {
    const game = appState.games.find(g => g.id == id);
    if (!game) return;

    appState.editingGameId = id;
    
    // Mudar textos do form
    document.getElementById('form-game-title').innerHTML = `<i class="fa-solid fa-pen-to-square"></i> Editar Jogo: ${game.name}`;
    document.getElementById('btn-save-text').textContent = 'Atualizar Jogo';
    document.getElementById('btn-cancel-edit').style.display = 'inline-flex';

    // Preencher campos
    document.getElementById('game_id').value = game.id;
    document.getElementById('game_name').value = game.name;
    document.getElementById('game_description').value = game.description;
    document.getElementById('game_channel_id').value = game.channel_id;
    document.getElementById('game_date').value = game.game_date ? game.game_date.replace(' ', 'T').substring(0, 16) : '';
    
    // Formatar preço para o form
    const priceStr = parseFloat(game.price).toFixed(2).replace('.', ',');
    document.getElementById('game_price').value = priceStr;
    
    document.getElementById('game_external_link').value = game.external_link || '';
    document.getElementById('game_status').value = game.status;

    // Rolar a tela suavemente para o formulário
    document.getElementById('form-game').scrollIntoView({ behavior: 'smooth' });
};

// Reseta o formulário de cadastros
window.resetGameForm = function() {
    appState.editingGameId = null;
    document.getElementById('form-game').reset();
    document.getElementById('game_id').value = '';
    
    document.getElementById('form-game-title').innerHTML = `<i class="fa-solid fa-circle-plus"></i> Novo Jogo para Venda`;
    document.getElementById('btn-save-text').textContent = 'Colocar para Venda';
    document.getElementById('btn-cancel-edit').style.display = 'none';
};

// Exclui jogo cadastrado
window.deleteGame = function(id) {
    if (!confirm('Deseja realmente excluir este jogo?')) return;

    const formData = new FormData();
    formData.append('id', id);

    fetch('ajax/delete_game.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            fetchGames();
        } else {
            showToast(data.error || 'Erro ao excluir.', 'error');
        }
    })
    .catch(() => {
        showToast('Erro de conexão ao excluir.', 'error');
    });
};

// Copiar Link de Venda ou Transmissão
window.copyStreamLink = function(id) {
    const game = appState.games.find(g => g.id == id);
    if (!game) return;

    let linkToCopy = '';
    if (game.external_link && game.external_link.trim() !== '') {
        linkToCopy = game.external_link;
    } else {
        // Link gerado dinamicamente para o cliente final assistir / comprar no sistema
        // Obter URL base do sistema local ou Hostinger
        const base = window.location.href.split('#')[0].split('?')[0];
        linkToCopy = `${base}assistir.php?jogo=${game.id}`;
    }

    navigator.clipboard.writeText(linkToCopy)
    .then(() => {
        showToast('Link copiado para a Área de Transferência!', 'success');
    })
    .catch(() => {
        showToast('Falha ao copiar link automaticamente.', 'error');
    });
};

/* ==========================================
   UTILITÁRIO: TOAST NOTIFICATIONS
   ========================================== */
window.showToast = function(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = '<i class="fa-solid fa-circle-info"></i>';
    if (type === 'success') {
        icon = '<i class="fa-solid fa-circle-check"></i>';
    } else if (type === 'error') {
        icon = '<i class="fa-solid fa-circle-exclamation"></i>';
    }

    toast.innerHTML = `
        ${icon}
        <span>${message}</span>
    `;

    container.appendChild(toast);

    // Remover toast após 3 segundos
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) reverse forwards';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
};

/* ==========================================
   GRADE DE JOGOS INTEGRADA (SPA)
   ========================================== */
window.fetchIntegratedCalendar = function() {
    const gamesContainer = document.getElementById('integrated-games-container');
    const channelsContainer = document.getElementById('integrated-channels-container');
    const channelsCountSpan = document.getElementById('integrated-channels-count');
    if (!gamesContainer || !channelsContainer) return;

    gamesContainer.innerHTML = `
        <div class="empty-state" style="padding: 30px 20px;">
            <div class="spinner" style="margin: 0 auto 10px; width: 40px; height: 40px;"></div>
            <h3>Carregando Grade de Transmissões</h3>
        </div>
    `;

    // Load Games
    fetch('ajax/get_games.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const activeGames = data.games.filter(g => g.status === 'ativo');
            if (activeGames.length === 0) {
                gamesContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-calendar-xmark" style="font-size: 40px; color: rgba(124, 58, 237, 0.2); margin-bottom: 15px;"></i>
                        <h3>Nenhum Jogo Agendado</h3>
                        <p>Não há partidas ativas programadas para venda ou transmissão neste momento.</p>
                    </div>
                `;
            } else {
                gamesContainer.innerHTML = '';
                activeGames.forEach(game => {
                    const logoHtml = game.channel_logo ? 
                        `<img src="${game.channel_logo}" alt="Logo" onerror="handleLogoError(this)">` : 
                        `<i class="fa-solid fa-tv" style="font-size: 10px; color: var(--color-purple-light);"></i>`;

                    const dateHtml = game.game_date ? 
                        `<div class="game-ticket-date-integrated" style="font-size: 11px; color: var(--color-purple-light); font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 6px;"><i class="fa-solid fa-calendar-days"></i> ${formatDateTime(game.game_date)}</div>` : 
                        '';

                    const priceHtml = game.price > 0 ? 
                        `R$ ${parseFloat(game.price).toFixed(2).replace('.', ',')}` : 
                        'GRÁTIS';

                    const buttonHtml = game.price > 0 ? 
                        `<a href="assistir.php?jogo=${game.id}" target="_blank" class="btn-ticket-integrated buy"><i class="fa-solid fa-ticket"></i> Adquirir Acesso</a>` : 
                        `<button onclick="playMatchIntegrated(${game.id})" class="btn-ticket-integrated"><i class="fa-solid fa-circle-play"></i> Assistir Agora</button>`;

                    const card = document.createElement('div');
                    card.className = 'game-ticket-integrated';
                    card.innerHTML = `
                        <div class="ticket-header-integrated">
                            <div class="broadcaster-integrated">
                                <div class="broadcaster-logo-integrated">${logoHtml}</div>
                                <span class="broadcaster-name-integrated">${game.channel_name || 'Transmissão Direta'}</span>
                            </div>
                            <div class="badge-live-integrated"><i class="fa-solid fa-circle" style="font-size: 5px;"></i> NO AR</div>
                        </div>
                        <div class="ticket-body-integrated">
                            <h3 class="match-title-integrated" style="margin-bottom: 8px;">${game.name}</h3>
                            ${dateHtml}
                            <p class="match-desc-integrated">${game.description ? game.description.replace(/\n/g, '<br>') : ''}</p>
                        </div>
                        <div class="ticket-footer-integrated">
                            <div class="price-box-integrated">
                                <span class="price-label-integrated">Ingresso</span>
                                <span class="price-value-integrated ${game.price > 0 ? '' : 'free'}">${priceHtml}</span>
                            </div>
                            ${buttonHtml}
                        </div>
                    `;
                    gamesContainer.appendChild(card);
                });
            }
        }
    });

    // Load Sports Channels
    fetch('ajax/get_channels.php?sports_only=1&limit=1000')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const count = data.channels.length;
            if (channelsCountSpan) channelsCountSpan.textContent = count;

            if (count === 0) {
                channelsContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-tv" style="font-size: 40px; color: rgba(124, 58, 237, 0.2); margin-bottom: 15px;"></i>
                        <h3>Nenhum Canal Esportivo</h3>
                        <p>Nenhum canal da sua lista M3U foi marcado como esportivo ainda.</p>
                    </div>
                `;
            } else {
                channelsContainer.innerHTML = '';
                data.channels.forEach(chan => {
                    const logoHtml = chan.logo ? 
                        `<img src="${chan.logo}" alt="Logo" onerror="handleLogoError(this)">` : 
                        `<i class="fa-solid fa-tv"></i>`;

                    const card = document.createElement('div');
                    card.className = 'channel-card-integrated';
                    card.innerHTML = `
                        <div class="channel-logo-integrated">${logoHtml}</div>
                        <h4 class="channel-name-integrated">${chan.name}</h4>
                        <span class="channel-group-integrated">${chan.group_name || 'Esportes'}</span>
                        <button onclick="playChannelIntegrated(${chan.id})" class="btn-play-channel-integrated"><i class="fa-solid fa-play"></i> Sintonizar Sinal</button>
                    `;
                    channelsContainer.appendChild(card);
                });
            }
        }
    });
};
