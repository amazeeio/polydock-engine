<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\EnumHelper;
use Filament\Support\Contracts\HasLabel;
use PHPUnit\Framework\TestCase;

class EnumHelperTest extends TestCase
{
    public function test_get_enum_options_with_has_label_interface(): void
    {
        $options = EnumHelper::getEnumOptions(DummyHasLabelEnum::class);

        $expected = [
            'first' => 'First Label',
            'second' => 'Second Label',
        ];

        $this->assertEquals($expected, $options);
    }

    public function test_get_enum_options_without_has_label_interface_falls_back_to_title_case(): void
    {
        $options = EnumHelper::getEnumOptions(DummyNoLabelEnum::class);

        $expected = [
            'some-value' => 'Some-Value',
            'another_value' => 'Another_Value',
        ];

        $this->assertEquals($expected, $options);
    }
}

enum DummyHasLabelEnum: string implements HasLabel
{
    case FIRST = 'first';
    case SECOND = 'second';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIRST => 'First Label',
            self::SECOND => 'Second Label',
        };
    }
}

enum DummyNoLabelEnum: string
{
    case SOME_VALUE = 'some-value';
    case ANOTHER_VALUE = 'another_value';
}
