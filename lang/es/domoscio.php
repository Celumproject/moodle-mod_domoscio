<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Domoscio plugin language strings FRENCH
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Domoscio for Moodle';
$string['domoscio:addinstance'] = 'Agregar un test';
$string['domoscio:view'] = 'Ver información sobre el test';
$string['domoscio:viewreport'] = 'Ver analíticas sobre el test';
$string['domoscio:attempt'] = 'Iniciar un test adaptativo';
$string['domoscio:manage'] = 'Gestionar un curso';
$string['domoscio:submit'] = 'Enviar a la API de Domoscio';
$string['domoscio:view'] = 'Ver información sobre el test';
$string['domoscio:savetrack'] = "Guardar los resultados de los ejercicios SCORM";

// Settings page
$string['settings_id'] = 'Nombre de usuario Domoscio';
$string['settings_id_helptext'] = 'Ingrese su nombre de usuario API';
$string['settings_apikey'] = 'Clave API Domoscio';
$string['settings_apikey_helptext'] = 'Ingrese su clave API. Una clave API se presenta de esta forma: "bku5t-N7P4n-rJs17-fkAsB-38Mqv".';
$string['settings_apiurl'] = 'URL API Domoscio';
$string['settings_apiurl_helptext'] = "Ingrese la URL API proporcionada";

// TEST
$string['modulename'] = 'Domoscio';
$string['modulenameplural'] = 'Domoscios';
$string['modulenamedomoscio_plural'] = 'Domoscios';
$string['modulename_help'] = "Consolide sus conocimientos con el plugin Domoscio for Moodle. <br/><br/>Asocie el plugin a un módulo de aprendizaje,
 que sea procedente de un curso o un paquete SCORM, agregue preguntas procedentes de los tests Moodle o cree uno, o
 actividades SCORM. Evalúe la comprensión de sus alumnos con estadísticas.";
