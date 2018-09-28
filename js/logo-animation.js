var time_offset = 0;
var time_last_frame = 0;
var time_last_mousemove = 0;
time_last_mousemove=Date.now()-3000;

var half_width=window.innerWidth/2;
var half_height=window.innerHeight/2;

var screen=document.getElementById("screen");
//screen.style.height=window.innerHeight+'px';
screen.width=screen.clientWidth;
screen.height=screen.clientHeight;

var center_x=screen.width/2;
var center_y=screen.height/2;

var mouse_x=center_x;
var mouse_y=center_y;

var stage;
var renderer;

function refresh_screen_size(){
	//screen.style.height=window.innerHeight+'px';
	//screen.style.width=window.innerWidth+'px';
	//screen.width=screen.clientWidth;
	//screen.height=screen.clientHeight;
	half_width=window.innerWidth/2;
	half_height=window.innerHeight/2;
	center_x=screen.width/2;
	center_y=screen.height/2;
	logo_position();
	renderer = PIXI.autoDetectRenderer(
		screen.width,
		screen.height,
		{
			backgroundColor: 0xffffff,
			view: document.getElementById("screen"),
		}
	);
	refresh_screen=0;
}
window.addEventListener('orientationchange', function(event){
	if(0==refresh_screen){
		refresh_screen=setTimeout('refresh_screen_size()',50);
	}
});
window.addEventListener('resize', function(event){
	if(0==refresh_screen){
		refresh_screen=setTimeout('refresh_screen_size()',50);
	}
});
function getRandomInt(min, max){
  return Math.floor(Math.random() * (max - min + 1)) + min;
}
/*
window.onmousemove=function(e){
	x_change=Math.abs(mouse_x-e.clientX)/25;
	y_change=Math.abs(mouse_y-e.clientY)/25;
	if(mouse_x>e.clientX){
		mouse_x-=x_change;
	}
	else{
		mouse_x+=x_change;
	}
	if(mouse_y>e.clientY){
		mouse_y-=y_change;
	}
	else{
		mouse_y+=y_change;
	}
	time_last_mousemove=Date.now();
}*/
var globe,symbol_sum,symbol,symbol_shadow,symbol_blink,symbol_mask,border_mask,border_gradient,blurFilter,scale_logo;
var globe_center=250;
function load_resources(){
	globe=new PIXI.Sprite(PIXI.Texture.fromImage("/images/globe.png"));//500x500
	symbol_shadow=new PIXI.Sprite(PIXI.Texture.fromImage("/images/symbol_shadow.png"));//318x264
	border_mask=new PIXI.Sprite(PIXI.Texture.fromImage("/images/border_mask.png"));//284x229
	border_gradient=new PIXI.Sprite(PIXI.Texture.fromImage("/images/border_gradient.png"));//311x311
	border_gradient.mask=border_mask;
	border_gradient.position.x=100;
	border_gradient.position.y=-200;
	symbol_mask=new PIXI.Sprite(PIXI.Texture.fromImage("/images/symbol_mask.png"));//284x229
	symbol_blink=new PIXI.Sprite(PIXI.Texture.fromImage("/images/symbol_blink.png"));//457x385
	symbol_blink.mask=symbol_mask;
	symbol=new PIXI.Sprite(PIXI.Texture.fromImage("/images/symbol.png"));//284x229
	blurFilter = new PIXI.filters.BlurFilter();
	blurFilter.blur=0.2;
	border_mask.filters=[blurFilter];
	border_mask.alpha=0.5;
	symbol_sum=new PIXI.Container();
	logo_sum=new PIXI.Container();
	symbol_sum.addChild(symbol_shadow);
	symbol_sum.addChild(symbol);
	symbol_sum.addChild(symbol_blink);
	symbol_sum.addChild(symbol_mask);
	symbol_sum.addChild(border_gradient);
	symbol_sum.addChild(border_mask);
	logo_sum.addChild(globe);
	logo_sum.addChild(symbol_sum);
}
function logo_position(){
	symbol_shadow.position.x=-17;
	symbol_shadow.position.y=-12;
	border_gradient.position.x=-155;
	border_gradient.position.y=-155;
	border_gradient.alpha=0.8;
	symbol_blink.position.x=-86;
	symbol_blink.position.y=-78;
	symbol_sum.position.x=-142+globe_center;
	symbol_sum.position.y=-114+globe_center;
	symbol_sum.position.y+=20;
	//scale_logo=(half_width+half_width)/500;
	scale_logo=(center_x+center_x)/500;
	if(scale_logo>=1){
		scale_logo=1.0;
	}
	scale_logo2=(center_y+center_y)/500;
	if(scale_logo2>=1){
		scale_logo2=1.0;
	}
	logo_sum.scale.x=Math.min(scale_logo,scale_logo2);
	logo_sum.scale.y=Math.min(scale_logo,scale_logo2);
	logo_sum.scale.x*=0.8;
	logo_sum.scale.y*=0.8;
	logo_sum.position.x=(screen.width-logo_sum.width)/2;
	logo_sum.position.y=(screen.height-logo_sum.height)/2;
}
function logo_animation_init(){
	stage = new PIXI.Container();
	renderer = PIXI.autoDetectRenderer(
		screen.width,
		screen.height,
		{
			backgroundColor: 0xffffff,
			view: document.getElementById("screen"),
		}
	);
	load_resources();
	logo_position();
	stage.addChild(logo_sum);
	refresh_screen_size();
	requestAnimationFrame(logo_animation_update);
}
function blink_animation(){
	min_x=-250;
	max_x=100;
	x_dist=350;
	min_y=-150;
	max_y=-10;
	y_dist=160;
	new_y=min_y + (mouse_y/window.innerHeight * y_dist);
	if(new_y<min_y){new_y=min_y;}
	if(new_y>max_y){new_y=max_y;}
	symbol_blink.position.y=new_y;
	new_x=min_x + (mouse_x/window.innerWidth * x_dist);
	if(new_x<min_x){new_x=min_x;}
	if(new_x>max_x){new_x=max_x;}
	symbol_blink.position.x=new_x;
}
function border_animation(){
	min_x=-190;
	max_x=190;
	x_dist=380;
	min_y=-190;
	max_y=100;
	y_dist=290;
	new_y=max_y - (mouse_y/window.innerHeight * y_dist);
	if(new_y<min_y){new_y=min_y;}
	if(new_y>max_y){new_y=max_y;}
	border_gradient.position.y=new_y;
	new_x=max_x - (mouse_x/window.innerWidth * x_dist);
	if(new_x<min_x){new_x=min_x;}
	if(new_x>max_x){new_x=max_x;}
	border_gradient.position.x=new_x;
}
function mouse_animation(){
	var new_mouse_x=half_width + (Math.sin(Date.now()/1000) * half_width);
	var new_mouse_y=half_height + (Math.cos(Date.now()/1000) * half_height);
	x_change=Math.abs(mouse_x-new_mouse_x)/15;
	y_change=Math.abs(mouse_y-new_mouse_y)/15;
	if(mouse_x>new_mouse_x){
		mouse_x-=x_change;
	}
	else{
		mouse_x+=x_change;
	}
	if(mouse_y>new_mouse_y){
		mouse_y-=y_change;
	}
	else{
		mouse_y+=y_change;
	}
}
function logo_animation_update(){
	time_offset=Date.now() - time_last_frame;
	time_mouse_offset=Date.now() - time_last_mousemove;
	if(time_offset>=15){
		logo_position();
		blink_animation();
		border_animation();
		if(time_mouse_offset>3000){
			mouse_animation();
		}
		renderer.render(stage);
		time_last_frame=Date.now();
	}
	requestAnimationFrame(logo_animation_update);
}
$(document).ready(function(){
	logo_animation_init()
});