<?php
namespace App\Service;

class DataValidator {
  public function isEmailValid($email)
  {
    $blockedDomainsRegex ="/bcaoo.com|yuoia.com|urhen.com|10minutemail.pl|99cows.com|daymail.life|uber-mail.com|emltmp.com|dropmail.me|10mail.org|yomail.info|emltmp.com|emlpro.com|emlhub.com|supre.ml|drope.ml|vmani.com/";
    if(empty($email)) return false;
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
    if(preg_match($blockedDomainsRegex,$email))return false;
    return true;
  }
  public function isPasswordValid($password)
  {
    if(strlen($password) < 8) return false;
    return true;
  }
  public function isLoginValid($login){
    if(preg_match("/^[a-z]+[a-z0-9]+$/i",$login) && strlen($login)>3)return true;
    return false;
  }
  public function clearDangerousSymbols($value){
    return trim(htmlspecialchars($value));
  }
}
