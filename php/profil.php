<?php

require_once 'bibli_generale.php';
require_once 'bibli_erestou.php';

// bufferisation des sorties
ob_start();

// démarrage ou reprise de la session
session_start();

// si l'utilisateur n'est pas authentifié, on le redirige sur la page index.php
if (! estAuthentifie()){
    header('Location: ../index.php');
    exit;
}

$_SESSION['profil'] = true;

$err = null;
if(isset($_POST['validateEdit'])){
    $err = traitementUpdate();
}

$err2 = null;
if(isset($_POST['validateEdit2'])){
    $err2 = traitementUpdate2();
}

// affichage de l'entête
affEntete('Profil');
// affichage de la barre de navigation
affNav();
// affichage du contenu
affContenuL($err, $err2);
// affichage du pied de page
affPiedDePage();

ob_end_flush();

//_______________________________________________________________
/**
 * Génère le contenu de la page.
 * 
 * @param array     $err les erreurs lors de l'edit
 *
 * @return void
 */
function affContenuL(?array $err, ?array $err2){
    $infoUser = getInfoUser();

    //Stats
    $nbRepas = intval($infoUser['nbRepas']);
    $nbRepasCom = intval($infoUser['nbRepasCom']);
    $pourcentRepasCom = 0;
    if($nbRepas != 0){ // evite la division par 0
        $pourcentRepasCom =  ($nbRepasCom / $nbRepas) * 100;
    }
    $calories = round($infoUser['moyCalories'], 1); 
    $carbone = round($infoUser['moyCarbone'], 1); 

    list($jour, $mois, $annee) = getJourMoisAnneeFromDate($infoUser['usDateNaissance']);
    $mois = moisStr($mois);

    $edit = isset($_POST['edit']);
    if(isset($_POST['cancel'])){
        $edit = false;
    }

    $edit2 = isset($_POST['edit2']);
    if(isset($_POST['cancel2'])){
        $edit2 = false;
    }

    echo
        '<div class="profile">',
            '<div class="title">',
                '<h4>Informations du profil</h4>',
                '<h4>Statistiques de l\'utilisateur</h4>',
            '</div>',
            '<div class="infoUser">';
            if (is_array($err)) {
                echo    '<div class="error">Les erreurs suivantes ont été relevées lors de l\'édition :',
                            '<ul>';
                foreach ($err as $e) {
                    echo        '<li>', $e, '</li>';
                }
                echo        '</ul>',
                        '</div>';
            }
    echo
            '<form id="editProfile" action="profil.php" method="POST">',
                '<table>'
    ;
    affLigneInput(  'Prénom :', array('type' => 'text', 'name' => 'prenom', 'value' => $infoUser['usPrenom'], ($edit) ? : 'disabled' => 'disabled'));
    affLigneInput(  'Nom :', array('type' => 'text', 'name' => 'nom', 'value' => $infoUser['usNom'], ($edit) ? : 'disabled' => 'disabled'));
    affLigneInput(  'Mail :', array('type' => 'mail', 'name' => 'mail', 'value' => $infoUser['usMail'], ($edit) ? : 'disabled' => 'disabled'));

    echo
                    '<tr>',
                        '<td colspan="2">';
                    if(!$edit){
                        echo'<input type="submit" name="edit" value="Editer le profil">';
                    }else{
                        echo'<input type="submit" name="validateEdit" value="Valider">',
                            '<input type="submit" name="cancel" value="Annuler">';
                    }
    echo
                    '</td>',
                '</tr>';
                if (is_array($err2)) {
                    echo    '<div class="error">Les erreurs suivantes ont été relevées lors de l\'édition :',
                                '<ul>';
                    foreach ($err2 as $e) {
                        echo        '<li>', $e, '</li>';
                    }
                    echo        '</ul>',
                            '</div>';
                }
    echo        '<form id="editProfile2" action="profil.php" method="POST">';
    affLigneInput(  'Login :', array('type' => 'text', 'name' => 'oldLogin', 'value' => $infoUser['usLogin'], ($edit2) ? : 'disabled' => 'disabled'));
    affLigneInput(  'Mot de passe :', array('type' => 'password', 'name' => 'oldPasse', 'value' => '', 'placeholder' => '****', ($edit2) ? : 'disabled' => 'disabled'));
    ($edit2) ? affLigneInput(  'Nouveau Login :', array('type' => 'text', 'name' => 'login', 'value' => '')) : null;
    ($edit2) ? affLigneInput(  'Nouveau le MdP :', array('type' => 'password', 'name' => 'passe1', 'value' => '')) : null;
    ($edit2) ? affLigneInput(  'Confirmer le MdP :', array('type' => 'password', 'name' => 'passe2', 'value' => '')) : null;


    echo
                    '<tr>',
                        '<td colspan="2">';
                    if(!$edit2){
                        echo'<input type="submit" name="edit2" value="Editer ID">';
                    }else{
                        echo'<input type="submit" name="validateEdit2" value="Valider">',
                            '<input type="submit" name="cancel2" value="Annuler">';
                    }
    echo                '</td>',
                    '</tr>',
                '</table>',
            '</form>',
            '</div>',
            '<div class="statUser">',
                '<table>',
                    '<tr>',
                        '<td>',
                            'Nombre de repas pris :',
                        '</td>',
                        '<td>',
                            $nbRepas,
                        '</td>',
                    '</tr>',
                    '<tr>',
                        '<td>',
                            'Nombre de repas commentés :',
                        '</td>',
                        '<td>',
                            $nbRepasCom,
                        '</td>',
                    '</tr>',
                    '<tr>',
                        '<td>',
                            'Moyenne des notes :',
                        '</td>',
                        '<td>',
                            round(floatval($infoUser['moyNote']),1),
                        '</td>',
                    '</tr>',
                    '<tr>',
                        '<td>',
                            'Pourcentage de repas commentés :',
                        '</td>',
                        '<td>',
                            round($pourcentRepasCom, 1),'%',
                        '</td>',
                    '</tr>',
                    '<tr>',
                        '<td>',
                            'Apport énergétique moyen :',
                        '</td>',
                        '<td>',
                            $calories,'kCal',
                        '</td>',
                    '</tr>',
                    '<tr>',
                        '<td>',
                            'Empreinte carbone moyenne :',
                        '</td>',
                        '<td>',
                            $carbone,'kg eqCO2',
                        '</td>',
                    '</tr>',
                '</table>',
            '</div>',
        '</div>'
    ;

}

