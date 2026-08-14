<x-ds-page>
    <div
        class="uat-checklist"
        x-data="uatToolsChecklist(@js($groups), @js($phases), @js($baseline ?? []), @js($baselineRound3 ?? []), @js($baselineRound2 ?? []))"
        x-init="load()"
    >
        <div class="ds-page-header-bar">
            <h1 class="ds-page-title">تقييم أدوات المنصة (UAT) — 3 مراحل</h1>
            <div class="ds-btn-group">
                <button type="button" class="ds-btn ds-btn-primary" @click="copyReport()" :disabled="copying">
                    <i class="fas fa-copy" aria-hidden="true"></i>
                    <span x-text="copied ? 'تم النسخ — الصق في المحادثة' : 'نسخ التقرير كاملاً'"></span>
                </button>
                <button type="button" class="ds-btn ds-btn-outline" @click="loadBaseline()" title="تحميل تقييم 2026-08-14 20:27">
                    تحميل آخر تقييم (20:27)
                </button>
                <button type="button" class="ds-btn ds-btn-outline" @click="loadRound3()" title="تحميل تقييم 19:04">
                    تقييم 19:04
                </button>
                <button type="button" class="ds-btn ds-btn-outline" @click="resetAll()">
                    إعادة التعيين
                </button>
            </div>
        </div>

        <div class="ds-alert ds-alert-warning ds-mb-3">
            <strong>قاعدة المراحل:</strong> لا تُفتح المرحلة التالية حتى تُعلَّم <em>كل</em> أدوات المرحلة الحالية «يعتمد».
            صفحة تجريبية فقط — تُحذف عند النشر (`APP_ENV=production` أو `UAT_TOOLS_ENABLED=false`).
            <template x-if="baseline?.date">
                <span>
                    · الأساس: <strong x-text="baseline.label || 'تقييم سابق'"></strong>
                    (<span class="ds-ltr-num" x-text="baseline.date"></span>)
                </span>
            </template>
        </div>

        <nav class="uat-phase-nav ds-mb-3" aria-label="مراحل التقييم">
            <template x-for="phase in phases" :key="phase.id">
                <button
                    type="button"
                    class="uat-phase-tab"
                    :class="{
                        'is-active': activePhase === phase.id,
                        'is-locked': !isPhaseUnlocked(phase.id),
                        'is-done': isPhaseComplete(phase.id)
                    }"
                    :disabled="!isPhaseUnlocked(phase.id)"
                    @click="selectPhase(phase.id)"
                >
                    <span class="uat-phase-tab__num" x-text="phase.id"></span>
                    <span class="uat-phase-tab__body">
                        <strong x-text="phase.title"></strong>
                        <small x-text="phase.goal"></small>
                        <small class="ds-ltr-num">
                            <span x-text="phaseAccepted(phase.id)"></span>/<span x-text="phaseToolCount(phase.id)"></span> يعتمد
                            <template x-if="!isPhaseUnlocked(phase.id)"> · مقفلة</template>
                            <template x-if="isPhaseComplete(phase.id)"> · مكتملة ✓</template>
                        </small>
                    </span>
                </button>
            </template>
        </nav>

        <div class="ds-filters-row">
            <div class="ds-filter-field">
                <span class="ds-label">أدوات المرحلة</span>
                <strong class="ds-ltr-num" x-text="phaseToolCount(activePhase)"></strong>
            </div>
            <div class="ds-filter-field">
                <span class="ds-label">يعتمد</span>
                <strong class="ds-ltr-num" x-text="phaseCount(activePhase, 'يعتمد')"></strong>
            </div>
            <div class="ds-filter-field">
                <span class="ds-label">يحتاج تحسين</span>
                <strong class="ds-ltr-num" x-text="phaseCount(activePhase, 'يحتاج تحسين')"></strong>
            </div>
            <div class="ds-filter-field">
                <span class="ds-label">غير مجرّب</span>
                <strong class="ds-ltr-num" x-text="phaseCount(activePhase, 'غير مجرّب')"></strong>
            </div>
            <div class="ds-filter-field">
                <label class="ds-label" for="uat-filter">تصفية داخل المرحلة</label>
                <select id="uat-filter" class="ds-input" x-model="filter">
                    <option value="الكل">الكل</option>
                    @foreach ($verdicts as $verdict)
                        <option value="{{ $verdict }}">{{ $verdict }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <template x-if="isPhaseComplete(activePhase) && activePhase < 3">
            <div class="ds-alert ds-alert-success ds-mb-3">
                اكتملت المرحلة <span class="ds-ltr-num" x-text="activePhase"></span>.
                <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" @click="selectPhase(activePhase + 1)">
                    الانتقال للمرحلة التالية
                </button>
            </div>
        </template>

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
    Alpine.data('uatToolsChecklist', (groups, phases, baseline = {}, baselineRound3 = {}, baselineRound2 = {}) => ({
        groups,
        phases,
        baseline: baseline || {},
        baselineRound3: baselineRound3 || {},
        baselineRound2: baselineRound2 || {},
        activePhase: 1,
        filter: 'الكل',
        verdicts: {},
        tags: {},
        notes: {},
        copying: false,
        copied: false,
        storageKey: 'hollal.uat.tools.v5',

        get total() {
            return this.groups.reduce((sum, g) => sum + g.items.length, 0);
        },

        phaseGroups(phaseId) {
            return this.groups.filter((g) => Number(g.phase) === Number(phaseId));
        },

        phaseTools(phaseId) {
            return this.phaseGroups(phaseId).flatMap((g) => g.items.map((t) => ({ ...t, group: g.title })));
        },

        phaseToolCount(phaseId) {
            return this.phaseTools(phaseId).length;
        },

        phaseCount(phaseId, verdict) {
            return this.phaseTools(phaseId).filter((t) => (this.verdicts[t.id] || 'غير مجرّب') === verdict).length;
        },

        phaseAccepted(phaseId) {
            return this.phaseCount(phaseId, 'يعتمد');
        },

        isPhaseComplete(phaseId) {
            const tools = this.phaseTools(phaseId);
            if (!tools.length) return false;
            return tools.every((t) => (this.verdicts[t.id] || 'غير مجرّب') === 'يعتمد');
        },

        isPhaseUnlocked(phaseId) {
            if (Number(phaseId) <= 1) return true;
            return this.isPhaseComplete(Number(phaseId) - 1);
        },

        selectPhase(phaseId) {
            if (!this.isPhaseUnlocked(phaseId)) return;
            this.activePhase = Number(phaseId);
            this.persist();
        },

        applyBaseline(source) {
            const b = source || this.baseline || {};
            this.verdicts = Object.assign({}, b.verdicts || {});
            this.tags = Object.assign({}, b.tags || {});
            this.notes = Object.assign({}, b.notes || {});
            this.fillMissing();
            this.activePhase = this.highestUnlockedPhase();
        },

        highestUnlockedPhase() {
            let max = 1;
            this.phases.forEach((p) => {
                if (this.isPhaseUnlocked(p.id)) max = p.id;
            });
            return max;
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
                    this.activePhase = parsed.activePhase || 1;
                    loaded = Object.keys(this.verdicts).length > 0;
                }
            } catch (e) {}

            if (!loaded && this.baseline && this.baseline.verdicts) {
                this.applyBaseline(this.baseline);
                this.persist();
            } else {
                this.fillMissing();
                if (!this.isPhaseUnlocked(this.activePhase)) {
                    this.activePhase = this.highestUnlockedPhase();
                }
            }
        },

        loadBaseline() {
            if (!confirm('استبدال التقييم الحالي بتقييم 2026-08-14 20:27؟')) return;
            this.applyBaseline(this.baseline);
            this.persist();
            if (window.Livewire) {
                Livewire.dispatch('toast', { type: 'success', message: 'تم تحميل تقييم 20:27' });
            }
        },

        loadRound3() {
            if (!confirm('استبدال التقييم الحالي بتقييم 19:04؟')) return;
            this.applyBaseline(this.baselineRound3);
            this.persist();
            if (window.Livewire) {
                Livewire.dispatch('toast', { type: 'success', message: 'تم تحميل تقييم 19:04' });
            }
        },

        loadRound2() {
            if (!confirm('استبدال التقييم الحالي بتقييم التجربة الثانية (2026-08-13)؟')) return;
            this.applyBaseline(this.baselineRound2);
            this.persist();
            if (window.Livewire) {
                Livewire.dispatch('toast', { type: 'success', message: 'تم تحميل التجربة الثانية' });
            }
        },

        persist() {
            localStorage.setItem(this.storageKey, JSON.stringify({
                verdicts: this.verdicts,
                tags: this.tags,
                notes: this.notes,
                activePhase: this.activePhase,
                source: 'uat-baseline-round4',
            }));
        },

        count(verdict) {
            return this.allTools().filter((t) => (this.verdicts[t.id] || 'غير مجرّب') === verdict).length;
        },

        allTools() {
            return this.groups.flatMap((g) => g.items.map((t) => ({ ...t, group: g.title, phase: g.phase })));
        },

        visibleGroups() {
            let list = this.phaseGroups(this.activePhase);
            if (this.filter !== 'الكل') {
                list = list
                    .map((g) => ({
                        ...g,
                        items: g.items.filter((t) => (this.verdicts[t.id] || 'غير مجرّب') === this.filter),
                    }))
                    .filter((g) => g.items.length > 0);
            }
            return list;
        },

        isHttpPath(path) {
            return typeof path === 'string' && path.startsWith('/');
        },

        buildReport() {
            const tools = this.allTools();
            const lines = [
                '# تقرير تقييم أدوات — منصة حلّل (3 مراحل)',
                `التاريخ: ${new Date().toISOString().slice(0, 16).replace('T', ' ')}`,
                `الملخص الكلي: ${this.total} إجمالي · ${this.count('يعتمد')} يعتمد · ${this.count('يحتاج تحسين')} يحتاج تحسين · ${this.count('غير مجرّب')} غير مجرّب`,
                '',
            ];

            this.phases.forEach((phase) => {
                const done = this.isPhaseComplete(phase.id) ? 'مكتملة' : (this.isPhaseUnlocked(phase.id) ? 'مفتوحة' : 'مقفلة');
                lines.push(`## ${phase.title} — ${done}`);
                lines.push(`الهدف: ${phase.goal}`);
                lines.push(`التقدم: ${this.phaseAccepted(phase.id)}/${this.phaseToolCount(phase.id)} يعتمد`);
                lines.push('');
            });

            lines.push('---');
            lines.push('الهدف: إصلاح الملاحظات أدناه في الكود.');
            lines.push('');

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
                    lines.push(`### مرحلة ${t.phase} — ${t.group} — ${t.tool}`);
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
            if (!confirm('مسح التقييم المحلي ثم تحميل ملاحظات 20:27؟')) return;
            localStorage.removeItem(this.storageKey);
            this.applyBaseline(this.baseline);
            this.persist();
            if (window.Livewire) {
                Livewire.dispatch('toast', { type: 'success', message: 'أُعيد تحميل تقييم 20:27' });
            }
        },
    }));
</script>
@endscript
