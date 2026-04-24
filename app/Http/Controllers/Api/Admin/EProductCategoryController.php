<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EProductCategoryController extends Controller
{
    public function index()
    {
        $categories = EProductCategory::latest()->get();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255|unique:e_product_categories,name']);
        
        $category = EProductCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name)
        ]);

        return response()->json(['success' => true, 'message' => 'Kategori berhasil ditambahkan!', 'data' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $category = EProductCategory::findOrFail($id);
        
        $request->validate(['name' => 'required|string|max:255|unique:e_product_categories,name,' . $category->id]);
        
        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name)
        ]);

        return response()->json(['success' => true, 'message' => 'Kategori berhasil diperbarui!', 'data' => $category]);
    }

    public function destroy($id)
    {
        $category = EProductCategory::findOrFail($id);
        $category->delete();

        return response()->json(['success' => true, 'message' => 'Kategori berhasil dihapus!']);
    }
}