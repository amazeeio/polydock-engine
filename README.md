# Polydock Engine

Polydock Engine is a Laravel-based application designed to simplify and manage multiple deployments of applications within a Lagoon cluster. While Lagoon empowers developers to use Kubernetes without deep technical knowledge, Polydock Engine focuses on enabling non-technical users to deploy and manage multiple instances of the same application through a user-friendly interface.

## Overview

The key features of Polydock Engine include:
- Multiple deployments (Poly) of the same application
- User-friendly admin interface for non-technical users
- Application catalog management
- Deployment status monitoring
- Integration with Lagoon clusters

## Technical Details

### Technology Stack
- Laravel 10.x
- FT-Lagoon-PHP Library (https://github.com/Freedomtech-Hosting/ft-lagoon-php/)
- Sail-on-Lagoon (https://github.com/uselagoon/sailonlagoon)
- PHP 8.2+
- MySQL/MariaDB
- Docker
- amazee.io / Lagoon

### Development Environment

This project uses Laravel Sail with Lagoon integration ("Sail on Lagoon") for local development and production deployment.

#### Prerequisites
- Docker
- Docker Compose
- (Optional) Lagoon CLI (for interacting with Lagoon)
- (Optional) FT-Lagoon-PHP-CLI Library (https://github.com/Freedomtech-Hosting/ft-lagoon-php-cli/)

#### Local Development Setup

1. Clone the repository:

```bash
git clone https://github.com/your-repo/polydock-engine.git
cd polydock-engine
```

2. Install dependencies:

```bash
composer install
```

3. Configure environment variables:

```bash
cp .env.example .env
``` 

4. Start the development server:

```bash
./vendor/bin/sail up -d
```

5. Run migrations:

```bash
./vendor/bin/sail artisan migrate
```

6. Access the application at `http://localhost:8000`

#### Production Deployment
TODO: Add production deployment instructions