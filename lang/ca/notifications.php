<?php

declare(strict_types=1);

return [
    'preinscription_created_subject' => 'Preinscripció rebuda — :course',
    'preinscription_created_body' => "Hola :name,\n\nHem rebut la teva preinscripció al curs :course (:edition). El nostre equip revisarà la teva documentació i t\'informarà dels següents passos.\n\nGràcies per confiar en l\'IEM.",

    'preinscription_created_staff_subject' => 'Nova preinscripció — :course',
    'preinscription_created_staff_body' => "S\'ha registrat una nova preinscripció al curs :course (:edition). Revisa-la al panell de gestió.",

    'preinscription_accepted_subject' => 'La teva preinscripció ha estat acceptada — :course',
    'preinscription_accepted_body' => "Hola :name,\n\nBones notícies! La teva preinscripció al curs :course (:edition) ha estat acceptada. Aviat podràs formalitzar la matrícula i el pagament des del teu panell.",

    'preinscription_rejected_subject' => 'Actualització de la teva preinscripció — :course',
    'preinscription_rejected_body' => "Hola :name,\n\nLa teva preinscripció al curs :course (:edition) no ha pogut ser acceptada. Motiu: :reason\n\nSi tens dubtes, contacta amb l\'equip de gestió.",

    'preinscription_waitlisted_subject' => 'Estàs en llista d\'espera — :course',
    'preinscription_waitlisted_body' => "Hola :name,\n\nLa convocatòria :edition del curs :course no té places disponibles en aquest moment. T\'hem afegit a la llista d\'espera i t\'avisarem si se\'n allibera una.",

    'enrollment_available_subject' => 'Matrícula disponible — :course',
    'enrollment_available_body' => "Hola :name,\n\nJa pots formalitzar la teva matrícula del curs :course (:edition) i fer el pagament des del teu panell d\'estudiant.",
    'payment_confirmed_subject' => 'Pagament confirmat — IEM',
    'payment_confirmed_body' => "Hola :name,\n\nHem registrat correctament el teu pagament de :amount €. Gràcies. Aviat rebràs la factura al teu panell.",
    'new_message_subject' => 'Nou missatge — IEM',
    "new_message_body" => "Hola :name,\n\nTens un missatge nou al teu panell de l'IEM. Inicia sessió per llegir-lo i respondre.",
    'reminder_payment_subject' => 'Recordatori de pagament — IEM',
    "reminder_payment_body" => "Hola :name,\n\nTe recordem que tens un pagament pendent de :amount €. Pots abonar-lo des del teu panell d'estudiant.",
    'reminder_documents_subject' => 'Documentació pendent — IEM',
    "reminder_documents_body" => "Hola :name,\n\nLa teva preinscripció té documentació pendent o rebutjada. Revisa-la i puja-la des del teu panell.",
    'reminder_course_start_subject' => 'El teu curs comença aviat — :course',
    "reminder_course_start_body" => "Hola :name,\n\nEl curs :course (:edition) comença aviat. T'esperem!",
    'reminder_deadline_subject' => 'Tancament de termini proper — :course',
    "reminder_deadline_body" => "El termini de preinscripció de :course (:edition) està a punt de tancar-se.",
];
