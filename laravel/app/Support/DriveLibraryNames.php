<?php
// Pure naming rules shared by the importer (kept dependency-free so they can be unit-checked).
namespace App\Support;

final class DriveLibraryNames
{
    private const UPPER = [
        'ai' => 'AI', 'ui' => 'UI', 'vr' => 'VR', 'ar' => 'AR', 'it' => 'IT', 'atm' => 'ATM', 'usb' => 'USB',
        'vpn' => 'VPN', 'qr' => 'QR', '3d' => '3D', 'seo' => 'SEO', 'api' => 'API', 'cpu' => 'CPU',
        'gpu' => 'GPU', 'ev' => 'EV', 'tv' => 'TV', 'pc' => 'PC', 'sim' => 'SIM', 'sms' => 'SMS',
        'gps' => 'GPS', 'nft' => 'NFT', 'pdf' => 'PDF', 'dna' => 'DNA', 'id' => 'ID', 'cctv' => 'CCTV',
        '5g' => '5G', '4g' => '4G', 'hd' => 'HD', 'vip' => 'VIP', 'faq' => 'FAQ', 'ecg' => 'ECG',
        'bbq' => 'BBQ', 'kpi' => 'KPI', 'sso' => 'SSO', 'swot' => 'SWOT', 'mvp' => 'MVP', 'iot' => 'IoT',
        'ocr' => 'OCR', 'ssl' => 'SSL', 'pos' => 'POS', 'defi' => 'DeFi', 'd20' => 'D20', 'dj' => 'DJ',
        'json' => 'JSON', 'ram' => 'RAM', 'pbuh' => 'PBUH', 'bp' => 'BP', 'rss' => 'RSS', 'gif' => 'GIF',
        'ufo' => 'UFO', 'rpg' => 'RPG', 'suv' => 'SUV', 'xray' => 'X-Ray', 'wifi' => 'Wi-Fi', '1x' => '1x', 'eid' => 'Eid',
    ];

    /** Truncated or run-together words found in pack-coded file names. */
    private const CUT_WORDS = [
        'dumb' => 'dumbbell', 'screwd' => 'screwdriver', 'hardhat' => 'hard hat',
    ];

    private const SMALL = ['and', 'or', 'of', 'the', 'a', 'an', 'to', 'in', 'on', 'at', 'for', 'with', 'by', 'from'];

    private const TYPO = [
        'coffe' => 'coffee', 'mazimize' => 'maximize', 'loxk' => 'lock', 'antena' => 'antenna',
        'karyawan' => 'employees',
    ];

    /** Exact per-file names where the file name does not say what the asset shows. */
    private const OVERRIDES = [
        'Islamic Icons' => [
            '1' => 'Mosque', '2' => 'Lantern', '3' => 'Islamic Star', '4' => 'Quran', '5' => 'Tasbih',
            '6' => 'Mosque Dome', '7' => 'Golden Dome', '8' => 'Eid Calligraphy', '9' => 'Eid Mubarak Calendar',
            '10' => 'Gift Box', '11' => 'Quran Stand', '12' => 'Charity Hand', '13' => 'Date Palm',
            '14' => 'Soup Bowl', '15' => 'Mosque', '16' => 'Grand Mosque', '17' => 'Quran Book',
            '18' => 'Crescent Moon and Star', '19' => 'Prayer Mat', '20' => 'Muslim Man', '21' => 'Lantern',
            '22' => 'Arabic Coffee Pot', '23' => 'Tasbih', '24' => 'Quran Book', '25' => 'Crescent Moon',
        ],
        'Environment Icons' => ['first' => 'First Aid'],
        'UI Icons' => ['blind' => 'Hide', 'surat-keluar' => 'Send File', 'surat-masuk' => 'Receive File'],
        'Users Interface Icons' => ['end' => 'Skip to End'],
        'Flat Icons' => ['led' => 'Monitor'],
        'Modern UI Icons' => ['Pictogram Icon' => 'Color Swatches', 'Law Icon' => 'Gavel'],
        'Mobile User Interface Icons' => ['Back_5Seconds' => 'Back 5 Seconds', 'Next_5Second' => 'Next 5 Seconds'],
        '99 Names Of Allah' => [
            'AZDHAAHIR' => 'Az-Zaahir', 'DHULJALAALIWALIKRAAM' => 'Dhul-Jalaali Wal-Ikraam',
            'MAALIKULMULK' => 'Maalik-ul-Mulk',
        ],
    ];

