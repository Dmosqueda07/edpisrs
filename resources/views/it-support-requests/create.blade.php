<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('IT Support Request') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h1 class="text-xl font-semibold text-gray-900">Request EDP assistance</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Use this form for IT-related concerns, technical assistance, and system or application issues.
                    A confirmation will be sent to your registered email. For urgent concerns, coordinate directly
                    with the EDP IT Support Team.
                </p>
            </div>

            @if (session('status'))
                <div role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif
            <a href="{{ route('it-support-requests.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">
                View my requests
            </a>

            <livewire:it-support-request-form />
        </div>
    </div>
</x-app-layout>
