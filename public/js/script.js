//Obsługa navbaru z linkami do profili społecznosciowych
$('#navSocialContainer i').click(function(e) {
    let link = $(e.target).attr('data-link');
    window.open('https://' + link);
});
//Obsługa zmiany wybranego formularza na stronie logowania
$('#topBox div').click(function(e) {
    if ($(e.target).attr('data-action') == "register") {
        $('.choosenBox').css('marginLeft', '50%');
        $("#contentLogin").css({
            'display': 'none',
            'opacity': 0
        });
        $("#contentRegister").css('display', 'flex');
        $("#contentRegister").fadeTo(300, 1);
    } else {
        $('.choosenBox').css('marginLeft', '0%');
        $("#contentRegister").css({
            'display': 'none',
            'opacity': 0
        });
        $("#contentLogin").css('display', 'flex');
        $("#contentLogin").fadeTo(300, 1);
    }
})
//Pokazywanie i ukrywanie hasła
$('.togglePw').click(function(e) {
    if ($(e.target).hasClass('fa-eye')) {
        $(e.target).removeClass('fa-eye');
        $(e.target).addClass('fa-eye-slash');
        let actionTarget = $(e.target).attr('data-target');
        $(`#${actionTarget}`).attr('type', 'text');
    } else {
        $(e.target).addClass('fa-eye');
        $(e.target).removeClass('fa-eye-slash');
        let actionTarget = $(e.target).attr('data-target');
        $(`#${actionTarget}`).attr('type', 'password');
    }
})
//Sprawdza czy nick/email nie istnieje już w bazie i zwraca odpowiedź JSON serwera do podanej funkcji jaki callback
let isUnique = (route,value,callback) => {
    $.ajax({
    url:`/isunique/${route}`,
    data: {value:value},
    method:"POST",
    success:callback
  })
}
//Czy login ma conajmniej 3 znaki
let isLoginLongEnough = () => {
  let length = $("#regLogin").val().length;
  if(length && length < 3){
    if(!$('#loginShort').length)$('#regErr').append('<span id="loginShort">Login has to be at least 4 chars long</span>');
  }else if(length){
    $('#loginShort').remove();
  }
}
//Callback do sprawdzania czy login zajęty
let handleLoginUniqueCheck = (msg) => {
  if(msg.result){
    $('#loginTaken').remove();
  }else{
    if(!$('#loginTaken').length)$('#regErr').append('<span id="loginTaken">Sorry login already taken</span>');
  }
}
//Obsluga sprwdzenia unikalności loginu
let isLoginUnique = () => {
  let value = $('#regLogin').val();
  isUnique("login",value,handleLoginUniqueCheck);
}
//Wywołuje funkcje sprawdzające dla loginu
$("#regLogin").keyup(() => {
  isLoginLongEnough();
  isLoginUnique();
})
//Callback do sprawdzania czy email zajęty
let handleEmailUniqueCheck = (msg) => {
  if(msg.result){
    $('#emailTaken').remove();
  }else{
    if(!$('#emailTaken').length)$('#regErr').append('<span id="emailTaken">Sorry email already taken</span>');
  }
}
//Obsluga sprwdzenia unikalności emala
let isEmailUnique = () => {
  let value = $('#form_email').val();
  isUnique("email",value,handleEmailUniqueCheck);
}
//Sprawdza czy mail jest poprawny i pasuje do regexpa ze standardu
let isEmailValid = () => {
  let emailRegex = /(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9]))\.){3}(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9])|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/;
  let val = $("#form_email").val();
  if(val.match(emailRegex) || !val.length){
    $('#emailWrong').remove();
  }else{
    if(!$('#emailWrong').length)$('#regErr').append('<span id="emailWrong">Incorrect email</span>');
  }
}
//Sprawdza czy domena nie jest zablokowana aka 10minutowa
let isDomainBlocked = () => {
  let blockedDomains = /bcaoo.com|yuoia.com|urhen.com|10minutemail.pl|99cows.com|daymail.life|uber-mail.com|emltmp.com|dropmail.me|10mail.org|yomail.info|emltmp.com|emlpro.com|emlhub.com|supre.ml|drope.ml|vmani.com/;
  let val = $('#form_email').val();
  if(val.match(blockedDomains)){
    if(!$('#blockedDomain').length)$('#regErr').append('<span id="blockedDomain">Sorry but email matches 10minutemail domain which for security reasons are blocked</span>');
  }
}
$('#form_email').keyup(()=>{
  isEmailUnique();
  isEmailValid();
  isDomainBlocked();
})
let isPasswordLongEnough = e => {
  if($(e.target).attr('id')=="regPw"){
    if($(e.target).val().length < 8){
      if(!$('#passShort').length)$('#regErr').append('<span id="passShort">Sorry but password needs to be at least 8 chars long</span>');
    }else{
      $('#passShort').remove();
    }
  }
}
//Ponieważ nie da sie sprawdzać długosci pola z hasłem bezposrednio nasłuch na cały formularz
$('#registerForm').keyup((e)=>{
  isPasswordLongEnough(e);
  changeRegisterState();
});
var changeRegisterState = () => {
  if($('#regErr').text()){
    $("#form_register").attr('disabled','disabled');
  }else if($('#form_email').val() && $('#regLogin').val() && $('#regPw').val()){
    $("#form_register").removeAttr('disabled');
  }
}
