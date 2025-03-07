# Polydock Engine

> **⚠️ Warning: Experimental Project**  
> Polydock Engine is currently in active development and has not yet reached a stable production release. This project should be considered experimental.
>
> If you are interested in using Polydock Engine in a production setting, please contact Bryan Gruneberg (bryan@workshoporange.co) from [Workshop Orange](https://www.workshoporange.co), one of the sponsoring organizations.

Polydock Engine is a Laravel-based application management and deployment platform that enables organizations to offer self-service trials and deployments of their applications on top of Lagoon [Lagoon](https://www.lagoon.sh) platforms (such as [amazee.io](https://www.amazee.io)). While Lagoon 
empowers developers to use Kubernetes without deep technical knowledge, Polydock Engine focuses on enabling non-technical users to deploy and manage 
multiple instances of the same application through a user-friendly interface.

## Key Features (some implemented, some planned, some considered)

### Application Management
- Multi-store support for organizing applications by region and organization
- Public and private stores with marketplace visibility control
- Trial availability flags for controlling which apps can be offered as trials

### User Management
- Group-based access control
- Role-based permissions (Owner, Member)
- Email-based user registration
- Automatic group creation for new trial users

### Trial Registration System
- Remote registration API
- Support for multiple registration types:
  - Standard trial requests
  - Unlisted region requests
  - Test/simulation modes for developing and testing registration workflows
- Registration validation
- Privacy policy and AUP acceptance tracking

### Webhook System
- Store-level webhook configuration
- Automatic retry with exponential backoff
- Detailed webhook call tracking
- Event-based notifications for:
  - Registration status changes
  - Trial provisioning updates
- Robust error handling and logging

### Deployment Integration
- Integration with Lagoon deployment system
- Region-specific deployment configuration
- Project prefix management for multi-tenant deployments
- Git-based deployment source control

## Planned and considered features

### Enhanced Trial Management
- Trial duration controls
- Usage quotas and limits
- Automatic trial expiration
- Trial extension workflows

### Advanced Monitoring
- Trial usage analytics
- Store performance metrics
- Registration conversion tracking
- Webhook reliability monitoring

### Extended Integration
- Additional deployment platform support
- SSO integration options
- Custom domain management
- Automated DNS provisioning

### Administrative Features
- Advanced user management
- Billing integration
- Usage reporting
- Audit logging

## Technical Details

- Built with Laravel 10
- Queue-based processing for reliability
- Event-driven architecture
- Comprehensive logging and monitoring
- Database transaction support
- Robust error handling
- Test coverage with PHPUnit

## Getting Started

[Documentation coming soon]

## Overview

The key features of Polydock Engine include:
- Multiple deployments (Poly) of the same application
- User-friendly admin interface for non-technical users
- Application catalog management
- Deployment status monitoring
- Integration with Lagoon clusters

## Technical Details

### Technology Stack
- Laravel 11.x
- Laravel Horizon for queue management
- FT-Lagoon-PHP Library (https://github.com/Freedomtech-Hosting/ft-lagoon-php/)
- Sail-on-Lagoon (https://github.com/uselagoon/sailonlagoon)
- PHP 8.2+
- MySQL/MariaDB
- Redis for queue processing (Horizon depedency)
- Docker
- Lagoon [Lagoon](https://www.lagoon.sh)
- amazee.io [amazee.io](https://www.amazee.io)

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
git clone https://github.com/freedomtech-hosting/polydock-engine.git
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
(reconfigure the .env file as needed)

4. Start the development server:

```bash
./vendor/bin/sail up
```
(use `-d` to run in detached mode)

5. Run migrations:

```bash
./vendor/bin/sail artisan migrate
```

5.1 (optional) Run the seed command to create a default user:

```bash
./vendor/bin/sail artisan db:seed
```
(See DatabaseSeeder.php for details on the default users, groups, apps that are created)

6. Access the application at `http://localhost`

### Production Deployment
[amazee.io](https://www.amazee.io) kindly sponsors a production instance of Polydock Engine for development purposes. This respository is connected via webhook to the production instance and will automatically deploy changes when a push is made to the `main` branch, the `dev` branch, or the `staging` branch.

NB: The Polydock Enginge hosted for development purposes on amazee.io is only configured to deploy Polydock Applicaitons to a testing (non-production) Lagoon Kubernetes cluster. 

For acceess to the production development environment, please contact Bryan Gruneberg (bryan@workshoporange.co) from [Workshop Orange](https://www.workshoporange.co).

#### Queue Processing with Laravel Horizon
- Dedicated queue workers for different job types
  - Default queue for general processing
  - Webhook queue for external notifications
- Automatic scaling based on load
- Configurable retry policies with exponential backoff
- Real-time queue monitoring dashboard (see /horizon)
- Job failure tracking and debugging
- Environment-specific worker configurations defaults:
  - Production: Up to 10 concurrent processes
  - Local: 3 processes for development
  - Separate supervisor for webhook processing

## Sponsoring Organizations

- [Workshop Orange](https://www.workshoporange.co) - Project Delivery Professionals
- [amazee.io](https://www.amazee.io) - Enterprise Hosting  
- [Freedomtech Hosting](https://www.freedomtech.hosting) - Privact Focused Hosting of Freedomtech Applications

## Contributing
We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details on how to get started.

Please ensure that you:
- Follow our coding standards
- Write tests for new features
- Update documentation as needed
- Follow the pull request process outlined in the contributing guide

## License
This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
