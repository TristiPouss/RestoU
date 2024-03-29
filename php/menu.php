<?php

// chargement des bibliothèques de fonctions
require_once('bibli_erestou.php');
require_once('bibli_generale.php');

// bufferisation des sorties
ob_start();

// démarrage ou reprise de la session
session_start();

$_SESSION['back'] = $_SERVER['REQUEST_URI'];
$_SESSION['profil'] = false;

// affichage de l'entête
affEntete('Menus et repas');

// affichage de la barre de navigation
affNav();

// enregistrement de la commande si elle existe
$erreurs = null;
if(isset($_POST['btnCommander'])){
    $erreurs = verifCommande();
}

$erreursCom = null;
if(isset($_POST['validateCom'])){
    $erreursCom = verifCommentaire();
}

// contenu de la page 
affContenuL($erreurs);

// affichage du pied de page
affPiedDePage();

// fin du script --> envoi de la page 
ob_end_flush();


//_______________________________________________________________
/**
 * Vérifie la validité des paramètres reçus dans l'URL, renvoie la date affichée ou l'erreur détectée
 *
 * La date affichée est initialisée avec la date courante ou actuelle.
 * Les éventuels paramètres jour, mois, annee, reçus dans l'URL, permettent respectivement de modifier le jour, le mois, et l'année de la date affichée.
 *
 * @return int|string      string en cas d'erreur, int représentant la date affichée au format AAAAMMJJ sinon
 */
function dateConsulteeL() : int|string {
    if (!parametresControle('GET', [], ['jour', 'mois', 'annee'])){
        return 'Nom de paramètre invalide détecté dans l\'URL.';
    }

    // date d'aujourd'hui
    list($jour, $mois, $annee) = getJourMoisAnneeFromDate(DATE_AUJOURDHUI);

    // vérification si les valeurs des paramètres reçus sont des chaînes numériques entières
    foreach($_GET as $cle => $val){
        if (! estEntier($val)){
            return 'Valeur de paramètre non entière détectée dans l\'URL.';
        }
        // modification du jour, du mois ou de l'année de la date affichée
        $$cle = (int)$val;
    }

    if ($annee < 1000 || $annee > 9999){
        return 'La valeur de l\'année n\'est pas sur 4 chiffres.';
    }
    if (!checkdate($mois, $jour, $annee)) {
        return "La date demandée \"$jour/$mois/$annee\" n'existe pas.";
    }
    if ($annee < ANNEE_MIN){
        return 'L\'année doit être supérieure ou égale à '.ANNEE_MIN.'.';
    }
    if ($annee > ANNEE_MAX){
        return 'L\'année doit être inférieure ou égale à '.ANNEE_MAX.'.';
    }
    return $annee*10000 + $mois*100 + $jour;
}
//_______________________________________________________________
/**
 * Génération de la navigation entre les dates
 *
 * @param  int     $date   date affichée
 *
 * @return void
 */
function affNavigationDateL(int $date): void{
    list($jour, $mois, $annee) = getJourMoisAnneeFromDate($date);

    // on détermine le jour précédent (ni samedi, ni dimanche)
    $jj = 0;
    do {
        $jj--;
        $dateVeille = getdate(mktime(12, 0, 0, $mois, $jour+$jj, $annee));
    } while ($dateVeille['wday'] == 0 || $dateVeille['wday'] == 6);
    // on détermine le jour suivant (ni samedi, ni dimanche)
    $jj = 0;
    do {
        $jj++;
        $dateDemain = getdate(mktime(12, 0, 0, $mois, $jour+$jj, $annee));
    } while ($dateDemain['wday'] == 0 || $dateDemain['wday'] == 6);

    $dateJour = getdate(mktime(12, 0, 0, $mois, $jour, $annee));
    $jourSemaine = array('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi');

    // affichage de la navigation pour choisir le jour affiché
    echo '<h2>',
            $jourSemaine[$dateJour['wday']], ' ',
            $jour, ' ',
            getTableauMois()[$dateJour['mon']-1], ' ',
            $annee,
        '</h2>',

        // on utilise un formulaire qui renvoie sur la page courante avec une méthode GET pour faire apparaître les 3 paramètres sur l'URL
        '<form id="navDate" action="menu.php" method="GET">',
            '<a href="menu.php?jour=', $dateVeille['mday'], '&amp;mois=', $dateVeille['mon'], '&amp;annee=',  $dateVeille['year'], '">Jour précédent</a>',
            '<a href="menu.php?jour=', $dateDemain['mday'], '&amp;mois=', $dateDemain['mon'], '&amp;annee=', $dateDemain['year'], '">Jour suivant</a>',
            'Date : ';

    affListeNombre('jour', 1, 31, 1, $jour);
    affListeMois('mois', $mois);
    affListeNombre('annee', ANNEE_MIN, ANNEE_MAX, 1, $annee);

    echo    '<input type="submit" value="Consulter">',
        '</form>';
        // le bouton submit n'a pas d'attribut name. Par conséquent, il n'y a pas d'élément correspondant transmis dans l'URL lors de la soumission
        // du formulaire. Ainsi, l'URL de la page a toujours la même forme (http://..../php/menu.php?jour=7&mois=3&annee=2023) quel que soit le moyen
        // de navigation utilisé (formulaire avec bouton 'Consulter', ou lien 'précédent' ou 'suivant')
}

