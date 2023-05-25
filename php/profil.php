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

// affichage de l'entête
affEntete('Profil');
// affichage de la barre de navigation
affNav();
// affichage du contenu
affContenuL($err);
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
function affContenuL(?array $err){
    $infoUser = getInfoUser();

    //Stats
    $nbRepas = intval($infoUser['nbRepas']);
    $nbRepasCom = intval($infoUser['nbRepasCom']);
    $pourcentRepasCom = 0;
    if($nbRepasCom != 0){ // evite la division par 0
        $pourcentRepasCom =  $nbRepas / $nbRepasCom * 100;
    }
    $calories = round($infoUser['moyCalories'], 1); 
    $carbone = round($infoUser['moyCarbone'], 1); 

    list($jour, $mois, $annee) = getJourMoisAnneeFromDate($infoUser['usDateNaissance']);
    $mois = moisStr($mois);

    $edit = isset($_POST['edit']);
    if(isset($_POST['cancel'])){
        $edit = false;
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
                   usPrenom, usNom, usDateNaissance, usMail 
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