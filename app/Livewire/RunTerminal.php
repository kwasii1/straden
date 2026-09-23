<?php

namespace App\Livewire;

use App\Models\Run;
use App\Services\RunResultService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RunTerminal extends Component
{
    public Run $run;

    public int $offset = 0;

    public bool $finished = false;

    public function mount(Run $run): void
    {
        $this->run = $run;

        $chunk = RunResultService::readLogChunk($run->id, 0, 262144);

        $this->streamChunk($chunk);
        $this->offset = $chunk['nextOffset'];

        $this->finished = ! $this->isActive() && $chunk['eof'];
    }

    public function isActive(): bool
    {
        return in_array($this->run->status, ['queued', 'running'], true);
    }

    public function poll(): void
    {
        $chunk = RunResultService::readLogChunk($this->run->id, $this->offset);

        $this->streamChunk($chunk);
        $this->offset = $chunk['nextOffset'];

        $this->finished = ! $this->isActive() && $chunk['eof'];
    }

    /**
     * Emit newly read log content to the browser instead of accumulating it in
     * component state, keeping the Livewire payload small as logs grow.
     *
     * @param  array{content: string, nextOffset: int, eof: bool}  $chunk
     */
    private function streamChunk(array $chunk): void
    {
        if ($chunk['content'] !== '') {
            $this->dispatch('log-chunk', content: $chunk['content']);
        }
    }

    public function render(): View
    {
        return view('livewire.run-terminal');
    }
}