//_______________________________________________________________
/**
 * Récupération du menu de la date affichée
 *
 * @param int       $date           date affichée
 * @param array     $menu           menu de la date affichée (paramètre de sortie)
 *
 * @return bool                     true si le restoU est ouvert, false sinon
 */
function bdMenuL(int $date, array &$menu) : bool {

    // ouverture de la connexion à la base de données
    $bd = bdConnect();

    // Récupération des plats qui sont proposés pour le menu (boissons incluses, divers exclus)
    $sql = "SELECT plID, plNom, plCategorie, plCalories, plCarbone
            FROM plat LEFT JOIN menu ON (plID=mePlat AND meDate=$date)
            WHERE mePlat IS NOT NULL OR plCategorie = 'boisson'";

    // envoi de la requête SQL
    $res = bdSendRequest($bd, $sql);

    // Quand le resto U est fermé, la requête précédente renvoie tous les enregistrements de la table Plat de
    // catégorie boisson : il y en a NB_CAT_BOISSON
    if (mysqli_num_rows($res) <= NB_CAT_BOISSON) {
        // libération des ressources
        mysqli_free_result($res);
        // fermeture de la connexion au serveur de base de  données
        mysqli_close($bd);
        return false; // ==> fin de la fonction bdMenuL()
    }

    // tableau associatif contenant les constituants du menu : un élément par section
    $choix = array(  'entrees'           => array(),
                    'plats'             => array(),
                    'accompagnements'   => array(),
                    'desserts'          => array(),
                    'boissons'          => array()
                );

    // parcours des ressources :
    while ($tab = mysqli_fetch_assoc($res)) {
        switch ($tab['plCategorie']) {
            case 'entree':
                $menu['entrees'][] = $tab;
                break;
            case 'viande':
            case 'poisson':
                $menu['plats'][] = $tab;
                break;
            case 'accompagnement':
                $menu['accompagnements'][] = $tab;
                break;
            case 'dessert':
            case 'fromage':
                $menu['desserts'][] = $tab;
                break;
            default:
                $menu['boissons'][] = $tab;
        }
    }
    // libération des ressources
    mysqli_free_result($res);
    // fermeture de la connexion au serveur de base de  données
    mysqli_close($bd);
    return true;
}
//_______________________________________________________________
/**
 * Récupération du choix de la date affichée
 *
 * @param int       $date           date affichée
 * @param array     $choix          choix effectué de la date affichée
 * 
 * @return bool                     true si un choix a été fait, false sinon
 */
function getChoixUser(int $date, array &$choix) : bool {
    if(!isset($_SESSION['usID'])){
        return false;
    }
    
    $userID = $_SESSION['usID'];

    // ouverture de la connexion à la base de données
    $bd = bdConnect();

    // Récupération des plats qui sont proposés pour le menu (boissons incluses, divers exclus)
    $sql = "SELECT rePlat, plNom, plCategorie
            FROM repas INNER JOIN plat ON (plID=rePlat AND reDate=$date AND reUsager=$userID) WHERE plCategorie != 'divers'";

    // envoi de la requête SQL
    $res = bdSendRequest($bd, $sql);

    // Pas de repas commandé à cette date
    if (mysqli_num_rows($res) <= 0) {
        // libération des ressources
        mysqli_free_result($res);
        // fermeture de la connexion au serveur de base de  données
        mysqli_close($bd);
        return false; // ==> fin de la fonction bdMenuL()
    }


    // tableau associatif contenant les constituants du menu : un élément par section
    $choix = array( 'entrees'           => array(),
                    'plats'             => array(),
                    'accompagnements'   => array(),
                    'desserts'          => array(),
                    'boissons'          => array()
                );

    // parcours des ressources :
    while ($tab = mysqli_fetch_assoc($res)) {
        switch ($tab['plCategorie']) {
            case 'entree':
                $choix['entrees'] = $tab['rePlat'];
                break;
            case 'viande':
            case 'poisson':
                $choix['plats'] = $tab['rePlat'];
                break;
            case 'accompagnement':
                $choix['accompagnements'][] = $tab['rePlat'];
                break;
            case 'dessert':
            case 'fromage':
                $choix['desserts'] = $tab['rePlat'];
                break;
            default:
                $choix['boissons'] = $tab['rePlat'];
        }
    }
    // libération des ressources
    mysqli_free_result($res);
    // fermeture de la connexion au serveur de base de  données
    mysqli_close($bd);
    return true;
}

