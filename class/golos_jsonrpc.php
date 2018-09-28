<?php
class golos_jsonrpc_web{
	public $endpoint='';
	public $debug=false;
	public $request_arr=array();
	public $result_arr=array();
	public $post_num=1;
	private $api=array(
		//https://github.com/GolosChain/golos/blob/master/plugins/account_by_key/account_by_key_plugin.cpp
		'get_key_references'=>'account_by_key',

		//https://github.com/GolosChain/golos/blob/master/plugins/auth_util/plugin.cpp
		'check_authority_signature'=>'auth_util',

		//https://github.com/GolosChain/golos/blob/master/plugins/block_info/plugin.cpp
		'get_block_info'=>'block_info',
		'get_blocks_with_info'=>'block_info',

		//https://github.com/GolosChain/golos/blob/master/plugins/follow/plugin.cpp
		'get_followers'=>'follow',
		'get_following'=>'follow',
		'get_follow_count'=>'follow',
		'get_feed_entries'=>'follow',
		'get_feed'=>'follow',
		'get_blog_entries'=>'follow',
		'get_blog'=>'follow',
		'get_account_reputations'=>'follow',
		'get_reblogged_by'=>'follow',
		'get_blog_authors'=>'follow',

		'get_inbox'=>'private_message',
		'get_outbox'=>'private_message',

		//https://github.com/GolosChain/golos/blob/master/plugins/database_api/api.cpp
		/* Blocks and transactions */
		'get_block_header'=>'database_api',
		'get_block'=>'database_api',
		'get_ops_in_block'=>'operation_history',
		'set_block_applied_callback'=>'database_api',
		'get_config'=>'database_api',
		'get_dynamic_global_properties'=>'database_api',
		'get_chain_properties'=>'database_api',
		'get_hardfork_version'=>'database_api',
		'get_next_scheduled_hardfork'=>'database_api',
		/* Accounts */
		'get_accounts'=>'database_api',
		'lookup_account_names'=>'database_api',
		'lookup_accounts'=>'database_api',
		'get_account_count'=>'database_api',
		'get_owner_history'=>'database_api',
		'get_recovery_request'=>'database_api',
		'get_escrow'=>'database_api',
		'get_withdraw_routes'=>'database_api',
		'get_account_bandwidth'=>'database_api',
		/* Witnesses */
		'get_current_median_history_price'=>'witness_api',
		'get_feed_history'=>'witness_api',
		'get_miner_queue'=>'witness_api',
		'get_witness_schedule'=>'witness_api',
		'get_witnesses'=>'witness_api',
		'get_witness_by_account'=>'witness_api',
		'get_witnesses_by_vote'=>'witness_api',
		'get_witness_count'=>'witness_api',
		'lookup_witness_accounts'=>'witness_api',
		'get_active_witnesses'=>'witness_api',
		/* Authority / validation */
		'get_transaction_hex'=>'database_api',
		'get_required_signatures'=>'database_api',
		'get_potential_signatures'=>'database_api',
		'verify_authority'=>'database_api',
		'verify_account_authority'=>'database_api',
		'get_conversion_requests'=>'database_api',
		'get_account_history'=>'account_history',
		'get_savings_withdraw_from'=>'database_api',
		'get_savings_withdraw_to'=>'database_api',
		'get_transaction'=>'operation_history',

		//https://github.com/GolosChain/golos/blob/master/plugins/social_network/social_network.cpp
		'get_languages'=>'social_network',
		'get_active_votes'=>'social_network',
		'get_content_replies'=>'social_network',
		'get_all_content_replies'=>'social_network',
		'get_discussions_by_feed'=>'social_network',
		'get_discussions_by_blog'=>'social_network',
		'get_discussions_by_comments'=>'social_network',
		'get_trending_categories'=>'social_network',
		'get_best_categories'=>'social_network',
		'get_active_categories'=>'social_network',
		'get_recent_categories'=>'social_network',
		'get_discussions_by_trending'=>'social_network',
		'get_discussions_by_promoted'=>'social_network',
		'get_account_votes'=>'social_network',
		'get_discussions_by_created'=>'social_network',
		'get_discussions_by_active'=>'social_network',
		'get_discussions_by_cashout'=>'social_network',
		'get_discussions_by_payout'=>'social_network',
		'get_discussions_by_votes'=>'social_network',
		'get_discussions_by_children'=>'social_network',
		'get_discussions_by_hot'=>'social_network',
		'get_trending_tags'=>'social_network',
		'get_tags_used_by_author'=>'social_network',
		'get_content'=>'social_network',
		'get_discussions_by_author_before_date'=>'social_network',
		'get_replies_by_last_update'=>'social_network',

		//https://github.com/GolosChain/golos/blob/master/plugins/raw_block/plugin.cpp
		'get_raw_block'=>'raw_block',

		//https://github.com/GolosChain/golos/blob/master/plugins/private_message/private_message_plugin.cpp
		'get_inbox'=>'private_message_plugin',
		'get_outbox'=>'private_message_plugin',

		//https://github.com/GolosChain/golos/blob/master/plugins/market_history/market_history_plugin.cpp
		'get_ticker'=>'market_history',
		'get_volume'=>'market_history',
		'get_order_book'=>'market_history',
		'get_trade_history'=>'market_history',
		'get_recent_trades'=>'market_history',
		'get_market_history'=>'market_history',
		'get_market_history_buckets'=>'market_history',
		'get_open_orders'=>'market_history',

		//https://github.com/GolosChain/golos/blob/master/plugins/network_broadcast_api/network_broadcast_api.cpp
		'broadcast_transaction'=>'network_broadcast_api',
		'broadcast_transaction_synchronous'=>'network_broadcast_api',
		'broadcast_block'=>'network_broadcast_api',
		'broadcast_transaction_with_callback'=>'network_broadcast_api',
	);
	function golos_jsonrpc_web($endpoint='',$debug=false){
		$this->endpoint=$endpoint;
		$this->debug=$debug;
		$this->request_arr=array();
		$this->result_arr=array();
	}
	function get_url($url,$post=array()){
		$this->last_url=$url;
		$method='GET';
		if($post){
			$method='POST';
		}
		preg_match('#://(.*)/#iUs',$url,$stock);
		preg_match('#://'.$stock[1].'/(.*)$#iUs',$url,$stock2);
		$host=$stock[1];
		$use_port=false;
		if(false!==strpos($host,':')){
			$use_port=intval(substr($host,strpos($host,':')+1));
			$host=substr($host,0,strpos($host,':'));
		}
		$path=$stock2[1];
		$request=$method." /".$path." HTTP/1.1\r\n";
		$request.="Host: ".$host."\r\n";
		foreach($this->headers_arr as $k=>$v){
			if($v){
				$request.=$k.': '.$v."\r\n";
			}
		}
		$request.="Connection: close\r\n";
		$request.="Content-Type: application/x-www-form-urlencoded\r\n";
		$request.="Content-Length: ".strlen($post)."\r\n\r\n";
		$request.=$post;
		$request.="\r\n";
		$request.="\r\n";
		if($this->debug){
			$this->request_arr[]=$request;
		}
		$result='';
		$port=80;
		if(false!==strpos($url,'https://')){
			$port=443;
			$host='ssl://'.$host;
		}
		if(false!==strpos($url,'wss://')){
			$port=443;
			$host='ssl://'.$host;
		}
		if(false!==$use_port){
			$port=$use_port;
		}
		if($sock=fsockopen($host, $port, $errno, $errstr, 4)){
			fwrite($sock,$request,strlen($request));
			while(!feof($sock)){
				$result.=fread($sock,1024);
			}
			fclose($sock);
		}
		print $result;
		if($this->debug){
			$this->result_arr[]=$result;
		}
		return $result;
	}
	function parse_web_result($fp){
		$headers=mb_substr($fp,0,mb_strpos($fp,"\r\n\r\n"));
		$clear_r=mb_substr($fp,mb_strpos($fp,"\r\n\r\n")+4);
		if(false!==strpos($headers,'Transfer-Encoding: chunked')){$clear_r=clear_chunked($clear_r);}
		if(false!==strpos($headers,'Content-Encoding: gzip')){$clear_r=gzdecode($clear_r);}
		return array($headers,$clear_r);
	}
	function build_method($method,$params,$array=false){
		$params_arr=array();
		foreach($params as $k => $v){
			if(!is_int($v)){
				$params_arr[]='"'.$v.'"';
			}
			else{
				$params_arr[]=$v;
			}
		}
		$params=implode(',',$params_arr);
		if($array){
			$params='['.$params.']';
		}
		$return='{"id":'.$this->post_num.',"jsonrpc":"2.0","method":"call","params":["'.$this->api[$method].'","'.$method.'",['.$params.']]}';
		$this->post_num++;
		return $return;
	}
	function execute_method($method,$params,$array=false,$debug=false){
		$jsonrpc_query=$this->build_method($method,$params,$array);
		list($header,$result)=$this->parse_web_result($this->get_url($this->endpoint,$jsonrpc_query));
		if($debug){
			print '<!-- ENDPOINT: '.$this->endpoint.' -->'.PHP_EOL;
			print '<!-- QUERY: '.$jsonrpc_query.' -->'.PHP_EOL;
			print '<!-- HEADER: '.$header.' -->'.PHP_EOL;
		}
		$result_arr=json_decode($result,true);
		return $result_arr['result'];
	}
}