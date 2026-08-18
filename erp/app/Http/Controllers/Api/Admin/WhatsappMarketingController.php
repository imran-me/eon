<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCampaign;
use Illuminate\Http\Request;

class WhatsappMarketingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Fetch WhatsApp marketing campaigns from the database
            $campaigns = WhatsappCampaign::latest()->paginate(10);

            // Return the view with campaigns data
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp marketing campaigns retrieved successfully.',
                'data' => $campaigns
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch WhatsApp marketing campaigns.'
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
                'name' => 'required|string|max:255',
                'message' => 'required|string',
                // Add other validation rules as needed
            ]);

            // Create a new WhatsApp marketing campaign
            $campaign = WhatsappCampaign::create($request->all());

            // Return a JSON response with success message
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp marketing campaign created successfully.',
                'data' => $campaign
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to create WhatsApp marketing campaign.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            // Fetch the specific WhatsApp marketing campaign
            $campaign = WhatsappCampaign::findOrFail($id);

            // Return the view for displaying the campaign
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp marketing campaign retrieved successfully.',
                'data' => $campaign
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch WhatsApp marketing campaign.'
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
                'name' => 'required|string|max:255',
                'message' => 'required|string',
                // Add other validation rules as needed
            ]);

            // Find the campaign and update it
            $campaign = WhatsappCampaign::findOrFail($id);
            $campaign->update($request->all());

            // Return a JSON response with success message
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp marketing campaign updated successfully.',
                'data' => $campaign
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to update WhatsApp marketing campaign.'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the campaign by ID and delete it
            $campaign = WhatsappCampaign::findOrFail($id);
            $campaign->delete();

            // Return a JSON response with success message
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp marketing campaign deleted successfully.'
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete WhatsApp marketing campaign.'
            ], 500);
        }
    }
}
