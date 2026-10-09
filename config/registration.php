<?php

$domains = array_filter(array_map(
    static fn (string $domain): string => strtolower(trim($domain)),
    explode(',', (string) env('REGISTRATION_ALLOWED_EMAIL_DOMAINS', ''))
));

return [
    'allowed_domains' => array_values($domains),
];
