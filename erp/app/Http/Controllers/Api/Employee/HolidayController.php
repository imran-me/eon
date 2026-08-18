<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    /**
     * Display a listing of past and future holidays.
     */
    public function index(Request $request)
    {
        $today = Carbon::today()->toDateString();
        
        // Fetch past holidays (end_date completely in the past)
        $pastHolidays = Holiday::where('end_date', '<', $today)
            ->orderBy('start_date', 'desc')
            ->get();
            
        // Fetch upcoming/future/current holidays
        $upcomingHolidays = Holiday::where('end_date', '>=', $today)
            ->orderBy('start_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Holidays retrieved successfully.',
            'data' => [
                'upcoming' => $upcomingHolidays,
                'past' => $pastHolidays
            ]
        ]);
    }
}