//_______________________________________________________________
/**
 * donne les informations de l'utilisateur
 * 
 * @return array    le tableau des infos
 */
function getInfoUser(){
    $info = [];
    $ID = $_SESSION['usID'];

    // ouverture de la connexion à la base de données
    $bd = bdConnect();

    // Récupération des plats qui sont proposés pour le menu (boissons incluses, divers exclus)
    $sql = "SELECT 
                   AVG(plCalories)                              as moyCalories, 
                   AVG(plCarbone)                               as moyCarbone, 
                   AVG(coNote)                                  as moyNote, 
                   COUNT(DISTINCT reDate)                       as nbRepas, 
                   COUNT(DISTINCT coDateRepas)                  as nbRepasCom, 
                   usPrenom, usNom, usDateNaissance, usMail, usLogin
            FROM usager 
                   LEFT JOIN repas ON usID = reUsager 
                   LEFT JOIN commentaire ON usID = coUsager 
                   LEFT JOIN plat ON rePlat = plID 
            WHERE usID=$ID;";

    // envoi de la requête SQL
    $res = bdSendRequest($bd, $sql);

    if (mysqli_num_rows($res) <= 0) {
        // libération des ressources
        mysqli_free_result($res);
        // fermeture de la connexion au serveur de base de  données
        mysqli_close($bd);
        return null; // ==> fin de la fonction bdMenuL()
    }

    // parcours des ressources :
    $tab = mysqli_fetch_assoc($res);
    foreach($tab as $key => $value){
        if($value != null){
            $info[$key] = htmlProtegerSorties($value);
        }else{
            $info[$key] = '0';
        }
    }

    // libération des ressources
    mysqli_free_result($res);
    // fermeture de la connexion au serveur de base de  données
    mysqli_close($bd);

    return $info;
}

function traitementUpdate(): array {
    
    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML sont considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sessionExit() */

    if( !parametresControle('post', ['nom', 'prenom', 'mail', 'validateEdit'])) {
        sessionExit();   
    }

    $erreurs = [];

    // vérification des noms et prénoms
    $expRegNomPrenom = '/^[[:alpha:]]([\' -]?[[:alpha:]]+)*$/u';
    $nom = $_POST['nom'] = trim($_POST['nom']);
    $prenom = $_POST['prenom'] = trim($_POST['prenom']);
    verifierTexte($nom, 'Le nom', $erreurs, LMAX_NOM, $expRegNomPrenom);
    verifierTexte($prenom, 'Le prénom', $erreurs, LMAX_PRENOM, $expRegNomPrenom);

    // vérification du format de l'adresse email
    $email = $_POST['mail'] = trim($_POST['mail']);
    verifierTexte($email, 'L\'adresse email', $erreurs, LMAX_EMAIL);
    // la validation faite par le navigateur en utilisant le type email pour l'élément HTML input
    // est moins forte que celle faite ci-dessous avec la fonction filter_var()
    // Exemple : 'l@i' passe la validation faite par le navigateur et ne passe pas
    // celle faite ci-dessous
    if(! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'L\'adresse email n\'est pas valide.';
    }

    // si erreurs --> retour
    if (count($erreurs) > 0) {
        return $erreurs;   //===> FIN DE LA FONCTION
    }

    // on vérifie si le login et l'adresse email ne sont pas encore utilisés que si tous les autres champs
    // sont valides car ces 2 dernières vérifications nécessitent une connexion au serveur de base de données
    // consommatrice de ressources système

    // ouverture de la connexion à la base 
    $bd = bdConnect();

    // protection des entrées
    $email = mysqli_real_escape_string($bd, $email);
    $nom = mysqli_real_escape_string($bd, $nom);
    $prenom = mysqli_real_escape_string($bd, $prenom);

    $sql = "UPDATE usager
            SET usNom='{$nom}',
                usPrenom='{$prenom}',
                usMail='{$email}'
            WHERE usID='{$_SESSION['usID']}';";
        
    bdSendRequest($bd, $sql);

    // fermeture de la connexion à la base de données
    mysqli_close($bd);
    unset($_POST['validateEdit']);
    header('Location: profil.php');
}

