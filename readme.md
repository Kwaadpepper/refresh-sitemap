# Refresh Sitemap

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

Creates sitemap.xml and refresh using a config file

## Installation

Via Composer

``` bash
$ composer require kwaadpepper/refresh-sitemap
```

## Usage

1 - `php artisan vendor:publish --provider="Kwaadpepper\RefreshSitemap\RefreshSitemapServiceProvider"`
2 - Change configuration in config/refresh-sitemap.php
3 - You can test your configuration using `php artisan sitemap:refresh --dry-run`

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Security

If you discover any security related issues, please email github@jeremydev.ovh instead of using the issue tracker.

## Credits

- [Jérémy Munsch][link-author]
- [All Contributors][link-contributors]

## License

MIT. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/kwaadpepper/refresh-sitemap?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/kwaadpepper/refresh-sitemap?style=flat-square

[link-packagist]: https://packagist.org/packages/kwaadpepper/refresh-sitemap
[link-downloads]: https://packagist.org/packages/kwaadpepper/refresh-sitemap
[link-author]: https://github.com/kwaadpepper
