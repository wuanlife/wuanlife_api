<?php

namespace App\Http\Controllers;

use App\Models\Articles\ArticlesStatus;
use App\Models\Users\UserCollection;
use Illuminate\Http\Request;
use App\Models\Users\User_collection;
use App\Models\Articles\ArticlesBase;

class UserController extends Controller
{
    //收藏文章A12
    public function collect($user_id, Request $request)
    {
        if ($request->get('id-token') != NULL) {
            //判断是否登陆
            $uid = $request->get('id-token')->uid;
            if ($uid == $user_id) {
                //判断uid和token里的id是否一致
                $article_id = $request->input("article_id");
//                $article_id = 2;
                $bool = ArticlesBase::find($article_id);
                if (isset($bool)) {
                    //判断文章是否存在
                    $status = ArticlesStatus::where('id', $article_id)->first();
                    if ($status) {
                        $status = $status->status;
                    }
                    if ($status != 4) {
                        //判断文章是否被删除 4为删除
                        $user = User_collection::where(['user_id' => $user_id, 'article_id' => $article_id])->first();
//                        dd($user);
                        if (!isset($user)) {
                            //判断是否被收藏
                            $a = new User_collection;
                            $a->user_id = $user_id;
                            $a->article_id = $article_id;
                            $a->create_at = time();
                            $bool = $a->save();
                            if ($bool == true) {
                                return response(['收藏成功'], 204);
                            } else {
                                return response(['收藏失败'], 400);
                            }
                        } else {
                            return response(['已收藏'], 204);
                        }
                    } else {
                        return response(['文章已被删除'], 410);
                    }
                } else {
                    return response(['文章不存在'], 404);
                }
            } else {
                return response(['没有权限操作'], 403);
            }
        } else {
            return response(['未登录，不能操作'], 401);
        }

    }

    //取消收藏文章A16
    public function del_collect($user_id, Request $request)
    {
        if ($request->get('id-token') != NULL) {
            //判断是否登陆
            $uid = $request->get('id-token')->uid;
            //判断是否登陆
            if ($uid == $user_id) {
                //判断uid和token里的id是否一致
                $article_id = $request->input("article_id");
//                $article_id = 1;
                $bool = ArticlesBase::find($article_id);
                if (isset($bool)) {
                    //判断文章是否存在
                    $status = ArticlesStatus::where('id', $article_id)->first()->status;
                    if ($status != 4) {
                        //判断文章是否被删除 4为删除
                        $user = User_collection::where(['user_id' => $user_id, 'article_id' => $article_id])->first();
                        if (isset($user)) {
                            //判断是否被收藏
                            $bool = User_collection::where(['user_id' => $user_id, 'article_id' => $article_id])->delete();
                            if ($bool == true) {
                                return response(['取消收藏成功'], 204);
                            } else {
                                return response(['取消收藏成功失败'], 400);
                            }
                        } else {
                            return response(['已取消收藏'], 204);
                        }
                    } else {
                        return response(['文章已被删除'], 410);
                    }
                } else {
                    return response(['文章不存在'], 404);
                }
            } else {
                return response(['没有权限操作'], 403);
            }
        } else {
            return response(['未登录，不能操作'], 401);
        }
    }

    /**
     * A13 收藏列表
     * @param Request $request
     * @param $user_id
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getCollect(Request $request, $user_id)
    {
        //判断是否登陆
        if ($request->get('id-token') == NULL) {
            return response(['未登录，不能操作'], 401);
        }
        $uid = $request->get('id-token')->uid;
        //判断uid和token里的id是否一致
        if ($uid != $user_id) {
            return response(['没有权限操作'], 403);
        }
        //用户、作者相关
        $user_info = Builder::requestInnerApi(
            env('OIDC_SERVER'),
            "/api/app/users/{$uid}"
        );
        $user = json_decode($user_info['contents']);
        if(is_null($user)){
            return response(['error'=>'获取用户文章列表失败'],400);
        }
        $author['name'] = $user->name;
        $author['id'] = $uid;
        //文章相关
        $input = $request->all();
        $offset = empty($input['offset']) ? 0 : (int)$input['offset'];
        $limit = empty($input['limit']) ? 20 : (int)$input['limit'];

        $articles = UserCollection::with(['article.content', 'article.articles_status', 'article.articles_image'])->where(['user_id' => $uid])->offset($offset)->limit($limit)->get();
        if($articles->isEmpty()){
            return response(['articles' => array()],200);
        }
        $res = [];
        foreach($articles as $article){
//            dd($article->toArray());
            $res[] = [
                'id' => $article->article->id,
                'title' => $article->article->content->title,
                'content' => $article->article->content->content,
                'update_at' => $article->article->update_at,
                'create_at' => $article->article->create_at,
                'collect_at' => $article->create_at,
                'delete' => ArticlesStatus::status($article->article->articles_status->status, '删除'),
                'image_urls' => $article->article->articles_image,
            ];
        }
        $response['articles'] = $res;
        $response['author'] = $author;
        return response()->json($response,200)->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
