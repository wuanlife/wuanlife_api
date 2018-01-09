<?php
/**
 * Created by PhpStorm.
 * User: moe
 * Date: 2018/1/6
 * Time: 21:16
 */

class Articles_model extends CI_Model
{
    private $a_base = 'articles_base';
    private $a_content = 'articles_content';
    private $a_status = 'articles_status';
    private $a_status_detail = 'a_status_detail';
    private $a_approval = 'articles_approval';
    private $a_approval_count = 'articles_approval_count';
    private $a_comments = 'articles_comments';
    private $a_comments_count = 'articles_comments_count';
    private $c_contents = 'comment_contents';   //评论内容表
    private $i_url = 'image_url';   //文章图片url表
    public function __construct()
    {
        parent::__construct();
    }

    public function articleOne()
    {

    }
    /**
     * 获得文章内容
     * @param $data
     * @param string $field
     * @return array
     */
    public function articleInfo($data, $field='*'):array
    {
        $res = $this->db
            ->select($field)
            ->from($this->a_base)
            ->where($data)
            ->get()
            ->result_array();
        return $res;
    }

    /**
     * 获得文章内容及权限
     * @param $data
     * @param string $field
     * @return array
     */
    public function articleInfoStatus($data, $field='*'):array
    {
        $res = $this->db
            ->select($field)
            ->from($this->a_base.' ab')
            ->join($this->a_status.' as', 'ab.id = as.id', 'left')
            ->where($data)
            ->get()
            ->result_array();
        return $res;
    }

    /**
     * 添加文章
     * @param $data
     * @return int
     */
    public function articleAdd($data): int
    {
        $id = 0;
        //回滚 start
        $this->db->trans_start();
        //添加文章表
        $data_base = [
            'author_id'=>$data['user_id'],
            'author_name'=>$data['user_name'],
            'content_digest'=> $data['resume'],
            'create_at'=>date('Y-m-d H:i:s')
        ];
        $this->db->insert($this->a_base, $data_base);
        $id = $this->db->insert_id();

        //添加文章内容表
        $data_content = [
            'id'=>$id,
            'title'=>$data['title'],
            'content'=>$data['content'],
        ];
        $this->db->insert($this->a_content, $data_content);

        //添加文章状态表
        $data_status = [
            'id'=>$id,
            'status'=>0,
            'create_at'=>date('Y-m-d H:i:s')
        ];
        $this->db->insert($this->a_status, $data_status);

        //添加文章图片url
        $data_url = [];
        foreach($data['image_urls_arr'] as $k => $v){
            $data_url[$k] = [
                'article_id'=>$id,
                'url'=>$v
            ];
        }
        $this->db->insert_batch($this->i_url, $data_url);

        //结束 end
        $this->db->trans_complete();
        return $id;
    }

    /**
     * 修改文章内容
     * @param $data
     * @return int
     */
    public function articleUpd($data): int
    {
        //回滚 start
        $this->db->trans_start();

        //添加文章表
        $data_base_where = [
            'id'=>$data['id'],
        ];
        $data_base_data = [
            'content_digest'=>$data['resume'],
        ];
        $this->db->where($data_base_where)->update($this->a_base, $data_base_data);
        $id = $this->db->insert_id();

        //添加文章内容表
        $data_content_where = [
            'id'=>$data['id'],
        ];
        $data_content_data = [
            'title'=>$data['title'],
            'content'=>$data['content'],
        ];
        $this->db->where($data_content_where)->update($this->a_content, $data_content_data);

        //结束 end
        $this->db->trans_complete();
        return $data['id'];
    }

    /**
     * 获取文章评论数量缓存
     * @param $id
     * @return int
     */
    public function commentsCount($id): int
    {
        $res = $this->db->select('count')->from($this->a_comments_count)->where('articles_id', $id)->get();
        $result = $res->row();
        if(empty($result)){
            return 0;
        }else{
            return $res->result()->count;
        }
    }

    /**
     * 获得评论
     * @param $data
     * @param string $field
     * @return array
     */
    public function commentsInfo($data, $field='*'):array
    {
        $res = $this->db
            ->select($field)
            ->from($this->a_comments)
            ->where($data)
            ->get()
            ->result_array();
        return $res;
    }

