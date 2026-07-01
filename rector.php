<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ]);

    // Register sets for PHP version upgrade
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_83,
    ]);

    // Enforces declare(strict_types=1); at the top of all files
    $rectorConfig->rule(SafeDeclareStrictTypesRector::class);

    $rectorConfig->skip([
        '**/vendor/**',
    ]);
};
