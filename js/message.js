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
});