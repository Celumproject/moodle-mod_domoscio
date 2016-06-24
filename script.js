document.addEventListener('DOMContentLoaded', function() {
    var check = [];
    var children_notions = document.getElementsByClassName("children_notions");
    var parent_notion = document.getElementById("parent_notion");
    var add_notion = document.getElementById("addnotion");

    for (var i = 0, max = children_notions.length; i < max; i++) {
        if (children_notions[i].hasAttribute("checked")) {
            check.push(children_notions[i].getAttribute("id"));
        }
    }

    parent_notion.addEventListener("click", function(){
        disableNotions(this);
    });

    function disableNotions(target){
        if (target.checked)
        {
            for (var i = 0, max = children_notions.length; i < max; i++) {
                children_notions[i].setAttribute("disabled", "disabled")
                children_notions[i].removeAttribute("checked");
                add_notion.setAttribute("disabled", "disabled");
            }
        } else {
            for (var i = 0, max = children_notions.length; i < max; i++) {
                children_notions[i].removeAttribute("disabled");
                if (check.indexOf(children_notions[i].getAttribute('id')) !== -1) {
                    children_notions[i].setAttribute("checked", "checked");
                }
            }

            add_notion.removeAttribute("disabled");
        }
    }
});



function send_result() {
    var scoreHtml = document.getElementById('scoretag');
    var frame = document.getElementById('iframe');
    var innerFrame = iframe.contentDocument || iframe.contentWindow.document;
    var scoreElem = innerFrame.getElementsByClassName('pquizScore');
    var scoreTxt = scoreElem[0].textContent || scoreElem[0].innerText;
    var score = scoreTxt.replace(" / 10", "");
    if (score == " ") {
        scoreHtml.value = "NAN";
    } else {
        scoreHtml.value = score;
    }
}
