<?php
// Este arquivo deve ser incluído nas páginas que têm problemas com o dropdown de notificações
?>
<script>
// Script para corrigir problemas com o dropdown de notificações em páginas específicas
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se o dropdown já foi inicializado
    const notificationsToggle = document.querySelector('.notifications-toggle');
    const notificationsContent = document.querySelector('.notifications-content');
    
    if (notificationsToggle && notificationsContent) {
        // Remover eventos existentes para evitar duplicação
        const newToggle = notificationsToggle.cloneNode(true);
        notificationsToggle.parentNode.replaceChild(newToggle, notificationsToggle);
        
        // Adicionar evento de clique ao botão de notificações
        newToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notificationsContent.classList.toggle('active');
            
            // Carregar notificações via AJAX quando o dropdown for aberto
            if (notificationsContent.classList.contains('active')) {
                // Adicionar um indicador de carregamento
                const notificationsList = notificationsContent.querySelector('.notifications-list');
                if (notificationsList) {
                    notificationsList.innerHTML = '<div class="loading-notifications"><p>Carregando notificações...</p></div>';
                
                    fetch('get_notifications.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.notificacoes && data.notificacoes.length > 0) {
                                let html = '';
                                
                                data.notificacoes.forEach(notificacao => {
                                    let icone = 'notification.png';
                                    switch (notificacao.tipo) {
                                        case 'conquista': icone = 'achievement.png'; break;
                                        case 'batalha': icone = 'battle.png'; break;
                                        case 'troca': icone = 'trade.png'; break;
                                        case 'evento': icone = 'event.png'; break;
                                        case 'sistema': icone = 'system.png'; break;
                                    }
                                    
                                    html += `
                                        <div class="notification-item">
                                            <div class="notification-icon ${notificacao.tipo}">
                                                <img src="img/icons/${icone}" alt="${notificacao.tipo}">
                                            </div>
                                            <div class="notification-content">
                                                <p>${notificacao.mensagem}</p>
                                                <span class="notification-time">${new Date(notificacao.data_criacao).toLocaleDateString('pt-BR')} ${new Date(notificacao.data_criacao).toLocaleTimeString('pt-BR')}</span>
                                            </div>
                                            <form method="post" class="mark-read">
                                                <input type="hidden" name="notificacao_id" value="${notificacao.id}">
                                                <button type="submit" name="marcar_lida" title="Marcar como lida">✓</button>
                                            </form>
                                        </div>
                                    `;
                                });
                                
                                notificationsList.innerHTML = html;
                            } else {
                                notificationsList.innerHTML = '<div class="empty-notifications"><p>Você não tem novas notificações.</p></div>';
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao carregar notificações:', error);
                            notificationsList.innerHTML = '<div class="error-notifications"><p>Erro ao carregar notificações. Tente novamente.</p></div>';
                        });
                }
            }
        });
        
        // Fechar o dropdown ao clicar fora dele
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notifications-dropdown')) {
                notificationsContent.classList.remove('active');
            }
        });
    }
});
</script> 