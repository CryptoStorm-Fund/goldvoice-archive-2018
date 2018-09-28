var user={
	tz:-1,
	login:'',
	posting_key:'',
	active_key:'',
	owner_key:'',
	verify:0,
	session_id:'',
	feed_view_mode:'',
	default_currency:'',
	post_autovote:0,
	profile:{},
	comment_percent:100,
	post_percent:100,
	hide_flag_action:0,
	hide_tags_preview_action:0,
	adult_filter_select:0,
};
var multi_account=[];
var gate;
var modal=0;
var user_profile_load_timer=0;
var user_profile_load_attempts=0;
var rebuild_usr_cards_timer=0;
var update_comments_list_timer;
var update_comments_list_timeout=3500;
var waiting_update_comments_list=0;
var wysiwyg_active=0;
var window_width=0;
var feed_view_modes={'all':l10n.view_mode.all,'only_posts':l10n.view_mode.posts,'only_reposts':l10n.view_mode.reposts};
var blog_view_mode='all';
var draft={title:'',image:'',text:'',tags:''};
var draft_autoload=0;
var draft_timer=0;
var notify_feed_timer=0;
var notify_replies_timer=0;
var notifications_list_count=-1;
var update_notifications_list_timer=0;
var global_scroll_top=0;
var user_card_action={wait:0,login:'',action:'',need_confirmation:0,confirmation_text:'',confirmation:0};
var vote_card_action={wait:0,login:'',permlink:'',action:'',need_confirmation:0,confirmation_text:'',weight:100,confirmation:0,proper_target:''};
var repost_card_action={wait:0,login:'',permlink:'',comment:'',need_confirmation:0,confirmation:0,proper_target:''};
var post_geo={lat:0,lng:0,address:''};
var path_array=window.location.pathname.split('/');
function set_post_geo(lat,lng,address=''){
	$('.get_post_geo').css('display','none');
	$('.clear_post_geo').css('display','inline-block');
	post_geo.lat=lat;
	post_geo.lng=lng;
	if(''!=address){
		post_geo.address=address;
		$('.post_geo span').html(post_geo.address);
	}
}
function isJsonString(str) {
	try{
		JSON.parse(str);
	}
	catch(e){
		return false;
	}
	return true;
}
function date_str(timestamp,add_time,add_seconds){
	if(-1==timestamp){
		var d=new Date();
	}
	else{
		var d=new Date(timestamp);
	}
	var day=d.getDate();
	if(day<10){
		day='0'+day;
	}
	var month=d.getMonth()+1;
	if(month<10){
		month='0'+month;
	}
	var minutes=d.getMinutes();
	if(minutes<10){
		minutes='0'+minutes;
	}
	var hours=d.getHours();
	if(hours<10){
		hours='0'+hours;
	}
	var seconds=d.getSeconds();
	if(seconds<10){
		seconds='0'+seconds;
	}
	var datetime_str=day+'.'+month+'.'+d.getFullYear();
	if(add_time){
		datetime_str=datetime_str+' '+hours+':'+minutes;
		if(add_seconds){
			datetime_str=datetime_str+':'+seconds;
		}
	}
	return datetime_str;
}
function reg_subscribe_to_list(){
	let login='';
	let new_reg=false;
	if(''!=user.login){
		login=user.login;
	}
	if(0<$('.registration-form input[name=login]').length){
		login=$('.registration-form input[name=login]').val();
		if(login.length>0){
			new_reg=true;
		}
	}
	$.ajax({
		type:'POST',
		url:'/ajax/reg_subscribe_to_list/',
		data:{'login':login},
		success:function(data_json){
			let master_password='';
			let posting_key='';
			if(new_reg){
				master_password=$('.registration-form input.pass').val();
				posting_key=gate.auth.toWif(login,master_password,'posting');
			}
			else{
				posting_key=user.posting_key;
			}
			data_obj=JSON.parse(data_json);
			if(typeof data_obj.status !== undefined){
				if('ok'==data_obj.status){
					if(0!=data_obj.list.length){
						for(k in data_obj.list){
							let following_login=data_obj.list[k];
							var json=JSON.stringify(['follow',{follower:login,following:following_login,what:['blog']}]);
							gate.broadcast.customJson(posting_key,[],[login],'follow',json,function(err, result){if(!err){$('.subscribe-history').append('<p>'+l10n.registration.subscribe+' <a href="/@'+following_login+'/">@'+following_login+'</a></p>');}});
						}
					}
					if(new_reg){
						add_notify('<strong>OK</strong> '+l10n.registration.ok,5000);
						local_session_clear();
						preset.user_profile={};
						preset.page_refresh=true;
						user_auth(login,posting_key);
					}
				}
			}
		},
	});
}
function check_registration_login(login){
	$.ajax({
		type:'POST',
		url:'/ajax/check_login/',
		data:{'login':login},
		success:function(data_json){
			console.log(data_json);
			data_obj=JSON.parse(data_json);
			if('ok'==data_obj.status){
				let master_password=$('.registration-form input.pass').val();
				let active_key=gate.auth.toWif(login,master_password,'active');
				gate.broadcast.accountWitnessProxy(active_key,login,'goldvoice',function(err,result){
					if(!err){
					}
					else{
						console.log(err);
					}
				});
				reg_subscribe_to_list();
			}
			else{
				setTimeout(function(){check_registration_login(login);},500);
			}
		},
	});
}
function check_registration_form(){
	if(0<$('.registration-form').length){
		$('.registration-form input[name=login]').val($('.registration-form input[name=login]').val().replace(/([^a-z0-9\.\-]*)/g,''));
		let user_login_exp = /^[a-z][-\.a-z\d]+[a-z\d]$/g;
		if(!user_login_exp.test($('.registration-form input[name=login]').val())){
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.registration.error_login_incorrect,5000,true);
			return false;
		}
		if('rgb(255, 21, 21)'==$('.registration-form input[name=login]').css('border-color')){
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.registration.error_login_taken,5000,true);
			return false;
		}
		if($('.registration-form input.pass').val().length<8){
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.registration.error_password_length,5000,true);
			return false;
		}
		var approve=$('.registration-form input[name=approve]').prop('checked');
		if(!approve){
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.registration.error_approve,5000,true);
			return false;
		}
		$.ajax({
			type:'POST',
			url:'/ajax/registration/',
			data:$('.registration-form').serialize(),
			success:function(response){
				console.log(response);
				if('ok'==response){
					$('.registration-form input').attr('disabled','disabled');
					$('.registration-form input[name=registration]').addClass('waiting');
					$('.registration-form input[name=registration]').val(l10n.registration.waiting);
					check_registration_login($('.registration-form input[name=login]').val());
				}
				else if('ip'==response){
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.registration.error_ip_24,10000,true);
				}
				else if('spam'==response){
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.registration.error_ip_spam,10000,true);
				}
				else if('closed'==response){
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.registration.error_invite_closed,10000,true);
				}
				else{
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
				}
			}
		});
	}
	return false;
}
function update_post_geo(position){
	post_geo.lat=position.coords.latitude;
	post_geo.lng=position.coords.longitude;
	$('.post_geo span').html('<i class="fa fa-fw fa-spin fa-spinner" aria-hidden="true"></i> '+l10n.add_post.wait_geo);
	$.ajax({
		type:'POST',
		url:'/ajax/geolocation_name/',
		data:{lat:post_geo.lat,lng:post_geo.lng},
		success:function(data_json){
			data_obj=JSON.parse(data_json);
			if(typeof data_obj.result !== 'undefined'){
				if('auth'==data_obj.result){
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.global.no_auth,5000,true);
				}
				else{
					post_geo.address=data_obj.result;
					$('.post_geo span').html(post_geo.address);
				}
			}
		}
	});
}
function error_post_geo(msg){
	add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.geo.error,5000,true);
}
function clear_post_geo(){
	post_geo={lat:0,lng:0,address:''};
	$('.post_geo span').html('');
}
function get_post_geo(){
	if(navigator.geolocation){
		post_geo={lat:0,lng:0,address:''};
		navigator.geolocation.getCurrentPosition(update_post_geo,error_post_geo);
	}
	else{
		add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.geo.unsupported,5000,true);
	}
}
function post_draft_autosave(){
	if(0==draft_autoload){
		if(null!=localStorage.getItem('draft')){
			draft=JSON.parse(localStorage.getItem('draft'));
			if(''!=draft.text){
				if(''==$('textarea[name=post_text]').val()){
					$('input[name=post_title]').val(draft.title);
					$('input[name=post_image]').val(draft.image);
					$('input[name=post_tags]').val(draft.tags);
					$('textarea[name=post_text]').val(draft.text);
				}
			}
		}
		draft_autoload=1;
	}
	else{
		draft.title=$('input[name=post_title]').val();
		draft.image=$('input[name=post_image]').val();
		draft.tags=$('input[name=post_tags]').val();
		draft.text=$('textarea[name=post_text]').val();
		if(wysiwyg_active){
			draft.text=tinyMCE.activeEditor.getContent();
		}
		draft_json=JSON.stringify(draft);
		localStorage.setItem('draft',draft_json);
	}
	window.clearTimeout(draft_timer);
	draft_timer=window.setTimeout(function(){post_draft_autosave();},5000);
}
function unique_array(arr){
	var seen={};
	var out=[];
	var len=arr.length;
	var j=0;
	for(var i = 0; i < len; i++){
		var item = arr[i];
		if(seen[item] !== 1){
			seen[item] = 1;
			out[j++] = item;
		}
	}
	return out;
}
var tags_symbols_ru={
	'а':'a',
	'б':'b',
	'в':'v',
	/*'ґ':'g',*/
	'г':'g',
	'д':'d',
	'е':'e',
	'ё':'yo',
	'ж':'zh',
	'з':'z',
	'и':'i',
	'й':'ij',
	'к':'k',
	'л':'l',
	'м':'m',
	'н':'n',
	'о':'o',
	'п':'p',
	'р':'r',
	'с':'s',
	'т':'t',
	'у':'u',
	'ф':'f',
	'ы':'y',
	'х':'kh',
	'ц':'cz',
	'ч':'ch',
	'ш':'sh',
	'щ':'shch',
	'ъ':'xx',
	'ь':'x',
	'э':'ye',
	'ю':'yu',
	'я':'ya',
	/*'і':'i',
	'є':'ye',
	'ї':'yi',*/
}
function tags_convert(tag,lang='ru',ignore_find=0){
	var result='';
	var find_lang=0;
	if('ru'==lang){
		tag=tag.replace('ые','yie');
	}
	for(var i=0;i<tag.length;i++){
		var char=tag[i].toLowerCase();
		if(' '==char){
			result=result+'-';
		}
		else{
			if('ru'==lang){
				if(tags_symbols_ru[char]){
					result=result+tags_symbols_ru[char];
					find_lang=1;
				}
				else{
					result=result+char;
				}
			}
		}
	}
	if(ignore_find){
		find_lang=0;
	}
	if(find_lang){
		if('ru'==lang){
			result='ru--'+result;
		}
	}
	return result;
}
function get_waiting_update_comments_list(){
	return waiting_update_comments_list;
}
function set_waiting_update_comments_list(value){
	waiting_update_comments_list=value;
	if(1==value){
		update_comments_list_timeout=3500;
		window.clearTimeout(update_comments_list_timer);
		update_comments_list_timer=window.setTimeout(function(){update_comments_list();},update_comments_list_timeout);
	}
}
function gate_connect(){
	//https://github.com/steemit/steem-js/tree/master/doc
	if(typeof golos !== 'undefined'){
		gate=golos;
		//gate.config.set('websocket','wss://ws17.golos.blog');
		gate.config.set('websocket','wss://ws.golos.io');
		//gate.config.set('websocket','wss://ws.goldvoice.club');
		//gate.config.set('websocket','wss://api.golos.cf');
		//gate.config.set('url','https://ws.goldvoice.club/');
	}
	else{
		gate=steem;
		gate.config.set('websocket','wss://ws.golos.io');
		gate.config.set('address_prefix','GLS');
		gate.config.set('chain_id','782a3039b478c839e4cb0c941ff4eaeb7df40bdd68bd441afd444b9da763de12');
	}
}
var notify_id=0;
function del_notify(id){
	$('.notify-list .notify[rel="'+id+'"]').remove();
}
function fade_notify(id){
	$('.notify-list .notify[rel="'+id+'"]').css('opacity','0.0');
	window.setTimeout('del_notify("'+id+'")',300);
}
function add_notify(html,fade_time=5000,dark=false){
	notify_id++;
	var element_html='<div class="notify'+(dark?' notify-dark':'')+'" rel="'+notify_id+'">'+html+'</div>';
	$('.notify-list').append(element_html);
	window.setTimeout('fade_notify('+notify_id+')',fade_time);
}
function generate_password(length=20){
	var chars='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!-_=+';
	var result='';
	for (var i = 0,n=chars.length;i<length;++i){
		result+=chars.charAt(Math.floor(Math.random()*n));
	}
	return result;
}
function wysiwyg_activate(){
	$('.go-top-left-wrapper').css('display','none');
	$('.header-line').css('position','absolute');
	$('.header-line').css('z-index','4');
	wysiwyg_active=1;
	tinymce.init({
		selector: "textarea",
		plugins: [
			"advlist autolink link image lists anchor codesample",
			"wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
			"table contextmenu directionality textcolor paste textcolor colorpicker textpattern hr"
		],

		toolbar1: "undo redo | removeformat | subscript superscript | bold italic strikethrough | alignleft aligncenter alignright alignjustify | styleselect",
		toolbar2: "bullist numlist | outdent indent blockquote codesample | link unlink anchor image media hr | forecolor | fullscreen code",

		menubar: false,
		toolbar_items_size: "small",
		relative_urls : false,
		remove_script_host : false,
		/*document_base_url : "https://goldvoice.club/",*/
		browser_spellcheck:true,
		language : "ru",
		language_url : "https://goldvoice.club/js/tinymce_ru.js",
		style_formats: [
			{title: "Центрирование", block: "center"},
			{title: 'Спойлер', inline : 'span', classes : 'spoiler'},
			{title: "Заголовок 1", block: "h1"},
			{title: "Заголовок 2", block: "h2"},
			{title: "Заголовок 3", block: "h3"},
			{title: "Заголовок 4", block: "h4"},
			{title: "Заголовок 5", block: "h5"},
		],
		content_css : "/css/wysiwyg.css?" + new Date().getTime(),
	});
}
function scroll_top_action(){
	if(0!=$(window).scrollTop()){
		global_scroll_top=$(window).scrollTop();
		$(window).scrollTop(0);
	}
	else{
		$(window).scrollTop(global_scroll_top);
	}
}
function update_bandwidth(login,market=false){
	gate.api.getDynamicGlobalProperties(function(e,r){
		let global_chain_properties=r;
		gate.api.getAccounts([login],function(e,r){
			if(null==e){
				let vesting_shares=parseFloat(r[0].vesting_shares.split(' ')[0]);
				//let received_vesting_shares=parseFloat(r[0].received_vesting_shares.split(' ')[0]);
				//let delegated_vesting_shares=parseFloat(r[0].delegated_vesting_shares.split(' ')[0]);

				let max_virtual_bandwidth=parseFloat(global_chain_properties.max_virtual_bandwidth);
				let total_vesting_shares=parseFloat(global_chain_properties.total_vesting_shares.split(' ')[0]);

				let allocated_bandwidth=max_virtual_bandwidth * (vesting_shares/*+received_vesting_shares-delegated_vesting_shares*/) / total_vesting_shares;
				allocated_bandwidth=allocated_bandwidth/1000000;

				let recovery_speed=604800;//7 days*24*60*60
				let last_bandwidth_update_date=Date.parse(r[0].last_bandwidth_update);
				if(market){
					last_bandwidth_update_date=Date.parse(r[0].last_market_bandwidth_update);
				}
				let elapsed_seconds=parseInt((new Date().getTime() - last_bandwidth_update_date + (new Date().getTimezoneOffset()*60000))/1000);
				let average_bandwidth=parseFloat(r[0].average_bandwidth);
				if(market){
					average_bandwidth=parseFloat(r[0].average_market_bandwidth)/10;
				}

				let used_bandwidth=average_bandwidth;
				if(elapsed_seconds<recovery_speed){
					used_bandwidth=(((recovery_speed - elapsed_seconds) * average_bandwidth) / recovery_speed);
				}
				used_bandwidth=Math.round(used_bandwidth/1000000);
				let unused_percent=(100-(100 * used_bandwidth / allocated_bandwidth)).toFixed(2);
				//console.log('used_bandwidth: '+used_bandwidth);
				//console.log('bandwidth percent used: '+(100 * used_bandwidth / allocated_bandwidth));
				//console.log('bandwidth percent unused: '+(100-(100 * used_bandwidth / allocated_bandwidth)));
				$('.user_bandwidth[rel='+login+']').html('<i class="fa fa-fw fa-bolt" aria-hidden="true"></i> '+unused_percent+'%');
			}
		});
	});
}
function update_dropdown_currencies(output_currency='GBG'){
	var result='';
	if(typeof user.login !== undefined){
		result+='<div class="user_bandwidth" rel="'+user.login+'" title="'+l10n.global.bandwidth+'"></div>';
	}
	result+='<div><strong>'+l10n.global.energy_exchange_popular+'</strong></div>';
	result+='<div>1 GOLOS = '+convert_currency('GOLOS',1,'GBG')+' GBG</div>';
	result+='<div>1 XRP = '+convert_currency('XRP',1,'USD')+' USD</div>';
	result+='<div>1 XRP = '+convert_currency('XRP',1,'RUB')+' RUB</div>';
	result+='<div>1 BTC = '+convert_currency('BTC',1,'USD')+' USD</div>';
	result+='<div>1 BTC = '+convert_currency('BTC',1,'RUB')+' RUB</div>';
	result+='<div>1 ETH = '+convert_currency('ETH',1,'USD')+' USD</div>';
	result+='<div>1 ETH = '+convert_currency('ETH',1,'RUB')+' RUB</div>';
	result+='<div><strong>'+l10n.global.energy_exchange_rates+'</strong></div>';
	if('GOLOS'!=output_currency)
	result+='<div>1 GOLOS = '+convert_currency('GOLOS',1,output_currency)+' '+output_currency+'</div>';
	if('GBG'!=output_currency)
	result+='<div>1 GBG = '+convert_currency('GBG',1,output_currency)+' '+output_currency+'</div>';
	if('STEEM'!=output_currency)
	result+='<div>1 STEEM = '+convert_currency('STEEM',1,output_currency)+' '+output_currency+'</div>';
	if('SBD'!=output_currency)
	result+='<div>1 SBD = '+convert_currency('SBD',1,output_currency)+' '+output_currency+'</div>';
	if('XRP'!=output_currency)
	result+='<div>1 XRP = '+convert_currency('XRP',1,output_currency)+' '+output_currency+'</div>';
	if('BTC'!=output_currency)
	result+='<div>1 BTC = '+convert_currency('BTC',1,output_currency)+' '+output_currency+'</div>';
	if('ETH'!=output_currency)
	result+='<div>1 ETH = '+convert_currency('ETH',1,output_currency)+' '+output_currency+'</div>';
	if('USD'!=output_currency)
	result+='<div>1 USD = '+convert_currency('USD',1,output_currency)+' '+output_currency+'</div>';
	if('RUB'!=output_currency)
	result+='<div>1 RUB = '+convert_currency('RUB',1,output_currency)+' '+output_currency+'</div>';
	$('.view-dropdown-currencies').html(result);
	if(typeof user.login !== undefined){
		if(''!=user.login){
			update_bandwidth(user.login);
		}
	}
}
function posts_list_filter_form(){
	let result='';
	result+='<div class="action-button posts-list-filter-clear-action right"><i class="fa fa-fw fa-eraser" aria-hidden="true"></i> Очистить фильтр</div>';
	result+='<input type="text" class="bubble" name="posts-list-filter-tag-name" placeholder="Тэг">';
	result+='<div class="action-button posts-list-filter-show-action"><i class="fa fa-fw fa-eye" aria-hidden="true"></i> Показывать</div>';
	result+='<div class="action-button posts-list-filter-hide-action"><i class="fa fa-fw fa-eye-slash" aria-hidden="true"></i> Скрывать</div>';
	$('.posts-list-filter form').html(result);
}
function post_list_filter_show_add(tag,ignore=false){
	var tag_arr=tag.split(',');
	if(tag_arr.length>1){
		for(var i=0;i<tag_arr.length;i++){
			tag_arr[i]=tag_arr[i].trim();//tags_convert
			let tag_en=tags_convert(tag_arr[i]);
			if(ignore){
				tag_en=tag_arr[i];
			}
			if(''!=tag_en){
				let tag_html='<div class="tag" rel="'+escape_html(tag_en)+'">'+escape_html(tag_en)+'</div>';
				$('.posts-list-filter-show').append(tag_html);
				if(tag_arr[i]!==tag_en){
					tag_html='<div class="tag" rel="'+escape_html(tag_arr[i])+'">'+escape_html(tag_arr[i])+'</div>';
					$('.posts-list-filter-show').append(tag_html);
				}
			}
		}
	}
	else{
		let tag_en=tags_convert(tag);
		if(ignore){
			tag_en=tag;
		}
		if(''!=tag_en){
			let tag_html='<div class="tag" rel="'+escape_html(tag_en)+'">'+escape_html(tag_en)+'</div>';
			$('.posts-list-filter-show').append(tag_html);
			if(tag!==tag_en){
				tag_html='<div class="tag" rel="'+escape_html(tag)+'">'+escape_html(tag)+'</div>';
				$('.posts-list-filter-show').append(tag_html);
			}
		}
	}
	posts_list_filter_save();
}
function post_list_filter_hide_add(tag,ignore=false){
	var tag_arr=tag.split(',');
	if(tag_arr.length>1){
		for(var i=0;i<tag_arr.length;i++){
			tag_arr[i]=tag_arr[i].trim();//tags_convert
			let tag_en=tags_convert(tag_arr[i]);
			if(ignore){
				tag_en=tag_arr[i];
			}
			if(''!=tag_en){
				let tag_html='<div class="tag" rel="'+escape_html(tag_en)+'">'+escape_html(tag_en)+'</div>';
				$('.posts-list-filter-hide').append(tag_html);
				if(tag_arr[i]!==tag_en){
					tag_html='<div class="tag" rel="'+escape_html(tag_arr[i])+'">'+escape_html(tag_arr[i])+'</div>';
					$('.posts-list-filter-hide').append(tag_html);
				}
			}
		}
	}
	else{
		let tag_en=tags_convert(tag);
		if(ignore){
			tag_en=tag;
		}
		if(''!=tag_en){
			let tag_html='<div class="tag" rel="'+escape_html(tag_en)+'">'+escape_html(tag_en)+'</div>';
			$('.posts-list-filter-hide').append(tag_html);
			if(tag!==tag_en){
				tag_html='<div class="tag" rel="'+escape_html(tag)+'">'+escape_html(tag)+'</div>';
				$('.posts-list-filter-hide').append(tag_html);
			}
		}
	}
	posts_list_filter_save();
}
function posts_list_filter_save(){
	let tags_filter_show=[];
	let tags_filter_hide=[];
	$('.posts-list-filter-show .tag').each(function(){
		tags_filter_show.push($(this).attr('rel'));
	});
	$('.posts-list-filter-hide .tag').each(function(){
		tags_filter_hide.push($(this).attr('rel'));
	});
	let tags_filter_show_unique=tags_filter_show.filter((v,i,a)=>a.indexOf(v)===i);
	let tags_filter_hide_unique=tags_filter_hide.filter((v,i,a)=>a.indexOf(v)===i);

	localStorage.setItem('posts_list_filter_show',JSON.stringify(tags_filter_show_unique));
	localStorage.setItem('posts_list_filter_hide',JSON.stringify(tags_filter_hide_unique));
}
function posts_list_filter_hide_action(){
	let tag=$('input[name=posts-list-filter-tag-name]').val().trim().toLowerCase();
	post_list_filter_hide_add(tag);
	$('input[name=posts-list-filter-tag-name]').val('');
	posts_list_filter(true);
}
function posts_list_filter_show_action(){
	let tag=$('input[name=posts-list-filter-tag-name]').val().trim().toLowerCase();
	post_list_filter_show_add(tag);
	$('input[name=posts-list-filter-tag-name]').val('');
	posts_list_filter(true);
}
function posts_list_filter_clear_action(){
	$('.posts-list-filter-show').html('');
	$('.posts-list-filter-hide').html('');
	$('.post-card').css('display','block');
	if(0<$('.feed_view_mode').length){
		apply_feed_view_mode();
	}
	if(0<$('.blog_view_mode').length){
		apply_blog_view_mode();
	}
	localStorage.setItem('posts_list_filter_show','[]');
	localStorage.setItem('posts_list_filter_hide','[]');
	posts_list_filter(true);
}
function posts_list_filter(load_more){
	let tags_filter_show=[];
	let tags_filter_hide=[];
	$('.posts-list-filter-show .tag').each(function(){
		tags_filter_show.push($(this).attr('rel'));
	});
	$('.posts-list-filter-hide .tag').each(function(){
		tags_filter_hide.push($(this).attr('rel'));
	});
	let filters_count=tags_filter_show.length+tags_filter_hide.length;
	if(filters_count>0){
		$(".posts-list-filter-button span").html('('+filters_count+')');
	}
	else{
		$(".posts-list-filter-button span").html('');
	}
	$('.post-card').each(function(){
		var show=true;
		var find_show=false;
		var find_hide=false;
		if(0==tags_filter_show.length){
			find_show=true;
		}
		$(this).find('.post-tags .tag').each(function(){
			let post_tag=$(this).attr('rel');
			for(i in tags_filter_show){
				if(tags_filter_show[i]==post_tag){
					find_show=true;
				}
			}
			for(i in tags_filter_hide){
				if(tags_filter_hide[i]==post_tag){
					find_hide=true;
				}
			}
		});
		if(!find_show){
			show=false;
		}
		if(find_hide){
			show=false;
		}
		if(!show){
			$(this).css('display','none');
		}
	});
	group_reposts();
	if(load_more){
		check_load_more();
	}
}
function check_load_more(){
	var scroll_top=$(window).scrollTop();
	var window_height=window.innerHeight;
	if(0==scroll_top){
		if($('.go-top-button').length>0){
			if(0==global_scroll_top){
				$('.go-top-button').css('display','none');
			}
			$('.go-top-button i').addClass('fa-chevron-down');
			$('.go-top-button i').removeClass('fa-chevron-up');
		}
	}
	else{
		if($('.go-top-button').length>0){
			$('.go-top-button').css('display','block');
			$('.go-top-button i').addClass('fa-chevron-up');
			$('.go-top-button i').removeClass('fa-chevron-down');
		}
		close_dropdown();
		$('.menu-dropdown').find('.fa').addClass('fa-caret-right');
		$('.menu-dropdown').find('.fa').removeClass('fa-caret-down');
	}
	$('.load-more-indicator').each(function(){
		var indicator=$(this);
		if('1'!=indicator.attr('data-busy')){
			var offset=indicator.offset();
			if((scroll_top+window_height)>(offset.top-10)){
				if('new-posts'==indicator.attr('data-action')){
					var posts_list=indicator.parent();
					indicator.attr('data-busy','1');
					indicator.find('.fa-spinner').addClass('fa-spin');
					var last_post_id=99999999999;
					posts_list.find('.post-card').each(function(){
						var find_post_id=parseInt($(this).attr('data-id'))
						if(find_post_id<last_post_id){
							last_post_id=find_post_id;
						}
					});
					$.ajax({
						type:'POST',
						url:'/ajax/load_more/',
						data:{action:indicator.attr('data-action'),last_id:last_post_id},
						success:function(data_html){
							if('none'==data_html){
								indicator.css('display','none');
							}
							else{
								indicator.before(data_html);
								update_post_payout(user.default_currency);
								update_posts_dates();
								update_posts_view();
								indicator.find('.fa-spinner').removeClass('fa-spin');
								indicator.attr('data-busy','0');
								posts_list_filter(true);
							}
						}
					});
				}
				if('feed-posts'==indicator.attr('data-action')){
					var posts_list=indicator.parent();
					indicator.attr('data-busy','1');
					indicator.find('.fa-spinner').addClass('fa-spin');
					var last_post_id=99999999999;
					posts_list.find('.post-card').each(function(){
						var find_post_id=parseInt($(this).attr('data-id'));
						if(typeof $(this).attr('data-reblog-id') !== 'undefined'){
							find_post_id=parseInt($(this).attr('data-reblog-id'));
						}
						if(find_post_id<last_post_id){
							last_post_id=find_post_id;
						}
					});
					$.ajax({
						type:'POST',
						url:'/ajax/load_more/',
						data:{action:indicator.attr('data-action'),last_id:last_post_id,user:indicator.attr('data-user-login')},
						success:function(data_html){
							if('none'==data_html){
								indicator.css('display','none');
							}
							else{
								indicator.before(data_html);
								update_post_payout(user.default_currency);
								update_posts_dates();
								update_posts_view();
								indicator.find('.fa-spinner').removeClass('fa-spin');
								indicator.attr('data-busy','0');
								if(0<$('.feed_view_mode').length){
									$('.feed_view_mode').html(feed_view_modes[user.feed_view_mode]);
									apply_feed_view_mode();
								}
								posts_list_filter(true);
							}
						}
					});
				}
				if('group-feed'==indicator.attr('data-action')){
					var posts_list=indicator.parent();
					indicator.attr('data-busy','1');
					indicator.find('.fa-spinner').addClass('fa-spin');
					var last_post_id=99999999999;
					posts_list.find('.post-card').each(function(){
						var find_post_id=parseInt($(this).attr('data-id'));
						if(typeof $(this).attr('data-reblog-id') !== 'undefined'){
							find_post_id=parseInt($(this).attr('data-reblog-id'));
						}
						if(find_post_id<last_post_id){
							last_post_id=find_post_id;
						}
					});
					$.ajax({
						type:'POST',
						url:'/ajax/load_more/',
						data:{action:indicator.attr('data-action'),group_id:indicator.attr('data-group-id'),last_id:last_post_id},
						success:function(data_html){
							if('none'==data_html){
								indicator.css('display','none');
							}
							else{
								indicator.before(data_html);
								update_post_payout(user.default_currency);
								update_posts_dates();
								update_posts_view();
								indicator.find('.fa-spinner').removeClass('fa-spin');
								indicator.attr('data-busy','0');
								if(0<$('.feed_view_mode').length){
									$('.feed_view_mode').html(feed_view_modes[user.feed_view_mode]);
									apply_feed_view_mode();
								}
								posts_list_filter(true);
							}
						}
					});
				}
				if('tag-posts'==indicator.attr('data-action')){
					var posts_list=indicator.parent();
					indicator.attr('data-busy','1');
					indicator.find('.fa-spinner').addClass('fa-spin');
					var last_post_id=99999999999;
					posts_list.find('.post-card').each(function(){
						var find_post_id=parseInt($(this).attr('data-id'))
						if(find_post_id<last_post_id){
							last_post_id=find_post_id;
						}
					});
					$.ajax({
						type:'POST',
						url:'/ajax/load_more/',
						data:{action:indicator.attr('data-action'),tag:indicator.attr('data-tag'),last_id:last_post_id},
						success:function(data_html){
							if('none'==data_html){
								indicator.css('display','none');
							}
							else{
								indicator.before(data_html);
								update_post_payout(user.default_currency);
								update_posts_dates();
								update_posts_view();
								indicator.find('.fa-spinner').removeClass('fa-spin');
								indicator.attr('data-busy','0');
								apply_blog_view_mode();
							}
						}
					});
				}
				if('category-posts'==indicator.attr('data-action')){
					var posts_list=indicator.parent();
					indicator.attr('data-busy','1');
					indicator.find('.fa-spinner').addClass('fa-spin');
					var last_post_id=99999999999;
					posts_list.find('.post-card').each(function(){
						var find_post_id=parseInt($(this).attr('data-id'))
						if(find_post_id<last_post_id){
							last_post_id=find_post_id;
						}
					});
					$.ajax({
						type:'POST',
						url:'/ajax/load_more/',
						data:{action:indicator.attr('data-action'),category:indicator.attr('data-category'),last_id:last_post_id},
						success:function(data_html){
							if('none'==data_html){
								indicator.css('display','none');
							}
							else{
								indicator.before(data_html);
								update_post_payout(user.default_currency);
								update_posts_dates();
								update_posts_view();
								indicator.find('.fa-spinner').removeClass('fa-spin');
								indicator.attr('data-busy','0');
							}
						}
					});
				}
				if('user-posts'==indicator.attr('data-action')){
					var posts_list=indicator.parent();
					indicator.attr('data-busy','1');
					indicator.find('.fa-spinner').addClass('fa-spin');
					var last_post_id=99999999999;
					posts_list.find('.post-card').each(function(){
						var find_post_id=parseInt($(this).attr('data-id'));
						if(typeof $(this).attr('data-reblog-id') !== 'undefined'){
							find_post_id=parseInt($(this).attr('data-reblog-id'));
						}
						if(find_post_id<last_post_id){
							last_post_id=find_post_id;
						}
					});
					$.ajax({
						type:'POST',
						url:'/ajax/load_more/',
						data:{action:indicator.attr('data-action'),user_id:indicator.attr('data-user'),last_id:last_post_id},
						success:function(data_html){
							if('none'==data_html){
								indicator.css('display','none');
							}
							else{
								indicator.before(data_html);
								update_post_payout(user.default_currency);
								update_posts_dates();
								update_posts_view();
								indicator.find('.fa-spinner').removeClass('fa-spin');
								indicator.attr('data-busy','0');
								apply_blog_view_mode();
								posts_list_filter(true);
							}
						}
					});
				}
				if('user-upvotes'==indicator.attr('data-action')){
					var posts_list=indicator.parent();
					indicator.attr('data-busy','1');
					indicator.find('.fa-spinner').addClass('fa-spin');
					var last_post_id=99999999999;
					posts_list.find('.post-card').each(function(){
						var find_post_id=parseInt($(this).attr('data-id'))
						if(find_post_id<last_post_id){
							last_post_id=find_post_id;
						}
					});
					$.ajax({
						type:'POST',
						url:'/ajax/load_more/',
						data:{action:indicator.attr('data-action'),user_id:indicator.attr('data-user'),last_id:last_post_id},
						success:function(data_html){
							if('none'==data_html){
								indicator.css('display','none');
							}
							else{
								indicator.before(data_html);
								update_post_payout(user.default_currency);
								update_posts_dates();
								update_posts_view();
								indicator.find('.fa-spinner').removeClass('fa-spin');
								indicator.attr('data-busy','0');
							}
						}
					});
				}
				if('user-flags'==indicator.attr('data-action')){
					var posts_list=indicator.parent();
					indicator.attr('data-busy','1');
					indicator.find('.fa-spinner').addClass('fa-spin');
					var last_post_id=99999999999;
					posts_list.find('.post-card').each(function(){
						var find_post_id=parseInt($(this).attr('data-id'))
						if(find_post_id<last_post_id){
							last_post_id=find_post_id;
						}
					});
					$.ajax({
						type:'POST',
						url:'/ajax/load_more/',
						data:{action:indicator.attr('data-action'),user_id:indicator.attr('data-user'),last_id:last_post_id},
						success:function(data_html){
							if('none'==data_html){
								indicator.css('display','none');
							}
							else{
								indicator.before(data_html);
								update_post_payout(user.default_currency);
								update_posts_dates();
								update_posts_view();
								indicator.find('.fa-spinner').removeClass('fa-spin');
								indicator.attr('data-busy','0');
							}
						}
					});
				}
			}
		}
	});
}
function set_parallax_background(selector){
	$(selector).each(function(){
		var current_parallax=$(this);
		var speed=current_parallax.attr('data-parallax-speed');
		var offset=current_parallax.offset();
		top_px_translate=0;
		viewable_height=25;
		if($(window).scrollTop()+$(window).height()>offset.top+viewable_height){
			top_px_translate=-(($(window).scrollTop()+$(window).height()-offset.top-viewable_height)/speed);
		}
		if(top_px_translate<-(current_parallax.height()-current_parallax.parent().outerHeight())){
			top_px_translate=-(current_parallax.height()-current_parallax.parent().outerHeight());
		}
		current_parallax.css('transform','translate3d(0px, '+top_px_translate+'px, 0px)');
	});
}
function rebuild_user_cards(){
	if(1==user.verify){
		$('.user-card').each(function(i,el){
			var user_card_login=$(el).attr('data-user-login');
			var subscribed=parseInt($(el).attr('data-subscribed'));
			var ignored=parseInt($(el).attr('data-ignored'));
			var subscribed_by=parseInt($(el).attr('data-subscribed-by'));
			var ignored_by=parseInt($(el).attr('data-ignored-by'));
			var actions='';
			if(!subscribed){
				if(ignored){
					actions=actions+'<a class="button button-line action-stop-ignore action-confirm">'+l10n.user_card.unignore+'</a>';
				}
				else{
					actions=actions+'<a class="button button-line action-add-friend">'+l10n.user_card.subscribe+'</a>';
					actions=actions+'<a class="button button-line action-add-ignore">'+l10n.user_card.ignore+'</a>';
				}
				if(ignored_by){
					actions=actions+'<div class="ignored_by_text">'+l10n.user_card.ignoring+'</div>';
				}
				if(subscribed_by){
					actions=actions+'<div class="subscribed_by_text">'+l10n.user_card.subscriber+'</div>';
				}
			}
			else{
				if(subscribed_by){
					actions=actions+'<a class="button button-line action-stop-friend action-confirm">'+l10n.user_card.friended+'</a>';
				}
				else{
					actions=actions+'<a class="button button-line action-stop-friend action-confirm">'+l10n.user_card.subscribed+'</a>';
				}
				if(ignored_by){
					actions=actions+'<div class="ignored_by_text">'+l10n.user_card.ignoring+'</div>';
				}
			}
			$(el).find('.user-card-actions').html(actions);
		});
	}
	else{
		window.setTimeout('rebuild_user_cards()',100);
	}
}
function detect_tz(){
	return (((-1)*(new Date().getTimezoneOffset()))/60);
}
function change_feed_view_mode(){
	if('all'==user.feed_view_mode){
		user.feed_view_mode='only_posts';
	}
	else{
		if('only_posts'==user.feed_view_mode){
			user.feed_view_mode='only_reposts';
		}
		else{
			user.feed_view_mode='all';
		}
	}
	localStorage.setItem('feed_view_mode',user.feed_view_mode);
	if(0<$('.feed_view_mode').length){
		$('.feed_view_mode').html(feed_view_modes[user.feed_view_mode]);
		apply_feed_view_mode();
	}
}
function apply_feed_view_mode(){
	$('.post-card').each(function(){
		var post=true;
		var repost=false;
		if(0<$(this).find('.post-reblog-info').length){
			var post=false;
			var repost=true;
		}
		if('all'==user.feed_view_mode){
			$(this).css('display','block');
		}
		if('only_posts'==user.feed_view_mode){
			if(repost){
				$(this).css('display','none');
			}
			else{
				$(this).css('display','block');
			}
		}
		if('only_reposts'==user.feed_view_mode){
			if(post){
				$(this).css('display','none');
			}
			else{
				$(this).css('display','block');
			}
		}
	});
	posts_list_filter(false);
	check_load_more();
}
function change_blog_view_mode(){
	if('all'==blog_view_mode){
		blog_view_mode='only_posts';
	}
	else{
		if('only_posts'==blog_view_mode){
			blog_view_mode='only_reposts';
		}
		else{
			blog_view_mode='all';
		}
	}
	$('.blog_view_mode').html(feed_view_modes[blog_view_mode]);
	apply_blog_view_mode();
}
function apply_blog_view_mode(){
	$('.post-card').each(function(){
		var post=true;
		var repost=false;
		if($(this).attr('data-reblog-id')){//checking repost
			var post=false;
			var repost=true;
		}
		if('all'==blog_view_mode){
			$(this).css('display','block');
		}
		if('only_posts'==blog_view_mode){
			if(repost){
				$(this).css('display','none');
			}
			else{
				$(this).css('display','block');
			}
		}
		if('only_reposts'==blog_view_mode){
			if(post){
				$(this).css('display','none');
			}
			else{
				$(this).css('display','block');
			}
		}
	});
	posts_list_filter(false);
	check_load_more();
}
function blogpost_view(){
	if(1==user.blogpost_show_menu){
		if(0<$('.page-type.page-max-wrapper.blogpost').length){
			$('.page-type.blogpost').addClass('page-wrapper');
			$('.page-type.blogpost').removeClass('page-max-wrapper');
			$('.menu').removeClass('collapsed');
			$('.adaptive-menu').css('display','');
		}
	}
}
function local_user_init(){
	window.clearTimeout(user_profile_load_timer);
	if(-1==user.tz){
		temp_tz=localStorage.getItem('tz');
		if(!temp_tz){
			temp_tz=detect_tz();
		}
		user.tz=temp_tz;
		localStorage.setItem('tz',user.tz);
	}
	if(100==user.post_percent){
		if(!isNaN(localStorage.getItem('comment_percent'))){
			user.post_percent=parseInt(localStorage.getItem('post_percent'));
			localStorage.setItem('post_percent',user.post_percent);
		}
	}
	if(100==user.comment_percent){
		if(!isNaN(localStorage.getItem('comment_percent'))){
			user.comment_percent=parseInt(localStorage.getItem('comment_percent'));
			localStorage.setItem('comment_percent',user.comment_percent);
		}
	}
	if(0==user.hide_flag_action){
		if(!isNaN(localStorage.getItem('hide_flag_action'))){
			user.hide_flag_action=parseInt(localStorage.getItem('hide_flag_action'));
			localStorage.setItem('hide_flag_action',user.hide_flag_action);
		}
	}
	if(0==user.hide_tags_preview_action){
		if(!isNaN(localStorage.getItem('hide_tags_preview_action'))){
			user.hide_tags_preview_action=parseInt(localStorage.getItem('hide_tags_preview_action'));
			localStorage.setItem('hide_tags_preview_action',user.hide_tags_preview_action);
		}
	}
	if(0==user.adult_filter_select){
		if(!isNaN(localStorage.getItem('adult_filter_select'))){
			user.adult_filter_select=parseInt(localStorage.getItem('adult_filter_select'));
			localStorage.setItem('adult_filter_select',user.adult_filter_select);
		}
	}
	if(!isNaN(localStorage.getItem('feed_max_post_id'))){
		user.feed_max_post_id=parseInt(localStorage.getItem('feed_max_post_id'));
	}
	if(!isNaN(localStorage.getItem('post_autovote'))){
		user.post_autovote=parseInt(localStorage.getItem('post_autovote'));
	}
	localStorage.getItem('draft')
	user.feed_view_mode='all';
	if(null!=localStorage.getItem('feed_view_mode')){
		user.feed_view_mode=localStorage.getItem('feed_view_mode');
		if(0<$('.feed_view_mode').length){
			$('.feed_view_mode').html(feed_view_modes[user.feed_view_mode]);
			apply_feed_view_mode();
		}
	}
	else{
		localStorage.setItem('feed_view_mode',user.feed_view_mode);
	}
	user.default_currency='GBG';
	if(null!=localStorage.getItem('default_currency')){
		user.default_currency=localStorage.getItem('default_currency');
	}
	else{
		localStorage.setItem('default_currency',user.default_currency);
	}
	if(0<$('.blog_view_mode').length){
		$('.blog_view_mode').html(feed_view_modes[blog_view_mode]);
		apply_blog_view_mode();
	}
	user.blogpost_show_menu=0;
	if(!isNaN(localStorage.getItem('blogpost_show_menu'))){
		user.blogpost_show_menu=parseInt(localStorage.getItem('blogpost_show_menu'));
		localStorage.setItem('blogpost_show_menu',user.blogpost_show_menu);
		blogpost_view();
	}
	if(null!=localStorage.getItem('login')){
		user.login=localStorage.getItem('login');
		$('.login-form input[name=login]').val(user.login);
	}
	if(null!=localStorage.getItem('posting_key')){
		user.posting_key=localStorage.getItem('posting_key');
		if(isJsonString(user.posting_key)){
			user.posting_key=sjcl.decrypt('goldvoice.club',localStorage.getItem('posting_key'));
		}
		else{
			localStorage.setItem('posting_key',sjcl.encrypt('goldvoice.club',user.posting_key));
		}
		if(null!=localStorage.getItem('active_key')){
			user.active_key=localStorage.getItem('active_key');
			if(isJsonString(user.active_key)){
				user.active_key=sjcl.decrypt('goldvoice.club',localStorage.getItem('active_key'));
			}
			else{
				localStorage.setItem('active_key',sjcl.encrypt('goldvoice.club',user.active_key));
			}
		}
		gate.api.getChainProperties(function(err,result){local_user_check();});
	}
	else{
		gate.api.getChainProperties(function(err,result){});
	}
}
function cookie_value(name){
	var value='; ' + document.cookie;
	var parts=value.split('; ' + name + '=');
	if (parts.length == 2)
		return parts.pop().split(';').shift();
}
function escape_html(text){
	if(typeof text !== 'undefined'){
		var map={
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g,function(m){return map[m];});
	}
	else{
		return '';
	}
}
function update_user_metadata(){
	if(''!=user.login){
		gate.api.getAccounts([user.login],function(err,response){
			if(!err){
				if(typeof response[0].json_metadata !== 'undefined'){
					let json_metadata=response[0].json_metadata;
					if(''!=json_metadata){
						let metadata=JSON.parse(json_metadata);
					}
					if(typeof response[0].voting_power !== 'undefined'){
						let last_vote_time=Date.parse(response[0].last_vote_time);
						let delta_time=parseInt((new Date().getTime() - last_vote_time+(new Date().getTimezoneOffset()*60000))/1000);
						let voting_power=response[0].voting_power;
						let new_voting_power=parseInt(voting_power+(delta_time/43.2));
						if(new_voting_power>10000){
							new_voting_power=10000;
						}
						let delta_time_min=parseInt(delta_time/60);
						$('.menu-energy span').html(''+(new_voting_power/100)+'%');
					}
					if(0<$('.post-energy span').length){
						if(typeof response[0].post_bandwidth !== 'undefined'){
							let delta_time_min=parseInt((new Date().getTime() - Date.parse(response[0].last_root_post)+(new Date().getTimezoneOffset()*60000))/60000);
							let new_post_bandwidth=parseInt(((1440 - delta_time_min ) / 1440 * parseInt(response[0].post_bandwidth) ) + 10000);
							let reward_weight=10000;
							if(0<new_post_bandwidth){
								reward_weight=(40000*40000*10000)/(new_post_bandwidth*new_post_bandwidth);
								if(reward_weight>10000){
									reward_weight=10000;
								}
							}
							$('.post-energy span').html(''+(reward_weight/100).toFixed(2)+'%');
						}
					}
				}
			}
		});
		$('.user-balance').each(function(){
			if(''!=$(this).attr('data-login')){
				let test_login=$(this).attr('data-login');
				gate.api.getDynamicGlobalProperties(function(err,result){
					if(!err){
						let total_vesting_fund_steem=parseFloat(result.total_vesting_fund_steem.split(' ')[0]);
						let total_vesting_shares=parseFloat(result.total_vesting_shares.split(' ')[0]);
						preset.currencies_price.sg_per_vests=total_vesting_fund_steem/total_vesting_shares;
						gate.api.getAccounts([test_login],function(err,response){
							if(!err){
								let balance=parseFloat(response[0].balance.split(' ')[0]);
								let sbd_balance=parseFloat(response[0].sbd_balance.split(' ')[0]);
								let savings_balance=parseFloat(response[0].savings_balance.split(' ')[0]);
								let savings_sbd_balance=parseFloat(response[0].savings_sbd_balance.split(' ')[0]);
								let vesting_shares=parseFloat(response[0].vesting_shares.split(' ')[0]);
								$('.user-balance-golos span').html(balance.toFixed(3));
								$('.user-balance-gbg span').html(sbd_balance.toFixed(3));
								$('.user-balance-savings-golos span').html(savings_balance.toFixed(3));
								$('.user-balance-savings-gbg span').html(savings_sbd_balance.toFixed(3));
								$('.user-balance-sg span.user-balance-sg-amount').html((vesting_shares*preset.currencies_price.sg_per_vests).toFixed(3));
								if('0.000000 GESTS'==response[0].vesting_withdraw_rate){
									$('.user-balance-powerdown').css('display','none');
									$('.user-balance-powerdown').attr('title','');
								}
								else{
									let powerdown_time=Date.parse(response[0].next_vesting_withdrawal);
									if(powerdown_time>0){
										$('.user-balance-powerdown').css('display','inline-block');
										$('.user-balance-powerdown').attr('title','Следующее понижение состоится '+date_str(powerdown_time-(new Date().getTimezoneOffset()*60000),true,true)+' в размере: '+(parseFloat(response[0].vesting_withdraw_rate.split(' ')[0])*preset.currencies_price.sg_per_vests).toFixed(3)+' GOLOS');
									}
								}
								update_post_payout(user.default_currency);
							}
						});
					}
				});
			}
		});
	}
}
function update_user_block(){
	if(1==user.verify){
		if(''==user.profile.avatar){
			user.profile.avatar='https://goldvoice.club/images/noava.png';
		}
		var user_block_menu='';
		var energy_value='empty';
		if(typeof user.balance !== 'undefined'){
			if(user.balance.voting_power>80){
				energy_value='full';
			}
			else if(user.balance.voting_power>60){
				energy_value='three-quarters';
			}
			else if(user.balance.voting_power>40){
				energy_value='half';
			}
			else if(user.balance.voting_power>20){
				energy_value='quarter';
			}
			user_block_menu=user_block_menu+'<a class="menu-energy right-button" title="'+l10n.global.energy+'"><i class="fa fa-fw fa-battery-'+energy_value+'" aria-hidden="true"></i> <span>'+user.balance.voting_power+'%</span></a>';
			user_block_menu=user_block_menu+'<a class="menu-notifications right-button" title="'+l10n.notifications.page_title+'"><i class="fa fa-bell" aria-hidden="true"></i></a>';
		}
		user_block_menu=user_block_menu+'<a class="menu-add-post right-button" href="/add-post/"><i class="fa fa-fw fa-pencil-square" aria-hidden="true"></i><div class="caption">'+l10n.global.add_post+'</div></a>';
		if(null==user.profile.name){
			user.profile.name=user.profile.login;
		}
		user_block_menu=user_block_menu+'<a class="menu-dropdown right-button"><div class="menu-avatar" title="'+escape_html(user.profile.name)+'"><img src="https://imgp.golos.io/32x32/'+escape_html(user.profile.avatar)+'"></div><i class="fa fa-fw fa-caret-right" aria-hidden="true"></i></a>';
		$('.view-dropdown .menu-my-page').prop('href','/@'+escape_html(user.login)+'/');
		$('#user-block').html(user_block_menu);
		window.setTimeout('bind_menu()',100);
	}
}
function add_multi_account(login,posting_key,active_key){
	let find_current=false;
	for(account in multi_account){
		if(multi_account[account].login==login){
			find_current=true;
			if(''!=posting_key){
				multi_account[account].posting_key=posting_key;
				if(multi_account[account].login==user.login){
					localStorage.setItem('posting_key',sjcl.encrypt('goldvoice.club',multi_account[account].posting_key));
				}
			}
			if(''!=active_key){
				multi_account[account].active_key=active_key;
				if(multi_account[account].login==user.login){
					localStorage.setItem('active_key',sjcl.encrypt('goldvoice.club',multi_account[account].active_key));
				}
			}
		}
	}
	if(false==find_current){
		let buf_account={};
		buf_account.login=login;
		if(''!=posting_key){
			buf_account.posting_key=posting_key;
		}
		if(''!=active_key){
			buf_account.active_key=active_key;
		}
		multi_account.push(buf_account);
	}
	save_multi_account();
	update_multi_account();
}
function select_multi_account(login){
	for(account in multi_account){
		if(multi_account[account].login==login){
			$('.menu-dropdown').removeClass('arrow');
			$('.view-dropdown').css('display','none');
			$('.multi-account-selector-dropdown').css('display','none');
			local_session_clear();
			preset.user_profile={};
			preset.page_refresh=true;
			user_auth(multi_account[account].login,multi_account[account].posting_key,multi_account[account].active_key);
		}
	}
}
function remove_multi_account(login){
	for(account in multi_account){
		if(multi_account[account].login==login){
			multi_account.splice(account,1);
		}
	}
	save_multi_account();
	update_multi_account();
}
function save_multi_account(){
	let multi_account_json=JSON.stringify(multi_account);
	let encrypt=sjcl.encrypt('goldvoice.club',multi_account_json);
	localStorage.setItem('multi_account',encrypt);
}
function update_multi_account(){
	multi_account=[];
	if(null!=localStorage.getItem('multi_account')){
		if(isJsonString(localStorage.getItem('multi_account'))){
			multi_account=JSON.parse(localStorage.getItem('multi_account'));
			if(typeof multi_account.iv !== 'undefined'){
				let decoded=sjcl.decrypt('goldvoice.club',localStorage.getItem('multi_account'));
				if(isJsonString(decoded)){
					multi_account=JSON.parse(decoded);
				}
			}
		}
	}
	for(account in multi_account){
		if(typeof multi_account[account] == 'undefined'){
			multi_account.splice(account,1);
		}
		else{
			if(''==multi_account[account].login){
				multi_account.splice(account,1);
			}
		}
	}
	let find_current=false;
	for(account in multi_account){
		if(multi_account[account].login==user.login){
			find_current=true;
		}
	}
	if(false==find_current){
		let buf_account={};
		buf_account.login=user.login;
		buf_account.posting_key=user.posting_key;
		buf_account.active_key=user.active_key;
		multi_account.push(buf_account);
		save_multi_account();
	}
	if(0<$('.multi-account-list').length){
		let multi_account_list='';
		for(account in multi_account){
			multi_account_list+='<div><a href="/@'+multi_account[account].login+'/" target="_blank">@'+multi_account[account].login+'</a>'+(typeof multi_account[account].active_key !== 'undefined'?' <i class="fa fa-fw fa-key" aria-hidden="true" title="'+l10n.settings.multi_accounts_active_key_saved+'"></i>':'')+' <a class="multi-account-select" data-login="'+multi_account[account].login+'"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> '+l10n.settings.multi_accounts_switch+'</a> <a class="multi-account-remove right" data-login="'+multi_account[account].login+'"><i class="fa fa-fw fa-times" aria-hidden="true"></i> '+l10n.settings.multi_accounts_remove+'</a></div>';
		}
		$('.multi-account-list').html(multi_account_list);
	}
}
function rebuild_session(){
	local_session_clear();
	local_user_init();
}
function user_profile_load(){
	if(typeof preset !== 'undefined'){
		if(typeof preset.user_profile !== 'undefined'){
			if(preset.user_profile !== null){
				if(typeof preset.user_profile.id !== 'undefined'){
					if(parseInt(preset.user_profile.id)>0){
						user.profile=preset.user_profile;
						user.balance=preset.user_balance;
						user_profile_load_timer=0;
						user.verify=1;
						update_user_block();
						update_feed_max_post_id();
						update_notify_feed_count();
						update_notify_replies_count();
						update_notifications_list();
						update_user_witnesses();
						update_comments_view();
						update_multi_account();
						reg_subscribe_to_list();
						$('input[name=login-button]').removeAttr('disabled');
						if(true==preset.page_refresh){
							document.location=document.location;
						}
					}
				}
			}
		}
	}
	if(0==user.verify){
		user_profile_load_attempts++;
		if(user_profile_load_attempts>20){
			user_profile_load_attempts=0;
			local_user_clear();
			local_session_clear();
			$('#user-block').html('<a href="/login/" class="menu-login right-button">'+l10n.menu.login+'</a><a href="https://golos.io/enter_email" target="_blank" class="right-button">'+l10n.menu.registration+'</a>');
		}
		else{
			$('#user-block').html('<a class="right-button"><i class="fa fa-fw fa-spin fa-spinner" aria-hidden="true"></i> '+l10n.global.wait+'</a>');
			$.ajax({
				type:'POST',
				url:'/ajax/user_profile/',
				data:{},
				success:function(data_json){
					data_obj=JSON.parse(data_json);
					if(typeof data_obj.error !== 'undefined'){
						console.log(''+new Date().getTime()+': '+data_obj.error+' - '+data_obj.error_str);
						if('rebuild_session'==data_obj.error){
							rebuild_session();
						}
						else if('wait'==data_obj.error){
							user_profile_load_timer=window.setTimeout('user_profile_load()',1500);
						}
					}
					else
					if(typeof data_obj.id !== 'undefined'){
						user.profile=data_obj;
						user_profile_load_timer=0;
						user.verify=1;
						update_user_block();
						update_feed_max_post_id();
						update_notify_feed_count();
						update_notify_replies_count();
						update_notifications_list();
						update_user_witnesses();
						update_comments_view();
						update_multi_account();
						if(1==preset.forbidden){
							if('/'==document.location.pathname){
								document.location='https://goldvoice.club/@'+user.profile.login+'/';
							}
							else{
								document.location=document.location;
							}
						}
						$('input[name=login-button]').removeAttr('disabled');
						if(true==preset.page_refresh){
							document.location=document.location;
						}
					}
					else
					if(typeof user.profile.id !== 'undefined'){
						user_profile_load_timer=window.setTimeout('user_profile_load()',5000);
					}
				}
			});
		}
	}
}
function local_user_check(){
	user.session_id=cookie_value('session_id');
	if(''==user.session_id){
		user_auth(user.login,user.posting_key,user.active_key);
	}
	else{
		user_profile_load_timer=window.setTimeout('user_profile_load()',100);
	}
}
function local_user_auth(){
	var key=generate_password();
	$.ajax({
		type:'POST',
		url:'/ajax/create_session/',
		data:{'key':key},
		success:function(session){
			var expire = new Date();
			expire.setTime(expire.getTime() + 350 * 24 * 3600 * 1000);
			document.cookie='session_id='+session+'; expires='+expire.toUTCString()+'; path=/; domain=goldvoice.club;';
			user.session_id=session;
		}
	});
	gate.broadcast.customJson(user.posting_key,[],[user.login],'goldvoice','["auth",{"key":"'+key+'"}]',function(err,result){if(!err){local_user_auth_finish();}else{local_user_auth_finish(true);console.log(err);}});
}
function local_user_auth_finish(error=false){
	if(!error){
		$('.login-form .login_error').css('display','none');
		$('#user-block').html('<a class="right-button"><i class="fa fa-fw fa-spin fa-spinner" aria-hidden="true"></i> '+l10n.global.wait+'</a>');
		user_profile_load_timer=window.setTimeout('user_profile_load()',6000);
		close_modal();
	}
	else{
		$('.login-form .login_error').css('display','block');
		$('input[name=login-button]').removeAttr('disabled');
	}
	$('#modal-login').removeClass('pulse');
}
function local_user_clear(){
	localStorage.removeItem('tz');
	localStorage.removeItem('feed_view_mode');
	localStorage.removeItem('login');
	localStorage.removeItem('posting_key');
	localStorage.removeItem('multi_account');
	$('input[name=login-button]').removeAttr('disabled');
	user={};
	multi_account={};
	document.cookie='session_id=; path=/; domain=goldvoice.club;';
	localStorage.clear();
}
function local_session_clear(){
	user={tz:-1,login:'',posting_key:'',verify:0,session_id:'',feed_view_mode:'',profile:{}};
	document.cookie='session_id=; path=/; domain=goldvoice.club;';
}
function show_modal(name){
	$('body').addClass('modal-open');
	$('.modal-overlay').addClass('show');
	outer_width=$('#modal-'+name).outerWidth();
	outer_height=$('#modal-'+name).outerHeight();
	$('#modal-'+name).css('margin-left','-'+(outer_width/2)+'px');
	$('#modal-'+name).css('margin-top','-'+(outer_height/2)+'px');
	$('#modal-'+name).addClass('show');
	modal=name;
}
function close_modal(){
	$('body').removeClass('modal-open');
	$('.modal-box').removeClass('show');
	$('.modal').removeClass('show');
	$('.modal-overlay').removeClass('show');
	modal=0;
}
function bind_menu(){
	$('.menu-login').unbind('click');
	$('.menu-login').bind('click',function(e){
		show_modal('login');
		$('.login-form input[name=login]').focus();
		e.preventDefault();
	});
	$('.menu-energy').unbind('click');
	$('.menu-energy').bind('click',function(e){
		if('none'==$('.view-dropdown-currencies').css('display')){
			close_dropdown();
			var offset=$(this).offset();
			$('.view-dropdown-currencies').css('top','48px');
			var left_position=(offset.left+($(this).outerWidth()/2)-100);
			if(window_width<(left_position+200)){
				left_position=window_width-$(this).outerWidth()*2;
			}
			$('.view-dropdown-currencies').css('left',left_position+'px');
			$('.menu-energy').addClass('arrow');
			$('.view-dropdown-currencies').css('display','block');
		}
		else{
			$('.menu-energy').removeClass('arrow');
			$('.view-dropdown-currencies').css('display','none');
		}
	});
	$('.menu-notifications').unbind('click');
	$('.menu-notifications').bind('click',function(e){
		if('none'==$('.view-dropdown-notifications').css('display')){
			close_dropdown();
			var offset=$(this).offset();
			$('.view-dropdown-notifications').css('top','48px');
			var left_position=(offset.left+($(this).outerWidth()/2)-150);
			if(window_width<(left_position+200)){
				left_position=window_width-$(this).outerWidth()*2;
			}
			$('.view-dropdown-notifications').css('left',left_position+'px');
			$('.menu-notifications').addClass('arrow');
			$('.view-dropdown-notifications').css('display','block');
		}
		else{
			$('.menu-notifications').removeClass('arrow');
			$('.view-dropdown-notifications').css('display','none');
		}
	});
	$('.menu-dropdown').unbind('click');
	$('.menu-dropdown').bind('click',function(e){
		if($(this).find('.fa').hasClass('fa-caret-right')){
			close_dropdown();
			var offset=$(this).offset();
			$('.view-dropdown').css('top','48px');
			var left_position=(offset.left+($(this).outerWidth()/2)-75);
			if(window_width<(left_position+150)){
				left_position=window_width-$(this).outerWidth()*2;
			}
			//multi-account
			if(0==$('.view-dropdown').find('.multi-account-selector').length){
				let multi_account_list='';
				for(account in multi_account){
					if(multi_account[account].login!=user.login){
						multi_account_list+='<a class="multi-account-select button button-line" data-login="'+multi_account[account].login+'">@'+multi_account[account].login+'</a>';
					}
				}
				if(''!=multi_account_list){
					$('.view-dropdown').prepend('<div class="multi-account-selector-dropdown">'+multi_account_list+'</div><a class="multi-account-selector button button-line">'+l10n.global.multi_accounts+'</a>');
				}
			}
			$('.view-dropdown').css('left',left_position+'px');
			$('.menu-dropdown').addClass('arrow');
			$('.view-dropdown').css('display','block');
			$(this).find('.fa').addClass('fa-caret-down');
			$(this).find('.fa').removeClass('fa-caret-right');
		}
		else{
			$('.menu-dropdown').removeClass('arrow');
			$('.view-dropdown').css('display','none');
			$('.multi-account-selector-dropdown').css('display','none');
			$(this).find('.fa').addClass('fa-caret-right');
			$(this).find('.fa').removeClass('fa-caret-down');
		}
	});
	$('.menu-my-page').unbind('click');
	$('.menu-my-page').bind('click',function(e){
		if(typeof user.profile.login !== 'undefined'){
			document.location='/@'+user.profile.login+'/';
		}
	});
	$('.menu-my-friends').unbind('click');
	$('.menu-my-friends').bind('click',function(e){
		if(typeof user.profile.login !== 'undefined'){
			document.location='/@'+user.profile.login+'/friends/';
		}
	});
	$('.menu-logout').unbind('click');
	$('.menu-logout').bind('click',function(e){
		$('#user-block').html('<a href="/login/" class="menu-login right-button">'+l10n.menu.login+'</a><a href="https://golos.io/enter_email" target="_blank" class="right-button">'+l10n.menu.registration+'</a>');
		$('.menu-dropdown').removeClass('arrow');
		$('.view-dropdown').css('display','none');
		$('.multi-account-selector-dropdown').css('display','none');
		local_user_clear();
		bind_menu();
		document.location=document.location;
	});
}
function profile_update(){
	if(0<$('.profile-update').length){
		$('.profile-update').css('opacity','1.0');
		var test_user=user.login;
		gate.api.getAccounts([test_user],function(err,response){
			if(!err){
				let json_metadata=response[0].json_metadata;
				let metadata;
				if(''==json_metadata){
					metadata={};
				}
				else{
					metadata=JSON.parse(json_metadata);
				}

				if(typeof metadata.profile == 'undefined'){
					metadata.profile={};
				}
				if(typeof metadata.profile.about !== 'undefined'){
					$('.profile-update input[name=about]').val(metadata.profile.about);
				}
				if(typeof metadata.profile.name !== 'undefined'){
					$('.profile-update input[name=name]').val(metadata.profile.name);
				}
				if(typeof metadata.profile.location !== 'undefined'){
					$('.profile-update input[name=location]').val(metadata.profile.location);
				}
				if(typeof metadata.profile.website !== 'undefined'){
					$('.profile-update input[name=website]').val(metadata.profile.website);
				}
				if(typeof metadata.profile.profile_image !== 'undefined'){
					$('.profile-update input[name=avatar]').val(metadata.profile.profile_image);
				}
				if(typeof metadata.profile.cover_image !== 'undefined'){
					$('.profile-update input[name=cover]').val(metadata.profile.cover_image);
				}
				if(typeof metadata.profile.telegram !== 'undefined'){
					$('.profile-update input[name=telegram]').val(metadata.profile.telegram);
				}
				if(typeof metadata.profile.background_color !== 'undefined'){
					$('.profile-update input[name=background_color]').val(metadata.profile.background_color);
				}
				if(typeof metadata.profile.gender !== 'undefined'){
					$('.profile-update select[name=gender]').val(metadata.profile.gender);
				}
				if(typeof metadata.profile.ad !== 'undefined'){
					if(typeof metadata.profile.ad.type !== 'undefined'){
						$('.profile-update select[name=ad_type]').val(metadata.profile.ad.type);
					}
					if(typeof metadata.profile.ad.a_ads_id !== 'undefined'){
						$('.profile-update input[name=ad_a_ads_id]').val(metadata.profile.ad.a_ads_id);
					}
					if(typeof metadata.profile.ad.adsense_client !== 'undefined'){
						$('.profile-update input[name=ad_adsense_client]').val(metadata.profile.ad.adsense_client);
					}
					if(typeof metadata.profile.ad.adsense_slot !== 'undefined'){
						$('.profile-update input[name=ad_adsense_slot]').val(metadata.profile.ad.adsense_slot);
					}
					if(typeof metadata.profile.ad.ignore_cashout_time !== 'undefined'){
						if(metadata.profile.ad.ignore_cashout_time){
							$('.profile-update input[name=ad_ignore_cashout_time]').prop('checked','checked');
						}
					}

				}
				if(typeof metadata.profile.adsense !== 'undefined'){//for changes, delete later
					if(typeof metadata.profile.adsense.ad_client !== 'undefined'){
						$('.profile-update input[name=ad_adsense_client]').val(metadata.profile.adsense.ad_client);
					}
					if(typeof metadata.profile.adsense.ad_slot !== 'undefined'){
						$('.profile-update input[name=ad_adsense_slot]').val(metadata.profile.adsense.ad_slot);
					}
					if(metadata.profile.adsense.ignore_cashout_time){
						$('.profile-update input[name=ad_ignore_cashout_time]').prop('checked','checked');
					}
				}
				if(typeof metadata.profile.seo !== 'undefined'){
					if(typeof metadata.profile.seo.show_comments !== 'undefined'){
						if(metadata.profile.seo.show_comments){
							$('.profile-update input[name=seo_show_comments]').prop('checked','checked');
						}
					}
					if(typeof metadata.profile.seo.index_comments !== 'undefined'){
						if(metadata.profile.seo.index_comments){
							$('.profile-update input[name=seo_index_comments]').prop('checked','checked');
						}
					}
				}
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
			}
		});
	}
}
function profile_save(){
	if(0<$('.profile-update').length){
		var test_user=user.login;
		gate.api.getAccounts([test_user],function(err,response){
			if(!err){
				let json_metadata=response[0].json_metadata;
				let metadata;
				if(''==json_metadata){
					metadata={};
				}
				else{
					metadata=JSON.parse(json_metadata);
				}
				if(typeof metadata.profile == 'undefined'){
					metadata.profile={};
				}
				metadata.profile.about=$('.profile-update input[name=about]').val();
				metadata.profile.name=$('.profile-update input[name=name]').val();
				metadata.profile.location=$('.profile-update input[name=location]').val();
				metadata.profile.website=$('.profile-update input[name=website]').val();
				metadata.profile.profile_image=$('.profile-update input[name=avatar]').val();
				metadata.profile.cover_image=$('.profile-update input[name=cover]').val();
				metadata.profile.telegram=$('.profile-update input[name=telegram]').val();
				metadata.profile.gender=$('.profile-update select[name=gender]').val();
				metadata.profile.background_color=$('.profile-update input[name=background_color]').val();
				metadata.profile.background_color=$('.profile-update input[name=background_color]').val();
				if(typeof metadata.profile.ad == 'undefined'){
					metadata.profile.ad={};
				}
				metadata.profile.ad.type=$('.profile-update select[name=ad_type]').val();
				metadata.profile.ad.a_ads_id=$('.profile-update input[name=ad_a_ads_id]').val();
				metadata.profile.ad.adsense_client=$('.profile-update input[name=ad_adsense_client]').val();
				metadata.profile.ad.adsense_slot=$('.profile-update input[name=ad_adsense_slot]').val();
				metadata.profile.ad.ignore_cashout_time=$('.profile-update input[name=ad_ignore_cashout_time]').prop('checked');
				if(typeof metadata.profile.seo == 'undefined'){
					metadata.profile.seo={};
				}
				metadata.profile.seo.show_comments=$('.profile-update input[name=seo_show_comments]').prop('checked');
				metadata.profile.seo.index_comments=$('.profile-update input[name=seo_index_comments]').prop('checked');

				json_metadata=JSON.stringify(metadata);
				console.log(json_metadata);
				//gate.broadcast.accountUpdate(user.owner_key,test_user,response[0].owner,response[0].active,response[0].posting,response[0].memo_key,json_metadata,function(err, result){
				gate.broadcast.accountMetadata(user.posting_key,test_user,json_metadata,function(err, result){
					console.log(err);
					if(!err){
						add_notify('<strong>ОК</strong> Ваш профиль был успешно обновлен',10000,false);
					}
					else{
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
					}
				});
				/*
				gate.broadcast.accountUpdate(user.active_key,test_user,undefined,undefined,undefined,response[0].memo_key,json_metadata,function(err, result){
					console.log(err);
					if(!err){
						add_notify('<strong>ОК</strong> Ваш профиль был успешно обновлен',10000,false);
					}
					else{
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
					}
				});*/
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
			}
		});
	}
}
function wallet_savings_cancel(){
	if(0<$('.wallet_action').length){
		if(''!=user.active_key){
			var test_user=user.login;
			if(typeof user.active_login !== 'undefined'){
				if(''!=user.active_login){
					test_user=user.active_login;
				}
			}
			var request=$('.wallet-savings-cancel input[name=request_code]').val();
			var memo='CANCEL WITHDRAW SAVINGS: '+request;
			gate.broadcast.cancelTransferFromSavings(user.active_key,test_user,parseInt(request),function(err,result){
				if(!err){
					let tr_html='<tr class="wallet-history-out new"><td>'+date_str(-1,true,true)+'</td><td>'+test_user+'</td><td>&mdash;</td><td>&mdash;</td><td>&mdash;</td><td class="wallet-memo-set">'+escape_html(memo)+'</td></tr>';
					$('.wallet-history tbody').prepend(tr_html);
					$('.wallet-savings-cancel-action').removeClass('waiting');
					update_user_wallet(false);
				}
				else{
					console.log(err);
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
					$('.wallet-savings-cancel-action').removeClass('waiting');
				}
			});
		}
	}
}
function wallet_savings_withdraw(){
	if(0<$('.wallet_action').length){
		if(''!=user.active_key){
			var test_user=user.login;
			if(typeof user.active_login !== 'undefined'){
				if(''!=user.active_login){
					test_user=user.active_login;
				}
			}
			var amount=parseFloat($('.wallet-savings-withdraw-form input[name=amount]').val().replace(',','.')).toFixed(3);
			var asset=$('.wallet-savings-withdraw-form select[name=asset]').val();
			var request=parseInt(new Date().getTime()/1000);
			var memo='WITHDRAW SAVINGS: '+request;
			gate.broadcast.transferFromSavings(user.active_key,test_user,request,test_user,amount+' '+asset,memo,function(err,result){
				if(!err){
					let tr_html='<tr class="wallet-history-out new"><td>'+date_str(-1,true,true)+'</td><td>'+test_user+'</td><td><span class="wallet-recipient-set">'+test_user+'</span></td><td><span class="wallet-amount-set">'+amount+'</span></td><td><span class="wallet-asset-set">'+asset+'</span></td><td class="wallet-memo-set">'+escape_html(memo)+'</td></tr>';
					$('.wallet-history tbody').prepend(tr_html);
					$('.wallet-savings-withdraw-action').removeClass('waiting');
					update_user_wallet(false);
				}
				else{
					console.log(err);
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
					$('.wallet-savings-withdraw-action').removeClass('waiting');
				}
			});
		}
	}
}
function wallet_transfer(){
	if(0<$('.wallet_action').length){
		if(''!=user.active_key){
			var test_user=user.login;
			if(typeof user.active_login !== 'undefined'){
				if(''!=user.active_login){
					test_user=user.active_login;
				}
			}
			var recipient=$('.wallet_action .wallet-transfer input[name=recipient]').val();
			var amount=parseFloat($('.wallet_action .wallet-transfer input[name=amount]').val().replace(',','.')).toFixed(3);
			var asset=$('.wallet_action .wallet-transfer select[name=asset]').val();
			var memo=$('.wallet_action .wallet-transfer input[name=memo]').val();
			var vesting=$('.wallet_action .wallet-transfer input[name=vesting]').prop('checked');
			var savings=$('.wallet_action .wallet-transfer input[name=savings]').prop('checked');
			if(''!=recipient){
				if(vesting){
					gate.broadcast.transferToVesting(user.active_key,test_user,recipient,amount+' '+asset,function(err,result){
						if(!err){
							let tr_html='<tr class="wallet-history-out new"><td>'+date_str(-1,true,true)+'</td><td>'+test_user+'</td><td><span class="wallet-recipient-set">'+recipient+'</span></td><td><span class="wallet-amount-set">'+amount+'</span></td><td><span class="wallet-asset-set">'+asset+'</span></td><td class="wallet-memo-set">'+escape_html('GOLOS POWER')+'</td></tr>';
							$('.wallet-history tbody').prepend(tr_html);
							if('false'!=$('.wallet_action .wallet-transfer input[name=amount]').attr('data-autoclear')){
								$('.wallet_action .wallet-transfer input[name=amount]').val('0');
							}
							add_notify('<strong>OK</strong> Перевод в Силу Голоса успешно отправлен',2000);
							$('.wallet-send-action').removeClass('waiting');
							update_user_wallet(false);
						}
						else{
							console.log(err);
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
							$('.wallet-send-action').removeClass('waiting');
						}
					});
				}
				else if(savings){
					gate.broadcast.transferToSavings(user.active_key,test_user,recipient,amount+' '+asset,memo,function(err,result){
						if(!err){
							if(memo){
								memo='DEPOSIT SAVINGS: '+memo;
							}
							else{
								memo='DEPOSIT SAVINGS';
							}
							let tr_html='<tr class="wallet-history-out new"><td>'+date_str(-1,true,true)+'</td><td>'+test_user+'</td><td><span class="wallet-recipient-set">'+recipient+'</span></td><td><span class="wallet-amount-set">'+amount+'</span></td><td><span class="wallet-asset-set">'+asset+'</span></td><td class="wallet-memo-set">'+escape_html(memo)+'</td></tr>';
							$('.wallet-history tbody').prepend(tr_html);
							if('false'!=$('.wallet_action .wallet-transfer input[name=amount]').attr('data-autoclear')){
								$('.wallet_action .wallet-transfer input[name=amount]').val('0');
							}
							add_notify('<strong>OK</strong> Перевод в сейф успешно отправлен',2000);
							$('.wallet-send-action').removeClass('waiting');
							update_user_wallet(false);
						}
						else{
							console.log(err);
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
							$('.wallet-send-action').removeClass('waiting');
						}
					});
				}
				else{
					gate.broadcast.transfer(user.active_key,test_user,recipient,amount+' '+asset,memo,function(err,result){
						if(!err){
							let tr_html='<tr class="wallet-history-out new"><td>'+date_str(-1,true,true)+'</td><td>'+test_user+'</td><td><span class="wallet-recipient-set">'+recipient+'</span></td><td><span class="wallet-amount-set">'+amount+'</span></td><td><span class="wallet-asset-set">'+asset+'</span></td><td class="wallet-memo-set">'+escape_html(memo)+'</td></tr>';
							$('.wallet-history tbody').prepend(tr_html);
							if('false'!=$('.wallet_action .wallet-transfer input[name=amount]').attr('data-autoclear')){
								$('.wallet_action .wallet-transfer input[name=amount]').val('0');
							}
							add_notify('<strong>OK</strong> Перевод успешно отправлен',2000);
							$('.wallet-send-action').removeClass('waiting');
							if($('.wallet-send-success').length>0){
								$('.wallet-send-success').addClass('show');
							}
							update_user_wallet(false);
						}
						else{
							console.log(err);
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
							$('.wallet-send-action').removeClass('waiting');
						}
					});
				}
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> Вы не заполнили поле &laquo;Получатель&raquo;',10000,true);
			}
		}
	}
}
function wallet_stop_withdraw_vesting(){
	if(0<$('.wallet_action').length){
		if(''!=user.active_key){
			var test_user=user.login;
			if(typeof user.active_login !== 'undefined'){
				if(''!=user.active_login){
					test_user=user.active_login;
				}
			}
			gate.api.getAccounts([test_user],function(err,response){
				if(!err){
					gate.broadcast.withdrawVesting(user.active_key,test_user,'0.000000 GESTS',function(err,response){
						console.log(err);
						console.log(response);
						update_user_wallet(false);
					});
				}
				else{
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
				}
			});
		}
	}
}
function wallet_withdraw_vesting(){
	if(0<$('.wallet_action').length){
		if(''!=user.active_key){
			var test_user=user.login;
			if(typeof user.active_login !== 'undefined'){
				if(''!=user.active_login){
					test_user=user.active_login;
				}
			}
			gate.api.getDynamicGlobalProperties(function(err,result){
				if(!err){
					let total_vesting_fund_steem=parseFloat(result.total_vesting_fund_steem.split(' ')[0]);
					let total_vesting_shares=parseFloat(result.total_vesting_shares.split(' ')[0]);
					preset.currencies_price.sg_per_vests=total_vesting_fund_steem/total_vesting_shares;
					gate.api.getAccounts([test_user],function(err,response){
						if(!err){
							let current_vesting_shares=response[0].vesting_shares;
							let delegated_vesting_shares=response[0].delegated_vesting_shares;
							let withdraw_amount=parseFloat(current_vesting_shares).toFixed(6) - parseFloat(delegated_vesting_shares).toFixed(6);
							gate.broadcast.withdrawVesting(user.active_key,test_user,withdraw_amount+' GESTS',function(err,response){
								update_user_wallet(false);
							});
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				else{
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
				}
			});
		}
	}
}
function update_user_wallet_history(){
	if(0<$('.wallet-history').length){
		var test_user=user.login;
		if(typeof user.active_login !== 'undefined'){
			if(''!=user.active_login){
				test_user=user.active_login;
			}
		}
		$('.wallet-history tbody').html('<tr><td colspan="6"><center><i class="fa fa-fw fa-spin fa-spinner" aria-hidden="true"></i> '+l10n.global.wait+'</center></td></tr>');
		setTimeout(function(){
			$.ajax({
				type:'POST',
				url:'/ajax/transfers_history_table/',
				data:{'user':test_user},
				success:function(data_html){
					if(''!=data_html){
						$('.wallet-history tbody').html(data_html);
						update_datetime();
					}
				},
			});
		},1000);
	}
}
function update_user_wallet(update_history){
	if(0<$('.wallet_action').length){
		if(''!=user.active_key){
			var test_user=user.login;
			if(typeof user.active_login !== 'undefined'){
				if(''!=user.active_login){
					test_user=user.active_login;
				}
			}
			$('.wallet_action').css('opacity','1.0');
			$('.wallet_action').css('display','block');
			gate.api.getDynamicGlobalProperties(function(err,result){
				if(!err){
					let total_vesting_fund_steem=parseFloat(result.total_vesting_fund_steem.split(' ')[0]);
					let total_vesting_shares=parseFloat(result.total_vesting_shares.split(' ')[0]);
					preset.currencies_price.sg_per_vests=total_vesting_fund_steem/total_vesting_shares;
					gate.api.getAccounts([test_user],function(err,response){
						if(!err){
							//was .wallet_action .wallet-balances selector, but removed first part for custom layout support
							$('.wallet-balances span[rel=golos]').html(response[0].balance.split(' ')[0]);
							$('.wallet-balances span[rel=golos_power]').html((parseFloat(response[0].vesting_shares.split(' ')[0])*preset.currencies_price.sg_per_vests).toFixed(3));
							$('.wallet-balances span[rel=gbg]').html(response[0].sbd_balance.split(' ')[0]);
							if('0.000000 GESTS'==response[0].vesting_withdraw_rate){
								$('.wallet-withdraw-vesting').css('display','inline-block');
								$('.wallet-stop-withdraw-vesting').css('display','none');
								$('.wallet-withdraw-vesting-status').css('display','none');
								$('.wallet-withdraw-vesting-status').attr('title','');
							}
							else{
								$('.wallet-withdraw-vesting').css('display','none');
								$('.wallet-stop-withdraw-vesting').css('display','inline-block');
								let powerdown_time=Date.parse(response[0].next_vesting_withdrawal);
								if(powerdown_time>0){
									$('.wallet-withdraw-vesting-status').css('display','inline-block');
									$('.wallet-withdraw-vesting-status').attr('title','Следующее понижение состоится '+date_str(powerdown_time-(new Date().getTimezoneOffset()*60000),true,true)+' в размере: '+(parseFloat(response[0].vesting_withdraw_rate.split(' ')[0])*preset.currencies_price.sg_per_vests).toFixed(3)+' GOLOS');
								}
							}
							$('.wallet-savings-balances').css('display','none');
							if('0.000 GOLOS'!=response[0].savings_balance){
								$('.wallet-savings-balances').css('display','block');
								$('.wallet-savings-balances span.wallet-savings-balance[rel=golos]').html(parseFloat(response[0].savings_balance.split(' ')[0]).toFixed(3));
							}
							if('0.000 GBG'!=response[0].savings_sbd_balance){
								$('.wallet-savings-balances').css('display','block');
								$('.wallet-savings-balances span.wallet-savings-balance[rel=gbg]').html(parseFloat(response[0].savings_sbd_balance.split(' ')[0]).toFixed(3));
							}
							if(0<parseInt(response[0].savings_withdraw_requests)){
								$('.wallet-savings-cancel').css('display','block');
							}
							else{
								$('.wallet-savings-cancel').css('display','none');
							}
							if(update_history){
								update_user_wallet_history();
							}
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				else{
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
				}
			});
		}
	}
}
function update_user_witnesses(){
	$('.witness-action').removeClass('active');
	$('.witness-action').removeClass('inactive');
	$('.witness-action').attr('title','');
	if(''!=user.login){
		var test_user=user.login;
		if(typeof user.active_login !== 'undefined'){
			if(''!=user.active_login){
				test_user=user.active_login;
			}
		}
		$('.witness-action').attr('title','Дать голос');
		if(''!=user.active_key){
			gate.api.getAccounts([test_user],function(err, response){
				if(!err){
					for(i in response[0].witness_votes){
						witness_login=response[0].witness_votes[i];
						$('.witness-action[data-witness-login="'+witness_login+'"]').addClass('active');
						$('.witness-action[data-witness-login="'+witness_login+'"]').attr('title','Снять голос');
					}
				}
				else{
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
				}
			});
		}
		else{
			gate.api.getAccounts([test_user],function(err, response){
				if(!err){
					for(i in response[0].witness_votes){
						witness_login=response[0].witness_votes[i];
						$('.witness-action[data-witness-login="'+witness_login+'"]').addClass('active');
						$('.witness-action[data-witness-login="'+witness_login+'"]').attr('title','Снять голос');
					}
				}
				else{
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
				}
			});
			$('.witness-action').addClass('inactive');
		}
	}
	else{
		$('.witness-action').addClass('inactive');
	}
}
function unvote_bad_witnesses(){
	if(''!=user.active_key){
		$('.witness-action.active').each(function(){
			let witness_login=$(this).attr('data-witness-login');
			let unvote=false;
			if($(this).closest('.witness-item-tr').hasClass('witness-inactive')){
				unvote=true;
			}
			let feed_red=$(this).closest('.witness-item-tr').find('td.red').length;
			if(feed_red>0){
				unvote=true;
			}
			if(unvote){
				user_witness_unvote(witness_login);
			}
		});
	}
	else{
		add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
	}
}
function unlock_owner_key(login,owner_key){
	user.owner_login=login;
	user.owner_key=owner_key;
	unlock_owner_key_form();
}
function unlock_owner_key_form(){
	let form=$('.unlock-owner-key');
	if(''!=user.owner_key){
		var test_user=user.login;
		if(typeof user.owner_login !== 'undefined'){
			if(''!=user.owner_login){
				test_user=user.owner_login;
			}
		}
		let html='<h3><i class="fa fa-fw fa-unlock-alt" aria-hidden="true"></i> Разблокирован</h3>';
		html+='Вход выполнен<br>Использумый аккаунт: '+test_user;
		html+='<div class="clear"></div>';
		form.html(html);
	}
	else{
		let html='<h3><i class="fa fa-fw fa-lock" aria-hidden="true"></i> Заблокирован</h3>';
		html+='Для выполнения действий на этой странице введите свой ключ владельца:<br>';
		html+='<input type="text" name="login" value="'+user.login+'"> &mdash; Логин<br>';
		html+='<input type="password" name="owner_key" value=""> &mdash; Приватный Owner ключ<br>';
		html+='<input type="submit" class="unlock-owner-key-button" value="Разблокировать доступ">';
		html+='<div class="clear"></div>';
		form.html(html);
	}
}
function check_owner_key(login,owner_key){
	gate.api.getAccounts([login], function(err, response){
		if(!err){
			owner_public_key=response[0].owner.key_auths[0][0];
			try{
				let test=gate.auth.wifIsValid(owner_key,owner_public_key);
				if(test===true){
					unlock_owner_key(login,owner_key);
				}
				else{
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_owner_key,10000,true);
				}
			}
			catch(e){
				try{
					let gen_owner=gate.auth.toWif(login,owner_key,'owner');
					let test_gen_pass=gate.auth.wifIsValid(gen_owner,owner_public_key);
					if(test_gen_pass===true){
						unlock_owner_key(login,gen_owner);
					}
					else{
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_owner_key,10000,true);
					}
				}
				catch(e){
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_owner_key,10000,true);
				}
			}
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
		}
	});
}
function unlock_active_key(login,active_key){
	user.active_login=login;
	user.active_key=active_key;
	if(0<$('.unlock-active-key input[name=save_active_key]:checked').length){
		add_multi_account(user.active_login,'',user.active_key);
	}
	unlock_active_key_form();
}
function unlock_active_key_update(){
	update_user_witnesses();
	update_user_wallet(true);
}
function unlock_active_key_form(){
	let form=$('.unlock-active-key');
	if(''!=user.active_key){
		var test_user=user.login;
		if(typeof user.active_login !== 'undefined'){
			if(''!=user.active_login){
				test_user=user.active_login;
			}
		}
		let html='<h3><i class="fa fa-fw fa-unlock-alt" aria-hidden="true"></i> Разблокирован</h3>';
		html+='Вход выполнен<br>Использумый аккаунт: '+test_user;
		html+='<div class="clear"></div>';
		form.html(html);
		unlock_active_key_update();
		if(typeof form.attr('data-action') != 'undefined'){
			setTimeout(form.attr('data-action')+'(\''+test_user+'\',\''+test_user+'\')',100);
		}
	}
	else{
		let preset_login='';
		if(typeof form.attr('data-preset-login') != 'undefined'){
			preset_login=form.attr('data-preset-login');
		}
		let html='<h3><i class="fa fa-fw fa-lock" aria-hidden="true"></i> Заблокирован</h3>';
		html+='Для выполнения действий на этой странице введите пароль:<br>';
		html+='<input type="text" name="login" value="'+(user.login?user.login:preset_login)+'"> &mdash; Логин<br>';
		html+='<input type="password" name="active_key" value=""> &mdash; Пароль или приватный Active ключ<br>';
		html+='<label><input type="checkbox" name="save_active_key"> &mdash; Запомнить ключ</label><br>';
		html+='<input type="submit" class="unlock-active-key-button" value="Разблокировать доступ">';
		html+='<div class="clear"></div>';
		form.html(html);
	}
}
function check_active_key(login,active_key){
	gate.api.getAccounts([login], function(err, response){
		if(!err){
			active_public_key=response[0].active.key_auths[0][0];
			try{
				let test=gate.auth.wifIsValid(active_key,active_public_key);
				if(test===true){
					unlock_active_key(login,active_key);
				}
				else{
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
				}
			}
			catch(e){
				try{
					let gen_active=gate.auth.toWif(login,active_key,'active');
					let test_gen_pass=gate.auth.wifIsValid(gen_active,active_public_key);
					if(test_gen_pass===true){
						unlock_active_key(login,gen_active);
					}
					else{
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
					}
				}
				catch(e){
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
				}
			}
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
		}
	});
}
function user_witness_vote(witness_login){
	if(''!=user.active_key){
		var test_user=user.login;
		if(typeof user.active_login !== 'undefined'){
			if(''!=user.active_login){
				test_user=user.active_login;
			}
		}
		gate.broadcast.accountWitnessVote(user.active_key,test_user,witness_login,true,function(err, result){
			if(!err){
				$('.witness-action[data-witness-login="'+witness_login+'"]').addClass('active');
				$('.witness-action[data-witness-login="'+witness_login+'"]').attr('title','Снять голос');
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
			}
		});
	}
}
function user_witness_unvote(witness_login){
	if(''!=user.active_key){
		var test_user=user.login;
		if(typeof user.active_login !== 'undefined'){
			if(''!=user.active_login){
				test_user=user.active_login;
			}
		}
		gate.broadcast.accountWitnessVote(user.active_key,test_user,witness_login,false,function(err, result){
			if(!err){
				$('.witness-action[data-witness-login="'+witness_login+'"]').removeClass('active');
				$('.witness-action[data-witness-login="'+witness_login+'"]').attr('title','Дать голос');
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
			}
		});
	}
}
function user_auth(login,posting_key,active_key){
	if(login){
		login=login.toLowerCase();
		if('@'==login.substring(0,1)){
			login=login.substring(1);
		}
		login=login.trim();
		$('#modal-login').addClass('pulse');
		gate.api.getAccounts([login],function(err,response){
			if(typeof response[0] !== 'undefined'){
				let public_wif_posting=response[0].posting.key_auths[0][0];
				if(posting_key){
					let error=false;
					if('GLS'==posting_key.substring(0,3)){
						error=true;
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.public_key,10000,true);
						local_user_clear();
						$('#modal-login').removeClass('pulse');
					}
					try{
						let test_public=gate.auth.wifIsValid(posting_key,public_wif_posting);
					}
					catch(e){
						let new_posting_key=gate.auth.toWif(login,posting_key,'posting');
						try{
							test_public=gate.auth.wifIsValid(new_posting_key,public_wif_posting);
							posting_key=new_posting_key;
						}
						catch(e2){
							error=true;
							add_notify('<strong>'+l10n.global.error_caption+'</strong> Пароль не подходит',10000,true);
							local_user_clear();
							$('#modal-login').removeClass('pulse');
						}
					}
					if(!error){
						$('input[name=login-button]').attr('disabled','disabled');
						user.login=login;
						localStorage.setItem('login',user.login);
						user.posting_key=posting_key;
						if(typeof active_key == 'undefined'){
							active_key='';
						}
						user.active_key=active_key;
						localStorage.setItem('posting_key',sjcl.encrypt('goldvoice.club',user.posting_key));
						localStorage.setItem('active_key',sjcl.encrypt('goldvoice.club',user.active_key));
						local_user_auth();
					}
				}
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> Данный аккаунт не обнаружен',10000,true);
				local_user_clear();
				$('#modal-login').removeClass('pulse');
			}
		});
	}
}
function app_keyboard(e){
	if(!e)e=window.event;
	var key=(e.charCode)?e.charCode:((e.keyCode)?e.keyCode:((e.which)?e.which:0));
	if(key==27){
		if(0!=modal){
			e.preventDefault();
			close_modal();
		}
	}
	if(key==13){
		if('login'==modal){
			e.preventDefault();
			user_auth($('.login-form input[name=login]').val(),$('.login-form input[name=posting_key]').val());
		}
		if($('.search-textbox:focus').length>0){
			document.location='/search?q='+$('.search-textbox:focus').val();
		}
		if($('.unlock-active-key input[name=active_key]:focus').length>0){
			$('.unlock-active-key-button').click();
		}
	}
}
function execute_user_card_action(){
	if(1==user.verify){
		if(1==user_card_action.wait){
			var allow=false;
			if(0==user_card_action.need_confirmation){
				allow=true;
			}
			else{
				if(1==user_card_action.confirmation){
					allow=true;
				}
			}
			if(allow){
				if('subscribe'==user_card_action.action){
					var json=JSON.stringify(['follow',{follower:user.login,following:user_card_action.login,what:['blog']}]);
					gate.broadcast.customJson(user.posting_key,[],[user.login],'follow',json,function(err, result){
						if(!err){
							$('.user-card[data-user-login="'+user_card_action.login+'"]').attr('data-subscribed','1');
							$('.user-card[data-user-login="'+user_card_action.login+'"]').attr('data-ignored','0');
							user_card_action.wait=0;
							rebuild_user_cards();
						}
						else{
							user_card_action.wait=0;
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('ignore'==user_card_action.action){
					var json=JSON.stringify(['follow',{follower:user.login,following:user_card_action.login,what:['ignore']}]);
					gate.broadcast.customJson(user.posting_key,[],[user.login],'follow',json,function(err, result){
						if(!err){
							$('.user-card[data-user-login="'+user_card_action.login+'"]').attr('data-subscribed','0');
							$('.user-card[data-user-login="'+user_card_action.login+'"]').attr('data-ignored','1');
							user_card_action.wait=0;
							rebuild_user_cards();
						}
						else{
							user_card_action.wait=0;
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('clear'==user_card_action.action){
					var json=JSON.stringify(['follow',{follower:user.login,following:user_card_action.login,what:[]}]);
					gate.broadcast.customJson(user.posting_key,[],[user.login],'follow',json,function(err, result){
						if(!err){
							$('.user-card[data-user-login="'+user_card_action.login+'"]').attr('data-subscribed','0');
							$('.user-card[data-user-login="'+user_card_action.login+'"]').attr('data-ignored','0');
							user_card_action.wait=0;
							rebuild_user_cards();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				user_card_action.wait=0;
			}
		}
	}
}
function show_user_card_dropdown(target){
	var offset=$(target).offset();
	$('.user-card-dropdown .confirmation-text').html(user_card_action.confirmation_text);
	$('.user-card-dropdown').css('top',(offset.top+$(target).outerHeight()+2)+'px');
	var left_position=(offset.left+($(target).outerWidth()/2)-($('.user-card-dropdown').outerWidth()/2));

	if(window_width<(left_position+300)){
		var offset_correction=0;
		if(window_width>968){
			offset_correction=(window_width-968)/2;
		}
		$('.user-card-dropdown-arrow').css('margin-left',($('.user-card-dropdown').outerWidth()/2+125 - offset_correction)+'px');
		left_position=window_width-314;
	}
	$('.user-card-dropdown').css('left',left_position+'px');
	$('.user-card-dropdown').css('display','block');
	$('.user-card-confirm').unbind('click');
	$('.user-card-refuse').unbind('click');
	$('.user-card-confirm').bind('click',function(){
		user_card_action.confirmation=1;
		execute_user_card_action();
		$('.user-card-dropdown').css('display','none');
	});
	$('.user-card-refuse').bind('click',function(){
		user_card_action.wait=0;
		$('.user-card-dropdown').css('display','none');
	});
}
function rebuild_comments_flags(){
	$('.comment-card').each(function(){
		var comment_card=$(this);
		comment_card.find('.flag-action').each(function(){
			if('1'==comment_card.attr('data-flag')){
				comment_card.prop('title',l10n.flag_card.comment_time+' '+date_str((parseInt(comment_card.attr('data-vote-time')))*1000,true));
				$(this).find('.fa').addClass('fa-flag');
				$(this).find('.fa').removeClass('fa-flag-o');
			}
			if('0'==comment_card.attr('data-flag')){
				comment_card.prop('title','');
				$(this).find('.fa').addClass('fa-flag-o');
				$(this).find('.fa').removeClass('fa-flag');
			}
		});
	});
}
function rebuild_posts_flags(){
	$('.post-card').each(function(){
		var post_card=$(this);
		post_card.find('.flag-action').each(function(){
			if('1'==post_card.attr('data-flag')){
				post_card.prop('title',l10n.flag_card.time+' '+date_str((parseInt(post_card.attr('data-vote-time')))*1000,true));
				$(this).find('.fa').addClass('fa-flag');
				$(this).find('.fa').removeClass('fa-flag-o');
			}
			if('0'==post_card.attr('data-flag')){
				post_card.prop('title','');
				$(this).find('.fa').addClass('fa-flag-o');
				$(this).find('.fa').removeClass('fa-flag');
			}
		});
	});
}
function rebuild_comments_votes(){
	$('.comment-card').each(function(){
		var comment_card=$(this);
		comment_card.find('.upvote-action').each(function(){
			if('1'==comment_card.attr('data-vote')){
				$(this).prop('title',l10n.upvote_card.comment_time+' '+date_str((parseInt(comment_card.attr('data-vote-time')))*1000,true));
				$(this).find('.fa').addClass('fa-thumbs-up');
				$(this).find('.fa').removeClass('fa-thumbs-o-up');
			}
			if('0'==comment_card.attr('data-vote')){
				$(this).prop('title','');
				$(this).find('.fa').addClass('fa-thumbs-o-up');
				$(this).find('.fa').removeClass('fa-thumbs-up');
			}
		});
	});
}
function rebuild_posts_votes(){
	$('.post-card').each(function(){
		var post_card=$(this);
		post_card.find('.upvote-action').each(function(){
			if('1'==post_card.attr('data-vote')){
				$(this).prop('title',l10n.upvote_card.time+' '+date_str((parseInt(post_card.attr('data-vote-time')))*1000,true));
				$(this).find('.fa').addClass('fa-thumbs-up');
				$(this).find('.fa').removeClass('fa-thumbs-o-up');
			}
			if('0'==post_card.attr('data-vote')){
				$(this).prop('title','');
				$(this).find('.fa').addClass('fa-thumbs-o-up');
				$(this).find('.fa').removeClass('fa-thumbs-up');
			}
		});
	});
}
function execute_repost_card_action(){
	if(1==user.verify){
		if(1==repost_card_action.wait){
			var allow=false;
			if(0==repost_card_action.need_confirmation){
				allow=true;
			}
			else{
				if(1==repost_card_action.confirmation){
					allow=true;
				}
			}
			if(allow){
				var json_object=['reblog',{account:user.login,author:repost_card_action.login,permlink:repost_card_action.permlink}];
				if(''!=repost_card_action.comment){
					json_object[1].comment=repost_card_action.comment;
				}
				var json=JSON.stringify(json_object);
				let proper_target=repost_card_action.proper_target;
				gate.broadcast.customJson(user.posting_key,[],[user.login],'follow',json,function(err, result){
					if(!err){
						proper_target.find('textarea').val('');
						proper_target.removeClass('reposted');
						proper_target.addClass('reposted');
						repost_card_action.wait=0;
					}
					else{
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
					}
				});
				repost_card_action.wait=0;
			}
		}
	}
}
function execute_vote_card_action(){
	if(1==user.verify){
		if(1==vote_card_action.wait){
			var allow=false;
			if(0==vote_card_action.need_confirmation){
				allow=true;
			}
			else{
				if(1==vote_card_action.confirmation){
					allow=true;
				}
			}
			if(allow){
				let proper_target=vote_card_action.proper_target;
				if('remove_vote_comment'==vote_card_action.action){
					gate.broadcast.vote(user.posting_key,user.login,vote_card_action.login,vote_card_action.permlink,0,function(err, result){
						if(!err){
							proper_target.closest('.comment-card').attr('data-vote','0');
							proper_target.closest('.comment-card').attr('data-flag','0');
							proper_target.closest('.comment-card').attr('data-vote-weight','0');
							proper_target.closest('.comment-card').attr('data-vote-time',''+Math.floor(Date.now() / 1000));
							proper_target.find('span').html(parseInt(proper_target.find('span').html())-1);
							vote_card_action.wait=0;
							rebuild_comments_votes();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('vote_comment'==vote_card_action.action){
					gate.broadcast.vote(user.posting_key,user.login,vote_card_action.login,vote_card_action.permlink,vote_card_action.weight*100,function(err, result){
						if(!err){
							proper_target.closest('.comment-card').attr('data-vote','1');
							proper_target.closest('.comment-card').attr('data-flag','0');
							proper_target.closest('.comment-card').attr('data-vote-weight',vote_card_action.weight*100);
							proper_target.closest('.comment-card').attr('data-vote-time',''+Math.floor(Date.now() / 1000));
							proper_target.find('span').html(1+parseInt(proper_target.find('span').html()));
							vote_card_action.wait=0;
							rebuild_comments_votes();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('remove_flag_comment'==vote_card_action.action){
					gate.broadcast.vote(user.posting_key,user.login,vote_card_action.login,vote_card_action.permlink,0,function(err, result){
						if(!err){
							proper_target.closest('.comment-card').attr('data-vote','0');
							proper_target.closest('.comment-card').attr('data-flag','0');
							proper_target.closest('.comment-card').attr('data-vote-weight','0');
							proper_target.closest('.comment-card').attr('data-vote-time',''+Math.floor(Date.now() / 1000));
							proper_target.find('span').html(parseInt(proper_target.find('span').html())-1);
							vote_card_action.wait=0;
							rebuild_comments_flags();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('flag_comment'==vote_card_action.action){
					gate.broadcast.vote(user.posting_key,user.login,vote_card_action.login,vote_card_action.permlink,-10000,function(err, result){
						if(!err){
							proper_target.closest('.comment-card').attr('data-vote','0');
							proper_target.closest('.comment-card').attr('data-flag','1');
							proper_target.closest('.comment-card').attr('data-vote-weight','-10000');
							proper_target.closest('.comment-card').attr('data-vote-time',''+Math.floor(Date.now() / 1000));
							proper_target.find('span').html(1+parseInt(proper_target.find('span').html()));
							vote_card_action.wait=0;
							rebuild_comments_flags();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('remove_flag_post'==vote_card_action.action){
					gate.broadcast.vote(user.posting_key,user.login,vote_card_action.login,vote_card_action.permlink,0,function(err, result){
						if(!err){
							proper_target.closest('.post-card').attr('data-vote','0');
							proper_target.closest('.post-card').attr('data-flag','0');
							proper_target.closest('.post-card').attr('data-vote-weight','0');
							proper_target.closest('.post-card').attr('data-vote-time',''+Math.floor(Date.now() / 1000));
							proper_target.find('span').html(parseInt(proper_target.find('span').html())-1);
							vote_card_action.wait=0;
							rebuild_posts_flags();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('flag_post'==vote_card_action.action){
					gate.broadcast.vote(user.posting_key,user.login,vote_card_action.login,vote_card_action.permlink,-10000,function(err, result){
						if(!err){
							proper_target.closest('.post-card').attr('data-vote','0');
							proper_target.closest('.post-card').attr('data-flag','1');
							proper_target.closest('.post-card').attr('data-vote-weight','-10000');
							proper_target.closest('.post-card').attr('data-vote-time',''+Math.floor(Date.now() / 1000));
							proper_target.find('span').html(1+parseInt(proper_target.find('span').html()));
							vote_card_action.wait=0;
							rebuild_posts_flags();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('remove_vote_post'==vote_card_action.action){
					gate.broadcast.vote(user.posting_key,user.login,vote_card_action.login,vote_card_action.permlink,0,function(err, result){
						if(!err){
							proper_target.closest('.post-card').attr('data-vote','0');
							proper_target.closest('.post-card').attr('data-flag','0');
							proper_target.closest('.post-card').attr('data-vote-weight','0');
							proper_target.closest('.post-card').attr('data-vote-time',''+Math.floor(Date.now() / 1000));
							proper_target.find('span').html(parseInt(proper_target.find('span').html())-1);
							vote_card_action.wait=0;
							rebuild_posts_votes();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				if('vote_post'==vote_card_action.action){
					gate.broadcast.vote(user.posting_key,user.login,vote_card_action.login,vote_card_action.permlink,vote_card_action.weight*100,function(err, result){
						if(!err){
							proper_target.closest('.post-card').attr('data-vote','1');
							proper_target.closest('.post-card').attr('data-flag','0');
							proper_target.closest('.post-card').attr('data-vote-weight',vote_card_action.weight*100);
							proper_target.closest('.post-card').attr('data-vote-time',''+Math.floor(Date.now() / 1000));
							proper_target.find('span').html(1+parseInt(proper_target.find('span').html()));
							vote_card_action.wait=0;
							rebuild_posts_votes();
						}
						else{
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
				vote_card_action.wait=0;
			}
		}
	}
}
function show_repost_card_dropdown(target){
	$('.vote-card-dropdown').css('display','none');
	var offset=target.offset();
	$('.repost-card-dropdown').css('top',(offset.top+target.outerHeight()+12)+'px');
	$('.repost-card-dropdown-arrow').css('margin-left',($('.repost-card-dropdown').outerWidth()/2 - 10)+'px');
	var left_position=(offset.left+(target.outerWidth()/2)-($('.repost-card-dropdown').outerWidth()/2));
	if(window_width<(left_position+300)){
		var offset_correction=0;
		if(window_width>968){
			offset_correction=(window_width-968)/2;
		}
		left_position=window_width-314;
	}
	if(left_position<0){
		left_position=0;
	}
	$('.repost-card-dropdown').css('left',left_position+'px');
	$('.repost-card-dropdown').css('display','block');
	$('.repost-card-confirm').unbind('click');
	$('.repost-card-refuse').unbind('click');
	$('.repost-card-confirm').bind('click',function(){
		repost_card_action.confirmation=1;
		repost_card_action.comment=$('textarea[name=repost-comment]').val();
		execute_repost_card_action();
		$('.repost-card-dropdown').css('display','none');
	});
	$('.repost-card-refuse').bind('click',function(){
		repost_card_action.wait=0;
		$('.repost-card-dropdown').css('display','none');
	});
}
function show_vote_card_dropdown(target){
	$('.repost-card-dropdown').css('display','none');
	var offset=target.offset();
	if(vote_card_action.confirmation_text){
		$('.vote-card-dropdown .confirmation-text').html(vote_card_action.confirmation_text);
		$('.vote-card-dropdown .confirmation-text').css('display','block');
	}
	else{
		$('.vote-card-dropdown .confirmation-text').css('display','none');
	}
	$('.vote-card-dropdown').css('top',(offset.top+target.outerHeight()+12)+'px');
	$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2)+'px');
	var left_position=(offset.left+(target.outerWidth()/2)-($('.vote-card-dropdown').outerWidth()/2));
	if(window_width<(left_position+300)){
		var offset_correction=0;
		if(window_width>968){
			offset_correction=(window_width-968)/2;
		}
		if('vote_post'==vote_card_action.action){
			$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2+125 - offset_correction)+'px');
		}
		if('remove_vote_post'==vote_card_action.action){
			$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2+125 - offset_correction)+'px');
		}
		if('flag_post'==vote_card_action.action){
			$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2+85 - offset_correction)+'px');
		}
		if('remove_flag_post'==vote_card_action.action){
			$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2+85 - offset_correction)+'px');
		}
		if('vote_comment'==vote_card_action.action){
			$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2+125 - offset_correction)+'px');
		}
		if('remove_vote_comment'==vote_card_action.action){
			$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2+125 - offset_correction)+'px');
		}
		if('flag_comment'==vote_card_action.action){
			$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2+85 - offset_correction)+'px');
		}
		if('remove_flag_comment'==vote_card_action.action){
			$('.vote-card-dropdown-arrow').css('margin-left',($('.vote-card-dropdown').outerWidth()/2+85 - offset_correction)+'px');
		}
		left_position=window_width-314;
	}
	$('.vote-card-dropdown').css('left',left_position+'px');
	$('.vote-card-dropdown').css('display','block');
	$('.vote-card-confirm').unbind('click');
	$('.vote-card-refuse').unbind('click');

	if('vote_post'==vote_card_action.action){
		$('.fix-percent .fix-percent-10[data-percent='+user.post_percent+']').click();
	}
	if('vote_comment'==vote_card_action.action){
		$('.fix-percent .fix-percent-10[data-percent='+user.comment_percent+']').click();
	}
	$('.vote-card-confirm').bind('click',function(){
		vote_card_action.confirmation=1;
		execute_vote_card_action();
		$('.vote-card-dropdown').css('display','none');
	});
	$('.vote-card-refuse').bind('click',function(){
		vote_card_action.wait=0;
		$('.vote-card-dropdown').css('display','none');
	});
}
function sort_comment_find_next(id){
	var comment_level=$('.comments .comment-card[data-id='+id+']').attr('data-level');
	var current_id=0;
	var current_level=0;
	var find=0;
	$('.comments .comment-card[data-id='+id+']').nextAll('.comment-card').each(function(){
		if(0==find){
			current_id=$(this).attr('data-id');
			current_level=$(this).attr('data-level');
			if(current_level<=comment_level){
				find=parseInt(current_id);
			}
		}
	});
	return find;
}
function fast_str_replace(search,replace,str){
	return str.split(search).join(replace);
}
function update_posts_dates(){
	$('.post-date').each(function(){
		var datetime_str=date_str(parseInt($(this).attr('data-timestamp'))*1000,true);
		datetime_str=fast_str_replace(date_str(-1)+' ','',datetime_str);
		$(this).html(datetime_str);
	});
	$('.post-reblog-date').each(function(){
		var datetime_str=date_str(parseInt($(this).attr('data-timestamp'))*1000,true);
		datetime_str=fast_str_replace(date_str(-1)+' ','',datetime_str);
		$(this).html(datetime_str);
	});
}
function update_comments_dates(){
	$('.comment-date').each(function(){
		datetime_str=fast_str_replace(date_str(-1)+' ','',date_str(parseInt($(this).attr('data-timestamp'))*1000,true));
		$(this).html(datetime_str);
	});
}
function sort_new_comments_list(){
	$('.new-comments .comment-card').each(function(){
		var comment_id=parseInt($(this).attr('data-id'));
		if(0==$('.comments .comment-card[data-id='+comment_id+']').length){
			var parent_id=parseInt($(this).attr('data-parent'));
			if(0!=parent_id){
				var parent_comment_next=sort_comment_find_next(parent_id);
				if(0!=parent_comment_next){
					$('.comment-card[data-id='+parent_comment_next+']')[0].outerHTML=$(this)[0].outerHTML+$('.comment-card[data-id='+parent_comment_next+']')[0].outerHTML;
				}
				else{
					var last_comment_id=parseInt($('.comments .comment-card').last().attr('data-id'));
					if(last_comment_id){
						$('.comment-card[data-id='+last_comment_id+']')[0].outerHTML=$('.comment-card[data-id='+last_comment_id+']')[0].outerHTML+$(this)[0].outerHTML;
					}
					else{
						$('.comments').append($(this)[0].outerHTML);
					}
				}
			}
			else{
				var last_comment_id=parseInt($('.comments .comment-card').last().attr('data-id'));
				if(last_comment_id){
					$('.comment-card[data-id='+last_comment_id+']')[0].outerHTML=$('.comment-card[data-id='+last_comment_id+']')[0].outerHTML+$(this)[0].outerHTML;
				}
				else{
					$('.comments').append($(this)[0].outerHTML);
				}
			}
		}
	});
	$('.new-comments').html('');
	$('.comments-count').html($('.comments .comment-card').length);
	update_comments_dates();
	update_comments_view();
}
function update_comments_list(){
	var post_id=$('.post-card').attr('data-id');
	var newest_comment_id=0;
	$('.comments .comment-card').each(function(){
		var comment_id=parseInt($(this).attr('data-id'))
		if(newest_comment_id<comment_id){
			newest_comment_id=comment_id;
		}
	});
	$.ajax({
		type:'POST',
		url:'/ajax/load_new_comments/',
		data:{'post_id':post_id,'last_id':newest_comment_id},
		success:function(data_html){
			if(''!=data_html){
				$('.new-comments').html(data_html);
				window.setTimeout(function(){set_waiting_update_comments_list(0);},100);
				sort_new_comments_list();
			}
		},
	});
	window.clearTimeout(update_comments_list_timer);
	update_comments_list_timer=window.setTimeout(function(){update_comments_list();},update_comments_list_timeout);
	update_comments_list_timeout+=500;
	if(update_comments_list_timeout>20000){
		update_comments_list_timeout=20000;
	}
}
function wait_post(author,permlink){
	$.ajax({
		type:'POST',
		url:'/ajax/post_exist/',
		data:{'author':author,'permlink':permlink},
		success:function(data_json){
			data_obj=JSON.parse(data_json);
			if('ok'==data_obj.status){
				draft={title:'',image:'',text:'',tags:''};
				draft_json=JSON.stringify(draft);
				localStorage.setItem('draft',draft_json);
				document.location='/@'+author+'/'+permlink+'/';
			}
			else{
				setTimeout(function(){wait_post(author,permlink);},1000);
			}
		},
	});
}
function try_upload_percent(e){
	var percent = parseInt(e.loaded / e.total * 100);
	$('#modal-drop-images').html('<i class="fa fa-fw fa-spinner fa-spin" aria-hidden="true"></i> '+l10n.global.loading+' ('+percent+'%)&hellip;');
}
function try_upload(file,input_name){
	if(file.type.match(/image.*/)){
		$('#modal-drop-images').html('<i class="fa fa-fw fa-spinner fa-spin" aria-hidden="true"></i> '+l10n.global.loading+'&hellip;');
		var post_form = new FormData();
		post_form.append('image',file);
		var xhr=new XMLHttpRequest();
		xhr.upload.addEventListener('progress',try_upload_percent,false);
		xhr.open('POST','https://api.imgur.com/3/image.json');
		xhr.onload=function(){
			if(200==xhr.status){
				var img_url = JSON.parse(xhr.responseText).data.link;
				img_url=img_url.replace('http://','https://');
				if(''==input_name){
					if(''==$('input[name=post_image]').val()){
						$('input[name=post_image]').val(img_url);
					}
					if(wysiwyg_active){
						tinyMCE.execCommand('mceInsertContent',false,'\n<img src="'+img_url+'" alt="">\n');
					}
					else{
						$('textarea[name=post_text]').val($('textarea[name=post_text]').val()+'\n'+img_url+'\n');
						$('textarea[name=post_text]').focus();
					}
				}
				else{
					$('input[name='+input_name+']').val(img_url);
				}
				$('#modal-drop-images').html('<i class="fa fa-fw fa-file-image-o" aria-hidden="true"></i> '+l10n.modals.drop_image);
				close_modal();
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.xhr_upload+' '+xhr.status+'',10000,true);
				$('#modal-drop-images').html('<i class="fa fa-fw fa-file-image-o" aria-hidden="true"></i> '+l10n.modals.drop_image);
				close_modal();
			}
		}
		xhr.onerror=function(){
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.upload_image,10000,true);
			close_modal();
		}
		xhr.setRequestHeader('Authorization','Client-ID 5041272c7bd1787');//5041272c7bd1787 goldvoice public gate
		xhr.send(post_form);
	}
}
function payback_recount_status(){
	let count=$('table.post-votes-stats .payback-bonus').length;
	let ignore_count=$('table.post-votes-stats .payback-ignore').length;
	let success_count=$('table.post-votes-stats .payback-success').length;
	let error_count=$('table.post-votes-stats .payback-error').length;
	let payback_ignore_threshold=$('input[name=payback-ignore-threshold]:checked').length;
	var payback_threshold=parseFloat($('input[name=payback-threshold]').val()).toFixed(3);
	var sum_amount=0;
	$('table.post-votes-stats tbody tr').each(function(){
		let payback_td=$(this).find('td.payback-bonus');
		if(!payback_td.hasClass('payback-ignore')){
			let payback_amount=parseFloat(payback_td.text()).toFixed(3);
			if(1==payback_ignore_threshold){
				if(payback_threshold>payback_amount){
					payback_amount=payback_threshold;
				}
			}
			if(payback_td.hasClass('payback-success')){
				sum_amount=parseFloat(parseFloat(sum_amount)+parseFloat(payback_amount)).toFixed(3);
			}
		}
	});
	$('.payback-action').html('Статус рассылки бонусов: '+success_count+' успешно, '+error_count+' ошибок, отправлено '+parseFloat(sum_amount).toFixed(3)+' GBG');
	if((success_count+error_count)==(count-ignore_count)){
		$('.payback-action').removeClass('disabled');
	}
}
function payback_repost_recount_status(){
	let count=$('table.post-reposts-stats .payback-bonus').length;
	let ignore_count=$('table.post-reposts-stats .payback-ignore').length;
	let success_count=$('table.post-reposts-stats .payback-success').length;
	let error_count=$('table.post-reposts-stats .payback-error').length;
	var sum_amount=0;
	$('table.post-reposts-stats tbody tr').each(function(){
		let payback_td=$(this).find('td.payback-bonus');
		if(!payback_td.hasClass('payback-ignore')){
			let payback_amount=parseFloat(payback_td.text()).toFixed(3);
			if(payback_td.hasClass('payback-success')){
				sum_amount=parseFloat(parseFloat(sum_amount)+parseFloat(payback_amount)).toFixed(3);
			}
		}
	});
	$('.payback-repost-action').html('Статус рассылки бонусов: '+success_count+' успешно, '+error_count+' ошибок, отправлено '+parseFloat(sum_amount).toFixed(3)+' GBG');
	if((success_count+error_count)==(count-ignore_count)){
		$('.payback-repost-action').removeClass('disabled');
	}
}
function payback_ignore_stop_list(){
	let payback_stop_list=$('textarea[name=payback-stop-list]').val();
	payback_stop_list=payback_stop_list.replace(',',' ');
	payback_stop_list=payback_stop_list.replace("\n",' ');
	payback_stop_list=payback_stop_list.replace('   ',' ');
	payback_stop_list=payback_stop_list.replace('  ',' ');
	payback_stop_list=payback_stop_list.replace('  ',' ');
	$('textarea[name=payback-stop-list]').val(payback_stop_list);
	if(''!=payback_stop_list){
		localStorage.setItem('payback-payback-stop-list',payback_stop_list);
	}
	let payback_stop_list_arr=payback_stop_list.split(' ');
	if(null!=payback_stop_list_arr){
		payback_stop_list_arr=unique_array(payback_stop_list_arr);
		var users_len = payback_stop_list_arr.length;
		for(var i = 0; i < users_len; i++){
			payback_stop_list_arr[i]=payback_stop_list_arr[i].replace(/^\@/g,'');
			payback_stop_list_arr[i]=payback_stop_list_arr[i].replace(/\.$/g,'');
		}
	}
	$('table.post-votes-stats tbody tr').each(function(){
		let voter=$(this).find('td.voter').attr('data-voter');
		let payback_td=$(this).find('td.payback-bonus');
		if(voter){
			for(i in payback_stop_list_arr){
				if(voter==payback_stop_list_arr[i]){
					payback_td.addClass('payback-ignore');
				}
				if(voter==user.active_login){
					payback_td.addClass('payback-ignore');
				}
			}
		}
	});
}
function payback_repost_ignore_stop_list(){
	let payback_stop_list=$('textarea[name=payback-repost-stop-list]').val();
	payback_stop_list=payback_stop_list.replace(',',' ');
	payback_stop_list=payback_stop_list.replace("\n",' ');
	payback_stop_list=payback_stop_list.replace('   ',' ');
	payback_stop_list=payback_stop_list.replace('  ',' ');
	payback_stop_list=payback_stop_list.replace('  ',' ');
	$('textarea[name=payback-repost-stop-list]').val(payback_stop_list);
	if(''!=payback_stop_list){
		localStorage.setItem('payback-repost-stop-list',payback_stop_list);
	}
	let payback_stop_list_arr=payback_stop_list.split(' ');
	if(null!=payback_stop_list_arr){
		payback_stop_list_arr=unique_array(payback_stop_list_arr);
		var users_len = payback_stop_list_arr.length;
		for(var i = 0; i < users_len; i++){
			payback_stop_list_arr[i]=payback_stop_list_arr[i].replace(/^\@/g,'');
			payback_stop_list_arr[i]=payback_stop_list_arr[i].replace(/\.$/g,'');
		}
	}
	$('table.post-reposts-stats tbody tr').each(function(){
		let voter=$(this).find('td.voter').attr('data-voter');
		let payback_td=$(this).find('td.payback-bonus');
		if(voter){
			for(i in payback_stop_list_arr){
				if(voter==payback_stop_list_arr[i]){
					payback_td.addClass('payback-ignore');
					payback_td.html('0.000');
				}
				if(voter==user.active_login){
					payback_td.addClass('payback-ignore');
				}
			}
		}
	});
}
function send_payback_repost_queue(){
	if(''!=user.active_key){
		var test_user=user.login;
		if(typeof user.active_login !== 'undefined'){
			if(''!=user.active_login){
				test_user=user.active_login;
			}
		}
		if(!$('.payback-repost-action').hasClass('disabled')){
			$('.payback-repost-action').addClass('disabled');
			$('.payback-repost-form input').attr('disabled','disabled');
			$('.payback-repost-form select').attr('disabled','disabled');
			$('.payback-repost-form textarea').attr('disabled','disabled');
			let comment=$('input[name=payback-repost-comment]').val();
			let comment_link=$('input[name=payback-repost-comment-link]:checked').length;
			if(comment_link){
				comment=comment+' '+$('input[name=post-link]').val();
			}
			comment=comment.trim();
			let payback_repost_asset=$('select[name=payback-repost-asset]').val();
			let payback_repost_amount=parseFloat($('input[name=payback-repost-amount]').val()).toFixed(3);
			payback_repost_ignore_stop_list();
			payback_repost_recount_status();
			$('table.post-reposts-stats tbody tr').each(function(){
				let voter=$(this).find('td.voter').attr('data-voter');
				let payback_td=$(this).find('td.payback-bonus');
				if(!payback_td.hasClass('payback-ignore')){
					if(!payback_td.hasClass('payback-success')){
						let payback_amount=parseFloat(payback_td.html()).toFixed(3);
						if(voter){
							gate.broadcast.transfer(user.active_key,test_user,voter,payback_amount+' '+payback_repost_asset,comment,function(err,result){
								if(!err){
									payback_td.addClass('payback-success');
									payback_td.removeClass('payback-error');
									payback_repost_recount_status();
								}
								else{
									console.log(err);
									payback_td.addClass('payback-error');
									add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
									payback_repost_recount_status();
								}
							});
						}
					}
				}
			});
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> Вы уже производили рассылку бонуса',10000,true);
		}
	}
	else{
		add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
	}
}
function send_payback_queue(){
	if(''!=user.active_key){
		var test_user=user.login;
		if(typeof user.active_login !== 'undefined'){
			if(''!=user.active_login){
				test_user=user.active_login;
			}
		}
		if(!$('.payback-action').hasClass('disabled')){
			$('.payback-action').addClass('disabled');
			$('.payback-form input').attr('disabled','disabled');
			$('.payback-form select').attr('disabled','disabled');
			$('.payback-form textarea').attr('disabled','disabled');
			payback_ignore_stop_list();
			payback_recount_status();
			let comment=$('input[name=payback-comment]').val();
			let comment_link=$('input[name=payback-comment-link]:checked').length;
			if(comment_link){
				comment=comment+' '+$('input[name=post-link]').val();
			}
			comment=comment.trim();
			let payback_threshold=parseFloat($('input[name=payback-threshold]').val());
			let payback_ignore_threshold=$('input[name=payback-ignore-threshold]:checked').length;
			$('table.post-votes-stats tbody tr').each(function(){
				let voter=$(this).find('td.voter').attr('data-voter');
				let payback_td=$(this).find('td.payback-bonus');
				if(!payback_td.hasClass('payback-ignore')){
					if(!payback_td.hasClass('payback-success')){
						let payback_amount=parseFloat(payback_td.html()).toFixed(3);
						if(1==payback_ignore_threshold){
							if(payback_threshold>payback_amount){
								payback_amount=payback_threshold;
							}
						}
						if(voter){
							gate.broadcast.transfer(user.active_key,test_user,voter,payback_amount+' GBG',comment,function(err,result){
								if(!err){
									payback_td.addClass('payback-success');
									payback_td.removeClass('payback-error');
									payback_recount_status();
								}
								else{
									console.log(err);
									payback_td.addClass('payback-error');
									add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
									payback_recount_status();
								}
							});
						}
					}
				}
			});
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> Вы уже производили рассылку бонуса',10000,true);
		}
	}
	else{
		add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
	}
}
function recalc_post_votes_payback(){
	if(0<$('input[name=payback-size]').length){
		let payback_size=parseFloat($('input[name=payback-size]').val());
		let payback_threshold=parseFloat($('input[name=payback-threshold]').val());
		let payback_ignore_threshold=$('input[name=payback-ignore-threshold]:checked').length;
		let sum_rshares=0;
		$('table.post-votes-stats tbody tr').each(function(){
			let rshares=parseInt($(this).find('td.weight').html());
			if(rshares>=0){
				sum_rshares+=parseInt($(this).find('td.weight').html());
			}
		});
		$('table.post-votes-stats tbody tr').each(function(){
			let rshares=parseInt($(this).find('td.weight').html());
			let rshares_percent=rshares/sum_rshares;
			let user_payback_size=(rshares_percent*payback_size).toFixed(5);
			$(this).find('td.payback-bonus').html(user_payback_size);
			$(this).find('td.payback-bonus').removeClass('payback-ignore');
			if(0==payback_ignore_threshold){
				if(user_payback_size<payback_threshold){
					$(this).find('td.payback-bonus').addClass('payback-ignore');
				}
			}
		});
		payback_ignore_stop_list();
	}
}
function recalc_post_reposts_payback(){
	if(0<$('input[name=payback-repost-amount]').length){
		let payback_repost_amount=parseFloat($('input[name=payback-repost-amount]').val()).toFixed(3);
		let payback_repost_type=$('select[name=payback-repost-type]').val();
		if('each'==payback_repost_type){
			$('table.post-reposts-stats tbody tr').each(function(){
				$(this).find('td.payback-bonus').html(payback_repost_amount);
				$(this).find('td.payback-bonus').removeClass('payback-ignore');
			});
		}
		else{
			payback_repost_type=payback_repost_type.replace('split_','');
			let sum=0;
			$('table.post-reposts-stats tbody tr').each(function(){
				if(!$(this).find('td.payback-bonus').hasClass('payback-ignore')){
					sum+=parseFloat($(this).find('td[rel='+payback_repost_type+']').html());
				}
			});
			$('table.post-reposts-stats tbody tr').each(function(){
				let current=parseFloat($(this).find('td[rel='+payback_repost_type+']').html());
				let current_percent=current/sum;
				let user_payback_size=(current_percent*payback_repost_amount).toFixed(3);
				$(this).find('td.payback-bonus').html(user_payback_size);
				$(this).find('td.payback-bonus').removeClass('payback-ignore');
			});
		}
		payback_repost_ignore_stop_list();
	}
}
function update_post_votes_stats(){
	$('.post-votes-stats').each(function(){
		let author=$(this).attr('data-author');
		let permlink=$(this).attr('data-permlink');
		gate.api.getContent(author,permlink,-1,function(err,result){
			if(!err){
				let total_payout_value=result.total_payout_value;
				$('.payback-payment-full').html(total_payout_value);
				let part_payout_value=(parseFloat(total_payout_value.split(' ')[0])/2).toFixed(3);
				$('.payback-payment-part').html(part_payout_value);
				let payback_size=part_payout_value*(parseInt($('input[name="payback-percent"]').val())/100);
				$('input[name=payback-size]').val(payback_size);
				for(i in result.active_votes){
					if(0<$('td[data-voter="'+result.active_votes[i].voter+'"]').length){
						let voter_tr=$('td[data-voter="'+result.active_votes[i].voter+'"]').parent();
						voter_tr.find('td.weight').html(result.active_votes[i].rshares);
					}
				}
				recalc_post_votes_payback();
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
			}
		});
	});
}
var update_transfers_history_timer=0;
function update_transfers_history(set_user='',set_target=''){
	var last_transfer_id=0;
	$('.transfers_history tbody tr').each(function(){
		if(typeof $(this).attr('data-tansfer-id') != 'undefined'){
			var find_transfer_id=parseInt($(this).attr('data-tansfer-id'))
			if(find_transfer_id>last_transfer_id){
				last_transfer_id=find_transfer_id;
			}
		}
	});
	if(''!=set_user){
		$('.transfers_history').attr('data-user',set_user);
	}
	if(''!=set_target){
		$('.transfers_history').attr('data-target',set_target);
	}
	$.ajax({
		type:'POST',
		url:'/ajax/transfers_history/',
		data:{user:$('.transfers_history').attr('data-user'),way:$('.transfers_history').attr('data-way'),target:$('.transfers_history').attr('data-target'),currency:$('.transfers_history').attr('data-currency'),transfer_id:last_transfer_id},
		success:function(data_html){
			if(''!=data_html){
				$('.transfers_history tbody').prepend(data_html);
				update_datetime();
			}
		}
	});
	window.clearTimeout(update_transfers_history_timer);
	update_transfers_history_timer=window.setTimeout(function(){update_transfers_history();},5000);
}
function app_mouse(e){
	if(!e)e=window.event;
	var target=e.target || e.srcElement;
	if($(target).closest('.go-top-left-wrapper').length>0){
		scroll_top_action();
	}
	if($(target).closest('.post-card')){
		if($(target).closest('.post-card').hasClass('post-adult')){
			$(target).closest('.post-card').removeClass('post-adult');
			e.preventDefault();
		}
	};
	if($(target).hasClass('profile-select-background-color')){
		$('.profile-update input[name=background_color]').val($(target).attr('rel'));
		$('.header-line').css('background-image','none');
		$('.header-line').css('background-color','#'+$(target).attr('rel'));
	}
	if($(target).hasClass('selectable')){
		$('input[name='+$(target).attr('data-input')+']').val($(target).attr('data-value'));
		$('.selectable[rel='+$(target).attr('rel')+']').removeClass('selected');
		$(target).addClass('selected');
	}
	if($(target).hasClass('tag') && $(target).parent().hasClass('posts-list-filter-show')){
		$(target).remove();
		if(0<$('.feed_view_mode').length){
			apply_feed_view_mode();
		}
		if(0<$('.blog_view_mode').length){
			apply_blog_view_mode();
		}
		posts_list_filter(true);
	}
	if($(target).hasClass('tag') && $(target).parent().hasClass('posts-list-filter-preset')){
		if('link'==$(target).attr('data-type')){
			document.location=$(target).attr('data-tag');
		}
		else
		if('external'==$(target).attr('data-type')){
			window.open('https://goldvoice.club/tags/'+$(target).attr('data-tag')+'/');
		}
		else{
			if('single'==$(target).attr('data-type')){
				posts_list_filter_clear_action();
			}
			if(''!=$(target).attr('data-tag')){
				post_list_filter_show_add($(target).attr('data-tag'));
			}
			posts_list_filter(true);
		}
	}
	if($(target).hasClass('tag') && $(target).parent().hasClass('posts-list-filter-hide')){
		$(target).remove();
		if(0<$('.feed_view_mode').length){
			apply_feed_view_mode();
		}
		if(0<$('.blog_view_mode').length){
			apply_blog_view_mode();
		}
		posts_list_filter(true);
	}
	if($(target).hasClass('post-comments') || $(target).parent().hasClass('post-comments')){
		if($(target).closest('.post-card')){
			document.location='/@'+$(target).closest('.post-card').attr('data-author')+'/'+$(target).closest('.post-card').attr('data-permlink')+'/#comments';
			e.preventDefault();
		}
	}
	if($(target).hasClass('profile-update-action') || $(target).parent().hasClass('profile-update-action')){
		profile_save();
	}
	if($(target).hasClass('wallet-savings-withdraw') || $(target).parent().hasClass('wallet-savings-withdraw')){
		let proper_target=$(target);
		if($(target).parent().hasClass('wallet-savings-withdraw')){
			proper_target=$(target).parent();
		}
		$('.wallet-savings-withdraw-form').css('display','block');
		$('.wallet-savings-withdraw-form input[name=amount]').val($('.wallet-savings-balance[rel='+proper_target.attr('rel')+']').text());
		$('.wallet-savings-withdraw-form select[name=asset]').val(proper_target.attr('data-asset'));
	}
	if($(target).hasClass('wallet-savings-withdraw-action') || $(target).parent().hasClass('wallet-savings-withdraw-action')){
		let proper_target=$(target);
		if($(target).parent().hasClass('wallet-savings-withdraw-action')){
			proper_target=$(target).parent();
		}
		if(!proper_target.hasClass('waiting')){
			proper_target.addClass('waiting');
			wallet_savings_withdraw();
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.waiting,10000,true);
		}
	}
	if($(target).hasClass('wallet-send-action') || $(target).parent().hasClass('wallet-send-action')){
		let proper_target=$(target);
		if($(target).parent().hasClass('wallet-send-action')){
			proper_target=$(target).parent();
		}
		if(!proper_target.hasClass('waiting')){
			proper_target.addClass('waiting');
			wallet_transfer();
			if(typeof proper_target.attr('data-action') != 'undefined'){
				setTimeout(proper_target.attr('data-action')+'()',100);
			}
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.waiting,10000,true);
		}
	}
	if($(target).hasClass('posts-list-filter-hide-action') || $(target).parent().hasClass('posts-list-filter-hide-action')){
		posts_list_filter_hide_action();
	}
	if($(target).hasClass('posts-list-filter-show-action') || $(target).parent().hasClass('posts-list-filter-show-action')){
		posts_list_filter_show_action();
	}
	if($(target).hasClass('posts-list-filter-clear-action') || $(target).parent().hasClass('posts-list-filter-clear-action')){
		posts_list_filter_clear_action();
	}
	if($(target).hasClass('wallet-withdraw-vesting') || $(target).parent().hasClass('wallet-withdraw-vesting')){
		wallet_withdraw_vesting();
	}
	if($(target).hasClass('wallet-stop-withdraw-vesting') || $(target).parent().hasClass('wallet-stop-withdraw-vesting')){
		wallet_stop_withdraw_vesting();
	}
	if($(target).hasClass('wallet-refresh') || $(target).parent().hasClass('wallet-refresh')){
		update_user_wallet(true);
	}
	if($(target).hasClass('wallet-history-filter-all') || $(target).parent().hasClass('wallet-history-filter-all')){
		$('.wallet-history tbody tr').css('display','table-row');
	}
	if($(target).hasClass('wallet-history-filter-in') || $(target).parent().hasClass('wallet-history-filter-in')){
		$('.wallet-history tbody tr').css('display','none');
		$('.wallet-history tbody tr.wallet-history-in').css('display','table-row');
	}
	if($(target).hasClass('wallet-history-filter-out') || $(target).parent().hasClass('wallet-history-filter-out')){
		$('.wallet-history tbody tr').css('display','none');
		$('.wallet-history tbody tr.wallet-history-out').css('display','table-row');
	}
	if($(target).hasClass('wallet-recipient-set')){
		$('.wallet_action .wallet-transfer input[name=recipient]').val($(target).text());
	}
	if(0<$(target).closest('.wallet-memo-set').length){
		$('.wallet_action .wallet-transfer input[name=memo]').val($(target).closest('.wallet-memo-set').text());
	}
	if($(target).hasClass('wallet-balance-set')){
		$('.wallet_action .wallet-transfer input[name=amount]').val(parseFloat($(target).text()));
		if(typeof $(target).attr('data-asset') !== 'undefined'){
			$('.wallet_action .wallet-transfer select[name=asset]').val($(target).attr('data-asset'));
			$('.wallet_action .wallet-transfer select[name=asset]').change();
		}
		if(typeof $(target).attr('data-set-asset') !== 'undefined'){
			$('select[name='+$(target).attr('data-set-asset')+']').val($(target).attr('data-asset'));
			$('select[name='+$(target).attr('data-set-asset')+']').change();
		}
		if(typeof $(target).attr('data-set-amount') !== 'undefined'){
			$('input[name='+$(target).attr('data-set-amount')+']').val(parseFloat($(target).text()).toFixed(3));
		}
	}
	if($(target).hasClass('wallet-amount-set')){
		$('.wallet_action .wallet-transfer input[name=amount]').val(parseFloat($(target).text()));
	}
	if($(target).hasClass('wallet-asset-set')){
		$('.wallet_action .wallet-transfer select[name=asset]').val($(target).text());
		$('.wallet_action .wallet-transfer select[name=asset]').change();
	}
	if($(target).hasClass('multi-account-selector')){
		if('none'==$('.multi-account-selector-dropdown').css('display')){
			$('.multi-account-selector-dropdown').css('display','block');
		}
		else{
			$('.multi-account-selector-dropdown').css('display','none');
		}
	}
	if($(target).hasClass('clear-notifications')||$(target).parent().hasClass('clear-notifications')){
		$.ajax({
			type:'POST',
			url:'/ajax/clear_notifications/',
			data:{'confirm':1},
			success:function(data){
				$('.notify').removeClass('notify-unread');
				update_notifications_list();
				update_notify_replies_count();
			}
		});
	}
	if($(target).hasClass('add-multi-account')){
		let multi_account_login=$('input[name=multi-account-login]').val();
		multi_account_login=multi_account_login.toLowerCase();
		if('@'==multi_account_login.substring(0,1)){
			multi_account_login=multi_account_login.substring(1);
		}
		multi_account_login=multi_account_login.trim();
		$('input[name=multi-account-login]').val(multi_account_login);
		let multi_account_posting_key=$('input[name=multi-account-posting-key]').val();
		let multi_account_active_key=$('input[name=multi-account-active-key]').val();
		gate.api.getAccounts([multi_account_login],function(err, response){
			if(!err){
				posting_public_key=response[0].posting.key_auths[0][0];
				active_public_key=response[0].active.key_auths[0][0];
				try{
					let error=false;
					let test=gate.auth.wifIsValid(multi_account_posting_key,posting_public_key);
					if(test!==true){
						error=true;
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_posting_key,10000,true);
					}
					if(''!=multi_account_active_key){
						let test=gate.auth.wifIsValid(multi_account_active_key,active_public_key);
						if(test!==true){
							error=true;
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
						}
					}
					if(!error){
						$('input[name=multi-account-login]').val('');
						$('input[name=multi-account-posting-key]').val('');
						$('input[name=multi-account-active-key]').val('');
						add_multi_account(multi_account_login,multi_account_posting_key,multi_account_active_key);
					}
				}
				catch(e){
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_posting_key,10000,true);
				}
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
			}
		});
	}
	if($(target).hasClass('multi-account-remove') || $(target).parent().hasClass('multi-account-remove')){
		let proper_target=$(target);
		if($(target).parent().hasClass('multi-account-remove')){
			proper_target=$(target).parent();
		}
		let multi_account_login=proper_target.attr('data-login');
		remove_multi_account(multi_account_login);
	}
	if($(target).hasClass('multi-account-select') || $(target).parent().hasClass('multi-account-select')){
		let proper_target=$(target);
		if($(target).parent().hasClass('multi-account-select')){
			proper_target=$(target).parent();
		}
		let multi_account_login=proper_target.attr('data-login');
		select_multi_account(multi_account_login);
	}
	if($(target).hasClass('get_post_geo')){
		$('.get_post_geo').css('display','none');
		get_post_geo();
		$('.clear_post_geo').css('display','inline-block');
	};
	if($(target).hasClass('clear_post_geo')){
		$('.clear_post_geo').css('display','none');
		clear_post_geo();
		$('.get_post_geo').css('display','inline-block');
	};
	if($(target).hasClass('posts-list-filter-button') || $(target).parent().hasClass('posts-list-filter-button')){
		if('none'==$('.posts-list-filter').css('display')){
			$('.posts-list-filter').css('display','block');
		}
		else{
			$('.posts-list-filter').css('display','none');
		}
	}
	if($(target).hasClass('change_languange')){
		if('none'==$('.select_languange').css('display')){
			$('.select_languange').css('display','block');
		}
		else{
			$('.select_languange').css('display','none');
		}
	}
	if($(target).hasClass('show_post_addon')){
		if('none'==$('.post_addon').css('display')){
			$('.post_addon').css('display','block');
		}
		else{
			$('.post_addon').css('display','none');
		}
	}
	if($(target).hasClass('online_status')){
		add_notify($(target).attr('title'),5000);
	}
	if($(target).hasClass('offline_status')){
		add_notify($(target).attr('title'),5000);
	}
	if($(target).hasClass('feed_view_mode')){
		change_feed_view_mode();
	}
	if($(target).hasClass('blog_view_mode')){
		change_blog_view_mode();
	}
	if($(target).hasClass('link_upload_file')){
		$('#upload-file').unbind('change');
		$('#upload-file').bind('change',function(e){
			e.preventDefault();
			var files = this.files;
			var file = files[0];
			show_modal('drop-images');
			try_upload(file,'');
		});
		$('#upload-file').click();
	}
	if($(target).hasClass('profile-update-upload-avatar') || $(target).parent().hasClass('profile-update-upload-avatar')){
		$('#upload-file').unbind('change');
		$('#upload-file').bind('change',function(e){
			e.preventDefault();
			var files = this.files;
			var file = files[0];
			show_modal('drop-images');
			try_upload(file,'avatar');
		});
		$('#upload-file').click();
	}
	if($(target).hasClass('profile-update-upload-cover') || $(target).parent().hasClass('profile-update-upload-cover')){
		$('#upload-file').unbind('change');
		$('#upload-file').bind('change',function(e){
			e.preventDefault();
			var files = this.files;
			var file = files[0];
			show_modal('drop-images');
			try_upload(file,'cover');
		});
		$('#upload-file').click();
	}
	if($(target).hasClass('unlock-active-key-button')){
		check_active_key($('.unlock-active-key input[name=login]').val(),$('.unlock-active-key input[name=active_key]').val());
	}
	if($(target).hasClass('unlock-owner-key-button')){
		check_owner_key($('.unlock-owner-key input[name=login]').val(),$('.unlock-owner-key input[name=owner_key]').val());
	}
	if($(target).hasClass('unvote-bad-witnesses')){
		unvote_bad_witnesses();
	}
	if($(target).hasClass('payback-action')){
		send_payback_queue();
	}
	if($(target).hasClass('payback-repost-action')){
		send_payback_repost_queue();
	}
	if($(target).hasClass('witness-action') || $(target).parent().hasClass('witness-action')){
		let proper_target=$(target);
		if($(target).parent().hasClass('witness-action')){
			proper_target=$(target).parent();
		}
		if(!proper_target.hasClass('inactive')){
			let witness_login=proper_target.attr('data-witness-login');
			if(proper_target.hasClass('active')){
				if(confirm('Вы уверены, что хотите снять свой голос с @'+witness_login)){
					user_witness_unvote(witness_login);
				}
			}
			else{
				if(confirm('Вы уверены, что хотите отдать свой голос @'+witness_login)){
					user_witness_vote(witness_login);
				}
			}
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.private_active_key,10000,true);
		}
	}
	if($(target).hasClass('post-payments') || $(target).parent().hasClass('post-payments')){
		e.preventDefault();
		var payment_card=$(target);
		if($(target).parent().hasClass('post-payments')){
			payment_card=$(target).parent();
		}
		var payout=parseFloat(payment_card.attr('data-payout').split(' ')[0]);
		var curator_payout=parseFloat(payment_card.attr('data-curator-payout').split(' ')[0]);
		var pending_payout=parseFloat(payment_card.attr('data-pending-payout').split(' ')[0]);
		var payment_currency_default='GBG';

		var initial_payout=payout;
		var initial_curator_payout=curator_payout;
		var initial_pending_payout=pending_payout;
		var output_currency=user.default_currency;

		curator_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_curator_payout,'REAL_RUB')),output_currency));
		payout=parseFloat(convert_currency(payment_currency_default,initial_payout/2,output_currency));
		pending_payout=parseFloat(convert_currency(payment_currency_default,initial_pending_payout/2,output_currency));
		payout+=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_payout/2,'REAL_RUB')),output_currency));
		pending_payout+=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_pending_payout/2,'REAL_RUB')),output_currency));
		var round_numbers=2;
		if('BTC'==output_currency){
			round_numbers=4;
		}
		if('ETH'==output_currency){
			round_numbers=3;
		}
		var notify_text='';
		if(0!=payout){
			notify_text=notify_text+'<strong>'+l10n.notify.author_payout+': </strong> '+payout.toFixed(round_numbers)+' '+output_currency+'<br>';
		}
		if(0!=curator_payout){
			notify_text=notify_text+'<strong>'+l10n.notify.curator_payout+': </strong> '+curator_payout.toFixed(round_numbers)+' '+output_currency+'<br>';
		}
		if(0!=pending_payout){
			notify_text=notify_text+'<strong>'+l10n.notify.payout+': </strong> '+pending_payout.toFixed(round_numbers)+' '+output_currency;
			var cashout_time=parseInt(payment_card.attr('data-cashout-time'));
			if(cashout_time>0){
				notify_text=notify_text+'<br><strong>'+l10n.notify.cashout_time+': </strong> '+date_str(cashout_time*1000,true);
			}
		}
		add_notify(notify_text,10000);
	}
	if($(target).hasClass('comment-payments') || $(target).parent().hasClass('comment-payments')){
		e.preventDefault();
		var payment_card=$(target);
		if($(target).parent().hasClass('comment-payments')){
			payment_card=$(target).parent();
		}
		var payout=parseFloat(payment_card.attr('data-comment-payout').split(' ')[0]);
		var curator_payout=parseFloat(payment_card.attr('data-comment-curator-payout').split(' ')[0]);
		var pending_payout=parseFloat(payment_card.attr('data-comment-pending-payout').split(' ')[0]);
		var payment_currency_default='GBG';
		var payment_inpower=parseInt(payment_card.closest('.comment-card').attr('data-payment-inpower'));

		var output_currency=user.default_currency;

		var initial_payout=payout;
		var initial_curator_payout=curator_payout;
		var initial_pending_payout=pending_payout;
		if(1==payment_inpower){
			curator_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_curator_payout,'REAL_RUB')),output_currency));
			payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_payout,'REAL_RUB')),output_currency));
			pending_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_pending_payout,'REAL_RUB')),output_currency));
		}
		else{
			curator_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_curator_payout,'REAL_RUB')),output_currency));
			payout=parseFloat(convert_currency(payment_currency_default,initial_payout/2,output_currency));
			pending_payout=parseFloat(convert_currency(payment_currency_default,initial_pending_payout/2,output_currency));
			payout+=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_payout/2,'REAL_RUB')),output_currency));
			pending_payout+=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_pending_payout/2,'REAL_RUB')),output_currency));
		}
		if(isNaN(payout)){
			payout=0;
		}
		if(isNaN(curator_payout)){
			curator_payout=0;
		}
		if(isNaN(pending_payout)){
			pending_payout=0;
		}

		var round_numbers=2;
		if('BTC'==output_currency){
			round_numbers=4;
		}
		if('ETH'==output_currency){
			round_numbers=3;
		}
		var notify_text='';
		if(0!=payout){
			notify_text=notify_text+'<strong>'+l10n.notify.author_payout+': </strong> '+payout.toFixed(round_numbers)+' '+output_currency+'<br>';
		}
		if(0!=curator_payout){
			notify_text=notify_text+'<strong>'+l10n.notify.curator_payout+': </strong> '+curator_payout.toFixed(round_numbers)+' '+output_currency+'<br>';
		}
		if(0!=pending_payout){
			notify_text=notify_text+'<strong>'+l10n.notify.payout+': </strong> '+pending_payout.toFixed(round_numbers)+' '+output_currency;
		}
		add_notify(notify_text,10000);
	}
	if($(target).hasClass('adaptive-menu') || $(target).parent().hasClass('adaptive-menu')){
		e.preventDefault();
		if('none'==$('.menu').css('display')){
			close_dropdown();
			$('.menu').css('display','block');
		}
		else{
			$('.menu').css('display','none');
		}
	}
	if($(target).hasClass('modal-overlay')){
		e.preventDefault();
		close_modal();
	}
	if($(target).hasClass('wysiwyg_activate')){
		wysiwyg_activate();
		$(target).remove();
	}
	if(($(target).hasClass('comment-delete')) || ($(target).parent().hasClass('comment-delete'))){
		if(1==user.verify){
			e.preventDefault();
			var proper_target=$(target);
			if($(target).parent().hasClass('comment-delete')){
				proper_target=$(target).parent();
			}
			var comment_card=proper_target.closest('.comment-card');
			if(confirm(l10n.comment_card.delete_confirm_text)){
				gate.broadcast.deleteComment(user.posting_key,user.login,comment_card.attr('data-permlink'),function(err, result){
					if(!err){
						comment_card.css('display','none');
						add_notify(l10n.comment_card.delete_ok,10000,true);
					}
					else{
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.comment_card.delete_error,10000,true);
					}
				});
			}
		}
	}
	if($(target).hasClass('vote_witnesses_poll')){
		if('disabled'!=$(target).attr('disabled')){
			$(target).attr('disabled','disabled');
			let option=$("input[name=witness-poll-vote-option]:checked").val();
			let url=path_array[3];
			if(option){
				if(confirm('Подтвердите свое намерение проголосовать, как делегат по данному вопросу')){
					var json=JSON.stringify({'url':url,'option':option});
					gate.broadcast.customJson(user.posting_key,[],[user.login],'witness_poll_vote',json,function(err, result){
						if(!err){
							$(target).removeAttr('disabled');
							add_notify('<strong>Ок</strong> Ваш голос скоро будет учтен',10000);
						}
						else{
							$(target).removeAttr('disabled');
							add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						}
					});
				}
			}
			else{
				$(target).removeAttr('disabled');
				add_notify('<strong>'+l10n.global.error_caption+'</strong> Вы не выбрали пункт для голосования',10000,true);
			}
		}
	}
	if($(target).hasClass('create_witnesses_poll')){
		if('disabled'!=$(target).attr('disabled')){
			$(target).attr('disabled','disabled');
			let url=$('form.witnesses_polls').find('input[name=url]').val();
			let name=$('form.witnesses_polls').find('input[name=name]').val();
			let days=parseInt($('form.witnesses_polls').find('input[name=days]').val());
			let descr=$('form.witnesses_polls').find('textarea[name=descr]').val();
			let options=$('form.witnesses_polls').find('textarea[name=options]').val();
			if((''!=url)&&(''!=options)&&(''!=name)){
				if(confirm('Вы уверены, что хотите создать опрос для голосования делегатов?')){
					if(!days){
						days=14;
					}
					if(days<14){
						days=14;
					}
					if((''!=url)&&(''!=options)&&(''!=name)){
						let options_arr=options.split('|');
						var json=JSON.stringify({'url':url,'name':name,'days':days,'descr':descr,'options':options_arr});
						gate.broadcast.customJson(user.posting_key,[],[user.login],'witness_poll',json,function(err, result){
							if(!err){
								$('form.witnesses_polls').find('input[type=text], textarea').val('');
								$(target).removeAttr('disabled');
								add_notify('<strong>Ок</strong> Опрос был создан, он появится на странице через некоторое время',10000);
							}
							else{
								$(target).removeAttr('disabled');
								add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
							}
						});
					}
				}
			}
			else{
				$(target).removeAttr('disabled');
				add_notify('<strong>'+l10n.global.error_caption+'</strong> Вы не заполнили все данные',10000,true);
			}
		}
	}
	if($(target).hasClass('post-clear-action')){
		if(confirm(l10n.add_post.reset_confirm_text)){
			$('input[name=post_title]').val('');
			$('input[name=post_image]').val('');
			$('textarea[name=post_text]').val('');
			$('input[name=post_tags]').val('');
			$('input[name=post_url]').val('');
			if(wysiwyg_active){
				tinyMCE.activeEditor.setContent('');
			}
			$('.clear_post_geo').css('display','none');
			clear_post_geo();
			$('.get_post_geo').css('display','inline-block');
			draft={title:'',image:'',text:'',tags:''};
			draft_json=JSON.stringify(draft);
			localStorage.setItem('draft',draft_json);
		}
	}
	if($(target).hasClass('post-action')){
		if(1==user.verify){
			target=$('.post-action');
			var post_title=$('input[name=post_title]').val();
			var post_permlink='';
			if(''!=$('input[name=post_url]').val()){
				$('input[name=post_url]').val(tags_convert($('input[name=post_url]').val(),'ru',1));
				post_permlink=$('input[name=post_url]').val();
			}
			else{
				post_permlink=tags_convert(post_title,'ru',1);
			}
			if('disabled'!=$('input[name=post_url]').attr('disabled')){
				post_permlink=post_permlink.replace(/([^a-zA-Z0-9 \-]*)/g,'');
				post_permlink=post_permlink.replace(/---/g,'-');
				post_permlink=post_permlink.replace(/--/g,'-');
				post_permlink=post_permlink.replace(/--/g,'-');
				post_permlink=post_permlink.replace(/^\-+|\-+$/g, '');
			}
			$('input[name=post_url]').val(post_permlink);

			var post_text=$('textarea[name=post_text]').val();
			var post_tags=$('input[name=post_tags]').val();
			var post_format='markdown';
			if(wysiwyg_active){
				post_text=tinyMCE.activeEditor.getContent();
				post_format='html';
			}
			if(-1!=post_text.indexOf('</p>')){
				post_format='html';
			}
			if(-1!=post_text.indexOf('</code>')){
				post_format='html';
			}
			if(-1!=post_text.indexOf('</li>')){
				post_format='html';
			}
			post_text=post_text.replace(' rel="noopener"','');
			var post_image=$('input[name=post_image]').val().trim();
			if(''==post_image){
				let links_arr=post_text.match(/((https?:|)\/\/[^\s]+)/g);
				for(i in links_arr){
					let regExp = /^.*((youtube.com|youtu.be)\/(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?\"]*).*/;
					let match = links_arr[i].match(regExp);
					if(match && match[6].length == 11){
						post_image='https://img.youtube.com/vi/'+match[6]+'/0.jpg';
						$('input[name=post_image]').val(post_image);
						break;
					}
				}
			}
			var post_tags_arr=post_tags.split(',');
			if(post_tags_arr.length>1){
				for(var i=0;i<post_tags_arr.length;i++){
					post_tags_arr[i]=post_tags_arr[i].trim();//tags_convert
				}
			}
			else{
				post_tags_arr=post_tags.split(' ');
				/*for(var i=0;i<post_tags_arr.length;i++){
					post_tags_arr[i]=tags_convert(post_tags_arr[i].trim());
				}*/
			}
			var users_exp = /@([a-z0-9A-Z\-_\.]+)/g;
			var users_arr=post_text.match(users_exp);
			if(null!=users_arr){
				users_arr=unique_array(users_arr);
				var users_len = users_arr.length;
				for(var i = 0; i < users_len; i++){
					users_arr[i]=users_arr[i].replace(/^\@/g,'');
					users_arr[i]=users_arr[i].replace(/\.$/g,'');
				}
			}
			var json_object={'tags':post_tags_arr,'format':post_format,'app':'goldvoice.club','image':[post_image],'users':users_arr};
			if(''!=post_geo.name){
				json_object.geo=post_geo;
			}
			var json=JSON.stringify(json_object);
			var parent_permlink='goldvoice';
			//parent_permlink=post_tags_arr[0];
			if(0<$('input[name=post_parent_permlink]').length){
				parent_permlink=$('input[name=post_parent_permlink]').val();
			}
			post_draft_autosave();
			$('input[name=post_url]').attr('disabled','disabled');
			$(target).val(l10n.add_post.sending);
			$(target).attr('disabled','disabled');
			if(1==$(target).attr('data-edit')){
				gate.broadcast.comment(user.posting_key,'',parent_permlink,user.profile.login,post_permlink,post_title,post_text,json,function(err,result){
					if(!err){
						add_notify('<strong>'+l10n.add_post.ok+'</strong> '+l10n.add_post.ok_descr,5000);
						if(1==user.post_autovote){
							setTimeout(function(){gate.broadcast.vote(user.posting_key,user.login,user.login,post_permlink,10000,function(err, result){
								if(err){
									console.log(err);
								}
							})},500);
						}
						setTimeout(function(){wait_post(user.profile.login,post_permlink);},3500);
					}
					else{
						console.log(err);
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						$('input[name=post_url]').removeAttr('disabled');
						$(target).removeAttr('disabled');
						$(target).val(l10n.add_post.post_button);
					}
				});
			}
			else{
				$.ajax({
					type:'POST',
					url:'/ajax/check_url/',
					data:{'author':user.profile.login,'permlink':post_permlink},
					success:function(data_json){
						data_obj=JSON.parse(data_json);
						if('ok'==data_obj.status){//post exist
							if(confirm('Пост с таким url уже существует, вы хотите заменить его?')){
								gate.broadcast.comment(user.posting_key,'',parent_permlink,user.profile.login,post_permlink,post_title,post_text,json,function(err,result){
									if(!err){
										add_notify('<strong>'+l10n.add_post.ok+'</strong> '+l10n.add_post.ok_descr,5000);
										if(1==user.post_autovote){
											setTimeout(function(){gate.broadcast.vote(user.posting_key,user.login,user.login,post_permlink,10000,function(err, result){
												if(err){
													console.log(err);
												}
											})},500);
										}
										setTimeout(function(){wait_post(user.profile.login,post_permlink);},3500);
									}
									else{
										console.log(err);
										add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
										$('input[name=post_url]').removeAttr('disabled');
										$(target).removeAttr('disabled');
										$(target).val(l10n.add_post.post_button);
									}
								});
							}
							else{
								$('input[name=post_url]').removeAttr('disabled');
								$(target).removeAttr('disabled');
								$(target).val(l10n.add_post.post_button);
							}
						}
						else{//new post
							gate.broadcast.comment(user.posting_key,'',parent_permlink,user.profile.login,post_permlink,post_title,post_text,json,function(err,result){
								if(!err){
									add_notify('<strong>'+l10n.add_post.ok+'</strong> '+l10n.add_post.ok_descr,5000);
									if(1==user.post_autovote){
										setTimeout(function(){gate.broadcast.vote(user.posting_key,user.login,user.login,post_permlink,10000,function(err, result){
											if(!err){
											}
											else{
												console.log(err);
												add_notify('<strong>Ошибка</strong> Не получилось автоматически проголосовать за пост',3000,true);
											}
										})},500);
									}
									setTimeout(function(){wait_post(user.profile.login,post_permlink);},3500);
								}
								else{
									console.log(err);
									add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
									$('input[name=post_url]').removeAttr('disabled');
									$(target).removeAttr('disabled');
									$(target).val(l10n.add_post.post_button);
								}
							});
						}
					}
				});
			}
		}
	}
	if($(target).hasClass('comment-edit-execute')){
		var edit_form=$(target).closest('.comment-edit-form');
		var comment_text=edit_form.find('textarea[name=comment-edit-text]').val();
		if(''!=comment_text){
			$(target).val(l10n.comments.sending);
			$(target).attr('disabled','disabled');
			var comment_id=parseInt(edit_form.attr('data-edit-comment'));
			var comment_parent_id=parseInt($('.comment-card[data-id='+comment_id+']').attr('data-parent'));
			var comment_author=$('.comment-card[data-id='+comment_id+']').attr('data-author');
			var comment_permlink=$('.comment-card[data-id='+comment_id+']').attr('data-permlink');
			var comment_parent_author=$('.post-card').attr('data-author');
			var comment_parent_permlink=$('.post-card').attr('data-permlink');
			var comment_title='Re: '+$('.post-card .page_title').html();
			if(0!=comment_parent_id){
				comment_parent_author=$('.comment-card[data-id='+comment_parent_id+']').attr('data-author');
				comment_parent_permlink=$('.comment-card[data-id='+comment_parent_id+']').attr('data-permlink');
			}
			var json=JSON.stringify({format:'markdown',app:'goldvoice.club'});
			gate.broadcast.comment(user.posting_key,comment_parent_author,comment_parent_permlink,comment_author,comment_permlink,comment_title,comment_text,json,function(err,result){
				if(!err){
					$.ajax({
						type:'POST',
						url:'/ajax/text_to_view/',
						data:{'text':comment_text},
						success:function(data){
							$('.comment-card[data-id='+comment_id+']').find('.comment-text').html(data);
							$(target).parent().remove();
							add_notify('<strong>'+l10n.comments.edit_ok+'</strong> '+l10n.comments.edit_ok_descr,5000);
						}
					});
				}
				else{
					console.log(err);
					add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
					$(target).removeAttr('disabled');
					$(target).val(l10n.comments.post_button);
				}
			});
		}
	}
	if($(target).hasClass('reply-execute')){
		var post_id=$(target).parent().attr('data-reply-post');
		var comment_id=$(target).parent().attr('data-reply-comment');
		var comment_text=$(target).parent().find('textarea').val();
		if(''!=comment_text){
			$(target).val(l10n.comments.sending);
			$(target).attr('disabled','disabled');
			if(0!=post_id){
				var comment_parent_author=$('.post-card[data-id='+post_id+']').attr('data-author');
				var comment_parent_permlink=$('.post-card[data-id='+post_id+']').attr('data-permlink');
				var comment_author=user.profile.login;
				var comment_permlink=user.profile.login+'-re-'+comment_parent_permlink.substring(0,comment_parent_permlink.lastIndexOf('-'))+'-'+(Date.now());
				comment_permlink=comment_permlink.replace(/([^a-zA-Z0-9 \-]*)/g,'');
				var comment_title='Re: '+$('.post-card .page_title').html();
				var json=JSON.stringify({format:'markdown',app:'goldvoice.club'});
				gate.broadcast.comment(user.posting_key,comment_parent_author,comment_parent_permlink,comment_author,comment_permlink,comment_title,comment_text,json,function(err,result){
					if(!err){
						$(target).parent().remove();
						set_waiting_update_comments_list(1);
						add_notify('<strong>'+l10n.comments.ok+'</strong> '+l10n.comments.ok_descr,5000);
					}
					else{
						console.log(err);
						window.setTimeout(function(){set_waiting_update_comments_list(0);},100);
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						$(target).removeAttr('disabled');
						$(target).val(l10n.comments.post_button);
					}
				});
			}
			if(0!=comment_id){
				var comment_parent_author=$('.comment-card[data-id='+comment_id+']').attr('data-author');
				var comment_parent_permlink=$('.comment-card[data-id='+comment_id+']').attr('data-permlink');
				var comment_author=user.profile.login;
				var comment_permlink=user.profile.login+'-re-'+comment_parent_permlink.substring(0,comment_parent_permlink.lastIndexOf('-'))+'-'+(Date.now());
				comment_permlink=comment_permlink.replace(/([^a-zA-Z0-9 \-]*)/g,'');
				var comment_title='Re: '+$('.post-card .page_title').html();
				var json=JSON.stringify({format:'markdown',app:'goldvoice.club'});
				gate.broadcast.comment(user.posting_key,comment_parent_author,comment_parent_permlink,comment_author,comment_permlink,comment_title,comment_text,json,function(err,result){
					if(!err){
						$(target).parent().remove();
						set_waiting_update_comments_list(1);
						add_notify('<strong>'+l10n.comments.ok+'</strong> '+l10n.comments.ok_descr,5000);
					}
					else{
						console.log(err);
						window.setTimeout(function(){set_waiting_update_comments_list(0);},100);
						add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.broadcast,10000,true);
						$(target).removeAttr('disabled');
					}
				});
			}
		}
	}
	if($(target).hasClass('reply-action') || $(target).parent().hasClass('reply-action')){
		e.preventDefault();
		var proper_target=$(target);
		if($(target).parent().hasClass('reply-action')){
			proper_target=$(target).parent();
		}
		if(1==user.verify){
			window.clearTimeout(update_comments_list_timer);
			var post_id=0;
			var comment_id=0;
			if(proper_target.hasClass('post-reply')){
				post_id=parseInt(proper_target.attr('data-post-id'));
			}
			if(proper_target.hasClass('comment-reply')){
				comment_id=parseInt(proper_target.attr('data-comment-id'));
			}
			var comment_form='<div class="reply-form" data-reply-post="'+post_id+'" data-reply-comment="'+comment_id+'"><textarea name="reply-text" placeholder="'+l10n.comments.placeholder+'"></textarea><input type="button" class="reply-execute" value="'+l10n.comments.post_button+'"></div>'
			if(comment_id){
				if(0==$('.reply-form[data-reply-comment='+comment_id+']').length){
					proper_target.closest('.comment-info').after(comment_form);
					proper_target.closest('.comment-info').parent().find('.reply-form textarea[name=reply-text]').focus();
				}
				else{
					$('.reply-form[data-reply-comment='+comment_id+']').remove();
				}
			}
			if(post_id){
				if(0==$('.reply-form[data-reply-post='+post_id+']').length){
					proper_target.closest('.comments').find('h2').after(comment_form);
					proper_target.closest('.comments').find('.reply-form textarea[name=reply-text]').focus();
				}
				else{
					$('.reply-form[data-reply-post='+post_id+']').remove();
				}
			}
		}
	}
	if($(target).hasClass('comment-edit') || $(target).parent().hasClass('comment-edit')){
		e.preventDefault();
		var proper_target=$(target);
		if($(target).parent().hasClass('comment-edit')){
			proper_target=$(target).parent();
		}
		if(1==user.verify){
			var comment_id=parseInt(proper_target.closest('.comment-card').attr('data-id'));
			if(comment_id){
				if(0==$('.comment-edit-form[data-edit-comment='+comment_id+']').length){
					$.ajax({
						type:'POST',
						url:'/ajax/comment_body/'+comment_id+'/',
						success:function(data){
							var comment_form='<div class="comment-edit-form" data-edit-comment="'+comment_id+'"><textarea name="comment-edit-text" placeholder="'+l10n.comments.placeholder+'">'+escape_html(data)+'</textarea><input type="button" class="comment-edit-execute" value="'+l10n.comments.post_button+'"></div>'
							proper_target.closest('.comment-info').after(comment_form);
						}
					});
				}
				else{
					$('.comment-edit-form[data-edit-comment='+comment_id+']').remove();
				}
			}
		}
	}
	if($(target).hasClass('fix-percent-10')){
		e.preventDefault();
		var percent=$(target).attr('data-percent');
		$('.fix-percent .fix-percent-10').removeClass('active');
		for(var i=10;i<=percent;i=i+10){
			$('.fix-percent .fix-percent-10[data-percent='+i+']').addClass('active');
		}
		vote_card_action.weight=percent;
		$(target).parent().find('span').html(''+percent+'%');
	}
	if(($(target).hasClass('share-action')) || ($(target).parent().hasClass('share-action'))){
		window.open('https://app.sharpay.io/share?s=21e50&u=' + encodeURIComponent( window.location.href ), 'sharpay', 'toolbar=no,scrollbars=no,width=800,height=450');
		return false;
	}
	if(($(target).hasClass('repost-action')) || ($(target).parent().hasClass('repost-action'))){
		if(1==user.verify){
			e.preventDefault();
			var proper_target=$(target);
			if($(target).parent().hasClass('repost-action')){
				proper_target=$(target).parent();
			}
			$('.repost-card-dropdown').css('display','none');
			if(proper_target.hasClass('reposted')){
				add_notify(l10n.repost_card.reposted,5000);
			}
			else{
				if(typeof proper_target.closest('.post-card')[0] !== 'undefined'){
					$('.repost-card-dropdown').css('display','block');
					repost_card_action.login=proper_target.closest('.post-card').attr('data-author');
					repost_card_action.permlink=proper_target.closest('.post-card').attr('data-permlink');
					repost_card_action.proper_target=proper_target;
					repost_card_action.wait=1;
					repost_card_action.need_confirmation=1;
					repost_card_action.confirmation=0;
					show_repost_card_dropdown(proper_target);
				}
			}
		}
	}
	if(($(target).hasClass('upvote-action')) || ($(target).parent().hasClass('upvote-action'))){
		if(1==user.verify){
			e.preventDefault();
			var proper_target=$(target);
			if($(target).parent().hasClass('upvote-action')){
				proper_target=$(target).parent();
			}
			$('.vote-card-dropdown .fix-percent').css('display','none');
			vote_card_action.action='';
			if(typeof proper_target.closest('.post-card')[0] !== 'undefined'){
				if('1'==proper_target.closest('.post-card').attr('data-vote')){
					vote_card_action.action='remove_vote_post';
					vote_card_action.confirmation_text=l10n.upvote_card.unvote;
				}
				else{
					$('.vote-card-dropdown .fix-percent').css('display','block');
					vote_card_action.action='vote_post';
					vote_card_action.confirmation_text='';
				}
				vote_card_action.login=proper_target.closest('.post-card').attr('data-author');
				vote_card_action.permlink=proper_target.closest('.post-card').attr('data-permlink');
			}
			if(typeof proper_target.closest('.comment-card')[0] !== 'undefined'){
				if('1'==proper_target.closest('.comment-card').attr('data-vote')){
					vote_card_action.action='remove_vote_comment';
					vote_card_action.confirmation_text=l10n.upvote_card.unvote;
				}
				else{
					$('.vote-card-dropdown .fix-percent').css('display','block');
					vote_card_action.action='vote_comment';
					vote_card_action.confirmation_text='';
				}
				vote_card_action.login=proper_target.closest('.comment-card').attr('data-author');
				vote_card_action.permlink=proper_target.closest('.comment-card').attr('data-permlink');
			}
			if(vote_card_action.action){
				vote_card_action.proper_target=proper_target;
				vote_card_action.wait=1;
				vote_card_action.need_confirmation=1;
				vote_card_action.confirmation=0;
				show_vote_card_dropdown(proper_target);
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.upvote_card.error,10000,true);
			}
		}
	}
	if(($(target).hasClass('flag-action')) || ($(target).parent().hasClass('flag-action'))){
		if(1==user.verify){
			e.preventDefault();
			var proper_target=$(target);
			if($(target).parent().hasClass('flag-action')){
				var proper_target=$(target).parent();
			}
			$('.vote-card-dropdown .fix-percent').css('display','none');
			vote_card_action.action='';
			if(typeof proper_target.closest('.post-card')[0] !== 'undefined'){
				if('1'==proper_target.closest('.post-card').attr('data-flag')){
					vote_card_action.action='remove_flag_post';
					vote_card_action.confirmation_text=l10n.flag_card.unflag;
				}
				else{
					vote_card_action.action='flag_post';
					vote_card_action.confirmation_text=l10n.flag_card.flag;
				}
				vote_card_action.login=proper_target.closest('.post-card').attr('data-author');
				vote_card_action.permlink=proper_target.closest('.post-card').attr('data-permlink');
			}
			if(typeof proper_target.closest('.comment-card')[0] !== 'undefined'){
				if('1'==proper_target.closest('.comment-card').attr('data-flag')){
					vote_card_action.action='remove_flag_comment';
					vote_card_action.confirmation_text=l10n.flag_card.unflag;
				}
				else{
					vote_card_action.action='flag_comment';
					vote_card_action.confirmation_text=l10n.flag_card.flag;
				}
				vote_card_action.login=proper_target.closest('.comment-card').attr('data-author');
				vote_card_action.permlink=proper_target.closest('.comment-card').attr('data-permlink');
			}
			if(vote_card_action.action){
				vote_card_action.proper_target=proper_target;
				vote_card_action.wait=1;
				vote_card_action.need_confirmation=1;
				vote_card_action.confirmation=0;
				show_vote_card_dropdown(proper_target);
			}
			else{
				add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.flag_card.error,10000,true);
			}
		}
	}
	if($(target).hasClass('action-add-friend')){
		if(0==user_card_action.wait){
			user_card_action.wait=1;
			user_card_action.action='subscribe';
			user_card_action.login=$(target).closest('.user-card').attr('data-user-login');
			user_card_action.confirmation_text=l10n.user_card.subscribe_text+' @'+escape_html(user_card_action.login)+'?';
			if($(target).hasClass('action-confirm')){
				user_card_action.need_confirmation=1;
				user_card_action.confirmation=0;
				show_user_card_dropdown(target);
			}
			else{
				execute_user_card_action();
			}
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.wait,10000,true);
		}
	}
	if($(target).hasClass('action-add-ignore')){
		if(0==user_card_action.wait){
			user_card_action.wait=1;
			user_card_action.action='ignore';
			user_card_action.login=$(target).closest('.user-card').attr('data-user-login');
			user_card_action.confirmation_text=l10n.user_card.ignore_text+' @'+escape_html(user_card_action.login)+'?';
			if($(target).hasClass('action-confirm')){
				user_card_action.need_confirmation=1;
				user_card_action.confirmation=0;
				show_user_card_dropdown(target);
			}
			else{
				execute_user_card_action();
			}
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.wait,10000,true);
		}
	}
	if($(target).hasClass('action-stop-ignore')){
		if(0==user_card_action.wait){
			user_card_action.wait=1;
			user_card_action.action='clear';
			user_card_action.login=$(target).closest('.user-card').attr('data-user-login');
			user_card_action.confirmation_text=l10n.user_card.unignore_text+' @'+escape_html(user_card_action.login)+'?';
			if($(target).hasClass('action-confirm')){
				user_card_action.need_confirmation=1;
				user_card_action.confirmation=0;
				show_user_card_dropdown(target);
			}
			else{
				execute_user_card_action();
			}
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.wait,10000,true);
		}
	}
	if($(target).hasClass('action-stop-friend')){
		if(0==user_card_action.wait){
			user_card_action.wait=1;
			user_card_action.action='clear';
			user_card_action.login=$(target).closest('.user-card').attr('data-user-login');
			user_card_action.confirmation_text=l10n.user_card.unsubscribe_text+' @'+escape_html(user_card_action.login)+'?';
			if($(target).hasClass('action-confirm')){
				user_card_action.need_confirmation=1;
				user_card_action.confirmation=0;
				show_user_card_dropdown(target);
			}
			else{
				execute_user_card_action();
			}
		}
		else{
			add_notify('<strong>'+l10n.global.error_caption+'</strong> '+l10n.errors.wait,10000,true);
		}
	}
}
function bind_search_user_list(){
	$('#search-user-list').unbind('keyup')
	$('#search-user-list').bind('keyup',function(){
		var find_count=0;
		var search_str=$(this).val().toLowerCase();
		$('.user-list-search-result').css('display','none');
		$('.user-list-item').css('display','none');
		$('.user-list-item').each(function(){
			var search_test=$(this).find('.user-search-text').html().toLowerCase();
			if(-1!=search_test.indexOf(search_str)){
				$(this).css('display','block');
				find_count++;
			}
		});
		if(0==find_count){
			$('.user-list-search-result').css('display','block');
			$('.user-list-search-result').html(l10n.global.search_not_found);
		}
	})
}
function convert_currency(currency_type,currency_amount,output_currency='GBG'){
	if(preset.currencies_price !== false){
		currency_amount=parseFloat(currency_amount);
		var output_currency_amount=currency_amount;
		if(output_currency!=currency_type){
			if('GBG'==currency_type){
				if('RUB'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.rub * currency_amount;
				}
				if('REAL_RUB'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.real_rub * currency_amount;
				}
				if('USD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.usd * currency_amount;
				}
				if('BTC'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.btc * currency_amount;
				}
				if('ETH'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.eth * currency_amount;
				}
				if('GOLOS'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.golos * currency_amount;
				}
				if('STEEM'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.steem * currency_amount;
				}
				if('SBD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.sbd * currency_amount;
				}
				if('XRP'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.xrp * currency_amount;
				}
			}
			if('RUB'==currency_type){
				if('GBG'==output_currency){
					output_currency_amount=currency_amount/preset.currencies_price.gbg.rub;
				}
				if('BTC'==output_currency){
					output_currency_amount=currency_amount/preset.currencies_price.btc.rub;
				}
				if('USD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.usd * currency_amount/preset.currencies_price.gbg.rub;
				}
				if('ETH'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.eth * currency_amount/preset.currencies_price.gbg.rub;
				}
				if('GOLOS'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.golos * currency_amount/preset.currencies_price.gbg.rub;
				}
				if('STEEM'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.steem * currency_amount/preset.currencies_price.gbg.rub;
				}
				if('SBD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.sbd * currency_amount/preset.currencies_price.gbg.rub;
				}
				if('XRP'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.xrp * currency_amount/preset.currencies_price.gbg.rub;
				}
			}
			if('USD'==currency_type){
				if('GBG'==output_currency){
					output_currency_amount=currency_amount/preset.currencies_price.gbg.usd;
				}
				if('BTC'==output_currency){
					output_currency_amount=currency_amount/preset.currencies_price.btc.usd;
				}
				if('RUB'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.rub * currency_amount/preset.currencies_price.gbg.usd;
				}
				if('ETH'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.eth * currency_amount/preset.currencies_price.gbg.usd;
				}
				if('GOLOS'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.golos * currency_amount/preset.currencies_price.gbg.usd;
				}
				if('STEEM'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.steem * currency_amount/preset.currencies_price.gbg.usd;
				}
				if('SBD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.sbd * currency_amount/preset.currencies_price.gbg.usd;
				}
				if('XRP'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.xrp * currency_amount/preset.currencies_price.gbg.usd;
				}
			}
			if('BTC'==currency_type){
				if('GBG'==output_currency){
					output_currency_amount=preset.currencies_price.btc.gbg * currency_amount;
				}
				if('RUB'==output_currency){
					output_currency_amount=preset.currencies_price.btc.rub * currency_amount;
				}
				if('USD'==output_currency){
					output_currency_amount=preset.currencies_price.btc.usd * currency_amount;
				}
				if('ETH'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.eth * currency_amount/preset.currencies_price.gbg.btc;
				}
				if('GOLOS'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.golos * currency_amount/preset.currencies_price.gbg.btc;
				}
				if('STEEM'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.steem * currency_amount/preset.currencies_price.gbg.btc;
				}
				if('SBD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.sbd * currency_amount/preset.currencies_price.gbg.btc;
				}
				if('XRP'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.xrp * currency_amount/preset.currencies_price.gbg.btc;
				}
			}
			if('ETH'==currency_type){
				if('GBG'==output_currency){
					output_currency_amount=preset.currencies_price.eth.gbg * currency_amount;
				}
				if('RUB'==output_currency){
					output_currency_amount=preset.currencies_price.eth.rub * currency_amount;
				}
				if('USD'==output_currency){
					output_currency_amount=preset.currencies_price.eth.usd * currency_amount;
				}
				if('BTC'==output_currency){
					output_currency_amount=preset.currencies_price.eth.btc * currency_amount;
				}
				if('GOLOS'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.golos * currency_amount/preset.currencies_price.gbg.eth;
				}
				if('STEEM'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.steem * currency_amount/preset.currencies_price.gbg.eth;
				}
				if('SBD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.sbd * currency_amount/preset.currencies_price.gbg.eth;
				}
				if('XRP'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.xrp * currency_amount/preset.currencies_price.gbg.eth;
				}
			}
			if('STEEM'==currency_type){
				if('GBG'==output_currency){
					output_currency_amount=preset.currencies_price.steem.gbg * currency_amount;
				}
				if('RUB'==output_currency){
					output_currency_amount=preset.currencies_price.steem.rub * currency_amount;
				}
				if('USD'==output_currency){
					output_currency_amount=preset.currencies_price.steem.usd * currency_amount;
				}
				if('BTC'==output_currency){
					output_currency_amount=preset.currencies_price.steem.btc * currency_amount;
				}
				if('GOLOS'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.golos * currency_amount/preset.currencies_price.gbg.steem;
				}
				if('SBD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.sbd * currency_amount/preset.currencies_price.gbg.steem;
				}
				if('XRP'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.xrp * currency_amount/preset.currencies_price.gbg.steem;
				}
			}
			if('SBD'==currency_type){
				if('GBG'==output_currency){
					output_currency_amount=preset.currencies_price.sbd.gbg * currency_amount;
				}
				if('RUB'==output_currency){
					output_currency_amount=preset.currencies_price.sbd.rub * currency_amount;
				}
				if('USD'==output_currency){
					output_currency_amount=preset.currencies_price.sbd.usd * currency_amount;
				}
				if('BTC'==output_currency){
					output_currency_amount=preset.currencies_price.sbd.btc * currency_amount;
				}
				if('GOLOS'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.golos * currency_amount/preset.currencies_price.gbg.sbd;
				}
				if('STEEM'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.steem * currency_amount/preset.currencies_price.gbg.sbd;
				}
				if('XRP'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.xrp * currency_amount/preset.currencies_price.gbg.sbd;
				}
			}
			if('GOLOS'==currency_type){
				if('GBG'==output_currency){
					output_currency_amount=preset.currencies_price.golos.gbg * currency_amount;
				}
				if('RUB'==output_currency){
					output_currency_amount=preset.currencies_price.golos.rub * currency_amount;
				}
				if('USD'==output_currency){
					output_currency_amount=preset.currencies_price.golos.usd * currency_amount;
				}
				if('BTC'==output_currency){
					output_currency_amount=preset.currencies_price.golos.btc * currency_amount;
				}
				if('ETH'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.eth * currency_amount/preset.currencies_price.gbg.golos;
				}
				if('STEEM'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.steem * currency_amount/preset.currencies_price.gbg.golos;
				}
				if('SBD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.sbd * currency_amount/preset.currencies_price.gbg.golos;
				}
				if('XRP'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.xrp * currency_amount/preset.currencies_price.gbg.golos;
				}
			}
			if('XRP'==currency_type){
				if('GBG'==output_currency){
					output_currency_amount=preset.currencies_price.xrp.gbg * currency_amount;
				}
				if('RUB'==output_currency){
					output_currency_amount=preset.currencies_price.xrp.rub * currency_amount;
				}
				if('USD'==output_currency){
					output_currency_amount=preset.currencies_price.xrp.usd * currency_amount;
				}
				if('BTC'==output_currency){
					output_currency_amount=preset.currencies_price.xrp.btc * currency_amount;
				}
				if('ETH'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.eth * currency_amount/preset.currencies_price.gbg.xrp;
				}
				if('STEEM'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.steem * currency_amount/preset.currencies_price.gbg.xrp;
				}
				if('SBD'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.sbd * currency_amount/preset.currencies_price.gbg.xrp;
				}
				if('GOLOS'==output_currency){
					output_currency_amount=preset.currencies_price.gbg.golos * currency_amount/preset.currencies_price.gbg.xrp;
				}
			}
		}
		var round_numbers=4;
		if('BTC'==output_currency){
			round_numbers=8;
		}
		if('ETH'==output_currency){
			round_numbers=8;
		}
		return output_currency_amount.toFixed(round_numbers);
	}
	else{
		return 0;
	}
}
function update_post_payout(output_currency='GBG'){
	var round_numbers=2;
	if('BTC'==output_currency){
		round_numbers=4;
	}
	if('ETH'==output_currency){
		round_numbers=3;
	}
	$('.user-balance-summary').each(function(){
		var summary_balance=0.0;

		var balance_golos=$(this).parent().find('.user-balance-golos span').html();
		summary_balance=summary_balance+parseFloat(convert_currency('GOLOS',balance_golos,output_currency));

		var balance_gbg=$(this).parent().find('.user-balance-gbg span').html();
		summary_balance=summary_balance+parseFloat(convert_currency('GBG',balance_gbg,output_currency));

		var balance_sg=$(this).parent().find('.user-balance-sg span.user-balance-sg-amount').html();
		summary_balance=summary_balance+parseFloat(convert_currency('GOLOS',balance_sg,output_currency));

		if(0<$(this).parent().parent().find('.user-balance-savings-golos span').length){
			var savings_golos=$(this).parent().parent().find('.user-balance-savings-golos span').html();
			summary_balance=summary_balance+parseFloat(convert_currency('GOLOS',savings_golos,output_currency));
		}

		if(0<$(this).parent().parent().find('.user-balance-savings-gbg span').length){
			var savings_gbg=$(this).parent().parent().find('.user-balance-savings-gbg span').html();
			summary_balance=summary_balance+parseFloat(convert_currency('GBG',savings_gbg,output_currency));
		}

		$(this).html(l10n.profile.summary_cost+': '+Intl.NumberFormat().format(summary_balance.toFixed(round_numbers))+' '+output_currency);
	});
	$('.currency').each(function(){
		var currency_amount=parseFloat($(this).html().split(' ')[0]);
		var currency_type=$(this).html().split(' ')[1];
		var output_currency_amount=0;
		$(this).html(convert_currency(currency_type,currency_amount,output_currency)+' '+output_currency);
	});
	$('.post-card').each(function(){
		var payment_decline=parseInt($(this).attr('data-payment-decline'));
		var payment_inpower=parseInt($(this).attr('data-payment-inpower'));
		var payment_card=$(this).find('.post-payments');
		var payment_value=payment_card.find('span');
		var payout=parseFloat(payment_card.attr('data-payout').split(' ')[0]);
		var curator_payout=parseFloat(payment_card.attr('data-curator-payout').split(' ')[0]);
		var pending_payout=parseFloat(payment_card.attr('data-pending-payout').split(' ')[0]);
		var payment_currency_default='GBG';

		var initial_payout=payout;
		var initial_curator_payout=curator_payout;
		var initial_pending_payout=pending_payout;
		if(1==payment_inpower){
			curator_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_curator_payout,'REAL_RUB')),output_currency));
			payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_payout,'REAL_RUB')),output_currency));
			pending_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_pending_payout,'REAL_RUB')),output_currency));
		}
		else{
			curator_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_curator_payout,'REAL_RUB')),output_currency));
			payout=parseFloat(convert_currency(payment_currency_default,initial_payout/2,output_currency));
			pending_payout=parseFloat(convert_currency(payment_currency_default,initial_pending_payout/2,output_currency));
			payout+=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_payout/2,'REAL_RUB')),output_currency));
			pending_payout+=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_pending_payout/2,'REAL_RUB')),output_currency));
		}
		if(isNaN(payout)){
			payout=0;
		}
		if(isNaN(curator_payout)){
			curator_payout=0;
		}
		if(isNaN(pending_payout)){
			pending_payout=0;
		}
		if(1==payment_decline){
			payment_value.html('&mdash;');
		}
		else{
			if(0==payout){
				if(0==pending_payout){
					payment_value.html('&hellip;');
				}
				else{
					payment_value.html('~'+pending_payout.toFixed(round_numbers)+' '+output_currency);
				}
			}
			else{
				if(0==pending_payout){
					payment_value.html(payout.toFixed(round_numbers)+' '+output_currency);
				}
				else{
					payment_value.html('~'+(payout+pending_payout).toFixed(round_numbers)+' '+output_currency);
				}
			}
		}
	});
	$('.comment-card').each(function(){
		var payment_decline=parseInt($(this).attr('data-payment-decline'));
		var payment_inpower=parseInt($(this).attr('data-payment-inpower'));
		var payment_card=$(this).find('.comment-payments');
		var payment_value=payment_card.find('span');
		var payout=parseFloat(payment_card.attr('data-comment-payout').split(' ')[0]);
		var curator_payout=parseFloat(payment_card.attr('data-comment-curator-payout').split(' ')[0]);
		var pending_payout=parseFloat(payment_card.attr('data-comment-pending-payout').split(' ')[0]);
		var payment_currency_default='GBG';

		var initial_payout=payout;
		var initial_curator_payout=curator_payout;
		var initial_pending_payout=pending_payout;
		if(1==payment_inpower){
			curator_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_curator_payout,'REAL_RUB')),output_currency));
			payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_payout,'REAL_RUB')),output_currency));
			pending_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_pending_payout,'REAL_RUB')),output_currency));
		}
		else{
			curator_payout=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_curator_payout,'REAL_RUB')),output_currency));
			payout=parseFloat(convert_currency(payment_currency_default,initial_payout/2,output_currency));
			pending_payout=parseFloat(convert_currency(payment_currency_default,initial_pending_payout/2,output_currency));
			payout+=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_payout/2,'REAL_RUB')),output_currency));
			pending_payout+=parseFloat(convert_currency('RUB',parseFloat(convert_currency(payment_currency_default,initial_pending_payout/2,'REAL_RUB')),output_currency));
		}
		if(isNaN(payout)){
			payout=0;
		}
		if(isNaN(curator_payout)){
			curator_payout=0;
		}
		if(isNaN(pending_payout)){
			pending_payout=0;
		}
		if(1==payment_decline){
			payment_value.html('&mdash;');
		}
		else{
			if(0==payout){
				if(0==pending_payout){
					payment_value.html('&hellip;');
					payment_card.css('display','none');
				}
				else{
					payment_value.html('~'+pending_payout.toFixed(round_numbers)+' '+output_currency);
				}
			}
			else{
				if(0==pending_payout){
					payment_value.html(payout.toFixed(round_numbers)+' '+output_currency);
				}
				else{
					payment_value.html('~'+(payout+pending_payout).toFixed(round_numbers)+' '+output_currency);
				}
			}
		}
	});
}
$(window).on('hashchange',function(e){
	e.preventDefault();
	if(''!=window.location.hash){
		$(window).scrollTop($(window.location.hash).offset().top - 56);
	}
	else{
		$(window).scrollTop(0);
	}
});
function update_feed_max_post_id(){
	if('feed'==path_array[1]){
		var max_post_id=0;
		$('.post-card').each(function(){
			var text_id=parseInt($(this).attr('data-id'));
			if(typeof $(this).attr('data-reblog-id')!== 'undefined'){
				text_id=parseInt($(this).attr('data-reblog-id'));
			}
			if(text_id>max_post_id){
				max_post_id=text_id;
			}
		});
		user.feed_max_post_id=max_post_id;
		localStorage.setItem('feed_max_post_id',user.feed_max_post_id);
	}
}
function set_notify_feed_count(count){
	if(0==count){
		$('.notify-feed-count').css('display','none');
		$('.notify-feed-count').html(count);
	}
	else{
		$('.notify-feed-count').html(count);
		$('.notify-feed-count').css('display','block');
	}
}
function update_notify_feed_count(){
	update_feed_max_post_id();
	$.ajax({
		type:'POST',
		url:'/ajax/feed_new_posts_count/',
		data:{id:user.feed_max_post_id},
		success:function(data_json){
			data_obj=JSON.parse(data_json);
			if(typeof data_obj.result !== 'undefined'){
				set_notify_feed_count(data_obj.result);
			}
		}
	});
	window.clearTimeout(notify_feed_timer);
	notify_feed_timer=window.setTimeout(function(){update_notify_feed_count();},60000);
}
function set_notify_replies_count(count){
	if(0==count){
		$('.notify-replies-count').css('display','none');
		$('.notify-replies-count').html(count);
	}
	else{
		$('.notify-replies-count').html(count);
		$('.notify-replies-count').css('display','block');
	}
}
function set_notifications_list(data){
	$('.menu-notifications').removeClass('active');
	if(notifications_list_count>0){
		$('.menu-notifications').addClass('active');
	}
	$('.view-dropdown-notifications').html(data);
}
function close_dropdown(resize){
	$('.menu-notifications').removeClass('arrow');
	$('.view-dropdown-notifications').css('display','none');

	$('.menu-energy').removeClass('arrow');
	$('.view-dropdown-currencies').css('display','none');

	$('.menu-dropdown').removeClass('arrow');
	$('.view-dropdown').css('display','none');
	$('.multi-account-selector-dropdown').css('display','none');
	if(false==resize){
		$('.repost-card-dropdown').css('display','none');
	}
	vote_card_action.wait=0;
	$('.vote-card-dropdown').css('display','none');
	user_card_action.wait=0;
	$('.user-card-dropdown').css('display','none');
}
function update_notifications_list(){
	$.ajax({
		type:'POST',
		url:'/ajax/notifications_list/',
		success:function(data_json){
			data_obj=JSON.parse(data_json);
			if(-1!=notifications_list_count){
				if(data_obj.count!=notifications_list_count){
					//sound
				}
			}
			notifications_list_count=data_obj.count;
			if(typeof data_obj.result !== 'undefined'){
				set_notifications_list(data_obj.result);
			}
		}
	});
	window.clearTimeout(update_notifications_list_timer);
	update_notifications_list_timer=window.setTimeout(function(){update_notifications_list();},60000);
}
function update_notify_replies_count(){
	update_feed_max_post_id();
	$.ajax({
		type:'POST',
		url:'/ajax/new_replies_count/',
		success:function(data_json){
			data_obj=JSON.parse(data_json);
			if(typeof data_obj.result !== 'undefined'){
				set_notify_replies_count(data_obj.result);
			}
		}
	});
	window.clearTimeout(notify_replies_timer);
	notify_replies_timer=window.setTimeout(function(){update_notify_replies_count();},60000);
}
function update_posts_view(){
	if(1==user.hide_flag_action){
		$('.post-card').each(function(){
			$(this).find('.flag-action').css('display','none');
		});
	}
	if(0==user.hide_flag_action){
		$('.post-card').each(function(){
			$(this).find('.flag-action').css('display','inline-block');
		});
	}
	if(1==user.hide_tags_preview_action){
		$('a.post-card').each(function(){
			$(this).find('.post-tags').css('display','none');
		});
	}
	if(0==user.hide_tags_preview_action){
		$('a.post-card').each(function(){
			$(this).find('.post-tags').css('display','block');
		});
	}
	if(1==user.adult_filter_select){
		$('.post-adult').each(function(){
			$(this).removeClass('post-adult');
		});
	}
	if(2==user.adult_filter_select){
		$('.post-adult').each(function(){
			$(this).css('display','none');
		});
	}
}
function group_reposts(){
	$('.repost-userlist').html('');
	$('.post-card').each(function(){
		let repost_id=$(this).attr('data-reblog-id');
		if(repost_id){
			let repost_user=$(this).attr('data-reblog-user');
			let post_id=$(this).attr('data-id');
			let first_repost=$('.post-card[data-id='+post_id+']:first');
			if(typeof first_repost.attr('data-reblog-id') !== 'undefined'){
				if(first_repost.attr('data-reblog-id')!=repost_id){
					first_repost.find('.post-reblog-info .repost-userlist').append('@'+repost_user+', ');
					$('.post-card[data-reblog-id='+repost_id+']').css('display','none');
				}
			}
		}
	});
}
function update_comments_view(){
	if(1==user.hide_flag_action){
		$('.comment-card').each(function(){
			$(this).find('.flag-action').css('display','none');
		});
	}
	if(0==user.hide_flag_action){
		$('.comment-card').each(function(){
			$(this).find('.flag-action').css('display','inline-block');
		});
	}
	if(typeof user.profile.login === 'undefined'){
		if(typeof preset.user_profile !== 'undefined'){
			if(typeof preset.user_profile.login !== 'undefined'){
				user.profile=preset.user_profile;
			}
		}
	}
	if(1==user.verify){
		$('.comments .comment-card').each(function(){
			if($(this).attr('data-author')==user.profile.login){
				if($(this).find('.comment-info .comment-edit').length==0){
					$(this).find('.comment-info').prepend('<a class="comment-edit"><i class="fa fa-fw fa-pencil" aria-hidden="true"></i> '+l10n.comment_card.edit+'</a>');
					if($('.comments .comment-card[data-parent='+$(this).attr('data-id')+']').length==0){
						if('0'==$('.comments .comment-card[data-id='+$(this).attr('data-id')+'] .comment-upvotes span').html()){
							if('0'==$('.comments .comment-card[data-id='+$(this).attr('data-id')+'] .comment-flags span').html()){
								$(this).find('.comment-info').prepend('<a class="comment-delete"><i class="fa fa-fw fa-times" aria-hidden="true"></i> '+l10n.comment_card.delete+'</a>');
							}
						}
					}
				}
			}
		});
	}
}
function update_datetime(){
	$('.timestamp').each(function(){
		$(this).html(date_str($(this).attr('data-timestamp')*1000,true,true));
	});
}
$(document).ready(function(){
	var hash_load=window.location.hash;
	if(''!=hash_load){
		window.location.hash='';
		window.location.hash=hash_load;
	}

	gate_connect();
	local_user_init();

	bind_menu();
	rebuild_user_cards();
	update_post_payout(user.default_currency);
	update_posts_dates();
	update_comments_dates();
	update_posts_view();
	update_comments_view();
	update_dropdown_currencies(user.default_currency);
	update_user_metadata();
	blogpost_view();
	group_reposts();

	update_datetime();
	unlock_active_key_form();
	unlock_owner_key_form();

	window_width=window.innerWidth;
	if($('.comments').length>0){
		update_comments_list_timer=window.setTimeout(function(){update_comments_list();},update_comments_list_timeout);
	}
	document.addEventListener('keyup', app_keyboard, false);
	document.addEventListener('click', app_mouse, false);
	document.addEventListener('tap', app_mouse, false);
	//document.addEventListener('touchstart', app_mouse, false);
	$('input[name=login-button]').bind('click',function(){
		$('.login-form .login_error').css('display','none');
		user_auth($('.login-form input[name=login]').val().trim(),$('.login-form input[name=posting_key]').val().trim());
	});
	$('input[name=close-button]').bind('click',function(){
		close_modal();
	});
	if($('#search-user-list').length>0){
		bind_search_user_list();
	}
	check_load_more();
	$(window).scroll(function(){
		check_load_more();
	});
	$(window).resize(function(){
		window_width=window.innerWidth;
		close_dropdown(true);
		$('.menu-dropdown').find('.fa').addClass('fa-caret-right');
		$('.menu-dropdown').find('.fa').removeClass('fa-caret-down');
		/* // Smartphone bug - when clicked on textarea window resized by keyboard
		repost_card_action.wait=0;
		$('.repost-card-dropdown').css('display','none');
		*/
		check_load_more();
	});
	if($('.responsive-background').length>0){
		set_parallax_background('.responsive-background');
		$(window).scroll(function(){
			set_parallax_background('.responsive-background');
		});
		$(window).bind('touchmove',function(){
			set_parallax_background('.responsive-background');
		});
	}
	if($('.hide_flag_action').length>0){
		$('.hide_flag_action').val(user.hide_flag_action);
		$('.hide_flag_action').change(function(){
			user.hide_flag_action=parseInt($(this).val());
			localStorage.setItem('hide_flag_action',user.hide_flag_action);
			update_posts_view();
			update_comments_view();
		});
	}
	if($('.hide_tags_preview_action').length>0){
		$('.hide_tags_preview_action').val(user.hide_tags_preview_action);
		$('.hide_tags_preview_action').change(function(){
			user.hide_tags_preview_action=parseInt($(this).val());
			localStorage.setItem('hide_tags_preview_action',user.hide_tags_preview_action);
			update_posts_view();
		});
	}
	if($('.blogpost_show_menu_action').length>0){
		$('.blogpost_show_menu_action').val(user.blogpost_show_menu);
		$('.blogpost_show_menu_action').change(function(){
			user.blogpost_show_menu=parseInt($(this).val());
			localStorage.setItem('blogpost_show_menu',user.blogpost_show_menu);
			var expire = new Date();
			expire.setTime(expire.getTime() + 350 * 24 * 3600 * 1000);
			document.cookie='blogpost_show_menu='+user.blogpost_show_menu+'; expires='+expire.toUTCString()+'; path=/; domain=goldvoice.club;';
		});
	}
	if($('.adult_filter_select').length>0){
		$('.adult_filter_select').val(user.adult_filter_select);
		$('.adult_filter_select').change(function(){
			user.adult_filter_select=parseInt($(this).val());
			localStorage.setItem('adult_filter_select',user.adult_filter_select);
			update_posts_view();
		});
	}
	if($('.default_currency_select').length>0){
		$('.default_currency_select').val(user.default_currency);
		$('.default_currency_select').change(function(){
			user.default_currency=$(this).val();
			localStorage.setItem('default_currency',user.default_currency);
			update_post_payout(user.default_currency);
			update_dropdown_currencies(user.default_currency);
		});
	}
	if($('.post_percent_select').length>0){
		$('.post_percent_select').val(user.post_percent);
		$('.post_percent_select').change(function(){
			user.post_percent=parseInt($(this).val());
			localStorage.setItem('post_percent',user.post_percent);
		});
	}
	if($('.comment_percent_select').length>0){
		$('.comment_percent_select').val(user.comment_percent);
		$('.comment_percent_select').change(function(){
			user.comment_percent=parseInt($(this).val());
			localStorage.setItem('comment_percent',user.comment_percent);
		});
	}
	if($('.post_autovote_action').length>0){
		if(1==user.post_autovote){
			$('.post_autovote_action').prop('checked','checked');
		}
		else{
			$('.post_autovote_action').prop('checked','');
		}
		$('.post_autovote_action').change(function(){
			user.post_autovote=0;
			if($('.post_autovote_action:checked').length>0){
				user.post_autovote=1;
			}
			localStorage.setItem('post_autovote',user.post_autovote);
		});
	}
	if($('.feed_view_mode_select').length>0){
		$('.feed_view_mode_select').val(user.feed_view_mode);
		$('.feed_view_mode_select').change(function(){
			user.feed_view_mode=$(this).val();
			localStorage.setItem('feed_view_mode',user.feed_view_mode);
			if(0<$('.feed_view_mode').length){
				$('.feed_view_mode').html(feed_view_modes[user.feed_view_mode]);
				apply_feed_view_mode();
			}
		});
	}
	if(0<$('.post-votes-stats').length){
		update_post_votes_stats();
	}
	if(0<$('.post-reposts-stats').length){
		recalc_post_reposts_payback();
	}

	if(0<$('input[name=payback-percent]').length){
		$('input[name=payback-size]').bind('keyup',function(){
			let part_payout_value=parseFloat($('.payback-payment-part').html());
			let payback_size=parseFloat($(this).val()).toFixed(3);
			let payback_percent=((payback_size*100)/part_payout_value).toFixed(3);
			$('input[name=payback-percent]').val(payback_percent);
			recalc_post_votes_payback();
		})
		$('input[name=payback-percent]').bind('keyup',function(){
			let part_payout_value=parseFloat($('.payback-payment-part').html());
			let payback_percent=parseFloat($(this).val()).toFixed(3);
			let payback_size=(part_payout_value*(payback_percent/100)).toFixed(3);
			$('input[name=payback-size]').val(payback_size);
			recalc_post_votes_payback();
		})
		$('input[name=payback-threshold]').bind('keyup',function(){
			recalc_post_votes_payback();
		})
		$('input[name=payback-ignore-threshold]').bind('change',function(){
			recalc_post_votes_payback();
		})
	}

	if(0<$('select[name=payback-repost-asset]').length){
		$('input[name=payback-repost-amount]').bind('keyup',function(){
			recalc_post_reposts_payback();
		})
		$('select[name=payback-repost-type]').bind('change',function(){
			recalc_post_reposts_payback();
		})
	}
	if(0<$('.menu.collapsed').length){
		$('.menu .expand-hide').css('display','none');
		$('.adaptive-menu').css('display','inline-block');
	}
	if(0<$('textarea[name=payback-stop-list]').length){
		if(null!=localStorage.getItem('payback-stop-list')){
			$('textarea[name=payback-stop-list]').val(localStorage.getItem('payback-stop-list'));
		}
		$('textarea[name=payback-stop-list]').bind('keyup',function(){
			localStorage.setItem('payback-stop-list',$(this).val());
		});
	}
	if(0<$('input[name=payback-comment]').length){
		if(null!=localStorage.getItem('payback-comment')){
			$('input[name=payback-comment]').val(localStorage.getItem('payback-comment'));
		}
		$('input[name=payback-comment]').bind('keyup',function(){
			localStorage.setItem('payback-comment',$(this).val());
		});
	}
	if(0<$('textarea[name=payback-repost-stop-list]').length){
		if(null!=localStorage.getItem('payback-repost-stop-list')){
			$('textarea[name=payback-repost-stop-list]').val(localStorage.getItem('payback-repost-stop-list'));
		}
		$('textarea[name=payback-repost-stop-list]').bind('keyup',function(){
			localStorage.setItem('payback-repost-stop-list',$(this).val());
		});
	}
	if(0<$('input[name=payback-repost-comment]').length){
		if(null!=localStorage.getItem('payback-repost-comment')){
			$('input[name=payback-repost-comment]').val(localStorage.getItem('payback-repost-comment'));
		}
		$('input[name=payback-repost-comment]').bind('keyup',function(){
			localStorage.setItem('payback-repost-comment',$(this).val());
		});
	}
	if(0<$('select[name=payback-repost-type]').length){
		if(null!=localStorage.getItem('payback-repost-type')){
			$('select[name=payback-repost-type]').val(localStorage.getItem('payback-repost-type'));
		}
		$('select[name=payback-repost-type]').bind('change',function(){
			localStorage.setItem('payback-repost-type',$(this).val());
		});
	}
	if(0<$('.posts-list-filter').length){
		posts_list_filter_form();
		let posts_list_filter_show=[];
		let posts_list_filter_hide=[];
		if(null!=localStorage.getItem('posts_list_filter_show')){
			posts_list_filter_show=JSON.parse(localStorage.getItem('posts_list_filter_show'));
		}
		if(null!=localStorage.getItem('posts_list_filter_hide')){
			posts_list_filter_hide=JSON.parse(localStorage.getItem('posts_list_filter_hide'));
		}
		for(i in posts_list_filter_show){
			post_list_filter_show_add(posts_list_filter_show[i],true);
		}
		for(i in posts_list_filter_hide){
			post_list_filter_hide_add(posts_list_filter_hide[i],true);
		}
		posts_list_filter(true);
	}
	if(0<$('input[name=generate_wif_user]').length){
		var generate_wif=function(){
			let wif_user=$('input[name=generate_wif_user]').val();
			$('.public_wif_list .public_wif_owner').html('&hellip;');
			$('.public_wif_list .public_wif_active').html('&hellip;');
			$('.public_wif_list .public_wif_posting').html('&hellip;');
			$('.public_wif_list .public_wif_memo').html('&hellip;');
			$('.public_wif_list .public_wif_sign').html('&hellip;');
			gate.api.getAccounts([wif_user],function(err,response){
				if(!err){
					if(typeof response[0] !== 'undefined'){
						let public_wif_owner=response[0].owner.key_auths[0][0];
						$('.public_wif_list .public_wif_owner').html(public_wif_owner);
						let public_wif_active=response[0].active.key_auths[0][0];
						$('.public_wif_list .public_wif_active').html(public_wif_active);
						let public_wif_posting=response[0].posting.key_auths[0][0];
						$('.public_wif_list .public_wif_posting').html(public_wif_posting);
						let public_wif_memo=response[0].memo_key;
						$('.public_wif_list .public_wif_memo').html(public_wif_memo);
					}
				}
			});
			gate.api.getWitnessByAccount(wif_user,function(err,response){
				if(!err){
					if(typeof response.signing_key !== 'undefined'){
						let public_wif_signing=response.signing_key;
						$('.public_wif_list .public_wif_sign').html(public_wif_signing);
					}
				}
			});
		}
		generate_wif();
		$('input[name=generate_wif_user]').bind('keyup',function(){
			generate_wif();
		});
		$('input[name=generate_wif_password]').bind('keyup',function(){
			let wif_user=$('input[name=generate_wif_user]').val();
			let owner_key=gate.auth.toWif(wif_user,$(this).val(),'owner');
			$('.generate_wif_list .generate_wif_owner').html(owner_key);
			if(gate.auth.wifIsValid(owner_key,$('.public_wif_list .public_wif_owner').html())){
				$('.public_wif_list .public_wif_owner').css('color','#0b0');
			}
			else{
				$('.public_wif_list .public_wif_owner').css('color','#b00');
			}
			let active_key=gate.auth.toWif(wif_user,$(this).val(),'active');
			$('.generate_wif_list .generate_wif_active').html(active_key);
			if(gate.auth.wifIsValid(active_key,$('.public_wif_list .public_wif_active').html())){
				$('.public_wif_list .public_wif_active').css('color','#0b0');
			}
			else{
				$('.public_wif_list .public_wif_active').css('color','#b00');
			}
			let posting_key=gate.auth.toWif(wif_user,$(this).val(),'posting');
			$('.generate_wif_list .generate_wif_posting').html(posting_key);
			if(gate.auth.wifIsValid(posting_key,$('.public_wif_list .public_wif_posting').html())){
				$('.public_wif_list .public_wif_posting').css('color','#0b0');
			}
			else{
				$('.public_wif_list .public_wif_posting').css('color','#b00');
			}
			let memo_key=gate.auth.toWif(wif_user,$(this).val(),'memo');
			$('.generate_wif_list .generate_wif_memo').html(memo_key);
			if(gate.auth.wifIsValid(memo_key,$('.public_wif_list .public_wif_memo').html())){
				$('.public_wif_list .public_wif_memo').css('color','#0b0');
			}
			else{
				$('.public_wif_list .public_wif_memo').css('color','#b00');
			}
			let sign_key=gate.auth.toWif(wif_user,$(this).val(),'sign');
			$('.generate_wif_list .generate_wif_sign').html(sign_key);
		});
	}
	if(0<$('.wallet_action .wallet-transfer select[name=asset]').length){
		$('.wallet_action .wallet-transfer select[name=asset]').bind('change',function(){
			if('GOLOS'==$('.wallet_action .wallet-transfer select[name=asset]').val()){
				$('.wallet_action .wallet-vesting').css('display','block');
				$('.wallet_action .wallet-savings input[name=savings]').prop('checked',false);
			}
			else{
				$('.wallet_action .wallet-vesting').css('display','none');
				$('.wallet_action .wallet-vesting input[name=vesting]').prop('checked',false);
				$('.wallet_action .wallet-savings input[name=savings]').prop('checked',false);
			}
		});
		$('.wallet_action .wallet-savings input[name=savings]').bind('change',function(){
			$('.wallet_action .wallet-vesting input[name=vesting]').prop('checked',false);
		});
		$('.wallet_action .wallet-vesting input[name=vesting]').bind('change',function(){
			$('.wallet_action .wallet-savings input[name=savings]').prop('checked',false);
		});
		function wallet_history_filter(){
			var filter=$('input[name=wallet-history-filter]').val();
			$('.wallet-history tbody tr').removeClass('filtered');
			$('.wallet-history tbody tr').each(function(){
				if('none'!=$(this).css('display')){
					let pos=$(this).text().toLowerCase().indexOf(filter);
					if(-1!==pos){

					}
					else{
						$(this).addClass('filtered');
					}
				}
			});
			var filter_amount=parseFloat(parseFloat($('input[name=wallet-history-filter-amount1]').val().replace(',','.')).toFixed(3));
			var filter_amount2=parseFloat(parseFloat($('input[name=wallet-history-filter-amount2]').val().replace(',','.')).toFixed(3));
			$('.wallet-history tbody tr').each(function(){
				var found_amount=parseFloat(parseFloat($(this).find('td[rel=amount]').text()).toFixed(3));
				if('none'!=$(this).css('display')){
					if(filter_amount>0){
						if(filter_amount>found_amount){
							$(this).addClass('filtered');
						}
					}
					if(filter_amount2>0){
						if(filter_amount2<found_amount){
							$(this).addClass('filtered');
						}
					}
				}
			});
		}
		$('input[name=wallet-history-filter]').bind('keyup',function(){
			wallet_history_filter();
		});
		$('input[name=wallet-history-filter-amount1]').bind('keyup',function(){
			wallet_history_filter();
		});
		$('input[name=wallet-history-filter-amount2]').bind('keyup',function(){
			wallet_history_filter();
		});
	}
	if(0<$('.wallet-send-action').length){
		if(typeof $('.wallet-send-action').attr('data-action') != 'undefined'){
			if('true'==$('.wallet-send-action').attr('data-action-onload')){
				setTimeout($('.wallet-send-action').attr('data-action')+'()',100);
			}
		}
	}
	if(0<$('.profile-select-background-color').length){
		$('.profile-select-background-color').each(function(){
			$(this).css('background','#'+$(this).attr('rel'));
		});
	}
	if(typeof blogger_background_color !== 'undefined'){
		$('.header-line').css('background-image','none');
		$('.header-line').css('background-color',blogger_background_color);
	}
	if(0<$('.registration-form').length){
		function registration_form_important(){
			$('.registration-form input[name=login]').val($('.registration-form input[name=login]').val().replace(/([^a-z0-9\.\-]*)/g,''));
			let login=$('.registration-form input[name=login]').val();
			let master_password=$('.registration-form input.pass').val();
			if(''!=login){
				gate.api.getAccounts([login],function(err,response){
					if(0!=response.length){
						$('.registration-form input[name=login]').css('border-color','rgb(255, 21, 21)');
					}
					else{
						let user_login_exp = /^[a-z][-\.a-z\d]+[a-z\d]$/g;
						if(!user_login_exp.test($('.registration-form input[name=login]').val())){
							$('.registration-form input[name=login]').css('border-color','rgb(255, 21, 21)');
						}
						else{
							$('.registration-form input[name=login]').css('border-color','#0bea4d');
						}
					}
				});
				if(''!=master_password){
					if(8>master_password.length){
						$('.registration-form input.pass').css('border-color','rgb(255, 21, 21)');
					}
					else{
						$('.registration-form input.pass').css('border-color','#0bea4d');
						let owner_key=gate.auth.toWif(login,master_password,'owner');
						let owner_key_pub=gate.auth.wifToPublic(owner_key);
						$('.registration-form input[name=owner]').val(owner_key_pub);
						let active_key=gate.auth.toWif(login,master_password,'active');
						let active_key_pub=gate.auth.wifToPublic(active_key);
						$('.registration-form input[name=active]').val(active_key_pub);
						let posting_key=gate.auth.toWif(login,master_password,'posting');
						let posting_key_pub=gate.auth.wifToPublic(posting_key);
						$('.registration-form input[name=posting]').val(posting_key_pub);
						let memo_key=gate.auth.toWif(login,master_password,'memo');
						let memo_key_pub=gate.auth.wifToPublic(memo_key);
						$('.registration-form input[name=memo]').val(memo_key_pub);
						let important='';
						important+='GoldVoice Login: '+login+'<br>';
						important+='GoldVoice Master Password: '+master_password+'<br>';
						important+=l10n.registration.form_active_key+': '+active_key+'<br>';
						important+=l10n.registration.form_posting_key+': '+posting_key+'<br>';
						//important+='Owner key (ключ владельца): '+owner_key+'<br>';
						//important+='Memo key (ключ заметок): '+memo_key+'<br>';
						important+=l10n.registration.form_additional_info+'<br>';
						important+=l10n.registration.form_additional_info2;
						$('.registration-form .important').html(important);
						$('.registration-form .important').css('line-height','30px');
						$('.registration-form .important').css('padding','8px');
						$('.registration-form .important').css('margin-bottom','8px');
						$('.registration-form .important').css('background','#fff4e6');
						$('.registration-form .important').css('border','1px solid #ffa93a');
					}
				}
			}
		}
		$('.registration-form input.pass').unbind('keyup')
		$('.registration-form input.pass').bind('keyup',function(){registration_form_important();});
		$('.registration-form input[name=login]').unbind('keyup')
		$('.registration-form input[name=login]').bind('keyup',function(){registration_form_important();});
	}
	if(0<$('.wallet-history').length){
		update_user_wallet_history();
	}
	if(0<$('.user_voting_power').length){
		$('.user_voting_power').each(function(){
			let test_login=$(this).attr('data-login');
			gate.api.getAccounts([test_login],function(err,response){
				if(!err){
					let last_vote_time=Date.parse(response[0].last_vote_time);
					let delta_time=parseInt((new Date().getTime() - last_vote_time+(new Date().getTimezoneOffset()*60000))/1000);
					let voting_power=response[0].voting_power;
					let new_voting_power=parseInt(voting_power+(delta_time/43.2));
					if(new_voting_power>10000){
						new_voting_power=10000;
					}
					if(typeof response[0].voting_power !== 'undefined'){
						$('.user_voting_power[data-login='+test_login+']').html(''+(new_voting_power/100).toFixed(2)+'%');
						$('.user_voting_power_recovery[data-login='+test_login+']').html(''+((10000-new_voting_power)*43.2/60).toFixed(2)+'');
						$('.user_voting_power_recovery_sec[data-login='+test_login+']').html(''+Math.round((10000-new_voting_power)*43.2)+'');
					}
				}
			});
		});
	}
	if(0<$('.profile-update').length){
		profile_update();
	}
	if(0<$('.l10n').length){
		$('.l10n').each(function(){
			$(this).html(l10n[$(this).attr('data-cat')][$(this).attr('data-name')]);
		});
	}
	rebuild_comments_votes();
	rebuild_posts_votes();
	rebuild_comments_flags();
	rebuild_posts_flags();
});
(function(){
	if(typeof self === 'undefined' || !self.Prism || !self.document){
		return;
	}
	Prism.hooks.add('complete',function(env){
		if(!env.code){
			return;
		}
		var pre = env.element.parentNode;
		pre.className += ' line-numbers';
		var match = env.code.match(/\n(?!$)/g);
		var linesNum = match ? match.length + 1 : 1;
		var lineNumbersWrapper;
		var lines = new Array(linesNum + 1);
		lines = lines.join('<span></span>');
		lineNumbersWrapper = document.createElement('span');
		lineNumbersWrapper.setAttribute('aria-hidden', 'true');
		lineNumbersWrapper.className = 'line-numbers-rows';
		lineNumbersWrapper.innerHTML = lines;
		env.element.appendChild(lineNumbersWrapper);
	});
}());