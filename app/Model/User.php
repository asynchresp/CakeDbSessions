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