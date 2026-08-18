<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Portal;
use App\Models\Ticket;
use App\Models\Stock;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Ticket::select('tickets.*')
            ->leftJoin('users', 'users.id', '=', 'tickets.vendor_id')
            ->leftJoin('portals', 'portals.id', '=', 'tickets.portal_id')
            ->leftJoin('airports as from_airport', 'from_airport.id', '=', 'tickets.from_airport_id')
            ->leftJoin('airports as to_airport', 'to_airport.id', '=', 'tickets.to_airport_id')
            ->orderBy('tickets.title', 'asc');

        if ($request->has('vendor_id') && !empty($request->vendor_id)) {
            $query->where('tickets.vendor_id', $request->vendor_id);
        }

        if ($request->has('portal_id') && !empty($request->portal_id)) {
            $query->where('tickets.portal_id', $request->portal_id);
        }

        if ($request->has('from_airport_id') && !empty($request->from_airport_id)) {
            $query->where('tickets.from_airport_id', $request->from_airport_id);
        }

        if ($request->has('to_airport_id') && !empty($request->to_airport_id)) {
            $query->where('tickets.to_airport_id', $request->to_airport_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('tickets.title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->whereDate('tickets.status', $request->status);
        }

        $datas = $query->paginate(20);   

        // $airports = Airport::orderBy('name')->get();      
        // $vendors = User::orderBy('name')->role('vendor')->get();
        // $portals = Portal::orderBy('name')->where('status', 'active')->get();

        return response()->json([
            'success' => true,
            'message' => 'Tickets data retrieved successfully.',
            'data' => $datas
        ]);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('tickets.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {        
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            // 'vendor_id' => 'required',
            // 'portal_id' => 'required',
            'from_airport_id' => 'required',
            'to_airport_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);            
        }
        
        try {
            DB::transaction(function () use($request) {            
                $data = Ticket::create([
                    'title' => $request->title,
                    'vendor_id' => $request->vendor_id,
                    'portal_id' => $request->portal_id,
                    'from_airport_id' => $request->from_airport_id,
                    'to_airport_id' => $request->to_airport_id,
                    'price' => $request->price,
                    'qty' => $request->qty,  
                    'status' => $request->status ? 1 : 0
                ]);        
            });            
        } catch (\Throwable $th) {            
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.'
        ]);
    
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('tickets.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;        
        $data = Ticket::findOrFail($id);
        if (empty($data)) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);                
            } else{
                return redirect()->back()->with('error', 'Data Info Not Found!');
            }
        }
        $validated = $request->validate([
            'title' => 'required',
            'vendor_id' => 'required',
            'portal_id' => 'required',
            'from_airport_id' => 'required',
            'to_airport_id' => 'required'
        ]);

        try {            
            DB::transaction(function () use ($data, $request) {
                $data->update([
                    'title' => $request->title,
                    'vendor_id' => $request->vendor_id,
                    'portal_id' => $request->portal_id,
                    'from_airport_id' => $request->from_airport_id,
                    'to_airport_id' => $request->to_airport_id,
                    'price' => $request->price,
                    'qty' => $request->qty,
                    'status' => $request->status ? 1 : 0
                ]);        
            });
        } catch (\Throwable $th) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage()
                ]);                
            } else {
                return redirect()->back()->with('error', $th->getMessage());
            }
        }

        if (request()->ajax()) {            
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.'
            ]);
        } else {
            return redirect()->back()->with('success', 'Data updated successfully.');
        }
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = Ticket::with('ticket_sales', 'ticket_purchases')->find($request->item_id);
            if ($item) {
                if ($item->ticket_sales()->exists() || $item->ticket_purchases()->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Can not delete this Item, its containing purchase & sales!'
                    ]);
                }
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }

    
    public function getTicketEditModal(Request $request)
    {
        try {
            $data = $request->item_id ? Ticket::find($request->item_id) : null;

            $airports = Airport::orderBy('name')->get();
            $vendors = User::orderBy('name')->role('vendor')->get();
            $portals = Portal::orderBy('name')->where('status', 'active')->get();

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found!'
                ]);
            }
            $data['modal_view'] = view('tickets.edit-modal', [
                'data' => $data,
                'airports' => $airports,
                'vendors' => $vendors,
                'portals' => $portals,
            ])->render();

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found Successfully.'
        ]);
    }
}
