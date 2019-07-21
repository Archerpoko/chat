<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Session\Storage;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Doctrine\ORM\EntityManagerInterface;

use App\Service\DataValidator;
use App\Service\FriendsTableCreator;
use App\Service\ChatTableCreator;
use App\Service\Authenticator;
use App\Service\UniquenessChecker;
use App\Service\UserDataManager;

use App\Entity\Users;

use App\Form\UsersType;

class ChatController extends AbstractController {

  /**
  * @Route("/welcome",name="welcome")
  */
  public function welcome(Request $request, Session $session,Authenticator $authenticator){
    if($session->has('login'))return $this->redirectToRoute('panel');

    $formBuilder = new UsersType();
    $user = new Users();

    $loginForm = $formBuilder->buildLoginForm($this->createFormbuilder($user));
    $registerForm = $formBuilder->buildRegisterForm($this->createFormbuilder($user));

    if($request->request->has('signin') && isset($request->request->get('signin')['login']) && isset($request->request->get('signin')['password'])){

      $signinparams = $request->get('signin');
      $repository = $this->getDoctrine()->getRepository(Users::class);
      $authenticator->handleLogin($signinparams,$repository);

      if($authenticator->isLoginAuthorized()){
        $authenticator->setSessionVariables();
        return $this->redirectToRoute('panel');
      }else{
        $authenticator->displayFlashLoginErrorrs();
      }

    }else if($request->request->has('signup') && isset($request->request->get('signup')['login']) && isset($request->request->get('signup')['email']) && isset($request->request->get('signup')['password'])){

      $signupparams = $request->get('signup');
      $repository = $this->getDoctrine()->getRepository(Users::class);
      $authenticator->handleRegistration($signupparams,$repository);

      if($authenticator->isRegistrationAuthorized()){
        $em = $this->getDoctrine()->getManager();
        $authenticator->createUser($em)->setSessionVariables();
        return $this->redirectToRoute('panel');
      }else{
        $authenticator->displayFlashRegistrationErrors();
      }
    }
    return $this->render('pages/login.html.twig',["loginForm"=>$loginForm->createView(),"registerForm"=>$registerForm->createView()]);
  }