    /**
     * 添加评论
     * @param $data
     * @return array|bool
     */
    public function commentsAdd($data)
    {
        $id = 0;
        //获得现有楼层
        $count = $this->commentsCount($data['article_id']);

        //回滚 start
        $this->db->trans_start();

        //添加文章评论表
        $now_time = date('Y-m-d H:i:s');
        $data_comments = [
            'article_id'=>$data['article_id'],
            'user_id'=>$data['user_id'],
            'floor'=>$count+1,
            'create_at'=>$now_time
        ];
        $res1 = $this->db->insert($this->a_comments, $data_comments);
        $id = $this->db->insert_id();

        //添加文章内容表
        $data_c_contents = [
            'id'=>$id,
            'content'=>$data['comment']
        ];
        $res2 = $this->db->insert($this->c_contents, $data_c_contents);
        //回滚 end
        $this->db->trans_complete();

        if($res1 && $res2){
            $result = [
                'comment'=>$data['comment'],
                'update_at'=>$now_time,
                'create_at'=>$now_time,
                'floor'=>$count+1
            ];
            return $result;
        }else{
            return false;
        }
    }

    /**
     * 删除评论
     * @param $id
     * @return bool
     */
    public function commentsDel($id)
    {
        //回滚 start
        $this->db->trans_start();

        //删除评论基础信息
        $data_comments = [
            'comment_id'=>$id
        ];
        $res1 = $this->db->where($data_comments)->delete($this->a_comments);

        //删除文章评论数缓存信息
        $data_contents = [
            'id'=>$id
        ];
        $res2 = $this->db->where($data_contents)->delete($this->c_contents);

        //回滚 end
        $this->db->trans_complete();

        return ($res1&&$res2);
    }



    /*
     * 获得用户点赞
     * @param $data
     * @return mixed
     */
    public function get_approval_post($data)
    {
        //查询数据库中对应文章点赞情况
        $sql=$this->db->select('*')
            ->from('articles_approval')
            ->where('article_id',$data['article_id'])
            ->where('user_id',$data['user_id'])
            ->get()
            ->row_array();           
      return $sql;
    }



    /**
     * 更新点赞
     * @param $data
     * @return bool
     */
    public function update_approval_post($data)
    {
        //获取点赞状态
        $approved = $this->get_approval_post($data);
        
        if($approved['user_id']){

            //文章总赞数减一
            $res1 = $this->db->set('count','count-1',false)
                         ->where('article_id',$data['article_id'])
                         ->update('articles_approval_count');
            //取消用户对应文章点赞
            $res2 = $this->db->delete('articles_approval',$approved);
            //返回点赞成功
            return true;                   
        }else{
            //调用时，无user_id时，操作失败
            return $this->response(['error'=>'操作失败'],400);
        }

    }

    /**
     * 增加点赞
     * @param $data
     * @return bool
     */
    public function add_approval_post($data)
    {
        $field = array(
            'user_id' => $data['user_id'],
            'article_id' => $data['article_id'],
        );
        //在articles_approval表中添加点赞数据：user_id和文章_id
        return $this->db->insert('articles_approval',$field);
    }




    /*
     * 获得文章状态，为（A10）（A11）（A12）做准备 
     * @param $data
     * @return mixed
     */

    public function get_status_post($data)
    {
        //获取文章的状态
        $sql=$this->db->select('*')
            ->from('articles_status')
            ->where('id',$data)
            ->get()
            ->row_array(); 
        return $sql;
    }


    /*
     * 锁定文章(A10)  
     * @param $data
     * @return mixed
     */
    public function lock_post($data)
    {
        //锁定文章
        $sql=$this->db->set('status',1,false)
            ->where('id',$data)
            ->update('articles_status');
        return $sql;
    }



    /**
     * 删除帖子
     * @param $data
     * @return bool
     */
    public function delete_post($data){
        $d_data['delete'] = 1;
        return $this->db->where('id', $data['post_id'])
            ->update('post_base',$d_data)?
            TRUE:
            FALSE;
    }

    /**
     * 判断用户是否收藏帖子
     * @param $data
     * @return mixed
     */
    public function check_collect_post($data){
        $sql=$this->db->select('*')
            ->from('user_collection')
            ->where('post_base_id',$data['post_id'])
            ->where('user_base_id',$data['user_id'])
            ->get()
            ->row_array();
        return $sql;
    }

    /**
     * 收藏帖子
     * @param $data
     * @return bool
     */
    public function collect_post($data){
        $i_data=array(
            'post_base_id'=>$data['post_id'],
            'user_base_id'=>$data['user_id'],
            'create_time'=>time(),
        );
        return $this->db->insert('user_collection',$i_data);
    }








