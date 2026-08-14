<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'الرئيسية' }} — منصة حلل</title>
    <link rel="stylesheet" href="{{ asset('css/hollal-ds.css') }}?v={{ @filemtime(public_path('css/layout.css')) ?: '1' }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @livewireStyles
</head>
<body>
    @include('partials.navbar')

    <div class="ds-sidebar-backdrop" id="ds-sidebar-backdrop" aria-hidden="true"></div>

    <div class="ds-main-layout">
        @include('partials.sidebar')

        <main class="ds-content ds-page-rtl" dir="rtl">
            @hasSection('content')
                @yield('content')
            @else
                {{ $slot ?? '' }}
            @endif
        </main>
    </div>

    <x-ds-toast />

    <script>
        document.addEventListener('alpine:init', function () {
            Alpine.data('dsSearchSelect', function (config) {
                return {
                    options: config.options || [],
                    wireModel: config.wireModel,
                    placeholder: config.placeholder || 'ابحث للاختيار…',
                    open: false,
                    query: '',
                    value: null,
                    highlight: 0,

                    init: function () {
                        var self = this;
                        if (self.wireModel && self.$wire) {
                            self.value = self.$wire.get(self.wireModel);
                            self.$watch(function () { return self.$wire.get(self.wireModel); }, function (v) {
                                self.value = v;
                                if (! self.open) self.query = '';
                            });
                        }
                    },

                    get hasValue() {
                        return this.value !== null && this.value !== undefined && this.value !== '';
                    },

                    get selected() {
                        var self = this;
                        return this.options.find(function (o) { return String(o.value) === String(self.value); }) || null;
                    },

                    get displayPlaceholder() {
                        return this.placeholder;
                    },

                    get inputValue() {
                        if (this.open) return this.query;
                        return this.selected ? this.selected.label : '';
                    },

                    get filtered() {
                        var q = (this.query || '').trim().toLocaleLowerCase('ar');
                        if (! q) return this.options.slice(0, 80);
                        return this.options.filter(function (o) {
                            var label = (o.label || '').toLocaleLowerCase('ar');
                            var sub = (o.sub || '').toLocaleLowerCase('ar');
                            var val = String(o.value || '').toLocaleLowerCase('ar');
                            return label.indexOf(q) !== -1 || sub.indexOf(q) !== -1 || val.indexOf(q) !== -1;
                        }).slice(0, 80);
                    },

                    openList: function () {
                        this.open = true;
                        this.query = '';
                        this.highlight = 0;
                    },

                    onInput: function (event) {
                        this.open = true;
                        this.query = event.target.value;
                        this.highlight = 0;
                    },

                    close: function () {
                        this.open = false;
                        this.query = '';
                    },

                    setValue: async function (v) {
                        this.value = v;
                        if (this.wireModel && this.$wire) {
                            await this.$wire.set(this.wireModel, v);
                        }
                    },

                    choose: async function (opt) {
                        await this.setValue(opt.value);
                        this.close();
                    },

                    clear: async function () {
                        await this.setValue(null);
                        this.query = '';
                        this.open = false;
                    },

                    selectFirst: function () {
                        if (this.filtered.length) {
                            this.choose(this.filtered[this.highlight] || this.filtered[0]);
                        }
                    },

                    move: function (delta) {
                        if (! this.open) this.openList();
                        var max = Math.max(this.filtered.length - 1, 0);
                        this.highlight = Math.min(max, Math.max(0, this.highlight + delta));
                    },
                };
            });
        });
    </script>

    @livewireScripts
    @include('partials.app-shell-scripts')
    @stack('scripts')
</body>
</html>
