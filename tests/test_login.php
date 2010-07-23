<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');

class LoginTestCase extends KWWebTestCase
{
  var $url = null;
  
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
    $this->clickSubmitByName('sent');
    $this->assertNoText('Wrong email or password');
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
    $this->clickSubmitByName('sent');
    $this->assertText('Bots are not allowed to obtain CDash accounts!','Bots detected in test_login.php.42');
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
    $this->clickSubmitByName('sent',array('url' => 'catchbot'));
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
