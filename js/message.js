$ = jQuery;
var post_edit_form_submit = true;
$(function(){
	$('.message-commands input[name="toggle"]').click(function(){
        $(".ids").each(function(i){
            $(this).prop("checked", !$(this).prop("checked"));
        });
    });
    $('.multi-delete').click(function(){
        var re = confirm('Are you sure you want to delete selected messages?');
        if ( re ) $("form[name='list']").submit();
        else return false;
    });
	
	$form_message = $("form.message-send");
	
	$(".message-send input[type='submit']").click(function(){
        post_edit_form_submit = true;
        $form_message.submit();
    });
	
    $('body').on('click', ".message-send input[type='file']", function(){
        post_edit_form_submit = false;		
    });
	
	 $form_message.submit(function(){
		var $this = $(this);
		
		if ( post_edit_form_submit ) {
			$this.prop('action', '');
		}
		else{			
			$this.prop('action', '/message/api?call=fileUpload');
			
			$(".send .message-send .buttons > span.file-upload .text").toggle();
			$(".send .message-send .buttons > span.file-upload .loader").toggle();
			$(".send .message-send .buttons > span.file-upload input[type='file']").toggle();
			
			message_ajax_file_upload( $this, callback_message_ajax_file_upload );
			return false;
		}
	 });
	//$("body").on("change", ".send-upload-files, callback_message_send_file_upload );
	
	$('body').on('click', ".send .message-send .message-uploaded-files .item .delete", callback_message_delete_file);
	
	$('body').on('click', ".message-uploaded-files .item.photo", callback_show_image);
	$('body').on('click', ".message-modal-window", callback_remove_modal_window);
	
	$(window).resize( callback_window_resize );	
});

function callback_message_send_file_upload(){	
	message_ajax_file_upload( $(this), callback_message_ajax_file_upload);
}

function message_ajax_file_upload($form, callback_message_function)
{	
    var $upload_progress = $(".ajax-file-upload-progress-bar");
    $form.ajaxSubmit({
        beforeSend: function() {
            //console.log("seforeSend:");
            $upload_progress.show();
            var percentVal = '0%';
            $upload_progress.find('.percent').width(percentVal);
            $upload_progress.find('.caption').html('Upload: 0%');
        },
        uploadProgress: function(event, position, total, percentComplete) {
            //console.log("while uploadProgress:" + percentComplete + '%');
            var percentVal = percentComplete + '%';
            $upload_progress.find('.percent').width(percentVal);
            $upload_progress.find('.caption').html('Upload: ' + percentVal);
        },
        success: function() {
            //console.log("upload success:");
            var percentVal = '100%';
            $upload_progress.find('.percent').width(percentVal);
            $upload_progress.find('.caption').html('Upload: ' + percentVal);
        },
        complete: function(xhr) {
            //console.log("Upload completed!!");
            var re;
            try {
                re = JSON.parse(xhr.responseText);
            }
            catch ( e ) {
                return alert( xhr.responseText );
            }
            // console.log(re);
            callback_message_function( $form, re );
            setTimeout(function(){
                $upload_progress.hide();												
            }, 500);
            $.each($form.find("input[type='file']"), function(i, v){
                var name = $(this).prop('name');
                var markup = "<input type='file' name='" + name + "' multiple onchange='jQuery(this).parent().submit();'>";
                $(this).replaceWith(markup);
            });
        }
    });
}


function callback_message_ajax_file_upload($form, re)
{
    //console.log("callback_ajax_file_upload() begin");
    var data;
    try {
        data = JSON.parse(re);
    }
    catch (e) {
        alert(re);
        return;
    }
    //console.log(data['files']);
    var i;
    for( i in data['files'] ) {
        var file = data['files'][i];
        console.log(file['fid']);
        var val = $form.find('[name="fid"]').val();
        val += ',' + file['fid'];
        $form.find('[name="fid"]').val( val );
    }
    if ( typeof callback_message_file_upload_complete == 'function' ) callback_message_file_upload_complete($form, data['files']);
}

