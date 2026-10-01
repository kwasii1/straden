<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncAiModels extends Command
{
    protected $signature = 'ai:sync-models';

    protected $description = 'Download the text model catalog for supported providers from models.dev';

    /**
     * Map of our provider keys to models.dev provider ids.
     *
     * @var array<string, string>
     */
    private const PROVIDERS = [
        'anthropic' => 'anthropic',
        'azure' => 'azure',
        'bedrock' => 'amazon-bedrock',
        'deepseek' => 'deepseek',
        'gemini' => 'google',
        'groq' => 'groq',
        'mistral' => 'mistral',
        'openai' => 'openai',
        'openrouter' => 'openrouter',
        'xai' => 'xai',
    ];

    private const EXCLUDED_PATTERN = '/realtime|image|live|preview|audio|tts|transcribe|search|codex|deep-research|embed|vision|-exp|daybreak|safeguard|voxtral|^zai-|^labs-|^(us|eu|global|jp|apac|au)\./';

    public function handle(): int
    {
        $response = Http::timeout(60)->get('https://models.dev/api.json');

        if (! $response->successful()) {
            $this->error('Failed to download models.dev catalog.');

            return self::FAILURE;
        }

        $data = $response->json();
        $catalog = [];

        foreach (self::PROVIDERS as $key => $remoteKey) {
            $models = collect($data[$remoteKey]['models'] ?? [])
                ->filter(fn (array $model) => in_array('text', $model['modalities']['output'] ?? [], true)
                    && ($model['tool_call'] ?? false)
                    && ($model['status'] ?? null) !== 'deprecated'
                    && ! preg_match(self::EXCLUDED_PATTERN, $model['id']))
                ->sortByDesc(fn (array $model) => $model['release_date'] ?? '')
                ->pluck('id')
                ->values()
                ->all();

            $catalog[$key] = $models;
            $this->line(sprintf('%s: %d models', $key, count($models)));
        }

        file_put_contents(
            resource_path('data/ai-models.json'),
            json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        $this->info('Catalog written to resources/data/ai-models.json');

        return self::SUCCESS;
    }
}
