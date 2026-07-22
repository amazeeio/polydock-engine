<?php

namespace App\Services;

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

            $classes[$class] = Str::headline($reflection->getShortName());
        }

        ksort($classes);

        return $classes;
    }
}
