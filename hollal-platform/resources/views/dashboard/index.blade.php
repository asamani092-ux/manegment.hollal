@extends('layouts.app')

@section('title', 'الرئيسية')

@section('content')
    <h1 class="ds-page-title">الرئيسية</h1>

    <div class="ds-stats-grid">
        <div class="ds-stat-card">
            <div class="ds-stat-mini-label">مرحباً</div>
            <div class="ds-stat-mini-val">{{ auth()->user()->name }}</div>
        </div>
        <div class="ds-stat-card">
            <div class="ds-stat-mini-label">الدور</div>
            <div class="ds-stat-mini-val">
                @if (auth()->user()->roles->first())
                    <x-ds-role-label :name="auth()->user()->roles->first()->name" />
                @else
                    —
                @endif
            </div>
        </div>
        <div class="ds-stat-card">
            <div class="ds-stat-mini-label">القسم</div>
            <div class="ds-stat-mini-val">{{ auth()->user()->department?->name ?? '—' }}</div>
        </div>
    </div>

    <section class="ds-section ds-section-spaced">
        <h2 class="ds-section-title">
            <i class="fas fa-chart-line ds-section-icon"></i>
            منصة حلل للإدارة
        </h2>
        <p class="ds-text-muted">نظرة عامة على حسابك وصلاحياتك في المنصة.</p>
    </section>
@endsection
