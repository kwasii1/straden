<?php

namespace App\Livewire;

use App\Models\Run;
use App\Services\RunResultService;
use Livewire\Component;

class RunTerminal extends Component
{
    public Run $run;

    public int $offset = 0;

    public string $content = '';

    public bool $finished = false;

    public function mount(Run $run): void
    {
        $this->run = $run;

        $chunk = RunResultService::readLogChunk($run->id, 0, 262144);
        $this->content = $chunk['content'];
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

        if ($chunk['content'] !== '') {
            $this->content .= $chunk['content'];
        }

        $this->offset = $chunk['nextOffset'];

        $this->finished = ! $this->isActive() && $chunk['eof'];
    }

    public function render()
    {
        return view('livewire.run-terminal');
    }
}
