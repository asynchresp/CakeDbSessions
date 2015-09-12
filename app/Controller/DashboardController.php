<?php

class DashboardController extends AppController
{
    public $uses = ['UserSession'];
    public function index()
    {
        $this->UserSession->logoutEverywhereButHere();
    }
}