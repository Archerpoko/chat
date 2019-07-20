var chat = {
  chatid: null,
  updateChatId: function(){
    return this.chatid = $('.choosen').attr('data-chatid');
  },
  getMessages:function() {
    $.ajax({
      url:"/messages",
      data: {chatid: chat.chatid},
      method:'GET'
    }).done(function(msg){
        if(msg.history[0]){
          chat.displayMessages(msg);
        }
    })
  },
  displayMessages:function(messages){
    $('#messages').html('');
    for(message in messages.history){
      if(messages["history"][message].from == messages.userid){
        $('#messages').append(`<div class="myMysg">${messages["history"][message].message}</div>`);
      }else{
        $('#messages').append(`<div class="friendMsg boxShadow">${messages["history"][message].message}</div>`);
      }
    }
    $(`div[data-chatid=${chat.chatid}] .nickAndMessageContainer .lastMsg`).html(messages.history[messages.history.length-1].message)
  },
  sendMessage:function(){
    let msg = $('#sendMessageInput').val();
    $('#sendMessageInput').val('');
    $.ajax({
      url:"/send",
      data: {chatid:chat.chatid,message:msg},
      method:"POST"
    }).done(function(){
        chat.getMessages();
    })
  },
  changeChat:function(e){
    e.preventDefault();
    let target = e.target;
    while(!$(target).hasClass('friendOnList')){
      target = $(target).parent();
    }
    let chatid = $(target).attr('data-chatid');
    window.location.href = "http://"+window.location.hostname+"/chat/"+chatid;
  }
}
$(function() {
  chat.updateChatId();
  $('#sendMessageButton').click(()=>{chat.sendMessage()});
  $('.friendOnList').click((e)=>{chat.changeChat(e)})
  setInterval(function() {
    chat.getMessages();
  },1000)
  $('#sendMessageInput').keydown(e=>{
    if(e.keyCode==13)chat.sendMessage();
  })
})
