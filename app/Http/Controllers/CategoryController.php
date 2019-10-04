<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Category;
use Validator;


class CategoryController extends Controller
{

    public function __construct(){
        $this->middleware(['role:superadmin']);
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $categories = Category::all();
            return response()->json([
                'status' => true,
                'message' => 'successfully fetch all categories',
                'categories' => $categories
            ],
            200
        );
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
    public function store(Request $request)
    {
        $messages = [];
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 401);
        }

        $input = $request->all();

        $category = new Category;
        $category->title = $input['title'];
        $category->description = $input['description'];
        $category->save();


        if($category){
            return response()->json([
                'status' => true,
                'message' => 'Successfully created category',
                'category' => $category,
            ], 200);

        }else{
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
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
        $category = Category::findOrFail($id);
            return response()->json([
                'status' => true,
                'message' => 'successfully fetched category',
                'category' => $category
            ],
            200
        );
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
        $messages = [];
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 401);
        }

        $input = $request->all();

        $category = Category::findOrfail($id);
        $category->title = $input['title'];
        $category->description = $input['description'];
        $category->save();

        if ($category) {
            return response()->json([
                'status' => true,
                'message' => 'Successfully updated category',
                'category' => $category,
            ], 200);

        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
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
        $category = Category::findOrfail($id);
        $category->delete();

        if ($category) {
            return response()->json([
                'status' => true,
                'message' => 'Successfully deleted category',
            ], 200);

        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
        }

    }
}
