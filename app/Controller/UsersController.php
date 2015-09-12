<?php

class UsersController extends AppController
{
    public $uses = ['User', 'UserSession'];

    public function beforeFilter()
    {
        parent::beforeFilter();
    }

    public function isAuthorized($user = null)
    {
        parent::beforeFilter();
        return true;
    }

    public function index()
    {
        $this->set('users', $this->User->find('all', []));
    }

    public function add()
    {
        if ($this->request->is('post')) {
            if ($this->User->save($this->request->data)) {
                $this->redirect(['controller' => 'Users', 'action' => 'index']);
            }

            $this->Session->setFlash('ERROR');
        }
    }

    public function edit()
    {

    }

    public function login()
    {
        if ($this->request->is('post')) {

            if ($this->Auth->login()) {
                $this->redirect($this->Auth->redirectUrl());
            }

            $this->Session-setFlash('ERROR');
        }
    }

    public function logout()
    {
        $this->redirect($this->Auth->logout());
    }
}