//_______________________________________________________________
/**
 * Affichage d'un des constituants du menu.
 *
 * @param  array       $p      tableau associatif contenant les informations du plat en cours d'affichage
 * @param  string      $catAff catégorie d'affichage du plat
 *
 * @return void
 */
function affPlatL(array $p, string $catAff): void {
    if ($catAff != 'accompagnements'){ //radio bouton
        $name = "rad$catAff";
        $id = "{$name}{$p['plID']}";
        $type = 'radio';
    }
    else{ //checkbox
        $id = $name = "cb{$p['plID']}";
        $type = 'checkbox';
    }

    // protection des sorties contre les attaques XSS
    $p['plNom'] = htmlProtegerSorties($p['plNom']);

    echo    '<input id="', $id, '" name="', $name, '" type="', $type, '" value="', $p['plID'], '" disabled>',
            '<label for="', $id,'">',
                '<img src="../images/repas/', $p['plID'], '.jpg" alt="', $p['plNom'], '" title="', $p['plNom'], '">',
                $p['plNom'], '<br>', '<span>', $p['plCarbone'],'kg eqCO2 / ', $p['plCalories'], 'kcal</span>',
            '</label>';

}

//_______________________________________________________________
/**
 * Affichage d'un des constituants du menu.
 *
 * @param  array       $p      tableau associatif contenant les informations du plat en cours d'affichage
 * @param  array       $choix  tableau des choix de l'utilisateur
 * @param  string      $catAff catégorie d'affichage du plat
 *
 * @return void
 */
function affPlatLChecked(array $p, array $choix,string $catAff): void {
    if ($catAff != 'accompagnements'){ //radio bouton
        $name = "rad$catAff";
        $id = "{$name}{$p['plID']}";
        $type = 'radio';
    }
    else{ //checkbox
        $id = $name = "cb{$p['plID']}";
        $type = 'checkbox';
    }
    // protection des sorties contre les attaques XSS    
    $p['plNom'] = htmlProtegerSorties($p['plNom']);

    if($catAff != 'accompagnements'){
        $checked = ($choix[$catAff] == $p['plID']) ? "checked" : "";
    } else {
        $isChecked = false;
        for($i = 0; $i<sizeof($choix[$catAff]); $i++){
            if($choix[$catAff][$i] == $p['plID']){
                $isChecked = true;
            }
        }
        $checked = ($isChecked) ? "checked" : "";
    }


    echo    '<input id="', $id, '" name="', $name, '" type="', $type, '" value="', $p['plID'], '" disabled ',$checked,'>',
            '<label for="', $id,'">',
                '<img src="../images/repas/', $p['plID'], '.jpg" alt="', $p['plNom'], '" title="', $p['plNom'], '">',
                $p['plNom'], '<br>', '<span>', $p['plCarbone'],'kg eqCO2 / ', $p['plCalories'], 'kcal</span>',
            '</label>';

}

//_______________________________________________________________
/**
 * Affichage d'un des constituants du menu non désactivé.
 *
 * @param  array       $p      tableau associatif contenant les informations du plat en cours d'affichage
 * @param  string      $catAff catégorie d'affichage du plat
 *
 * @return void
 */
function affPlatFormL(array $p, string $catAff): void {
    if ($catAff != 'accompagnements'){ //radio bouton
        $name = "rad$catAff";
        $id = "{$name}{$p['plID']}";
        $type = 'radio';
    }
    else{ //checkbox
        $id = $name = "cb{$p['plID']}";
        $type = 'checkbox';
    }

    // protection des sorties contre les attaques XSS
    $p['plNom'] = htmlProtegerSorties($p['plNom']);

    $checked = (isset($_POST[$name]) && $_POST[$name] == $p['plID']) ? "checked" : "";

    echo    '<input id="', $id, '" name="', $name, '" type="', $type, '" value="', $p['plID'], '" ',$checked,'>',
            '<label for="', $id,'">',
                '<img src="../images/repas/', $p['plID'], '.jpg" alt="', $p['plNom'], '" title="', $p['plNom'], '">',
                $p['plNom'], '<br>', '<span>', $p['plCarbone'],'kg eqCO2 / ', $p['plCalories'], 'kcal</span>',
            '</label>';

}

