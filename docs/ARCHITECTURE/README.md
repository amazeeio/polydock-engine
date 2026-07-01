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
  - Implements the `PolydockAppInterface` from the inlined Polydock core (`app/Polydock/Core/PolydockAppInterface.php`; see docs below)

### Instances and Groups
- **PolydockAppInstance**: A deployed instance of a store app
  - Tracks deployment status and URLs
  - Manages trial state and timings for the specific instance
  - Handles one-time login URLs
  - Contains instance-specific variables and data
  - Belongs to a user group
  - Implements the `PolydockAppInstanceInterface` from the inlined Polydock core (`app/Polydock/Core/PolydockAppInstanceInterface.php`; see docs below)

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

A `PolydockAppInstance` advances through its lifecycle as a state machine driven
by the `PolydockAppInstanceStatus` enum
(`app/Polydock/Core/Enums/PolydockAppInstanceStatus.php`). Each lifecycle
**stage** follows a fixed four-state pattern:

```
PENDING_<STAGE> → <STAGE>_RUNNING → <STAGE>_COMPLETED   (or <STAGE>_FAILED)
```

The queued lifecycle jobs live in `app/Jobs/ProcessPolydockAppInstanceJobs/`,
organised by stage (`Create/`, `Deploy/`, `Claim/`, `Upgrade/`, `Remove/`,
`Health/`, `Purge/`, ...). A stage job picks up an instance in a `PENDING_*`
status, flips it to `*_RUNNING`, runs the work, and on success sets `*_COMPLETED`
(or `*_FAILED` on error).

Transitions between stages are handled by
`app/Jobs/ProcessPolydockAppInstanceJobs/ProgressToNextStageJob.php`, which only
operates on `PolydockAppInstance::$completedStatuses`. It maps each `*_COMPLETED`
status to the next stage's `PENDING_*`. The stage order is:

```
PRE_CREATE → CREATE → POST_CREATE → PRE_DEPLOY → DEPLOY → POST_DEPLOY
```

After `POST_DEPLOY_COMPLETED`, the instance branches: if it has a remote
registration or a `user-email` value it moves to `PENDING_POLYDOCK_CLAIM`
(then, once claimed, `RUNNING_HEALTHY_CLAIMED`); otherwise it goes straight to
`RUNNING_HEALTHY_UNCLAIMED`. Removal runs
`PRE_REMOVE → REMOVE → POST_REMOVE`, ending in `REMOVED`. An upgrade path
(`PRE_UPGRADE → UPGRADE → POST_UPGRADE`) also exists in the transition map.

> **Not yet implemented**: the Upgrade jobs
> (`Upgrade/{PreUpgradeJob,UpgradeJob,PollUpgradeJob,PostUpgradeJob}.php`) and
> `Health/PollHealthJob.php` are currently `TODO: Implement` stubs — the status
> transitions are wired up but the jobs perform no real work yet.

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

As of commit `b6f2ff09d195`, the Polydock core, clients, and app
implementations are **inlined** into this repository under `app/Polydock/`.
They are no longer external Composer packages, so there is no cross-repo
tag-and-cascade release workflow for them — edit the code directly here.

- **`app/Polydock/Clients/Lagoon/`**: Lagoon client
  - Handles Lagoon GraphQL/SSH communication
  - Manages deployments and environments
  - Tracks deployment status
- **`app/Polydock/Clients/AmazeeAi/`**: amazee.ai backend client
  - Integration with amazee.ai services


### Important Laravel Framework Components
- **laravel/horizon**: Queue monitoring and management
  
### Development Tools
- **laravel/sail**: Docker development environment
- **laravel/pint**: PHP code style fixer - still needs to be configured
- **phpunit/phpunit**: Testing framework - is hardly used but should be used a lot

### Additional Services

## Event System

### Key Events

Laravel events live in `app/Events/`:

- **PolydockAppInstanceCreatedWithNewStatus** - fired when an app instance is created
- **PolydockAppInstanceStatusChanged** - fired when an app instance's lifecycle status changes
- **UserRemoteRegistrationCreated** - fired when a remote registration record is created
- **UserRemoteRegistrationStatusChanged** - fired when a remote registration's status changes

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

### Core Polydock Code (inlined)

As of commit `b6f2ff09d195` the following were inlined into this repository
under `app/Polydock/` and are no longer external Composer packages.

- **`app/Polydock/Core/`**: base interfaces and abstractions
  - Defines `PolydockAppInterface` - the contract all Polydock apps must implement
  - Defines `PolydockAppInstanceInterface` - the contract for app instances
  - Defines `PolydockEngineInterface` - the contract for deployment engines
  - Contains core enums like `PolydockAppInstanceStatus`
    (`app/Polydock/Core/Enums/`)
  - Provides base implementations and utilities (`PolydockAppBase`,
    `PolydockEngineBase`, and shared traits under `app/Polydock/Core/Traits/`)

- **`app/Polydock/Apps/Generic/`**: generic amazee.io deployment implementation
  - Concrete apps (`app/Polydock/Apps/{AnythingLlm,PrivateGpt,AmazeeClaw,DependencyTrack}/`)
    build on this generic base.
  - Provides two main implementation types:
    1. **PolydockApp**: Standard Lagoon deployment implementation
       - Uses standard Lagoon Git workflow
       - Handles direct Git repository deployments
       - Manages standard Lagoon project lifecycle
    2. **PolydockAiApp**: AI-enhanced deployment implementation
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
  - Implements `PolydockEngineInterface` from the inlined Polydock core
    (`app/Polydock/Core/PolydockEngineInterface.php`); the orchestrator lives at
    `app/PolydockEngine/Engine.php`

- **Engine Helpers** (`app/PolydockEngine/Helpers/`):
  1. **LagoonHelper**: Handles Lagoon-specific operations
     - Uses the inlined Lagoon client at `app/Polydock/Clients/Lagoon/`
     - Manages Lagoon API interactions
     - Handles project creation and management
     - Controls environment operations
     - Executes deployment tasks

  2. **AmazeeAiBackendHelper**: Manages amazee.ai backend interactions
     - Uses the inlined amazee.ai backend client at `app/Polydock/Clients/AmazeeAi/`
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
