<?php

namespace App\Http\Controllers;

use App\Category;
use App\Http\Requests\StoreCategory;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:superadmin', 'role:admin']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $categories = Category::orderBy('created_at', 'desc')->paginate(10);
        if ($categories) {
            return CategoryResource::collection($categories);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCategory $request)
    {
        $data = $request->validated();

        $category = Category::create($data);

        if ($category) {
            $categories = Category::orderBy('created_at', 'desc')->paginate(10);
            return CategoryResource::collection($categories);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if ($category = Category::whereId($id)->first()) {
            return new CategoryResource($category);
        } else {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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

        $data = $request->validate([
            'title' => 'required|unique:categories',
            'description' => 'nullable',
            'updated_by' => 'required',
        ]);

        if ($category = Category::find($id)) {
            if ($category->update($data)) {
                return new CategoryResource($category);
            }
        } else {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (Category::find($id)->delete()) {
            return response()->json([
                'message' => 'Category deleted successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }
    }
}