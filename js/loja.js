document.addEventListener('DOMContentLoaded', function() {
    // Gerenciar as abas da loja
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    if (tabButtons.length > 0 && tabContents.length > 0) {
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remover classe active de todos os botões e conteúdos
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Adicionar classe active ao botão clicado
                this.classList.add('active');
                
                // Mostrar o conteúdo correspondente
                const tabId = this.getAttribute('data-tab');
                const targetTab = document.getElementById(`${tabId}-tab`);
                if (targetTab) {
                    targetTab.classList.add('active');
                }
            });
        });
    }
    
    // Efeito neon pulsante para os cards de pacotes
    const packageCards = document.querySelectorAll('.package-card');
    
    if (packageCards.length > 0) {
        // Função para criar efeito de pulso neon
        function pulseNeonEffect() {
            packageCards.forEach(card => {
                // Verificar se é um pacote premium
                const isPremium = card.classList.contains('premium');
                
                // Cores diferentes para pacotes comuns e premium
                const baseColor = isPremium ? 'rgba(212, 175, 55,' : 'rgba(52, 152, 219,';
                
                // Gerar um valor aleatório para a intensidade do brilho
                const intensity = 0.2 + Math.random() * 0.3; // Entre 0.2 e 0.5 (mais suave)
                
                // Aplicar o efeito de brilho
                card.style.boxShadow = `0 0 15px ${baseColor}${intensity})`;
                card.style.borderColor = `${baseColor}${intensity * 0.8})`;
            });
        }
        
        // Iniciar o efeito de pulso e repetir a cada 2 segundos
        pulseNeonEffect();
        setInterval(pulseNeonEffect, 2000);
        
        // Efeito de hover mais intenso
        packageCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                const isPremium = this.classList.contains('premium');
                const glowColor = isPremium ? 'rgba(212, 175, 55, 0.6)' : 'rgba(52, 152, 219, 0.6)';
                
                this.style.transform = 'translateY(-10px) scale(1.03)';
                this.style.boxShadow = `0 0 25px ${glowColor}`;
                this.style.borderColor = isPremium ? 'rgba(212, 175, 55, 0.4)' : 'rgba(52, 152, 219, 0.4)';
                
                // Efeito de zoom na imagem
                const img = this.querySelector('img');
                if (img) {
                    img.style.transform = 'scale(1.05)';
                }
            });
            
            card.addEventListener('mouseleave', function() {
                const isPremium = this.classList.contains('premium');
                const glowColor = isPremium ? 'rgba(212, 175, 55, 0.3)' : 'rgba(52, 152, 219, 0.3)';
                
                this.style.transform = '';
                this.style.boxShadow = `0 0 15px ${glowColor}`;
                this.style.borderColor = isPremium ? 'rgba(212, 175, 55, 0.2)' : 'rgba(52, 152, 219, 0.2)';
                
                // Resetar zoom na imagem
                const img = this.querySelector('img');
                if (img) {
                    img.style.transform = '';
                }
            });
        });
    }
    
    // Verificar se o usuário tem moedas suficientes
    const buyButtons = document.querySelectorAll('button[name="comprar_pacote"]');
    
    if (buyButtons.length > 0) {
        // Pré-carregar o som de abertura do pacote
        const packOpenSound = new Audio('sounds/pack_open.mp3');
        packOpenSound.volume = 0.7;
        packOpenSound.load();
        
        buyButtons.forEach(button => {
            const form = button.closest('form');
            const packageCard = button.closest('.package-card');
            
            if (packageCard) {
                const priceElement = packageCard.querySelector('.package-price span');
                
                if (priceElement) {
                    const price = parseInt(priceElement.textContent);
                    const isPremium = packageCard.classList.contains('premium');
                    
                    // Obter saldo atual - verificar se os elementos existem primeiro
                    const commonBalanceElement = document.querySelector('.balance-item:not(.premium) span');
                    const premiumBalanceElement = document.querySelector('.balance-item.premium span');
                    
                    if (commonBalanceElement && premiumBalanceElement) {
                        const commonBalance = parseInt(commonBalanceElement.textContent);
                        const premiumBalance = parseInt(premiumBalanceElement.textContent);
                        
                        // Verificar se o usuário tem saldo suficiente
                        if ((isPremium && premiumBalance < price) || (!isPremium && commonBalance < price)) {
                            button.disabled = true;
                            button.textContent = 'Moedas insuficientes';
                            button.classList.add('disabled');
                        }
                    }
                }
            }
            
            // Adicionar animação ao clicar no botão
            button.addEventListener('click', function(e) {
                if (!button.disabled) {
                    // Adicionar classe para efeito de clique
                    button.classList.add('clicked');
                    
                    // Tocar som de abertura do pacote
                    try {
                        packOpenSound.play().catch(e => console.log('Erro ao tocar som:', e));
                    } catch (error) {
                        console.log('Erro ao tocar som:', error);
                    }
                }
            });
        });
    }
    
    // Adicionar efeito de brilho aos botões
    function addButtonGlowEffects() {
        const primaryButtons = document.querySelectorAll('.btn-primary');
        const premiumButtons = document.querySelectorAll('.btn-premium');
        const specialButtons = document.querySelectorAll('.btn-special:not([disabled])');
        
        // Efeito para botões primários
        primaryButtons.forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                btn.style.boxShadow = '0 0 15px rgba(52, 152, 219, 0.6)';
            });
            
            btn.addEventListener('mouseleave', () => {
                btn.style.boxShadow = '0 0 10px rgba(52, 152, 219, 0.3)';
            });
        });
        
        // Efeito para botões premium
        premiumButtons.forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                btn.style.boxShadow = '0 0 15px rgba(212, 175, 55, 0.6)';
            });
            
            btn.addEventListener('mouseleave', () => {
                btn.style.boxShadow = '0 0 10px rgba(212, 175, 55, 0.3)';
            });
        });
        
        // Efeito para botões especiais
        specialButtons.forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                btn.style.boxShadow = '0 0 20px rgba(231, 76, 60, 0.5)';
            });
            
            btn.addEventListener('mouseleave', () => {
                btn.style.boxShadow = '0 0 15px rgba(231, 76, 60, 0.3)';
            });
        });
    }
    
    // Inicializar efeitos de brilho nos botões
    addButtonGlowEffects();
}); 