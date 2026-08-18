<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SmsCampaign;

class SmsMarketingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch SMS marketing campaigns from the database
            $datas = SmsCampaign::latest()->paginate(10);

            // Return the view with campaigns data
            return response()->json([
                'success' => true,
                'message' => 'SMS marketing campaigns retrieved successfully.',
                'data' => $datas
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch SMS marketing campaigns.'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'schedule_at' => 'required', // Ensure the schedule_at is a valid date// Ensure status is either
                // Add other validation rules as needed
            ]);

            // Create a new SMS marketing campaign
            $smsCampaign = SmsCampaign::create($request->all());

            // Return a JSON response with success message
            return response()->json([
                'success' => true,
                'message' => 'SMS marketing campaign created successfully.',
                'data' => $smsCampaign
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to create SMS marketing campaign.'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Validate the request data
            $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                // Add other validation rules as needed
            ]);

            // Find the SMS marketing campaign by ID and update it
            $smsCampaign = SmsCampaign::findOrFail($id);
            $smsCampaign->update($request->all());

            // Return a JSON response with success message
            return response()->json([
                'success' => true,
                'message' => 'SMS marketing campaign updated successfully.',
                'data' => $smsCampaign
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to update SMS marketing campaign.'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the SMS marketing campaign by ID
            $smsCampaign = SmsCampaign::findOrFail($id);

            // Delete the SMS marketing campaign
            $smsCampaign->delete();

            // Return a JSON response with success message
            return response()->json([
                'success' => true,
                'message' => 'SMS marketing campaign deleted successfully.'
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete SMS marketing campaign.'
            ], 500);
        }
    }
}
