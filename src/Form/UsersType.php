<?php
namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;

use App\Entity\Users;

class UsersType extends AbstractType {
  public function buildLoginForm(FormBuilderInterface $builder){
    return $builder
    ->add('login',TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'login'],'label'=>false])
    ->add('password',PasswordType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'password'],'label'=>false])
    ->add('Login',SubmitType::class,['attr'=>['class'=>'submitStyled']])->getForm();
  }
  public function buildRegisterForm(FormBuilderInterface $builder){
    return $builder
    ->add('login',TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'login'],'label'=>false])
    ->add('email',TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'email'],'label'=>false])
    ->add('password',PasswordType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'password'],'label'=>false])
    ->add('register',SubmitType::class,['attr'=>['class'=>'submitStyled'],'disabled'=>true],'Register')->getForm();
  }
  public function buildUpdateForm(FormBuilderInterface $builder){
    return $builder->add("login",TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'login'],'label'=>false,'required'=>false,'disabled'=>true])
    ->add("name",TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'name'],'label'=>false,'required'=>false])
    ->add("surname",TextType::class,['attr'=>['class'=>'inputStyled','placeholder'=>'surname'],'label'=>false,'required'=>false])
    ->add("avatar",FileType::class,['mapped'=>false,'label'=>'Choose File','required'=>false])
    ->add("update",SubmitType::class,['attr'=>['class'=>'inputAction']])->getForm();
  }

}
