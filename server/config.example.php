<?php
/* ============================================================
   EON server — configuration.
   Copy to config.local.php (git-ignored) and fill in. Everything
   is optional: with nothing set the server runs in demo mode.
   ============================================================ */
return [
    // ---- who may talk to EON -------------------------------------------
    // Shared secret the app sends as  Authorization: Bearer <token>  (or ?token=).
    // Leave empty to allow same-origin requests without a token (demo / intranet).
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
        'max_tokens' => 4096,
        'allow_sql_tool' => true,      // let the model run SELECT-only queries (read-only DB user!)
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

    // ---- misc ------------------------------------------------------------
    'cache_ttl' => 300,                     // seconds the dataset is cached
    'demo_dataset' => __DIR__ . '/storage/data/demo-dataset.json',
    'log' => __DIR__ . '/storage/logs/eon.log',
];
