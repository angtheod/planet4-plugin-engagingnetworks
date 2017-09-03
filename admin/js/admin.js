jQuery(document).ready(function() {

    $('.notice.is-dismissible').animate({"margin-left" : '+=20', "opacity" : '+=0.9'}, 800);
    $('.p4en_message').animate({"opacity" : '+=0.9'}, 800);
    
    setTimeout(function() {
        $('.notice.is-dismissible, .p4en_message').fadeOut(2000, function () {
            $(this).remove();
        });
    }, 3800);

    $('.do_copy').off('click').on('click', function(e){
        e.preventDefault();

        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val( $(this).attr('data-href') ).select();
        document.execCommand("copy");
        $temp.remove();
    });

    $('[name="p4en_pages_posts_sync_button"]').off('click').on('click', function ( event ) {
        event.preventDefault();

        // TODO - Ajax call
    });
} );