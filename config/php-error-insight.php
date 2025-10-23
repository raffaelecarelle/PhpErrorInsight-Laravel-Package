<?php

return [
    // Master enable switch (also controlled by env PHP_ERROR_INSIGHT_ENABLED)
    'enabled' => env('PHP_ERROR_INSIGHT_ENABLED', true),

    // Backend: none|local|api|openai|anthropic|google|gemini
    'backend' => env('PHP_ERROR_INSIGHT_BACKEND', 'none'),

    // Model name (e.g. llama3:instruct, gpt-4o-mini, claude-3-5-sonnet-20240620, gemini-1.5-flash)
    'model' => env('PHP_ERROR_INSIGHT_MODEL', ''),

    // API key and URL for API backends
    'apiKey' => env('PHP_ERROR_INSIGHT_API_KEY', ''),
    'apiUrl' => env('PHP_ERROR_INSIGHT_API_URL', ''),

    // Language for AI prompt (it, en, ...)
    'language' => env('PHP_ERROR_INSIGHT_LANG', 'it'),

    // Output format: auto|html|text|json (web rendering forces html)
    'output' => env('PHP_ERROR_INSIGHT_OUTPUT', 'auto'),

    // Verbose details rendering
    'verbose' => env('PHP_ERROR_INSIGHT_VERBOSE', false),

    // Optional custom HTML template path
    'template' => env('PHP_ERROR_INSIGHT_TEMPLATE', ''),

    // Project root and host root for editor links
    'projectRoot' => env('PHP_ERROR_INSIGHT_ROOT', base_path()),
    'hostProjectRoot' => env('PHP_ERROR_INSIGHT_HOST_ROOT', ''),

    // Editor URL template (e.g. vscode://file/%file:%line)
    'editorUrl' => env('PHP_ERROR_INSIGHT_EDITOR', ''),
];
