<?php

use App\Support\Money;

it('formats NPR with lakh grouping', function () {
    expect(Money::format('125000'))->toBe('Rs 1,25,000.00');
    expect(Money::format(999.5))->toBe('Rs 999.50');
    expect(Money::format('12345678.9'))->toBe('Rs 1,23,45,678.90');
    expect(Money::format(-1500))->toBe('-Rs 1,500.00');
    expect(Money::format(null))->toBe('Rs 0.00');
});
