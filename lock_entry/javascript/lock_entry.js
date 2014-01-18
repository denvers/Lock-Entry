$(function () {
    if ($("#lock_entry_error").length > 0) return;

    // show lock message
    if (message_html != "")
    {
        $("<div id='lock_entry_error' style='padding: 10px; background: #E7174B; color: #fff;'>"+message_html+"</div>").insertBefore(".contents .heading");
    }

    if (hard_lock == true) {
        // remove submit button
        setTimeout(function () {
            $("#publish_submit_buttons input[type=submit]").hide();
        }, 100);

        setTimeout(function () {
            $("#publish_submit_buttons input[type=submit]").hide();
        }, 1000);

        // remove submit event from form
        $("#publishForm").submit(function () {
            return false;
        });

        // mark form fields readonly
        $("#publishForm input, #publishForm textarea, #publishForm select").attr('readonly', 'readonly');
    }
});