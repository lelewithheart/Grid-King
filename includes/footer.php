<footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo APP_NAME; ?></h5>
                    <p class="mb-0">Professional racing league management system</p>
                    <small class="text-muted">Version <?php echo APP_VERSION; ?></small>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="social-links">
                        <a href="#" class="text-light me-3"><i class="bi bi-discord"></i></a>
                        <a href="#" class="text-light me-3"><i class="bi bi-youtube"></i></a>
                        <a href="#" class="text-light"><i class="bi bi-twitch"></i></a>
                    </div>
                    <p class="mb-0 mt-2">
                        <small>&copy; <?php echo date('Y'); ?> Leonhard Yvon. All rights reserved.</small>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Race countdown timer
        function updateCountdown(elementId, targetDate) {
            const target = new Date(targetDate).getTime();
            const element = document.getElementById(elementId);
            
            if (!element) return;
            
            function update() {
                const now = new Date().getTime();
                const distance = target - now;
                
                if (distance < 0) {
                    element.innerHTML = "RACE STARTED!";
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                element.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;
            }
            
            update();
            setInterval(update, 1000);
        }
        
        // Initialize all countdowns on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-countdown]').forEach(function(element) {
                updateCountdown(element.id, element.dataset.countdown);
            });
        });
        
        // Form validation and AJAX helpers
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container');
            if (container) {
                container.insertBefore(alertDiv, container.firstChild);
            }
        }
    </script>
</body>
</html>