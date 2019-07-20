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
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

use Doctrine\ORM\EntityManagerInterface;

use App\Service\DataValidator;
use App\Service\FriendsTableCreator;
use App\Service\ChatTableCreator;

use App\Entity\Users;

class ChatController extends AbstractController {

  /**
  * @Route("/welcome",name="welcome")
  */
  public function welcome(Request $request, Session $session){
    if($session->has('login'))return $this->redirectToRoute('panel');

    $user = new Users();
    $loginForm = $this->createFormbuilder($user)
    ->add('login',TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'login'],'label'=>false])
    ->add('password',PasswordType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'password'],'label'=>false])
    ->add('Login',SubmitType::class,['attr'=>['class'=>'submitStyled']])->getForm();

    $registerForm = $this->createFormbuilder($user)
    ->add('login',TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'login'],'label'=>false])
    ->add('email',TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'email'],'label'=>false])
    ->add('password',PasswordType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'password'],'label'=>false])
    ->add('register',SubmitType::class,['attr'=>['class'=>'submitStyled'],'disabled'=>true],'Register')->getForm();

    if($request->request->has('signin') && isset($request->request->get('signin')['login']) && isset($request->request->get('signin')['password'])){
      $signinparams = $request->get('signin');
      $login = $signinparams['login'];
      $validate = new DataValidator();

      if($validate->isPasswordValid($signinparams['password'])){
        $password = $signinparams['password'];
        $repository = $this->getDoctrine()->getRepository(Users::class);
        $user = $repository->findOneBy(['login'=>$login]);
        if(!$user){
          $session->getFlashBag()->add('loginError','There is no such user');
        }else {
          if(password_verify($password,$user->getPassword())){
            $session->set('id',$user->getId());
            $session->set('login',$user->getLogin());
            $session->set('name',$user->getName());
            $session->set('surname',$user->getSurname());
            $session->set('email',$user->getEmail());
            $session->set('avatar',$user->getAvatar());
            $session->set('friendTableName',$user->getFriendsTableName());
            return $this->redirectToRoute('panel');
          }else{
            $session->getFlashBag()->add('loginError','Wrong password');
          }
        }
      }
    }else if($request->request->has('signup') && isset($request->request->get('signup')['login']) && isset($request->request->get('signup')['email']) && isset($request->request->get('signup')['password'])){
      $validate = new DataValidator();
      $login = $request->request->get('signup')['login'];
      $email = $request->request->get('signup')['email'];
      $password = $request->request->get('signup')['password'];
      $qb = $this->getDoctrine()->getManager()->createQueryBuilder()
           ->select('u')
           ->from('App\Entity\Users','u')
           ->andWhere('LOWER(u.login) = :login')
           ->setParameter('login', mb_strtolower($login))
           ->getQuery();
      $lookForLogin = $qb->execute();
      $qb = $this->getDoctrine()->getManager()->createQueryBuilder()
           ->select('u')
           ->from('App\Entity\Users','u')
           ->andWhere('LOWER(u.email) = :email')
           ->setParameter('email', mb_strtolower($email))
           ->getQuery();
      $lookForEmail = $qb->execute();

      $status = true;
      if($lookForEmail){
        $session->getFlashBag()->add('registerError','Email already taken');
        $status = false;
      }
      if($lookForLogin){
        $session->getFlashBag()->add('registerError','Login already taken');
        $status = false;
      }
      if(!$validate->isEmailValid($email)){
        $session->getFlashBag()->add('registerError','Invalid email or email from 10minutemail domain');
        $status = false;
      }
      if(!$validate->isPasswordValid($password)){
        $session->getFlashBag()->add('registerError','Password too short');
        $status = false;
      }
      if(!$validate->isLoginValid($login)){
        $session->getFlashBag()->add('registerError','Login have to start with letter and can have only letters and nubmers');
        $status = false;
      }
      if($status){
        $user = new Users();
        $user->setLogin($login);
        $user->setEmail($email);
        $password = password_hash($password,PASSWORD_DEFAULT);
        $user->setPassword($password);

        $em = $this->getDoctrine()->getManager();
        $friendTable = new FriendsTableCreator($user,$em);
        $friendTable->prepareLogin()->createTableName()->buildQuery();
        $friendTable->execute();
        $user->setFriendsTableName($friendTable->tableName);

        $em->persist($user);
        $em->flush();

        $user = $this->getDoctrine()->getRepository(Users::class)->findOneBy(['login'=>$login]);
        $session->set('id',$user->getId());
        $session->set('login',$login);
        $session->set('name',"");
        $session->set('surname',"");
        $session->set('email',$email);
        $session->set('avatar',"img/avatars/defaultAvatar.png");
        $session->set('friendTableName',$friendTable->tableName);
        return $this->redirectToRoute('panel');
      }
    }
    return $this->render('pages/login.html.twig',["loginForm"=>$loginForm->createView(),"registerForm"=>$registerForm->createView()]);
  }

  /**
  * @Route("/chat/{chatid}",name="chat")
  */
  public function chat(Session $session,$chatid)
  {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');
    $friendsTableName = $session->get('friendTableName');

    $conn = $this->getDoctrine()->getManager()->getConnection();
    $friendsList = "SELECT userid,chatid FROM $friendsTableName WHERE status = 2";

    $stmt = $conn->prepare($friendsList);
    $stmt->execute();
    $friends = $stmt->fetchAll();

    foreach ($friends as $key => $friend) {
      $user = $this->getDoctrine()->getRepository(Users::class)->find($friend['userid']);
      $friends[$key]['login'] = $user->getLogin();
      $friends[$key]['avatar'] = $user->getAvatar();
      if($friend['chatid']==$chatid){
        $friends[$key]['picked'] = true;
      }
      $friendchatid = $friends[$key]['chatid'];
      $lastMsgSQL = "SELECT message FROM $friendchatid ORDER BY timesend DESC LIMIT 1";

      $stmt = $conn->prepare($lastMsgSQL);
      $stmt->execute();
      $lastmsg = $stmt->fetchAll();

      $friends[$key]['lastMsg'] = (isset($lastmsg[0]) && isset($lastmsg[0]['message']))? $lastmsg[0]['message'] : 'No messages say hello';
    }

    $messagesSQL = "SELECT message,`from`,timesend FROM $chatid ORDER BY timesend";

    $stmt = $conn->prepare($messagesSQL);
    $stmt->execute();
    $messages = $stmt->fetchAll();

    return $this->render('pages/chat.html.twig',['friends'=>$friends,'messages'=>$messages]);
  }
  /**
  * @Route("/messages",methods={"GET"},name="get_messages")
  */
  public function getMessages(Request $request,Session $session){

    if(!$session->has('login')) return $this->redirectToRoute('welcome');
    $conn = $this->getDoctrine()->getManager()->getConnection();
    $messages = [];

    $chatid = $request->query->get('chatid');
    $friendsTableName = $session->get('friendTableName');

    $grantOrNotSQL = "SELECT id FROM $friendsTableName WHERE chatid = '$chatid'";
    $stmt = $conn->prepare($grantOrNotSQL);
    $stmt->execute();
    $result = $stmt->fetchAll();

    if(isset($result[0]) && isset($result[0]['id'])){
      $getMessagesSQL = "SELECT message,`from`,timesend FROM $chatid ORDER BY timesend";
      $stmt = $conn->prepare($getMessagesSQL);
      $stmt->execute();
      $messages = $stmt->fetchAll();
    }

    return new JsonResponse(["history"=>$messages,"userid"=>$session->get('id')]);
  }
  /**
  * @Route("/send",methods={"POST"},name="send_message")
  */
  public function send(Request $request,Session $session){
    if(!$session->has('login')) return $this->redirectToRoute('welcome');

    $chatid = $request->request->get('chatid');
    $message = htmlspecialchars($request->request->get('message'));
    $message = trim($request->request->get('message'));
    $conn = $this->getDoctrine()->getManager()->getConnection();

    $friendsTableName = $session->get('friendTableName');
    $grantOrNotSQL = "SELECT id FROM $friendsTableName WHERE chatid = '$chatid'";
    $stmt = $conn->prepare($grantOrNotSQL);
    $stmt->execute();
    $result = $stmt->fetchAll();

    if(isset($result[0]) && isset($result[0]['id']) && strlen($message)){
      $time = date("Y-m-d H:i:s");
      $userid = $session->get('id');
      $sendMessageSQL = "INSERT INTO $chatid (`from`,message,timesend) VALUES($userid,'$message','$time')";

      $conn->prepare($sendMessageSQL)->execute();
    }

    return new Response();
  }
  /**
  * @Route("/panel",name="panel")
  */
  public function panel(Request $request,Session $session)
  {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');
    $user = new Users();
    $updateProfileForm = $this->createFormbuilder($user)->add("login",TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'login'],'label'=>false,'required'=>false,'disabled'=>true])
    ->add("name",TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'name'],'label'=>false,'required'=>false])
    ->add("surname",TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'surname'],'label'=>false,'required'=>false])
    ->add("avatar",FileType::class,['mapped'=>false,'label'=>'Choose File','required'=>false])
    ->add("update",SubmitType::class,['attr'=>['class'=>'inputAction']])->getForm();

    $updateProfileForm->handleRequest($request);

    $conn = $this->getDoctrine()->getConnection();
    $em = $this->getDoctrine()->getManager();
    $tableName = $session->get('friendTableName');
    $sql = "SELECT userid,chatid,status FROM $tableName WHERE status = 1 OR status = 2";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll();
    foreach ($result as $key => $value) {
      $user = $em->getRepository(Users::class)->find($value['userid']);
      $result[$key]['avatar'] = $user->getAvatar();
      $result[$key]['nick'] = $user->getLogin();
    }
    if($updateProfileForm->isSubmitted() && $updateProfileForm->isvalid()){

      $user = $em->getRepository(Users::class)->findOneBy(['login'=>$session->get('login')]);
      $data = $updateProfileForm->getData();
      $file = $request->files->get('form')['avatar'];
      if($file){
        $fileName = md5(uniqid()).'.'.$file->guessClientExtension();
        $file->move('img/avatars/',$fileName);
        $user->setAvatar("/img/avatars/$fileName");
        $session->set('avatar',"/img/avatars/$fileName");
      }
      $user->setName($data->getName());
      $user->setSurname($data->getSurname());
      $session->set('name',$data->getName());
      $session->set('surname',$data->getSurname());
      $em->flush();
    }

    return $this->render('pages/userPanel.html.twig',['updateProfileForm'=>$updateProfileForm->createView(),'friends'=>$result]);
  }
  /**
  * @Route("/isunique/{field}",name="check_if_unique",requirements={"field"="email|login"})
  */
  public function isUnique(Request $request,$field)
  {
    $value = "";
    $result = NULL;
    if($request->request->has('value')){
      $value = $request->request->get('value');
      $user = $this->getDoctrine()->getRepository(Users::class)->findOneBy([$field=>$value]);
      $result = (!$user)? true : false;
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
  public function remove($nick,Session $session) {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');

    $validator = new DataValidator();
    if($validator->isLoginValid($nick)){
      $conn = $this->getDoctrine()->getConnection();
      $targetUser = $this->getDoctrine()->getRepository(Users::class)->findOneBy(['login'=>$nick]);
      if($targetUser){
        $userFriendTableName = $session->get('friendTableName');
        $userId = $session->get('id');
        $targetUserFriendTableName = $targetUser->getFriendsTableName();
        $targetUserId = $targetUser->getId();

        $userSQL = "DELETE FROM $userFriendTableName WHERE userid = $targetUserId";
        $targetUserSQL = "DELETE FROM $targetUserFriendTableName WHERE userid = $userId";

        $conn->prepare($userSQL)->execute();
        $conn->prepare($targetUserSQL)->execute();

      }
    }
    return $this->redirectToRoute('panel');
  }
  /**
  * @Route("/accept/{nick}",name="accept_friend_request")
  */
  public function accept($nick,Session $session) {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');

    $validator = new DataValidator();
    if($validator->isLoginValid($nick)){
      $conn = $this->getDoctrine()->getConnection();
      $targetUser = $this->getDoctrine()->getRepository(Users::class)->findOneBy(['login'=>$nick]);
      if($targetUser){
        $userFriendTableName = $session->get('friendTableName');
        $userId = $session->get('id');
        $targetUserFriendTableName = $targetUser->getFriendsTableName();
        $targetUserId = $targetUser->getId();

        $em = $this->getDoctrine()->getManager();
        $chatTable = new ChatTableCreator($em);
        $chatTable->createTableName()->buildQuery()->execute();
        $chatTableName = $chatTable->tableName;

        $updateTargetUserSQL = "UPDATE $targetUserFriendTableName SET chatId = '$chatTableName',status = 2 WHERE userid = $userId";
        $updateUserSQL = "UPDATE $userFriendTableName SET chatId = '$chatTableName',status = 2 WHERE userid = $targetUserId";

        $conn->prepare($updateTargetUserSQL)->execute();
        $conn->prepare($updateUserSQL)->execute();
      }
    }
    return $this->redirectToRoute('panel');
  }
  /**
  * @Route("/invite/{nick}",name="invite_friend")
  */
  public function invite($nick,Session $session) {
    if(!$session->has('login')) return $this->redirectToRoute('welcome');

    $tableName = $session->get('friendTableName');
    $validator = new DataValidator();
    if($validator->isLoginValid($nick) && $nick != $session->get('login')){
      $conn = $this->getDoctrine()->getConnection();
      $targetUser = $this->getDoctrine()->getRepository(Users::class)->findOneBy(['login'=>$nick]);
      if($targetUser){

        $targetUserId = $targetUser->getId();
        $targetUserFriendTableName = $targetUser->getFriendsTableName();
        $userid = $session->get('id');
        $userFriendTableName = $session->get('friendTableName');

        $isInviteSendSQL = "SELECT id FROM $userFriendTableName WHERE userid = $targetUserId";

        $stmt = $conn->prepare($isInviteSendSQL);
        $stmt->execute();

        $result = $stmt->fetchAll();

        if(!isset($result[0]) && !isset($result[0]['id'])){
          $insertRequestInTargetUserSQL = "INSERT INTO $targetUserFriendTableName (userid,status) VALUES($userid,1)";
          $conn->prepare($insertRequestInTargetUserSQL)->execute();
          $insertRequestInUserSQl = "INSERT INTO $userFriendTableName (userid,status) VALUES($targetUserId,3)";
          $stmt = $conn->prepare($insertRequestInUserSQl)->execute();
        }
      }
    }
    return $this->redirectToRoute('panel');
  }
  /**
  * @Route("/try")
  */
  public function try(){
    dump(md5(uniqid()));
    return new Response();
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
