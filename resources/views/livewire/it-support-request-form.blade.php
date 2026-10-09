<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <form method="POST" action="{{ route('it-support-requests.store') }}" enctype="multipart/form-data" class="space-y-8">
        @csrf

        <section>
            <h2 class="text-lg font-medium text-gray-900">Requester information</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="requester-name" class="block text-sm font-medium text-gray-700">Full name</label>
                    <input id="requester-name" type="text" value="{{ auth()->user()->full_name }}" readonly
                        class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm">
                </div>
                <div>
                    <label for="requester-division" class="block text-sm font-medium text-gray-700">Division / Section</label>
                    <input id="requester-division" type="text" value="{{ auth()->user()->division->value }}" readonly
                        class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm">
                </div>
            </div>
        </section>

        <section>
            <fieldset>
                <legend class="text-lg font-medium text-gray-900">Type of IT Support Needed <span aria-hidden="true">*</span></legend>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($requestTypes as $type)
                        <label class="flex items-start gap-3 rounded-md border border-gray-200 p-3 hover:border-blue-400">
                            <input type="radio" name="request_type_id" value="{{ $type->id }}" wire:model.live="requestTypeId"
                                @checked((string) old('request_type_id') === (string) $type->id)
                                required
                                class="mt-1 border-gray-300 text-blue-700 focus:ring-blue-600">
                            <span class="text-sm text-gray-800">{{ $type->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('request_type_id')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </fieldset>

            @foreach ($fields as $field)
                <div class="mt-6">
                    <label for="answer-{{ $field['key'] }}" class="block text-sm font-medium text-gray-700">
                        {{ $field['label'] }} <span aria-hidden="true">*</span>
                    </label>
                    <textarea id="answer-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" rows="5" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">{{ old('answers.'.$field['key'], old('details')) }}</textarea>
                    @error('answers.'.$field['key'])
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </section>

        <section>
            <label for="attachments" class="block text-sm font-medium text-gray-700">Supporting documents (optional)</label>
            <p class="mt-1 text-sm text-gray-500">Attach up to 5 images or PDFs, 5 MB each.</p>
            <input id="attachments" name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                class="mt-2 block w-full text-sm text-gray-700 file:me-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-medium file:text-blue-800 hover:file:bg-blue-100">
            @error('attachments')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('attachments.*')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </section>

        <section>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="certification" value="1" @checked(old('certification'))
                    required class="mt-1 rounded border-gray-300 text-blue-700 focus:ring-blue-600">
                <span class="text-sm text-gray-700">
                    I hereby certify that the information provided in this form is true, complete, and accurate to the best of my knowledge.
                </span>
            </label>
            @error('certification')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                Submit request
            </button>
        </div>
    </form>
</div>
