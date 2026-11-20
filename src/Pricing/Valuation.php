<?php

namespace Glubschi\Seat\Contracts\Pricing;

/**
 * What a contract asks for its one capital ship, without the rest of its contents.
 *
 * Kinds (column "valuation"):
 * - single:   exactly one capital ship is handed over; net_price = price minus the Jita buy value of everything
 *             else. Counted in the price figures.
 * - multiple: more than one capital ship; the price cannot be split. Shown, not counted.
 * - requests: the contract also asks for items in return (is_included = false); its price says nothing about
 *             the ship. Shown, not counted.
 * - none:     no capital ship in it.
 *
 * An auction counts with its buyout; without a buyout it has no net price.
 */
final class Valuation
{
    public const SINGLE = 'single';

    public const MULTIPLE = 'multiple';

    public const REQUESTS = 'requests';

    public const NONE = 'none';

    /**
     * @param  iterable<array{type_id: int, quantity: int, is_included: bool, is_blueprint_copy?: bool|null}>  $items
     * @param  array<int, true>  $capitals  type IDs of capital ships, as a set
     * @param  array<int, float>  $prices  Jita buy by type ID
     * @return array{valuation: string, main_type_id: int|null, extras_value: float|null, extras_unpriced: int|null, net_price: float|null}
     */
    public static function value(iterable $items, array $capitals, array $prices, string $type, ?float $price, ?float $buyout): array
    {
        $ships = [];
        $extras = [];
        $requests = false;
        foreach ($items as $item) {
            if (! $item['is_included']) {
                $requests = true;
                continue;
            }
            if (isset($capitals[$item['type_id']])) {
                for ($i = 0; $i < max(1, (int) $item['quantity']); $i++) {
                    $ships[] = (int) $item['type_id'];
                }
            } else {
                $extras[] = $item;
            }
        }

        $result = ['valuation' => self::NONE, 'main_type_id' => null, 'extras_value' => null, 'extras_unpriced' => null, 'net_price' => null];
        if ($ships === []) {
            return $result;
        }
        $result['main_type_id'] = $ships[0];
        if (count($ships) > 1) {
            return ['valuation' => self::MULTIPLE] + $result;
        }
        if ($requests) {
            return ['valuation' => self::REQUESTS] + $result;
        }

        $value = 0.0;
        $unpriced = 0;
        foreach ($extras as $item) {
            // A blueprint copy has no market; the buy orders for its type are for the original.
            $unitPrice = empty($item['is_blueprint_copy']) ? ($prices[$item['type_id']] ?? null) : null;
            if ($unitPrice === null) {
                $unpriced++;
                continue;
            }
            $value += $unitPrice * (int) $item['quantity'];
        }

        $asked = $type === 'auction' ? ($buyout ?: null) : $price;

        return [
            'valuation' => self::SINGLE,
            'main_type_id' => $ships[0],
            'extras_value' => $value,
            'extras_unpriced' => $unpriced,
            'net_price' => $asked === null ? null : $asked - $value,
        ];
    }
}
