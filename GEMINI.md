# Gemini Context: Polydock Engine

Polydock Engine is a Laravel-based application management and deployment platform that enables organizations to offer self-service trials and deployments of their applications on top of [Lagoon](https://www.lagoon.sh) platforms.

## Core Concepts

- **PolydockStore**: Represents an organization or region's marketplace. Can be public or private.
- **PolydockStoreApp**: An application available within a store. Defines trial duration, Lagoon project prefix, and implementation class.
- **PolydockAppInstance**: A specific instance of an app deployed for a user/group. Tracks status (Pending, Provisioning, Ready, etc.).
- **UserRemoteRegistration**: Handles incoming trial requests from external systems.
- **PolydockServiceProvider**: Interface for interacting with different deployment backends (e.g., `AmazeeAiBackend`, `FTLagoon`).

## Tech Stack

- **Framework**: Laravel 11.x
- **UI**: Filament (Admin Panel)
- **Queues**: Laravel Horizon (Redis-backed)
- **Deployment**: Integrated with Lagoon via `ft-lagoon-php` library.
- **Local Dev**: Laravel Sail / Sail on Lagoon (Docker).

## Project Structure

- `app/PolydockEngine/`: Core engine logic and helpers.
- `app/PolydockServiceProviders/`: Implementations for different Lagoon backends.
- `app/Filament/`: Admin interface definitions.
- `app/Jobs/`: Async tasks for trial provisioning, status polling, and webhooks.
- `app/Models/`: Core domain models.
- `docs/`: Comprehensive documentation.

## Common Commands

- **Local Dev**: `./vendor/bin/sail up`
- **Tests**: `./vendor/bin/sail artisan test` or `php artisan test`
- **Linting**: `./vendor/bin/sail pint` or `vendor/bin/pint`
- **Migrations**: `php artisan migrate`
- **Seeders**: `php artisan db:seed`

## Guidelines for Gemini

- **Conventions**: Follow standard Laravel conventions and PSR-12/Pint styling.
- **Service Providers**: When adding support for new platforms, look at `app/PolydockServiceProviders/`.
- **Testing**: Prioritize Feature tests for API endpoints and trial lifecycle. Use `Tests\TestCase`.
- **Models**: Use Enums for statuses (found in `app/Enums/`).
- **Webhooks**: Use the built-in webhook system for external notifications.
