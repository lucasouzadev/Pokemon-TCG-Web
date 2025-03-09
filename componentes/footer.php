<footer>
    <div class="container">
        <p>&copy; 2023 Pokémon TCG Pocket. Todos os direitos reservados.</p>
        <p>Pokémon e suas marcas são propriedade da Nintendo, Game Freak e The Pokémon Company.</p>
    </div>
</footer>

<script src="js/main.js"></script>
<script>
    // Script para o dropdown de notificações
    document.addEventListener('DOMContentLoaded', function() {
        const notificationsToggle = document.querySelector('.notifications-toggle');
        const notificationsContent = document.querySelector('.notifications-content');
        
        if (notificationsToggle && notificationsContent) {
            notificationsToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationsContent.classList.toggle('active');
            });
            
            // Fechar o dropdown quando clicar fora dele
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.notifications-dropdown')) {
                    notificationsContent.classList.remove('active');
                }
            });
        }
    });
</script> 