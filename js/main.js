// Função para animação de rolagem suave
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 100,
                behavior: 'smooth'
            });
        }
    });
});

// Animação para cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    
    // Adiciona classe para animação quando o card estiver visível
    function checkVisibility() {
        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const isVisible = (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
            
            if (isVisible) {
                card.classList.add('visible');
            }
        });
    }
    
    // Verifica visibilidade inicial e durante o scroll
    checkVisibility();
    window.addEventListener('scroll', checkVisibility);
});

// Use funções auto-executáveis para isolar o escopo das variáveis
(function() {
    // Verifique se a variável forms já foi declarada
    if (typeof forms === 'undefined') {
        // Se não foi declarada, declare-a
        const forms = document.querySelectorAll('form');
        
        // Resto do código que usa a variável forms
        if (forms) {
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const emailInput = form.querySelector('input[type="email"]');
                    const passwordInput = form.querySelector('input[type="password"]');
                    
                    let isValid = true;
                    
                    if (emailInput && !isValidEmail(emailInput.value)) {
                        alert('Por favor, insira um email válido.');
                        isValid = false;
                    }
                    
                    if (passwordInput && passwordInput.value.length < 6) {
                        alert('A senha deve ter pelo menos 6 caracteres.');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
            });
        }
    } else {
        // Se já foi declarada, apenas use-a
        if (forms) {
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const emailInput = form.querySelector('input[type="email"]');
                    const passwordInput = form.querySelector('input[type="password"]');
                    
                    let isValid = true;
                    
                    if (emailInput && !isValidEmail(emailInput.value)) {
                        alert('Por favor, insira um email válido.');
                        isValid = false;
                    }
                    
                    if (passwordInput && passwordInput.value.length < 6) {
                        alert('A senha deve ter pelo menos 6 caracteres.');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
            });
        }
    }
})();

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Função para o dropdown de notificações
document.addEventListener('DOMContentLoaded', function() {
    // Função auxiliar para adicionar eventos com segurança
    function addSafeEventListener(selector, event, callback) {
        const element = document.querySelector(selector);
        if (element) {
            element.addEventListener(event, callback);
        }
    }
    
    // Código para o modal de cartas (se existir na página)
    const cardModal = document.getElementById('card-modal');
    const cards = document.querySelectorAll('.card');
    
    if (cardModal && cards.length > 0) {
        const closeBtn = document.querySelector('.close');
        
        cards.forEach(card => {
            card.addEventListener('click', function() {
                const cardId = this.getAttribute('data-id');
                
                // Alterar a URL para carta_detalhes.php em vez de get_card_details.php
                fetch('carta_detalhes.php?id=' + cardId + '&format=json')
                    .then(response => response.json())
                    .then(data => {
                        const modalImg = document.getElementById('modal-card-image');
                        const modalName = document.getElementById('modal-card-name');
                        const modalType = document.getElementById('modal-card-type');
                        const modalRarity = document.getElementById('modal-card-rarity');
                        const modalDescription = document.getElementById('modal-card-description');
                        const modalOriginInfo = document.getElementById('modal-card-origin-info');
                        
                        modalImg.src = 'img/cards/' + data.imagem;
                        modalName.textContent = data.nome;
                        modalType.textContent = 'Tipo: ' + data.tipo;
                        modalRarity.textContent = 'Raridade: ' + data.raridade;
                        modalDescription.textContent = data.descricao;
                        
                        // Informações de origem
                        let originHTML = '';
                        if (data.origem === 'troca') {
                            originHTML = `
                                <div class="origin-info troca">
                                    <h3>Carta de Troca</h3>
                                    <p>Esta carta foi obtida através de uma troca com outro jogador.</p>
                                    <p>Data: ${new Date(data.data_obtencao).toLocaleDateString('pt-BR')}</p>
                                </div>
                            `;
                        } else if (data.origem === 'batalha') {
                            originHTML = `
                                <div class="origin-info batalha">
                                    <h3>Troféu de Batalha</h3>
                                    <p>Esta carta foi conquistada como recompensa de uma batalha vitoriosa!</p>
                                    <p>Data: ${new Date(data.data_obtencao).toLocaleDateString('pt-BR')}</p>
                                </div>
                            `;
                        } else if (data.origem === 'evento') {
                            originHTML = `
                                <div class="origin-info evento">
                                    <h3>Carta de Evento</h3>
                                    <p>Esta carta especial foi obtida durante um evento do jogo.</p>
                                    <p>Data: ${new Date(data.data_obtencao).toLocaleDateString('pt-BR')}</p>
                                </div>
                            `;
                        }
                        
                        modalOriginInfo.innerHTML = originHTML;
                        cardModal.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Erro ao buscar detalhes da carta:', error);
                    });
            });
        });
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                cardModal.style.display = 'none';
            });
        }
        
        window.addEventListener('click', function(event) {
            if (event.target === cardModal) {
                cardModal.style.display = 'none';
            }
        });
    }
    
    // Código para o sistema de notificações (se existir na página)
    addSafeEventListener('.notification-icon', 'click', function(e) {
        e.preventDefault();
        const dropdown = document.querySelector('.notification-dropdown');
        if (dropdown) {
            dropdown.classList.toggle('active');
        }
    });
    
    // Fechar dropdown de notificações ao clicar fora
    document.addEventListener('click', function(e) {
        const icon = document.querySelector('.notification-icon');
        const dropdown = document.querySelector('.notification-dropdown');
        
        if (icon && dropdown && !icon.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });
    
    // Código para o sistema de trocas (se estiver na página de trocas)
    const usuarioDestinoSelect = document.getElementById('usuario_destino');
    
    if (usuarioDestinoSelect) {
        usuarioDestinoSelect.addEventListener('change', function() {
            // Código relacionado à seleção de usuário para troca...
        });
    }
    
    // Outros eventos específicos de página podem ser adicionados aqui
    // usando a função addSafeEventListener para evitar erros
});

