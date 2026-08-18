<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmailCampaign;

class EmailMarketingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch email marketing campaigns from the database
            $campaigns = EmailCampaign::latest()->paginate(10);
            // Return the view with campaigns data
            return response()->json([
                'success' => true,
                'message' => 'Email marketing campaigns retrieved successfully.',
                'data' => $campaigns
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email marketing campaigns.'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $campaign = EmailCampaign::create([
                'subject' => $request->subject,
                'body' => $request->body,
                'schedule_at' => $request->schedule_at,
            ]);

            // $subscribers = Subscriber::all();

            // foreach ($subscribers as $subscriber) {
            //     SendEmailCampaign::dispatch($subscriber, $campaign);
            // }

            return response()->json([
                'success' => true,
                'message' => 'Email campaign dispatched!',
                'data' => $campaign
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to create email marketing campaign.'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Find the email marketing campaign by ID
            $campaign = EmailCampaign::findOrFail($id);

            // Validate the request data
            $request->validate([
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                // Add other validation rules as needed
            ]);

            // Update the campaign
            $campaign->update($request->all());

            // Redirect to the index with success message
            return response()->json([
                'success' => true,
                'message' => 'Email marketing campaign updated successfully.',
                'data' => $campaign
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email marketing campaign.'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the email marketing campaign by ID
            $campaign = EmailCampaign::findOrFail($id);

            // Delete the campaign
            $campaign->delete();

            // Redirect to the index with success message
            return response()->json([
                'success' => true,
                'message' => 'Email marketing campaign deleted successfully.'
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email marketing campaign.'
            ], 500);
        }
    }
}
