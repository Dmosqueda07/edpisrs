<?php

namespace App\Livewire;

use App\Models\RequestType;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ItSupportRequestForm extends Component
{
    public string $requestTypeId = '';

    public function mount(): void
    {
        $this->requestTypeId = old('request_type_id', '');
    }

    public function render(): View
    {
        $requestTypes = RequestType::active()->get();
        $selectedType = $requestTypes->firstWhere('id', (int) $this->requestTypeId);
        $selectedDefinition = collect(config('request_forms.request_types'))
            ->firstWhere('key', $selectedType?->key);

        return view('livewire.it-support-request-form', [
            'requestTypes' => $requestTypes,
            'fields' => $selectedDefinition['fields'] ?? [],
        ]);
    }
}
