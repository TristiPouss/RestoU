<?php
/* ------------------------------------------------------------------------------
    Architecture de la page
    - étape 1 : vérifications diverses et traitement des soumissions
    - étape 2 : génération du code HTML de la page
------------------------------------------------------------------------------*/

// chargement des bibliothèques de fonctions
require_once('bibli_erestou.php');
require_once('bibli_generale.php');

// bufferisation des sorties
ob_start();

// démarrage ou reprise de la session
session_start();

/*------------------------- Etape 1 --------------------------------------------
- vérifications diverses et traitement des soumissions
------------------------------------------------------------------------------*/

// si l'utilisateur est déjà authentifié
if (estAuthentifie()){
    // redirection vers la page précédente
    if(isset($_SESSION['back'])){
        header('Location: ' . $_SESSION['back']);
        exit();
    }
    header('Location: menu.php');
    exit();
}

// si formulaire soumis, traitement de la demande d'inscription
if (isset($_POST['btnConnexion'])) {
    $erreurs = traitementConnexionL(); // ne revient pas quand les données soumises sont valides
}
else{
    $erreurs = null;
}

/*------------------------- Etape 2 --------------------------------------------
- génération du code HTML de la page
------------------------------------------------------------------------------*/

// génération de la page
affEntete('Connexion');
affNav();

affFormulaireL($erreurs);

affPiedDePage();

// facultatif car fait automatiquement par PHP
ob_end_flush();

// ----------  Fonctions locales du script ----------- //

/**
 * Contenu de la page : affichage du formulaire de connexion
 *
 * En absence de soumission (i.e. lors du premier affichage), $err est égal à null
 * Quand l'inscription échoue, $err est un tableau de chaînes
 *
 * @param ?bool    $err    Booléen selon les erreurs en cas de soumission du formulaire, null lors du premier affichage
 *
 * @return void
 */
function affFormulaireL(?bool $err): void {
    // réaffichage des données soumises en cas d'erreur, sauf les mots de passe
    if (isset($_POST['btnConnexion'])){
        $values = htmlProtegerSorties($_POST);
    }
    else{
        $values['login'] = '';
    }

    echo
        '<section>',
            '<h3>Formulaire d\'authentification</h3>',
            '<p>Pour vous authentifier, merci de fournir les informations suivantes. </p>';

    if ($err) {
        echo '<div class="error">Echec d\'authentification. Utilisateur inconnu ou mot de passe incorrect.</div>';
    }


    echo
            '<form method="post" action="connexion.php">',
                '<table>';

    affLigneInput(  'Login :', array('type' => 'text', 'name' => 'login', 'value' => $values['login'],
                    'placeholder' => LMIN_LOGIN . ' à '. LMAX_LOGIN . ' lettres minuscules ou chiffres', 'required' => null));
    affLigneInput(  'Mot de passe :', array('type' => 'password', 'name' => 'passe', 'value' => '',
                    'placeholder' => LMIN_PASSWORD . ' caractères minimum', 'required' => null));
    echo
                    '<tr>',
                        '<td colspan="2">',
                            '<input type="submit" name="btnConnexion" value="Se connecter">',
                            '<input type="reset" value="Réinitialiser">',
                            '<input type="button" value="S\'inscrire" onclick="location.href=\'inscription.php\'">',
                        '</td>',
                    '</tr>',
                '</table>',
            '</form>',
        '</section>';
}


/**
 * Traitement d'une demande d'inscription
 *
 * Vérification de la validité des données
 * Si on trouve des erreurs => return un tableau les contenant
 * Sinon
 *     Connexion de l'utilisateur
 *     Ouverture de la session et redirection vers la page precedente
 * FinSi
 * Toutes les erreurs détectées qui nécessitent une modification du code HTML sont considérées comme des tentatives de piratage
 * et donc entraînent l'appel de la fonction em_sessionExit() sauf :
 * - les éventuelles suppressions des attributs required car l'attribut required est une nouveauté apparue dans la version HTML5 et
 *   nous souhaitons que l'application fonctionne également correctement sur les vieux navigateurs qui ne supportent pas encore HTML5
 * - une éventuelle modification de l'input de type date en input de type text car c'est ce que font les navigateurs qui ne supportent
 *   pas les input de type date
 *
 *  @return bool    un booléen vrai si il y a des erreurs
 */
function traitementConnexionL(): bool {
    
    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML sont considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sessionExit() */

    if(!parametresControle('post', ['login', 'passe', 'btnConnexion'])) {
        sessionExit();   
    }

    $erreurs = false;

    // vérification du login
    $login = $_POST['login'] = trim($_POST['login']);

    if (!preg_match('/^[a-z][a-z0-9]{' . (LMIN_LOGIN - 1) . ',' .(LMAX_LOGIN - 1). '}$/u',$login)) {
        $erreurs = true;
    }

    // vérification du mot de passe
    $nb = mb_strlen($_POST['passe'], encoding:'UTF-8');
    if ($nb < LMIN_PASSWORD || $nb > LMAX_PASSWORD){
        $erreurs = true;
    }

    // si erreurs --> retour
    if ($erreurs) {
        return $erreurs;   //===> FIN DE LA FONCTION
    }

    // récupération du mot de passe
    $passe = $_POST['passe'];

    // ouverture de la connexion à la base 
    $bd = bdConnect();

    // protection des entrées
    $login2 = mysqli_real_escape_string($bd, $login); // fait par principe, mais inutile ici car on a déjà vérifié que le login
                                                      // ne contenait que des caractères alphanumériques
    $sql = "SELECT usID, usLogin, usPasse FROM usager WHERE usLogin = '{$login2}'";
    $res = bdSendRequest($bd, $sql);

    while($tab = mysqli_fetch_assoc($res)) {
        if ($tab['usLogin'] == $login){
            if(password_verify($passe, $tab['usPasse'])){
                // mémorisation de l'ID dans une variable de session
                // cette variable de session permet de savoir si l'utilisateur est authentifié
                $_SESSION['usID'] = $tab['usID'];
            } else {
                $erreurs = true;
            }
        } else {
            $erreurs = true;
        }
    }

    mysqli_free_result($res);
    // fermeture de la connexion à la base de données
    mysqli_close($bd);

    // si erreurs --> retour
    if ($erreurs) {
        return $erreurs;   //===> FIN DE LA FONCTION
    }

    // mémorisation du login dans une variable de session (car affiché dans la barre de navigation sur toutes les pages)
    // enregistrement dans la variable de session du login avant passage par la fonction mysqli_real_escape_string()
    // car, d'une façon générale, celle-ci risque de rajouter des antislashs
    // Rappel : ici, elle ne rajoutera jamais d'antislash car le login ne peut contenir que des caractères alphanumériques
    $_SESSION['usLogin'] = $login;

    // redirection vers la page précédente
    if(isset($_SESSION['back'])){
        header('Location: ' . $_SESSION['back']);
        exit();
    }
    header('Location: menu.php');
    exit(); //===> Fin du script
}
