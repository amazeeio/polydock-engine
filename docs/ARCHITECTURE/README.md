# Polydock Engine Architecture

## Core Objects

### Stores and Apps
- **PolydockStore**: Represents a collection of related applications, typically grouped by organization or region
  - Contains configuration for Lagoon deployment settings for all apps in the store
  - Manages webhook configurations
  - Controls marketplace visibility
  - Holds region and organization specific settings

- **PolydockStoreApp**: Represents a specific application within a store
  - Defines deployment scripts and configurations for a single app
  - Controls trial settings and durations
  - Manages email notification templates and triggers
  - Allows for custom Lagoon service and container definitions
  - Implements the `PolydockAppInterface` from the `polydock-app` package (see docs below and [polydock-app](https://github.com/amazeeio/polydock-app))

### Instances and Groups
- **PolydockAppInstance**: A deployed instance of a store app
  - Tracks deployment status and URLs
  - Manages trial state and timings for the specific instance
  - Handles one-time login URLs
  - Contains instance-specific variables and data
  - Belongs to a user group
  - Implements the `PolydockAppInstanceInterface` from the `polydock-app` package (see docs below and [polydock-app](https://github.com/amazeeio/polydock-app))

- **UserGroup**: Collection of users who have access to app instances
  - Contains owners and members
  - Controls access permissions
  - Manages group ownership

### Supporting Objects
- **PolydockVariable**: Key-value pairs for storing configuration
  - Polymorphic relationship to stores, apps, and instances
  - Supports sensitive data handling
  - Used for deployment variables

- **PolydockStoreWebhook**: Configures external notifications
  - Handles retry logic
  - Tracks delivery status
  - Manages webhook security

## Job Processing Architecture

// TODO: This needs a lot of work.

### Trial Management Jobs
- ProcessMidtrialEmailJob
- ProcessOneDayLeftEmailJob
- ProcessTrialCompleteEmailJob
- ProcessTrialCompleteStageRemovalJob

### Queue Management
- Laravel Horizon for monitoring and scaling
- Dedicated queues for different job types
- Automatic retry handling

## Key Dependencies

### Lagoon & amazee.io Integration
- **amazeeio/ft-lagoon-php**: Lagoon API client library
  - Handles Lagoon API communication
  - Manages deployments and environments
  - Tracks deployment status
- **amazeeio/ft-amazeeai-backend-client-php**: Integration with amazee.ai services


### Important Laravel Framework Components
- **laravel/horizon**: Queue monitoring and management
  
### Development Tools
- **laravel/sail**: Docker development environment
- **laravel/pint**: PHP code style fixer - still needs to be configured
- **phpunit/phpunit**: Testing framework - is hardly used but should be used a lot

### Additional Services

## Event System

### Key Events
// TODO: This needs a lot of work.

### Webhook System
- Event-driven notifications
- Automatic retries
- Status tracking
- Error handling

## Deployment Flow

1. Store App Configuration
   - Define deployment scripts
   - Configure trial settings
   - Set up email templates

2. Instance Creation
   - Generate unique identifiers
   - Configure deployment variables
   - Initialize trial settings

3. Deployment Process
   - Pre-deployment checks
   - Lagoon integration
   - Status monitoring
   - Post-deployment configuration

4. Trial Management
   - Automated notifications
   - Status tracking
   - Expiration handling
   - Cleanup processes

### Core Polydock Packages
- **amazeeio/polydock-app**: Core package that defines the base interfaces and abstractions
  - Defines `PolydockAppInterface` - the contract all Polydock apps must implement
  - Defines `PolydockAppInstanceInterface` - the contract for app instances
  - Defines `PolydockEngineInterface` - the contract for deployment engines
  - Contains core enums like `PolydockAppInstanceStatus`
  - Provides base implementations and utilities

- **amazeeio/polydock-app-amazeeio-generic**: Implementation package for generic amazee.io deployments
  - Provides two main implementation types:
    1. **PolydockApp**: Standard Lagoon deployment implementation
       - Uses standard Lagoon Git workflow
       - Handles direct Git repository deployments
       - Manages standard Lagoon project lifecycle
    2. **PolydockAppAi**: AI-enhanced deployment implementation
       - Integrates with amazee.ai backend services
       - Supports AI-driven deployments and configurations
       - Uses amazee.ai templates and optimizations
  - Both implementations:
    - Share common Lagoon integration code
    - Follow the same core interfaces
    - Support the full deployment lifecycle
    - Can be selected based on store app configuration

### Engine and Helpers
- **PolydockEngine**: Core service provider that manages deployment implementations
  - Acts as a factory/service provider for deployment helpers
  - Injects dependencies into the appropriate helpers
  - Manages lifecycle of deployment operations
  - Implements `PolydockEngineInterface` from polydock-app package

- **Engine Helpers**:
  1. **LagoonHelper**: Handles Lagoon-specific operations
     - Depends on `freedomtech/ft-lagoon-php`
     - Manages Lagoon API interactions
     - Handles project creation and management
     - Controls environment operations
     - Executes deployment tasks

  2. **AmazeeAiBackendHelper**: Manages amazee.ai backend interactions
     - Depends on `freedomtech/polydock-amazeeai-backend-client-php`
     - Handles AI-driven deployments
     - Manages template operations
     - Controls backend service interactions

The Engine acts as a bridge between the application and these specialized helpers, ensuring:
- Proper dependency injection
- Consistent interface for deployment operations
- Separation of concerns between Lagoon and amazee.ai operations
- Clean abstraction of underlying API interactions

This architecture allows the PolydockEngine to:
1. Select appropriate implementation (standard or AI-enhanced)
2. Provide necessary dependencies to helpers
3. Coordinate operations between helpers
4. Maintain clean separation of concerns
