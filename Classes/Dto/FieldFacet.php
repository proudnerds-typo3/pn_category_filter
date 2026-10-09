<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Dto;

/**
 * A filter on the value of a TCA select field, configured in settings.fieldFacets.<key>
 */
final readonly class FieldFacet
{
    /**
     * @param array<int|string,string> $values value => label, in TCA order; PHP turns numeric values into int keys
     * @param list<string> $selected Selected values, all of them known in $values
     */
    public function __construct(
        public string $key,
        public string $table,
        public string $field,
        public string $label,
        public bool $multiple,
        public array $values,
        public array $selected,
    ) {}

    public function isActive(): bool
    {
        return $this->selected !== [];
    }
}
