<x-ds-page>
    <div
        class="uat-checklist"
        x-data="uatToolsChecklist(@js($groups), @js($baseline ?? []))"
        x-init="load()"
    >
        <div class="ds-page-header-bar">
            <h1 class="ds-page-title">تقييم أدوات المنصة (UAT)</h1>
            <div class="ds-btn-group">
                <button type="button" class="ds-btn ds-btn-primary" @click="copyReport()" :disabled="copying">
                    <i class="fas fa-copy" aria-hidden="true"></i>
                    <span x-text="copied ? 'تم النسخ — الصق في المحادثة' : 'نسخ التقرير كاملاً'"></span>
                </button>
                <button type="button" class="ds-btn ds-btn-outline" @click="loadBaseline()" title="تحميل تقييم عبدالله 2026-08-13">
                    تحميل التقييم السابق
                </button>
                <button type="button" class="ds-btn ds-btn-outline" @click="resetAll()">
                    إعادة التعيين
                </button>
            </div>
        </div>

        <div class="ds-alert ds-alert-warning ds-mb-3">
            صفحة تجريبية فقط. تُحذف تلقائيًا عند النشر (`APP_ENV=production` أو `UAT_TOOLS_ENABLED=false`).
            عبّئ التقييم والملاحظة ثم انسخ التقرير وأرسله في محادثة Cursor لإصلاح الملاحظات.
            <template x-if="baseline?.date">
                <span>
                    · الأساس المحمّل: <strong x-text="baseline.label || 'تقييم سابق'"></strong>
                    (<span class="ds-ltr-num" x-text="baseline.date"></span>)
                    — <span x-text="baseline.summary || ''"></span>
                </span>
            </template>
        </div>

        <div class="ds-filters-row">
            <div class="ds-filter-field">
                <span class="ds-label">الإجمالي</span>
                <strong class="ds-ltr-num" x-text="total"></strong>
            </div>
            <div class="ds-filter-field">
                <span class="ds-label">يعتمد</span>
                <strong class="ds-ltr-num" x-text="count('يعتمد')"></strong>
            </div>
            <div class="ds-filter-field">
                <span class="ds-label">يحتاج تحسين</span>
                <strong class="ds-ltr-num" x-text="count('يحتاج تحسين')"></strong>
            </div>
            <div class="ds-filter-field">
                <span class="ds-label">غير مجرّب</span>
                <strong class="ds-ltr-num" x-text="count('غير مجرّب')"></strong>
            </div>
            <div class="ds-filter-field">
                <label class="ds-label" for="uat-filter">تصفية</label>
                <select id="uat-filter" class="ds-input" x-model="filter">
                    <option value="الكل">الكل</option>
                    @foreach ($verdicts as $verdict)
                        <option value="{{ $verdict }}">{{ $verdict }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <template x-for="group in visibleGroups()" :key="group.id">
            <section class="ds-card ds-mb-3">
                <h2 class="ds-section-title" x-text="group.title"></h2>
                <div class="ds-table-wrap">
                    <table class="ds-table">
                        <thead>
                            <tr>
                                <th scope="col">الأداة</th>
                                <th scope="col">المسار</th>
                                <th scope="col">ما يُتحقق منه</th>
                                <th scope="col">التقييم</th>
                                <th scope="col">تصنيف الملاحظة</th>
                                <th scope="col">الملاحظة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="tool in group.items" :key="tool.id">
                                <tr>
                                    <td>
                                        <strong x-text="tool.tool"></strong>
                                        <template x-if="isHttpPath(tool.path)">
                                            <div>
                                                <a class="ds-link" :href="tool.path" target="_blank" rel="noopener">فتح</a>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="ds-ltr-num" x-text="tool.path"></td>
                                    <td x-text="tool.checks"></td>
                                    <td>
                                        <select class="ds-input" x-model="verdicts[tool.id]" @change="persist()">
                                            @foreach ($verdicts as $verdict)
                                                <option value="{{ $verdict }}">{{ $verdict }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="ds-input" x-model="tags[tool.id]" @change="persist()">
                                            @foreach ($noteTags as $tag)
                                                <option value="{{ $tag }}">{{ $tag === '' ? '—' : $tag }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <textarea
                                            class="ds-input"
                                            rows="2"
                                            placeholder="اكتب الملاحظة بالتفصيل…"
                                            x-model="notes[tool.id]"
                                            @change="persist()"
                                        ></textarea>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</x-ds-page>

@script
<script>
    Alpine.data('uatToolsChecklist', (groups, baseline = {}) => ({
        groups,
        baseline: baseline || {},
        filter: 'الكل',
        verdicts: {},
        tags: {},
        notes: {},
        copying: false,
        copied: false,
        storageKey: 'hollal.uat.tools.v2',

        get total() {
            return this.groups.reduce((sum, g) => sum + g.items.length, 0);
        },

        applyBaseline() {
            const b = this.baseline || {};
            this.verdicts = Object.assign({}, b.verdicts || {});
            this.tags = Object.assign({}, b.tags || {});
            this.notes = Object.assign({}, b.notes || {});
            this.fillMissing();
        },

        fillMissing() {
            this.groups.forEach((g) => {
                g.items.forEach((t) => {
                    if (!this.verdicts[t.id]) this.verdicts[t.id] = 'غير مجرّب';
                    if (this.tags[t.id] === undefined) this.tags[t.id] = '';
                    if (this.notes[t.id] === undefined) this.notes[t.id] = '';
                });
            });
        },

        load() {
            let loaded = false;
            try {
                const raw = localStorage.getItem(this.storageKey);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    this.verdicts = parsed.verdicts || {};
                    this.tags = parsed.tags || {};
                    this.notes = parsed.notes || {};
                    loaded = Object.keys(this.verdicts).length > 0;
                }
            } catch (e) {}

            if (!loaded && this.baseline && this.baseline.verdicts) {
                this.applyBaseline();
                this.persist();
            } else {
                this.fillMissing();
            }
        },

        loadBaseline() {
            if (!confirm('استبدال التقييم الحالي بتقييم التجربة الثانية (2026-08-13)؟')) return;
            this.applyBaseline();
            this.persist();
            if (window.Livewire) {
                Livewire.dispatch('toast', { type: 'success', message: 'تم تحميل التقييم السابق' });
            }
        },

        persist() {
            localStorage.setItem(this.storageKey, JSON.stringify({
                verdicts: this.verdicts,
                tags: this.tags,
                notes: this.notes,
                source: 'uat-baseline-round2',
            }));
        },

        count(verdict) {
            return this.allTools().filter((t) => (this.verdicts[t.id] || 'غير مجرّب') === verdict).length;
        },

        allTools() {
            return this.groups.flatMap((g) => g.items.map((t) => ({ ...t, group: g.title })));
        },

        visibleGroups() {
            if (this.filter === 'الكل') return this.groups;
            return this.groups
                .map((g) => ({
                    ...g,
                    items: g.items.filter((t) => (this.verdicts[t.id] || 'غير مجرّب') === this.filter),
                }))
                .filter((g) => g.items.length > 0);
        },

        isHttpPath(path) {
            return typeof path === 'string' && path.startsWith('/');
        },

        buildReport() {
            const tools = this.allTools();
            const lines = [
                '# تقرير تقييم أدوات — منصة حلّل',
                `التاريخ: ${new Date().toISOString().slice(0, 16).replace('T', ' ')}`,
                `الملخص: ${this.total} إجمالي · ${this.count('يعتمد')} يعتمد · ${this.count('يحتاج تحسين')} يحتاج تحسين · ${this.count('غير مجرّب')} غير مجرّب`,
                '',
                'الهدف: إصلاح الملاحظات أدناه في الكود.',
                '',
            ];

            const sections = [
                ['يحتاج تحسين', 'يحتاج تحسين'],
                ['يعتمد', 'يعتمد'],
                ['غير مجرّب', 'غير مجرّب'],
            ];

            sections.forEach(([title, verdict]) => {
                const rows = tools.filter((t) => (this.verdicts[t.id] || 'غير مجرّب') === verdict);
                if (!rows.length) return;
                lines.push(`## ${title} (${rows.length})`);
                rows.forEach((t) => {
                    lines.push(`### ${t.group} — ${t.tool}`);
                    lines.push(`- المسار: \`${t.path}\``);
                    lines.push(`- ما يُتحقق منه: ${t.checks}`);
                    lines.push(`- التقييم: ${verdict}`);
                    const tag = this.tags[t.id] || '';
                    if (tag) lines.push(`- تصنيف الملاحظة: ${tag}`);
                    const note = (this.notes[t.id] || '').trim();
                    if (note) lines.push(`- الملاحظة: ${note}`);
                    lines.push('');
                });
            });

            return lines.join('\n');
        },

        async copyReport() {
            this.copying = true;
            this.copied = false;
            const text = this.buildReport();
            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                const area = document.createElement('textarea');
                area.value = text;
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                document.body.removeChild(area);
            }
            this.copying = false;
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 4000);
            if (window.Livewire) {
                Livewire.dispatch('toast', { type: 'success', message: 'نُسخ التقرير — الصقه في محادثة Cursor' });
            }
        },

        resetAll() {
            if (!confirm('مسح كل التقييمات والملاحظات المحفوظة في هذا المتصفح؟')) return;
            localStorage.removeItem(this.storageKey);
            this.verdicts = {};
            this.tags = {};
            this.notes = {};
            this.fillMissing();
            this.persist();
        },
    }));
</script>
@endscript
