<?php

namespace App\Services;

use App\Forms\FormLabel;
use App\Forms\HostedFormInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Discovers concrete HostedFormInterface implementations in app/Forms so the
 * admin panel can offer them when creating/editing a hosted form record.
 */
class HostedFormClassDiscovery
{
    /**
     * @return array<class-string, string> FQCN => human-readable label
     */
    public function getAvailableFormClasses(): array
    {
        // ponytail: static memo — the class list can't change mid-process
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $classes = [];

        foreach (File::files(app_path('Forms')) as $file) {
            $class = 'App\\Forms\\'.$file->getFilenameWithoutExtension();

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(HostedFormInterface::class)) {
                continue;
            }

            $label = $reflection->getAttributes(FormLabel::class)[0] ?? null;

            $classes[$class] = $label?->newInstance()->label
                ?? Str::headline($reflection->getShortName());
        }

        ksort($classes);

        return $cached = $classes;
    }
}
