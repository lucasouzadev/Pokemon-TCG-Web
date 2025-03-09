document.addEventListener('DOMContentLoaded', function() {
    console.log('Script de abertura de pacote carregado'); // Log para debug
    
    const packageAnimation = document.getElementById('packageAnimation');
    const cardsReveal = document.getElementById('cardsReveal');
    const cardItems = document.querySelectorAll('.card-item');
    
    if (!packageAnimation || !cardsReveal) {
        console.error('Elementos não encontrados na página'); // Log para debug
        return;
    }
    
    console.log('Elementos encontrados, adicionando evento de clique'); // Log para debug
    
    // Animação de clique no pacote
    packageAnimation.addEventListener('click', function() {
        console.log('Pacote clicado!'); // Log para debug
        
        // Animação de abertura do pacote
        packageAnimation.style.transform = 'scale(1.2)';
        packageAnimation.style.opacity = '0';
        packageAnimation.style.transition = 'all 0.8s ease';
        
        // Após a animação, mostrar as cartas
        setTimeout(function() {
            packageAnimation.style.display = 'none';
            cardsReveal.style.display = 'block';
            
            // Revelar as cartas uma por uma
            cardItems.forEach((card, index) => {
                setTimeout(() => {
                    revealCard(card);
                }, index * 300);
            });
            
            // Após a animação de abertura do pacote
            setTimeout(function() {
                document.querySelector('.pacote-abertura').classList.remove('pacote-abertura');
            }, 3000); // Tempo da animação em milissegundos
        }, 800);
    });
    
    // Função para revelar uma carta
    function revealCard(card) {
        console.log('Revelando carta'); // Log para debug
        const cardInner = card.querySelector('.card-inner');
        if (cardInner) {
            cardInner.style.transform = 'rotateY(180deg)';
        }
        
        // Adicionar efeito de som (opcional)
        playCardSound(card);
    }
    
    // Função para tocar som de carta (opcional)
    function playCardSound(card) {
        const raridade = card.querySelector('.card-rarity').textContent.trim().toLowerCase();
        let soundFile = 'card_flip.mp3';
        
        // Sons diferentes para raridades diferentes (se implementado)
        if (raridade === 'rara') {
            soundFile = 'rare.mp3';
        } else if (raridade === 'ultra rara') {
            soundFile = 'ultra_rare.mp3';
        }
        
        // Descomente se tiver arquivos de som
        const sound = new Audio(`sounds/${soundFile}`);
        sound.volume = 0.5;
        sound.play();
    }
    
    // Permitir que o usuário clique em uma carta para virá-la manualmente
    cardItems.forEach(card => {
        card.addEventListener('click', function() {
            console.log('Carta clicada manualmente'); // Log para debug
            const cardInner = this.querySelector('.card-inner');
            if (cardInner) {
                cardInner.style.transform = 'rotateY(180deg)';
            }
        });
    });
}); 