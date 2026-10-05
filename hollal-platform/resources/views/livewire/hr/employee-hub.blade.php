<x-ds-page>
    <x-ds-page-header title="مساحتي" />
    <livewire:users.employee-profile-show :user="auth()->user()" :key="'self-file-'.auth()->id()" />
</x-ds-page>