// Código para o painel lateral de detalhes da carta
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se estamos na página de coleção
    const cardsGrid = document.querySelector('.cards-grid');
    if (!cardsGrid) return;
    
    const cards = document.querySelectorAll('.card');
    const detailsPanel = document.querySelector('.card-details-panel');
    const overlay = document.querySelector('.panel-overlay');
    const closeBtn = document.querySelector('.panel-close');
    
    let selectedCard = null;
    
    // Função para abrir o painel com os detalhes da carta
    function openCardDetails(card) {
        // Remover seleção anterior
        if (selectedCard) {
            selectedCard.classList.remove('selected');
        }
        
        // Selecionar a carta atual
        card.classList.add('selected');
        selectedCard = card;
        
        const cardId = card.getAttribute('data-id');
        const colecaoId = card.getAttribute('data-colecao-id');
        
        // Construir a URL com o ID da carta e o ID da coleção
        let url = 'get_card_details.php?id=' + cardId;
        if (colecaoId) {
            url += '&colecao_id=' + colecaoId;
        }
        
        // Buscar detalhes da carta
        fetch(url)
            .then(response => response.json())
            .then(data => {
                // Verificar se os elementos existem antes de manipulá-los
                const panelCardImage = document.getElementById('panel-card-image');
                const panelCardName = document.getElementById('panel-card-name');
                const panelCardType = document.getElementById('panel-card-type');
                const panelCardRarity = document.getElementById('panel-card-rarity');
                const panelCardQuantity = document.getElementById('panel-card-quantity');
                const panelCardDescription = document.getElementById('panel-card-description');
                const viewDetailsLink = document.getElementById('panel-view-details');
                
                // Preencher o painel com os detalhes, verificando se cada elemento existe
                if (panelCardImage) panelCardImage.src = './img/cards/' + data.imagem;
                if (panelCardName) panelCardName.textContent = data.nome;
                if (panelCardType) panelCardType.textContent = data.tipo;
                if (panelCardRarity) panelCardRarity.textContent = data.raridade;
                if (panelCardQuantity) panelCardQuantity.textContent = data.quantidade || '0';
                if (panelCardDescription) panelCardDescription.textContent = data.descricao || 'Sem descrição disponível';
                
                // Verificar se o elemento existe antes de definir o href
                if (viewDetailsLink) {
                    viewDetailsLink.href = 'carta_detalhes.php?id=' + cardId;
                }
                
                // Informações de origem
                let originHTML = '';
                if (data.origem === 'troca') {
                    originHTML = `
                        <div class="panel-origin-info troca">
                            <h3>Carta de Troca</h3>
                            <p>Esta carta foi obtida através de uma troca com outro jogador.</p>
                            <p>Data: ${new Date(data.data_obtencao).toLocaleDateString('pt-BR')}</p>
                        </div>
                    `;
                } else if (data.origem === 'batalha') {
                    originHTML = `
                        <div class="panel-origin-info batalha">
                            <h3>Troféu de Batalha</h3>
                            <p>Esta carta foi conquistada como recompensa de uma batalha vitoriosa!</p>
                            <p>Data: ${new Date(data.data_obtencao).toLocaleDateString('pt-BR')}</p>
                        </div>
                    `;
                } else if (data.origem === 'evento') {
                    originHTML = `
                        <div class="panel-origin-info evento">
                            <h3>Carta de Evento</h3>
                            <p>Esta carta especial foi obtida durante um evento do jogo.</p>
                            <p>Data: ${new Date(data.data_obtencao).toLocaleDateString('pt-BR')}</p>
                        </div>
                    `;
                } else if (data.origem === 'pacote') {
                    originHTML = `
                        <div class="panel-origin-info pacote">
                            <h3>Carta de Pacote</h3>
                            <p>Esta carta foi obtida ao abrir um pacote de cartas na loja.</p>
                            <p>Data: ${new Date(data.data_obtencao).toLocaleDateString('pt-BR')}</p>
                        </div>
                    `;
                }
                
                // Adicionar as informações de origem ao painel
                const originInfoContainer = document.getElementById('panel-card-origin-info');
                if (originInfoContainer) {
                    originInfoContainer.innerHTML = originHTML;
                }
                
                // Adicionar botão de venda se a quantidade for maior que 1 e estiver na página de coleção
                const panelActions = document.querySelector('.panel-actions');
                if (panelActions && window.location.pathname.includes('colecao.php') && parseInt(data.quantidade) > 1) {
                    // Remover botão de venda existente, se houver
                    const existingSellButton = document.getElementById('panel-sell-card');
                    if (existingSellButton) {
                        existingSellButton.remove();
                    }
                    
                    // Adicionar botão de venda
                    const sellButton = document.createElement('button');
                    sellButton.id = 'panel-sell-card';
                    sellButton.className = 'btn-primary';
                    sellButton.textContent = 'Vender Carta';
                    sellButton.setAttribute('data-card-id', cardId);
                    sellButton.setAttribute('data-colecao-id', data.colecao_id);
                    
                    // Adicionar evento de clique
                    sellButton.addEventListener('click', function() {
                        venderCarta(cardId, data.colecao_id);
                    });
                    
                    panelActions.appendChild(sellButton);
                }
                
                // Verificar se os elementos existem antes de manipulá-los
                if (detailsPanel) detailsPanel.classList.add('active');
                if (overlay) overlay.classList.add('active');
            })
            .catch(error => {
                console.error('Erro ao buscar detalhes da carta:', error);
            });
    }
    
    // Função para fechar o painel
    function closeCardDetails() {
        if (selectedCard) {
            selectedCard.classList.remove('selected');
            selectedCard = null;
        }
        
        if (detailsPanel) detailsPanel.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
    }
    
    // Adicionar evento de clique às cartas
    if (cards && cards.length > 0) {
        cards.forEach(card => {
            card.addEventListener('click', function() {
                openCardDetails(this);
            });
        });
    }
    
    // Fechar o painel ao clicar no botão de fechar
    if (closeBtn) {
        closeBtn.addEventListener('click', closeCardDetails);
    }
    
    // Fechar o painel ao clicar fora dele
    if (overlay) {
        overlay.addEventListener('click', closeCardDetails);
    }
    
    // Fechar o painel ao pressionar ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCardDetails();
        }
    });
});

