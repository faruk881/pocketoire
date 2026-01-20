<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Term;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function storeTerms(Request $request) {
        try{
            $terms = $request->validate([
                'content' => 'required|string',
            ]);
            $terms['updated_by'] = auth()->id();

            Term::updateOrCreate($terms);

            return apiSuccess('Terms of Service updated successfully.');
        } catch (\Throwable $e) {
            return apiError($e->getMessage()); 
        }
    }

    public function storeFaq(Request $request) {
        try{
            $faq = $request->validate([
                'question' => 'required|string',
                'answer' => 'required|string',
            ]);
            $faq['updated_by'] = auth()->id();

            Faq::create($faq);

            return apiSuccess('FAQ created successfully.');
        } catch (\Throwable $e) {
            return apiError($e->getMessage()); 
        }
    }

    public function storePrivacyPolicy(Request $request) {
        try{
            $privacyPolicy = $request->validate([
                'content' => 'required|string',
            ]);
            $privacyPolicy['updated_by'] = auth()->id();

            \App\Models\PrivacyPolicy::updateOrCreate($privacyPolicy);

            return apiSuccess('Privacy Policy updated successfully.');
        } catch (\Throwable $e) {
            return apiError($e->getMessage()); 
        }
    }
}
