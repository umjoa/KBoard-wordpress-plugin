<?php
include_once 'KBFileHandler.class.php';

/**
 * KBoard 워드프레스 게시판 게시물
 * @link www.cosmosfarm.com
 * @copyright Copyright 2013 Cosmosfarm. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl.html
 */
class Content {
	
	var $next;
	
	var $option;
	var $attach;
	
	// 스킨에서 사용 할 첨부파일 input[type=file] 이름의 prefix를 정의한다.
	var $skin_attach_prefix = 'kboard_attach_';
	// 스킨에서 사용 할 사용자 정의 input[type=text] 이름의 prefix를 정의한다.
	var $skin_option_prefix = 'kboard_option_';
	
	var $attach_store_path;
	var $thumbnail_store_path;
	
	private $row;
	
	function __construct($board_id=''){
		if($board_id) $this->setBoardID($board_id);
		$this->next = $_POST['next'];
	}
	
	public function __get($name){
		return stripslashes($this->row->{$name});
	}
	
	public function __set($name, $value){
		$this->row->{$name} = $value;
	}
	
	function setBoardID($board_id){
		$this->board_id = $board_id;
		
		// 첨부파일 업로드 경로를 만든다.
		$upload_dir = wp_upload_dir();
		$this->attach_store_path = str_replace(KBOARD_WORDPRESS_ROOT, '', $upload_dir['basedir']) . "/kboard_attached/$board_id/" . date("Ym", current_time('timestamp')) . '/';
		$this->thumbnail_store_path = str_replace(KBOARD_WORDPRESS_ROOT, '', $upload_dir['basedir']) . "/kboard_thumbnails/$board_id/" . date("Ym", current_time('timestamp')) . '/';
	}
	
	function initWithUID($uid){
		if($uid){
			$this->row = mysql_fetch_object(mysql_query("SELECT * FROM kboard_board_content WHERE uid=$uid LIMIT 1"));
			$this->initOptions();
			$this->initAttachedFiles();
		}
		return $this;
	}
	
	function initWithRow($row){
		if($row){
			$this->row = $row;
			$this->initOptions();
			$this->initAttachedFiles();
		}
		return $this;
	}
	
	function execute(){
		$this->member_uid = $_POST['member_uid'];
		$this->member_display = $_POST['member_display'];
		$this->title = trim($_POST['title']);
		$this->content = trim($_POST['kboard_content']);
		$this->date = $_POST['date'];
		$this->view = $_POST['view'];
		$this->thumbnail_file = $_POST['thumbnail_file'];
		$this->thumbnail_name = $_POST['thumbnail_name'];
		$this->category1 = addslashes($_POST['category1']);
		$this->category2 = addslashes($_POST['category2']);
		$this->secret = $_POST['secret'];
		$this->notice = $_POST['notice'];
		$this->password = $_POST['password'];
		
		if($this->uid && $this->date){
			// 기존게시물 업데이트
			$this->_updateContent();
			$this->setThumbnail($this->uid);
			$this->update_options($this->uid);
			$this->update_attach($this->uid);
		}
		else if(!$this->uid && $this->title){
			// 신규게시물 등록
			$uid = $this->_insertContent();
			if($uid){
				$this->setThumbnail($uid);
				$this->update_options($uid);
				$this->update_attach($uid);
			}
		}
		
		if($this->next) die("<script>location.href='$this->next';</script>");
	}
	
	private function _insertContent(){
		global $user_ID;
		$userdata = get_userdata($user_ID);
		
		$data['board_id'] = $this->board_id;
		$data['member_uid'] = $userdata->data->ID?$userdata->data->ID:0;
		$data['member_display'] = $this->member_display?kboard_htmlclear($this->member_display):kboard_htmlclear($userdata->data->display_name);
		$data['title'] = addslashes(kboard_htmlclear($this->title));
		$data['content'] = addslashes(kboard_xssfilter($this->content));
		$data['date'] = date("YmdHis", current_time('timestamp'));
		$data['view'] = 0;
		$data['category1'] = $this->category1;
		$data['category2'] = $this->category2;
		$data['secret'] = $this->secret;
		$data['notice'] = $this->notice;
		$data['thumbnail_file'] = '';
		$data['thumbnail_name'] = '';
		if($this->password) $data['password'] = $this->password;
		
		foreach($data AS $key => $value){
			$value = addslashes($value);
			$insert_key[] = "`$key`";
			$insert_data[] = "'$value'";
		}
		
		$query = "INSERT INTO kboard_board_content (".implode(',', $insert_key).") VALUE (".implode(',', $insert_data).")";
		mysql_query($query);
		
		return mysql_insert_id();
	}
	