//_______________________________________________________________
/**
 * Génère le contenu de la page.
 * 
 * @param array         $erreurs    les erreurs dans la commande, 
 *                                  null si aucune erreur
 *
 * @return void
 */
function affContenuL(?array $erreurs): void {

    $date = dateConsulteeL();
    // si dateConsulteeL() renvoie une erreur
    if (is_string($date)){
        echo    '<h4 class="center nomargin">Erreur</h4>',
                '<p>', $date, '</p>',
                (strpos($date, 'URL') !== false) ?
                '<p>Il faut utiliser une URL de la forme :<br>http://..../php/menu.php?jour=7&mois=3&annee=2023</p>':'';
        return; // ==> fin de la fonction affContenuL()
    }
    // si on arrive à ce point de l'exécution, alors la date est valide
    
    if(isset($_POST['deleteCom'])){
        delCom($date);
    }

    $erreursCom = null;
    if(isset($_POST['validateAdd'])){
        $erreursCom = traitementAddCom($date);
    }

    if(isset($_POST['validateEdit'])){
        $erreursCom = traitementEditCom($date);
    }

    // Génération de la navigation entre les dates 
    affNavigationDateL($date);

    if(isset($_SESSION['valide']) && $_SESSION['valide']){
        echo '<div class="valid">La commande à été enregistrée avec succès.</div>';
        unset($_SESSION['valide']);
    }
    if(isset($_POST['btnCommander']) && empty($erreurs)){
        addCommande();
    }

    // menu du jour
    $menu = [];

    // choix du jour
    $choix = [];

    $restoOuvert = bdMenuL($date, $menu);

    if (! $restoOuvert){
        echo '<p>Aucun repas n\'est servi ce jour.</p>';
        return; // ==> fin de la fonction affContenuL()
    }
    
    $choixFait = getChoixUser($date,$choix);

    // titre h3 des sections à afficher
    $h3 = array('entrees'           => 'Entrée',
                'plats'             => 'Plat', 
                'accompagnements'   => 'Accompagnement(s)',
                'desserts'          => 'Fromage/dessert', 
                'boissons'          => 'Boisson'
                );
    
    // affichage du menu
    $compdate = compareDate($date,DATE_AUJOURDHUI);
    $isForm = estAuthentifie() && !$choixFait && $compdate == 0;

    if($isForm){
        echo 
            '<p class="notice">',
                '<img src="../images/notice.png" alt="notice" width="50" height="48">',
                'Tous les plateaux sont composés avec un verre, un couteau, une fouchette et une petite cuillère.',
            '</p>',
            '<form method="POST" action="menu.php">'
        ;

        if (is_array($erreurs) && !empty($erreurs)) {
            echo    '<div class="error">Les erreurs suivantes ont été relevées lors de votre inscription :',
                        '<ul>';
            foreach ($erreurs as $e) {
                echo        '<li>', $e, '</li>';
            }
            echo        '</ul>',
                    '</div>';
        }
    }
    $i = 0;
    foreach($menu as $key => $value){
        echo '<section class="bcChoix"><h3>', $h3[$key], '</h3>';
        if (estAuthentifie() && $choixFait){
            foreach ($value as $p){
                affPlatLChecked($p, $choix, $key, $i);
            }
        }
        else if($isForm){
            affChoixNull($key);
            foreach($value as $p){
                affPlatFormL($p, $key);
            }
        }
        else{
            foreach ($value as $p) {
                affPlatL($p, $key);
            }
        }
        echo '</section>';
    }

    if($isForm){
        affSupplEtBoutons();
        echo '</form>';
    }

    // affichage des commentaires
    if($compdate < 1){
        if (is_array($erreursCom) && !empty($erreursCom)) {
            echo    '<div class="error">Les erreurs suivantes ont été relevées lors de votre inscription :',
                        '<ul>';
            foreach ($erreursCom as $e) {
                echo        '<li>', $e, '</li>';
            }
            echo        '</ul>',
                    '</div>';
        }
        affComs(getCom($date), getMoy($date), $choixFait);
    }
}

//_______________________________________________________________
/**
 * Insère dans la base de donnees la commande passée par l'utilisateur
 * 
 * @return array    le tableau d'erreurs si ily en a 
 */
