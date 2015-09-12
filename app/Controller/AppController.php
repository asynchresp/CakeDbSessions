<?php
App::uses('Controller', 'Controller');

class AppController extends Controller
{
    public $components = [
        'Auth' => [
            'loginRedirect' => [
                'controller' => 'Dashboard', // Doesn't need to be the DashboardController, could be anything
                'action' => 'index',
            ],
            'logoutRedirect' => [
                'controller' => 'Users',
                'action' => 'login',
            ],
            'authenticate' => [
                'Form' => [
                    'fields' => [
                        'username' => 'email', // Tweaks to use the email address as the username
                        'password' => 'password', // Just making sure
                    ],
                    'scope' => [
                        'is_deleted' => 0, // Disabled users can not sign in
                    ],
                    'passwordHasher' => 'Blowfish', // Otherwise CakePHP will use something else when signing in
                ],
            ],
            'authorize' => [
                'Controller', // isAuthorized required in Controllers to control if a user can do something
            ],
        ],
        'Session',
        'Paginator', // For paginating your list of users
    ];

    public function beforeFilter()
    {
        $this->Auth->deny();
        $this->Auth->allow('login','add'); // TODO: Remove the "add" after creating a user
    }

    public function isAuthorized($user = null)
    {
        return true; // Default to true if a controller does not override this method
    }
}