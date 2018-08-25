<?php
/**
 * 文章评论控制器
 * Created by PhpStorm.
 * User: hp
 * Date: 2018/7/31
 * Time: 17:22
 */

namespace App\Http\Controllers;

use App\Models\Articles\Articles_Comments;
use App\Models\Articles\Articles_Comments_Count;
use App\Models\Articles\ArticlesComment;
use App\Models\Articles\Users_Base;
use App\Models\Articles\Comment_Contents;
use App\Models\Articles\ArticlesBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Validator;

class ArticlesCommentController extends Controller
{
    /**
     * @param Request $request
     * @return mixed
     * 2018/8/6 15:22---aunhappy
     * 搜索帖子A14
     */
    public function get_articles_search(Request $request)
    {
        $data = [
            'limit'     => $request->input('limit') ?? 20,     //每页显示数
            'offset'    => $request->input('offset') ?? 0,     //每页起始数
            'keyword'     => $request->input('keyword'),       //关键字
            'order'     => $request->input('order') ?? 'asc',
        ];
        $articles_id = ArticlesBase::search($data['keyword'])->keys()->toArray();
        //sort($articles_id);
        $article = ArticlesBase::wherein('articles_base.id',$articles_id)
            ->wherenotin('articles_base.id',function ($query){
                $query->select('articles_status.id')
                ->from('articles_status')
                ->whereRaw('`status` >> 2 & 1 = 1 AND articles_base.id = articles_status.id');
        });

        $articles =  $article->orderBy('update_at',$data['order'])->offset($data['offset'])->limit($data['limit'])->get();

        foreach ($articles as $k=>$v) {
            $user_info = Builder::requestInnerApi(
                env('OIDC_SERVER'),
                "/api/app/users/{$v->author_id}"
            );
            $user = json_decode($user_info['contents']);
            $rs['articles'][$k]=[
                "id"=>$v->id,
                "title"=>$v->content['title'],
               // "content"=>$v->content['content'],
                "content_digest"=>$v->content_digest,
                "update_at"=>$v->update_at,
                "create_at"=>$v->create_at,
                "author"=>[
                    "avatar_url"=>$user->avatar_url,
                    "name"=>$user->name,
                    "id"=>$user->id
                ]
            ];
        }
        $rs['total'] = $article->count();
        return $rs;
    }
    /**
     * @param Request $request
     * @return mixed
     * 2018/8/6 12:41---aunhappy
     * A1帖子主页
     */
    public function get_articles_index(Request $request)
    {
        $data = [
            'limit'     => $request->input('limit') ?? 20,     //每页显示数
            'offset'    => $request->input('offset') ?? 0,     //每页起始数
            'order'     => $request->input('order') ?? 'asc',
            'id'        => $request->get('id-token')->uid ?? null,
        ];
        $with = [
            //'content','avatar_url','approval_count','collections_count','comments_count',
            'approved'=>function($query) use ($data) {
                $query->where('user_id',$data['id']);
            },
            'collected'=>function($query) use ($data) {
                $query->where('user_id',$data['id']);
            },
            'replied'=>function($query) use ($data) {
                $query->where('user_id',$data['id']);
            },
            //'articles_status'
        ];
        $article = ArticlesBase::with($with)->whereNotExists(function ($query){
            $query->select('articles_status.id')
                ->from('articles_status')
                ->whereRaw('`status` >> 2 & 1 = 1 AND articles_base.id = articles_status.id');
        });
        $articles =  $article->orderBy('update_at',$data['order'])->offset($data['offset'])->limit($data['limit'])->get();
        foreach ($articles as $k => $v){
            $j = 0;
            $images = [];
            foreach ($v->articles_image as $url) {
                if ( ! empty($url->url) && $j++ < 3) {
                    $images[] = $url->url;
                }
            }
            $rs['articles'][$k]=[
                "id"=>$v->id,
                "title"=>$v->content['title'],
                //"content"=>$v->content['content'],
                "content_digest"=>$v->content_digest,
                "update_at"=>$v->update_at,
                "create_at"=>$v->create_at,
                "approved"=>$v->approved?true:false,
                "approved_num"=>$v->approval_count['count'],
                "collected"=>$v->collected?true:false,
                "collected_num"=>$v->collections_count['count'],
                "replied"=>$v->replied?true:false,
                "replied_num"=>$v->comments_count['count'],
                "image_urls"=>$images,
                "author"=>[
                    "avatar_url"=>$v->avatar_url['url'],
                    "name"=>$v->author_name,
                    "id"=>$v->author_id
                ]
            ];
        }
        $rs['total'] = $article->count();
        return $rs;
    }