    /** Folder name → shown pack name, where the cleaned folder name would be unclear or collide. */
    private const PACK_OVERRIDES = [
        'icons' => [
            '99 Names Of Allah' => '99 Names of Allah',
            '99 Names Of Prophet PBUH' => '99 Names of Prophet (PBUH)',
            'User_Interface' => 'Essential UI Icons',
            'Users Interface Icons' => 'Media Player Icons',
            'Artificial-Intelligence-Icons' => 'Artificial Intelligence Icons',
            'IT-and-Networking-Icons' => 'IT and Networking Icons',
            'Social-Media-Icons' => 'Social Media Icons',
            'Duo-Color-Fill-Icons' => 'Duo Color Fill Icons',
            'Education-and-eLearning' => 'Education and eLearning Icons',
            'Payment_Shopping' => 'Payment & Shopping Icons',
            'Ramadan_Eid' => 'Ramadan & Eid Icons',
        ],
        'illustrations' => [
            'Business_Teamwork' => 'Business & Teamwork Illustrations',
            'Finance-Analytics' => 'Finance & Analytics Illustrations',
            'Finance_Crypto' => 'Finance & Crypto Illustrations',
            'Health_Medical' => 'Health & Medical Illustrations',
            'Health_Wellness' => 'Health & Wellness Illustrations',
            'IT_Networking' => 'IT & Networking Illustrations',
            'Marketing-Startup' => 'Marketing & Startup Illustrations',
            'Shopping_Ecommerce' => 'Shopping & E-Commerce Illustrations',
            'Teamwork-Remote-Work' => 'Teamwork & Remote Work Illustrations',
        ],
        '3d' => [
            'Crypto_Finance' => '3D Crypto & Finance',
            'Ecommerce_Shopping' => '3D E-Commerce & Shopping',
            'Food_Drinks' => '3D Food & Drinks',
            'Gaming_VR' => '3D Gaming & VR',
            'Sports_Games' => '3D Sports & Games',
            'Travel_Tourism' => '3D Travel & Tourism',
        ],
    ];

    public static function titleCase(array $words): string
    {
        $words = array_values(array_filter($words, fn ($w) => $w !== ''));
        $out = [];
        foreach (array_values($words) as $i => $w) {
            $lw = strtolower($w);
            if ($lw === '') {
                continue;
            }
            if (isset(self::UPPER[$lw])) {
                $out[] = self::UPPER[$lw];
            } elseif ($i > 0 && $i < count($words) - 1 && in_array($lw, self::SMALL, true)) {
                $out[] = $lw;
            } else {
                $out[] = ucfirst($lw);
            }
        }
        $s = implode(' ', $out);
        $s = preg_replace('/\bE (Bike|Sign|Commerce|Mail|Book|Learning)\b/', 'E-$1', $s);
        $s = preg_replace('/\bTshirt\b/', 'T-Shirt', $s);
        $s = preg_replace('/\bT Shirt\b/', 'T-Shirt', $s);
        $s = preg_replace('/\bX Ray\b/', 'X-Ray', $s);
        $s = preg_replace('/\bTo Do\b/', 'To-Do', $s);
        $s = preg_replace('/\bWi Fi\b/', 'Wi-Fi', $s);
        $s = preg_replace('/\bSelf Driving\b/', 'Self-Driving', $s);

        return trim($s);
    }

    /**
     * Drops pack codes and version tags from a title: "Pack20 50 Cone V5"
     * becomes "Cone". Only titles that carry such a code are touched, so
     * names like "Ice Pack" or "Heart Icon 02" stay exactly as they are.
     */
    public static function cleanTitle(string $title): string
    {
        $orig = trim($title);
        if (! preg_match('/\b(pack|set)\s?\d+\b|\bv\d+(\.\d+)*$/i', $orig)) {
            return $orig;
        }
        $t = preg_split('/[\s_\-]+/', $orig, -1, PREG_SPLIT_NO_EMPTY);
        $t = array_values(array_filter($t, fn ($w) => ! preg_match('/^(pack|set)\d+$/i', $w)));
        while (count($t) > 1 && (ctype_digit($t[0]) || (preg_match('/^(pack|set)$/i', $t[0]) && isset($t[1]) && ctype_digit($t[1])))) {
            array_shift($t);
        }
        while (count($t) > 1 && preg_match('/^(v\d+(\.\d+)*|ver|version|final|copy)$/i', end($t))) {
            array_pop($t);
        }
        // Words cut short in source file names ("Dumb", "Screwd").
        $t = explode(' ', implode(' ', array_map(fn ($w) => self::CUT_WORDS[strtolower($w)] ?? $w, $t)));
        $out = self::titleCase($t);

        return $out !== '' ? $out : $orig;
    }