	private function _updateContent(){
		if($this->uid){
			$data['board_id'] = $this->board_id;
			$data['member_uid'] = $this->member_uid;
			$data['member_display'] = kboard_htmlclear($this->member_display);
			$data['title'] = addslashes(kboard_htmlclear($this->title));
			$data['content'] = addslashes(kboard_xssfilter($this->content));
			$data['date'] = $this->date;
			$data['category1'] = $this->category1;
			$data['category2'] = $this->category2;
			$data['secret'] = $this->secret;
			$data['notice'] = $this->notice;
			if($this->password) $data['password'] = $this->password;
			
			foreach($data AS $key => $value){
				$value = addslashes($value);
				$update[] = "`$key`='$value'";
			}
			
			mysql_query("UPDATE kboard_board_content SET ".implode(',', $update)." WHERE uid=$this->uid");
		}
	}
	
	/**
	 * 게시물의 조회수를 증가한다.
	 */
	function increaseView(){
		if($this->uid && !@in_array($this->uid, $_SESSION['increased_document_uid'])){
			$_SESSION['increased_document_uid'][] = $this->uid;
			mysql_query("UPDATE kboard_board_content SET view=view+1 WHERE uid=$this->uid");
		}
	}
	
	function initOptions(){
		if(!$this->uid) return '';
		$result = mysql_query("SELECT * FROM kboard_board_option WHERE content_uid=$this->uid");
		while($row = mysql_fetch_array($result)){
			$option[$row['option_key']] = stripslashes($row['option_value']);
		}
		$this->option = (object)$option;
		return $option;
	}
	
	function initAttachedFiles(){
		if(!$this->uid) return '';
		$result = mysql_query("SELECT * FROM kboard_board_attached WHERE content_uid=$this->uid");
		while($row = @mysql_fetch_array($result)){
			$file[$row['file_key']] = array($row['file_path'], $row['file_name']);
		}
		$this->attach = (object)$file;
		return $file;
	}
	
	function update_attach($uid){
		if(!$this->attach_store_path) die('업로드 경로가 없습니다. 게시판 ID를 입력하고 초기화 해주세요.');
		
		$file = new KBFileHandler();
		$file->setPath($this->attach_store_path);
		
		foreach($_FILES AS $key => $value){
			$key = str_replace($this->skin_attach_prefix, '', $key);
			
			$upload = $file->upload($this->skin_attach_prefix . $key);
			$original_name = $upload['original_name'];
			$file_path = $upload['path'] . $upload['stored_name'];
			
			if($original_name){
				$present_file = @reset(mysql_fetch_row(mysql_query("SELECT file_path FROM kboard_board_attached WHERE file_key LIKE '$key' AND content_uid=$uid")));
				if($present_file){
					unlink(KBOARD_WORDPRESS_ROOT . $present_file);
					$this->_update_attach($uid, $key, $file_path, $original_name);
				}
				else{
					$this->_insert_attach($uid, $key, $file_path, $original_name);
				}
			}
		}
	}
	
	private function _update_attach($uid, $key, $file_path, $file_name){
		$key = addslashes($key);
		$file_path = addslashes($file_path);
		$file_name = addslashes($file_name);
		mysql_query("UPDATE kboard_board_attached SET file_path='$file_path', file_name='$file_name' WHERE file_key LIKE '$key' AND content_uid=$uid");
	}
	
	private function _insert_attach($uid, $key, $file_path, $file_name){
		$date = date("YmdHis", current_time('timestamp'));
		$key = addslashes($key);
		$file_path = addslashes($file_path);
		$file_name = addslashes($file_name);
		mysql_query("INSERT INTO kboard_board_attached (content_uid, file_key, date, file_path, file_name) VALUE ($uid, '$key', '$date', '$file_path', '$file_name')");
	}
	
	private function _remove_all_attached($uid){
		$result = mysql_query("SELECT file_path FROM kboard_board_attached WHERE content_uid=$uid");
		while($file = mysql_fetch_row($result)){
			unlink(KBOARD_WORDPRESS_ROOT . stripslashes($file[0]));
		}
		mysql_query("DELETE FROM kboard_board_attached WHERE content_uid=$uid");
	}
	
