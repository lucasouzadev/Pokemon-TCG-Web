<?php
// Incluir o arquivo de contadores se ainda não foi incluído
if (!function_exists('carregarContadores')) {
    require_once 'contadores.php';
}

// Carregar contadores se o usuário estiver logado
$contadores = [];
if (isset($_SESSION['usuario_id'])) {
    $contadores = carregarContadores($_SESSION['usuario_id']);
}

// Atualizar a última visita à coleção se estiver na página de coleção
$pagina_atual = basename($_SERVER['PHP_SELF']);
if ($pagina_atual === 'colecao.php' && isset($_SESSION['usuario_id'])) {
    atualizarUltimaVisitaColecao($_SESSION['usuario_id']);
}
?>

<header>
    <div class="container header-container">
        <div class="logo">
            <a href="index.php">
                <img src="img/icons/pokemon-tcg-pocket-logo.png" alt="Pokémon TCG Pocket">
            </a>
        </div>
        
        <nav>
            <ul>
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li>
                        <a href="index.php" class="<?php echo $pagina_atual === 'index.php' ? 'active' : ''; ?>">
                            <img src="img/icons/home.png" alt="Início" class="nav-icon"> 
                            Início
                        </a>
                    </li>
                    <li>
                        <a href="colecao.php" class="<?php echo $pagina_atual === 'colecao.php' ? 'active' : ''; ?>">
                            <img width="32" height="32" src="https://img.icons8.com/windows/32/music-album.png" alt="colecao" class="nav-icon">
                            Coleção
                            <?php if (isset($contadores['cartas_novas']) && $contadores['cartas_novas'] > 0): ?>
                                <span class="nav-badge"><?php echo $contadores['cartas_novas']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="loja.php" class="<?php echo $pagina_atual === 'loja.php' ? 'active' : ''; ?>">
                            <img width="50" height="50" src="https://img.icons8.com/fluency-systems-regular/50/shop.png" alt="loja" class="nav-icon">
                            Loja
                        </a>
                    </li>
                    <li>
                        <a href="trocas.php" class="<?php echo $pagina_atual === 'trocas.php' ? 'active' : ''; ?>">
                            <img width="32" height="32" src="https://img.icons8.com/windows/32/refresh.png" alt="Trocas" class="nav-icon">
                            Trocas
                            <?php if (isset($contadores['trocas_pendentes']) && $contadores['trocas_pendentes'] > 0): ?>
                                <span class="nav-badge"><?php echo $contadores['trocas_pendentes']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="batalha.php" class="<?php echo $pagina_atual === 'batalha.php' ? 'active' : ''; ?>">
                            <img width="32" height="32" src="https://img.icons8.com/windows/32/head-to-head.png" alt="batalha" class="nav-icon">
                            Batalha
                        </a>
                    </li>
                    <li>
                        <a href="estatisticas.php" class="<?php echo $pagina_atual === 'estatisticas.php' ? 'active' : ''; ?>">
                            <img width="50" height="50" src="https://img.icons8.com/fluency-systems-regular/50/bar-chart.png" alt="bar-chart" class="nav-icon">
                            Estatísticas
                        </a>
                    </li>
                    <li>
                        <a href="eventos.php" class="<?php echo $pagina_atual === 'eventos.php' ? 'active' : ''; ?>">
                            <img width="32" height="32" src="https://img.icons8.com/windows/32/coliseum.png" alt="coliseum" class="nav-icon">
                            Eventos
                            <?php if (isset($contadores['eventos_disponiveis']) && $contadores['eventos_disponiveis'] > 0): ?>
                                <span class="nav-badge"><?php echo $contadores['eventos_disponiveis']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="perfil.php" class="<?php echo $pagina_atual === 'perfil.php' ? 'active' : ''; ?>">
                            <img width="50" height="50" src="https://img.icons8.com/fluency-systems-filled/50/user-menu-male.png" alt="Perfil" class="nav-icon">
                            Perfil
                        </a>
                    </li>
                    <li>
                        <div class="notifications-dropdown">
                            <a href="#" class="notifications-toggle">
                                <img width="50" height="50" src="https://img.icons8.com/ios/50/alarm--v1.png" alt="Notificações" class="nav-icon">
                                <?php 
                                $notificacoes_nao_lidas = 0;
                                if (function_exists('contarNotificacoesNaoLidas') && isset($_SESSION['usuario_id'])) {
                                    $notificacoes_nao_lidas = contarNotificacoesNaoLidas($_SESSION['usuario_id']);
                                }
                                if ($notificacoes_nao_lidas > 0): 
                                ?>
                                    <span class="notifications-badge"><?php echo $notificacoes_nao_lidas; ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="notifications-content">
                                <div class="notifications-header">
                                    <h3>Notificações</h3>
                                    <?php if ($notificacoes_nao_lidas > 0): ?>
                                    <div class="mark-all-read">
                                        <form method="post" action="processar_notificacoes.php">
                                            <input type="hidden" name="acao" value="marcar_todas_lidas">
                                            <button type="submit">Marcar todas como lidas</button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="notifications-list">
                                    <?php
                                    if (function_exists('buscarNotificacoesNaoLidas')) {
                                        $notificacoes = buscarNotificacoesNaoLidas($_SESSION['usuario_id']);
                                        
                                        if (empty($notificacoes)): 
                                    ?>
                                        <div class="empty-notifications">
                                            <p>Não há novas notificações</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($notificacoes as $notificacao): ?>
                                            <div class="notification-item unread">
                                                <div class="notification-icon <?php echo htmlspecialchars($notificacao['tipo']); ?>">
                                                    <img src="img/icons/<?php 
                                                        if ($notificacao['tipo'] === 'conquista') {
                                                            echo 'achievement.png';
                                                        } elseif ($notificacao['tipo'] === 'batalha') {
                                                            echo 'battle.png';
                                                        } elseif ($notificacao['tipo'] === 'troca') {
                                                            echo 'trade.png';
                                                        } elseif ($notificacao['tipo'] === 'evento') {
                                                            echo 'event.png';
                                                        } else {
                                                            echo 'system.png';
                                                        }
                                                    ?>" alt="<?php echo ucfirst($notificacao['tipo']); ?>">
                                                </div>
                                                <div class="notification-content">
                                                    <p><?php echo htmlspecialchars($notificacao['mensagem']); ?></p>
                                                    <span class="notification-time"><?php echo date('d/m/Y H:i', strtotime($notificacao['data_criacao'])); ?></span>
                                                </div>
                                                <div class="mark-read">
                                                    <form method="post" action="processar_notificacoes.php">
                                                        <input type="hidden" name="acao" value="marcar_lida">
                                                        <input type="hidden" name="notificacao_id" value="<?php echo $notificacao['id']; ?>">
                                                        <button type="submit">✓</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; 
                                    } else { ?>
                                        <div class="empty-notifications">
                                            <p>Sistema de notificações indisponível</p>
                                        </div>
                                    <?php } ?>
                                </div>
                                
                                <div class="notifications-footer">
                                    <a href="notificacoes.php">Ver todas as notificações</a>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li>
                        <a href="logout.php">
                            <img src="img/icons/logout.png" alt="Sair" class="nav-icon">
                            Sair
                        </a>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="<?php echo $pagina_atual === 'login.php' ? 'active' : ''; ?>">Login</a></li>
                    <li><a href="cadastro.php" class="<?php echo $pagina_atual === 'cadastro.php' ? 'active' : ''; ?>">Cadastro</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header> 