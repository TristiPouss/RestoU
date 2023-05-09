<?php

require_once 'bibli_erestou.php';

// démarrage ou reprise de la session
// pas besoin de démarrer la bufferisation des sorties
session_start();

sessionExit();

// redirection vers la page précédente
if(isset($_SERVER['HTTP_REFERER'])){
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}

// redirection vers la page menu.php
header('Location: menu.php');
