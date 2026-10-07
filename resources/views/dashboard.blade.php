<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p>{{ __("You're logged in!") }}</p>
                    <a href="{{ route('it-support-requests.create') }}" class="mt-4 inline-flex rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        Submit an IT support request
                    </a>
                    <a href="{{ route('it-support-requests.index') }}" class="mt-4 ms-3 inline-flex rounded-md border border-blue-700 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                        Track my requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
