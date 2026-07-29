<?php
namespace api\home\controller;
use think\Db;
use think\Validate;
use cmf\controller\RestBaseController;
use api\user\model\UserLikeModel;
use api\portal\model\PortalPostModel;

class NoticeController extends RestBaseController
{
    protected $postModel;

    public function __construct(PortalPostModel $postModel)
    {
        parent::__construct();
        $this->postModel = $postModel;
    }

    public function _initialize(){
       header('Content-Type:text/html; charset= utf-8'); 
    }
    //地址列表
    public function lists()
    {
        $validate = new Validate([
            'type'          => 'require',
            ]);
        $validate->message([
           'type.require'   => '请选择公告类型!',
        ]);
        $data=$this->request->param();
        $limit_start=empty($data['limit_start'])?0:$data['limit_start'];
        $limit_num=empty($data['limit_num'])?10:$data['limit_num'];
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        if($data['type']!=1&&$data['type']!=2){
            $this->error('要查看的公告类型不正确！');
        }
        $type=$data['type'];
        //查当前分类下的文章id们
        $category=Db::name('portal_category_post')->where(['category_id'=>$type,'status'=>1])->column('post_id');
        $where['post_type']=1;
        $where['post_status']=1;
        $where['p.id']=['in', $category];
        if(isset($data['keyword'])&&!empty($data['keyword'])){
            $where['p.post_title']=['like', "%".$data['keyword']."%"];
        }        

        $field="u.user_nickname,p.id,p.post_title,p.create_time,p.create_time AS ctime,p.post_content,p.post_excerpt,p.thumbnail,p.post_like,p.post_cai";
        $count=        Db::name("portal_post")->alias('p')
                            ->join('jl_user u','u.id= p.user_id')
                            ->where($where)
                            ->count(1);
        

        $selectdata=   Db::name("portal_post")->alias('p')
                        ->join('jl_user u','u.id= p.user_id')
                        ->where($where)
                        ->field($field)
                        ->order('p.id desc')
                        ->limit($limit_start,  $limit_num)
                        ->select()->toArray();
        
        $like_array  = array();
        $unlike_array  = array();

        $userId = $this->userId;

        $userLikeModel = new UserLikeModel();

        $like_data= $userLikeModel->where([
            'user_id'   => $userId,
        ])->where('table_name', 'portal_post_like')->select();

        foreach ($like_data as $key => $value) {
            $like_array[] = $value['object_id'];
        }

        $unlike_data = $userLikeModel->where([
            'user_id'   => $userId,
        ])->where('table_name', 'portal_post_unlike')->select();

        foreach ($unlike_data as $key => $value) {
            $unlike_array[] = $value['object_id'];
        }


        foreach( $selectdata as $key => &$v){
            $v['visit_url'] =  url('portal/Article/index',['id'=>$v['id']],true,true);
            $v['post_content'] = strip_tags(htmlspecialchars_decode($v['post_content']));
            // $_SERVER['HTTP_HOST']."/api/home/notice/getMore?id=".$v['id'];
            $v['is_liked'] = 0;
            $v['is_cai'] = 0;
            if(in_array($v['id'], $like_array)){
                $v['is_liked'] = 1;
            }
            if(in_array($v['id'], $unlike_array)){
                $v['is_cai'] = 1;
            }            
        }
            //标题 创建人 创建时间 内容   摘要  缩略图
            // post_title  user_id管理user表  create_time   post_content  post_excerpt  thumbnail
        if ($count<=0) {
            $this->success('操作成功！',['count'=>0,'list'=>[]]);
        }else{
            $this->success('操作成功！',['count'=>$count,'list'=>$selectdata]);
        }
    }



