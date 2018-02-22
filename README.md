# CakeDbSessions

CakePHP 2.8.x: Storing Sessions in the Database Revisited
Preface
A while back in January 2013, I wrote an article on how to store CakePHP’s sessions in the database, instead of using PHP as the session handler. Recently I received a question if it would be possible to expire (delete) all of a user’s sessions except the current one. It made me look back at the code I wrote in 2013 and I wondered if I could do better than I did before.

Summary
In this article, I cover how to setup CakePHP 2.8.x (the version is important, because of a bug fix that was necessary to make it work!) to use sessions stored in the database. I answer the question if it is possible to delete a user’s old sessions except the current one too. I also include an updated version of my previous attempt of retrieving online users. I placed all the code used on github for easy viewing.


Creating the database tables
We will be using at least two different tables: users and user_sessions. You can not use the name sessions because of a model named Session in CakePHP’s core files, that’s why I chose user_sessions as the table name.

The User table is pretty straightforward. I like using an email address instead of a username because of it’s double value: people tend to remember their email address, and you have a way to contact the user. I also like to add a field called is_deleted. This boolean value (expressed by TINYINT 0 or 1, as used in the CakePHP convention for storing booleans) is used to check if a user is allowed to sign in in the first place.

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
`id` int(10) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

ALTER TABLE `users` ADD PRIMARY KEY (`id`);
The UserSession table is a little less straight forward. The id field is not an UNSIGNED INTEGER, but rather a VARCHAR. The id field stores the session_id expressed as an MD5 hash string.

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` varchar(255) NOT NULL,
  `data` text,
  `user_id` int(10) unsigned DEFAULT NULL,
  `expires` int(10) unsigned DEFAULT NULL,
  `created` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `user_sessions` ADD PRIMARY KEY (`id`);
Remember to configure your CakePHP to use your database of choice with your credentials.

Configuring CakePHP Sessions
To configure CakePHP to use our custom database session handler, open the file app/Config/core.php in your editor of choice and search for the following code:

Configure::write('Session', array(
    'defaults' => 'php'
));
And replace it with this:

Configure::write('Session', [
    'defaults' => 'database',
    'handler' => [
        'model' => 'UserSession',
    ]
]);
The [] tags are the shorter way of describing arrays in PHP as of version 5.4.x. You might have seen them before in JavaScript code. It saves the hassle of writing the word array every time, which is nice.

Building the User and UserSession Models
Moving on, let’s start by creating our User and UserSession Model files e.g. app/Model/User.php and app/Model/UserSession.php. These files have a one to many relationship with each other. Every user can have multiple sessions, and every session belongs to one user only. By setting the behaviour of these models to containable, we can use CakePHP’s mechanism for automatic joins (contain).

<?php
// Using the Blowfish Password Hasher for added security, instead of weak MD5 hashes
App::uses('BlowfishPasswordHasher', 'Controller/Component/Auth');

class User extends AppModel
{
    // Add the Containable behaviour
    public $actsAs = ['Containable'];
    
    // Add the relation between the User and the UserSession
    public $hasMany = ['UserSession'];

    // Validation rules (please add your own where necessary, these are the bare minimum)
    public $validate = [
        'email' => [
            'rule' => 'isUnique',
            'allowEmpty' => false,
            'message' => 'This email address has already been used.',
        ],
        'password' => [
            'rule' => ['minLength', 8], // I like to force users to choose a password of at least 8 characters, without any other limitations
            'message' => 'A password should be at least %d characters long.',
        ],
    ];

    /**
     * Before Save callback
     * used to turn a user's plain password into a Hashed password
     * @param array $options The options passed to the save method
     * @return bool
     */
    public function beforeSave($options = [])
    {
        $data = $this->data;
        $alias = $this->alias;

        if (isset($data[$alias]['password']) && !empty($data[$alias]['password'])) {
            $hasher = new BlowfishPasswordHasher();
            $this->data[$alias]['password'] = $hasher->hash($data[$alias]['password']);
        }
        return true;
    }
}
The methods for retrieving active users and expiring every session except the current one are within the UserSession class. If you have any questions about how they work, please feel free to ask. I believe them to be easily understandable but that’s just me of course.

<?php
// Not the way it should be used (it should be part of the Controller) but I didn't know any other way
App::uses('AuthComponent', 'Controller/Component'); 

class UserSession extends AppModel
{
    // Add the Containable behaviour
    public $actsAs = ['Containable'];
    
    // Add the relation between the User and the UserSession
    public $belongsTo = ['User'];

    // Time in seconds before a session no longer should be seen as active
    private $_activeSessionThreshold = 600;

    /**
     * Before Save callback
     * used to add the User's ID to session data (if any, otherwise null is used)
     * @param array $options The options passed to the save method
     * @return bool
     */
    public function beforeSave($options = [])
    {
        $this->data[$this->alias]['user_id'] = AuthComponent::user('id');
        return true;
    }

    /**
     * Expire every session except the current one
     * @return bool
     */
    public function expireAllExceptCurrent()
    {
        if (!AuthComponent::user('id')) {
            return false;
        }
        
        $query = [
            'UserSession.id <>' => session_id(),
            'UserSession.user_id' => AuthComponent::user('id'),
        ];
        return $this->deleteAll($query);
    }

    /**
     * Find all the sessions that are considered active
     * @return array
     */
    public function findActive()
    {
        $query = [
            'recursive' => -1,
            'contain' => [
                'User' => [
                    'fields' => [
                        'User.id',
                        'User.email',
                    ],
                ],
            ],
            'conditions' => [
                'expires >=' => time() - $this->_activeSessionThreshold,
            ],
            'fields' => [
                'UserSession.id',
            ],
        ];
        return $this->find('all', $query);
    }
}
AppController
Because I use the Blowfish Password Hasher, and an email address instead of a username, I will show you what you have to put in your app/Controller/AppController.php file to make sure the User system works as expected. I will not go into detail about the User view files and the UserController because I believe these to be straightforward. You can always visit the github page mentioned in the summary for a more elaborate view on these files (which might not be complete, but still).

The AppController file just needs a few tweaks to make it work the way we want it to!

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
        return true; // Defaults to true if a controller does not override this method
    }
}
Epilogue
I hope you enjoyed this article in which I tried to show by example how I would solve the aforementioned question. Please feel free to respond if you have any questions, comments, or suggestions; and do so in the Disqus form below ;).


See http://wp.me/p5vqnY-3N for the article that comes with this code
