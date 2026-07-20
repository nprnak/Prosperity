<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for the project's bilingual backed enums: option lists the
 * Vue selects render straight from Inertia props, and lenient label lookups
 * for values that were stored before the column was constrained.
 *
 * Expects the using enum to implement App\Enums\Contracts\HasLabels.
 */
trait HasOptions
{
    /**
     * Raw backing values, for Rule::in() and plain array checks.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The shape the <select> markup consumes.
     *
     * @return array<int, array{value: string, label: string, label_np: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->labelEn(),
            'label_np' => $case->labelNp(),
        ], self::cases());
    }

    /**
     * Label lookups that tolerate null and unrecognised values rather than
     * throwing, so a stale row can still be displayed.
     */
    public static function tryLabelEn(?string $value): ?string
    {
        return self::tryFrom((string) $value)?->labelEn();
    }

    public static function tryLabelNp(?string $value): ?string
    {
        return self::tryFrom((string) $value)?->labelNp();
    }
}
