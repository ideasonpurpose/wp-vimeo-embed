# wp-vimeo-embed

#### Version: 0.3.0

[![Build Status](https://travis-ci.org/ideasonpurpose/wp-vimeo-embed.svg?branch=master)](https://travis-ci.org/ideasonpurpose/wp-vimeo-embed)
[![Coverage Status](https://coveralls.io/repos/github/ideasonpurpose/wp-vimeo-embed/badge.svg?branch=master)](https://coveralls.io/github/ideasonpurpose/wp-vimeo-embed?branch=master)

A collection of Vimeo embed tools and shortcodes for use in WordPress themes.

## Shortcodes

The following shortcodes will be supported:

- `[vimeo 1234567]` -- straight embed, stretches to 100% width
- `[vimeo 1234567 loop]` -- video tag embed, loops
- `[vimeo 1234567 autoplay]` -- video tag embed, autoplay
- `[vimeo 1234567 loop autoplay]` -- video tag embed, loops and autoplays
- `[vimeo 1234567 autoPLAY LoOp]` -- same as above (order and case don't matter)
- `[vimeo 1234567 lightbox]` -- standard embed pops open an Ekko lightbox wrapper

## Code

There are three methods for injecting Vimeo embed codes in to a page:

```php
$vimeo->wrap($vimeoID);       // Wrap Vimeo's embed code in a responsive wrapper
$vimeo->embed($vimeoID, [$arg1]);      // Inject an HTML5 video tag
$vimeo->lightbox($vimeoID);   // Inject a image link which opens a video lightbox
```

The library should be initialized with a Vimeo API token:

```php
use ideasonpurpose/VimeoEmbed;

$vimeo = new VimeoEmbed('a1234a2bbdcc9d43250b2aefcff944ce');

$vimeo->embed('1234567');
```

Or, using the output directly from ACF Pro's oEmbed field:

    $vimeo->wrap(get_field('video'));

## Usage

This library is not on Packagist so Composer needs to be told where to find it. Add this to the `composer.json` `repositories` key:

```json
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/ideasonpurpose/wp-vimeo-embed"
    }
  ]
```

Then tell Composer to load the package:

```
$ composer require ideasonpurpose/wp-vimeo-embed
```

Initialize the code with a a Vimeo API token:

```php
use ideasonpurpose/VimeoEmbed;

$vimeo = new VimeoEmbed('1234567890abcdef0000000000000000');
```

## Development

Install dependencies with `composer install` and `yarn` (or `npm install`).

Tests run with [PHPUnit][]. All tests have been wrapped in package.json script commands. To run test suite use `yarn test` or `npm test`. To watch all files and automatically re-run the test suite on changes use `yarn watch` or `npm run watch`.

[phpunit]: https://phpunit.de/
