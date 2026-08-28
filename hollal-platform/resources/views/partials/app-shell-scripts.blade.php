<script>
    (function () {
        if (window.__dsAppShellBound) {
            return;
        }
        window.__dsAppShellBound = true;

        var navStorageKey = 'ds-nav-open-groups';

        function sidebarEl() {
            return document.querySelector('.ds-sidebar');
        }

        function toggleEl() {
            return document.getElementById('ds-sidebar-toggle');
        }

        function backdropEl() {
            return document.getElementById('ds-sidebar-backdrop');
        }

        function closeSidebar() {
            sidebarEl()?.classList.remove('open');
            backdropEl()?.classList.remove('open');
            toggleEl()?.setAttribute('aria-expanded', 'false');
        }

        function readOpenGroups() {
            try {
                return JSON.parse(window.localStorage.getItem(navStorageKey)) || [];
            } catch (e) {
                return [];
            }
        }

        function persistOpenGroups() {
            var open = Array.prototype.slice.call(sidebarEl()?.querySelectorAll('.ds-sidebar-group.is-open') || [])
                .map(function (group) { return group.dataset.navGroup; });

            try {
                window.localStorage.setItem(navStorageKey, JSON.stringify(open));
            } catch (e) { /* التخزين المحلي معطّل — الطي يبقى للجلسة فقط */ }
        }

        function applyChrome() {
            var toggle = toggleEl();

            try {
                if (window.innerWidth > 768 && window.localStorage.getItem('ds-sidebar-collapsed') === '1') {
                    document.querySelector('.ds-main-layout')?.classList.add('is-sidebar-collapsed');
                    toggle?.setAttribute('aria-expanded', 'false');
                    toggle?.setAttribute('aria-label', 'توسيع القائمة');
                }
            } catch (e) { /* التخزين المحلي معطّل */ }

            readOpenGroups().forEach(function (label) {
                sidebarEl()?.querySelectorAll('.ds-sidebar-group').forEach(function (group) {
                    if (group.dataset.navGroup === label) {
                        group.classList.add('is-open');
                        group.querySelector('.ds-sidebar-group-label')?.setAttribute('aria-expanded', 'true');
                    }
                });
            });
        }

        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('#ds-sidebar-toggle');
            if (toggle) {
                if (window.innerWidth <= 768) {
                    if (sidebarEl()?.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        sidebarEl()?.classList.add('open');
                        backdropEl()?.classList.add('open');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                    return;
                }

                var layout = document.querySelector('.ds-main-layout');
                var collapsed = layout?.classList.toggle('is-sidebar-collapsed');
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', collapsed ? 'توسيع القائمة' : 'طي القائمة');
                try {
                    window.localStorage.setItem('ds-sidebar-collapsed', collapsed ? '1' : '0');
                } catch (err) { /* التخزين المحلي معطّل */ }
                return;
            }

            if (e.target.closest('#ds-sidebar-backdrop')) {
                closeSidebar();
                return;
            }

            if (e.target.closest('.ds-sidebar-item') && window.innerWidth <= 768) {
                closeSidebar();
            }

            var groupLabel = e.target.closest('.ds-sidebar-group-label');
            if (groupLabel) {
                var group = groupLabel.closest('.ds-sidebar-group');
                var isOpen = group.classList.toggle('is-open');
                groupLabel.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                persistOpenGroups();
                return;
            }

            var moreToggle = e.target.closest('#ds-sidebar-more-toggle');
            if (moreToggle) {
                var moreSection = document.getElementById('ds-sidebar-more');
                var moreOpen = moreSection?.classList.toggle('is-open');
                moreToggle.setAttribute('aria-expanded', moreOpen ? 'true' : 'false');
                return;
            }

            var trigger = e.target.closest('#ds-user-trigger');
            var dropdown = document.getElementById('ds-user-dropdown');
            if (trigger) {
                var userOpen = dropdown?.classList.toggle('open');
                trigger.setAttribute('aria-expanded', userOpen ? 'true' : 'false');
                return;
            }

            if (dropdown && !dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
                document.getElementById('ds-user-trigger')?.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('input', function (e) {
            if (e.target.id !== 'ds-nav-search') {
                return;
            }

            var term = e.target.value.trim();
            var matches = 0;
            var sidebar = sidebarEl();

            sidebar?.querySelectorAll('.ds-sidebar-group').forEach(function (group) {
                var groupMatches = 0;

                group.querySelectorAll('.ds-sidebar-item').forEach(function (item) {
                    var hit = term === '' || (item.dataset.navLabel || '').indexOf(term) !== -1;
                    item.hidden = !hit;
                    if (hit) {
                        groupMatches++;
                    }
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

            var navNoResults = sidebar?.querySelector('.ds-sidebar-no-results');
            if (navNoResults) {
                navNoResults.hidden = term === '' || matches > 0;
            }
        });

        document.addEventListener('DOMContentLoaded', applyChrome);
        document.addEventListener('livewire:navigated', applyChrome);
        if (document.readyState !== 'loading') {
            applyChrome();
        }
    })();
</script>
<script>
    (function () {
        function registerDsSearchSelect() {
            if (!window.Alpine || typeof Alpine.data !== 'function' || window.__dsSearchSelectBound) {
                return;
            }
            window.__dsSearchSelectBound = true;
            Alpine.data('dsSearchSelect', ({ options = [], wireModel = null, placeholder = 'ابحث للاختيار…' }) => ({
                options,
                wireModel,
                placeholder,
                query: '',
                open: false,
                highlight: 0,
                value: null,

                init() {
                    if (this.wireModel) {
                        this.value = this.$wire.get(this.wireModel);
                    }
                    this.$watch('options', () => {
                        /* options refreshed from Livewire */
                    });
                },

                get selected() {
                    return this.options.find((opt) => String(opt.value) === String(this.value)) ?? null;
                },

                get hasValue() {
                    return this.value !== null && this.value !== undefined && this.value !== '';
                },

                get displayPlaceholder() {
                    return this.hasValue && ! this.open ? '' : this.placeholder;
                },

                get inputValue() {
                    if (this.open) {
                        return this.query;
                    }

                    return this.selected ? this.selected.label : '';
                },

                get filtered() {
                    const term = this.query.trim().toLowerCase();
                    if (term === '') {
                        return this.options;
                    }

                    return this.options.filter((opt) => (
                        (opt.label + ' ' + (opt.sub || '')).toLowerCase().includes(term)
                    ));
                },

                onInput(event) {
                    this.query = event.target.value;
                    this.open = true;
                    this.highlight = 0;
                },

                openList() {
                    this.query = '';
                    this.open = true;
                    this.highlight = 0;
                },

                close() {
                    this.open = false;
                    this.query = '';
                },

                move(delta) {
                    if (! this.open) {
                        this.openList();

                        return;
                    }

                    const count = this.filtered.length;
                    if (count === 0) {
                        return;
                    }

                    this.highlight = (this.highlight + delta + count) % count;
                },

                selectFirst() {
                    const opt = this.filtered[this.highlight];
                    if (opt) {
                        this.choose(opt);
                    }
                },

                choose(opt) {
                    this.value = opt.value;
                    if (this.wireModel) {
                        this.$wire.set(this.wireModel, opt.value);
                    }
                    this.close();
                },

                clear() {
                    this.value = null;
                    this.query = '';
                    if (this.wireModel) {
                        this.$wire.set(this.wireModel, null);
                    }
                },
            }));
        }

        document.addEventListener('alpine:init', registerDsSearchSelect);
        if (window.Alpine) {
            registerDsSearchSelect();
        }
    })();
</script>
