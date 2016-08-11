<?php
// +----------------------------------------------------------------------
// | jxch168 金享财行
// +----------------------------------------------------------------------
// | Copyright (c) 2011 http://www.jxch168.com/ All rights reserved.
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------

require APP_ROOT_PATH.'app/Lib/deal.php';
class deal
{
	public function index(){
		
		$root = array();
		
		$id = intval($GLOBALS['request']['id']);
		$email = strim($GLOBALS['request']['email']);//用户名或邮箱
		$pwd = strim($GLOBALS['request']['pwd']);//密码
		
		//检查用户,用户密码
		$user = user_check($email,$pwd);
		$user_id  = intval($user['id']);
		if ($user_id >0){
			
			$root['is_faved'] = $GLOBALS['db']->getOne("SELECT count(*) FROM ".DB_PREFIX."deal_collect WHERE deal_id = ".$id." AND user_id=".$user_id);	
		}else{
			$root['is_faved'] = 0;//0：未关注;>0:已关注
		}
		$root['response_code'] = 1;
		$deal = get_deal($id);	
		//format_deal_item($deal,$email,$pwd);
		//print_r($deal);
		//exit;		
		$root['deal'] = $deal;
		$root['open_ips'] = intval(app_conf("OPEN_IPS"));
		$root['ips_acct_no'] = $user['ips_acct_no'];
		$root['ips_bill_no'] = $deal['ips_bill_no'];

//		function bid_calculate(){
//			require_once APP_ROOT_PATH."app/Lib/deal_func.php";
//			echo bid_calculate($_POST);
//		}	
		
		if (!empty($root['ips_bill_no'])){
			//第三方托管标
			if (!empty($user['ips_acct_no'])){
				$result = GetIpsUserMoney($user_id,0);
					
				$root['user_money'] = $result['pBalance'];
			}else{
				$root['user_money'] = 0;
			}
		}else{
			$root['user_money'] = $user['money'];
		}
			
		$root['user_money_format'] = format_price($user['user_money']);//用户金额
		//data.deal.name
		$root['program_title'] = "产品详情";
                //
                $root['deal']['min_loan_money_num']=  str_replace('.00','',$root['deal']['min_loan_money']);        
		output($root);		
	}
}
?>