// Função para verificar se estamos na página de detalhes da carta
function isPaginaDetalhes() {
    // Verificar se o modal de detalhes existe na página
    return document.getElementById('cardDetailsModal') !== null;
}

// Função para exibir detalhes da carta com verificações de segurança
function exibirDetalhesCarta(cartaId) {
    // Verificar se estamos na página correta
    if (!isPaginaDetalhes()) {
        console.log('Página atual não suporta visualização de detalhes da carta');
        return;
    }
    
    try {
        // Verificar se o modal existe
        const modalDetalhes = document.getElementById('cardDetailsModal');
        if (!modalDetalhes) {
            console.error('Modal de detalhes não encontrado no DOM');
            return;
        }
        
        // Mostrar o modal
        modalDetalhes.style.display = 'flex';
        
        // Buscar detalhes da carta via AJAX
        fetch(`carta_detalhes.php?id=${cartaId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                try {
                    // Verificar se os elementos existem antes de manipulá-los
                    const cardImage = modalDetalhes.querySelector('.card-image img');
                    const cardName = modalDetalhes.querySelector('.card-name');
                    const cardType = modalDetalhes.querySelector('.card-type');
                    const cardRarity = modalDetalhes.querySelector('.card-rarity');
                    const cardDescription = modalDetalhes.querySelector('.card-description');
                    
                    // Verificar cada elemento individualmente e definir valores com segurança
                    if (cardImage && data.imagem) {
                        cardImage.src = data.imagem || 'img/cards/placeholder.png';
                    } else {
                        console.warn('Elemento de imagem não encontrado no modal ou dados de imagem ausentes');
                    }
                    
                    if (cardName) cardName.textContent = data.nome || 'Nome não disponível';
                    if (cardType) cardType.textContent = data.tipo || 'Tipo não disponível';
                    if (cardRarity) cardRarity.textContent = data.raridade || 'Raridade não disponível';
                    if (cardDescription) cardDescription.textContent = data.descricao || 'Descrição não disponível';
                    
                    // Esconder indicador de carregamento se existir
                    const loadingIndicator = modalDetalhes.querySelector('.loading-indicator');
                    if (loadingIndicator) loadingIndicator.style.display = 'none';
                    
                    // Mostrar conteúdo se existir
                    const cardContent = modalDetalhes.querySelector('.card-details-content');
                    if (cardContent) cardContent.style.display = 'block';
                } catch (innerError) {
                    console.error('Erro ao processar dados da carta:', innerError);
                }
            })
            .catch(error => {
                console.error('Erro ao buscar detalhes da carta:', error);
                
                // Mostrar mensagem de erro no modal se existir
                const errorMessage = modalDetalhes.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.textContent = 'Não foi possível carregar os detalhes da carta.';
                    errorMessage.style.display = 'block';
                }
                
                // Esconder indicador de carregamento se existir
                const loadingIndicator = modalDetalhes.querySelector('.loading-indicator');
                if (loadingIndicator) loadingIndicator.style.display = 'none';
            });
    } catch (error) {
        console.error('Erro geral na função exibirDetalhesCarta:', error);
    }
}

// Função para fechar o modal de detalhes
function fecharModalDetalhes() {
    const modalDetalhes = document.getElementById('cardDetailsModal');
    if (modalDetalhes) {
        modalDetalhes.style.display = 'none';
    }
}

// Inicializar eventos quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se estamos na página que suporta detalhes da carta
    const temModalDetalhes = isPaginaDetalhes();
    
    // Adicionar evento de clique para cards na coleção ou em outras páginas
    if (temModalDetalhes) {
        const cardLinks = document.querySelectorAll('.card-item[data-card-id]');
        if (cardLinks.length > 0) {
            cardLinks.forEach(card => {
                card.addEventListener('click', function() {
                    const cartaId = this.getAttribute('data-card-id');
                    if (cartaId) {
                        exibirDetalhesCarta(cartaId);
                    }
                });
            });
        }
        
        // Adicionar evento para fechar o modal
        const closeButtons = document.querySelectorAll('.close-modal, .modal-overlay');
        if (closeButtons.length > 0) {
            closeButtons.forEach(button => {
                button.addEventListener('click', fecharModalDetalhes);
            });
        }
        
        // Fechar modal com a tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModalDetalhes();
            }
        });
    }
    
    // Outros eventos e inicializações que não dependem do modal de detalhes
    // ...
});

// Código para o sistema de notificações
document.addEventListener('DOMContentLoaded', function() {
    // Função para gerenciar o dropdown de notificações
    function setupNotificationsDropdown() {
        const notificationsToggle = document.querySelector('.notifications-toggle');
        const notificationsContent = document.querySelector('.notifications-content');
        
        // Verificar se estamos em uma página que deve ter o dropdown
        const currentPage = window.location.pathname.split('/').pop();
        const isNotificationsPage = currentPage === 'notificacoes_pagina.php';
        
        // Se estivermos na página de notificações, não inicializar o dropdown
        if (isNotificationsPage) {
            return;
        }
        
        if (notificationsToggle && notificationsContent) {
            // Adicionar evento de clique ao botão de notificações
            notificationsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                notificationsContent.classList.toggle('active');
                
                // Carregar notificações via AJAX quando o dropdown for aberto
                if (notificationsContent.classList.contains('active')) {
                    // Adicionar um indicador de carregamento
                    notificationsContent.querySelector('.notifications-list').innerHTML = 
                        '<div class="loading-notifications"><p>Carregando notificações...</p></div>';
                    
                    fetch('get_notifications.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Erro na resposta do servidor: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            const notificationsList = document.querySelector('.notifications-list');
                            
                            if (notificationsList) {
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
                                    
                                    // Adicionar eventos aos botões de marcar como lida
                                    document.querySelectorAll('.mark-read').forEach(form => {
                                        form.addEventListener('submit', function(e) {
                                            e.preventDefault();
                                            const notificacaoId = this.querySelector('input[name="notificacao_id"]').value;
                                            
                                            fetch('marcar_notificacao.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/x-www-form-urlencoded',
                                                },
                                                body: `notificacao_id=${notificacaoId}&marcar_lida=1`
                                            })
                                            .then(response => {
                                                if (!response.ok) {
                                                    throw new Error('Erro na resposta do servidor: ' + response.status);
                                                }
                                                return response.json();
                                            })
                                            .then(data => {
                                                if (data.success) {
                                                    // Remover a notificação da lista ou marcá-la como lida
                                                    this.closest('.notification-item').remove();
                                                    
                                                    // Atualizar o contador de notificações
                                                    const badge = document.querySelector('.notifications-badge');
                                                    if (badge) {
                                                        const count = parseInt(badge.textContent) - 1;
                                                        if (count > 0) {
                                                            badge.textContent = count;
                                                        } else {
                                                            badge.remove();
                                                            // Se não houver mais notificações, mostrar mensagem
                                                            if (document.querySelectorAll('.notification-item').length === 0) {
                                                                notificationsList.innerHTML = '<div class="empty-notifications"><p>Você não tem novas notificações.</p></div>';
                                                            }
                                                        }
                                                    }
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Erro ao marcar notificação como lida:', error);
                                                notificationsList.innerHTML = '<div class="error-notifications"><p>Erro ao processar a solicitação. Tente novamente.</p></div>';
                                            });
                                        });
                                    });
                                } else {
                                    notificationsList.innerHTML = '<div class="empty-notifications"><p>Você não tem novas notificações.</p></div>';
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao carregar notificações:', error);
                            const notificationsList = document.querySelector('.notifications-list');
                            if (notificationsList) {
                                notificationsList.innerHTML = '<div class="error-notifications"><p>Erro ao carregar notificações. Tente novamente.</p></div>';
                            }
                        });
                }
            });
            
            // Fechar o dropdown ao clicar fora dele
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.notifications-dropdown')) {
                    notificationsContent.classList.remove('active');
                }
            });
            
            // Adicionar evento ao botão de marcar todas como lidas
            const markAllReadBtn = document.querySelector('.mark-all-read button');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    fetch('marcar_notificacao.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'marcar_todas_lidas=1'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro na resposta do servidor: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Limpar a lista de notificações
                            const notificationsList = document.querySelector('.notifications-list');
                            if (notificationsList) {
                                notificationsList.innerHTML = '<div class="empty-notifications"><p>Você não tem novas notificações.</p></div>';
                            }
                            
                            // Remover o badge
                            const badge = document.querySelector('.notifications-badge');
                            if (badge) {
                                badge.remove();
                            }
                            
                            // Remover o botão de marcar todas como lidas
                            this.closest('.mark-all-read').remove();
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao marcar todas as notificações como lidas:', error);
                        const notificationsList = document.querySelector('.notifications-list');
                        if (notificationsList) {
                            notificationsList.innerHTML = '<div class="error-notifications"><p>Erro ao processar a solicitação. Tente novamente.</p></div>';
                        }
                    });
                });
            }
        }
    }
    
    // Inicializar o dropdown de notificações
    setupNotificationsDropdown();
});

// Função para vender carta
function venderCarta(cardId, colecaoId) {
    // Confirmar a venda
    if (!confirm('Tem certeza que deseja vender esta carta? Você receberá moedas comuns baseadas na raridade da carta.')) {
        return;
    }
    
    // Criar FormData para enviar os dados
    const formData = new FormData();
    formData.append('carta_id', cardId);
    if (colecaoId) {
        formData.append('colecao_id', colecaoId);
    }
    
    // Enviar requisição para vender a carta
    fetch('vender_carta.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            // Exibir mensagem de sucesso
            alert(data.mensagem);
            
            // Atualizar a quantidade da carta no painel
            const panelCardQuantity = document.getElementById('panel-card-quantity');
            if (panelCardQuantity) {
                const quantidade = parseInt(panelCardQuantity.textContent) - 1;
                panelCardQuantity.textContent = quantidade.toString();
            }
            
            // Se a quantidade for 1, remover o botão de venda
            if (parseInt(panelCardQuantity.textContent) <= 1) {
                const sellButton = document.getElementById('panel-sell-card');
                if (sellButton) {
                    sellButton.remove();
                }
            }
            
            // Atualizar o saldo de moedas, se o elemento existir
            const moedas = document.querySelector('.user-coins');
            if (moedas) {
                moedas.textContent = data.moedas;
            }
            
            // Recarregar a página após um breve atraso para mostrar a mensagem
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Exibir mensagem de erro
            alert('Erro: ' + data.mensagem);
        }
    })
    .catch(error => {
        console.error('Erro ao vender carta:', error);
        alert('Ocorreu um erro ao processar a venda. Por favor, tente novamente.');
    });
}
