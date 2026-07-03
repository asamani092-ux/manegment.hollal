<script>
    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.querySelector('.ds-sidebar');
        var toggle = document.getElementById('ds-sidebar-toggle');
        var backdrop = document.getElementById('ds-sidebar-backdrop');
        var dropdown = document.getElementById('ds-user-dropdown');
        var trigger = document.getElementById('ds-user-trigger');
        var menu = document.getElementById('ds-user-menu');

        function closeSidebar() {
            sidebar?.classList.remove('open');
            backdrop?.classList.remove('open');
            toggle?.setAttribute('aria-expanded', 'false');
        }

        function openSidebar() {
            sidebar?.classList.add('open');
            backdrop?.classList.add('open');
            toggle?.setAttribute('aria-expanded', 'true');
        }

        toggle?.addEventListener('click', function () {
            if (sidebar?.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        backdrop?.addEventListener('click', closeSidebar);

        sidebar?.querySelectorAll('.ds-sidebar-item').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        trigger?.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = dropdown?.classList.toggle('open');
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (dropdown && !dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
                trigger?.setAttribute('aria-expanded', 'false');
            }
        });
    });
</script>
