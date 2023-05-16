<?php

require_once 'bibli_erestou.php';

// démarrage ou reprise de la session
// pas besoin de démarrer la bufferisation des sorties
session_start();

sessionExit();

// redirection vers la page précédente
echo '<script>window.history.back();</script>';
