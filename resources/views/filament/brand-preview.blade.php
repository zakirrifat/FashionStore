@php
    $dark = \App\Support\Brand::url('logo_dark');
    $light = \App\Support\Brand::url('logo_light');
    $icon = \App\Support\Brand::url('icon_180');
@endphp
<div class="text-sm">
    @if ($dark)
        <p style="margin-bottom:.5rem;opacity:.7">Current logo (saved):</p>
        <div style="display:flex;gap:.5rem;align-items:stretch">
            <div style="flex:1;background:#faf9f6;border:1px solid #e3e0d8;padding:.75rem;display:grid;place-items:center">
                <img src="{{ $dark }}" alt="Dark logo" style="max-height:48px;max-width:100%">
            </div>
            <div style="flex:1;background:#141412;padding:.75rem;display:grid;place-items:center">
                <img src="{{ $light }}" alt="White logo" style="max-height:48px;max-width:100%">
            </div>
            @if ($icon)
                <img src="{{ $icon }}" alt="Icon" style="width:72px;height:72px;border:1px solid #e3e0d8">
            @endif
        </div>
    @else
        <p style="opacity:.7">No logo yet — the store name is shown as text.</p>
    @endif
</div>
