<?php

namespace App\Livewire;

use App\Jobs\GenerateRunInsightJob;
use App\Models\Project;
use App\Models\Run;
use App\Models\RunInsight;
use App\Services\AiCredentialManager;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RunInsightPanel extends Component
{
    public Run $run;

    public function mount(Run $run): void
    {
        $this->run = $run;
    }

    public function isActive(): bool
    {
        return in_array($this->run->status, ['queued', 'running'], true);
    }

    #[Computed]
    public function insight(): ?RunInsight
    {
        return $this->run->insight()->latest('id')->first();
    }

    #[Computed]
    public function insightsSelection(): ?array
    {
        return app(AiCredentialManager::class)->getInsightsSelection();
    }

    #[Computed]
    public function insightsReady(): bool
    {
        return app(AiCredentialManager::class)->isInsightsSelectionUsable();
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->run->script->test->project;
    }

    public function generate(): void
    {
        if ($this->isActive()) {
            Flux::toast(variant: 'warning', text: 'Wait for the run to finish before generating insights.');

            return;
        }

        if (! $this->insightsReady) {
            Flux::toast(variant: 'warning', text: 'Choose an insights model in Settings first.');

            return;
        }

        $insight = $this->insight ?? RunInsight::create([
            'run_id' => $this->run->id,
            'status' => 'queued',
        ]);

        if ($insight->status === 'completed') {
            $insight->update([
                'status' => 'queued',
                'report' => null,
                'error' => null,
            ]);
        }

        GenerateRunInsightJob::dispatch($this->run->id, $insight->id);
    }

    public function refresh(): void
    {
        unset($this->insight);
    }

    public function render()
    {
        return view('livewire.run-insight-panel');
    }
}
