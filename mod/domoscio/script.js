$(document).ready(function()
{
    var check = [];

    $(".children_notions").each(function(){

        if($(this).attr("checked"))
        {
            check.push($(this).attr('id'));
        }
    });

    $(".parent_notion").on("click", function(){
        disableNotions(this);
    });

    function disableNotions(target){
        if(target.checked)
        {
            $(".children_notions").prop("disabled", "disabled").removeAttr("checked");
            $("#addnotion").prop("disabled", "disabled");
        }
        else
        {
            $(".children_notions").removeAttr("disabled");
            $(".children_notions").each(function(){

                if($.inArray($(this).attr('id'), check) !== -1)
                {
                    $(this).prop("checked", "checked");
                }
            });
            $("#addnotion").removeAttr("disabled");
        }
    }

});
