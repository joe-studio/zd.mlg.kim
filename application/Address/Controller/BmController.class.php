<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-26
 * Time: 10:09
 */

namespace Address\Controller;


use Common\Controller\MemberbaseController;

class BmController extends MemberbaseController
{
    protected $_model;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->_model = D('Address');
    }

    function index(){
        $uid = sp_get_current_userid();
        $list = $this->_model->getAddressNoPaged("user_id:$uid");
        $this->assign('list',$list);
        $this->display();
    }

    function add(){
        $redirect=I('request.redirect','');
        if(!empty($redirect)){
            $redirect=base64_decode($redirect);
            $redirect ? session('address_http_referer',$redirect):'';
        }
        $this->display();
    }

    function add_post(){
        if(IS_POST){
            $post = I('post.');
            $post['user_id'] = sp_get_current_userid();
            $result = $this->_model->addAddress($post);
            if($result > 0){

                $session_login_http_referer=session('address_http_referer');
                $redirect=empty($session_login_http_referer) ? U('index'):$session_login_http_referer;
                session('address_http_referer','');

                $this->success('添加成功！',$redirect);
            }else{
                $this->error($this->_model->getError());
            }
        }
    }

    function edit(){
        $redirect=I('request.redirect','');
        if(!empty($redirect)){
            $redirect=base64_decode($redirect);
            $redirect ? session('address_http_referer',$redirect):'';
        }


        $id = I('get.id');
        $uid = sp_get_current_userid();
        $where = array('ad.address_id'=>$id,'user_id'=>$uid);
        $address = $this->_model->getAddress($where);
        $this->assign($address);
        $this->assign('areas',D('Areas')->getAreasByDistrict($address['district']));
        $this->display();
    }

    function edit_post(){
        if(IS_POST){
            $post = I('post.');
            $result = $this->_model->updateAddress($post);
            if($result !== false){

                $session_login_http_referer=session('address_http_referer');
                $redirect=empty($session_login_http_referer) ? '':$session_login_http_referer;
                session('address_http_referer','');

                $this->success('保存成功！',$redirect);
            }else{
                $this->error('保存失败！'.$this->_model->getError());
            }
        }
    }

    function delete(){
        if(IS_POST){
            $id = I('post.id');
            $uid = sp_get_current_userid();
            $where = array('address_id'=>$id,'user_id'=>$uid);
            $result = $this->_model->deleteAddress($where);
            if($result){
                $this->success('删除成功！', leuu('index'));
            }else{
                $this->error('删除失败！'.$this->_model->mGetErrorByCode($result));
            }
        }
    }

    function set_default(){
        if(IS_POST){
            $uid = sp_get_current_userid();
            $address_id = I('post.id');
            if($this->_model->check_access($uid, $address_id)){
                $result = $this->_model->setDefault($address_id);
                if($result > 0){
                    $this->success('操作成功！',leuu('index'));
                }else{
                    $this->error($this->_model->getError());
                }
            }else{
                $this->error('您没有权限操作该地址！');
            }
        }
    }

}