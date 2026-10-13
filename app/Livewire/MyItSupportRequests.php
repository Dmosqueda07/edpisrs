<?php

namespace App\Livewire;

use App\Enums\ItSupportRequestStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MyItSupportRequests extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user, 403);

        $this->validate([
            'status' => [Rule::when($this->status !== '', Rule::in(array_column(ItSupportRequestStatus::cases(), 'value')))],
        ]);

        return view('livewire.my-it-support-requests', [
            'requests' => $user->itSupportRequests()
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }
}
