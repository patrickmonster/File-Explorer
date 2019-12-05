<?
$view_ext=array("php","db",/*"html",*/"htm","js","css","exe","smi","sti");//검색제외항목 확장자
$media_ext=array("mp4","mp3","flac");//미디어 자막 파일 확장자.
//모바일 체크
$mAgent = array("iPhone","iPod","Android","Blackberry", 
    "Opera Mini", "Windows ce", "Nokia", "sony" );
$chkMobile = false;
for($i=0; $i<sizeof($mAgent); $i++){
    if(stripos( $_SERVER['HTTP_USER_AGENT'], $mAgent[$i] )){
        $chkMobile = true;
        break;
    }
}//모바일 체크
function h($var){
	if (is_array($var))return array_map('h', $var);
	else return htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
}

function getFileProperty($file){//파일 정보
	global $dir;
	$arr=array();
	$arr["lasttime"] = date("Y.m.d H시", filectime($dir."/".$file));
	$point=mb_strripos($file,"/",0,"utf-8");// 마지막 / 요소 찾음
	$len=mb_strlen($file,'UTF-8');
	if(!$point)$point=-1;//경로명이 아닐경우,공백으로 대입
	//$arr["dir"]=mb_substr($file,0,$point+1,'utf-8');//디렉토리
	$arr["file"]=mb_substr($file,$point+1,$len,'utf-8');//파일전채명
	$point=mb_strripos($file,".",$point,"utf-8");//파일 확장자
	if($point){//파일 포인터가 존재하면서, 파일인 요소
		$arr["ext"]=mb_substr($file,$point+1,$len-$point,'utf-8');
		$arr["name"]=mb_substr($arr["file"],0,$point,'utf-8');
		//$ext=mb_substr($file,$point+1,$len-$point,'utf-8');
		$arr["type"]=getTypeExt($arr[ext]);//파일 확장자/타입 불러온다
	}else{//확장자가 없네?/파일이 아니네?
		$arr["ext"]=false;//확장자
		$arr["name"]=$arr["file"];
		$arr["type"]="";//파일 타입
	}
	return $arr;
}
function getTypeExt($ext){//파일 스트림 확장용
	$a=getTypeExtN($ext);
	if($a)return $a."/".$ext;
	else return "application/octet-stream";
}
function getTypeExtN($ext){
			$ext=strtolower($ext);
	switch($ext){
		case "jpg":case "png":case "jpeg":case "bmp":case "ico":case "gif":
			return "image";
		case "mp3":case "flac":
			return "audio";
		case "mp4":case "mkv":case "avi":
			return "video";
		case "smi": case "sti":
			return "subtitle";
		case "txt": return "text";
		default:
			return false;
	}
}
function getIMG($type){//이미지
	switch($type){
		case "folder":case "dir":	return "http://hostdir/image/exp_folder.gif";
		case "zip":		return "http://hostdir/image/exp_archive.gif";
		case "music":	return "http://hostdir/image/exp_music.gif";
		case "video":	return "http://hostdir/image/exp_movie.gif";
		case "img":		return "http://hostdir/image/exp_pic.gif";
		case "document":case "page":case "text":return "http://hostdir/image/exp_office.gif";
		case "file":	return "http://hostdir/image/exp_etc.gif";
		case "up":	return "http://hostdir/image/icon_upper_black.gif";
		case "exit": return "http://hostdir/image/icon_bt_cencle.gif";
		case "down": return "http://hostdir/image/icon_bt_import.gif";
	}
}

