<?php


$string['pluginname'] = 'Domoscio for Moodle';


$string['domscio:addinstance'] = 'Ajouter un quiz';
$string['domscio:view'] = 'View quiz information';
$string['domscio:viewreports'] = 'View quiz reports';
$string['domscio:attempt'] = 'Attempt adaptive quiz';
$string['domscio:manage'] = 'Manage a lesson activity';
$string['domscio:submit']= 'Submit to Domoscio API';
$string['domscio:view'] = 'View quiz information';



// settings page
$string['settings_id'] = 'Identifiant Domoscio';
$string['settings_id_helptext'] = 'Entrez votre identifiant API';
$string['settings_apikey'] = 'Clé API Domoscio';
$string['settings_apikey_helptext'] = 'Entrez votre clé API. Une clé API ressemble à ceci : "bku5t-N7P4n-rJs17-fkAsB-38Mqv".';
$string['settings_apiurl'] = 'URL API Domoscio';
$string['settings_apiurl_helptext'] = "Entrez l'URL API fournie";


//TEST
$string['modulename'] = 'Domoscio';
$string['modulenameplural'] = 'Domoscios';
$string['modulenamedomoscio_plural'] = 'Domoscios';
$string['modulename_help'] = "Consolidez vos savoirs à l'aide du plugin Domoscio for Moodle. <br/><br/>Associez le plugin à un module de cours,
 qu'ils soient issus du cours ou un paquet SCORM, ajoutez des questions issues des quiz de Moodle ou créez-en, ou bien encore des
 activités SCORM. Evaluez la compréhension de vos élèves à l'aide de statistiques.";
$string['domoscioresourceset'] = 'Lier à une ressource';
$string['resourceset_resource'] = 'Module de cours';
$string['domoscioresourceset_help'] = 'Le plugin a besoin de connaître à quel module de cours il fait référence pour fonctionner';
$string['domoscioname'] = 'domoscio name';
$string['domoscioname_help'] = 'This is the content of the help tooltip associated with the domoscioname field. Markdown syntax is supported.';
$string['domoscio'] = 'domoscio';
$string['pluginadministration'] = 'Admin Domoscio';
$string['num'] = 'N°';
$string['delete'] = 'Supprimer';
$string['edit'] = 'Modifier';
$string['welcome'] = 'Bienvenue, ';
$string['notions_empty'] = "Vous n'avez pas défini de notions. Veuillez sélectionner une ou des notions pour associer des questions.";
$string['notions_intro'] = "Choisir les notions à proposer pour l'ancrage : ";
$string['add_notion_btn'] = "Ajouter une notion";
$string['new_notion_intro'] = 'Créer une nouvelle notion';
$string['linkto_intro'] = "Sélectionnez, parmi les modules contenant des exercices, les questions qui seront liées à la notion : ";
$string['questions_assigned'] = 'Ce plugin propose les questions suivantes : ';
$string['resource_assigned'] = 'Le plugin est lié à la ressource suivante : ';
$string['def_notions'] = 'Définir des notions';
$string['set_notions'] = 'Les notions que vous avez définies :';
$string['add_notion_expl'] = 'Si vous ne trouvez pas la notion souhaitée parmi la liste ci-dessous, créez-en une :';
$string['whole_expl'] = 'Ancrer la ressource dans son intégralité (1 notion, 1 rappel) :';
$string['each_expl'] = 'ou choisir les notions une à une (n notion, n rappel) :';
$string['choose_q'] = 'Choisir des questions';
$string['create_q'] = 'Créer des questions';
$string['stats_adv'] = "Avancé";
$string['results'] = 'Résultats';
$string['question'] = "Question ";
$string['start_btn'] = 'Lancez-vous !';
$string['student'] = 'Etudiant';
$string['enrol_students'] = "Etudiants inscrits";
$string['avr_rate'] = "Réussite globale";
$string['upd_qlist'] = 'La liste des questions est mise à jour.';
$string['notion_created'] = 'La notion a bien été créée';
$string['student_first_visit'] = "C'est votre première visite sur le plugin Domoscio. Nous en avons profité pour créer votre profil
d'apprentissage. Cliquez sur le bouton ci-dessous pour continuer :";
$string['reviewed'] = 'Vous révisez la ressource suivante : ';
$string['notion_title'] = 'Titre : ';
$string['text2'] = ' rappel';
$string['text3'] = ' à faire';
$string['next_due_th'] = 'Prochain rappel';
$string['test_done'] = 'Tests effectués';
$string['test_todo'] = 'Tests à faire';
$string['test_succeeded'] = 'Tests réussis';
$string['test_failed'] = 'Tests manqués';
$string['next_due'] = 'Prochain rappel sur cette notion : ';
$string['no_history'] = "Vous ne vous êtes pas évalué sur cette notion : ";
$string['validate_btn'] = 'Valider';
$string['select_answer'] = 'Choisir la bonne réponse';
$string['answer'] = 'Réponse : ';
$string['choose'] = 'Choisir... ';
$string['correction'] = 'La bonne réponse est : ';
$string['desk'] = 'Mon organiseur';
$string['module'] = 'Ressource';
$string['global_module'] = 'Ressource entière';
$string['do_test'] = 'Passer le test';
$string['do_review_btn'] = 'Faire le rappel';
$string['do_training'] = "S'entrainer";
$string['test_session'] = "Evaluation";
$string['see_notion'] = 'Voir la notion';
$string['stats'] = 'Statistiques';
$string['tests_empty'] = "Aucune question n'a été définie par le créateur de cours pour cette notion.";
$string['start_tests'] = 'Commencer la session';
$string['no_test'] = "Vous n'avez pas de rappel";
$string['running_time'] = "Durée de la session : ";
$string['notion_ok'] = "Notion connue";
$string['notion_rvw'] = "Revoir la notion";
$string['next_btn'] = "Suivant";
$string['home_btn'] = "Accueil";
$string['back_btn'] = "Retour";
$string['end_btn'] = "J'ai fini";
$string['at'] = " à ";
$string['gottatest'] = "Vous avez un rappel à faire sur la ressource ";

$string['proxy_login_required'] = 'You must be logged in to access this url. Please log in and retry.';
$string['proxy_permission_required'] = 'You don\'t have the permission to access this url.';
$string['proxy_curl_missing'] = 'The CURL lib is missing from your PHP server. This lib is required to use the MediaServer plugin.';
$string['proxy_action_missing'] = 'Action not set in request arguments.';
$string['proxy_request_error'] = 'Error during request to MediaServer. Error:';
$string['proxy_parsing_error'] = 'Response from MediaServer cannot be parsed.';

?>
