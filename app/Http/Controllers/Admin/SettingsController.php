<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\PrivacyPolicy;
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

    public function getTerms(Request $request) {
        try{
            $terms = Term::first(['id','content']);
            
            return apiSuccess('Terms retrieved successfully.', $terms);
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

    public function editFaq(Request $request, $id) {
        try{
            // Get the faq
            $faq = Faq::find($id);

            // check if faq exists
            if (!$faq) {
                return apiError('FAQ not found.', [], 404);
            }

            // Validate the request
            $data = $request->validate([
                'question' => 'required|string',
                'answer' => 'required|string',
            ]);

            // Add the updated_by field
            $data['updated_by'] = auth()->id();

            // Update the faq
            $faq->update($data);

            // Return the success message
            return apiSuccess('FAQ updated successfully.',$faq);
        } catch (\Throwable $e) {
            return apiError($e->getMessage()); 
        }
    }

    public function deleteFaq(Request $request, $id) {
        try{
            // Validate the request
            $faq = Faq::find($id);

            // check if faq exists
            if (!$faq) {
                return apiError('FAQ not found.', [], 404);
            }

            // Delete the faq
            Faq::destroy($id);

            // Return the success message
            return apiSuccess('FAQ deleted successfully.');
        } catch (\Throwable $e) {
            return apiError($e->getMessage()); 
        }
    }

    public function getFaq(Request $request) {
        try{
            $faqs = Faq::get(['id','question','answer']);
            
            return apiSuccess('FAQs retrieved successfully.', $faqs);
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

            PrivacyPolicy::updateOrCreate($privacyPolicy);

            return apiSuccess('Privacy Policy updated successfully.');
        } catch (\Throwable $e) {
            return apiError($e->getMessage()); 
        }
    }

    public function getPrivacyPolicy() {
        try{
            $privacyPolicy = PrivacyPolicy::first(['id','content']);
            
            return apiSuccess('Privacy Policy retrieved successfully.', $privacyPolicy);
        } catch (\Throwable $e) {
            return apiError($e->getMessage()); 
        }
    }
}