function fopen_utf8($filename){
	$encoding='';
	$handle = fopen($filename, 'r');
	$bom = fread($handle, 2);
	//  fclose($handle);
	rewind($handle);
	if($bom === chr(0xff).chr(0xfe)  || $bom === chr(0xfe).chr(0xff)){
			$encoding = 'UTF-16';
	} else {
		$file_sample = fread($handle, 1000) + 'e'; //read first 1000 bytes
		rewind($handle);
		$encoding = mb_detect_encoding($file_sample , 'UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP');
	}
	if ($encoding)stream_filter_append($handle, 'convert.iconv.'.$encoding.'/UTF-8');
	return ($handle);
}
function getSMI($f){//자막 인데 이건 접어두고
	$fp = fopen_utf8($f);//utf-8로 데이터 읽어들이기
	if(!$fp){
		echo "못읽음";
		die;
	}
	$index = -1;	//행의 개수
	$col_time = 0;	//시간을 담는 행
	$col_text = 1;	//문자를 담는 행
	$text = "";
	while(!feof($fp)){
		$line=@fgets($fp);
		if(stristr($line , "<Sync")){//파일 헤더
			$index++;//라인 카운팅
			$text = "";//
			$start = strpos($line , "=")+1;//시간부분 시작 위치
			$end = strpos($line , ">");//시간부분 끝 위치
			$time = substr($line , $start , $end-$start);//실제 자막 적용되는 타이밍
			if(strchr($time , " "))//공백 제거
				$time = substr($time ,0, strpos($time , " "));//공백 제거
			$smi[$index][$col_time] = $time;//테이블에 시간기록
			$text = strstr($line , ">");//텍스트 시작 위치
			$text = str_replace(array("\r\n","\r","\n"),'',$text);//개행문자 제거
			$text = preg_replace("/<p[^>]*>/i",'', $text);//p테그 제거
			$smi[$index][$col_text]=substr($text , 1 , strlen($text));//추출한 텍스트를 테이블에 기록
		}else{
			$line=str_replace(array("\r\n","\r","\n"),'',$line);//그냥 문자열이기 때문에 추출
			$smi[$index][$col_text].=$line;//추가
		}
	}
	return json_encode($smi,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);//json 형식 출력
}
function compress($source, $destination, $quality) {
	$info = getimagesize($source);
	if ($info['mime'] == 'image/jpeg') 
		$image = imagecreatefromjpeg($source);
	elseif ($info['mime'] == 'image/gif') 
		$image = imagecreatefromgif($source);
	elseif ($info['mime'] == 'image/png') 
		$image = imagecreatefrompng($source);
	imagejpeg($image, $destination, $quality);
	return $destination;
}

function plog($dir,$str){
	$dateObj = new DateTime();
	$access_time = $dateObj->format('Y-m-d H:i');
	$ip_addr = $_SERVER['REMOTE_ADDR'];
	$s =  $access_time . "\t" . $ip_addr . "\t"  . $str . "\r\n";
	$fp = fopen($dir , "ab");
	if (!is_resource($fp)){
		die("파일을 열지 못함");
	}
	flock($fp, LOCK_EX);
	fwrite($fp, $s);
	fflush($fp);
	flock($fp, LOCK_UN);
	fclose($fp);
}

$dir = "/mnt/HDD2/Twitch/";
$tmp = "/mnt/HDD2/tmp/";//임시 저장소
$host_dir = "twitch/"; // 호스트의 디렉토리

