<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class Airline extends Model
{

    protected $fillable = [
        'name',
        'status',
    ];

    /**
     * Logos uploaded from Ticketing → Airlines (owner, 2026-10-01), so a new
     * airline needs no hand-copied file on the server. Stored as
     * public/airline-logos/uploaded/airline-{id}-{time}.png: PNG always (any
     * upload is converted), the time in the name busts the browser cache on
     * a replace. The hand-placed marks in public/airline-logos/ are untouched
     * and still used for every airline without an upload.
     */
    public const LOGO_DIR = 'airline-logos/uploaded';

    /** id => file name of each airline's current uploaded logo. */
    public static function uploadedLogoFiles(): array
    {
        $map = [];
        foreach (glob(public_path(self::LOGO_DIR . '/airline-*.png')) ?: [] as $path) {
            if (preg_match('/airline-(\d+)-(\d+)\.png$/', $path, $m)) {
                $id = (int) $m[1];
                if (! isset($map[$id]) || (int) $m[2] > $map[$id][1]) {
                    $map[$id] = [basename($path), (int) $m[2]];
                }
            }
        }
        return array_map(fn ($v) => $v[0], $map);
    }

    public function uploadedLogoUrl(): ?string
    {
        $file = self::uploadedLogoFiles()[$this->id] ?? null;
        return $file ? asset(self::LOGO_DIR . '/' . $file) : null;
    }

    /**
     * The uploaded logos as brand entries for the shared airline-brands
     * list: slug is the path under public/airline-logos/ without ".png".
     */
    public static function uploadedLogoBrands(): array
    {
        $files = self::uploadedLogoFiles();
        if (! $files) {
            return [];
        }
        return self::whereIn('id', array_keys($files))->get(['id', 'name'])
            ->map(fn (Airline $a) => [
                'name' => $a->name,
                'code' => preg_match('/\(([A-Z0-9]{2,3})\)\s*$/i', (string) $a->name, $m) ? strtoupper($m[1]) : '',
                'slug' => 'uploaded/' . substr($files[$a->id], 0, -4),
            ])->values()->all();
    }

    /** Saves an upload as this airline's logo (converted to PNG), replacing any earlier one. */
    public function storeLogo(UploadedFile $file): bool
    {
        $img = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $img) {
            return false;
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $dir = public_path(self::LOGO_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ok = imagepng($img, $dir . '/airline-' . $this->id . '-' . time() . '.png');
        imagedestroy($img);
        if ($ok) {
            $this->removeLogo(keepNewest: true);
        }
        return $ok;
    }

    /** Deletes this airline's uploaded logo(s); the built-in mark, if any, shows again. */
    public function removeLogo(bool $keepNewest = false): void
    {
        $paths = glob(public_path(self::LOGO_DIR . '/airline-' . $this->id . '-*.png')) ?: [];
        rsort($paths); // newest first (the time is in the name)
        foreach ($keepNewest ? array_slice($paths, 1) : $paths as $path) {
            @unlink($path);
        }
    }
}
