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

        document.addEventListener('livewire:init', function () {
            if (! window.Livewire || ! Livewire.hook) {
                return;
            }

            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 419) {
                        preventDefault();
                        window.location.href = '/login?expired=1';
                    }
                });
            });
        });
    })();
</script>
<script>
    (function () {
        if (window.__orgChartBound) {
            return;
        }
        window.__orgChartBound = true;

        function initOrgChart() {
            var root = document.getElementById('org-chart-root');
            if (!root || root.dataset.bound === '1') {
                return;
            }
            root.dataset.bound = '1';
            var stage = document.getElementById('org-chart-stage');
            var scroller = root.querySelector('.org-chart-scroll');
            var scale = 1;

            function applyScale() {
                if (!stage) {
                    return;
                }
                stage.style.transform = 'scale(' + scale + ')';
            }

            function center() {
                if (!scroller) {
                    return;
                }
                var max = scroller.scrollWidth - scroller.clientWidth;
                if (max > 0) {
                    scroller.scrollLeft = max / 2;
                }
            }

            root.querySelectorAll('[data-org-toggle]').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var item = button.closest('.org-li');
                    if (item) {
                        item.classList.toggle('is-collapsed');
                    }
                });
            });

            root.querySelectorAll('[data-org-zoom]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var mode = button.getAttribute('data-org-zoom');
                    if (mode === 'in') {
                        scale = Math.min(1.8, scale + 0.1);
                    } else if (mode === 'out') {
                        scale = Math.max(0.4, scale - 0.1);
                    } else if (stage && scroller) {
                        scale = Math.min(1, scroller.clientWidth / Math.max(stage.scrollWidth, 1));
                    }
                    applyScale();
                });
            });

            root.querySelectorAll('[data-org-fold]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var collapse = button.getAttribute('data-org-fold') === 'all';
                    root.querySelectorAll('.org-li').forEach(function (item) {
                        if (item.querySelector(':scope > ul')) {
                            item.classList.toggle('is-collapsed', collapse);
                        }
                    });
                });
            });

            var search = document.getElementById('org-search');
            if (search) {
                search.addEventListener('input', function () {
                    var query = search.value.trim();
                    root.querySelectorAll('.org-card').forEach(function (card) {
                        card.classList.remove('is-hit', 'is-dim');
                    });
                    if (query === '') {
                        return;
                    }
                    var first = null;
                    root.querySelectorAll('.org-li').forEach(function (item) {
                        var card = item.querySelector(':scope > .org-card');
                        var label = item.getAttribute('data-org-label') || '';
                        if (label.indexOf(query) !== -1) {
                            if (card) {
                                card.classList.add('is-hit');
                            }
                            if (!first) {
                                first = card;
                            }
                            var cursor = item;
                            while (cursor) {
                                cursor.classList.remove('is-collapsed');
                                var parent = cursor.parentElement ? cursor.parentElement.closest('.org-li') : null;
                                if (parent) {
                                    var parentCard = parent.querySelector(':scope > .org-card');
                                    if (parentCard) {
                                        parentCard.classList.add('is-hit');
                                        parentCard.classList.remove('is-dim');
                                    }
                                }
                                cursor = parent;
                            }
                        } else if (card && !card.classList.contains('is-hit')) {
                            card.classList.add('is-dim');
                        }
                    });
                    if (first) {
                        first.scrollIntoView({ inline: 'center', block: 'nearest' });
                    }
                });
            }

            root.addEventListener('keydown', function (event) {
                if (event.key === '+' || event.key === '=') {
                    scale = Math.min(1.8, scale + 0.1);
                    applyScale();
                }
                if (event.key === '-' || event.key === '_') {
                    scale = Math.max(0.4, scale - 0.1);
                    applyScale();
                }
            });

            if (window.matchMedia('(max-width: 1023px)').matches) {
                root.querySelectorAll('[data-collapse-mobile="1"]').forEach(function (item) {
                    item.classList.add('is-collapsed');
                });
            }
            center();
        }

        document.addEventListener('DOMContentLoaded', initOrgChart);
        document.addEventListener('livewire:navigated', initOrgChart);
        if (document.readyState !== 'loading') {
            initOrgChart();
        }
    })();
</script>
