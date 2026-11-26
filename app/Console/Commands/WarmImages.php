<?php

namespace App\Console\Commands;

use App\Support\Img;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Pre-make all resized WebP images (e.g. after deploying or importing products). */
class WarmImages extends Command
{
    protected $signature = 'images:warm {--width=* : Only these widths (default: every size the shop uses)}';

    protected $description = 'Create resized WebP copies of all uploaded images';

    public function handle(): int
    {
        $widths = array_map('intval', $this->option('width'));
        $files = collect(Img::FOLDERS)
            ->flatMap(fn ($dir) => Storage::disk('public')->allFiles($dir))
            ->filter(fn ($path) => Img::canResize($path));

        $this->withProgressBar($files, function ($path) use ($widths) {
            if ($widths === []) {
                Img::warm($path); // every size, skipping ones larger than the original

                return;
            }
            foreach ($widths as $w) {
                try {
                    Img::make($path, $w);
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->warn("Skipped {$path}: {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info("Done: {$files->count()} images.");

        return self::SUCCESS;
    }
}
