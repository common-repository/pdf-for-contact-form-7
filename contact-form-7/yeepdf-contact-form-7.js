(function($) {
    "use strict";
    $( document ).ready( function () {
        $("body").on("change",".yeepdf_data_enable",function(e){
            var tab = $(this).data("tab");
            if($(this).is(":checked")){
                $(tab).removeClass("hidden");
            }else{
                $(tab).addClass("hidden");
            }
        })
    })
})(jQuery);