    public static function packTitle(string $type, string $folder): string
    {
        if (isset(self::PACK_OVERRIDES[$type][$folder])) {
            return self::PACK_OVERRIDES[$type][$folder];
        }
        $base = self::titleCase(preg_split('/[\s_\-]+/', trim($folder)));

        return match ($type) {
            'icons' => preg_match('/\bIcons$/', $base) ? $base : $base.' Icons',
            'illustrations' => $base.' Illustrations',
            default => '3D '.$base,
        };
    }

    public static function assetTitle(string $packFolder, string $fileName): string
    {
        $stem = preg_replace('/\.[^.]+$/', '', $fileName);
        $stem = trim(preg_replace('/\s*\(\d+\)$/', '', $stem));
        $stem = preg_replace('/[-_ ]\d{6,}$/', '', $stem);

        if (isset(self::OVERRIDES[$packFolder][$stem])) {
            return self::OVERRIDES[$packFolder][$stem];
        }
        if ($packFolder === '99 Names Of Allah') {
            return self::divineName($stem);
        }

        $stem = preg_replace('/(?<=[a-z])(?=[A-Z])/', '-', $stem);
        $norm = strtolower(preg_replace('/[\s_\-]+/', '-', $stem));
        $packSlug = strtolower(preg_replace('/[\s_\-]+/', '-', trim($packFolder)));

        if (str_starts_with($norm, $packSlug.'-') && strlen($norm) > strlen($packSlug) + 1) {
            $norm = substr($norm, strlen($packSlug) + 1);
        }
        $norm = preg_replace('/^[a-z]+-(?=\d+-)/', '', $norm);
        $norm = preg_replace('/^\d+-(?=\w)/', '', $norm);
        $norm = preg_replace('/-icon(-\d+)?$/', '', $norm);
        $norm = preg_replace('/(?<=[a-z])-\d$/', '', $norm);

        $words = array_map(fn ($w) => self::TYPO[$w] ?? $w, explode('-', $norm));

        return self::cleanTitle(self::titleCase($words) ?: ucfirst($stem));
    }

    private static function divineName(string $stem): string
    {
        $s = strtolower($stem);
        if (preg_match('/^a(sh|dh|th|[tdrzsn])(?=\1)/', $s, $m)) {
            return 'A'.$m[1].'-'.ucfirst(substr($s, 1 + strlen($m[1])));
        }
        if (str_starts_with($s, 'al') && strlen($s) > 4) {
            return 'Al-'.ucfirst(substr($s, 2));
        }

        return ucfirst($s);
    }

    /** Lower-case search tags for one asset. */
    public static function tags(string $type, string $title, string $packFolder): array
    {
        $stop = array_merge(self::SMALL, ['icons', 'icon', 'new', 'pbuh', 'names', '99']);
        $tags = [];
        foreach (preg_split('/[\s\-()]+/', strtolower($title)) as $w) {
            if (strlen($w) >= 2 && ! in_array($w, $stop, true) && ! ctype_digit($w)) {
                $tags[] = $w;
            }
        }
        if (strtolower($title) !== '' && str_contains($title, ' ')) {
            $tags[] = strtolower($title);
        }
        foreach (preg_split('/[\s_\-]+/', strtolower($packFolder)) as $w) {
            if (strlen($w) >= 3 && ! in_array($w, $stop, true) && ! ctype_digit($w)) {
                $tags[] = $w;
            }
        }
        $tags = array_merge($tags, match ($type) {
            'icons' => ['icon', 'svg', 'vector'],
            'illustrations' => ['illustration', 'vector', 'svg', 'eps'],
            default => ['3d', '3d icon', 'render', 'blender'],
        });

        return array_slice(array_values(array_unique($tags)), 0, 15);
    }
}
