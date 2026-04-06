    </div>
</div>

<script>
    // Auto-dismiss message boxes
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.querySelectorAll('.message:not(.sticky)');
        messages.forEach(msg => {
            setTimeout(() => {
                msg.style.display = 'none';
            }, 5000);
        });

        // Highlight current page in sidebar
        const currentPage = window.location.pathname.split('/').pop() || 'admin_dashboard.php';
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        sidebarLinks.forEach(link => {
            if (link.href.includes(currentPage)) {
                link.classList.add('active');
            }
        });
    });
</script>

<footer style="background: white; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">
    &copy; 2024 Secure Online Voting System. All rights reserved.
</footer>

</body>
</html>
