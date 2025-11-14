[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

> **⚠️ Important Credit**
>
> This package is a fork of the exceptional [Bouncer package](https://github.com/JosephSilber/bouncer) by [Joseph Silber](https://github.com/JosephSilber). Bouncer has provided the Laravel community with a powerful, elegant authorization solution for years, and this fork builds upon that incredible foundation. All core functionality, architecture, and innovation credit belongs to Joseph and the Bouncer project. This fork exists to explore alternative organizational patterns while maintaining full compatibility with Bouncer's proven approach.
>
> **Please consider [supporting the original Bouncer project](https://github.com/sponsors/JosephSilber)** - Joseph's work has been invaluable to the Laravel ecosystem.

# Warden

Warden is an elegant, framework-agnostic library for managing roles and permissions in your Laravel application. It provides a simple, expressive API for authorization that integrates seamlessly with Laravel's native authorization system.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/) and Laravel 12+**

## Installation

```bash
composer require cline/warden
```

## Quick Start

```php
use Cline\Warden\Facades\Warden;

// Grant abilities
Warden::allow($user)->to('edit', Post::class);

// Check permissions
if ($user->can('edit', $post)) {
    // User can edit this post
}

// Assign roles
Warden::assign('admin')->to($user);

// Check roles
if ($user->isAn('admin')) {
    // User is an admin
}
```

## Documentation

- **[Getting Started](cookbook/getting-started.md)** - Installation, configuration, and basic concepts
- **[Roles and Abilities](cookbook/roles-and-abilities.md)** - Creating and managing roles and permissions
- **[Model Restrictions](cookbook/model-restrictions.md)** - Scoping abilities to specific models
- **[Ownership](cookbook/ownership.md)** - Handling ownership-based permissions
- **[Removing Permissions](cookbook/removing-permissions.md)** - Disallowing and syncing abilities
- **[Forbidding](cookbook/forbidding.md)** - Explicit denials and blanket restrictions
- **[Checking Permissions](cookbook/checking-permissions.md)** - Various ways to check authorization
- **[Querying](cookbook/querying.md)** - Finding users by roles and abilities
- **[Authorization](cookbook/authorization.md)** - Integration with Laravel's authorization system
- **[Multi-Tenancy](cookbook/multi-tenancy.md)** - Scoping permissions per tenant
- **[Configuration](cookbook/configuration.md)** - Advanced configuration options
- **[Console Commands](cookbook/console-commands.md)** - Artisan commands for maintenance

## Key Features

- **Expressive API** - Fluent, readable syntax for managing permissions
- **Laravel Integration** - Works seamlessly with Laravel's Gate and authorization
- **Role-Based Access** - Traditional role management with abilities
- **Model-Level Permissions** - Scope abilities to specific models or instances
- **Ownership Support** - Built-in support for ownership-based permissions
- **Multi-Tenancy Ready** - Scope permissions per tenant out of the box
- **Caching** - Intelligent query caching for optimal performance
- **Zero Configuration** - Sensible defaults with full customization

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- **[Joseph Silber](https://github.com/JosephSilber)** - Creator of the original [Bouncer package](https://github.com/JosephSilber/bouncer) that serves as the foundation for this project
- [Brian Faust][link-maintainer] - Fork maintainer
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/warden/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/warden.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/warden.svg

[link-tests]: https://github.com/faustbrian/warden/actions
[link-packagist]: https://packagist.org/packages/cline/warden
[link-downloads]: https://packagist.org/packages/cline/warden
[link-security]: https://github.com/faustbrian/warden/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