$width_max = '1080px';
require_once "../lib/browser.php";//크롬브라우져 필터
if(isset($_GET[log])){
	$tmp .="log/Link.log";
	plog($tmp,h($_GET[log]));
}
if(isset($_GET[dir])){//디렉토리 리턴	//=================================분기점
	$dir.=h($_GET[dir]);
	if (mb_strpos($dir, '.')||!is_dir($dir)){
		json_encode(array(getFileProperty(".")),JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		exit;
	}
	$dirs = scandir($dir);
	$list=array();
	header("Contnet-Type: application/json; charset=UTF-8");
	header("X-Contnet-Type-Options: mosniff");
	foreach($dirs as $file){
		$data=getFileProperty($file);
		if(in_array($data["ext"],$view_ext)||$file==="."||$file==="..")
			continue;//열외 항목들
		if(isset($_GET[type])&&$_GET[type]!==$data["ext"])//데이터 필터
			continue;
		if(in_array($data["ext"],$media_ext)){//미디어 파일 확인
			if(is_file($dir.'/'. $data["name"] .".smi"))
				$data["smi"] = "smi";
			if(is_file($dir.'/'. $data["name"] .".sti"))
				$data["smi"] = "sti";
		}
		$list[]=$data;
	}
	echo json_encode($list,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	exit;
}else if(isset($_GET[window])|| isset($_GET[view])){//iframe에 전송할 내용	//=================================분기점<?
	?><!DOCTYPE html>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<meta name="robots" content="noarchive, nofollow, nosnippet" />
<!--meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="expires" content="0"-->
<meta content='IE=edge,chrome=1' http-equiv='X-UA-Compatible' />
<meta name="viewport" content="width=device-width, user-scalable=no">
<link href="http://hostdir/<?=$host_dir?>vrchat.png" rel="shortcut icon">
<link href="http://hostdir/<?=$host_dir?>vrchat.png" sizes="152x152" rel="apple-touch-icon">
<style>
input[type=range]{
	-webkit-appearance:none;
	outline:none;
	width:160px;
	height:9px;
	border-radius:4px;
	border:1px solid #252525;
	float:left;
	background-color:#f3f3f3;
	margin-left:0;
	margin-top:0
}
input[type="range"]::-webkit-slider-thumb{
	-webkit-appearance:none;
	background-color:#0ae;
	width:8px;
	height:7px
}
#sink[type="range"]::-webkit-slider-thumb{
	-webkit-appearance:none;
	background-color:#0ae;
	border-radius: 10em 10em 10em 10em;
	width:8px;
	height:7px
}
#sink[type=range]{
	width:300px;
	margin-top:8px;
	margin-left:5px;
	background-color:#a7e0f7;
	border:0;
}
#volume[type="range"]::-webkit-slider-thumb{
	-webkit-appearance:none;
	background-color:#0ae;
	border-radius: 10em 10em 10em 10em;
	width:8px;
	height:7px
}
#volume[type=range]{
	width:50px;
	margin-top:8px;
	margin-left:5px;
	background-color:#a7e0f7;
	border:0;
}
</style><?
	$is=isset($_GET[window]);
	$window=($is?h($_GET[window]):h($_GET[view]));
	$file=getFileProperty($dir . $window);
	$type=getTypeExtN($file[ext]);//파일 특성 가져오기
	echo "<title>$file[name]재생중...</title>";
	//echo '[접속중인 주소 :'.$_SERVER['REMOTE_ADDR'];
	echo '<img src='.getIMG("dir").'><font size=3>'.$window .'</font><br>';//헤더
	//echo '<img id=exit src='.getIMG("exit").' onclick="outpage();history.back()" title="돌아가기" class=bt>';//윈도우 종료
	if(!$is)
		echo '<a href="http://hostdir/'.$host_dir.'"><img src='.getIMG("up").'><font size=3>홈으로 이동하기</font></a><br>';
	else {
		echo '<a onclick="copyLink()"><img src='.getIMG("file").'><font size=3>공유를 위한  주소복사</font></a><br>';
		echo '<div style="width:'.$width_max.'">';
		echo '<a onclick="parent.next_data(1)" class=bt title="이전"><img id=begin src="http://hostdir/'.$host_dir.'img/l.png">이전</a>';
		echo '<a onclick="parent.next_data()" class=bt title="다음" style="float:right">다음<img id=next src="http://hostdir/'.$host_dir.'img/r.png"></a>';
		echo '</div>';
	}
	$index=0;
	switch($type){
		case "image":
			?>
			<a onclick="window.location.replace('?get=<?=$_GET[window]?>')" class=bt>
				<img src='<?=getIMG("down")?>' title="이미지 다운로드">다운로드
			</a><br>
			<img id=player width="100%"/><br>
			<?
			break;
		case "audio":
				?>
				<table>
					<tr>
						<td>
							<img style="width:30px;height:30px" title="play/pause" id=play class=bt src="http://hostdir/img/icons/stop_1.png"/>
							 <a onclick="this.childNodes[0].checked = !this.childNodes[0].checked"  class=bt><input type="checkbox" onclick="this.checked = !this.checked" id=loop>반복</a>
						</td>
						<td><span id=time_sync>00:00</span><!--/td><td-->/<span id=time_all>00:00</span></td>
					</tr>
					<tr>
						<td>
							<input id=sink oninput='audio.currentTime=this.value*audio.duration/100' type='range' min=0 max=100 step=.1 value=0 width='300px'>
						</td><td>
							<input id=volume oninput='audio.volume=this.value' type='range' min=0 max=1 step=.001 value=.5 width='160px'>
						</td>
					</tr>
					<tr>
						<td colSpan=2>
							<div id="subtitle"></div>
						</td>
					</tr>
					<tr>
						<td colSpan=2>
							<a onclick="window.location.replace('?get=<?=$_GET[window]?>')" class=bt>
								<img src='<?=getIMG("down")?>' title="이미지 다운로드">다운로드
							</a>
						</td>
					</tr>
				</table>
				<script>
var audio = new Audio(),isPlaying=false;
function humanReadable(seconds){
  var pad=function(x){return(x<10)?"0"+x:x}
  return pad(parseInt(seconds/60%60))+":"+pad(seconds%60)
}
				</script>
				<?
			break;
		case "video":?>
			<video id=player <?if($is){?>onended="parent.next_data()"<?}else{?>loop<?}?> controls autoplay width="<?=$width_max?>" height="auto"></video><br>
			<div id="subtitle"></div>
		<script>
		var isPlaying=false;
		
		var c_window = window;
		function outpage(){
			parent.pageOut=0;
			parent_table.style.display="block";
			parent_page.style.width="1px";
			parent_page.style.height="1px";
			changePage("about:blank");//공백화면
			parent.changeFrame = 0;
			parent.setPageOut(0);
		}
		
		function changePage(i){
			c_window.location.replace(i);
		}
		
		function copyLink(){
			var tmp = document.createElement("textarea");
			tmp.innerHTML = encodeURI("http://hostdir/<?=$host_dir?>?view=<?
			echo ($is?h($_GET[window]):h($_GET[view]));
			if(isset($_GET[smi]))
				echo '&smi='.h($_GET[smi]);
			?>");//+" - 우리의 VRC";//파일 링크
			document.body.appendChild(tmp);
			tmp.select();
			document.execCommand('copy');
			document.body.removeChild(tmp);
			
			alert("복사됨");
		}
		
		function humanReadable(seconds){
		  var pad=function(x){return(x<10)?"0"+x:x}
		  return pad(parseInt(seconds/60%60))+":"+pad(seconds%60)
		}
	</script><?
			break;
		case "text":
			?>
			<h2><?=$file[file]?>의 내용</h2>
			<textarea><?readfile($dir . $window)?></textarea>
			<?
		break;
	}//switch
	?><script>
	
	<?if($is){?>
	//공용 스크립트
	var c_window = window;
	function outpage(){
		parent.pageOut=0;
		parent_table.style.display="block";
		parent_page.style.width="1px";
		parent_page.style.height="1px";
		changePage("about:blank");//공백화면
		parent.changeFrame = 0;
		parent.setPageOut(0);
	}
	
	function changePage(i){
		c_window.location.replace(i);
	}
	
	function copyLink(){
		var tmp = document.createElement("textarea");
		tmp.innerHTML = encodeURI("http://hostdir/<?=$host_dir?>?view=<?
		echo ($is?h($_GET[window]):h($_GET[view]));
		if(isset($_GET[smi]))
			echo '&smi='.h($_GET[smi]);
		?>");//+" - 우리의 VRC";//파일 링크
		document.body.appendChild(tmp);
		tmp.select();
		document.execCommand('copy');
		document.body.removeChild(tmp);
		alert("복사됨");
		call("./?log=<?=$is?h($_GET[window]):h($_GET[view])?>");
	}<?}?>
	
	window.onload=function(){
		_subTitle = 0;
		call("http://hostdir/lib/log_JS.php?f=<?=($is?h($_GET[window]):h($_GET[view]))?>");
		<?if($type === "audio"){?>//오디오 파일 여부
			audio.src = "./?media=<?=$window?>";//데이터 링크
			audio.autoplay = true;
			isPlaying = true;
			document.getElementById("play").onclick=function(){
				this.src="http://hostdir/img/icons/"+(isPlaying?"play_1.jpg":"stop_1.png");//http://hostdir/img/icons/stop_1.png
				(isPlaying?audio.pause():audio.play());
				isPlaying=!isPlaying;
			}
			audio.volume=.5;
			audio.oncanplay = function(){document.getElementById("time_all").innerHTML = humanReadable(Math.floor(this.duration));}
			audio.onchange = function(){this.currentTime=this.value*this.duration/100}
			audio.onended = function(){
				if(!document.getElementById("loop").checked && typeof(opener) != "undefined" && parent.next_data())
					return;
				this.currentTime = 0;
				this.play();
			}
			audio.ontimeupdate = function(){
				document.getElementById("time_sync").innerHTML=humanReadable(Math.floor(this.currentTime));
				document.getElementById("sink").value=this.currentTime*100/this.duration;
				//자막 파일 
				if(!_subTitle)return;
				var time = Math.floor(this.currentTime*1000),a = Object.keys(_subTitle.data("sub"));
				for(var i = 1; i< a.length; i++){
					if(a[i-1]<time && time<=a[i]){
						if(i==_subTitle.data("g").indexOf()+1)return;
						_subTitle.data("g").css("display","none");//이전 요소를 가림
						_subTitle.childNodes[i-1].css("display","block");
						_subTitle.data("g",_subTitle.childNodes[i-1]);//현재 요소 교체
						return;
					}
				}
				_subTitle.data("g").css("display","none");//이전 요소를 가림
				_subTitle.childNodes[_subTitle.childNodes.length-1].css("display","block");
				_subTitle.data("g",_subTitle.childNodes[_subTitle.childNodes.length-1]);//현재 요소 교체
			}
		<?}else{?>
			document.getElementById("player").src="./?media=<?=$window?>";//페이지 로드 후 데이터 로딩
		<?}
		
		if(isset($_GET[smi])){
			?>
			getJ("?smi=<?=h($_GET[smi]);?>",function(a){
				if(!a)return;
				var e=Object.keys(a),begin;//자막 데이터가 들어갈 위
				_subTitle = document.getElementById("subtitle").data("sub",a).clear();
				for(var i=0;i<e.length-1;i++){
					var g=_subTitle.createElement("div").data("time",e[i]).css("textAlign","center");//엘리먼트 생성
					g.innerHTML=a[e[i]];
					//g.onclick=function(){audio.currentTime=e[this.indexOf()]/1000;};
					g.css("display",(!i?"block":"none"));
					if(begin)
						begin.data("next",g);//다음 엘리먼트 저장
					else _subTitle.data("g",g).data("time",e[i]);
					begin = g;
				}
			});
			<?
		}
		if($is){?>
		if(typeof(opener) != "undefined") {//부모요소 확인
			parent.setPageOut(outpage);
			parent_table = parent.document.getElementById("content");
			parent_page = parent.document.getElementById("page");
			parent.changeFrame = changePage;
		}else {
			console.log("페어런트 놉");
			history.back();//비정상 접근으로 현재창을 종료함
		}<?}?>
	}
	</script><?
}else if(isset($_GET[media])){//미디어는 스트리밍 이기 때문에 잠시
	$dir.=h($_GET[media]);//디렉토리 경로
	if($_GET['debug']){
		echo $dir;
		if(is_file($dir))echo '존재함';else echo '존재하지 않음';
		exit;
	}
	set_time_limit(0);
	ob_clean();
	//@ini_set('error_reporting', E_ALL & ~ E_NOTICE);
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 'Off');
	if(!is_file($dir))
		die('No sourch file...'.$dir);
	$size = filesize($dir);
	header('Content-type: application/octet-stream');	
	header('HTTP/1.1 206 Partial Content');
	header('Accept-Ranges: bytes');
	header('Content-Length: ' . $size);
	if(isset($_SERVER['HTTP_RANGE'])){
		$ranges = array_map('intval',explode('-',substr($_SERVER['HTTP_RANGE'], 6)));
		if(!$ranges[1])$ranges[1] = $size - 1;
	}else {
		$ranges = array(0,$size - 1);
	}
	header(
		sprintf(
			'Content-Range: bytes %d-%d/%d',
			$ranges[0], // The start range
			$ranges[1], // The end range
			$size // Total size of the file
		)
	);
	$f = fopen($dir, 'rb');
	$chunkSize = 8192;
	fseek($f, $ranges[0]);
	while(true){
		if(ftell($f) >= $ranges[1])break;
		echo fread($f, $chunkSize);
		@ob_flush();
		flush();
	}
	exit;
}else if(isset($_GET[file])){//스트림으로 뷰어함
	ob_clean();//출력 버퍼 클린
	flush();//시스템 버퍼 플러시(?)
	$dir.=h($_GET[file]);//file 인자에 해당하는 위치를 가져옵니다.
	$file=getFileProperty($dir);//파일 정보를 가져옵니다.
	if($file[ext]===false||in_array($file[ext],$view_ext)){
		die("폴더/금지파일를(을) 엑세스 하였습니다.");
		exit;
	}
	header("Pragma: private");
	header("Expires: 0");
	header("Content-Type: $file[type]");
	header("Content-Disposition: attachment; filename=\"$file[name]\"");
	header("Content-Transfer-Encoding: binary");
	//파일에 헤더를 전송합니다.
	if(in_array($file[ext],array('jpg','jpeg','png','gif'))){
		$tmp .= $file[name];
		if(!is_file($tmp))compress($dir,$tmp,50);
		$dir = $tmp;
	}
	header("Content-Length: ". filesize($dir));
	readfile($dir);//파일을 읽어 버퍼에 출력합니다.
	ob_flush();//버퍼를 플러시해줍니다
	exit;//공용스크립트가 읽히지 않도록 해 줍니다.
}else if(isset($_GET[get])){//무조건 파일 다운로드
	$dir.=h($_GET[get]);
	$file=getFileProperty($dir);
	header("Pragma: public");
	header("Expires: 0");
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=$file[name]");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: ".filesize($dir));
	ob_clean();
	flush();
	readfile($dir);
	exit;
}else if(isset($_GET[smi])){//smi 파일 json처리해서 내놔
	$dir.=h($_GET[smi]);
	$file=getFileProperty($dir);
	if($file[ext]===false||getTypeExtN($file[ext])!=="subtitle"){
		die("폴더/금지파일를(을) 엑세스" . $file[ext]);
		exit;
	}
	if($file[ext]==="smi"){
		header("Contnet-Type: application/json; charset=UTF-8");
		header("X-Contnet-Type-Options: mosniff");
		echo getSMI($dir);
	}else{
		header("Content-Type: application/octet-stream");
		header("X-Contnet-Type-Options: mosniff");
		readfile($dir);
	}
	exit;
}else{//첫화면(리스트)
	?><!DOCTYPE html>
<title>트위치 클립!</title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<meta name="robots" content="noarchive, nofollow, nosnippet" />
<meta content='IE=edge,chrome=1' http-equiv='X-UA-Compatible' />
<meta name="viewport" content="width=device-width, user-scalable=no">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="expires" content="0">
<link href="http://hostdir/<?=$host_dir?>vrchat.png" rel="shortcut icon">
<link href="http://hostdir/<?=$host_dir?>vrchat.png" sizes="152x152" rel="apple-touch-icon">

<!--nav></nav-->
<div id=title></div>
<table id=content>
</table>
<iframe id=page></iframe>
<img src="../lib/log_JS.php?f=VRC" style="width:1px;height:1px"/>

<script>
/*
히스토리

히스토리는 페이지가 아웃 될때에 저장됨.
*/
//(function(){$.get('http://hostdir/lib/log_JS.php',{f:_do.URL})})();
function getRoot(){
	var out="",l=stack.all();
	for(var a=0;a<l.length;a++)out+=l[a]+"/";
	return out;
}
function getCmpPos(obj){
	var rect=obj.getBoundingClientRect(),height;
	if(rect.height)
		height=rect.height;
	else height=rect.bottom-rect.top;
	return [rect.left,rect.top+height+10];
}
function dirs(arr){
	var a=this.getElementById("content"),b=this.createElement("tbody");
	if(!arr)return;//오류 방지 코드
	/*
	arr.sort(function(a,b){
		if(!a.ext)return 1;
		var x = a.name.toLowerCase(),y = b.name.toLowerCase();
		//console.log(x + "/" + y);
		//return x.localeCompare(y);
		return x>y;
	});*/
	a.clear();//리스트 출력할 데이터 비우기
	function addNode(element){
		var c=b.createElement("tr"),d,t,f;
		d=c.createElement("td");
		if(!element.ext&&element.ext!=null)
			d.createElement("img").src=getIMG("folder");
		else d.createElement("img").src=getIMG(getTYPE(element.ext));
		d.style.width = "20px";
		d=c.createElement("td");
		d.className="list-text-element bt";
		d.innerHTML=element.name;
		f = d;
		<?if(!$chkMobile){?>
		if(["img"].indexOf(getTYPE(element.ext))+1){//마우스 드롭 이벤트
			//이미지 드롭 이벤트를 기입해줍니다.
			d.data=element;//드롭이벤트시, 내 항목이 뭔지 알아야 하기 때문에, 저장한다.
			d.addEventListener("mouseover",function(){
				var d=this.data;//데이터 가져옴
				switch(getTYPE(d.ext)){//여러개 처리할려고 했기에..
					case "img"://항목이 이미지일경우 처리
						var loc=getCmpPos(this);//항목의 위치를 가져온다.
						var left=loc[0]+"px",top=loc[1]+"px";//좌표계산
						var div=document.body.createElement("popup"),img=div.createElement("img");//팝업항목과, 이미지 항목을 같이 생성.
						img.src="?file="+getRoot()+d.file;//이미지 지정(getRoot[파일 경로])
						img.style.width="300px";//지정크기
						img.style.height="auto";//세로는 마음대로
						div.id="popup";//아이디
						div.setAttribute("style","left:"+left+";top:"+top+";position:fixed");//위치 지정//디스플레이 고정
						break;
					default:break;
				}			
			},false);
			d.addEventListener("mouseout",function(){
				var d=this.data;//데이터 가져옴
				switch(getTYPE(d.ext)){
					case "img"://이미지 일 경우
						var popup=document.getElementById("popup");//팝업노드를 찾아서
						while(popup){
							popup.parentNode.removeChild(popup);//제거
							popup=document.getElementById("popup");//팝업노드를 찾아서
						}
						return false;
					default://기타처리
						break;
				}
			},false);
		}<?}?>
		d.onclick=function(){
			index = this.parentNode.indexOf();
			if(!listarr[index].ext&&listarr[index].ext!=null){//디렉토리
				stack.push(listarr[index].file);
				title.textContent=getRoot();
				b.clear();
				var tmp = b.createElement("tr"),tmp1=tmp.createElement("td");
				tmp1.innerHTML="불러오는중...";
				getJ("?dir="+getRoot(),dirs);
			}else{//파일
				switch(getTYPE(listarr[index].ext)){
					case "music":case "video":case "img":case "text":
						page.data("index",index);//index기록
						page.data("index_max",listarr.length);//index기록
						//location.href="?window="+getRoot()+listarr[index].file;
						page.style.width="<?=$width_max?>";
						page.style.height="96%";
						page.src="?window="+getRoot()+listarr[index].file + 
							(listarr[index].smi?("&smi="+getRoot()+listarr[index].name+"."+listarr[index].smi):"");//여기다가 추가
						history.pushState(null, document.title, "http://hostdir/<?=$host_dir?>");//뒤로가기 방지
						content.style.display="none";
						break;
					case "file":
					
						break;
					case "up": upState();
						break;
				}
			}
		}
		
		var z = 1;
		if(element.smi){
			d = c.createElement("td");
			d.innerHTML = "자막파일:"+element.smi;
			d.css({
				fontSize:"small",
				textAlign:"center",
				width:"100"
			});
			z--;
		}
		if(element.lasttime&&element.ext&&element.ext!=null){
			d = c.createElement("td");
			d.innerHTML=element.lasttime;
			d.style.fontSize = "small";
			d.style.textAlign = "right";
		}else f.colSpan = 2+z;
	}
	
	for(var e=arr.length-1;e>=0;e--){//역탐색
		var obj=arr[e];
		if(obj.name=="."||obj.name==".."||obj.ext=="php")
			arr.splice(e,1);
	}
	listarr=arr;
	listarr.sort(function(a,b){
		var ext=getTYPE(a.ext);
		if(!ext||ext==="folder")return -1;
		function low(a,b){
			if(!a||!b)return !b;
			return a.localeCompare(b);
		}
		if(a.ext===b.ext)
			return low(a.name,b.name);
		return low(a.ext,b.ext);
	});
	if(stack.length())listarr.unshift({dir:".",name:"상위",root:"상위",ext:"up"});
	for(var e=0;e<arr.length;e++){
		addNode(arr[e]);
	}
		/*if(arr[e].ext!="sti"||arr[e].ext!="smi")*/
	a.appendChild(b);
}

function upState(){
	stack.pop();
	title.textContent=getRoot();
	getJ("?dir="+getRoot(),dirs);
}

function next_data(i){//인덱스 데이터 
	var index = page.data("index")*1;//인덱스 불러오기
	do{//디렉토리 필터링
		index += (i?-1:1);
		if(index<0)index=listarr.length-1;
		if(index>=listarr.length)index = 0;
		if(index==(page.data("index")*1)){
			console.log("데이터가 존재하지 않음");
			return false;//돌고돌아 원위치 일 경우
		}
	}while(listarr[index].ext!==listarr[page.data("index")*1].ext||!listarr[index].ext&&listarr[index].ext!=null);//폴더이거나 확장자가 다른경우
	
	switch(getTYPE(listarr[index].ext)){
		case "music":case "video":case "img":case "text":
			page.data("index",index);//index기록
			page.data("index_max",listarr.length);//index기록
			//location.href="?window="+getRoot()+listarr[index].file;
			//page.changePage("?window="+getRoot()+listarr[index].file);//쥐도새도 모르게 변rd
            var link ="?window="+getRoot()+listarr[index].file + 
			(listarr[index].smi?("&smi="+getRoot()+listarr[index].name+"."+listarr[index].smi):"");
			typeof changeFrame=='function'?changeFrame(link):page.src=link;
			//history.pushState(null, document.title, "http://hostdir/<?=$host_dir?>");//뒤로가기 방지
			content.style.display="none";
			break;
		case "up": upState();
			break;
	}
	return true;
}

window.onload=function(){
	stack=new Stack();
	page_index = window.history.length;
	page=document.getElementById("page");
	content=document.getElementById("content");
	title=document.getElementById("title");
	getJ("?dir",dirs);
	//history.pushState(null, document.title, "");
	history.pushState(null, document.title,location.href);
	pageOut=0;
}


window.addEventListener('popstate', function(event) {//뒤로가기 버튼
	if(content.style.display === 'block'){
		if(stack.length()){//리스트가 있을경우
			history.pushState(null, document.title,"?"+Math.floor(Math.random() * 10) + 1);
			upState();
		}
	}else if(pageOut){
		//console.log(page_index + "/" + window.history.length);
		history.go(page_index);
		typeof pageOut=='function'&&pageOut();//함수 실행
	}
	
});
function setPageOut(func){
	pageOut = func;
}
</script>
	<?
}
?>

