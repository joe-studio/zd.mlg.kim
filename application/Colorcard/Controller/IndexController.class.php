<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-15
 * Time: 9:22
 */

namespace Colorcard\Controller;


use Common\Controller\HomebaseController;

class IndexController extends HomebaseController
{
    protected $_model;
    protected $page_model;
    protected $item_model;
    protected $tpl_model;
    protected $tplpage_model;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->_model = D('Colorcard');
        $this->page_model = D('Colorcard/Page');
        $this->item_model = D('Colorcard/Item');
        $this->tpl_model = D('Colorcard/Tpl');
        $this->tplpage_model = D('Colorcard/TplPage');
    }

    
    function index(){
        if(sp_is_mobile() && !IS_AJAX){
            $this->display();
            exit(); 
        }
        $where = array();
        $page_size = I('request.ps',16);
        $search = I('request.search/s','');
        if(!empty($search)){
            $where['card_name'] = array('LIKE','%'.$search.'%');
        }
        
        if(sp_is_mobile()){
            $where['card_type'] = 2;
        }else{
            $where['card_type'] = 1;            
        }
        $where['card_status'] = 20;
        $where['card_trash'] = 0;
        $where['ispublic'] = 1;
        if (sp_is_user_login()) {
            $where['field'] .= 'c.*,biz.biz_name,(SELECT COUNT(rec_id) FROM '.C('DB_PREFIX')
                .'collect_goods coll where coll.goods_id=c.card_id AND user_id='.sp_get_current_userid().
                ' AND type=2) as is_collected';
        }

        $list = $this->_model->getCardsPaged($where,$page_size);
        // $supplier_ids = array();
        // foreach($list['data'] as $card_id => $card){
        //     array_push($supplier_ids, $card['vend_id']);
        // }
        // $suppliers = D('BizMember')->getMembersNoPaged(array('id'=>array('IN',$supplier_ids)));
        // foreach($list['data'] as $card_id => $card){
        //     $list['data'][$card_id]['supplier'] = $suppliers[$card['vend_id']];
        // }
        $this->assign('list', $list);
        if(sp_is_mobile() && IS_AJAX){
            $html = $this->fetch('more');
            $list['html'] = $html;
            unset($list['data']);
            $this->ajaxReturn($list);
        }else{
            $this->display();
        }
    }
    
    public function visit_verify(){
        $this->display(":Index/visit_verify");
    }
    public function verify_post(){
        $uid = sp_get_current_userid();
        $share_model=M('Share');
        $share_pass=I('post.share_pass');
        
        $result = $share_model->where(array('target_id'=>I('post.target_id'),'share_url'=>I('post.su'),'user_id'=>$uid,'target_type'=>2))->find();
        $rs = $share_model->where(array('target_id'=>I('post.target_id'),'share_url'=>I('post.su'),'user_id'=>0,'target_type'=>2))->find(); 
        $gs = $share_model->where(array('target_id'=>I('post.target_id'),'share_url'=>I('post.su'),'user_id'=>array('gt',0),'target_type'=>2))->find(); 
        if(!empty($result)){
            //指定用户
            if($share_pass === $result['share_pass']){
                $expire_time = strtotime($result['expire_time']);
                $current_time = time();
                if($expire_time >= $current_time){
                    if($result['user_id']!== $uid && $result['user_id'] !==0){
                        $this->error('你没有权限查看！');
                    }elseif($result['user_id'] ==0 ||$result['user_id'] == $uid){
                        //访问次数
                        if($result['share_num'] < $result['share_limit']){
                            //自己是供应商上就不算
                            if($_SESSION['user']['member']['id']!=$result['supplier_id']){
                                $share_model->where(array('id'=>$result['id']))->save(array('share_num'=>array('exp','share_num+1'))); 
                            }  
                            session('short_url',1);
                            $url = $result['real_url']."/su/".$result['share_url'];
                            $this->success('验证成功!', $url);
                        }elseif($result['share_num'] = $result['share_limit']){
                            $this->error('你访问的次数已达上限！');
                        }  
                    } 
                }else{
                    $this->error('访问权限已过期！');
                }
              
            }else{
                $this->error("密码错误！");
            }
        }elseif(!empty($rs)){
            //不指定用户  
            if($share_pass === $rs['share_pass']){ 
                if(!empty($rs)){
                    //访问次数+1
                    $expire_time = strtotime($rs['expire_time']);
                    $current_time = time();
                    if($expire_time >= $current_time){
                        if($rs['share_num'] < $rs['share_limit']){
                            $data['share_num'] = array('exp','share_num+1');
                            if($_SESSION['user']['member']['id']!=$rs['supplier_id']){
                                $share_model->where(array('id'=>$rs['id']))->save(array('share_num'=>array('exp','share_num+1'))); 
                            }    
                            session('short_url',1);
                            $url = $rs['real_url']."/su/".$rs['share_url'];
                            $this->success('验证成功!', $url);
                        }elseif($rs['share_num'] = $rs['share_limit']){
                            $this->error('开放访问的次数已达上限！');
                        } 
                    }else{
                        $this->error('访问权限已过期！');
                    }
                      
                }else{
                    $this->error("信息错误！");
                }
            }else{
                $this->error("密码错误！");
            }  
        }elseif(!empty($gs)){
            //没有权限
            $this->error("你没有访问权限！");
        }
    }

    public function view(){

        $show_type = I('get.show_type');
        if(sp_is_mobile() && !session('short_url')==1){
            $short_url = I('get.short_url');
            $su = I('get.su');
            if($short_url==1){
                 $this->redirect('Colorcard/Index/visit_verify',array('id'=>$_GET['id'],"su"=>$su));
            }
        }
        session('short_url',null);
        if (isset($_GET['cardsn'])) {
            $where['card_no'] = $_GET['cardsn'];
        } else {
            $where['card_id'] = $_GET['id'];
        }
        $card = $this->_model->getCard($where);
    
        if(!sp_is_mobile() && $card['card_type']==2) {
            $this->error('请在微信端访问该色卡');
        }

        if(sp_is_mobile() && $card['card_type']== 1){
            $this->error('请在电脑端访问该色卡');
        }
       
        $member = D('BizMember')->getMember($card['supplier_id']);
        $member = array(
            'biz_name'  => $member['biz_name'],
            'biz_logo'  => $member['biz_logo'],
            'code_service'  => $member['code_service'],
            'address'  => $member['contact']['contact_city_name'].$member['contact']['contact_district_name'].$member['contact']['contact_address'],
            'contact_tel' =>$member['contact']['contact_tel'],
            'contact_qq' =>$member['contact']['contact_qq'],    
        );
        $this->assign('member', $member);
        $this->assign('contact_info', $member);

        foreach ($card['pages'] as $k=>$page) {
            $tpl = $this->tplpage_model->find($page['page_tpl']);
            $this->assign('page', $page);
            $card['pages'][$k]['content'] = $this->fetch('', $tpl['pg_content']);
        }
        
        if(sp_is_user_login() && $card){
            $uid = sp_get_current_userid();
            D('History')->addHistory($_GET['id'],$uid,$card['supplier_id'],2);
        }
        // var_dump($card);
        $this->assign($card);

        if(is_array($card['frontcover'])){
            $card['frontcover']['pg_content'] = $this->fetch('', $card['frontcover']['pg_content']);
        }
        if(is_array($card['backcover'])){
            $card['backcover']['pg_content'] = $this->fetch('', $card['backcover']['pg_content']);
        }


        $this->assign($card);
        $this->assign('tpl', $card['tpl']);

        if(sp_is_mobile() && $show_type == 2){
            $this->display(":Index/dafault");
        }else{
            $this->display();
        }



    }

    function qr($code){
        $url = leuu('view',array('code'=>$code),false,true);
        qrcode($url,1);
    }
}