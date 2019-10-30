<?php

namespace App\Http\Controllers;

use App\Http\Classes\ResponseTrait;
use App\Post;
use App\PostComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostCommentController extends Controller
{
    use ResponseTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function retrieve_post_comments($id)
    {
        $post_comments = PostComment::where('post_id', $id)->orderBy('created_at', 'asc')->get();

        if($post_comments){
            return $this->sendSuccess($post_comments, 'Commented', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function user_post_comment(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            'comment' => '',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $post = Post::where('id', $id)->get();

        $user_id = Auth()->user()->id;

        if($post){
            $post_comment = PostComment::create([
                'post_id' => $id,
                'user_id' => $user_id,
                'comment' => $request->input('comment')
            ]);
        }

        if($post_comment){
            return $this->sendSuccess($post_comment, 'Commented', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show_all_post_comments($id)
    {
        $post_comments = PostComment::where('post_id', $id)->get();

        if($post_comments){
            return $this->sendSuccess($post_comments, 'Commented', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update_user_comment(Request $request, $id)
    {

        $user_id = Auth::user()->id;

        $post_comment = PostComment::find($id)->where('user_id', $user_id);


        $data = ['comment' => $request->input('comment')];

        if($post_comment->update($data)){

            return $this->sendSuccess($data, 'Comment Updated', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete_user_comment($id)
    {
        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }

        $delete_comment = PostComment::destroy($id);

        if ($delete_comment) {

            return $this->sendSuccess($delete_comment, 'Comment Deleted', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }
}