function traitementUpdate2(): array {
    
    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML sont considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sessionExit() */

    if( !parametresControle('post', ['oldLogin', 'login', 'passe1', 'passe2', 'oldPasse', 'validateEdit2'])) {
        sessionExit();   
    }

    $erreurs = [];

    // vérification du login
    $oldLogin = $_POST['oldLogin'] = trim($_POST['oldLogin']);

    if (!preg_match('/^[a-z][a-z0-9]{' . (LMIN_LOGIN - 1) . ',' .(LMAX_LOGIN - 1). '}$/u',$oldLogin)) {
        $erreurs[] = "Erreur dans le login";
    }

    // vérification du mot de passe
    $nb = mb_strlen($_POST['oldPasse'], encoding:'UTF-8');
    if ($nb < LMIN_PASSWORD || $nb > LMAX_PASSWORD){
        $erreurs[] = "Erreur dans le Mot de Passe";
    }


    // vérification du nlogin
    $login = $_POST['login'] = trim($_POST['login']);

    if (!preg_match('/^[a-z][a-z0-9]{' . (LMIN_LOGIN - 1) . ',' .(LMAX_LOGIN - 1). '}$/u',$login)) {
        $erreurs[] = 'Le login doit contenir entre '. LMIN_LOGIN .' et '. LMAX_LOGIN .
                    ' lettres minuscules sans accents, ou chiffres, et commencer par une lettre.';
    }
    // vérification des n mots de passe
    if ($_POST['passe1'] !== $_POST['passe2']) {
        $erreurs[] = 'Les mots de passe doivent être identiques.';
    }
    $nb = mb_strlen($_POST['passe1'], encoding:'UTF-8');
    if ($nb < LMIN_PASSWORD || $nb > LMAX_PASSWORD){
        $erreurs[] = 'Le mot de passe doit être constitué de '. LMIN_PASSWORD . ' à ' . LMAX_PASSWORD . ' caractères.';
    }

    // si erreurs --> retour
    if (count($erreurs) > 0) {
        return $erreurs;   //===> FIN DE LA FONCTION
    }

    // récupération du mot de passe
    $oldPasse = $_POST['oldPasse'];

    // ouverture de la connexion à la base 
    $bd = bdConnect();

    // protection des entrées
    $oldLogin2 = mysqli_real_escape_string($bd, $oldLogin); // fait par principe, mais inutile ici car on a déjà vérifié que le login
                                                      // ne contenait que des caractères alphanumériques
    $sql = "SELECT usLogin, usPasse FROM usager WHERE usLogin = '{$oldLogin2}'";
    $res = bdSendRequest($bd, $sql);

    while($tab = mysqli_fetch_assoc($res)) {
        if ($tab['usLogin'] == $oldLogin){
            if(!password_verify($oldPasse, $tab['usPasse'])){
                $erreurs[] = "Erreur dans la connexion";
            }
        } else {
            $erreurs[] = "Erreur dans la connexion";
        }
    }
    mysqli_free_result($res);

    // si erreurs --> retour
    if (count($erreurs) > 0) {
        return $erreurs;   //===> FIN DE LA FONCTION
    }

    $login2 = mysqli_real_escape_string($bd, $login);

    $sql = "SELECT usLogin FROM usager WHERE usLogin = '{$login2}'";

    // envoi de la requête SQL
    $res = bdSendRequest($bd, $sql);

    if (mysqli_num_rows($res) > 0) {
        $erreurs[] = "Le nouveau login est déjà utilisé";
    }

    // si erreurs --> retour
    if (count($erreurs) > 0) {
        return $erreurs;   //===> FIN DE LA FONCTION
    }

    //UPDATE dans la BD

    // calcul du hash du mot de passe pour enregistrement dans la base.
    $passe = password_hash($_POST['passe1'], PASSWORD_DEFAULT);

    $passe = mysqli_real_escape_string($bd, $passe);

    // les valeurs sont écrites en respectant l'ordre de création des champs dans la table usager
    $sql = "UPDATE usager
            SET usLogin='{$login2}', usPasse = '{$passe}'
            WHERE usID={$_SESSION['usID']}";
        
    bdSendRequest($bd, $sql);

    // fermeture de la connexion à la base de données
    mysqli_close($bd);
    unset($_POST['validateEdit2']);
    header('Location: profil.php');
}