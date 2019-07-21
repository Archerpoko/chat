<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

use App\Service\DataValidator;
use App\Service\UniquenessChecker;
use App\Service\FriendsTableCreator;

use App\Entity\Users;

class Authenticator {
  private $loginAuthorized;
  private $registrationAuthorized;
  private $user;
  private $loginErrors;
  private $registrationErrors;
  private $signupparams;
  private $repository;
  public function __construct(){
    $this->user = new Users();
    $this->loginAuthorized =  false;
    $this->registrationAuthorized =  true;
    $this->loginErrors = [];
    $this->registrationErrors = [];
  }
  public function handleLogin($signinparams,$repository){
    $validate = new DataValidator();
    if($validate->isPasswordValid($signinparams['password'])){
      $user = $repository->findOneBy(['login'=>$signinparams['login']]);
      if(!$user){
        $this->loginErrors[] = 'There is no such user';
      }else {
        if(password_verify($signinparams['password'],$user->getPassword())){
          $this->user = $user;
          $this->loginAuthorized = true;
        }else{
          $this->loginErrors[] = 'password';
        }
      }
    }
    return $this;
  }
  public function isLoginAuthorized(){
    return $this->loginAuthorized;
  }
  public function setSessionVariables(){
    $session = new Session();
    $user = $this->user;
    $session->set('id',$user->getId());
    $session->set('login',$user->getLogin());
    $session->set('name',$user->getName());
    $session->set('surname',$user->getSurname());
    $session->set('email',$user->getEmail());
    $session->set('avatar',$user->getAvatar());
    $session->set('friendTableName',$user->getFriendsTableName());
  }
  public function displayFlashLoginErrorrs(){
    $session = new Session();
    foreach($this->loginErrors as $error){
      $session->getFlashBag()->add('loginError',$error);
    }
  }
  public function handleRegistration($signupparams,$repository){
    $validate = new DataValidator();
    $uc = new UniquenessChecker();

    $this->signupparams = $signupparams;
    $this->repository = $repository;

    if(!$uc->isValueUnique($repository,"email",$signupparams['email'])){
      $this->registrationErrors[] = 'Email already taken';
      $this->registrationAuthorized = false;
    }
    if(!$uc->isValueUnique($repository,"login",$signupparams['login'])){
      $this->registrationErrors[] = 'Login already taken';
      $this->registrationAuthorized = false;
    }
    if(!$validate->isEmailValid($signupparams['email'])){
      $this->registrationErrors[] = 'Invalid email or email from 10minutemail domain';
      $this->registrationAuthorized = false;
    }
    if(!$validate->isPasswordValid($signupparams['password'])){
      $this->registrationErrors[] = 'Password too short';
      $this->registrationAuthorized = false;
    }
    if(!$validate->isLoginValid($signupparams['login'])){
      $this->registrationErrors[] = 'Login have to start with letter and can have only letters and nubmers';
      $this->registrationAuthorized = false;
    }

  }
  public function isRegistrationAuthorized(){
    return $this->registrationAuthorized;
  }
  public function createUser($em){
    $signupparams = $this->signupparams;
    $this->user->setLogin($signupparams['login']);
    $this->user->setEmail($signupparams['email']);
    $password = password_hash($signupparams['password'],PASSWORD_DEFAULT);
    $this->user->setPassword($password);

    $ftc = new FriendsTableCreator($this->user,$em);
    $ftc->prepareLogin()->createTableName()->buildQuery()->execute();
    $this->user->setFriendsTableName($ftc->tableName);

    $em->persist($this->user);
    $em->flush();
    $this->user = $this->repository->findOneBy(['login'=>$signupparams['login']]);
    return $this;
  }
  public function displayFlashRegistrationErrors(){
    $session = new Session();
    foreach($this->registrationErrors as $error){
      $session->getFlashBag()->add('registerError',$error);
    }
  }
  public function updateProfile(Users $user,$em,$data,$file){
    if($file){
      $fileName = md5(uniqid()).'.'.$file->guessClientExtension();
      $file->move('img/avatars/',$fileName);
      $user->setAvatar("/img/avatars/$fileName");
      $session->set('avatar',"/img/avatars/$fileName");
    }
    $user->setName($data->getName());
    $user->setSurname($data->getSurname());
    $em->flush();
    $this->user = $user;
    return $this;
  }
  public function doUserHaveAcces(Users $user,$chatid,$conn){
    $friendsTableName = $user->getFriendsTableName();
    $grantOrNotSQL = "SELECT id FROM $friendsTableName WHERE chatid = '$chatid'";
    $stmt = $conn->prepare($grantOrNotSQL);
    $stmt->execute();
    $result = $stmt->fetchAll();
    return (isset($result[0]) && isset($result[0]['id']));
  }
}
