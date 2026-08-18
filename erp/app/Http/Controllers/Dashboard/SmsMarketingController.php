<?php

namespace App\Http\Controllers\Dashboard;

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
            $smsCampaigns = SmsCampaign::latest()->paginate(10);

            // Return the view with campaigns data
            return view('dashboard.sms-marketing.index', compact('smsCampaigns'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch SMS marketing campaigns.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Return the view for creating a new SMS marketing campaign
            return view('dashboard.sms-marketing.create');
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
        // dd($request->all());
        try {
            // Validate the request data
            $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'schedule_at' => 'required', // Ensure the schedule_at is a valid date// Ensure status is either
                // Add other validation rules as needed
            ]);

            // Create a new SMS marketing campaign
            SmsCampaign::create($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.sms-marketing.index')->with('success', 'SMS marketing campaign created successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to create SMS marketing campaign.']);
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
            // Find the SMS marketing campaign by ID
            $smsCampaign = SmsMarketing::findOrFail($id);

            // Return the view for editing the SMS marketing campaign
            return view('dashboard.sms_marketing.edit', compact('smsCampaign'));
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
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                // Add other validation rules as needed
            ]);

            // Find the SMS marketing campaign by ID and update it
            $smsCampaign = SmsMarketing::findOrFail($id);
            $smsCampaign->update($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.sms-marketing.index')->with('success', 'SMS marketing campaign updated successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to update SMS marketing campaign.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the SMS marketing campaign by ID
            $smsCampaign = SmsMarketing::findOrFail($id);

            // Delete the SMS marketing campaign
            $smsCampaign->delete();

            // Redirect to the index with success message
            return redirect()->route('dashboard.sms-marketing.index')->with('success', 'SMS marketing campaign deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to delete SMS marketing campaign.']);
        }
    }
}
