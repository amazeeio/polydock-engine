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

    /**
     * Treated as success: nothing on Lagoon left for Polydock to delete.
     * Either Lagoon reported the project does not exist, or the instance is
     * adopted — the project pre-exists Polydock and is deliberately left
     * intact while the Polydock record is cleaned up.
     */
    case AlreadyGone = 'already_gone';

    /** Lagoon project still has environments — caller should retry later. */
    case StillHasEnvironments = 'still_has_environments';

    /** Lagoon API returned an error or the call threw. Caller decides to retry or fail. */
    case Failed = 'failed';

    /** Project name could not be resolved from the instance. Non-retryable. */
    case MissingProjectName = 'missing_project_name';
}