function verifCommande(): array {
    $_SESSION['noTentative']++;

    $errs = [];

    $date = dateConsulteeL();
    $menu = [];
    bdMenuL($date, $menu); 

    $paramObligatoires = [];
    $ctrlOK = false;

    foreach($menu['accompagnements'] as $acc){
        if(isset($_POST["cb".$acc['plID']])){
            array_push($paramObligatoires, "cb".$acc['plID']);
            $ctrlOK = true;
        }
    }

    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML sont considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sessionExit() */
    array_push($paramObligatoires, 'radboissons', 'nbPains', 'nbServiettes', 'btnCommander', 'radentrees', 'radplats', 'raddesserts');
    if($_SESSION['noTentative'] > 2 && (!$ctrlOK || !parametresControle('post', $paramObligatoires))) {
        sessionExit();   
    }

    if(!isset($_POST['radentrees'])){
        array_push($errs, "Aucune entrée choisie");
    }
    if(!isset($_POST['radplats'])){
        array_push($errs, "Aucun plat choisi");
    }
    if(!$ctrlOK){
        array_push($errs, "Aucun accompagnement choisi");
    }
    if(!isset($_POST['radboissons'])){
        array_push($errs, "Aucune boisson choisie");
    }
    if(!isset($_POST['raddesserts'])){
        array_push($errs, "Aucun fromage/dessert choisi");
    }
    if($_POST['nbPains']<0 || $_POST['nbPains']>2){
        array_push($errs, "Erreur dans le nombre de pains");
    }
    if($_POST['nbServiettes']<1 || $_POST['nbServiettes']>5){
        array_push($errs, "Erreur dans le nombre de serviettes");
    }

    return $errs;
}

//_______________________________________________________________
/**
 * Insère dans la base de donnees la commande passée par l'utilisateur
 *
 * @return void
 */
function addCommande() {
    $ID = $_SESSION['usID'];
    $date = DATE_AUJOURDHUI;

    $noAccomp = 0;
    foreach($_POST as $cle => $valeur){
        if(preg_match('/^cb[0-9]+$/', $cle)){
            $noAccomp++;
        }
    }

    // ouverture de la connexion à la base 
    $bd = bdConnect();
    
    foreach($_POST as $cle => $valeur){
        if($valeur == "aucune" || $cle == "btnCommander"){
            continue;
        }

        $nbPortions = 1.0;
        if($_POST['radplats'] == "aucune" && preg_match('/^cb[0-9]+$/', $cle)){ 
            // les accompagnements on une portion de 1,5 si aucun plat choisi
            $nbPortions = 1.5 / $noAccomp;
        }
        if($cle == "nbPains"){
            $valeur = "38";
            $nbPortions = floatval($_POST['nbPains']);
        }
        if($cle == "nbServiettes"){
            $valeur = "39";
            $nbPortions = floatval($_POST['nbServiettes']);
        }

        if($nbPortions == 0){
            continue;
        }
        
        $valeur_p = mysqli_real_escape_string($bd, $valeur);

        $sql = "INSERT INTO repas (reDate, rePLat, reUsager, reNbPortions)
                VALUES ('$date', $valeur_p, $ID, $nbPortions)";

        $res = bdSendRequest($bd, $sql);
    }

    // fermeture de la connexion à la base de données
    mysqli_close($bd);

    $_POST = [];
    $_SESSION['valide'] = true;
    header("refresh: 0");
}

//_______________________________________________________________
/**
 * Génère le bouton pour ne pas choisir de plat, d'entree etc...
 *
 * @param string    $curr      la categorie d'affichage actuelle
 * 
 * @return void
 */
function affChoixNull(string $curr){
    if($curr == "accompagnements" || $curr == "boissons"){
        return;
    }

    $checked = (isset($_POST["rad$curr"]) && $_POST["rad$curr"] == "0") ? "checked" : "";

    echo 
        '<input id="rad', $curr, '" name="rad', $curr, '" type="radio" value="aucune" ', $checked,'>',
        '<label for="rad', $curr, '">'
    ;
    switch ($curr){
        case 'entrees':
            echo '<img src="../images/repas/0.jpg" alt="Pas d\'entrée" title="Pas d\'entrée">Pas d\'entrée';
            break;
        case 'plats':
            echo '<img src="../images/repas/0.jpg" alt="Pas de plat" title="Pas de plat">Pas de plat';
            break;
        case 'desserts':
            echo '<img src="../images/repas/0.jpg" alt="Pas de fromage/dessert" title="Pas de fromage/dessert">Pas de fromage/dessert';
            break;
    }
    echo '</label>';
}

//_______________________________________________________________
/**
 * Génère les suppléments et les boutons d'envoi et de reset
 *
 * @return void
 */
