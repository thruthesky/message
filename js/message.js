$ = jQuery;

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
	
	
		
	$("body").on( "click","section.message .table-list .row", function( e ){
		/*
		*temp
		*/
		var $this = $(this);		
		if( $(e.target).attr("class") == 'ids' ) e.stopPropagation();
		if( $(e.target).attr("class") == 'checkbox-wrapper' ) e.preventDefault();
	} );
});