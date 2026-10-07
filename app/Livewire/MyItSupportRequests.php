<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class MyItSupportRequests extends Component
{
    use WithPagination;

    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user, 403);

        return view('livewire.my-it-support-requests', [
            'requests' => $user->itSupportRequests()->latest()->paginate(10),
        ]);
    }
}
