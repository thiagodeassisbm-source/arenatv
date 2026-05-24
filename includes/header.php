<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arena Stream | Painel Administrativo</title>
    
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome para ícones premium -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Premium -->
        <aside class="sidebar">
            <div class="brand">
                <div class="logo-icon">
                    <i class="fa-solid fa-circle-play"></i>
                </div>
                <div class="brand-text">
                    <h2>ARENA</h2>
                    <span>STREAM</span>
                </div>
            </div>
            
            <nav class="nav-menu">
                <a href="#overview" class="nav-item active" data-tab="overview">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Painel Geral</span>
                </a>
                <a href="#import" class="nav-item" data-tab="import">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Importar M3U</span>
                </a>
                <a href="#channels" class="nav-item" data-tab="channels">
                    <i class="fa-solid fa-tv"></i>
                    <span>Gerenciar Canais</span>
                </a>
                <a href="#games" class="nav-item" data-tab="games">
                    <i class="fa-solid fa-ticket"></i>
                    <span>Vender Jogos</span>
                </a>
                <a href="#calendar" class="nav-item" data-tab="calendar">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Grade de Jogos</span>
                </a>
                <a href="jogos.php" target="_blank" class="nav-item">
                    <i class="fa-solid fa-globe"></i>
                    <span>Grade Pública (Portal)</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="server-status">
                    <span class="status-indicator online"></span>
                    <span class="status-text">Servidor Local Ativo</span>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Header Topo -->
            <header class="main-header">
                <div class="header-title">
                    <h1 id="page-title-display">Painel Geral</h1>
                    <p id="page-subtitle-display">Visão geral do seu sistema de streaming esportivo.</p>
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <span class="user-name">Administrador</span>
                        <span class="user-role">Super Admin</span>
                    </div>
                    <div class="user-avatar">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                </div>
            </header>
            
            <!-- Conteúdo da página injetado aqui -->
            <div class="content-body">
