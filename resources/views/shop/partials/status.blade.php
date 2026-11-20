@if (session('status'))
    @php app()->instance('inline-status-shown', true); @endphp
    <div class="border border-ink px-5 py-4 mb-8 flex items-start gap-3" role="status" data-reveal="fade">
        <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 8.5l3 3 7-7"/></svg>
        <span>{{ session('status') }}</span>
    </div>
@endif
