<?php

namespace Database\Seeders;

use App\Models\RequestType;
use Illuminate\Database\Seeder;

class RequestTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('request_forms.request_types') as $requestType) {
            RequestType::updateOrCreate(
                ['key' => $requestType['key']],
                $requestType,
            );
        }
    }
}
