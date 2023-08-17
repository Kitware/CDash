<?php

namespace App\Http\Controllers\Auth;

use App\Models\Password;
use App\Models\User;
use App\Http\Controllers\AbstractController;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/email/verify';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showRegistrationForm(Request $request): View
    {
        if (config('auth.user_registration_form_enabled') === false) {
            abort(404, 'Registration via form is disabled');
        }
        // We can route a user here with our form pre-populated
        return $this->view('auth.register', 'Register')
            ->with('fname', $request->get('fname'))
            ->with('lname', $request->get('lname'))
            ->with('email', $request->get('email'));
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        // create a custom message for the url field, which is actually a honeypot
        $messages = [
            'url.regex' => 'Bots are not allowed to obtain CDash accounts',
        ];

        $password_min = config('cdash.password.min');

        $rules = [
            'url' => ['bail', 'required', 'string', 'regex:/^catchbot$/'],
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:user'],
            'password' => ['required', 'string', "min:{$password_min}", 'complexity', 'confirmed'],
        ];

        return Validator::make($data, $rules, $messages);
    }

    /**
     * Create a new user instance after a valid registration.
     * Assumes that caller has already validated $data.
     */
    public function create(array $data) : User|null
    {
        if (is_null($data['institution'])) {
            $data['institution'] = '';
        }
        return User::create([
            'firstname' => $data['fname'],
            'lastname' => $data['lname'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution' => $data['institution'],
        ]);
    }

    /**
     * The user has been registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function registered(Request $request, $user)
    {
        $location = $user->hasVerifiedEmail() ? '/' : '/email/verify';
        return redirect($location);
    }

    /**
     * Handle a registration request for the application.
     *
     * @throws ValidationException
     */
    public function register(Request $request): Response|RedirectResponse
    {
        if (config('auth.user_registration_form_enabled') === false) {
            return response("Registration via form is disabled", 404);
        }
        try {
            $this->validator($request->all())->validate();
        } catch (ValidationException $e) {
            $e->response = response($this->view('auth.register', 'Register')
                    ->with('errors', $e->validator->getMessageBag())
                    ->with('fname', $request->get('fname'))
                    ->with('lname', $request->get('lname'))
                    ->with('email', $request->get('email'))
                    ->with('institution', $request->get('institution')), 422);
            throw $e;
        }

        $user = $this->create($request->all());

        $user->passwords()->save(new Password(['password' => $user->password]));
        event(new Registered($user));
        $this->guard()->login($user);

        return $this->registered($request, $user)
            ?: redirect($this->redirectPath());
    }
}