    /**
     * A5 文章评论列表
     * @param $id int 文章id
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function index($id, Request $request)
    {
        $limit = $request->input('limit') ?? env("LIMIT");
        $offset = $request->input('offset') ?? 0;

        // 判断文章是否存在
        $article = ArticlesBase::find($id);
        if (!$article) {
            return response(['error' => '该文章不存在'], 404);
        }

        $articles_comments = ArticlesComment::where("article_id", "=", $id)
            ->select("comment_id", "user_id", "floor", "create_at")
            ->paginate($limit, '', $offset);

        //拼接成文档约定的格式
        $data = [];
        foreach ($articles_comments as $articles_comment) {
            $user_info = Builder::requestInnerApi(
                env('OIDC_SERVER'),
                "/api/app/users/{$articles_comment->user_id}"
            );
            $user = json_decode($user_info['contents']);
            $buffer = $this->splicing($user, $articles_comment);
            $data[] = $buffer;
        }
        $result = [
            "reply" => $data,
            "total" => $articles_comments->total()
        ];
        return response($result, Response::HTTP_OK);
    }

    /**
     * A7 评论文章
     * @param Request $request
     * @param $id int 文章id
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function create(Request $request, $id)
    {
        //表单验证
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|filled|between:1,5000',
        ]);

        if ($validator->fails()) {
            return response(["error" => "表单验证失败"], Response::HTTP_BAD_REQUEST);
        }

        $comment = $request->input("comment");
        $user_id = $request->get("id-token")->uid;
        // 开启事务
        DB::beginTransaction();
        // 判断文章是否存在
        $article = ArticlesBase::find($id);
        if (!$article) {
            return response(['error' => '该文章不存在'], 404);
        }
        // 获取最新的楼数
        $floor = ArticlesComment::where([
            'article_id' => $id,
        ])->orderBy('create_at', 'desc')->value('floor');

        try {
            DB::beginTransaction();
            $article_comment = ArticlesComment::create([
                'article_id' => $id,
                'user_id' => $user_id,
                'floor' => $floor + 1 ?? 1,
                'create_at' => time(),
            ]);

            $article_comment->content()->create([
                'content' => $comment,
            ]);
            $response = Builder::requestInnerApi(
                env('OIDC_SERVER'),
                "/api/app/users/{$user_id}"
            );
            $user = json_decode($response['contents']);
            $data = $this->splicing($user, $article_comment);
            DB::commit();
            return response($data, Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return response(["error" => "新增评论失败"], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * A9 删除评论
     * @param $id
     * @param $floor
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     */
    public function delete(Request $request, $id, $floor)
    {
        $user_id = $request->get("id-token")->uid;

        // 查询文章评论
        $article_comment = ArticlesComment::where([
            'article_id' => $id,
            'floor' => $floor
        ])->first();

        if (!$article_comment) {
            return response(["error" => "评论不存在"], Response::HTTP_NOT_FOUND);
        }

        // 验证用户是否有权限进行操作，文章作者与评论者有权删除
        if ($article_comment->article->author_id == $user_id || $article_comment->user_id == $user_id) {
            return response(["error" => "没有权限操作"], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();
            // 删除评论详情
            $article_comment->content()->delete();
            // 删除评论
            $article_comment->delete();
            DB::commit();
            return response(["删除成功"], Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return response(["error" => "删除失败"], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 拼接信息
     * @param $user
     * @param $comment
     * @param $articles_comments
     * @return array
     */
    private function splicing($user, $comment, $articles_comments)
    {
        $data = [
            "user" => [
                "id" => $user->id,
                "name" => $user->name,
            ],
            "comment" => $comment["content"],
            "floor" => $articles_comments["floor"],
            "create_at" => $articles_comments["create_at"]
        ];
        return $data;
    }
}