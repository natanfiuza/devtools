<?php

namespace App\Http\Controllers;

use App\Actions\Contact\CreateContactMessage;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina publica de contato (`/{locale}/contact`).
 */
class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Contact', [
            'name' => $user instanceof User ? $user->name : null,
            'email' => $user instanceof User ? $user->email : null,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(
        StoreContactMessageRequest $request,
        CreateContactMessage $createContactMessage,
    ): RedirectResponse {
        $request->hitRateLimiter();

        $localeSlug = Locales::slug(app()->getLocale());

        // Honeypot: descarta silenciosamente para bots sem disparar erro nem salvar no banco
        if ($request->isSpam()) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('contact.sent_toast'),
            ]);

            return redirect()->route('contact', ['locale' => $localeSlug])
                ->with('status', 'sent');
        }

        $subjectKey = $request->string('subject')->value();
        $subjectLabel = (string) trans('contact.subjects.'.$subjectKey);

        $user = $request->user();

        $createContactMessage([
            'name' => $request->string('name')->value(),
            'email' => $request->string('email')->value(),
            'subject' => $subjectLabel !== '' ? $subjectLabel : $subjectKey,
            'message' => $request->string('message')->value(),
            'user_id' => $user instanceof User ? $user->id : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('contact.sent_toast'),
        ]);

        return redirect()->route('contact', ['locale' => $localeSlug])
            ->with('status', 'sent');
    }
}
