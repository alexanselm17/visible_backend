<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Models\PrivacyRequest;
use App\Models\SiteSetting;
use App\Services\LegalPageService;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LegalPageController extends Controller
{
    public function show(string $slug, LegalPageService $service)
    {
        $document = LegalDocument::where('slug', $slug)->firstOrFail();
        $settings = SiteSetting::current();
        $published = $document->published_at !== null;

        return response()->view('legal.document', [
            'settings' => $settings, 'document' => $document, 'preview' => false,
            'title' => $published ? $document->published_title : $document->title,
            // Sanitize at read time too, including content imported directly into the database.
            'content' => $published ? $service->sanitize($document->published_content) : null,
        ], $published ? 200 : 503)->header('Cache-Control', 'no-cache');
    }

    public function preview(LegalDocument $document, LegalPageService $service)
    {
        abort_unless(AdminAccess::allows('settings'), 403);
        $settings = SiteSetting::current();

        return response()->view('legal.document', [
            'settings' => $settings, 'document' => $document, 'preview' => true,
            'title' => $document->title, 'content' => $service->render($document->draft_content, $settings),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function requestForm(Request $request)
    {
        return response()->view('legal.request', [
            'settings' => SiteSetting::current(),
            'deletion' => $request->routeIs('legal.delete-account'),
            'title' => $request->routeIs('legal.delete-account') ? 'Delete your account & data' : 'Support & privacy requests',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['deletion', 'access', 'correction', 'review', 'privacy'])],
            'contact' => 'required|string|max:254',
            'message' => 'nullable|string|max:2000',
            'confirmation' => 'accepted',
            'website' => 'prohibited',
        ]);
        $privacyRequest = PrivacyRequest::create(collect($data)->only(['type', 'contact', 'message'])->all());

        // Never look up or modify an account from an unverified public request.
        return redirect()->route($data['type'] === 'deletion' ? 'legal.delete-account' : 'legal.privacy-request')
            ->with('request_reference', $privacyRequest->reference);
    }
}
