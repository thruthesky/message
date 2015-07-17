$ = jQuery;

$(function(){
	$("body").on("click","section.message .table-list .td.checkbox .checkbox-wrapper >input". callback_checkbox_checked );

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
});

function callback_checkbox_checked(){
	
}