function callback_message_file_upload_complete( $form, $files ){
	$(".send .message-send .buttons > span.file-upload .text").toggle();
	$(".send .message-send .buttons > span.file-upload .loader").toggle();
	$(".send .message-send .buttons > span.file-upload input[type='file']").toggle();


	var $files_html = "";
	var $images_html = "";
	for( var i in $files ){
		//console.log( $files[i]['type'].indexOf("image") );
		if( $files[i]['type'].indexOf("image") == -1 ){
			$files_html += "<div class='item file' fid='" + $files[i]['fid'] + "'><span class='delete'>x</span><img src='/modules/message/img/file.png'/><span class='file-name'>" + $files[i]['name'] + "</span></div>";
		}
		else{
			$images_html += "<div class='item photo' fid='" + $files[i]['fid'] + "'><span class='delete'>x</span><img src='" + $files[i]['thumbnails']['url_medium'] + "'/></div>";			
		}		
	}
	var $html = $images_html + $files_html;
	
	$(".send-wrapper .message-uploaded-files").append( $html );
}

function callback_message_delete_file(){
	var fid = $(this).parent().attr('fid');
	//console.log('fid:'+fid);
	var url = "/message/api?call=fileDelete&fid=" + fid;
	ajax_api( url, function(re) {
		if ( re.code == 0 ) {
			$(".send-wrapper .message-uploaded-files .item[fid='" + fid + "']").remove();
		}
	} );
}

function callback_show_image( e ){
	console.log( $('body').scrollTop() );
	if( $( e.target ).attr("class") == 'delete' ) return;

	// lock scroll position, but retain settings for later
      var scrollPosition = [
        self.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft,
        self.pageYOffset || document.documentElement.scrollTop  || document.body.scrollTop
      ];
      var html = jQuery('html'); // it would make more sense to apply this to body, but IE7 won't have that
      html.data('scroll-position', scrollPosition);
      html.data('previous-overflow', html.css('overflow'));
      html.css('overflow', 'hidden');
      window.scrollTo(scrollPosition[0], scrollPosition[1]);


      // un-lock scroll position
      var html = jQuery('html');
      var scrollPosition = html.data('scroll-position');
      html.css('overflow', html.data('previous-overflow'));
      window.scrollTo(scrollPosition[0], scrollPosition[1])
	
	
	
	$("body").append( getMessageModalWindow() );
	//$("body, html").css("height", $(window).height() );
	var fid = $(this).attr('fid');
	var url = "/message/api?call=getImage&fid=" + fid;
	
	ajax_api( url, function(re) {
		if ( re.code == 0 ) {
			//console.log( re );
			html = "<img src='" + re.url.url_original + "'/>";
			$(".message-modal-window").append( html );
			$(".message-modal-window > img").load(function(){
				$(this).show();
				modalImageResize();
			});
			
		}
	} );
}

function getMessageModalWindow(){
	html =	"<div class='message-modal-window'>" +
			"</div>";
			
	return html;
}

function modalImageResize(){

	image_width = $(".message-modal-window > img").width();	
	image_height = $(".message-modal-window > img").height();	
	
	window_width = $(window).width();
	window_height = $(window).height();
	
	if( window_width > image_width && window_height > image_height ){
		//if( image_height > image_width ) $(".message-modal-window > img").css("");		
	}
	else{		
		if( image_height > window_height ){
			$(".message-modal-window > img").css("height","100%");
		}
		else if( image_width > window_width ){
			$(".message-modal-window > img").css("width","100%");
		}
	}
	margin_left = ( window_width - $(".message-modal-window > img").width() ) / 2;	
	if( margin_left > 0 ) $(".message-modal-window > img").css("margin-left", margin_left);
	else $(".message-modal-window > img").css("margin-left", 0);
	
	margin_top = ( window_height - $(".message-modal-window > img").height() ) / 2;
	if( margin_top > 0 ) $(".message-modal-window > img").css("margin-top", margin_top);
	else $(".message-modal-window > img").css("margin-top", 0);
}

function callback_remove_modal_window( e ){
	if( $( e.target ).hasClass( "message-modal-window" ) ) $(this).remove();
}



/*callback_window_resize*/
function callback_window_resize(){
	if( $(".message-modal-window").length ){
		modalImageResize();
	}
}
/*eo callback_window_resize*/