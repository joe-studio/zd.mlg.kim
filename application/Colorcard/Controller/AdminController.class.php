<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-07
 * Time: 12:22
 */

namespace Colorcard\Controller;


use Common\Controller\AdminbaseController;
use Tf\Service\TfUnionService;

class AdminController extends AdminbaseController
{
    protected $cards_model;
    protected $_model;
    protected $page_model;
    protected $item_model;
    protected $tpl_model;
    protected $tplpage_model;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->cards_model = D('Colorcard', 'Logic');
        $this->_model = D('Colorcard');
        $this->page_model = D('Colorcard/Page');
        $this->item_model = D('Colorcard/Item');
        $this->tpl_model = D('Colorcard/Tpl');
        $this->tplpage_model = D('Colorcard/TplPage');
        $settings = $this->cards_model->get_settings();
        $this->assign('settings', $settings);
        $this->tfUnionService = new TfUnionService();
    }

    function index(){
        $where = array();
        if(isset($_GET['status'])){
            $where['card_status'] = $_GET['status'];
        }
        // $where['card_type'] = array('eq',1);
        $where['card_trash'] = 0;
        $cards = $this->_model->getCardsPaged($where);
        $this->assign('cards', $cards);
        $this->display();
    }

    
    function tpl_ajax(){
        $tpl_type = $_GET['tpl_type'];
        $data['data'] = M('ColorcardTpl')->where("tpl_type=$tpl_type")->select();
        $this-> ajaxReturn($data);
    }

    function pg_ajax(){
        $tpl_id = $_GET['tpl_id'];
        $data['data'] = M('ColorcardTplPage')->where(array('tpl_id'=>$tpl_id,'pg_type'=>array('in',"1,3")))->select();
        $this-> ajaxReturn($data);
    }

    public function newcard(){
        if (IS_POST) {
            $tpl_id = I('post.tpl');
            $supplier_id = I('post.supplier');
            $card_type = I('post.card_type');
            $card_rid = I('post.card_rid');
            
            if(empty($card_type)){
                $this->error('请选择色卡类型！');
            }
            if (empty($tpl_id)) {
                $this->error('请先选择模板！');
            }

            $tpl = $this->tpl_model->find($tpl_id);

            if (!empty($tpl) && $supplier_id > 0) {
                $data = array(
                    'supplier_id'   => $supplier_id,
                    'card_rid'   => $card_rid,
                    'card_status'   => 0,
                    'card_type'   => $card_type,
                    'card_tpl'      => $tpl_id,
                    'frontcover'    => $tpl['tpl_frontcover'],
                    'backcover'     => $tpl['tpl_backcover'],
                    'bgmusic'     => $tpl['tpl_bgmusic'],
                    'create_date'   => date('Y-m-d H:i:s'),
                );
                $result = $this->_model->create($data);
                if ($result !== false) {
                    $result = $this->_model->add();
                    if ($result !== false) {
                        $this->success('已生成色卡！', leuu('Colorcard/Admin/edit', array('id' => $result)));
                    } else {
                        $this->error($this->getDbError());
                    }
                } else {
                    $this->error($this->_model->getError());
                }
            } else {
                $this->error('传入数据错误！');
            }
        }
    }

    function edit(){
        $card = $this->_model->getCard(I('get.id'));
        if($card['card_status'] >= 20){
            $this->error(L('ALERT_IS_PUBLISHED'));
        }elseif($card['card_status'] >= 10){
            $this->error(L('ALERT_IS_CONFIRMING'));
        }
        $this->assign_selected_tf($card);

        $member = D('BizMember')->getMember($card['supplier_id']);
        $this->assign('member',$member);
        
        foreach($card['pages'] as $k=>$page){
            $card['pages'][$k]['content'] = $this->_pageContent($page,$member);
        }
        $this->assign($card);

        /* 筛选 */
        $filterCats = M('TextileFabricCats')->field('id,title as name,code')->where("pid=0")->order('listorder ASC, id ASC')->select();
        $this->assign('filterCats', $filterCats);


        if(is_array($card['frontcover'])){
            $card['frontcover']['pg_content'] = $this->fetch('', $card['frontcover']['pg_content']);
        }
        if(is_array($card['backcover'])){
            $card['backcover']['pg_content'] = $this->fetch('', $card['backcover']['pg_content']);
        }


        $this->assign($card);
        $tpl = D('Colorcard/Tpl')->getTpl($card['card_tpl']);
        $this->assign('tpl', $tpl);

        $tplPageModel = M('ColorcardTplPage');
        $frcovers = $tplPageModel->field('pg_id,pg_name,pg_thumb')->where(array('tpl_id'=>$tpl['tpl_id'],'pg_type'=>4))->select();
        $bgcovers = $tplPageModel->field('pg_id,pg_name,pg_thumb')->where(array('tpl_id'=>$tpl['tpl_id'],'pg_type'=>5))->select();

        $this->assign('frcovers',$frcovers);
        $this->assign('bgcovers',$bgcovers);
        $this->display();
    }

    function edit_post(){
        if(IS_POST){
            $post = I('post.');
            $frontSmeta = I('post.frontcoverdata');
            $backSmeta = I('post.backcoverdata');
            $post['custom_frontcover_smeta'] = json_encode($frontSmeta, 256);
            $post['custom_backcover_smeta'] = json_encode($backSmeta, 256);
            $post['frontcover'] = $post['thumb'];
            $result = $this->_model->saveCard($post);
            if($result){
                $this->success('修改成功！',leuu('edit',array('id'=>$post['card_id'])));
            }else{
                $this->error($this->_model->getError());
            }
        }
    }
    public function choose_cover(){
        if(IS_POST){
            $card_id = I('post.id/d',0);
            $pgtpl_id = I('post.tpl/d',0);
            $card = $this->_model->getCard(array(
                'cards.card_id'=>$card_id,
                'cards.card_status'=>0,
                'cards.card_trash'=>0,
            ));
            if(empty($card)){
                $this->error('操作失败！');
            }
            $pgtpl = M('ColorcardTplPage')
                ->where(array(
                    'tpl_id'=>$card['card_tpl'],
                    'pg_id'=>$pgtpl_id,
                    'pg_type'=>array('IN','4,5'),
                ))
                ->find();
            if(empty($pgtpl)){
                $this->error('没有这个封面模板！');
            }
            $this->assign($card);
            $pgtpl['pg_content'] = $this->fetch('', $pgtpl['pg_content']);
            $pgtpl['smeta'] = json_decode($pgtpl['smeta'], true);
            /*if($pgtpl['pg_type'] == 4){
                $result = $this->_model->save(array(
                    'card_id'=>$card_id,
                    'custom_frontcover'=>$pgtpl['pg_id'],
                ));
            }else{
                $result = $this->_model->save(array(
                    'card_id'=>$card_id,
                    'custom_backcover'=>$pgtpl['pg_id'],
                ));
            }
            if($result === false){
                $this->error('操作失败！');
            }*/
            $this->ajaxReturn(array(
                'status'=>1,
                'data'=>$pgtpl,
            ));
        }
    }
    
    public function addmobile_page(){
        if(IS_POST){
            $tpl_id = I('post.tpl');
            $supplier_id = I('post.supplier');
            $card_type = I('post.card_type');
            $card_rid = I('post.card_rid');

            if(empty($tpl_id)){
                $this->error('模板id不能为空！');
            }
            if(empty($supplier_id)){
                $this->error('供应商id不能为空！');
            }
            if(empty($card_type)){
                $this->error('色卡类型不能为空！');
            }
            if(empty($card_rid)){
                $this->error('父级关联id不能为空！');
            }

            $tpl = $this->tpl_model->find($tpl_id);

            if (!empty($tpl) && $supplier_id > 0) {
                $data = array(
                    'supplier_id'   => $supplier_id,
                    'card_rid'      => $card_rid,
                    'card_status'   => 0,
                    'card_type'     => $card_type,
                    'card_tpl'      => $tpl_id,
                    'frontcover'    => $tpl['tpl_frontcover'],
                    'backcover'     => $tpl['tpl_backcover'],
                    'bgmusic'       => $tpl['tpl_bgmusic'],
                    'create_date'   => date('Y-m-d H:i:s'),
                );
                $result = $this->_model->create($data);
                if ($result !== false) {
                    $result = $this->_model->add();//生成色卡
                    if ($result !== false) {
                        $page_ids = array();
                        $card_pages = $this->page_model->where(array('card_id'=>$card_rid))->select();
                        foreach($card_pages as $key=>$value){
                            array_push($page_ids, $value['page_id']);
                        }
                        if(!empty($page_ids)){

                            $all_items = D('Colorcard/Item')->where(array('page_id'=>array('IN',$page_ids)))->select();
                            $items_num = count($all_items,0);
                            if($tpl['show_type']==2){
                                $create_page_num = ceil($items_num/4);
                            }else{
                                $create_page_num = $items_num;//生成多少页
                            }   
                        }

                        //按照父级的色卡页，生成新的色卡也
                        if($tpl['show_type']==2){
                            $tpl_page = D('TplPage')->where(array('tpl_id'=>$tpl_id,'pg_type'=>1,'pg_status'=>1,'pg_item_num'=>4))->find();
                        }else{
                            $tpl_page = D('TplPage')->where(array('tpl_id'=>$tpl_id,'pg_type'=>1,'pg_status'=>1,'pg_item_num'=>1))->find();
                        }

                        $new_pageids = array();
                        if($tpl_page['pg_type'] == 1){
                            for($k = 1; $k <= $create_page_num; $k++){
                                $listorder = $this->page_model
                                    ->where(array('card_id' => $result))
                                    ->order('listorder DESC')
                                    ->getField('listorder');
                                $listorder++;
                                //初始化页
                                $data = array(
                                    'card_id'   => $result,
                                    'supplier_id'=> $supplier_id,
                                    'listorder' => $listorder,
                                    'page_tpl'  => $tpl_page['pg_id'],
                                    'page_type' => 1,
                                );
                                $rs = $this->page_model->add($data);
                                if ($rs === false) {
                                    $this->error($this->page_model->getDbError());  
                                }
                                array_push($new_pageids,$rs);
                                for ($i = 1; $i <= $tpl_page['pg_item_num']; $i++) {
                                    $item = array(
                                        'page_id'   => $rs,
                                        'tf_id'     => 0,
                                        'item_fabrid'=> '',
                                        'listorder' => $i,
                                    );
                                $item_rs = $this->item_model->saveItem($item);
                                }
                            }
                        
                            if(!empty($new_pageids)){
                                $new_items = D('Colorcard/Item')->where(array('page_id'=>array('IN',$new_pageids)))->select();
                                foreach($new_items as $key=>$value){
                                    foreach($all_items as $k=>$v){
                                        if($key==$k){
                                            $save_item = array(
                                                'item_id' => $value['item_id'],
                                                'tf_id'   => $v['tf_id'],
                                                'item_fabric' => $v['item_fabric'],
                                            );
                                            $this->item_model->save($save_item);
                                        }
                                        
                                    }  
                                }
                            }
                                $this->success('已生成色卡页！', leuu('Colorcard/Admin/edit', array('id' => $result)));  
                                
                        }else{
                            $this->error('生成色卡页失败！');
                        }
                    }else {
                        $this->error($this->getDbError());
                    }
                }else {
                    $this->error($this->_model->getError());
                }
            }else {
                $this->error('传入数据错误！');
            } 
        }
    }

    public function addweb_page(){
        if(IS_POST){
            $tpl_id = I('post.tpl_id');
            $pg_id = I('post.pg_id');
            $supplier_id = I('post.supplier');
            $card_type = I('post.card_type');
            $card_rid = I('post.card_rid');

            if(empty($tpl_id)){
                $this->error('模板id不能为空！');
            }
            if(empty($pg_id)){
                $this->error('模板页id不能为空！');
            }
            if(empty($supplier_id)){
                $this->error('供应商id不能为空！');
            }
            if(empty($card_type)){
                $this->error('色卡类型不能为空！');
            }
            if(empty($card_rid)){
                $this->error('父级关联id不能为空！');
            }

            $tpl = $this->tpl_model->find($tpl_id);

            if (!empty($tpl) && $supplier_id > 0) {
                $data = array(
                    'supplier_id'   => $supplier_id,
                    'card_rid'      => 0,
                    'card_status'   => 0,
                    'card_type'     => $card_type,
                    'card_tpl'      => $tpl_id,
                    'frontcover'    => $tpl['tpl_frontcover'],
                    'backcover'     => $tpl['tpl_backcover'],
                    'bgmusic'       => $tpl['tpl_bgmusic'],
                    'create_date'   => date('Y-m-d H:i:s'),
                );
                $result = $this->_model->create($data);
                if ($result !== false) {
                    $result = $this->_model->add();//生成色卡
                    
                    if ($result !== false) {
                        
                        $page_ids = array();
                        $card_pages = $this->page_model->where(array('card_id'=>$card_rid))->select();
                        foreach($card_pages as $key=>$value){
                            array_push($page_ids, $value['page_id']);
                        }
                        if(!empty($page_ids)){

                            $all_items = D('Colorcard/Item')->where(array('page_id'=>array('IN',$page_ids)))->select();
                            $items_num = count($all_items,0);
                            
                            $tpl_page = D('TplPage')->where(array('pg_id'=>$pg_id,'tpl_id'=>$tpl_id,'pg_status'=>1))->find();

                            if($tpl_page){
                                //依据面料个数生成多少页
                                $create_page_num = ceil($items_num/$tpl_page['pg_item_num']);
                            }
                        }

                        //按照父级的色卡页，生成新的色卡页   
                        $new_pageids = array();
                        if(!empty($tpl_page)){
                            for($k = 1; $k <= $create_page_num; $k++){
                                $listorder = $this->page_model
                                    ->where(array('card_id' => $result))
                                    ->order('listorder DESC')
                                    ->getField('listorder');
                                $listorder++;
                                //初始化页
                                $data = array(
                                    'card_id'   => $result,
                                    'supplier_id'=> $supplier_id,
                                    'listorder' => $listorder,
                                    'page_tpl'  => $tpl_page['pg_id'],
                                    'page_type' => 1,
                                );
                                $rs = $this->page_model->add($data);
                                if ($rs === false) {
                                    $this->error($this->page_model->getDbError());  
                                }
                                array_push($new_pageids,$rs);
                                for ($i = 1; $i <= $tpl_page['pg_item_num']; $i++) {
                                    $item = array(
                                        'page_id'   => $rs,
                                        'tf_id'     => 0,
                                        'item_fabrid'=> '',
                                        'listorder' => $i,
                                    );
                                $item_rs = $this->item_model->saveItem($item);
                                }
                            }
                            
                            if(!empty($new_pageids)){
                                $new_items = D('Colorcard/Item')->where(array('page_id'=>array('IN',$new_pageids)))->select();
                                foreach($new_items as $key=>$value){
                                    foreach($all_items as $k=>$v){
                                        if($key==$k){
                                            $save_item = array(
                                                'item_id' => $value['item_id'],
                                                'tf_id'   => $v['tf_id'],
                                                'item_fabric' => $v['item_fabric'],
                                            );
                                            $this->item_model->save($save_item);
                                        }
                                        
                                    }  
                                }
                            }
                            
                                $this->_model->save(array('card_id'=>$card_rid,'card_rid'=>$result));//写入关联id
                                $this->success('已生成色卡页！', leuu('Colorcard/Admin/edit', array('id' => $result)));  
                                
                        }else{
                            $this->error('生成色卡页失败！');
                        }
                    }else {
                        $this->error($this->getDbError());
                    }
                }else {
                    $this->error($this->_model->getError());
                }
            }else {
                $this->error('传入数据错误！');
            } 
        }
    }

    public function add_page(){
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $pgtpl_id = I('post.tpl/d', 0);
            if ($id == 0 || $pgtpl_id == 0) {
                $this->error('传入数据出错！');
            }
            if(!$this->_model->checkTfLimit($id, $pgtpl_id)){
                $this->error('本次增加的页已超出了该色卡的面料数量限制');
            }

            $card = $this->_model->find($id);

            $tpl = D('TplPage')->find($pgtpl_id);
            if (!empty($tpl)) {
                $listorder = $this->page_model
                    ->where(array('card_id' => $id))
                    ->order('listorder DESC')
                    ->getField('listorder');
                $listorder++;

                //初始化页
                $data = array(
                    'card_id'   => $id,
                    'supplier_id'=> $card['supplier_id'],
                    'listorder' => $listorder,
                    'page_tpl'  => $pgtpl_id,
                    'page_type' => $tpl['pg_type'],
                );
                $result = $this->page_model->add($data);
                if ($result === false) {
                    $this->error($this->page_model->getDbError());
                }
                $page_id = $result;

                //初始化页项
                if($tpl['pg_type'] == 1||$tpl['pg_type'] == 3){

                    for ($i = 1; $i <= $tpl['pg_item_num']; $i++) {
                        $item = array(
                            'page_id'   => $page_id,
                            'tf_id'     => 0,
                            'item_fabrid'=> '',
                            'listorder' => $i,
                        );
                        $result = $this->item_model->saveItem($item);
                        $item['item_id'] = $result;
                        if ($result !== false) {
                            $data['items'][$result] = $item;
                        }
                    }

                    $data['page_id'] = $page_id;
                    $this->assign('page', $data);

                    $member = D('BizMember')->getMember($card['supplier_id']);
                    $member = array(
                        'biz_name'  => $member['biz_name'],
                        'biz_logo'  => $member['biz_logo'],
                        'code_service'  => $member['code_service'],
                        'address'  => $member['contact']['contact_city_name'].$member['contact']['contact_district_name'].$member['contact']['contact_address'],
                        'contact_tel' =>$member['contact']['contact_tel'],
                        'contact_qq' =>$member['contact']['contact_qq'],    
                    );
                    $this->assign('member',$member);

                    $content = $this->fetch('', $tpl['pg_content']);
                    $this->assign('content', $content);
                    $html = $this->fetch('tpl_for_edit');
                }else{
                    $data['page_id'] = $page_id;
                    $this->assign('page', $data);
                    $content = $tpl['pg_content'] ? $this->fetch('', $tpl['pg_content']):'';
                    $this->assign('content', $content);
                    $html = $this->fetch('tpl_for_edit');
                }

                $this->ajaxReturn(array(
                    'status'    => 1,
                    'data'      => $data,
                    'html'      => $html,
                ));
            } else {
                $this->error('找不到模板！');
            }
        }
    }

    public function remove_page()
    {
        if (IS_POST) {
            $page_id = I('post.id/d', 0);
            if ($page_id == 0) {
                $this->error('传入数据错误！');
            }
            D('ColorcardItem')->where(array('page_id' => $page_id))->delete();
            $result = $this->page_model
                ->where(array('page_id' => $page_id))
                ->delete();

            if ($result !== false) {
                $this->success('已删除分页');
            } else {
                $this->error($this->page_model->getDbError());
            }
        }
    }

    public function listorder()
    {
        if (IS_POST) {
            $listorders = I('post.listorders');
            if (!is_array($listorders)) {
                $this->error('传入数据错误！');
            }
            foreach ($listorders as $row) {
                $where['page_id'] = $row['id'];
                $result = $this->page_model->where($where)->setField('listorder', $row['value']);
            }
            $this->ajaxReturn(array(
                'status'    => 1,
            ));
        }
    }

    public function saveitem()
    {
        if (IS_POST) {
            $tf_id = I('post.tf_id/d', 0);
            $item_id = I('post.item_id/d', 0);
            if ($item_id == 0) {
                $this->error('传入数据错误！');
            }

            $where['item_id'] = $item_id;
            //验证修改的item是否合法
            $page = $this->item_model
                ->alias('item')
                ->join('__COLORCARD_PAGE__ page ON page.page_id=item.page_id')
                ->where($where)
                ->find();
            if (empty($page)) {
                $this->error('传入数据错误！');
            }

            //面料项 更改
            if ($page['page_type'] == 1 || $page['page_type'] == 3) {
                if ($tf_id == 0) {
                    $data = array(
                        'item_id'       => $item_id,
                        'tf_id'         => 0,
                        'item_fabric'   => '',
                    );
                } else {
                    $fabric = D('Tf/Tf')->getTf($tf_id, 'cat');
                    if (empty($fabric)) {
                        $this->error('错误的面料信息！');
                    } else {
                        $tf = array(
                            'id'    => $fabric['id'],
                            'cid'   => $fabric['cid'],
                            'code'=> $fabric['code'],
                            'name'  => $fabric['name'],
                            'img'   => $fabric['img'],
                            'spec'  => $fabric['spec'],
                            'width' => $fabric['width'],
                            'weight'=> $fabric['weight'],
                            'material'  => $fabric['material'],
                            'component' => $fabric['component'],
                            'function'  => $fabric['function'],
                            'purpose'   => $fabric['purpose'],
                        );
                        $data = array(
                            'item_id'       => $item_id,
                            'tf_id'         => $tf_id,
                            'item_fabric'   => json_encode($tf, 256),
                        );
                    }
                }
            }

            //保存到数据库
            if (isset($data)) {
                $result = $this->item_model->saveItem($data);
                if ($result !== false) {
                    $this->ajaxReturn(array(
                        'status'    => 1,
                        'item'      => $data
                    ));
                } else {
                    $this->error($this->item_model->getError());
                }
            }
        } else {
            $this->error('传入数据错误！');
        }
    }

    public function change_cover()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $url = I('post.url');
            $type = I('post.type');
            $field = $type == 'front' ? 'frontcover':'backcover';
            $where['card_id'] = $id;
            $result = $this->_model
                ->where($where)
                ->setField($field, $url);
            if ($result !== false) {
                $this->success('背景图片已更新');
            } else {
                $this->error($this->_model->getDbError());
            }
        }
    }

    public function change_pagebg()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            $url = I('post.url');
            $where['page_id'] = $id;
            $field = 'page_back_bgurl';
            if(isset($_GET['front']) && $_GET['front'] == 1){
                $field = 'page_front_bgurl';
            }
            $result = $this->page_model
                ->where($where)
                ->setField($field, $url);
            if ($result !== false) {
                $this->success('背景图片已更新');
            } else {
                $this->error($this->page_model->getDbError());
            }
        }
    }

    //电脑板预览
    public function preview()
    {
        if (isset($_GET['code'])) {
            $where['card_no'] = $_GET['code'];
        } else {
            $where['card_id'] = $_GET['id'];
        }
        $card = $this->_model->getCard($where);


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


        foreach ($card['pages'] as $k => $page) {
            $tpl = $this->tplpage_model->find($page['page_tpl']);
            $this->assign('page', $page);
            $card['pages'][$k]['content'] = $this->fetch('', $tpl['pg_content']);
        }
        $this->assign($card);
        if(is_array($card['frontcover'])){
            $card['frontcover']['pg_content'] = $this->fetch('', $card['frontcover']['pg_content']);
        }
        if(is_array($card['backcover'])){
            $card['backcover']['pg_content'] = $this->fetch('', $card['backcover']['pg_content']);
        }


        $this->assign($card);
        $this->assign('tpl', $card['tpl']);
        $this->display();
    }

    //手机版预览
    public function view()
    {
        $show_type = I('get.show_type');
        if (isset($_GET['code'])) {
            $where['card_no'] = $_GET['code'];
        } else {
            $where['card_id'] = $_GET['id'];
        }
        $card = $this->_model->getCard($where);
        
        $member = D('BizMember')->getMember($card['supplier_id']);
        $member = array(
            'biz_name'  => $member['biz_name'],
            'biz_logo'  => $member['biz_logo'],
            'code_service'  => $member['code_service'],
            'address'  => $member['contact']['contact_city_name'].$member['contact']['contact_district_name'].$member['contact']['contact_address'],
            'contact_tel' =>$member['contact']['contact_tel'],
            'contact_qq' =>$member['contact']['contact_qq'],    
        );

        $this->assign('contact_info', $member);

        foreach ($card['pages'] as $k => $page) {
            $tpl = $this->tplpage_model->find($page['page_tpl']);
            $this->assign('page', $page);
            $card['pages'][$k]['content'] = $this->fetch('', $tpl['pg_content']);
        }
        $this->assign($card);

        if(is_array($card['frontcover'])){
            $card['frontcover']['pg_content'] = $this->fetch('', $card['frontcover']['pg_content']);
        }
        if(is_array($card['backcover'])){
            $card['backcover']['pg_content'] = $this->fetch('', $card['backcover']['pg_content']);
        }


        $this->assign($card);
        $this->assign('tpl', $card['tpl']); 
        if($show_type == 2){
            $this->display(":Admin/dafault");
        }else{
            $this->display();
        }
    }

    private function _pageContent($page,$member){
        $tpl = $this->tplpage_model->find($page['page_tpl']);
        $this->assign('page', $page);
        // var_dump($member);
        
        $member = array(
            'biz_name'  => $member['biz_name'],
            'biz_logo'  => $member['biz_logo'],
            'code_service'  => $member['code_service'],
            'address'  => $member['contact']['contact_city_name'].$member['contact']['contact_district_name'].$member['contact']['contact_address'],
            'contact_tel' =>$member['contact']['contact_tel'],
            'contact_qq' =>$member['contact']['contact_qq'],    
        );
        $this->assign('member', $member);
        
        $content = $this->fetch('', $tpl['pg_content']);

        $this->assign('content', $content);
        $html = $this->fetch('tpl_for_edit');
        return $html;
    }

    function qrcode(){
        qrcode(U('index',array(),false,true));
    }


    public function ajax_tf_list($card_id){
        $supplier_id = $this->_model->where(array('card_id'=>$card_id))->getField('supplier_id');
        $itemModel = D('Colorcard/Item');
        $item_tf_ids = $itemModel
            ->alias('item')
            ->join('__COLORCARD_PAGE__ page ON page.page_id=item.page_id')
            ->join('__COLORCARD__ card ON card.card_id=page.card_id')
            ->where("card.card_id=$card_id AND item.tf_id > 0")
            ->getField("CONCAT(tf_id,'_',tf_source)", true);

        $where['supplier_id'] = $supplier_id;
        $where['status'] = 1;
        if(!empty($item_tf_ids)){
            $where["CONCAT(id,'_',source)"] = array('NOT IN', $item_tf_ids);
            $result['notin'] = $itemModel->getLastSql();
        }


        if(isset($_GET['ccode'])){
            $ccode = I('get.ccode');
            $codes = getCatSubCodes($ccode);
            //$result['codes'] = $codes;
            if(!empty($codes)){
                $where['cat_code'] = array('IN', $codes);
            }
        }
        if(isset($_GET['ncode'])){
            $ncode = I('get.ncode');
            $where['name_code'] = $ncode;
        }

        $result['data'] = $this->tfUnionService->unionModel
            ->field('id,name,code,tf_code,img,spec,material,component,source')
            ->where($where)->select();

        foreach($result['data'] as $k => $v){
            $img = json_decode($v['img'], true);
            unset($result['data'][$k]['img']);
            $result['data'][$k]['thumb'] = get_thumb_url($img['thumb'], 300);
        }

        $this->ajaxReturn($result);
    }

    public function tf_list($card_id)
    {
        $supplier_id = $this->_model->where(array('card_id'=>$card_id))->getField('supplier_id');
        $item_tf_ids = D('Colorcard/Item')
            ->alias('item')
            ->join('__COLORCARD_PAGE__ page ON page.page_id=item.page_id')
            ->join('__COLORCARD__ card ON card.card_id=page.card_id')
            ->where("card.card_id=$card_id AND item.tf_id > 0")
            ->getField('item.tf_id', true);

        $fabrics = $this->tfUnionService->getRowsNoPaged("supplier_id:{$supplier_id};");
        $unselected_fabrics = array();
        $selected_fabrics = array();
        foreach ($fabrics as $tf_id => $tf) {
            if (in_array($tf_id, $item_tf_ids)) {
                $selected_fabrics[$tf_id] = $tf;
            } else {
                $unselected_fabrics[$tf_id] = $tf;
            }
        }

        $this->assign('unselected_fabrics',$unselected_fabrics);
        $this->assign('selected_fabrics',$selected_fabrics);
    }

    function assign_selected_tf($card){
        $unselected_tfids = array();
        foreach($card['pages'] as $page){
            foreach($page['items'] as $item){
                if($item['tf_id']){
                    array_push($unselected_tfids, $item['tf_id'].'_'.$item['tf_source']);
                }
            }
        }
        $fabrics = $this->tfUnionService->getRowsNoPaged("supplier_id:{$card['supplier_id']};");
        $unselected_fabrics = array();
        $selected_fabrics = array();
        foreach($fabrics as $k=>$v){
            if(in_array($v['id'].'_'.$v['source'], $unselected_tfids)){
                $selected_fabrics[$v['id']] = $v;
            }else{
                $unselected_fabrics[$v['id']] = $v;
            }
        }
        $this->assign('unselected_fabrics',$unselected_fabrics);
        $this->assign('selected_fabrics',$selected_fabrics);
    }

    function page_tpl(){
        $tplName = I('get.tpl');
        $this->assign('member',$this->member);
        $this->display('pagetpl/'.$tplName);
    }

    function pageContent($page){
        $this->assign('page',$page);
        return $this->fetch('pagetpl/'.$page['page_tpl']);
    }

    function send_confirm_msg(){
        if(IS_POST){
            $id = I('request.id');
            $card = $this->cards_model->getCard(I('request.id'));
            $uid = $card['supplier_id'];
            $title = L('MSG_CONFIRM_TITLE', array('name'=>$card['card_name']));
            $url = leuu('BmCard/view', array('id'=>$card['card_id']), false, true);
            $content = L('MSG_CONFIRM_CONTENT', array('url'=>$url,'name'=>$card['card_name']));
            $result = D('Notify/Msg')->sendMsg($uid,$title,$content);
            if($result['status']){
                //设置正在定稿
                $this->cards_model->setConfirming($id);
                D('Log')->logAction($id);
                $this->success('已发送通知！');
            }else{
                $this->error($result['error']);
            }
        }
    }

    /*
     * 设定
     */
    public function setting(){
        $_model = M('Options');
        $option_name = 'colorcard_settings';
        $option_where = array('option_name'=>$option_name);
        if(IS_POST){
            if($_POST['key'] != $option_name) exit();
            F('colorcard_settings', null);  //清除缓存
            $_POST['settings']['frontcoverurl'] = sp_asset_relative_url($_POST['settings']['frontcoverurl']);
            $_POST['settings']['backcoverurl'] = sp_asset_relative_url($_POST['settings']['backcoverurl']);
            $_POST['settings']['bgpm3'] = sp_asset_relative_url($_POST['settings']['bgpm3']);
            $option_value = json_encode(I('post.settings'));

            $count = $_model->where($option_where)->count();
            if($count > 0){
                $r = $_model->where($option_where)->setField('option_value', $option_value);
            }else{
                $post['option_name'] = $option_name;
                $post['option_value'] = $option_value;
                $r = $_model->add($post);
            }

            if ($r!==false) {
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
        }else{
            $options = $_model->where($option_where)->getField('option_value');
            $options = json_decode($options, true);

            $this->assign('options',$options);
            $this->display();
        }
    }

    //定稿实体色卡
    function finalize(){
        if(IS_POST){
            $id = I('post.id');
            $card_type = I('get.card_type');
            $show_type = I('get.show_type');
            $card = $this->_model->find($id);
            if(empty($card)){
                $this->error('传入数据错误');
            }
            $result = $this->cards_model->setConfirmed($id);
            if($result !== false){
                D('Log')->logAction($id);
                if($card_type ==2){
                    $this->success('操作成功！', leuu('view',array('id'=>$id,'show_type'=>$show_type)));
                }else{
                    $this->success('操作成功！', leuu('preview',array('id'=>$id)));
                }

            }else{
                $this->error('操作失败！');
            }
        }
    }
    function delete() {
        if(IS_POST){
            $id = I('post.id');
            $isentity = $this->cards_model->where("card_id=$id")->getField("isentity");
            if($isentity){
                $this->error("实体色卡不能删除");
            }
            //查找是否存在未删除对应的手机版色卡
            $mobile_card = $this->cards_model->where(array('card_rid'=>$id,'card_trash'=>0))->find();
            if($mobile_card){
                $this->error('请先删除对应的手机版色卡');
            }
            $result = $this->cards_model->where(array('card_id'=>$id))->setField('card_trash','1');
            if($result !== false){
                D('Log')->logAction($id);
                $this->success('删除成功！',leuu('Colorcard/Admin/index'));
            }else{
                $this->error('操作失败！');
            }
        }
    }
}