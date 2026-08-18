<?php

namespace App\Http\Controllers\Dashboard;

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
            return view('dashboard.email_marketing.index', compact('campaigns'));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch email marketing campaigns.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Return the view for creating a new email marketing campaign
            return view('dashboard.email_marketing.create');
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
            $campaign = EmailCampaign::create([
                'subject' => $request->subject,
                'body' => $request->body,
                'schedule_at' => $request->schedule_at,
            ]);

            // $subscribers = Subscriber::all();

            // foreach ($subscribers as $subscriber) {
            //     SendEmailCampaign::dispatch($subscriber, $campaign);
            // }

            return back()->with('success', 'Email campaign dispatched!');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to create email marketing campaign.']);
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
            // Find the email marketing campaign by ID
            $campaign = \App\Models\EmailMarketing::findOrFail($id);

            // Return the view with campaign data
            return view('dashboard.email_marketing.edit', compact('campaign'));
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
            // Find the email marketing campaign by ID
            $campaign = \App\Models\EmailMarketing::findOrFail($id);

            // Validate the request data
            $request->validate([
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                // Add other validation rules as needed
            ]);

            // Update the campaign
            $campaign->update($request->all());

            // Redirect to the index with success message
            return redirect()->route('dashboard.email-marketing.index')->with('success', 'Email marketing campaign updated successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to update email marketing campaign.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Find the email marketing campaign by ID
            $campaign = \App\Models\EmailMarketing::findOrFail($id);

            // Delete the campaign
            $campaign->delete();

            // Redirect to the index with success message
            return redirect()->route('dashboard.email-marketing.index')->with('success', 'Email marketing campaign deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to delete email marketing campaign.']);
        }
    }
}
