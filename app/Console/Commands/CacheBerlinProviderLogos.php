<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CacheBerlinProviderLogos extends Command
{
    protected $signature = 'providers:cache-logos';

    protected $description = 'Download Berlin provider logos to public/images/providers and update berlin-providers.json';

    /**
     * @var array<string, array{url: string, headers?: array<string, string>}>
     */
    private array $sources = [
        'degewo AG' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/b/b2/Degewo_Logo.svg'],
        'Gewobag' => ['url' => 'https://www.gewobag.de/wp-content/uploads/2019/04/unternehmen_logo.jpg'],
        'HOWOGE' => ['url' => 'https://www.inberlinwohnen.de/svg/generic/sites/default/files/2023-05/howoge_weiss_0.svg', 'headers' => ['Referer' => 'https://www.inberlinwohnen.de/']],
        'GESOBAU AG' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/0/0c/Gesobau_Logo.png'],
        'STADT UND LAND' => ['url' => 'https://a.storyblok.com/f/236636/300x66/a6bda076cc/logo.svg'],
        'WBM Wohnungsbaugesellschaft Berlin-Mitte' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/9/9a/WBM_Wohnungsbaugesellschaft_Berlin-Mitte_logo_%282024%29.svg'],
        'berlinovo' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/0/08/Berlinovo_Wortmarke.svg'],
        'inberlinwohnen' => [
            'url' => 'https://www.inberlinwohnen.de/img/images/default/ihre-landeseigenen-wohnungsbaugesellschaften.png',
            'headers' => ['Referer' => 'https://www.inberlinwohnen.de/'],
        ],
        'Berliner Wasserbetriebe' => ['url' => 'https://h2berlin.org/wp-a807c-content/uploads/2021/04/bwb.png'],
        'Stromnetz Berlin' => ['url' => 'https://www.stromnetz.berlin/files/ui/images/logo/logotype.svg'],
        'GASAG' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/3/3f/GASAG_logo.svg'],
        'Vattenfall Wärme Berlin' => ['url' => 'https://static.vattenfall.de/4-28-0/images/logo/VF_logo_linear_grey_RGB.svg'],
        'BVG' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/b/bf/BVG_Logo_07.2021.svg'],
        'Deutsche Bahn' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/d/d5/Deutsche_Bahn_AG-Logo.svg'],
        'Telekom' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/d/dd/Deutsche_Telekom_2022.svg'],
        'Vodafone' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/5/5f/Vodafone_logo_2017.svg'],
        'O2 Telefónica' => ['url' => 'https://cdn.simpleicons.org/o2'],
        '1&1' => ['url' => 'https://cdn.simpleicons.org/1and1'],
        'Techniker Krankenkasse' => ['url' => 'https://www.tk.de/blueprint/static/assets/base/images/logo.png'],
        'AOK Nordost' => ['url' => 'https://www.aok.de/pk/static/logo-social-media-3a2e5257eec48790d073aca432c19414.jpg'],
        'REWE' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/4/4c/Logo_REWE.svg'],
        'Edeka' => ['url' => 'https://cdn.simpleicons.org/edeka'],
        'Lidl' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/9/91/Lidl-Logo.svg'],
        'Aldi Nord' => ['url' => 'https://cdn.simpleicons.org/aldinord'],
        'Netto Marken-Discount' => ['url' => 'https://cdn.simpleicons.org/netto'],
        'Penny' => ['url' => 'https://cdn.simpleicons.org/penny'],
        'Kaufland' => ['url' => 'https://cdn.simpleicons.org/kaufland'],
        'dm-drogerie markt' => ['url' => 'https://cdn.simpleicons.org/dm'],
        'Rossmann' => ['url' => 'https://cdn.simpleicons.org/rossmann'],
        'Netflix' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg'],
        'Spotify' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/8/84/Spotify_icon.svg'],
        'Amazon Prime' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Amazon_logo.svg'],
        'McFit' => ['url' => 'https://content.rsggroup.com/image/upload/q_auto,f_auto/v1698702263/McFIT/Allgemeines/Logos/McFIT_Logo_Bildmarke.png'],
        'FitX' => ['url' => 'https://www.fitx.de/img/block/shared/header/fitx_logo.svg'],
        'Deutsche Bank' => ['url' => 'https://cdn.simpleicons.org/deutschebank'],
        'Sparkasse Berlin' => ['url' => 'https://cdn.simpleicons.org/sparkasse'],
    ];

    /**
     * @var array<string, string>
     */
    private array $slugOverrides = [
        '1&1' => '1und1',
    ];

    public function handle(): int
    {
        $path = database_path('data/berlin-providers.json');

        if (! file_exists($path)) {
            $this->error('berlin-providers.json not found.');

            return self::FAILURE;
        }

        $catalog = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $outputDir = public_path('images/providers');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $cached = 0;
        $failed = 0;

        foreach ($catalog['providers'] as &$entry) {
            $name = $entry['name'];

            if (! isset($this->sources[$name])) {
                $this->warn("No source URL configured for: {$name}");
                $failed++;

                continue;
            }

            $source = $this->sources[$name];
            $slug = $this->slugOverrides[$name] ?? Str::slug($name);
            $extension = $this->guessExtension($source['url']);
            $filename = "{$slug}.{$extension}";
            $destination = "{$outputDir}/{$filename}";

            $body = $this->download($source['url'], $source['headers'] ?? []);

            if ($body === null) {
                $this->error("Failed to download {$name}");
                $failed++;

                continue;
            }

            file_put_contents($destination, $this->transformLogo($name, $body));
            $entry['logo'] = '/images/providers/'.$filename;
            $cached++;

            $this->line("Cached {$name} → {$entry['logo']}");

            if (str_contains($source['url'], 'wikimedia.org')) {
                sleep(2);
            }
        }

        unset($entry);

        file_put_contents(
            $path,
            json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );

        $this->info("Done: {$cached} cached, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function download(string $url, array $headers = []): ?string
    {
        $request = Http::withHeaders(array_merge(
            ['User-Agent' => 'BudgetApp/1.0 (provider logo cache)'],
            $headers,
        ))->timeout(30);

        foreach ([0, 3, 8] as $delaySeconds) {
            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }

            $response = $request->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            if ($response->status() !== 429) {
                break;
            }
        }

        return null;
    }

    private function transformLogo(string $name, string $body): string
    {
        if ($name === 'HOWOGE') {
            return str_replace('#FFFFFF', '#E4002B', $body);
        }

        return $body;
    }

    private function guessExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'svg', 'png', 'jpg', 'jpeg', 'webp', 'gif' => $extension === 'jpeg' ? 'jpg' : $extension,
            default => 'svg',
        };
    }
}
