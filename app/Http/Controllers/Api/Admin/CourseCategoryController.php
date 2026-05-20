<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseCategoryController extends Controller
{
    public function index()
    {
        $categories = CourseCategory::withCount('courses')->latest()->get();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:course_categories,name',
            'description' => 'nullable|string|max:1000',
        ]);

        $category = CourseCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
        ]);

        return response()->json(['success' => true, 'message' => 'Kategori kursus berhasil ditambahkan!', 'data' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $category = CourseCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:course_categories,name,' . $category->id,
            'description' => 'nullable|string|max:1000',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
        ]);

        return response()->json(['success' => true, 'message' => 'Kategori kursus berhasil diperbarui!', 'data' => $category]);
    }

    public function destroy($id)
    {
        $category = CourseCategory::findOrFail($id);
        $category->delete();

        return response()->json(['success' => true, 'message' => 'Kategori kursus berhasil dihapus!']);
    }
}