<style>
* {
	margin:0;padding:0;
	font-family:맑은 고딕;
	white-space:nowrap;
	text-decoration:none;
	display:auto;
	cursor:default;
}
html, body{
	height:100%;
	width:<?=$width_max ?>;
}
.bt {
	cursor: pointer;
    height: 30px;
}
#content tbody{
	width:100%;
}
<?if($chkMobile){?>
*{
	font-size:1em;
}
#page{
	width:1;
	height:1;
	padding:0;
	margin:0;
	border: 0;
}
#content{
	width:100%;
}
tr:nth-child(2n-1) td{
	background:transparent;
	background-color:#F7F7F7;
}
tr:nth-child(2n) td{
	background:transparent;
	background-color:#FFFFFF;
}
.popup{
	position:fixed;
}
<?}else{?>
#page{
	width:1;
	height:1;
	padding:0;
	margin:0;
	border: 0;
}
#content{
	width:500px;
}
tr{
	width:400px;
}
tr:nth-child(2n-1) td{
	background:transparent;
	background-color:#F7F7F7;
}
tr:nth-child(2n) td{
	background:transparent;
	background-color:#FFFFFF;
}
.popup{
	position:fixed;
}
<?}?> 
</style>
<script>
//스텍
function Stack(){
	this.data = [];
	this.top = 0;
	this.push=function(element){this.data[this.top++]=element;}
	this.pop=function(){if(this.top)return this.data[--this.top];else return 0;}
	this.peek=function(){return this.data[this.top-1];}
	this.length=function(){return this.top;}
	this.clear=function(){this.top = 0;this.data.length=0;}
	this.all=function(a,l){l=[];for(a=0;a<this.top;a++)l.push(this.data[a]);return l;}
}

