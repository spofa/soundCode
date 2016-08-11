<?php
// +----------------------------------------------------------------------
// ｜jxch168 金享财行
// +----------------------------------------------------------------------
// | Copyright (c) 2011 http://www.jxch168.com/ All rights reserved.
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------

class RoleAction extends CommonAction{
	public function index()
	{
		$condition['is_delete'] = 0;
		$this->assign("default_map",$condition);
		$access_list = $GLOBALS['access_list'];
		$i = 1;
		foreach($access_list as $key=>$val){
			$access_list[$key]['count']=$i;
			$i++;
		}
//		echo "<pre>";
//		print_r($access_list);
		$this->assign("access_list",$access_list);
		parent::index();
	}
	public function trash()
	{
		$condition['is_delete'] = 1;
		$this->assign("default_map",$condition);
		parent::index();
	}
	public function add()
	{
		//输出module与action
		$access_list = $GLOBALS['access_list'];	
		$this->assign("access_list",$access_list);
		$this->display();
	}
	public function edit() {		
		$id = intval($_REQUEST ['id']);
		$condition['is_delete'] = 0;
		$condition['id'] = $id;		
		$vo = M(MODULE_NAME)->where($condition)->find();
		$this->assign ( 'vo', $vo );
		//输出module与action
		$access_list = $GLOBALS['access_list'];
		foreach($access_list as $k=>$v)
		{
			
			if($GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."role_access where role_id = ".$vo['id']." and module = '".$k."' and node = ''")>0)
			{
				$access_list[$k]['module_auth'] = 1;  //当前模块被授权
			}
			else
			{
				$access_list[$k]['module_auth'] = 0; 
			}
			
			$node_list = $v['node'];
			foreach($node_list as $kk=>$vv)
			{	
				if($vv['module']!=""){
					$module = $vv['module'];
				}
				else{
					$module = $k;
				}
				if($GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."role_access where role_id = ".$vo['id']." and module = '".$module."' and node = '".$vv['action']."'")>0)
				{
					$node_list[$kk]['node_auth'] = 1;
				}
				else
				{
					$node_list[$kk]['node_auth'] = 0;
				}
			}
			$access_list[$k]['node'] = $node_list;
			//非模块授权时的是否全选
			$r1 = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."role_access where role_id = ".$vo['id']." and module = '".$k."' and node <> ''");
			$r2 = count($v['node']);
			if($r1==$r2&&$r2!=0)
			{
				//全选
				$access_list[$k]['check_all'] = 1;
			}
			else
			{
				$access_list[$k]['check_all'] = 0;
			}
		}
		$this->assign("access_list",$access_list);
		$this->display ();
	}
	//相关操作
	public function set_effect()
	{
		$id = intval($_REQUEST['id']);
		$ajax = intval($_REQUEST['ajax']);
		$info = M(MODULE_NAME)->where("id=".$id)->getField("name");
		$c_is_effect = M(MODULE_NAME)->where("id=".$id)->getField("is_effect");  //当前状态
		$n_is_effect = $c_is_effect == 0 ? 1 : 0; //需设置的状态
		M(MODULE_NAME)->where("id=".$id)->setField("is_effect",$n_is_effect);	
		save_log($info.l("SET_EFFECT_".$n_is_effect),1);
		$this->ajaxReturn($n_is_effect,l("SET_EFFECT_".$n_is_effect),1)	;	
	}
	public function insert() {		
		B('FilterString');
		$data = M(MODULE_NAME)->create ();
		//开始验证有效性
		$this->assign("jumpUrl",u(MODULE_NAME."/add"));
		if(!check_empty($data['name']))
		{
			$this->error(L("ROLE_NAME_EMPTY_TIP"));
		}	
		// 更新数据
		$log_info = $data['name'];
		$role_id=M(MODULE_NAME)->add($data);
		if (false !== $role_id) {
			//开始关联节点
			if(isset($_REQUEST['role_access']))
			{
				$role_access =  $_REQUEST['role_access'];
				$role = array();
				foreach($role_access as $k=>$v)
				{
					//开始提交关联
					$v = strim($v);
					if(strpos($v,",")===false){
						$item = explode("|",$v);
						
						$access_item['role_id'] = $role_id;
						$access_item['node'] = empty($item[1])?"":$item[1];
						$access_item['module'] = $item[0];
						$GLOBALS['db']->autoExecute(DB_PREFIX."role_access",$access_item,"INSERT","","SILENT");
					}
					else{
						$access_item['role_id'] = $role_id;
						$access_item['node'] = "";
						$access_item['module'] = $v;
						$GLOBALS['db']->autoExecute(DB_PREFIX."role_access",$access_item,"INSERT","","SILENT");
					}
					
				}
			}
			//成功提示
			save_log($log_info.L("INSERT_SUCCESS"),1);
			$this->success(L("INSERT_SUCCESS"));
		} else {
			//错误提示
			save_log($log_info.L("INSERT_FAILED"),0);
			$this->error(L("INSERT_FAILED"));
		}
	}
	
	public function update() {
		B('FilterString');
		$data = M(MODULE_NAME)->create ();
		$log_info = M(MODULE_NAME)->where("id=".intval($data['id']))->getField("name");
		//开始验证有效性
		$this->assign("jumpUrl",u(MODULE_NAME."/edit",array("id"=>$data['id'])));
		if(!check_empty($data['name']))
		{
			$this->error(L("ROLE_NAME_EMPTY_TIP"));
		}	
		// 更新数据
		$list=M(MODULE_NAME)->save ($data);
		if (false !== $list) {
			//成功提示
			$role_id = $data['id'];
			M("RoleAccess")->where("role_id=".$role_id)->delete();
			//开始关联节点
			
			
			$role_access = $_REQUEST['role_access'];
			foreach($role_access as $k=>$v)
			{
				//开始提交关联
				$v = strim($v);
				if(strpos($v,",")===false){
					$item = explode("|",$v);
					if(empty($item[1]))
					{					
						//模块授权
						$GLOBALS['db']->query("delete from ".DB_PREFIX."role_access where role_id = ".$role_id." and module = '".$item[0]."'");
					}
					else
					{
						//节点授权
						$GLOBALS['db']->query("delete from ".DB_PREFIX."role_access where role_id = ".$role_id." and module = '".$item[0]."' and node = '".$item[1]."'");
					}
					$access_item['role_id'] = $role_id;
					$access_item['node'] = empty($item[1])?"":$item[1];
					$access_item['module'] = $item[0];
					$GLOBALS['db']->autoExecute(DB_PREFIX."role_access",$access_item,"INSERT","","SILENT");
				}
				else{
					//模块授权
					$GLOBALS['db']->query("delete from ".DB_PREFIX."role_access where role_id = ".$role_id." and module = ''");
					$access_item['role_id'] = $role_id;
					$access_item['node'] = "";
					$access_item['module'] = $v;
					$GLOBALS['db']->autoExecute(DB_PREFIX."role_access",$access_item,"INSERT","","SILENT");
				}
				
			}
			
			save_log($log_info.L("UPDATE_SUCCESS"),1);
			$this->success(L("UPDATE_SUCCESS"));
		} else {
			//错误提示
			save_log($log_info.L("UPDATE_FAILED"),0);
			$this->error(L("UPDATE_FAILED"));
		}
	}

	public function ajaxUpdate(){
		// 获取角色
		$role_id = $_REQUEST['role_isc'];
		$role_id_arr = explode(',',$role_id);
		// 获取module和node
		$access_isc = $_REQUEST['access_isc'];
		$access_isc_arr = explode(",",$access_isc);
		foreach($access_isc_arr as $val){
			$list[] = explode("|",$val);
		}
		foreach($list as $val){
			foreach($role_id_arr as $va){
				$data['role_id'] = $va;
				$data['node'] = $val[1];
				$data['module'] = $val[0];
				$res = M("role_access")->add($data);
				if(!$res){
					ajax_return("批量授权失败！");
				}
			}
		}
		$data['response_code'] = 1;
		$data['show_err'] = "操作成功";
		ajax_return($data);
	}


	public function delete() {
		//删除指定记录
		$ajax = intval($_REQUEST['ajax']);
		$id = $_REQUEST ['id'];
		if (isset ( $id )) {
				$condition = array ('id' => array ('in', explode ( ',', $id ) ) );
				$rel_data = M(MODULE_NAME)->where($condition)->findAll();				
				foreach($rel_data as $data)
				{
					$info[] = $data['name'];	
					//开始验证分组下是否存在管理员
					if(M("Admin")->where("is_effect = 1 and is_delete = 0 and role_id=".$data['id'])->count()>0)
					{
						$this->error ($data['name'].l("EXIST_ADMIN"),$ajax);
					}
				}
				if($info) $info = implode(",",$info);
				$list = M(MODULE_NAME)->where ( $condition )->setField ( 'is_delete', 1 );
				if ($list!==false) {
					save_log($info.l("DELETE_SUCCESS"),1);
					$this->success (l("DELETE_SUCCESS"),$ajax);
				} else {
					save_log($info.l("DELETE_FAILED"),0);
					$this->error (l("DELETE_FAILED"),$ajax);
				}
			} else {
				$this->error (l("INVALID_OPERATION"),$ajax);
		}		
	}
	public function restore() {
		//删除指定记录
		$ajax = intval($_REQUEST['ajax']);
		$id = $_REQUEST ['id'];
		if (isset ( $id )) {
				$condition = array ('id' => array ('in', explode ( ',', $id ) ) );
				$rel_data = M(MODULE_NAME)->where($condition)->findAll();				
				foreach($rel_data as $data)
				{
					$info[] = $data['name'];	
				}
				if($info) $info = implode(",",$info);
				$list = M(MODULE_NAME)->where ( $condition )->setField ( 'is_delete', 0 );
				if ($list!==false) {
					save_log($info.l("RESTORE_SUCCESS"),1);
					$this->success (l("RESTORE_SUCCESS"),$ajax);
				} else {
					save_log($info.l("RESTORE_FAILED"),0);
					$this->error (l("RESTORE_FAILED"),$ajax);
				}
			} else {
				$this->error (l("INVALID_OPERATION"),$ajax);
		}		
	}
	public function foreverdelete() {
		//彻底删除指定记录
		$ajax = intval($_REQUEST['ajax']);
		$id = $_REQUEST ['id'];
		if (isset ( $id )) {
				$condition = array ('id' => array ('in', explode ( ',', $id ) ) );
				$role_access_condition = array ('role_id' => array ('in', explode ( ',', $id ) ) );
				$rel_data = M(MODULE_NAME)->where($condition)->findAll();				
				foreach($rel_data as $data)
				{
					$info[] = $data['name'];
					//开始验证分组下是否存在管理员
					if(M("Admin")->where("is_effect = 1 and is_delete = 0 and role_id=".$data['id'])->count()>0)
					{
						$this->error ($data['name'].l("EXIST_ADMIN"),$ajax);
					}	
				}
				if($info) $info = implode(",",$info);
				$list = M(MODULE_NAME)->where ( $condition )->delete();
				M("RoleAccess")->where($role_access_condition)->delete();
				M("Admin")->where($role_access_condition)->delete();
				if ($list!==false) {
					save_log($info.l("FOREVER_DELETE_SUCCESS"),1);
					$this->success (l("FOREVER_DELETE_SUCCESS"),$ajax);
				} else {
					save_log($info.l("FOREVER_DELETE_FAILED"),0);
					$this->error (l("FOREVER_DELETE_FAILED"),$ajax);
				}
			} else {
				$this->error (l("INVALID_OPERATION"),$ajax);
		}
	}

	public function get_access_list(){
		$access_list = $GLOBALS['access_list'];
		$count = $_REQUEST['count'];
		$i=1;
		$list = array();
		foreach($access_list as $key => $val){
			if($i==$count){
				$list = $val['node'];
			}
			$i++;
		}
//		echo "<pre>";
//		print_r($list);exit;
		ajax_return($list);
	}
}
?>