/**
 * @author http://www.cosmosfarm.com/
 */

var console = window.console || { log: function() {} };
jQuery.fn.exists = function(){
	return this.length>0;
}

function kboard_editor_execute(form){
	var $ = jQuery;
	
	if(!$('input[name=title]', form).val()){
		alert('제목을 입력하세요.');
		$('input[name=title]', form).focus();
		return false;
	}
	else if($('input[name=member_display]', form).eq(1).exists() && !$('input[name=member_display]', form).eq(1).val()){
		alert('작성자를 입력하세요.');
		$('[name=member_display]', form).eq(1).focus();
		return false;
	}
	else if($('input[name=password]', form).exists() && !$('input[name=password]', form).val()){
		alert('비밀번호를 입력하세요.');
		$('input[name=password]', form).focus();
		return false;
	}
	
	return true;
}