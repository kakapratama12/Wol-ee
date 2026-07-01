<?php

namespace App\Exceptions;

use Exception;

class CartUnavailableException extends Exception
{
    /**
     * @param  list<array{product_id: int, name: string, requested_qty: int, max_fulfillable_qty: int}>  $unavailableProducts
     */
    public function __construct(
        string $message,
        public readonly array $unavailableProducts = [],
    ) {
        parent::__construct($message);
    }
}
