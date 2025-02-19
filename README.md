# Polydock Engine

> **⚠️ Warning: Experimental Project**  
> Polydock Engine is currently in active development and has not yet reached a stable production release. This project should be considered experimental.
>
> If you are interested in using Polydock Engine in a production setting, please contact Bryan Gruneberg (bryan@workshoporange.co) from [Workshop Orange](https://www.workshoporange.co), one of the sponsoring organizations.


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
[amazee.io](https://www.amazee.io) kindly sponsors a production instance of Polydock Engine for development purposes. This respository is connected via webhook to the production instance and will automatically deploy changes when a push is made to the `main` branch, the `dev` branch, or the `staging` branch.

NB: The Polydock Enginge hosted for development purposes on amazee.io is only configured to deploy Polydock Applicaitons to a testing (non-production) Lagoon Kubernetes cluster. 


## Sponsoring Organizations

- [Workshop Orange](https://www.workshoporange.co) - Project Delivery Professionals
- [amazee.io](https://www.amazee.io) - Enterprise Hosting  
- [Freedomtech Hosting](https://www.freedomtech.hosting) - Decentralized Hosting

## Contributing
Contributions are welcome! Please feel free to submit an issue or a PR.

## License
This project is licensed under the MIT License - see the LICENSE file for details.