function affSupplEtBoutons(){
    $nbPains = (isset($_POST['btnCommander'])) ? $_POST['nbPains'] : "0";
    $nbServiettes = (isset($_POST['btnCommander'])) ? $_POST['nbServiettes'] : "1";

    echo 
        '<section class="bcChoix">',
            '<h3>Suppléments</h3>',
            '<label>',
                '<img src="../images/repas/38.jpg" alt="Pain" title="Pain">Pain',
                '<input type="number" min="0" max="2" name="nbPains" value="', $nbPains,'">',
            '</label>',
            '<label>',
                '<img src="../images/repas/39.jpg" alt="Serviette en papier" title="Serviette en papier">Serviette en papier',
                '<input type="number" min="1" max="5" name="nbServiettes" value="', $nbServiettes,'">',
            '</label>',
        '</section>',
        '<section>',
            '<h3>Validation</h3>',
                '<p class="attention">',
                    '<img src="../images/attention.png" alt="attention" width="50" height="50">',
                    'Attention, une fois la commande réalisée, il n\'est pas possible de la modifier.<br>',
                    'Toute commande non-récupérée sera majorée d\'une somme forfaitaire de 10 euros.',
                '</p>',
                '<p class="center">',
                    '<input type="submit" name="btnCommander" value="Commander">',
                    '<input type="reset" name="btnAnnuler" value="Annuler">',
                '</p>',
        '</section>'
    ;
}

//_______________________________________________________________
/**
 * Génère un commentaire.
 * 
 * @param ID        l'Identifiant de la personne ayant commenté
 * @param dateRepas la date du repas
 * @param com       la chaine du commentaire deja protegee
 * @param nom       le nom de l'usager ayant commenté
 * @param prenom    le prenom de l'usager ayant commenté
 * @param date      la date formatté de la publication du commentaire
 * @param note      la note sur cinq attribuée au commentaire 
 *
 * @return void
 */
function affCom(string $ID, string $dateRepas, string $com, string $nom, string $prenom, string $date, string $note): void {
    $src = "../upload/{$dateRepas}_{$ID}.jpg";

    list($jour, $mois, $annee) = getJourMoisAnneeFromDate($dateRepas);
    $min = substr($date, -2);
    $heure = substr($date, -4, -2);

    if(isset($_SESSION['usID']) && $_SESSION['usID']==$ID){
        $edit = isset($_POST['editCom']);
        if(isset($_POST['cancelEdit'])){
            $edit = false;
        }
        //Bouton d'edition
        echo '<form id="editCom" method="POST" action="',$_SERVER['REQUEST_URI'],'#editCom">';
        if($edit){
            // Formulaire d'edition de commentaire
            echo '<label for="com">Commentaire : </label>',
                 '<br>',
                 '<textarea id="com" cols = "50" rows = "5" name = "com">',$com,'</textarea>',
                 '<br>',
                 '<label for="note">Note : </label>',
            affListeNombre("note", 0, 5, 1, $note);
            echo '<label for="image">Image : </label>',
                 '<input type="file", name="image" accept="image/*,.jpg"';
            echo '<br><br>',
                '<a href="#goModal" class="bout">Valider</a>',
                '<div id="goModal">',
                    '<div id="modal_content">',
                        '<p>Editer le commentaire ?</p>',
                        '<input type="submit" name="validateEdit" value="Valider">',
                        '<a href="#editCom" class="bout">Retour</a>',
                    '</div>',
                '</div>',
                '<input type="submit" name="cancelEdit" value="Annuler">';
        }else{
            echo '<input type="submit" name="editCom" value="Editer">',
                '<a href="#goModal" class="bout">Supprimer</a>',
                '<div id="goModal">',
                    '<div id="modal_content">',
                        '<p>Supprimer le commentaire ?</p>',
                        '<input type="submit" name="deleteCom" value="Supprimer">',
                        '<a href="#editCom" class="bout">Retour</a>',
                    '</div>',
                '</div>';
        }
        echo '</form>';
    }

    echo
    '<article>';
    if(file_exists($src)){
        echo '<img alt="screenshotRepas" src="'.$src.'">';
    }
    echo
        '<p><strong>Commentaire de '.$prenom.' '.$nom.', publié le '.$jour.' '.moisStr($mois).' '.$annee.' à '.$heure.':'.$min.'</strong><p>',
        '<p class="commentaire">'.$com.'</p>',
        '<p>Note : '.$note.' / 5</p>',
    '</article>'
    ;
}

//_______________________________________________________________
/**
 * Génère tous les commentaires.
 * 
 * @param array $liCom        liste des commentaires sous la forme
 *                            d'un tableau de tableaux de commentaires 
 *                            Exemple :
 *                            $liCom[0] = {
 *                             'com' = le commentaire,
 *                             'nom' = le nom
 *                              etc... (cf fonction affCom)             
 *                            }
 * @param float $moyenne      moyenne des commentaires
 * @param bool  $choixFait    indique si l'utilisateur a passé commande  
 *
 * @return void
 */
