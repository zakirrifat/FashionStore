<?php

namespace App\Http\Controllers;

use App\Services\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "Open" on a bell alert: clear it for the whole team, then go to the item. */
class AdminAlertController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $key = (string) $request->query('key');
        $url = AdminNotifier::urlFor($key);

        abort_unless($url !== null, 404);

        AdminNotifier::forget($key);

        return redirect($url);
    }
}
