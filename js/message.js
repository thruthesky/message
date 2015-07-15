$ = jQuery;

$(function(){
	//temporary
	if( $(".message .view .message-commands > a.delete").length ){
		$(".message .view .message-commands > a.delete").prop("href", $(".message .view .delete.view").prop("href"));
	}
	//$("body").on("click",".message .view .message-commands > a.delete", callback_delete_message);
});

function callback_delete_message(){
	//var $this = $(this);
	//alert( "a" );
	//alert( $(".message .view .delete.view").prop("href") );
	
}