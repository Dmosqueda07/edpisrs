<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Division;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewEmployeeRegistered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:225',
                'unique:users,email',
                function (string $attribute, string $value, \Closure $fail): void {
                    $allowedDomains = config('registration.allowed_domains', []);
                    $domain = strtolower((string) strrchr($value, '@'));
                    $domain = ltrim($domain, '@');

                    if ($allowedDomains !== [] && ! in_array($domain, $allowedDomains, true)) {
                        $fail('Registration is restricted to approved email domains.');
                    }
                },
            ],
            'division' => ['required', Rule::in(array_column(Division::cases(), 'value'))],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'division' => $validated['division'],
            'password' => $validated['password'],
            'role' => Role::Staff,
            'status' => UserStatus::Pending,
        ]);

        $administrators = User::query()
            ->whereIn('role', [Role::Administrator->value, Role::SuperAdministrator->value])
            ->where('status', UserStatus::Active->value)
            ->where('is_active', true)
            ->get();

        Notification::send($administrators, new NewEmployeeRegistered($user));

        return redirect()->route('login')->with('status', 'Your registration was submitted and is awaiting administrator approval.');
    }
}
