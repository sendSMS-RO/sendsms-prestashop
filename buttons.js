$(document).ready(function() {
    var ps_sendsms_buttons = document.getElementsByClassName('ps_sendsms_button');
    for (var i = 0; i < ps_sendsms_buttons.length; i++) {
        var ps_sendsms_button = ps_sendsms_buttons[i];
        ps_sendsms_button.onclick = function () {
            this.parentElement.nextElementSibling.getElementsByTagName('textarea')[0].value += this.innerHTML;
        };
    }
});
