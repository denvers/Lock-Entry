$(function(){

    var intervalID = setInterval(function(){
        $.get(lock_entry_ping_url);
    }, 10000); // send a keep-alive ping every 10 seconds

});