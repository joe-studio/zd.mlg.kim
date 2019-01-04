<?php
/**
 * Created by PhpStorm.
 * User: Vnimy
 * Date: 2017-01-19
 * Time: 11:16
 */

namespace Demand\Controller;


use Common\Controller\AdminbaseController;

class AdminTaskController extends AdminbaseController
{
    protected $_model;
    public $taskLimit;

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->_model = D('Demand/Task');
        $this->taskLimit = $this->_model->taskLimit;
        $this->assign('taskLimit', $this->taskLimit);
    }

    public function allot($id)
    {
        $this->assign('statuses', $this->_model->statuses);
        $demand = D('Demand')->getDemand($id);
        $this->assign($demand);

        $tasks = $this->_model->getTasksNoPaged("demand_id:$id;subData:supplier;");
        $this->assign('tasks', $tasks);
        $allot_ids = array();
        foreach ($tasks as $task_id => $task) {
            array_push($allot_ids, $task['supplier_id']);
        }
        $whereS['biz_status'] = 1;
        if (!empty($allot_ids)) {
            $whereS['id'] = array('NOT IN', $allot_ids);
        }
        $suppliers = D('BizMember')->field('id,biz_name')->where($whereS)->select();
        $this->assign('suppliers', $suppliers);
        $this->display();
    }

    public function allot_post()
    {
        if (IS_POST) {
            $post = I('post.');
            $demand_id = $post['demand_id'];
            $sids = $post['suppliers'];
            $count = $this->_model->where(array('demand_id' => $demand_id))->count();
            $count += count($sids);
            if($count > 0){
                D('Demand')->where(array('demand_id'=>$demand_id))->save(array('demand_status'=>2));
            }elseif($count = 0){
                D('Demand')->where(array('demand_id'=>$demand_id))->save(array('demand_status'=>0));
            }
            
            if ($count > $this->taskLimit) {
                $this->error('超过分配限额('.$this->taskLimit.')！');
            }

            if (!empty($sids)) {
                $result = $this->_model->assignTasks($demand_id, $sids);

                if ($result !== false) {
                    foreach ($result as $supplier_id => $res) {
                        if ($res['result'] !== false) {
                            $task_id = $res['result'];
                            $title = '您有新的需求任务！';
                            $href = leuu('Demand/Task/view', array('id' => $task_id));
                            $content = "来自".$task['demand_contact']['name']."的需求任务。
                            <a href=\"$href\" target=\"_blank\" class=\"btn-u btn-u-xs\">查看需求</a>";
                            D('Notify/Msg')->sendMsg($supplier_id, $title, $content, SESSION('ADMIN_ID'), 3, 2);
                        }
                    }
                    $this->success('已分配任务！', U('Demand/AdminDemand/view', array('id' => $demand_id)));
                }
            } else {
                $this->error('传入数据错误！');
            }
        }
    }

    public function delete()
    {
        if (IS_POST) {
            $id = I('post.id/d', 0);
            if ($id > 0) {
                $task = $this->_model->find($id);
                $result = $this->_model->delete($id);
                if ($result !== false) {
                    $title = '您的需求任务已被管理员删除！';
                    $content = '您有一个需求任务被管理员删除了，如有疑问，请联系管理员！';
                    D('Notify/Msg')->sendMsg($task['supplier_id'], $title, $content, SESSION('ADMIN_ID'), 3, 2);
                    $this->success('任务已删除！', U('Demand/AdminDemand/view', array('id' => $task['demand_id'])));
                } else {
                    $this->error('删除失败！');
                }
            } else {
                $this->error('传入数据失败！');
            }
        }
    }
}
