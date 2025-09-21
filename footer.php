</div>
    </div>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Job/Internship Referral Portal. All rights reserved.</p>
            <p>Designed with <i class="fas fa-heart" style="color: #e74a3b;"></i> for students and alumni</p>
        </div>
    </footer>
    
    <script>
        // Simple animations
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.card, .stat-card, .profile-header');
            elements.forEach(element => {
                element.classList.add('fade-in');
            });
        });
        
        // Function to show alerts and hide after 5 seconds
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.appendChild(document.createTextNode(message));
            
            const container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        // Check if there's a message in URL parameters to show as alert
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        const messageType = urlParams.get('type');
        
        if (message && messageType) {
            showAlert(decodeURIComponent(message), messageType);
        }
    </script>
</body>
</html>