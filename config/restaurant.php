<?php

return [
    'tax_rate' => (float) env('RESTAURANT_TAX_RATE', 0),
    'display_name' => env('RESTAURANT_DISPLAY_NAME', env('APP_NAME', 'Restaurant')),
];
