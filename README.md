# Mistral AI Provider for Moodle

A Moodle AI provider plugin that integrates [Mistral AI](https://mistral.ai) into Moodle's AI subsystem, enabling text generation, text summarisation, text explanation, and image generation.

## Features

- Text generation
- Text summarisation
- Text explanation
- Image generation

## Requirements

- Moodle 4.5 or later
- PHP 8.1 or later
- A valid [Mistral AI API key](https://console.mistral.ai)

## Configuration

### Provider settings

| Setting         | Description                                                                |
| --------------- | -------------------------------------------------------------------------- |
| API key         | Your Mistral API key from [console.mistral.ai](https://console.mistral.ai) |
| Organisation ID | Your Mistral organisation ID (optional)                                    |

### Action settings

Each action (generate text, summarise text, explain text, generate image) can be configured independently with:

| Setting            | Description                                                    |
| ------------------ | -------------------------------------------------------------- |
| Model              | The Mistral model to use, fetched from the API                 |
| Endpoint           | The Mistral API endpoint (pre-filled with the correct default) |
| System instruction | Custom system prompt (text actions only)                       |
| Extra parameters   | Additional model parameters in JSON format (see bellow)        |

### Supported extra parameters

```json
{
  "temperature": 0.7,
  "max_tokens": 500,
  "top_p": 0.95,
  "safe_prompt": false
}
```

Refer to the [Mistral API documentation](https://docs.mistral.ai/api/) for all supported parameters.

## Architecture

### Text actions

Text generation, summarisation, and explanation all use the Mistral chat completions API (`/v1/chat/completions`).

### Image generation

Image generation uses Mistral's agent API, which internally uses Black Forest Labs' Flux model. The flow is:

The image generation agent is created once and its ID is cached in Moodle's plugin config via `set_config()` to avoid hitting API rate limits. If the cached agent is deleted from Mistral Studio, it is automatically recreated on the next request.

### Image resolutions

Images are generated using Black Forest Labs' Flux aspect ratio:

| Moodle ratio | Size      | Aspect |
| ------------ | --------- | ------ |
| Square       | 1024×1024 | 1:1    |
| Landscape    | 1792×1024 | 7:4    |
| Portrait     | 1024×1792 | 4:7    |

## File structure

```
ai/provider/mistral/
├── classes/
│   ├── abstract_processor.php       # Base class for all processors
│   ├── hook_listener.php            # Provider and action settings form hooks
│   ├── helper.php                   # Utility methods (model fetching, HTTP helpers)
│   ├── provider.php                 # Provider class
│   ├── process_generate_text.php    # Text generation processor
│   ├── process_summarise_text.php   # Text summarisation processor
│   ├── process_explain_text.php     # Text explanation processor
│   ├── process_generate_image.php   # Image generation processor
│   ├── aimodel/                     # Model definitions
│   └── form/                        # Action settings forms
├── tests/                           # PHPUnit tests
├── lang/en/
│   └── aiprovider_mistral.php       # Language strings
├── db/
│   ├── install.php
│   └── hooks.php
├── lib.php                          # Constants (API endpoints, model names)
└── version.php
```

## Tests

Initialize the PHPUnit environment:

```bash
php public/admin/tool/phpunit/cli/init.php
```

Run specific tests:

```bash
vendor/bin/phpunit public/ai/provider/mistral/tests/...the_test_file.php
```

Run all plugin tests:

```bash
vendor/bin/phpunit --testsuite plugin
```


## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright 2026 [Fondation UNIT](https://www.unit.eu)