function libElement(ele){if(ele[lib_n])return;ele[lib_n]={event:{}}}//라이브러리 불러오기
Element.prototype.css=function(css){if(typeof css=="string")this.style[arguments[0]]=arguments[1];else for(var k in css)this.style[k]=css[k];return this;};
Element.prototype.siblings = function(){var c=this.parentNode.childNodes,i=this.indexOf(),l=[],j=0;for(;j<c.length;j++)if(i!=j)l.push(c[i]);return l;};
Element.prototype.clear=function(){while(this.firstChild)this.removeChild(this.firstChild);return this;}
//Element.prototype.clear=function(){this.innerHTML="";return this;};
Element.prototype.remove=function(){this.parentNode.removeChild(this);};
Element.prototype.getPosition=function(){return this.getBoundingClientRect()};
Element.prototype.indexOf=function(){var c=this.parentNode.childNodes,i=0;for(;i<c.length;i++)if(c[i]==this)return i;return -1;};
Element.prototype.data=function(){
	var a=arguments,b=a.length,c=(typeof a[1])=="string";
	if(!this._data)this._data = {};
	if(!(b-1))
		return this._data[a[0]];
	else {
		this._data[a[0]] = a[1];
		if(c)this.setAttribute("data-"+a[0],a[1]);
	}
	return this;
};
Element.prototype._data = {};
Element.prototype.indexOf=function(){
	var c=this.parentNode.childNodes,i=0;
	for(;i<c.length;i++)if(c[i]==this)return i;
	return -1;
};
Element.prototype.createElement=Element.prototype.C=function(ele){
	var e = document.createElement(ele);
	this.appendChild(e);
	return e;
}
Element.prototype.on=window.on=function(){//이벤트명/실행함수//이후에는 넘기는 인자
	var a=arguments,b=a.length,c,d=this,e=[],f=0;
	if(b<2)return d;//적용x
	libElement(d);
	c=d[lib_n].event[a[0]];//이벤트 스텍
	if(!c)c=[];
	for(;f<b-2;f++)
		e.push(a[f+2]);
	c.push({f:a[1],args:e});
	if(d["on"+a[0]]==null)
		d["on"+a[0]]=function(j){
			e=d[lib_n].event[a[0]];
			for(f=0;f<e.length;f++)
				e[f].f.call(d,j,e[f].args);
		};
	d[lib_n].event[a[0]]=c;
	return d;
};

