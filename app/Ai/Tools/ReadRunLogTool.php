<?php

namespace App\Ai\Tools;

use App\Models\Run;
use App\Services\RunResultService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadRunLogTool implements Tool
{
    public function __construct(public Run $run) {}

    public function description(): Stringable|string
    {
        return 'Fetch the raw k6 console output log for this run. Use this to inspect the actual k6 output — init/output warnings, per-VU errors, console messages — that are not captured in the summary metrics or InfluxDB data.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! RunResultService::hasLog($this->run->id)) {
            return json_encode([
                'available' => false,
                'error' => 'No run log is available. Logs are only persisted when the project has "persist logs" enabled.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $content = RunResultService::readLogChunk($this->run->id, 0)['content'];

        $truncated = false;

        if (strlen($content) > self::MAX_BYTES) {
            $content = '...'.substr($content, -self::MAX_BYTES);
            $truncated = true;
        }

        return json_encode([
            'available' => true,
            'run_id' => $this->run->id,
            'truncated' => $truncated,
            'content' => $content,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    private const MAX_BYTES = 65535;
}
