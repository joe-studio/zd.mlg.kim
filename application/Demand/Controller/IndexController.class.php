<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2016-11-26
 * Time: 9:56
 */

namespace Demand\Controller;

use Common\Controller\MemberbaseController;

class IndexController extends MemberbaseController
{
    protected $_model;

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->_model = D('Demand/Demand');
    }

    public function index()
    {
        if (sp_is_mobile() && !IS_AJAX) {
            $this->display();
            exit();
        }

        $where = array();

        if (isset($_REQUEST['status'])) {
            $_REQUEST['filter']['status'] = $_REQUEST['status'];
        }
        
        if (isset($_REQUEST['filter'])) {
            $filter = I('request.filter');
            $statuses = array_keys(D('Demand')->statuses);
            if(isset($filter['status'])){
                if($filter['status']==-1){
                    $where['demand_status'] = array('IN',$statuses);
                }else{
                    $where['demand_status'] = $filter['status'];
                }
            }
            if (!empty($filter['keywords'])) {
                $where['demand_component'] = array('LIKE','%'.$filter['keywords'].'%');
            }
            if (!empty($filter['datestart']) && !empty($filter['datefinish'])) {
                $where['demand_created'] = array('between', strtotime($filter['datestart'])
                . ',' . strtotime($filter['datefinish']));
            } elseif (!empty($filter['datestart']) && empty($filter['datefinish'])) {
                $where['demand_created'] = array('egt', strtotime($filter['datestart']));
            } elseif (empty($filter['datestart']) && !empty($filter['datefinish'])) {
                $where['demand_created'] = array('elt', strtotime($filter['datefinish']));
            }
            $this->assign('filter', $filter);
        }


        $where['demand_trash'] = 0;
        $where['user_id'] = $this->userid;
        $demands = $this->_model->getDemandsPaged($where, 10);
        $this->assign('demands', $demands);

        if (IS_AJAX) {
            $html = $this->fetch('more');
            $data['html'] = $html;
            $data['totalPages'] = $demands['totalPages'];
            $data['pageCount'] = count($demands['data']);
            $this->ajaxReturn($data);
        } else {
            $this->display();
        }
    }

    public function view($id)
    {
        $demand = $this->_model->getDemand($id);
        $this->assign($demand);
        $this->display();
    }

    public function trash()
    {
        if (IS_POST) {
            $id = I('post.id');
            $result = $this->_model->trash($id);
            if ($result !== false) {
                D('Demand/Log')->logAction($id, $this->userid);
                $this->success('需求已取消', leuu('index'));
            } else {
                $this->error('取消失败！');
            }
        }
    }

    public function accept()
    {
        if (IS_POST) {
            $task_id = I('post.id');
            $task_model = D('Demand/Task');
            $task = $task_model->getTask($task_id);
            if (empty($task)) {
                $this->error('非法操作！');
            }
            if ($task['demand_status'] != 3) {
                $this->error('面料商未完成报价！');
            }
            if ($task['user_id'] !== $this->userid) {
                $this->error('非法操作！');
            }
            if ($task['task_status'] != 1) {
                $this->error('面料商未处理！');
            }

            $data = array(
                'task_id'       => $task_id,
                'task_accepted' => 1,
            );
            $result = $task_model->saveTask($data);
            if ($result !== false) {
                $data = array(
                    'demand_id' => $task['demand_id'],
                    'demand_status' => 4,
                );
                $result = $this->_model->saveDemand($data);

                if ($result) {
                    D('Demand/Log')->logAction($task['demand_id'], $this->userid);
                    $title = '您的需求方案被采纳了！';
                    $content = '需求放已经采纳您的方案了，请进一步处理。';
                    D('Notify/Msg')->sendMsg($task['supplier_id'], $title, $content);
                    $this->success('', leuu('view', array('id'=>$task['demand_id'])));
                } else {
                    $this->error($this->_model->getError());
                }
            }
        }
    }
}
