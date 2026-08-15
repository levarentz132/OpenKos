<?php

namespace App\Services\Payments;

use InvalidArgumentException;
use OpenKOS\Core\Data\Payment\CheckoutInstruction;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;

final class CheckoutInstructionsMapper
{
    /**
     * @return array{url: ?string, entries: array<int, array{key: string, value: string, label: ?string}>}
     */
    public function toArray(CheckoutInstructions $instructions): array
    {
        return [
            'url' => $instructions->url,
            'entries' => array_values(array_map(
                static fn (CheckoutInstruction $entry): array => [
                    'key' => $entry->key,
                    'value' => $entry->value,
                    'label' => $entry->label,
                ],
                $instructions->entries,
            )),
        ];
    }

    public function fromArray(?array $value): CheckoutInstructions
    {
        if ($value === null) {
            return new CheckoutInstructions;
        }

        $url = $value['url'] ?? null;

        if ($url !== null && ! is_string($url)) {
            throw new InvalidArgumentException('Checkout instruction URL must be a string or null.');
        }

        if (array_key_exists('entries', $value) && ! is_array($value['entries'])) {
            throw new InvalidArgumentException('Checkout instruction entries must be an array.');
        }

        $rawEntries = $value['entries'] ?? [];
        $entries = [];
        foreach ($rawEntries as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null) || ! is_string($entry['value'] ?? null)) {
                throw new InvalidArgumentException('Checkout instruction entries are invalid.');
            }

            $label = $entry['label'] ?? null;
            if ($label !== null && ! is_string($label)) {
                throw new InvalidArgumentException('Checkout instruction labels must be strings or null.');
            }

            $entries[] = new CheckoutInstruction($entry['key'], $entry['value'], $label);
        }

        return new CheckoutInstructions($url, $entries);
    }
}
