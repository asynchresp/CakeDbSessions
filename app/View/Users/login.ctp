<h3>Inloggen</h3>
<?php
    echo $this->Form->create('User');
    echo $this->Form->input('User.email');
    echo $this->Form->input('User.password');
    echo $this->Form->submit('Inloggen');
    echo $this->Form->end();
?>