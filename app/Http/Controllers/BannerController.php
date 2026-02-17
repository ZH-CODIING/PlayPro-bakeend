<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        return response()->json(
            Banner::latest()->get()
        );
    }

    public function show(Banner $banner)
    {
        return response()->json($banner);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'video'       => 'nullable|mimes:mp4,mov,avi,webm|max:20480'
        ]);

        if ($request->hasFile('video')) {
            $data['video'] = $request->file('video')->store('banners', 'public');
        }

        $banner = Banner::create($data);

        return response()->json($banner, 201);
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'video'       => 'nullable|mimes:mp4,mov,avi,webm|max:20480'
        ]);

        if ($request->hasFile('video')) {
            $data['video'] = $request->file('video')->store('banners', 'public');
        }

        $banner->update($data);

        return response()->json($banner);
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();

        return response()->json([
            'message' => 'تم حذف البانر بنجاح'
        ]);
    }
}
