<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;
use Common\Controller\HomebaseController;
/**
 * 商家列表
*/
class BizController extends HomebaseController {

	protected $biz_member_model;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->biz_member_model = M("Biz_member");
        $this->fabric_model = M("Textile_fabric");
        $this->fabric_cats_model = M("Textile_fabric_cats");
        $this->fabric_prop_model = M("Textile_fabric_prop");
        $this->fabric_sku_model = M("Textile_fabric_sku");
    }

	//商家列表
	public function index() {

        $where_ands=array();
        $fields=array(
            'short_name'  => array("field"=>"short_name","operator"=>"like"),
        );
        foreach ($fields as $param =>$val){
            if (isset($_REQUEST[$param]) && !empty($_REQUEST[$param])) {
                $operator=$val['operator'];
                $field   =$val['field'];
                $get=$_REQUEST[$param];
                $_REQUEST[$param]=$get;
                if($operator=="like"){
                    $get="%$get%";
                }
                array_push($where_ands, "$field $operator '$get'" );
                
            }
        }
        array_push($where_ands, "authenticated=1 and type=1 and status=1");
        $where= join(" and ", $where_ands);

        $count = $this->biz_member_model->where($where)->count();// 查询满足要求的总记录数
        $Page  = new \Think\Page($count,10);// 实例化分页类 
        //定制分页样式
        $Page->rollPage = 5;//数码页数量
        $Page->lastSuffix = false;// 最后一页不显示总页数
        $Page->setConfig('prev','上一页');
        $Page->setConfig('next','下一页');
        $Page->setConfig('first','首页');
        $Page->setConfig('last','尾页');          
        $Page->setConfig('theme',"%FIRST% %UP_PAGE%  %LINK_PAGE% %DOWN_PAGE% %END%");//自定义分页的位置
        $show = bootstrap_page_style($Page->show());// 调用bootstrap_page_style 函数输出定制分页样式，

        $join1 = 'LEFT JOIN __BIZ_CONTACT__ b ON b.id = a.id';
        $join2 = 'LEFT JOIN __BIZ_AUTH__ c ON c.id = a.id';
        $join3 = 'LEFT JOIN __AREAS__ e ON e.id = a.company_province';
        $join4 = 'LEFT JOIN __AREAS__ f ON f.id = a.company_city';
        $join5 = 'LEFT JOIN __AREAS__ g ON g.id = a.company_district';
        $join6 = 'LEFT JOIN __AREAS__ h ON h.id = b.contact_province';
        $join7 = 'LEFT JOIN __AREAS__ i ON i.id = b.contact_city';
        $join8 = 'LEFT JOIN __AREAS__ j ON j.id = b.contact_district';
        $join9 = 'LEFT JOIN __AREAS__ k ON k.id = c.auth_bank_province';
        $join10 = 'LEFT JOIN __AREAS__ l ON l.id = c.auth_bank_city';
        $join11 = 'LEFT JOIN __AREAS__ m ON m.id = c.auth_bank_district';

        $biz_list=$this->biz_member_model
        ->alias('a')
        ->field('a.*,b.*,c.*,e.name as company_province,f.name as company_city,g.name as company_district,h.name as contact_province,i.name as contact_city,j.name as contact_district,k.name as auth_bank_province,l.name as auth_bank_city,m.name as auth_bank_district')
        ->join($join1)->join($join2)->join($join3)->join($join4)
        ->join($join5)->join($join6)->join($join7)->join($join8)
        ->join($join9)->join($join10)->join($join11)
        ->where($where)
        ->order('a.created_at desc')
        ->limit($Page->firstRow.','.$Page->listRows)
        ->select();

        $this->assign('biz_list', $biz_list);
        $this->assign('page',$show);
        $this->assign("formget",$_REQUEST);

    	$this->display(":stores-list");
	}

    /**
     *商家详情
    */
	public function biz_single(){
        $cid = isset($_GET['cid'])?$_GET['cid']:12;
        $biz_id = intval($_GET['id']);

        /*一条供应商信息*/
        $join1 = 'LEFT JOIN __BIZ_CONTACT__ b ON b.id = a.id';
        $join2 = 'LEFT JOIN __BIZ_AUTH__ c ON c.id = a.id';
        $join3 = 'LEFT JOIN __AREAS__ e ON e.id = a.company_province';
        $join4 = 'LEFT JOIN __AREAS__ f ON f.id = a.company_city';
        $join5 = 'LEFT JOIN __AREAS__ g ON g.id = a.company_district';
        $join6 = 'LEFT JOIN __AREAS__ h ON h.id = b.contact_province';
        $join7 = 'LEFT JOIN __AREAS__ i ON i.id = b.contact_city';
        $join8 = 'LEFT JOIN __AREAS__ j ON j.id = b.contact_district';
        $join9 = 'LEFT JOIN __AREAS__ k ON k.id = c.auth_bank_province';
        $join10 = 'LEFT JOIN __AREAS__ l ON l.id = c.auth_bank_city';
        $join11 = 'LEFT JOIN __AREAS__ m ON m.id = c.auth_bank_district';

        $biz_info=$this->biz_member_model
        ->alias('a')
        ->field('a.*,b.*,c.*,e.name as company_province,f.name as company_city,g.name as company_district,h.name as contact_province,i.name as contact_city,j.name as contact_district,k.name as auth_bank_province,l.name as auth_bank_city,m.name as auth_bank_district')
        ->join($join1)->join($join2)->join($join3)->join($join4)  
        ->join($join5)->join($join6)->join($join7)->join($join8)
        ->join($join9)->join($join10)->join($join11)
        ->where(array('authenticated'=>1,'type'=>1,'a.status'=>1,'a.id'=>$biz_id))
        ->order('a.id desc')
        ->find();

        $this->assign('biz_info', $biz_info);

        /*供应商有的面料*/
        $where_ands=array();
        $fields=array(
            'name'  => array("field"=>"name","operator"=>"like"),
        );
        foreach ($fields as $param =>$val){
            if (isset($_REQUEST[$param]) && !empty($_REQUEST[$param])) {
                $operator=$val['operator'];
                $field   =$val['field'];
                $get=$_REQUEST[$param];
                $_REQUEST[$param]=$get;
                if($operator=="like"){
                    $get="%$get%";
                }
                array_push($where_ands, "$field $operator '$get'" );
                
            }
        }
        array_push($where_ands, "vend_id=$biz_id");

        array_push($where_ands, "cid=$cid");

        $where= join(" and ", $where_ands);
        /*查询商家有的面料，及分页处理*/
        $join15 = 'LEFT JOIN __TEXTILE_FABRIC_CATS__ category ON category.id = tf.cid';
        $count = $this->fabric_model->alias('tf')->join($join15)->where($where)->count();// 查询满足要求的总记录数
        $Page  = new \Think\Page($count,9);// 实例化分页类 
        //定制分页样式
        $Page->rollPage = 5;//数码页数量
        $Page->lastSuffix = false;// 最后一页不显示总页数
        $Page->setConfig('prev','上一页');
        $Page->setConfig('next','下一页');
        $Page->setConfig('first','首页');
        $Page->setConfig('last','尾页');          
        $Page->setConfig('theme',"%FIRST% %UP_PAGE%  %LINK_PAGE% %DOWN_PAGE% %END%");//自定义分页的位置
        $show = bootstrap_page_style($Page->show());// 调用bootstrap_page_style 函数输出定制分页样式，
        $textile_info=$this->fabric_model
        ->alias('tf')
        ->join($join15)
        ->where($where)
        ->order('tf.id desc')
        ->limit($Page->firstRow.','.$Page->listRows)
        ->select();
        
        $this->assign('textile_info', $textile_info);
        $this->assign('page',$show);

		$this->display(":store-single");
	}
	
    
}
