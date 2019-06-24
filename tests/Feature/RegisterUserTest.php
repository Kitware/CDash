<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegisterUserTest extends TestCase
{
    use RefreshDatabase;

    private $url = 'register';

    public function testRegisterGivenNoOnSubmitEvent()
    {
        $form = $this->getForm(['url' => 'http://localhost/register']);

        $this->post('register', $form)
            ->assertSeeText('Bots are not allowed to obtain CDash accounts');
    }

    public function testRegisterGivenUserAlreadyExists()
    {
        $form = $this->getForm([], true);
        $this->post($this->url, $form)
            ->assertSeeText('The email has already been taken');
    }

    public function testRegisterGivenPasswordTooShort()
    {
        $passwords = ['password' => '1234', 'password_confirmation' => '1234'];
        $form = $this->getForm($passwords);

        $this->post($this->url, $form)
            ->assertSeeText('The password must be at least 5 characters');
    }

    public function testRegisterGivenEmailVerificationOff()
    {
        $setting = 'cdash.registration.email.verify';
        config([$setting => false]);
        $this->assertFalse(config($setting));

        $form = $this->getForm();
        $this->post($this->url, $form)
            ->assertRedirect('/');
    }

    public function testRegisterGivenEmailVerificationOn()
    {
        $setting = 'cdash.registration.email.verify';
        config([$setting => true]);
        $this->assertTrue(config($setting));

        $form = $this->getForm();
        $this->post($this->url, $form)
            ->assertRedirect('/email/verify');
    }

    private function getForm(array $supplied = [], $persist = false)
    {
        $factory = factory(User::class);

        if ($persist) {
            $user = $factory->create();
        } else {
            $user = $factory->make();
        }

        $post = [
            'fname' => $user->firstname,
            'lname' => $user->lastname,
            'email' => $user->email,
            'password' => 'secret',
            'password_confirmation' => 'secret',
            'institution' => $user->institution,
            'url' => 'catchbot',
        ];

        return array_merge($post, $supplied);
    }
}
