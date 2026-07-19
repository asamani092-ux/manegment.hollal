<?php

namespace App\Livewire\Programs;

use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\ProgramPrice;
use App\Services\ProgramService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 06A-B1 — full program card: prices, versions (history preserved), private
 * files, platform link + steps, executing organizations (derived), and the
 * «مشروع تطوير» button.
 */
class ProgramShow extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Program $program;

    /** @var array<string, string> service_type => price */
    public array $prices = [];

    public ?string $fileTitle = null;

    public string $fileKind = ProgramFile::KIND_OTHER;

    public $upload;

    public function mount(Program $program): void
    {
        $this->authorize('projects.programs.view');
        $this->program = $program;

        foreach (ProgramPrice::SERVICES as $service) {
            $this->prices[$service] = (string) ($program->prices->firstWhere('service_type', $service)?->unit_price ?? '');
        }
    }

    public function savePrices(): void
    {
        $this->authorize('projects.programs.manage');

        $this->validate([
            'prices.*' => 'nullable|numeric|min:0',
        ]);

        $payload = [];
        foreach ($this->prices as $service => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $payload[$service] = (float) $value;
        }

        app(ProgramService::class)->setPrices($this->program, $payload, auth()->user());

        $this->program = $this->program->fresh(['prices', 'versions']);
        $this->dispatch('ds-toast', message: 'تم تحديث الأسعار وتسجيل إصدار جديد');
    }

    public function uploadFile(): void
    {
        $this->authorize('projects.programs.manage');

        $this->validate([
            'fileTitle' => 'required|string|max:255',
            'fileKind' => 'required|in:'.implode(',', ProgramFile::KINDS),
            'upload' => 'required|file|max:20480|mimes:pdf,doc,docx,ppt,pptx,zip',
        ], [], [
            'fileTitle' => 'عنوان الملف',
            'upload' => 'الملف',
        ]);

        $path = $this->upload->store('programs/'.$this->program->id, 'local');

        ProgramFile::create([
            'program_id' => $this->program->id,
            'title' => $this->fileTitle,
            'kind' => $this->fileKind,
            'path' => $path,
            'uploaded_by' => auth()->id(),
        ]);

        $this->fileTitle = null;
        $this->fileKind = ProgramFile::KIND_OTHER;
        $this->upload = null;
        $this->dispatch('ds-toast', message: 'تم رفع الملف');
    }

    public function deleteFile(int $fileId): void
    {
        $this->authorize('projects.programs.manage');

        $file = ProgramFile::where('program_id', $this->program->id)->findOrFail($fileId);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        $this->dispatch('ds-toast', message: 'تم حذف الملف');
    }

    /** «مشروع تطوير» — spin up an internal project bound to this program. */
    public function createDevelopmentProject(): void
    {
        $this->authorize('projects.programs.manage');

        $project = app(ProgramService::class)->createDevelopmentProject($this->program, auth()->user());

        $this->redirectRoute('projects.show', $project->id, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.programs.program-show', [
            'program' => $this->program->load(['prices', 'files', 'versions.editor', 'currentVersion']),
            'services' => ProgramPrice::SERVICES,
            'fileKinds' => ProgramFile::KINDS,
            'executingOrganizations' => $this->program->executingOrganizations(),
        ])->layout('layouts.app', ['title' => $this->program->name]);
    }
}