function get(){
	var xmlhttp = new XMLHttpRequest(),a=arguments,b=a.length;
	if(b<1)return;
	xmlhttp.onreadystatechange=function(){
		if(this.readyState==4&&this.status==200)typeof a[1]==="function"&&a[1].call,this.responseText,a[2];
	};
	xmlhttp.open("GET",a[0],true);
	xmlhttp.send();
}
function call(){
	var a=arguments,b=a.length;
	if(b<1)return;
	var v = document.body.C("img");
	v.src = a[0];
	document.body.removeChild(v);
}
function getJ(url,callback){
	var xmlhttp = new XMLHttpRequest(),a=arguments,b=a.length;
	if(b<1)return;
	xmlhttp.onreadystatechange=function(){
		//if(this.readyState==4&&this.status==200)typeof a[1]==="function"&&a[1].call,this.responseText,a[2]);
		try{
			callback.call(document,JSON.parse(this.responseText));
		}catch(e){
			//console.log(e);
			callback.call(document,false,this.responseText);
		}
	};
	xmlhttp.open("GET",a[0],true);
	xmlhttp.send();
}
function getIMG(type){
	switch(type){
		case "folder":	return "<?=getIMG("folder")?>";
		case "zip":		return "<?=getIMG("zip")?>";
		case "music":	return "<?=getIMG("music")?>";
		case "video":	return "<?=getIMG("video")?>";
		case "img":		return "<?=getIMG("img")?>";
		case "document":case "page":case "subtitle":case "text":return "<?=getIMG("document")?>";
		case "file":	return "<?=getIMG("file")?>";
		case "up":	return "<?=getIMG("up")?>";
	}
}

function getTYPE(type){
	switch(type){
		case "folder":case false:
			return "folder";
		case "zip":
			return "zip";
		case "up":
			return "up";
		case "mp3":case "flac":
			return "music";
		case "jpg":case "png":case "jpeg":case "bmp":case "ico":case "gif":
			return "img";
		case "mp4":case "mkv":case "avi":
			return "video";
		case "hwp":case "pdf":
			return "document";
		case "smi":
			//return "subtitle";
		case "txt": case "sti":
			return "text";
		case "html":case "htm":case "php":
			return "page";//열람가능 페이지
		case "cs":case "cpp":case "c":case "java":case "py":
		default:return "file";//열람불가
	}
}
</script>






