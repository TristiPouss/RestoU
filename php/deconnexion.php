<?php

require_once 'bibli_erestou.php';

// démarrage ou reprise de la session
// pas besoin de démarrer la bufferisation des sorties
session_start();

if(isset($_SESSION['back'])){
    sessionExit($_SESSION['back']);
    exit();
}
sessionExit();