$string['domoscioresourceset'] = 'Asociar a un recurso';
$string['resourceset_resource'] = 'Módulo de aprendizaje';
$string['domoscioresourceset_help'] = 'El plugin necesita saber qué módulo de aprendizaje está asociado para funcionar';
$string['domoscioname'] = 'nombre domoscio';
$string['domoscioname_help'] = 'Aquí está el contenido de la herramienta de ayuda asociada al campo nombre domoscio. La sintaxis de Markdown está soportada';
$string['domoscio'] = 'domoscio';
$string['pluginadministration'] = 'Admin Domoscio';
$string['warning'] = 'Advertencia';
$string['num'] = 'N°';
$string['delete'] = 'Eliminar';
$string['edit'] = 'Editar';
$string['welcome'] = 'Bienvenido/a, ';
$string['notions_empty'] = "No ha determinado nociones. Seleccione una o varias nociones para asociar preguntas.";
$string['notions_intro'] = "Elegir las nociones para el anclaje: ";
$string['add_notion_btn'] = "Agregar una noción";
$string['newnotion_intro'] = 'Crear una nueva noción';
$string['linkto_intro'] = "Seleccione, entre los módulos que incluyen ejercicios, las preguntas que quiere asociar a la noción: ";
$string['questions_assigned'] = 'Este plugin propone las preguntas siguientes: ';
$string['resource_assigned'] = 'El plugin está asociado al recurso siguiente: ';
$string['global_view'] = "Visión global";
$string['def_notions'] = 'Definir nociones';
$string['set_notions'] = 'Las nociones definidas:';
$string['add_notion_expl'] = 'Si no encuentra la noción deseada en la lista abajo, cree una:';
$string['whole_expl'] = 'Anclar el recurso completo (1 noción, 1 revisión):';
$string['each_expl'] = 'o elegir cada noción por separado (n noción, n revisión):';
$string['choose_q'] = 'Elegir preguntas';
$string['choose_q_from_qbank'] = 'Elegir desde banco de preguntas';
$string['create_q'] = 'Crear preguntas';
$string['stats_adv'] = "Avanzado";
$string['results'] = 'Resultados';
$string['question'] = "Pregunta ";
$string['start_btn'] = 'Empezar';
$string['student'] = 'Alumno';
$string['enrol_students'] = "Alumnos inscritos";
$string['avr_rate'] = "Tasa de éxito";
$string['testnav'] = "Navigation de la session de test";
$string['upd_qlist'] = 'La lista de preguntas está actualizada.';
$string['notion_created'] = 'La noción ha sido creada';
$string['confirm_notiondel'] = 'Se eliminará definitivamente toda la información sobre la noción y sus resultados.
¿Desea continuar?';
$string['notion_deleted'] = 'Noción eliminada';
$string['student_first_visit'] = "Es su primera visita al plugin Domoscio. Hemos creado su perfil
de aprendizaje. Cliquee en el botón abajo para continuar:";
$string['reviewed'] = 'Está revisando el recurso siguiente: ';
$string['notion_title'] = 'Título: ';
$string['text2'] = ' revisión';
$string['text3'] = ' por hacer';
$string['next_due_th'] = 'Próxima revisión';
$string['test_done'] = 'Tests realizados';
$string['test_todo'] = 'Tests por hacer';
$string['test_succeeded'] = 'Tests pasados';
$string['test_failed'] = 'Tests fallados';
$string['next_due'] = 'Próxima revisión de esta noción: ';
$string['no_history'] = "Esta noción no ha sido evaluada: ";
$string['validate_btn'] = 'Validar';
$string['select_answer'] = 'Elegir la respuesta correcta';
$string['answer'] = 'Respuesta: ';
$string['choose'] = 'Elegir... ';
$string['correction'] = 'La respuesta correcta es: ';
$string['desk'] = 'Mi agenda';
$string['module'] = 'Recurso';
$string['global_module'] = 'Recurso completo';
$string['do_test'] = 'Hacer el test';
$string['do_review_btn'] = 'Hacer la revisión';
$string['do_training'] = "Entrenarse";
$string['test_session'] = "Evaluación";
$string['see_notion'] = 'Ver la noción';
$string['stats'] = 'Estadísticas';
$string['score'] = 'Resultado';
$string['state'] = 'Estado';
$string['update'] = 'Actualizar';
$string['no_stats'] = 'Sin datos disponibles';
$string['tests_empty'] = "No existen preguntas para esta noción.";
$string['start_tests'] = 'Iniciar la sesión';
$string['no_test'] = "No tiene revisiones";
$string['running_time'] = "Duración de la sesión: ";
$string['notion_ok'] = "Noción conocida";
$string['notion_rvw'] = "Revisar la noción";
$string['next_btn'] = "Siguiente";
$string['home_btn'] = "Inicio";
$string['back_btn'] = "Volver";
$string['end_btn'] = "Terminar";
$string['at'] = " a ";
$string['gottatest'] = "Tiene una revisión por hacer ";
$string['today'] = "Hoy";
$string['tomorrow'] = "Mañana";
$string['days'] = "días";
$string['scorm_warning'] = "Advertencia: valide sus resultados en la ventana SCORM antes de pasar a la siguiente.";
$string['next_page'] = "Página siguiente";
$string['prev_page'] = "Página anterior";
$string['settings_required'] = "Missing";
$string['back_to_coursepage'] = "Volver a la pagina de curso";
$string['proxy_login_required'] = 'Debe estar conectado/a para acceder a esta url. Conéctese y reintente.';
$string['proxy_permission_required'] = 'No tiene permiso para accader a esta url.';
$string['proxy_curl_missing'] = 'Falta la LibcURL en su servidor PHP. Es necesaria para utilizar el plugin MediaServer.';
$string['proxy_action_missing'] = 'La operación no está configurada.';
$string['proxy_request_error'] = 'Error en la solicitud para MediaServer. Error:';
$string['proxy_parsing_error'] = 'No se puede analizar la respuesta de MediaServer.';
$string['import_activities'] = 'Importer des activités';
$string['import_activities_help'] = "Utilisez ce formulaire pour associer un paquet SCORM d'activités. Si vous le laissez vide, vous devrez associer des activités pour chaque notion à l'étape suivante.";
$string['no_questions_in_bank'] = "No questions in plugin bank yet.";
$string['instance_disabled'] = "L'accès au service Domoscio a été désactivé.";
