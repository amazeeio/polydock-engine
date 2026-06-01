<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Outcome of a single purge attempt for a Lagoon project.
 */
enum PurgeResult: string
{
    /** Lagoon project was successfully deleted in this attempt. */
    case Purged = 'purged';

    /** Lagoon reported the project does not exist (treated as success). */
    case AlreadyGone = 'already_gone';

    /** Lagoon project still has environments — caller should retry later. */
    case StillHasEnvironments = 'still_has_environments';

    /** Lagoon API returned an error or the call threw. Caller decides to retry or fail. */
    case Failed = 'failed';

    /** Project name could not be resolved from the instance. Non-retryable. */
    case MissingProjectName = 'missing_project_name';
}
