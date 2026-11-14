<?php

namespace App\Services\Tools;

/**
 * The free browser tools under /tools.
 *
 * One list, read by the header menu, the mobile menu, the tools index and
 * each tool page, so a tool is added, renamed or reordered in one place.
 *
 * Every tool runs entirely in the visitor's browser: there is no upload
 * endpoint, no third-party API and no key behind any of them.
 */
class ToolRegistry
{
    private const TOOLS = [
        'converter' => [
            'nav' => 'Converter',
            'name' => 'Image Converter',
            'icon' => 'bi-arrow-left-right',
            'tone' => 'mint',
            'tagline' => 'Convert PNG, JPG, WebP and more',
            'description' => 'Turn images into PNG, JPG, WebP, BMP, ICO or PDF. Convert one file or a whole batch and download them together.',
        ],
        'compressor' => [
            'nav' => 'Compressor',
            'name' => 'Image Compressor',
            'icon' => 'bi-arrows-angle-contract',
            'tone' => 'violet',
            'tagline' => 'Shrink JPG, PNG and WebP files',
            'description' => 'Make images smaller with a quality slider, a side-by-side preview and the exact size you save on every file.',
        ],
        'qr-generator' => [
            'nav' => 'QR Generator',
            'name' => 'QR Code Generator',
            'icon' => 'bi-qr-code',
            'tone' => 'sky',
            'tagline' => 'Custom QR codes as PNG or SVG',
            'description' => 'Create QR codes for links, text, email and Wi-Fi. Pick colours and shapes, add a logo, then download as PNG or SVG.',
        ],
        'editor' => [
            'nav' => 'Editor',
            'name' => 'Image Editor',
            'icon' => 'bi-sliders',
            'tone' => 'rose',
            'tagline' => 'Crop, resize, adjust and annotate',
            'description' => 'Crop, rotate, resize, tune colours, apply filters, draw and add text — then export as PNG, JPG or WebP.',
        ],
    ];

    /**
     * URL of a file under public/ico-ext, with the file's modified time
     * appended so browsers fetch a new copy whenever it is replaced.
     */
    public static function asset(string $file): string
    {
        $path = public_path('ico-ext/'.$file);

        return asset('ico-ext/'.$file).'?v='.(is_file($path) ? filemtime($path) : '1');
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::TOOLS);
    }

    /** @return list<array<string,string>> */
    public function all(): array
    {
        $tools = [];

        foreach (self::TOOLS as $key => $tool) {
            $tools[] = $tool + ['key' => $key, 'url' => route('tools.show', ['tool' => $key])];
        }

        return $tools;
    }

    public function find(string $key): ?array
    {
        foreach ($this->all() as $tool) {
            if ($tool['key'] === $key) {
                return $tool;
            }
        }

        return null;
    }
}