function affComs(array $liCom, float $moyenne, bool $choixFait): void {
    $size = sizeof($liCom);

    $afficherAddComButton = $choixFait;
    foreach($liCom as $commentaire){
        if(isset($_SESSION['usID']) && $commentaire['ID'] == $_SESSION['usID']){
            $afficherAddComButton = false;
            break;
        }
    }
    
    if (floor($moyenne) == $moyenne){
        $moyenne = number_format($moyenne, 0);
    }else{
        $moyenne = number_format($moyenne, 1, ',');
    }

    echo 
        '<div id="com" class="Commentaires">',
        '<h4>Commentaires sur ce menu</h4>',
        '<br>';
    if($size > 0){
        echo '<p>Note moyenne de ce menu : '.$moyenne.' / 5 sur la base de '.$size.' commentaire';
        if($size > 1){echo 's';}
        echo '</p>';
    }
    //Ajout de commentaire    
    if($afficherAddComButton){
        $add = isset($_POST['addCom']);
        if(isset($_POST['cancelAdd'])){
            $add = false;
        }
        //Bouton d'ajout
        echo '<form id="addCom" method="POST" action="',$_SERVER['REQUEST_URI'],'#addCom">';
        if($add){
            echo '<label for="com">Commentaire : </label>',
                 '<br>',
                 '<textarea id="com" cols = "50" rows = "5" name = "com"></textarea>',
                 '<br>',
                 '<label for="note">Note : </label>',
            affListeNombre("note", 0, 5, 1, 0);
            echo '<label for="image">Image : </label>',
                 '<input type="file", name="image" accept="image/*,.jpg"';
            echo '<br><br>',
                '<a href="#goModal" class="bout">Valider</a>',
                '<div id="goModal">',
                    '<div id="modal_content">',
                        '<p>Ajouter le commentaire ?</p>',
                        '<input type="submit" name="validateAdd" value="Valider">',
                        '<a href="#editCom" class="bout">Retour</a>',
                    '</div>',
                '</div>',
                '<input type="submit" name="cancelAdd" value="Annuler">';                
        }else{
            echo '<input type="submit" name="addCom" value="Ajouter com.">';
        }
        echo '</form>';
    }

    if($size > 0){
        foreach($liCom as $commentaire){
            // on protege seulement le texte du commentaire, 
            // le reste a soit deja ete verifie soit est issu de la BD
            affCom($commentaire['ID'], 
                   $commentaire['dateRepas'], 
                   htmlspecialchars($commentaire['com'], ENT_QUOTES, 'UTF-8'), 
                   $commentaire['nom'], 
                   $commentaire['prenom'], 
                   $commentaire['date'], 
                   $commentaire['note']
            );
        }
    } else {
        echo '<p>Aucun commentaire pour l\'instant.</i></p>';
    }
    echo '</div>';
}

//_______________________________________________________________
/**
 * Récupère tous les commentaires dans la base de données.
 * 
 * @param date      la date actuelle sous forme d'entier au format AAAAMMJJ
 * 
 * @return array    la liste des commentaires 
 */
function getCom(int $date): array {
    $liCom = array();

    // ouverture de la connexion à la base 
    $bd = bdConnect();

    $sql = "SELECT usID, coDateRepas, coTexte, coDatePublication, coNote, usNom, usPrenom
            FROM commentaire INNER JOIN usager ON coUsager = usID 
            WHERE coDateRepas = '{$date}' ORDER BY coDatePublication DESC";
    $res = bdSendRequest($bd, $sql);

    while($tab = mysqli_fetch_assoc($res)) {
        $commentaire = array();

        $commentaire['ID'] = $tab['usID'];
        $commentaire['dateRepas'] = $tab['coDateRepas'];
        $commentaire['com'] = $tab['coTexte']; 
        $commentaire['date'] = $tab['coDatePublication'];
        $commentaire['note'] = $tab['coNote'];
        $commentaire['nom'] = $tab['usNom'];
        $commentaire['prenom'] = $tab['usPrenom'];

        array_push($liCom, $commentaire);
    }
    mysqli_free_result($res);

    // fermeture de la connexion à la base de données
    mysqli_close($bd);

    return $liCom;
}

//_______________________________________________________________
/**
 * Récupère la moyenne des commentaires d'un jour donné.
 * 
 * @param date      la date actuelle sous forme d'entier au format AAAAMMJJ
 * 
 * @return float    la moyenne (0 si aucun commentaires (ou si la moyenne est vraiment 0))
 */
