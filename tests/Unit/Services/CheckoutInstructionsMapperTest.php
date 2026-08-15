<?php

use App\Services\Payments\CheckoutInstructionsMapper;

it('rejects malformed persisted checkout instruction entries', function () {
    expect(fn () => (new CheckoutInstructionsMapper)->fromArray([
        'url' => null,
        'entries' => null,
    ]))->toThrow(InvalidArgumentException::class);
});
