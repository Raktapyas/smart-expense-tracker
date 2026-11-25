<x-layouts.app title="Import transactions">
    <div class="max-w-2xl space-y-6">
        <x-card>
            <h2 class="font-semibold">Upload a CSV file</h2>
            <p class="mt-1 text-sm text-muted">
                You will see a preview first. Nothing is saved until you confirm.
            </p>

            <form method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                @csrf

                <div>
                    <label for="file" class="mb-1.5 block text-sm font-medium text-muted">CSV file</label>
                    <input id="file" name="file" type="file" accept=".csv,text/csv,text/plain" required
                           class="block w-full text-sm text-muted file:mr-4 file:rounded-xl file:border-0 file:bg-surface-2 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-ink hover:file:opacity-90">
                    @error('file')
                        <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
                    @enderror
                </div>

                <x-select label="Account for rows without an account column" name="default_account_id" :options="$accountOptions" />
                <x-select label="Date format in the file" name="date_format" :options="$dateFormatOptions" selected="ymd" />

                <label class="flex items-center gap-2 text-sm text-muted">
                    <input type="checkbox" name="skip_duplicates" value="1" checked
                           class="rounded border-line bg-surface-2 text-accent focus:ring-accent">
                    Skip rows that already exist in my transactions
                </label>

                <x-button type="submit">Preview import</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="font-semibold">What the file should look like</h2>
            <p class="mt-2 text-sm text-muted">
                The first row must be a header. Required: <span class="text-ink">date</span> and
                <span class="text-ink">amount</span> (or separate <span class="text-ink">debit</span> and
                <span class="text-ink">credit</span> columns). Optional: <span class="text-ink">type</span>
                (income, expense or transfer), <span class="text-ink">merchant</span> (or description),
                <span class="text-ink">category</span>, <span class="text-ink">account</span>,
                <span class="text-ink">to account</span> (for transfers) and <span class="text-ink">note</span>.
            </p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-muted">
                <li>Negative amounts count as expenses and positive amounts as income, unless a type column says otherwise.</li>
                <li>Commas are treated as thousands separators, so 1,250.00 means one thousand two hundred fifty.</li>
                <li>Unknown categories are left uncategorised, and your smart rules then try to categorise them.</li>
                <li>At most 5,000 rows and 2 MB per file.</li>
            </ul>
            <div class="mt-5 flex flex-wrap gap-3">
                <x-button variant="secondary" :href="route('import.template')">Download a template</x-button>
                <x-button variant="secondary" :href="route('export.transactions', ['range' => 'all'])">Export my transactions</x-button>
            </div>
        </x-card>
    </div>
</x-layouts.app>
