<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    // public function index(Request $request)
    // {
    //     return response()->json(
    //         Product::query()
    //   ->filter($request)
    //             ->with('ratings.user')->latest()->get()
    //     );
    // }
    public function index(Request $request)
{
    $search = $request->get('search');

    $cacheKey = "products:index:search={$search}";

    $products = Cache::remember(
        $cacheKey,
        now()->addMinutes(10),
        function () use ($request) {
            return Product::query()
                ->filter($request)
                ->with('ratings.user')
                ->latest()
                ->get();
        }
    );

    return response()->json($products);
}


 public function show(Request $request, $id)
{
    $product = Product::query()
        ->where('id', $id)
        ->filter($request) 
        ->with('ratings.user')
        ->firstOrFail();

    return response()->json($product);
}
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric',
            'type'        => 'required|string',
            'quantity'    => 'required|integer',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);
Cache::flush();

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'sometimes|required|numeric',
            'type'        => 'sometimes|required|string',
            'quantity'    => 'sometimes|required|integer',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        Cache::flush();


        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
Cache::flush();

        return response()->json([
            'message' => 'تم حذف المنتج بنجاح'
        ]);
    }
}
