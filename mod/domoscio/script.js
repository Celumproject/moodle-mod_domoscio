$(document).ready(function()
{
    $(".parent_notion").on("click", function()
    {
        disableNotions(this);
    });

    function disableNotions(target)
    {
        if(target.checked)
        {
            $(".children_notions").attr("disabled", "disabled");
        }
        else
        {
            $(".children_notions").removeAttr("disabled");
        }
    }

});
