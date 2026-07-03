<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'category' => fake()->randomElement(['عقود', 'تقارير', 'مراسلات', 'مالية']),
            'project_id' => null,
            'confidentiality' => 'department',
            'uploader_id' => User::factory(),
            'path' => 'documents/'.fake()->uuid().'.pdf',
        ];
    }

    public function forProject(?Project $project = null): static
    {
        return $this->state(fn () => [
            'project_id' => $project?->id ?? Project::factory(),
            'confidentiality' => 'team',
        ]);
    }

    public function departmentConfidential(): static
    {
        return $this->state(fn () => ['confidentiality' => 'department', 'project_id' => null]);
    }

    public function managersConfidential(): static
    {
        return $this->state(fn () => ['confidentiality' => 'managers', 'project_id' => null]);
    }
}
