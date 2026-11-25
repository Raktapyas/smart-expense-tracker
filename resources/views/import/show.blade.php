@php
    $rows = collect($result->rows);
    $ok = $rows->where('status', 'ok');
    $duplicates = $rows->where('status', 'duplicate');
    $errors = $rows->where('status', 'error');
    $warned = $ok->filter(fn ($row) => $row['warning'])->count();
@endphp
<x-layouts.app title="Review import">
    @if ($result->fatal !== null)
        <x-card class="max-w-2xl">
            <p class="text-lg font-semibold text-expense">We could not read this file</p>
            <p class="mt-2 text-sm text-muted">{{ $result->fatal }}</p>
            <form method="POST" action="{{ route('import.destroy', $token) }}" class="mt-5">
                @csrf
                @method('DELETE')
                <x-button type="submit">Try another file</x-button>
            </form>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <x-card>
                <p class="text-sm text-muted">Ready to import</p>
                <p class="mt-2 text-2xl font-semibold text-income">{{ $ok->count() }}</p>
            </x-card>
            <x-card>
                <p class="text-sm text-muted">Duplicates (skipped)</p>
                <p class="mt-2 text-2xl font-semibold">{{ $duplicates->count() }}</p>
            </x-card>
            <x-card>
                <p class="text-sm text-muted">Errors (skipped)</p>
                <p class="mt-2 text-2xl font-semibold text-expense">{{ $errors->count() }}</p>
            </x-card>
        </div>

        @if ($errors->isNotEmpty())
            <x-card class="mt-6">
                <h2 class="mb-3 font-semibold">Problems</h2>
                <ul class="divide-y divide-line text-sm">
                    @foreach ($errors->take(20) as $row)
                        <li class="flex gap-3 py-2">
                            <span class="w-16 shrink-0 text-muted">Line {{ $row['line'] }}</span>
                            <span>{{ $row['message'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($errors->count() > 20)
                    <p class="mt-2 text-xs text-muted">…and {{ $errors->count() - 20 }} more.</p>
                @endif
            </x-card>
        @endif

        @if ($ok->isNotEmpty())
            <x-card class="mt-6">
                <h2 class="mb-1 font-semibold">Preview</h2>
                <p class="mb-3 text-xs text-muted">
                    The first {{ min(10, $ok->count()) }} of {{ $ok->count() }} rows that will be imported.
                    @if ($warned > 0)
                        {{ $warned }} {{ \Illuminate\Support\Str::plural('row', $warned) }} had an unknown category;
                        your smart rules will try to categorise them.
                    @endif
                </p>
                <ul class="divide-y divide-line text-sm">
                    @foreach ($ok->take(10) as $row)
                        @php $d = $row['data']; @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                            <span class="min-w-0">
                                <span class="text-muted">{{ \Illuminate\Support\Carbon::parse($d['occurred_on'])->format('d M Y') }}</span>
                                · {{ ucfirst($d['type']) }}
                                @if ($d['merchant'])
                                    · {{ $d['merchant'] }}
                                @endif
                            </span>
                            <span class="num font-medium">{{ \App\Support\Money::format($d['amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <div class="mt-6 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('import.store', $token) }}">
                    @csrf
                    <input type="hidden" name="default_account_id" value="{{ $options['default_account_id'] }}">
                    <input type="hidden" name="date_format" value="{{ $options['date_format'] }}">
                    <input type="hidden" name="skip_duplicates" value="{{ $options['skip_duplicates'] ? 1 : 0 }}">
                    <x-button type="submit">Import {{ $ok->count() }} {{ \Illuminate\Support\Str::plural('transaction', $ok->count()) }}</x-button>
                </form>
                <form method="POST" action="{{ route('import.destroy', $token) }}">
                    @csrf
                    @method('DELETE')
                    <x-button variant="secondary" type="submit">Cancel</x-button>
                </form>
            </div>
        @else
            <x-card class="mt-6">
                <p class="font-semibold">Nothing to import</p>
                <p class="mt-1 text-sm text-muted">Every row was a duplicate or had an error. Check the date format and columns, then try again.</p>
                <form method="POST" action="{{ route('import.destroy', $token) }}" class="mt-5">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit">Try another file</x-button>
                </form>
            </x-card>
        @endif
    @endif
</x-layouts.app>