    public function doLike()
    {
        $userId = $this->getUserId();

        $articleId = $this->request->param('id', 0, 'intval');

        $userLikeModel = new UserLikeModel();

        $findLikeCount = $userLikeModel->where([
            'user_id'   => $userId,
            'object_id' => $articleId
        ])->where('table_name', 'portal_post_like')->count();

        if (empty($findLikeCount)) {
            $article = $this->postModel->where(['id' => $articleId])->field('id,post_title,post_excerpt,more')->find();
            if (empty($article)) {
                $this->error('文章不存在！');
            }

            Db::startTrans();
            try {
                $this->postModel->where(['id' => $articleId])->setInc('post_like');
                $thumbnail = empty($article['more']['thumbnail']) ? '' : $article['more']['thumbnail'];
                $userLikeModel->insert([
                    'user_id'     => $userId,
                    'object_id'   => $articleId,
                    'table_name'  => 'portal_post_like',
                    'title'       => $article['post_title'],
                    'thumbnail'   => $thumbnail,
                    'description' => $article['post_excerpt'],
                    'url'         => json_encode(['action' => 'portal/Article/index', 'param' => ['id' => $articleId, 'cid' => $article['categories'][0]['id']]]),
                    'create_time' => time()
                ]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();

                $this->error('发表利好失败！');
            }

            $likeCount = $this->postModel->where('id', $articleId)->value('post_like');
            $this->success("利好+1！", ['post_like' => $likeCount]);
        } else {
            $this->error("您已表明利好态度了O(∩_∩)O");
        }
    }

    /**
     * 取消文章点赞
     */
    public function cancelLike()
    {
        $userId = $this->getUserId();

        $articleId = $this->request->param('id', 0, 'intval');

        $userLikeModel = new UserLikeModel();


        $findLikeCount = $userLikeModel->where([
            'user_id'   => $userId,
            'object_id' => $articleId
        ])->where('table_name', 'portal_post_like')->count();

        if (empty($findLikeCount)) {
            $this->error("您还未表明利好态度");            
        } else {
            $article = $this->postModel->where(['id' => $articleId])->field('id,post_title,post_excerpt,more')->find();
            if (empty($article)) {
                $this->error('文章不存在！');
            }
            Db::startTrans();
            try {
                $this->postModel->where(['id' => $articleId])->setDec('post_like');
                $userLikeModel->where([
                    'user_id'   => $userId,
                    'object_id' => $articleId
                ])->delete();
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('取消利好失败！');
            }
            $likeCount = $this->postModel->where('id', $articleId)->value('post_like');
            $this->success("利好-1！", ['post_like' => $likeCount]);
        }
    }

    public function doCai()
    {
        $userId = $this->getUserId();

        $articleId = $this->request->param('id', 0, 'intval');

        $userLikeModel = new UserLikeModel();

        $findLikeCount = $userLikeModel->where([
            'user_id'   => $userId,
            'object_id' => $articleId
        ])->where('table_name', 'portal_post_unlike')->count();

        if (empty($findLikeCount)) {
            $article = $this->postModel->where(['id' => $articleId])->field('id,post_title,post_excerpt,more')->find();
            if (empty($article)) {
                $this->error('文章不存在！');
            }

            Db::startTrans();
            try {
                $this->postModel->where(['id' => $articleId])->setInc('post_cai');
                $thumbnail = empty($article['more']['thumbnail']) ? '' : $article['more']['thumbnail'];
                $userLikeModel->insert([
                    'user_id'     => $userId,
                    'object_id'   => $articleId,
                    'table_name'  => 'portal_post_unlike',
                    'title'       => $article['post_title'],
                    'thumbnail'   => $thumbnail,
                    'description' => $article['post_excerpt'],
                    'url'         => json_encode(['action' => 'portal/Article/index', 'param' => ['id' => $articleId, 'cid' => $article['categories'][0]['id']]]),
                    'create_time' => time()
                ]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();

                $this->error('发表利空失败！');
            }

            $likeCount = $this->postModel->where('id', $articleId)->value('post_cai');
            $this->success("利空+1！", ['post_cai' => $likeCount]);
        } else {
            $this->error("您已表明利空态度了O(∩_∩)O");
        }
    }

    /**
     * 取消文章点赞
     */
    public function cancelCai()
    {

        $userId = $this->getUserId();

        $articleId = $this->request->param('id', 0, 'intval');

        $userLikeModel = new UserLikeModel();


        $findLikeCount = $userLikeModel->where([
            'user_id'   => $userId,
            'object_id' => $articleId
        ])->where('table_name', 'portal_post_unlike')->count();

        if (empty($findLikeCount)) {
            $this->error("您还未表明利空态度");            
        } else {
            $article = $this->postModel->where(['id' => $articleId])->field('id,post_title,post_excerpt,more')->find();
            if (empty($article)) {
                $this->error('文章不存在！');
            }
            Db::startTrans();
            try {
                $this->postModel->where(['id' => $articleId])->setDec('post_cai');
                $userLikeModel->where([
                    'user_id'   => $userId,
                    'object_id' => $articleId
                ])->delete();
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('取消利空失败！');
            }
            $likeCount = $this->postModel->where('id', $articleId)->value('post_cai');
            $this->success("利空-1！", ['post_cai' => $likeCount]);
        }
    }


    public function getMore(){
        $validate = new Validate([
            'id'          => 'require',
            ]);
        $validate->message([
            'id.require'  => '请输入公告id!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $where['post_type']=1;
        $where['post_status']=1;
        $where['p.id']=$data['id'];
        $field="u.user_nickname,p.id,p.post_title,p.create_time,p.update_time,p.post_content,p.post_excerpt,p.thumbnail";
        $selectdata=   Db::name("portal_post")->alias('p')
                        ->join('jl_user u','u.id= p.user_id')
                        ->where($where)
                        ->field($field)
                        ->find();


        if(!empty($selectdata)){
            $selectdata['post_content'] = strip_tags(htmlspecialchars_decode($selectdata['post_content']));

            $this->success('操作成功！',$selectdata);
        }else{
            $this->error('查询数据不存在,请重试！');
        }
    }
}
