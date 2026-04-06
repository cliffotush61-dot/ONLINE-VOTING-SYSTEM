    </main>
</div>

<footer style="text-align: center; padding: 30px; color: #6b7280; font-size: 13px; background: white; margin-top: 50px; border-top: 1px solid #e5e7eb;">
    <p>&copy; 2026 Secure Voting System | University Election Portal | All Rights Reserved</p>
</footer>

<script>
    // Close message after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const messageBoxes = document.querySelectorAll('.message-box');
        messageBoxes.forEach(function(box) {
            setTimeout(function() {
                box.style.opacity = '0';
                box.style.transform = 'translateY(-10px)';
                box.style.transition = 'all 0.3s ease';
                setTimeout(function() {
                    box.style.display = 'none';
                }, 300);
            }, 5000);
        });
    });

    // Highlight active sidebar item
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        sidebarLinks.forEach(function(link) {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    });
</script>

</body>
</html>
