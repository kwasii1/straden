<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DownloadProviderLogos extends Command
{
    protected $signature = 'logos:download
                            {--force : Overwrite existing files}';

    protected $description = 'Download provider logos from GitHub';

    private function getLogoUrls(): array
    {
        $baseUrl = 'https://raw.githubusercontent.com/ln-dev7/logos-apps/master/logos';

        return [
            'openai' => ["{$baseUrl}/openai.svg"],
            'anthropic' => ["{$baseUrl}/anthropic.svg"],
            'deepseek' => ["{$baseUrl}/deepseek.svg"],
            'google-gemini' => ["{$baseUrl}/google-gemini.svg"],
            'groq' => ["{$baseUrl}/groq.svg"],
            'x-ai' => ["{$baseUrl}/x-ai.svg"],
            'mistral-ai' => ["{$baseUrl}/mistral-ai.svg"],
            'cohere' => ["{$baseUrl}/cohere.svg"],
            'openrouter' => ["{$baseUrl}/openrouter.svg"],
            'ollama' => ["{$baseUrl}/ollama.svg"],
            'azure' => ["{$baseUrl}/azure.svg"],
            'aws' => ["{$baseUrl}/aws.svg"],
            'vllm' => ["{$baseUrl}/vllm.svg"],
            'jina-ai' => [
                "{$baseUrl}/jina-ai.svg",
                'https://raw.githubusercontent.com/lobehub/lobe-icons/master/packages/static-svg/icons/jina.svg',
            ],
            'voyage-ai' => [
                "{$baseUrl}/voyage-ai.svg",
                'https://raw.githubusercontent.com/lobehub/lobe-icons/master/packages/static-svg/icons/voyage.svg',
            ],
            'eleven-labs' => ["{$baseUrl}/eleven-labs.svg"],
        ];
    }

    public function handle()
    {
        $logos = $this->getLogoUrls();

        // Use the public disk
        $disk = Storage::disk('public');
        $path = 'images/providers';

        // Create directory if it doesn't exist
        if (!$disk->exists($path)) {
            $disk->makeDirectory($path);
            $this->info("Created directory: storage/app/public/{$path}");
        }

        $this->info('Downloading provider logos...');
        $bar = $this->output->createProgressBar(count($logos));

        $downloaded = 0;
        $failed = 0;

        foreach ($logos as $logo => $sourceUrls) {
            $filename = "{$logo}.svg";
            $filepath = "{$path}/{$filename}";

            // Skip if file exists and not forcing
            if ($disk->exists($filepath) && !$this->option('force')) {
                $bar->advance();
                continue;
            }

            $downloadedSuccess = false;

            foreach ($sourceUrls as $sourceUrl) {
                try {
                    $response = Http::timeout(10)->get($sourceUrl);

                    if ($response->successful()) {
                        $disk->put($filepath, $response->body());
                        $downloaded++;
                        $downloadedSuccess = true;
                        break;
                    }
                } catch (\Exception $e) {
                    // Log error if needed, continue to next source URL
                    continue;
                }
            }

            if (!$downloadedSuccess) {
                $failed++;
                $this->newLine();
                $this->warn("Failed to download {$filename} from all sources");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Check if storage symlink exists
        if (!is_link(public_path('storage'))) {
            $this->error('⚠️  Storage symlink not found!');
            $this->info('Run: php artisan storage:link');
            $this->info('This will create the symlink so logos can be accessed publicly.');
        } else {
            $this->info("✅ Downloaded: {$downloaded}");
            $this->info("❌ Failed: {$failed}");
            $this->info("📁 Logos saved to: storage/app/public/{$path}");
            $this->info("📂 Public URL: " . asset("storage/{$path}/openai.svg"));
        }
    }
}
