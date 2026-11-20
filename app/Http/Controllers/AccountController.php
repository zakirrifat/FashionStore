<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function overview(Request $request): View
    {
        $user = $request->user();

        return view('shop.account.overview', [
            'user' => $user,
            'defaultAddress' => $user->addresses()->first(),
            'wishlistCount' => $user->wishlistItems()->count(),
            'latestOrder' => \App\Models\Order::where('user_id', $user->id)->latest()->first(),
        ]);
    }

    /* ───────── Orders ───────── */

    public function orders(Request $request): View
    {
        return view('shop.account.orders', [
            'orders' => \App\Models\Order::with('items')->where('user_id', $request->user()->id)->latest()->paginate(10),
        ]);
    }

    public function order(Request $request, string $number): \Illuminate\Http\RedirectResponse
    {
        $order = \App\Models\Order::where('user_id', $request->user()->id)->where('number', $number)->firstOrFail();

        return redirect($order->confirmationUrl());
    }

    /* ───────── Profile ───────── */

    public function editProfile(Request $request): View
    {
        return view('shop.account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'current_password' => [Rule::requiredIf(Str::lower($request->input('email')) !== $user->email), 'nullable', 'current_password'],
        ], [
            'current_password.required' => 'Enter your current password to change your email.',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
        ]);

        return back()->with('status', 'Your details have been saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        // Sign out other devices that used the old password.
        Auth::logoutOtherDevices($data['password']);

        return back()->with('status', 'Your password has been changed.');
    }

    /* ───────── Addresses ───────── */

    public function addresses(Request $request): View
    {
        return view('shop.account.addresses', ['addresses' => $request->user()->addresses]);
    }

    public function createAddress(): View
    {
        return view('shop.account.address-form', ['address' => new Address(['country' => 'ZA'])]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->addresses()->count() >= 20, 422, 'You can save up to 20 addresses.');

        $address = $user->addresses()->make($this->validatedAddress($request));
        $address->is_default = $request->boolean('is_default') || ! $user->addresses()->exists();
        $this->saveAddress($address);

        return redirect()->route('account.addresses')->with('status', 'Address added.');
    }

    public function editAddress(Request $request, Address $address): View
    {
        $this->authorizeAddress($request, $address);

        return view('shop.account.address-form', ['address' => $address]);
    }

    public function updateAddress(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $address->fill($this->validatedAddress($request));
        $address->is_default = $request->boolean('is_default') || $address->is_default;
        $this->saveAddress($address);

        return redirect()->route('account.addresses')->with('status', 'Address updated.');
    }

    public function destroyAddress(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $wasDefault = $address->is_default;
        $address->delete();
        if ($wasDefault) {
            $request->user()->addresses()->first()?->update(['is_default' => true]);
        }

        return back()->with('status', 'Address removed.');
    }

    private function validatedAddress(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'company' => ['nullable', 'string', 'max:100'],
            'line1' => ['required', 'string', 'max:150'],
            'line2' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:80'],
            'province' => [Rule::requiredIf($request->input('country') === 'ZA'), 'nullable', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', Rule::in(array_keys(Address::countries()))],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        if ($data['country'] === 'ZA') {
            $request->validate(['province' => [Rule::in(Address::ZA_PROVINCES)]]);
        }

        return $data;
    }

    /** Save, keeping exactly one default address. */
    private function saveAddress(Address $address): void
    {
        DB::transaction(function () use ($address) {
            if ($address->is_default) {
                Address::where('user_id', $address->user_id)->whereKeyNot($address->id ?? 0)->update(['is_default' => false]);
            }
            $address->save();
        });
    }

    private function authorizeAddress(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
