<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
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
            $campaigns = \App\Models\WhatsappCampaign::latest()->paginate(10);

            // Return the view with campaigns data
            return view('dashboard.whatsapp_marketing.index', compact('campaigns'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch WhatsApp marketing campaigns.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Return the view for creating a new WhatsApp marketing campaign
            return view('dashboard.whatsapp_marketing.create');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load create form.']);
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
            \App\Models\WhatsappMarketing::create($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.whatsapp-marketing.index')->with('success', 'WhatsApp marketing campaign created successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to create WhatsApp marketing campaign.']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            // Fetch the specific WhatsApp marketing campaign
            $campaign = \App\Models\WhatsappMarketing::findOrFail($id);

            // Return the view for editing the campaign
            return view('dashboard.whatsapp_marketing.edit', compact('campaign'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form.']);
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
            $campaign = \App\Models\WhatsappMarketing::findOrFail($id);
            $campaign->update($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.whatsapp-marketing.index')->with('success', 'WhatsApp marketing campaign updated successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to update WhatsApp marketing campaign.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the campaign by ID and delete it
            $campaign = \App\Models\WhatsappMarketing::findOrFail($id);
            $campaign->delete();

            // Redirect to the index with success message
            return redirect()->route('dashboard.whatsapp-marketing.index')->with('success', 'WhatsApp marketing campaign deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to delete WhatsApp marketing campaign.']);
        }
    }
}