    /**
     *获取页面文章数据     //没有完成
     * @param array $data 
     * @param bool $spaging
     * @return array 
     */
    public function get_articles()
    {
        $select = ' articles_base.id,
                    articles_content.title,
                    articles_content.content,
                    articles_base.update_at,
                    articles_base.create_at,
                    articles_approval_count.count,
                    user_collections.user_id,
                    articles_comments_count.count,
                    articles_base.author_name,
                    articles_status.status,
                    articles_status.create_at
        ';
        $this->db->select($select);
        $this->db->from('articles_base');
        // $this->db->where('articles_content.id',$articles_id);
        $this->db->join('articles_approval','articles_approval.article_id = articles_base.id');
        $this->db->join('articles_approval_count','articles_approval.article_id = articles_base.id');
        // $this->db->join('articles_comments','articles_comments.article_id = articles_base.id');
        $this->db->join('articles_comments_count','articles_comments_count.articles_id = articles_base.id');
        $this->db->join('articles_content',' articles_base.id = articles_content.id');
        $this->db->join('articles_status','articles_status.id = articles_content.id');
        $this->db->join('user_collections','user_collections.article_id = articles_base.id');
        $query = $this->db->get();
        return $query->result_array();
    }


    /**
     * A4 文章详情 文章内容
     * @param  [type] $article_id [description]
     * @return [type]             [description]
     */
    public function get_article($article_id):array
    {
        $select = ' articles_base.id,
                    articles_content.title,
                    articles_content.content,
                    articles_base.update_at,
                    articles_base.create_at,
                    articles_base.author_name,
                    articles_status.status
        ';
        $this->db->select($select);
        $this->db->from('articles_base');
        $this->db->where("articles_base.id = {$article_id}");
        $this->db->join('articles_content',' articles_content.id = articles_base.id');
        $this->db->join('users_base','users_base.name = articles_base.author_name');     
        $this->db->join('articles_status','articles_status.id = articles_base.id');

        $re['articles'] = $this->db->get()->row_array();
        $data['article_id'] = $article_id;
        //$re['articles']['image_urls'] = $this->users_model->get_article_img($data);
        
        //获取文章评论数
        // $re['articles']['replied_num'] = $this->db->select('articles_comments_count.count')->from('articles_comments_count')->where("articles_comments_count.articles_id = {$article_id}")->get()->row()->count;

        //获取文章点赞数
        $re['articles']['approved_num'] = $this->db->select('articles_approval_count.count')->from('articles_approval_count')->where("articles_approval_count.article_id = {$article_id}")->get()->row()->count;
        
        //获取文章作者id
        $re['articles']['author_id'] = $this->db->select('users_base.id')->from('users_base')->where("users_base.name = '{$re['articles']['author_name']}'")->get()->row()->id;
        
        //获取文章收藏数
        $re['articles']['collected_num'] = $this->db->select('user_collections.user_id')->from('user_collections')->where("article_id = {$article_id}")->get()->num_rows();

        //获取作者的id name 头像url 发表文章数
        $re['articles']['author']['id'] =  $re['articles']['author_id'];
        $re['articles']['author']['name'] = $re['articles']['author_name'];
        $re['articles']['author']['avatar_url'] = $this->db->select('avatar_url.url')->from('avatar_url')->where("avatar_url.user_id = {$re['articles']['author']['id']}")->get()->row()->url;
        $re['articles']['author']['articles_num'] = $this->db->select('users_articles_count.count')->from('users_articles_count')->where("users_articles_count.user_id = {$re['articles']['author']['id']}")->get()->row()->count;
        unset($re['articles']['author_id']);
        unset($re['articles']['author_name']);
        
        return $re;
    }


    /**
     * A5 文章评论列表
     * 
     * @return [type] [description]
     */
    public function get_comments($article_id)
    {
        $select = ' articles_comments.user_id,
                    articles_comments.floor,
                    articles_comments.create_at,
                    comment_contents.content,
                    users_base.name,
                    ';
        $this->db->select($select);
        $this->db->from('articles_comments');
        $this->db->join('comment_contents','comment_contents.id = articles_comments.comment_id');
        $this->db->join('users_base','users_base.id = articles_comments.user_id');
        $this->db->where("articles_comments.article_id ={$article_id}");
        $data['reply'] = $this->db->get()->result_array();
        $data['total'] = $this->db->select('*')->from('articles_comments')->where('article_id',$article_id)->get()->num_rows();
        // for ($i=0; $i < $data['total']; $i++) { 
        //     $data['reply'][$i]['comment'] = $data['reply'][$i]['content'];
        //     $data['reply'][$i]['user_name'] = $data['reply'][$i]['name'];
        //     unset($data['reply'][$i]['content']);
        //     unset($data['reply'][$i]['name']);
        // }
        
        return $data;

    }

}