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

        var navGroups = Array.prototype.slice.call(sidebar?.querySelectorAll('.ds-sidebar-group') || []);
        var navStorageKey = 'ds-nav-open-groups';

        function readOpenGroups() {
            try {
                return JSON.parse(window.localStorage.getItem(navStorageKey)) || [];
            } catch (e) {
                return [];
            }
        }

        function persistOpenGroups() {
            var open = navGroups
                .filter(function (group) { return group.classList.contains('is-open'); })
                .map(function (group) { return group.dataset.navGroup; });

            try {
                window.localStorage.setItem(navStorageKey, JSON.stringify(open));
            } catch (e) { /* التخزين المحلي معطّل — الطي يبقى للجلسة فقط */ }
        }

        readOpenGroups().forEach(function (label) {
            navGroups.forEach(function (group) {
                if (group.dataset.navGroup === label) {
                    group.classList.add('is-open');
                    group.querySelector('.ds-sidebar-group-label')?.setAttribute('aria-expanded', 'true');
                }
            });
        });

        navGroups.forEach(function (group) {
            var label = group.querySelector('.ds-sidebar-group-label');
            label?.addEventListener('click', function () {
                var isOpen = group.classList.toggle('is-open');
                label.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                persistOpenGroups();
            });
        });

        var navSearch = document.getElementById('ds-nav-search');
        var navNoResults = sidebar?.querySelector('.ds-sidebar-no-results');

        navSearch?.addEventListener('input', function () {
            var term = navSearch.value.trim();
            var matches = 0;

            navGroups.forEach(function (group) {
                var groupMatches = 0;

                group.querySelectorAll('.ds-sidebar-item').forEach(function (item) {
                    var hit = term === '' || (item.dataset.navLabel || '').indexOf(term) !== -1;
                    item.hidden = !hit;
                    if (hit) { groupMatches++; }
                });

                matches += groupMatches;
                group.hidden = term !== '' && groupMatches === 0;

                if (term !== '') {
                    group.classList.toggle('is-open', groupMatches > 0);
                } else {
                    group.classList.toggle('is-open', readOpenGroups().indexOf(group.dataset.navGroup) !== -1
                        || group.classList.contains('is-active'));
                }
            });

            if (navNoResults) {
                navNoResults.hidden = term === '' || matches > 0;
            }
        });

        var moreToggle = document.getElementById('ds-sidebar-more-toggle');
        var moreSection = document.getElementById('ds-sidebar-more');

        moreToggle?.addEventListener('click', function () {
            var isOpen = moreSection?.classList.toggle('is-open');
            moreToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
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
