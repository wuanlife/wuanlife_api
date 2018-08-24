<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/12 0012
 * Time: 下午 2:35
 */

namespace App\Models\Users;


use Illuminate\Database\Eloquent\Model;

class UserCollections extends Model
{
    protected $table = 'user_collections';
    protected $primaryKey = ['user_id','article_id'];
    public $timestamps = false;

    /**
     * 查询此文章是否已被该用户收藏
     * @param $user_id
     * @param $article_id
     * @return bool
     */
    public static function getIsCollected($user_id,$article_id)
    {
        $res = self::where('user_id',$user_id)
            -> where('article_id',$article_id)
            -> first();
        return $res ? true : false;
    }

    /**
     * 查询文章被收藏数目
     * @param $article_id
     * @return mixed
     */
    public static function getCollectedNum($article_id)
    {
        return self::where('article_id',$article_id) -> count();
    }
}