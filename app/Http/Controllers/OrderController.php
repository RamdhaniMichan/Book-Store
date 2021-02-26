<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{

    public function __construct(){
        $this->middleware(function($request, $next){
            if(Gate::allows('manage-orders')) return $next($request);
            abort(403, 'Anda tidak memiliki cukup hak akses');
        });
    }
    
    public function index(Request $request)
    {
        $status = $request->get('status');
        $buyer_email = $request->get('buyer_email');
        $orders = Order::with('user')->with('books')->whereHas('user', function($query) use ($buyer_email) {
            $query->where('email', 'LIKE', "%$buyer_email%");
        })
        ->where('status', 'LIKE', "%$status%")
        ->paginate(10);
        return view('orders.index', ['orders' => $orders]);
    }

   
    public function create()
    {
        
    }

   
    public function store(Request $request)
    {
        //
    }

   
    public function show($id)
    {
        //
    }

    
    public function edit($id)
    {
        $order = Order::findOrFail($id);
        return view('orders.edit', ['order' => $order]);
    }

   
    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->status = $request->get('status');
        $order->save();
        return redirect()->route('orders.edit', [$order->id])->with('status', 'Order status succesfully updated');
    }

    
    public function destroy($id)
    {
        //
    }
}
