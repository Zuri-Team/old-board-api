<?php

namespace App\Http\Controllers;

use App\Post;
use App\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Auth;
use App\Notifications\PostNotifications;
use Illuminate\Support\Facades\Validator;

class PostsController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['role:superadmin'])->only(['store', 'update', 'delete']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $posts = Post::with('user')->with('category')->orderBy('created_at', 'desc')->paginate(10);
        if ($posts) {

            return $this->sendSuccess($posts, 'All Posts', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function view_posts_in_category($id)
    {
        $posts = Post::where('category_id', $id)->with('user')->orderBy('created_at', 'desc')->paginate(10);
        if ($posts) {
            return $this->sendSuccess($posts, 'All Posts in a Category fetched', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
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
        $validator = Validator::make($request->all(), [
            'post_title' => 'bail|required|unique:posts,post_title|min:8',
            'category_id' => 'required|integer',
            'post_body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $category = Category::find($request['category_id']);
        if(!$category) return $this->sendError('Category does not exist', 400, []);

        $user = Auth::user();
        $request = $request->all();
        $request['user_id'] = $user->id;

        $postCollection = [];
        try{
            $postCollection = Post::create($request);

            //SEND NOTIFICATION HERE (to db)
            $message = [
                'message'=>`New post created successfully.`,
            ];
            
            $user->notify(new PostNotifications($message));
            
            return $this->sendSuccess($postCollection, 'Post has been created successfully.', 201);

        }catch (\Exception $e){
            Log::error($e->getMessage());
           return $this->sendError('Internal server error.', 500, []);
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

        $post = Post::find($id)->with('user')->with('category')->first();

        if ($post) {
             $post['category'] = $post->category;
            //  $post['user'] = $post->user;
                return $this->sendSuccess($post, 'View a Post', 200);
        } else {
            return $this->sendError('Post not found', 404, []);
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
        $validator = Validator::make($request->all(), [
            'post_title' => 'bail|required|min:8',
            'category_id' => 'required|integer',
            'post_body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $category = Category::find($request['category_id']);
        if(!$category) return $this->sendError('Category does not exist', 400, []);

        try {

            if ($post = Post::find($id)) {
                if ($post->update($request->all())) {
                    return $this->sendSuccess($post, 'Post has been updated successfully.', 200);
                }
            } else {
                return $this->sendError('Post not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
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
        try {

            if ($post = Post::find($id)) {
                if ($post->delete()) {
                    return $this->sendSuccess($post, 'Post has been deleted successfully.', 200);
                }
            } else {
                return $this->sendError('Post not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }
}