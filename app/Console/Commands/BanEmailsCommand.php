<?php

namespace App\Console\Commands;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Models\PolydockAppInstance;
use App\Models\PolydockBannedPattern;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserRemoteRegistration;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BanEmailsCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polydock:ban
                            {patterns* : Individual emails, domains, or wildcard patterns to ban (e.g., spam@mail.com, spam.com, *@spam.ru)}
                            {--reason= : Reason for the ban to store in the database}
                            {--dry-run : Simulate the ban and show affected users, registrations, and app instances}
                            {--force : Bypass confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add email/domain patterns to the ban list, delete matching users/groups, and queue associated app instances for immediate purge';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $inputPatterns = $this->argument('patterns');
        $reason = $this->option('reason') ?: 'Banned via administrative cleanup command';
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        // 1. Normalize the patterns to secure wildcard domain bans
        $patterns = $this->normalizePatterns($inputPatterns);

        $this->info('Normalized patterns to ban:');
        foreach ($patterns as $pattern) {
            $this->line("  - {$pattern}");
        }
        $this->newLine();

        // 2. Identify Matching Users
        $users = $this->findMatchingUsers($patterns);
        $bannedUserIds = $users->pluck('id')->toArray();

        // 3. Identify User Groups associated with these users
        $groupsToCheck = UserGroup::whereHas('users', function ($query) use ($bannedUserIds) {
            $query->whereIn('user_id', $bannedUserIds);
        })->get();

        // 4. Identify Matching Registrations
        $registrations = $this->findMatchingRegistrations($patterns, $bannedUserIds);

        // 5. Identify Matching Polydock App Instances
        $instances = $this->findMatchingAppInstances($patterns, $groupsToCheck->pluck('id')->toArray());

        // 6. Output dry run information or prompt for confirmation
        if ($users->isEmpty() && $registrations->isEmpty() && $instances->isEmpty()) {
            $this->warn('No existing users, registrations, or app instances match these patterns.');
        } else {
            $this->comment('Summary of affected records:');
            $this->line("  - Users to delete: {$users->count()}");
            $this->line("  - Registrations to fail: {$registrations->count()}");
            $this->line("  - App instances to purge: {$instances->count()}");
            $this->newLine();

            if ($users->isNotEmpty()) {
                $this->info('Matching Users:');
                foreach ($users as $user) {
                    $this->line("    ID: {$user->id} | Email: {$user->email} | Name: {$user->name}");
                }
                $this->newLine();
            }

            if ($registrations->isNotEmpty()) {
                $this->info('Matching Registrations:');
                foreach ($registrations as $reg) {
                    $this->line("    ID: {$reg->id} | Email: {$reg->email} | Status: {$reg->status->value}");
                }
                $this->newLine();
            }

            if ($instances->isNotEmpty()) {
                $this->info('Matching App Instances:');
                foreach ($instances as $instance) {
                    $email = $instance->getUserEmail() ?: 'N/A';
                    $this->line("    ID: {$instance->id} | Name: {$instance->name} | Email: {$email} | Status: {$instance->status->getLabel()}");
                }
                $this->newLine();
            }
        }

        if ($isDryRun) {
            $this->info('DRY RUN: No modifications were made.');

            return 0;
        }

        if (! $force) {
            if (! $this->confirm('Are you sure you want to add these bans and proceed with the cleanup?', false)) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $deletedGroups = [];

        // 7. Perform DB modifications inside a transaction for atomic safety
        DB::transaction(function () use ($patterns, $reason, $users, $groupsToCheck, $registrations, $instances, &$deletedGroups) {
            // Save patterns in polydock_banned_patterns table
            foreach ($patterns as $pattern) {
                PolydockBannedPattern::firstOrCreate(
                    ['pattern' => $pattern],
                    ['reason' => $reason]
                );
            }

            // Mark matched registrations as failed
            foreach ($registrations as $registration) {
                if ($registration->status !== UserRemoteRegistrationStatusEnum::FAILED) {
                    $registration->status = UserRemoteRegistrationStatusEnum::FAILED;
                    $registration->save();
                }
            }

            // Initiate graceful force-purge for matched app instances
            foreach ($instances as $instance) {
                // Skip if already fully removed or in removal/purge stages
                if (in_array($instance->status, PolydockAppInstance::$stageRemoveStatuses, true) ||
                    in_array($instance->status, PolydockAppInstance::$stagePurgeStatuses, true)) {
                    continue;
                }

                $instance->force_purge_requested_at = now();
                $instance->setStatus(
                    PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
                    "Terminated and queued for immediate purge by ban system: {$reason}"
                );
                $instance->save();
            }

            // Delete users and clean up empty groups
            if ($users->isNotEmpty()) {
                foreach ($users as $user) {
                    $user->groups()->detach();
                    $user->delete();
                }

                // Check groups that became empty or have no other active members
                foreach ($groupsToCheck as $group) {
                    $remainingUsersCount = $group->users()->count();
                    if ($remainingUsersCount === 0) {
                        // Delete any app instances in this group that might not have matched the email search
                        $groupInstances = $group->appInstances()
                            ->whereNotIn('status', PolydockAppInstance::$stageRemoveStatuses)
                            ->whereNotIn('status', PolydockAppInstance::$stagePurgeStatuses)
                            ->get();

                        foreach ($groupInstances as $gInstance) {
                            $gInstance->force_purge_requested_at = now();
                            $gInstance->setStatus(
                                PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
                                "Group empty. Terminated and queued for immediate purge by ban system: {$reason}"
                            );
                            $gInstance->save();
                        }

                        $group->delete();
                        $deletedGroups[] = [
                            'name' => $group->name,
                            'id' => $group->id,
                        ];
                    }
                }
            }
        });

        foreach ($deletedGroups as $deletedGroup) {
            $this->line("Deleted empty UserGroup: {$deletedGroup['name']} (ID: {$deletedGroup['id']})");
        }

        $this->info('Ban and cleanup operation executed successfully.');

        return 0;
    }

    /**
     * Normalize individual email and domain inputs to precise SQL-safe patterns.
     */
    protected function normalizePatterns(array $inputs): array
    {
        $normalized = [];
        foreach ($inputs as $input) {
            $input = trim(strtolower($input));
            if (empty($input)) {
                continue;
            }

            // If it is already a wildcard email pattern (like *@domain.com or *@*.domain.com)
            if (str_starts_with($input, '*@')) {
                $normalized[] = $input;
                $domain = substr($input, 2);
                if (! str_contains($domain, '*.')) {
                    $normalized[] = "*@*.{$domain}";
                }

                continue;
            }

            // If it starts with @ (like @spam.com)
            if (str_starts_with($input, '@')) {
                $domain = ltrim($input, '@');
                $normalized[] = "*@{$domain}";
                $normalized[] = "*@*.{$domain}";

                continue;
            }

            // If it contains @ but it's an email (like spammer@gmail.com)
            if (str_contains($input, '@')) {
                $normalized[] = $input;

                continue;
            }

            // If it doesn't contain @, treat as a domain name (like spam.com)
            $normalized[] = "*@{$input}";
            $normalized[] = "*@*.{$input}";
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Escape PHP patterns for SQL LIKE query with ESCAPE '=' clause.
     */
    protected function escapeLikePattern(string $pattern): string
    {
        $escaped = str_replace('=', '==', $pattern);
        $escaped = str_replace('%', '=%', $escaped);
        $escaped = str_replace('_', '=_', $escaped);

        return str_replace('*', '%', $escaped);
    }

    /**
     * Find existing users matching any of the normalized patterns.
     */
    protected function findMatchingUsers(array $patterns): Collection
    {
        if (empty($patterns)) {
            return new Collection;
        }

        return User::where(function ($query) use ($patterns) {
            foreach ($patterns as $pattern) {
                $escapedPattern = $this->escapeLikePattern($pattern);
                $query->orWhereRaw("email LIKE ? ESCAPE '='", [$escapedPattern]);
            }
        })->get();
    }

    /**
     * Find registrations matching patterns or linked to matched user IDs.
     */
    protected function findMatchingRegistrations(array $patterns, array $userIds): Collection
    {
        if (empty($patterns) && empty($userIds)) {
            return new Collection;
        }

        return UserRemoteRegistration::where(function ($query) use ($patterns, $userIds) {
            if (! empty($userIds)) {
                $query->whereIn('user_id', $userIds);
            }
            foreach ($patterns as $pattern) {
                $escapedPattern = $this->escapeLikePattern($pattern);
                $query->orWhereRaw("email LIKE ? ESCAPE '='", [$escapedPattern]);
            }
        })->get();
    }

    /**
     * Find app instances matching patterns or associated user group IDs.
     */
    protected function findMatchingAppInstances(array $patterns, array $groupIds): Collection
    {
        if (empty($patterns) && empty($groupIds)) {
            return new Collection;
        }

        $connectionType = DB::connection()->getDriverName();

        return PolydockAppInstance::where(function ($query) use ($patterns, $groupIds, $connectionType) {
            if (! empty($groupIds)) {
                $query->whereIn('user_group_id', $groupIds);
            }

            foreach ($patterns as $pattern) {
                $escapedPattern = $this->escapeLikePattern($pattern);

                if ($connectionType === 'sqlite') {
                    // SQLite handles extraction nicely, and json_unquote doesn't exist.
                    // SQLite extraction also automatically removes quotes from scalar strings.
                    $query->orWhereRaw("json_extract(data, '$.\"user-email\"') LIKE ? ESCAPE '='", [$escapedPattern]);
                } else {
                    $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"user-email\"')) LIKE ? ESCAPE '='", [$escapedPattern]);
                }
            }
        })->get();
    }

    #[\Override]
    public function sensitiveInputs(): array
    {
        return ['patterns'];
    }
}
