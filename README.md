# PHP Error Insight — Laravel Package

Integration package for Laravel that renders PHP Error Insight’s rich HTML error page (with AI-powered details and suggestions) during development.

- Core library: raffaelecarelle/php-error-insight
- Supports Laravel 9, 10, 11, 12 (requires PHP >= 8.2)

Screenshots:

![screenview1.png](resources/img/readme/screenview1.png)
![screenview2.png](resources/img/readme/screenview2.png)
![terminal.png](resources/img/readme/terminal.png)

## Installation

```bash
composer require raffaelecarelle/php-error-insight-laravel --dev
```

Laravel 5.5+ package auto-discovery will register the service provider automatically. No manual changes are needed.

## Configuration

Publish the config file to customize settings:

```bash
php artisan vendor:publish --tag=php-error-insight-config
```

Environment variables (same as the core library) are supported and merged with the package config:

- PHP_ERROR_INSIGHT_ENABLED=true|false (default: true)
- PHP_ERROR_INSIGHT_BACKEND=none|local|api|openai|anthropic|google|gemini
- PHP_ERROR_INSIGHT_MODEL=llama3:instruct|gpt-4o-mini|claude-3-5-sonnet-20240620|gemini-1.5-flash
- PHP_ERROR_INSIGHT_API_KEY=... (when using API backends)
- PHP_ERROR_INSIGHT_API_URL=... (optional override)
- PHP_ERROR_INSIGHT_LANG=it|en|...
- PHP_ERROR_INSIGHT_OUTPUT=auto|html|text|json (web rendering forces html)
- PHP_ERROR_INSIGHT_VERBOSE=true|false
- PHP_ERROR_INSIGHT_TEMPLATE=/absolute/path/to/custom/template.php
- PHP_ERROR_INSIGHT_ROOT=/absolute/path/to/project (used for relative paths and editor links)
- PHP_ERROR_INSIGHT_HOST_ROOT=/host/path (containers mapping)
- PHP_ERROR_INSIGHT_EDITOR="vscode://file/%file:%line" or "phpstorm://open?file=%file&line=%line"

## How it works in Laravel

- The package decorates the framework ExceptionHandler to render the core library’s HTML page on errors and exceptions.
- Activation conditions:
  - App is in debug mode (config/app.php: 'debug' => true)
  - Request expects HTML (not JSON)
  - The feature is enabled (via env/config)
- For all other cases (JSON/API requests, production, disabled), Laravel’s default handler is used.

No changes are made to your app/Exceptions/Handler.php.

## Editor links and stack features

The HTML page supports clickable file links and clipboard helpers. Configure:

```env
PHP_ERROR_INSIGHT_ROOT=/var/www/app
PHP_ERROR_INSIGHT_EDITOR="phpstorm://open?file=%file&line=%line"
# For containers mapping to host project path
PHP_ERROR_INSIGHT_HOST_ROOT=/Users/you/project
```

## Version compatibility

- Package requires PHP >= 8.1 (aligned with the core library)
- Tested path is compatible with Laravel 8, 9, 10, 11.
- Older Laravel versions that require PHP < 8.1 are not supported by this package due to PHP constraints.

## Disable in production

Set in .env:

```env
APP_DEBUG=false
PHP_ERROR_INSIGHT_ENABLED=false
```

## Troubleshooting

- If you don’t see the custom HTML page:
  - Ensure APP_DEBUG=true and request is in a browser (Accept: text/html)
  - Check that the package service provider is auto-discovered (config/app.php > providers) or add it manually.
  - Ensure environment variables are loaded and not cached, or run `php artisan config:clear`.
  - Verify your AI backend configuration if you expect AI-generated details.