	public function removeAttached($key){
		if($this->uid){
			$key = addslashes($key);
			$file = reset(mysql_fetch_row(mysql_query("SELECT file_path FROM kboard_board_attached WHERE file_key LIKE '$key' AND content_uid=$this->uid")));
			if($file){
				@unlink(KBOARD_WORDPRESS_ROOT . $file);
				$this->_update_attach($this->uid, $key, '', '');
			}
			mysql_query("DELETE FROM kboard_board_attached WHERE content_uid=$this->uid AND file_key LIKE '$key'");
		}
	}
	
	function update_options($uid){
		foreach($_REQUEST AS $key => $value){
			if(strstr($key, $this->skin_option_prefix) && trim($value)){
				
				$key = kboard_htmlclear(str_replace($this->skin_option_prefix, '', addslashes($key)));
				$value = kboard_xssfilter(trim(addslashes($value)));
				
				$present_value = @reset(mysql_fetch_row(mysql_query("SELECT option_value FROM kboard_board_option WHERE option_key LIKE '$key' AND content_uid=$uid")));
				if($present_value){
					$this->_update_option($uid, $key, $value);
				}
				else{
					$this->_insert_option($uid, $key, $value);
				}
			}
		}
		$this->_remove_empty_option();
	}
	
	private function _update_option($uid, $key, $value){
		mysql_query("UPDATE kboard_board_option SET option_value='$value' WHERE option_key LIKE '$key' AND content_uid=$uid");
	}
	
	private function _insert_option($uid, $key, $value){
		mysql_query("INSERT INTO kboard_board_option (content_uid, option_key, option_value) VALUE ($uid, '$key', '$value')");
	}
	
	private function _remove_empty_option(){
		mysql_query("DELETE FROM kboard_board_option WHERE option_value LIKE ''");
	}
	
	private function _remove_option($uid){
		mysql_query("DELETE FROM kboard_board_option WHERE content_uid=$uid");
	}
	
	function setThumbnail($uid){
		if(!$this->thumbnail_store_path) die('업로드 경로가 없습니다. 게시판 ID를 입력하고 초기화 해주세요.');
		
		$file = new KBFileHandler();
		$file->setPath($this->thumbnail_store_path);
		$upload = $file->upload('thumbnail');
		
		$original_name = $upload['original_name'];
		$file = $upload['path'] . $upload['stored_name'];
		
		if($upload['original_name']){
			$this->removeThumbnail($uid);
			mysql_query("UPDATE kboard_board_content SET thumbnail_file='$file', thumbnail_name='$original_name' WHERE uid=$uid");
		}
	}
	
	function removeThumbnail(){
		if($this->uid){
			$result = mysql_query("SELECT * FROM kboard_board_content WHERE uid=$this->uid LIMIT 1");
			$row = mysql_fetch_array($result);
			if($row['thumbnail_file']){
				@unlink(KBOARD_WORDPRESS_ROOT . $row['thumbnail_file']);
				mysql_query("UPDATE kboard_board_content SET thumbnail_file='', thumbnail_name='' WHERE uid=$this->uid");
			}
		}
	}
	
	function remove($next=''){
		if($this->uid){
			$this->_remove_option($this->uid);
			$this->_remove_all_attached($this->uid);
			$this->removeThumbnail();
			mysql_query("DELETE FROM kboard_board_content WHERE uid=$this->uid");
			if(defined('KBOARD_COMMNETS_VERSION')) mysql_query("DELETE FROM kboard_comments WHERE content_uid=$this->uid");
			if($next){
				echo "<script>location.href='$next';</script>";
				exit;
			}
		}
	}
	
	/**
	 * 본문에 인터넷 주소가 있을때 자동으로 링크를 생성한다.
	 */
	static function autolink($contents){
		// http://yongji.tistory.com/28
		$pattern = "/(http|https|ftp|mms):\/\/[0-9a-z-]+(\.[_0-9a-z-]+)+(:[0-9]{2,4})?\/?";// domain+port
		$pattern .= "([\.~_0-9a-z-]+\/?)*";                                                                                                                                                                                             // sub roots
		$pattern .= "(\S+\.[_0-9a-z]+)?"       ;                                                                                                                                                                                                    // file & extension string
		$pattern .= "(\?[_0-9a-z#%&=\-\+]+)*/i";                                                                                                                                                                               // parameters
		$replacement = "<a href=\"\\0\" target=\"window.opne(this.href); return false;\">\\0</a>";
		return preg_replace($pattern, $replacement, $contents, -1);
	}
}
?>