  /**
  * @Route("/chat/{chatid}",name="chat")
  */
  public function chat(Session $session,$chatid,Authenticator $authenticator) {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');
    $conn = $this->getDoctrine()->getManager()->getConnection();
    $rep = $this->getDoctrine()->getRepository(Users::class);
    $user = $rep->find($session->get('id'));
    $messages = [];
    if($authenticator->doUserHaveAcces($user,$chatid,$conn)){
      $udd = new UserDataManager($conn,$rep);
      $friends = $udd->getAcceptedFriends($session->get('login'),$chatid);
      $messages = $udd->getMessages($chatid);
    }
    return $this->render('pages/chat.html.twig',['friends'=>$friends,'messages'=>$messages]);
  }
  /**
  * @Route("/messages",methods={"GET"},name="get_messages")
  */
  public function getMessages(Request $request,Session $session,Authenticator $authenticator){

    if(!$session->has('login')) return $this->redirectToRoute('welcome');
    $conn = $this->getDoctrine()->getManager()->getConnection();
    $messages = [];
    $chatid = $request->query->get('chatid');
    $user = $this->getDoctrine()->getRepository(Users::class)->findOneBy(['login'=>$session->get('login')]);

    if($authenticator->doUserHaveAcces($user,$chatid,$conn)){
      $udd = new UserDataManager($conn,$this->getDoctrine()->getRepository(Users::class));
      $messages = $udd->getMessages($chatid);
    }
    return new JsonResponse(["history"=>$messages,"userid"=>$session->get('id')]);
  }
  /**
  * @Route("/send",methods={"POST"},name="send_message")
  */
  public function send(Request $request,Session $session,DataValidator $dv,Authenticator $authenticator){
    if(!$session->has('login')) return $this->redirectToRoute('welcome');

    $chatid = $request->request->get('chatid');
    $message = $dv->clearDangerousSymbols($request->request->get('message'));

    $conn = $this->getDoctrine()->getManager()->getConnection();
    $rep = $this->getDoctrine()->getRepository(Users::class);
    $user = $rep->find($session->get('id'));
    if($authenticator->doUserHaveAcces($user,$chatid,$conn)){
      $udd = new UserDataManager($conn,$rep);
      $udd->sendMessage($chatid,$user->getId(),$message);
    }
    return new Response();
  }
  /**
  * @Route("/panel",name="panel")
  */
  public function panel(Request $request,Session $session,Authenticator $authenticator){
    if(!$session->has('login')) return $this->redirectToRoute('welcome');
    $user = new Users();
    $formBuilder = new UsersType();
    $updateProfileForm = $formBuilder->buildUpdateForm($this->createFormbuilder($user));

    $updateProfileForm->handleRequest($request);

    $rep = $this->getDoctrine()->getRepository(Users::class);
    $conn = $this->getDoctrine()->getConnection();
    $udd = new UserDataManager($conn,$rep);
    $friendsList = $udd->getAllFriends($session->get('login'));

    if($updateProfileForm->isSubmitted() && $updateProfileForm->isvalid()){
      $em = $this->getDoctrine()->getManager();
      $user = $em->getRepository(Users::class)->findOneBy(['login'=>$session->get('login')]);
      $data = $updateProfileForm->getData();
      $file = $request->files->get('form')['avatar'];
      $authenticator->updateProfile($user,$em,$data,$file)->setSessionVariables();
    }

    return $this->render('pages/userPanel.html.twig',['updateProfileForm'=>$updateProfileForm->createView(),'friends'=>$friendsList]);
  }
  /**
  * @Route("/isunique/{field}",name="check_if_unique",requirements={"field"="email|login"})
  */
  public function isUnique(Request $request,$field,UniquenessChecker $uc){
    $value = "";
    $result = NULL;
    if($request->request->has('value')){
      $repository = $this->getDoctrine()->getRepository(Users::class);
      $value = $request->request->get('value');
      $result = $uc->isValueUnique($repository,$field,$value);
    }
    return new JsonResponse(['value'=>$value,'field'=>$field,'result'=>$result]);
  }
  /**
  * @Route("/logout")
  */
  public function logout(Session $session){
    $session->clear();
    return $this->redirectToRoute('welcome');
  }
  /**
  * @Route("/remove/{nick}",name="remove_friend_request")
  */
  public function remove($nick,Session $session,DataValidator $validator) {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');

    if($validator->isLoginValid($nick)){
      $conn = $this->getDoctrine()->getConnection();
      $rep = $this->getDoctrine()->getRepository(Users::class);
      $user = $rep->find($session->get('id'));
      $targetUser = $rep->findOneBy(['login'=>$nick]);
      $udd = new UserDataManager($conn,$rep);
      if($targetUser && $udd->isUserInvited($user,$targetUser)){
        $udd->removeFriend($user,$targetUser);
      }
    }
    return $this->redirectToRoute('panel');
  }
  /**
  * @Route("/accept/{nick}",name="accept_friend_request")
  */
  public function accept($nick,Session $session,DataValidator $validator) {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');

    if($validator->isLoginValid($nick)){
      $conn = $this->getDoctrine()->getConnection();
      $rep = $this->getDoctrine()->getRepository(Users::class);
      $em = $this->getDoctrine()->getManager();
      $user = $rep->find($session->get('id'));
      $targetUser = $rep->findOneBy(['login'=>$nick]);
      $udd = new UserDataManager($conn,$rep);
      if($targetUser && $udd->isUserInvited($user,$targetUser)){
        $udd->acceptInvite($user,$targetUser,$em);
      }
    }
    return $this->redirectToRoute('panel');
  }
  /**
  * @Route("/invite/{nick}",name="invite_friend")
  */
  public function invite($nick,Session $session,DataValidator $validator) {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');
    if($validator->isLoginValid($nick) && mb_strtolower($nick) != mb_strtolower($session->get('login'))){
      //Jeśli to nick i nie jest to nick aktualnego użytkownika
      $conn = $this->getDoctrine()->getConnection();
      $rep = $this->getDoctrine()->getRepository(Users::class);
      $user = $rep->find($session->get('id'));
      $targetUser = $rep->findOneBy(['login'=>$nick]);
      $udd = new UserDataManager($conn,$rep);
      if($targetUser && !$udd->isUserInvited($user,$targetUser)){
        $udd->inviteUser($user,$targetUser);
      }
    }
    return $this->redirectToRoute('panel');
  }
  /**
  * @Route("/")
  */
  public function index(Session $session){
    if($session->has('login')){
      return $this->redirectToRoute('panel');
    }else{
      return $this->redirectToRoute('welcome');
    }
  }
}
