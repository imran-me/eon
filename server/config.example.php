<?php
/* ============================================================
   EON server — configuration.
   Copy to config.local.php (git-ignored) and fill in. Everything
   is optional: with nothing set the server runs in demo mode.
   ============================================================ */
return [
    // ---- who may talk to EON -------------------------------------------
    // Shared secret the app sends as  Authorization: Bearer <token>  (or ?token=).
    // Leave empty ONLY for the pure demo (no db, no api_key). Once a database or a model key is
    // configured the API refuses to serve without a token. Generate one with:
    //     php -r "echo bin2hex(random_bytes(32));"
    // and paste the same value in the browser: localStorage.setItem('eon_token', '...') (or ?token=… once; the app remembers it).
    'token' => '',
    // Allowed browser origins for CORS (the ERP host, GitHub Pages, localhost…). '*' = any.
    'origins' => ['*'],

    // ---- the ERP database (READ-ONLY user recommended) -----------------
    'db' => [
        'enabled'  => false,
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'epal_erp',
        'user'     => 'eon_readonly',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    // EON's own tables (conversations, memory, decisions). Same DB by default;
    // set to a separate database if you prefer to keep EON's writes apart.
    'eon_db' => null,   // null = reuse 'db' with the eon_ prefix

    // ---- the language model --------------------------------------------
    'anthropic' => [
        'api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
        'model'   => 'claude-opus-5',
        'effort'  => 'high',           // low | medium | high | xhigh | max
        'max_tokens' => 8192,
        'allow_sql_tool' => false,     // let the model run guarded SELECT-only queries (needs a READ-ONLY DB user; credential tables/columns are blocked)
    ],

    // ---- EON's own voice -------------------------------------------------
    // The machine usually cannot pronounce বাংলা: Windows ships English voices
    // only and Chrome's Google voices include Hindi but not Bengali. So EON
    // renders the speech itself and the browser just plays the audio.
    'tts' => [
        'enabled' => true,
        // Best quality, and genuinely Bangladeshi. Free tier is 500k characters
        // a month: portal.azure.com → Speech service → Keys and Endpoint.
        'azure' => [
            'key'      => getenv('AZURE_SPEECH_KEY') ?: '',
            'region'   => getenv('AZURE_SPEECH_REGION') ?: '',   // e.g. southeastasia
            'voice_bn' => 'bn-BD-NabanitaNeural',                // or bn-BD-PradeepNeural
            'voice_en' => 'en-US-AriaNeural',
        ],
        // Google Cloud Text-to-Speech, if you would rather use that (bn-IN).
        'google' => ['key' => getenv('GOOGLE_TTS_KEY') ?: ''],
        // The keyless endpoint Google Translate's speaker button uses. It works
        // with no setup at all, which is why it is the default — but it is
        // undocumented and rate-limited, so treat it as the stopgap and add an
        // Azure key before anything that matters. Set false to refuse it.
        'allow_translate' => true,
    ],

    // ---- the boss --------------------------------------------------------
    'boss' => ['name' => 'Md Imran Hossain', 'title' => 'Managing Director', 'email' => 'imran@epal.com.bd', 'company_id' => null],
    'company' => ['name' => 'Epal Group', 'timezone' => 'Asia/Dhaka', 'currency' => 'BDT'],

    // ---- notifications ---------------------------------------------------
    'notify' => [
        'email_to'   => '',                 // where the morning brief and alerts go
        'email_from' => 'eon@epal.com.bd',
        'webhook'    => '',                 // optional POST hook (WhatsApp/SMS gateway, Slack…) — receives {title, text}
    ],

    // ---- Python analytics service (server/py/eon.py) ----------------------
    'python' => ['bin' => null],   // null = auto (python3 on Linux/Hostinger, python on Windows); or e.g. '/usr/bin/python3'

    // ---- misc ------------------------------------------------------------
    'cache_ttl' => 300,                     // seconds the dataset is cached
    'demo_dataset' => __DIR__ . '/storage/data/demo-dataset.json',
    'log' => __DIR__ . '/storage/logs/eon.log',
];
