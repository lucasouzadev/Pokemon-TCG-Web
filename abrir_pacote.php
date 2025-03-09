<?php
session_start();
require_once 'config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar se há cartas para exibir
if (!isset($_SESSION['cartas_abertas']) || !isset($_SESSION['pacote_aberto'])) {
    header('Location: loja.php');
    exit;
}

$cartas = $_SESSION['cartas_abertas'];
$pacote = $_SESSION['pacote_aberto'];

// Verificar se há cartas raras ou ultra raras
$tem_rara = false;
$tem_ultra_rara = false;

foreach ($cartas as $carta) {
    if ($carta['raridade'] == 'Rara') {
        $tem_rara = true;
    } else if ($carta['raridade'] == 'Ultra Rara') {
        $tem_ultra_rara = true;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrindo Pacote - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .open-package {
            padding: 40px 0;
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(5px);
        }
        
        h1 {
            margin-bottom: 30px;
            color: #333;
            font-size: 3rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            display: inline-block;
            font-weight: 800;
            background: linear-gradient(45deg, #3498db, #2ecc71, #f1c40f);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: 1px;
        }
        
        h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 4px;
            background: linear-gradient(90deg, #3498db, #2ecc71, #f1c40f);
            border-radius: 2px;
        }
        
        .package-stage {
            margin: 0 auto 40px;
            position: relative;
            cursor: pointer;
            max-width: 300px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            perspective: 1000px;
        }
        
        .package-animation {
            transform-style: preserve-3d;
            transition: transform 0.8s ease;
        }
        
        .package-stage:hover .package-animation {
            transform: translateY(-10px) rotateY(10deg);
        }
        
        .package-image {
            width: 100%;
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.5s ease;
        }
        
        .package-glow {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 15px;
            background: radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.8), transparent 70%);
            opacity: 0;
            transition: opacity 0.5s ease;
            pointer-events: none;
        }
        
        .package-stage:hover .package-glow {
            opacity: 0.7;
        }
        
        .click-instruction {
            position: absolute;
            bottom: -40px;
            left: 0;
            right: 0;
            text-align: center;
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
            100% { opacity: 0.6; transform: scale(1); }
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin: 40px 0;
            perspective: 1000px;
        }
        
        .card-item {
            perspective: 1000px;
            width: 200px;
            height: 280px;
            margin: 0 auto;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }
        
        .card-item:hover {
            transform: translateY(-10px);
        }
        
        /* Efeitos de borda brilhante para diferentes raridades */
        @keyframes borderGlowComum {
            0% { box-shadow: 0 0 5px 2px rgba(255, 255, 255, 0.5); }
            50% { box-shadow: 0 0 10px 3px rgba(255, 255, 255, 0.7); }
            100% { box-shadow: 0 0 5px 2px rgba(255, 255, 255, 0.5); }
        }
        
        @keyframes borderGlowIncomum {
            0% { box-shadow: 0 0 5px 2px rgba(165, 214, 167, 0.5); }
            50% { box-shadow: 0 0 10px 3px rgba(165, 214, 167, 0.7); }
            100% { box-shadow: 0 0 5px 2px rgba(165, 214, 167, 0.5); }
        }
        
        @keyframes borderGlowRara {
            0% { box-shadow: 0 0 5px 2px rgba(30, 144, 255, 0.5); }
            50% { box-shadow: 0 0 15px 5px rgba(30, 144, 255, 0.8); }
            100% { box-shadow: 0 0 5px 2px rgba(30, 144, 255, 0.5); }
        }
        
        @keyframes borderGlowUltraRara {
            0% { box-shadow: 0 0 10px 2px rgba(255, 215, 0, 0.6); }
            50% { box-shadow: 0 0 20px 5px rgba(255, 215, 0, 0.9); }
            100% { box-shadow: 0 0 10px 2px rgba(255, 215, 0, 0.6); }
        }
        
        .card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 1s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
            border-radius: 15px;
        }
        
        .card-front, .card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .card-front {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, #f0f0f0, #e6e6e6);
        }
        
        .card-back-design {
            width: 100%;
            height: 100%;
            background-image: url('img/card-back.png');
            background-size: cover;
            background-position: center;
            border-radius: 15px;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .card-back-design::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 50%, rgba(0,0,0,0.1) 100%);
            border-radius: 15px;
        }
        
        .card-back {
            transform: rotateY(180deg);
            background-color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        
        .card-back img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 15px;
        }
        
        /* Aplicar brilhos específicos para cada raridade */
        .card-item[data-rarity="comum"] .card-inner.flipped {
            animation: borderGlowComum 2s infinite;
        }
        
        .card-item[data-rarity="incomum"] .card-inner.flipped {
            animation: borderGlowIncomum 2s infinite;
        }
        
        .card-item[data-rarity="rara"] .card-inner.flipped {
            animation: borderGlowRara 2s infinite;
        }
        
        .card-item[data-rarity="ultra-rara"] .card-inner.flipped {
            animation: borderGlowUltraRara 1.5s infinite;
        }
        
        /* Efeito de partículas para cartas ultra raras */
        .particles-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 15;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: gold;
            border-radius: 50%;
            opacity: 0;
            animation: float 3s ease-in-out infinite;
            box-shadow: 0 0 10px gold;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* Efeito de revelação das cartas */
        .cards-reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease;
        }
        
        .cards-reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        .cards-reveal h2 {
            color: #333;
            margin-bottom: 30px;
            font-size: 2.5rem;
            position: relative;
            display: inline-block;
            font-weight: 700;
            background: linear-gradient(45deg, #f1c40f, #e74c3c, #9b59b6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .cards-reveal h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 3px;
            background: linear-gradient(90deg, #f1c40f, #e74c3c, #9b59b6);
            border-radius: 2px;
        }
        
        .actions {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .btn-primary, .btn-secondary {
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-primary::before, .btn-secondary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0.1), rgba(255,255,255,0.3), rgba(255,255,255,0.1));
            transition: all 0.5s ease;
            z-index: -1;
        }
        
        .btn-primary:hover::before, .btn-secondary:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
        }

        .btn-primary-zero {
            background-color:rgb(27, 80, 115);
            color: white;
            pointer: no-drop;
        }
        
        .btn-secondary {
            background: linear-gradient(45deg, #f5f5f5, #e0e0e0);
            color: #333;
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #2980b9, #3498db);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(45deg, #e0e0e0, #f5f5f5);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Efeito de confete para abertura do pacote */
        .confetti-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 100;
            overflow: hidden;
            display: none;
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #f00;
            opacity: 0.8;
            animation: confetti-fall 5s linear forwards;
        }
        
        @keyframes confetti-fall {
            0% {
                transform: translateY(-10px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
        
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .container {
                padding: 20px;
            }
            
            h1, .cards-reveal h2 {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
                gap: 15px;
            }
            
            h1, .cards-reveal h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header removido conforme solicitado -->

    <section class="open-package">
        <div class="container">
            <h1>Abrindo Pacote Básico</h1>
            
            <div class="package-stage" id="packageStage">
                <div class="package-animation" id="packageAnimation">
                    <img src="img/packages/<?php echo htmlspecialchars($pacote['imagem']); ?>" alt="<?php echo htmlspecialchars($pacote['nome']); ?>" class="package-image">
                    <div class="package-glow"></div>
                </div>
                <div class="click-instruction">Clique para abrir o pacote!</div>
            </div>
            
            <div class="pacote-abertura">
                <div class="cards-reveal" id="cardsReveal" style="display: none;">
                    <h2>Suas novas cartas!</h2>
                    
                    <div class="cards-grid">
                        <?php foreach ($cartas as $index => $carta): ?>
                            <div class="card-item" data-index="<?php echo $index; ?>" data-rarity="<?php echo strtolower(str_replace(' ', '-', $carta['raridade'])); ?>">
                                <div class="card-inner">
                                    <div class="card-front">
                                        <div class="card-back-design"></div>
                                    </div>
                                    <div class="card-back">
                                        <img src="img/cards/<?php echo htmlspecialchars($carta['imagem']); ?>" alt="<?php echo htmlspecialchars($carta['nome']); ?>">
                                    </div>
                                </div>
                                <div class="particles-container"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="actions">
                        <a href="colecao.php" class="btn-primary">Ver minha coleção</a>
                        <a href="loja.php" class="btn-secondary">Voltar à loja</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Confetti container -->
    <div class="confetti-container" id="confettiContainer"></div>

    <!-- Variáveis para controle de sons -->
    <script>
        // Informações sobre cartas raras para o JavaScript
        var temRara = <?php echo $tem_rara ? 'true' : 'false'; ?>;
        var temUltraRara = <?php echo $tem_ultra_rara ? 'true' : 'false'; ?>;
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const packageStage = document.getElementById('packageStage');
            const packageAnimation = document.getElementById('packageAnimation');
            const cardsReveal = document.getElementById('cardsReveal');
            const cardItems = document.querySelectorAll('.card-item');
            const confettiContainer = document.getElementById('confettiContainer');
            
            // Sons
            const packOpenSound = new Audio('sounds/pack_open.mp3');
            const cardRareSound = new Audio('sounds/rare.mp3');
            const cardUltraRareSound = new Audio('sounds/ultra_rare.mp3');
            
            // Configurar volumes
            packOpenSound.volume = 0.7;
            cardRareSound.volume = 0.7;
            cardUltraRareSound.volume = 0.7;
            
            // Impedir que o usuário saia da página
            window.history.pushState(null, "", window.location.href);
            window.onpopstate = function() {
                window.history.pushState(null, "", window.location.href);
            };
            
            if (packageStage && cardsReveal) {
                // Animação de clique no pacote
                packageStage.addEventListener('click', function() {
                    // Tocar som de abertura do pacote
                    packOpenSound.play().catch(e => console.log('Erro ao tocar som:', e));
                    
                    // Animação de abertura do pacote
                    packageAnimation.style.transform = 'rotateY(180deg) scale(1.2)';
                    packageAnimation.style.opacity = '0';
                    
                    // Criar efeito de confete
                    createConfetti();
                    confettiContainer.style.display = 'block';
                    
                    // Após a animação, mostrar as cartas
                    setTimeout(function() {
                        packageStage.style.display = 'none';
                        cardsReveal.style.display = 'block';
                        
                        // Adicionar classe para animar a entrada
                        setTimeout(() => {
                            cardsReveal.classList.add('active');
                        }, 100);
                        
                        // Revelar as cartas uma por uma
                        cardItems.forEach((card, index) => {
                            setTimeout(() => {
                                const cardInner = card.querySelector('.card-inner');
                                const raridade = card.getAttribute('data-rarity');
                                
                                if (cardInner) {
                                    cardInner.style.transform = 'rotateY(180deg)';
                                    cardInner.classList.add('flipped');
                                    
                                    // Tocar som baseado na raridade
                                    if (raridade === 'ultra-rara') {
                                        cardUltraRareSound.cloneNode(true).play().catch(e => console.log('Erro ao tocar som:', e));
                                        
                                        // Adicionar partículas para cartas ultra raras
                                        const particlesContainer = card.querySelector('.particles-container');
                                        if (particlesContainer) {
                                            createParticles(particlesContainer, 30);
                                        }
                                    } else if (raridade === 'rara') {
                                        cardRareSound.cloneNode(true).play().catch(e => console.log('Erro ao tocar som:', e));
                                    }
                                }
                            }, index * 300 + 500);
                        });
                    }, 800);
                });
                
                // Permitir que o usuário clique em uma carta para virá-la manualmente
                cardItems.forEach(card => {
                    card.addEventListener('click', function() {
                        const cardInner = this.querySelector('.card-inner');
                        const raridade = this.getAttribute('data-rarity');
                        
                        if (cardInner && cardInner.style.transform !== 'rotateY(180deg)') {
                            cardInner.style.transform = 'rotateY(180deg)';
                            cardInner.classList.add('flipped');
                            
                            // Tocar som baseado na raridade
                            if (raridade === 'ultra-rara') {
                                cardUltraRareSound.cloneNode(true).play().catch(e => console.log('Erro ao tocar som:', e));
                                
                                // Adicionar partículas para cartas ultra raras
                                const particlesContainer = this.querySelector('.particles-container');
                                if (particlesContainer) {
                                    createParticles(particlesContainer, 30);
                                }
                            } else if (raridade === 'rara') {
                                cardRareSound.cloneNode(true).play().catch(e => console.log('Erro ao tocar som:', e));
                            }
                        }
                    });
                });
            }
        });
        
        // Função para criar partículas
        function createParticles(container, count) {
            // Limpar partículas existentes
            container.innerHTML = '';
            
            for (let i = 0; i < count; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Posição aleatória
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                
                // Tamanho aleatório
                const size = Math.random() * 6 + 4;
                
                // Atraso aleatório
                const delay = Math.random() * 2;
                
                // Duração aleatória
                const duration = Math.random() * 2 + 2;
                
                // Aplicar estilos
                particle.style.left = posX + '%';
                particle.style.top = posY + '%';
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.animationDelay = delay + 's';
                particle.style.animationDuration = duration + 's';
                
                container.appendChild(particle);
            }
        }
        
        // Função para criar confete
        function createConfetti() {
            const confettiContainer = document.getElementById('confettiContainer');
            confettiContainer.innerHTML = '';
            
            const colors = ['#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4', '#009688', '#4CAF50', '#8BC34A', '#CDDC39', '#FFEB3B', '#FFC107', '#FF9800', '#FF5722'];
            
            for (let i = 0; i < 150; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                
                // Posição aleatória
                const posX = Math.random() * 100;
                
                // Tamanho aleatório
                const size = Math.random() * 10 + 5;
                
                // Cor aleatória
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                // Forma aleatória (quadrado ou círculo)
                const borderRadius = Math.random() > 0.5 ? '50%' : '0';
                
                // Atraso aleatório
                const delay = Math.random() * 5;
                
                // Duração aleatória
                const duration = Math.random() * 3 + 3;
                
                // Aplicar estilos
                confetti.style.left = posX + '%';
                confetti.style.width = size + 'px';
                confetti.style.height = size + 'px';
                confetti.style.background = color;
                confetti.style.borderRadius = borderRadius;
                confetti.style.animationDelay = delay + 's';
                confetti.style.animationDuration = duration + 's';
                
                confettiContainer.appendChild(confetti);
            }
        }
    </script>
</body>
</html> 