function getMoy(int $date): float {
    $moy = 0.0;

    // ouverture de la connexion à la base 
    $bd = bdConnect();

    $sql = "SELECT AVG(coNote) AS moy
            FROM commentaire WHERE coDateRepas = '{$date}'";
    $res = bdSendRequest($bd, $sql);

    $tab = mysqli_fetch_assoc($res);
    if($tab['moy'] != null){
        $moy = $tab['moy'];
    }
    mysqli_free_result($res);

    // fermeture de la connexion à la base de données
    mysqli_close($bd);

    return $moy;
}

//_______________________________________________________________
/**
 * Supprime un commentaire
 * 
 * @param int   $date      la date du commentaire sous forme 
 *                         d'entier au format AAAAMMJJ
 * 
 */
function delCom(int $date) {
    // ouverture de la connexion à la base 
    $bd = bdConnect();

    $sql = "DELETE FROM commentaire
            WHERE coDateRepas = '{$date}' AND 
                  coUsager = '{$_SESSION['usID']}'";
    $res = bdSendRequest($bd, $sql);

    // fermeture de la connexion à la base de données
    mysqli_close($bd);

    header('Location: '.$_SERVER['REQUEST_URI'].'#com');
}

//_______________________________________________________________
/**
 * Ajoute un commentaire
 * 
 * @param string     $date      la date du commentaire, format AAAAMMJJ
 * @param string     $texte     le texte du commentaire
 * @param string     $note      la note sur cinq donnée au repas
 * 
 */
function addCom(string $date, string $texte, string $note) {
    // ouverture de la connexion à la base 
    $bd = bdConnect();

    // proteger entrees
    $texte = mysqli_real_escape_string($bd, $texte);
    $note = mysqli_real_escape_string($bd, $note);
    $datePubli = date('YmdHi');

    $sql = "INSERT INTO commentaire 
            VALUES ('{$date}', '{$_SESSION['usID']}', '{$texte}', '{$datePubli}', '{$note}')";
    $res = bdSendRequest($bd, $sql);

    // fermeture de la connexion à la base de données
    mysqli_close($bd);

    header('Location: '.$_SERVER['REQUEST_URI'].'#com');
}

//_______________________________________________________________
/**
 * Modifie un commentaire
 * 
 * @param int        $date      la date du commentaire sous forme d'entier au format AAAAMMJJ
 * @param string     $texte     le texte du commentaire
 * @param string     $note      la note sur cinq donnée au repas
 * 
 */
function updateCom(int $date, string $texte, string $note) {
    // ouverture de la connexion à la base 
    $bd = bdConnect();

    // proteger entrees
    $texte = mysqli_real_escape_string($bd, $texte);
    $note = mysqli_real_escape_string($bd, $note);
    $datePubli = date('YmdHi');

    var_dump($texte, $note, $datePubli, $date);

    $sql = "UPDATE commentaire 
            SET coTexte = '{$texte}', coDatePublication = '{$datePubli}', coNote = '{$note}'
            WHERE coDateRepas = '{$date}' AND coUsager = '{$_SESSION['usID']}';";
    $res = bdSendRequest($bd, $sql);

    // fermeture de la connexion à la base de données
    mysqli_close($bd);

    header('Location: '.$_SERVER['REQUEST_URI'].'#com');
}

function traitementAddCom(int $date) : array {
    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML sont considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sessionExit() */

    if( !parametresControle('post', ['com', 'note', 'validateAdd'], ['image'])) {
        sessionExit();   
    }

    $errs = [];

    $com = trim($_POST['com']);
    
    if(strlen($com)==0){
        $errs[] = "Du texte doit être renseigné";
    }
    verifierTexte($com, 'Le commentaire', $errs, 1000);

    $note = intval($_POST['note']);
    if($note<0 || $note>5){
        sessionExit(); // Valeur impossible en théorie;
    }

    if(!empty($errs)){
        return $errs;
    }

    addCom($date, $com, $note);
}

function traitementEditCom(int $date) : array {
    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML sont considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sessionExit() */

    if( !parametresControle('post', ['com', 'note', 'validateEdit'], ['image'])) {
        sessionExit();   
    }

    $errs = [];

    $com = trim($_POST['com']);
    
    if(strlen($com)==0){
        $errs[] = "Du texte doit être renseigné";
    }
    verifierTexte($com, 'Le commentaire', $errs, 1000);

    $note = intval($_POST['note']);
    if($note<0 || $note>5){
        sessionExit(); // Valeur impossible en théorie;
    }

    if(!empty($errs)){
        return $errs;
    }

    updateCom($date, $com, $note);
}