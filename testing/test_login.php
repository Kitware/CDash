<?php
// simpletest library
require_once('simpletest/kw_web_tester.php');

class loginTestCase extends KWWebTestCase
{
  function __construct()
   {
   parent::__construct();
   require('config.test.php');
   $this->url = $configure['urlwebsite'];
   }
   
  function testHomePage()
    {
    $content = $this->connect($this->url);
    if($content == false)
      {
      return;
      }
    $this->clickLink('Login');
    $this->setField('login','simpletest@localhost');
    $this->setField('passwd','simpletest');
    $this->clickSubmit('Login >>');
    $this->assertNoText('Wrong email or password','No text Wrong email or password detected in test_login.php.18');
    }

  function testRegisterWithBotsDetection()
    {
    $content = $this->connect($this->url);
    if($content == false)
      {
      return;
      }
    $this->analyse($this->clickLink('Register'));
    $this->fillOutRegisterForm();
    $this->setField('url', 'catchbot');
    $this->clickSubmit('Register');
    $this->assertText('Bots are not allowed to obtain CDash accounts!','Bots detected');
    }
    
  function testRegister()
    {
    $url = $this->url.'/register.php';
    $content = $this->connect($url);
    if($content == false)
      {
      return;
      }
    $this->fillOutRegisterForm();
    $this->clickSubmit('Register',array('url' => 'catchbot'));
    $this->assertText('Registration Complete. Please login with your email and password.');
    }
    
  function fillOutRegisterForm()
   {
    $fname        = 'test';
    $lname        = 'kw';
    $email        = 'test@kw';
    $passwd       = 'kitware';
    $institution  = 'developer';
    $this->setField('fname',$fname);
    $this->setField('lname',$lname);
    $this->setField('email',$email);
    $this->setField('passwd',$passwd);
    $this->setField('passwd2',$passwd);
    $this->setField('institution',$institution);
   }
}
?>
