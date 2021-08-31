# Exception Handler

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-travis]][link-travis]
[![StyleCI][ico-styleci]][link-styleci]

Creates sitemap.xml and refresh using a config file

## Installation

Via Composer

``` bash
$ composer require kwaadpepper/refresh-sitemap
```

## Usage

1 - php artisan handler:install
2 - Change configuration in config/refresh-sitemap.php
3 - put at least your team email in the above file in `contactsList` array

You can uninstall it by using php artisan handler:remove or juste revert changes in Exception/Handler.php
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
