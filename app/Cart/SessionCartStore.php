<?php

namespace App\Cart;

use Illuminate\Contracts\Session\Session;

/**
 * Guest cart persistence (ADR-041): session only.
 */
class SessionCartStore
{
    public const SESSION_KEY = 'cart.lines';

    public function __construct(
        private readonly Session $session,
    ) {}

    /**
     * @return array<int, int> variant_id => quantity
     */
    public function lines(): array
    {
        $raw = $this->session->get(self::SESSION_KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        $lines = [];

        foreach ($raw as $variantId => $quantity) {
            if (! is_numeric($variantId) || ! is_numeric($quantity)) {
                continue;
            }

            $id = (int) $variantId;
            $qty = (int) $quantity;

            if ($id < 1 || $qty < 1) {
                continue;
            }

            $lines[$id] = $qty;
        }

        return $lines;
    }

    /**
     * @param  array<int, int>  $lines  variant_id => quantity
     */
    public function put(array $lines): void
    {
        $normalized = [];

        foreach ($lines as $variantId => $quantity) {
            $id = (int) $variantId;
            $qty = (int) $quantity;

            if ($id < 1 || $qty < 1) {
                continue;
            }

            $normalized[$id] = $qty;
        }

        ksort($normalized);

        $this->session->put(self::SESSION_KEY, $normalized);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
