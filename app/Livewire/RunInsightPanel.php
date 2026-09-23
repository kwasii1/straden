<?php

namespace App\Livewire;

use App\Jobs\GenerateRunInsightJob;
use App\Models\Project;
use App\Models\Run;
use App\Models\RunInsight;
use App\Services\AiCredentialManager;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property-read RunInsight|null $insight
 * @property-read array{provider: string, model: string}|null $insightsSelection
 * @property-read bool $insightsReady
 * @property-read Project|null $project
 */
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

    /**
     * @return array{provider: string, model: string}|null
     */
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

    public function export(): ?StreamedResponse
    {
        $insight = $this->insight;

        if (! $insight || $insight->status !== 'completed' || empty($insight->report)) {
            Flux::toast(variant: 'warning', text: 'No completed insight report to export yet.');

            return null;
        }

        $report = $insight->report;
        $filename = 'insight-'.$this->run->slug.'.md';
        $markdown = $this->toMarkdown($report);

        return response()->streamDownload(function () use ($markdown): void {
            echo $markdown;
        }, $filename, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function toMarkdown(array $report): string
    {
        $lines = [
            '# AI Insights — Run '.$this->run->slug,
            '',
            'Generated: '.$this->insight->updated_at?->toDateTimeString().' UTC',
            'System health: '.ucfirst((string) ($report['overall_health'] ?? 'acceptable')),
            '',
        ];

        if (! empty($report['summary'])) {
            $lines[] = '## Executive summary';
            $lines[] = '';
            $lines[] = (string) $report['summary'];
            $lines[] = '';
        }

        if (! empty($report['what_is_slow'])) {
            $lines[] = '## Performance bottlenecks';
            $lines[] = '';
            $lines[] = (string) $report['what_is_slow'];
            $lines[] = '';
        }

        if (! empty($report['key_findings'])) {
            $lines[] = '## Key diagnostic findings';
            $lines[] = '';
            foreach ($report['key_findings'] as $finding) {
                $severity = strtoupper((string) ($finding['severity'] ?? 'medium'));
                $lines[] = '- ['.$severity.'] '.($finding['title'] ?? '');
                if (! empty($finding['detail'])) {
                    $lines[] = '  '.$finding['detail'];
                }
            }
            $lines[] = '';
        }

        if (! empty($report['recommendations'])) {
            $lines[] = '## Recommended actions';
            $lines[] = '';
            foreach ($report['recommendations'] as $index => $recommendation) {
                $lines[] = ($index + 1).'. '.($recommendation['title'] ?? '');
                if (! empty($recommendation['impact'])) {
                    $lines[] = '   Impact: '.$recommendation['impact'];
                }
                if (! empty($recommendation['detail'])) {
                    $lines[] = '   '.$recommendation['detail'];
                }
            }
            $lines[] = '';
        }

        if (! empty($report['script_observations'])) {
            $lines[] = '## Script observations';
            $lines[] = '';
            $lines[] = (string) $report['script_observations'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function render(): View
    {
        return view('livewire.run-insight-panel');
    }
}
