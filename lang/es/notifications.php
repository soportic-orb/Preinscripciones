<?php

declare(strict_types=1);

return [
    'preinscription_created_subject' => 'Preinscripción recibida — :course',
    'preinscription_created_body' => "Hola :name,\n\nHemos recibido tu preinscripción al curso :course (:edition). Nuestro equipo revisará tu documentación y te informará de los siguientes pasos.\n\nGracias por confiar en el IEM.",

    'preinscription_created_staff_subject' => 'Nueva preinscripción — :course',
    'preinscription_created_staff_body' => "Se ha registrado una nueva preinscripción al curso :course (:edition). Revísala en el panel de gestión.",

    'preinscription_accepted_subject' => 'Tu preinscripción ha sido aceptada — :course',
    'preinscription_accepted_body' => "Hola :name,\n\n¡Buenas noticias! Tu preinscripción al curso :course (:edition) ha sido aceptada. En breve podrás formalizar la matrícula y el pago desde tu panel.",

    'preinscription_rejected_subject' => 'Actualización de tu preinscripción — :course',
    'preinscription_rejected_body' => "Hola :name,\n\nTu preinscripción al curso :course (:edition) no ha podido ser aceptada. Motivo: :reason\n\nSi tienes dudas, contacta con el equipo de gestión.",

    'preinscription_waitlisted_subject' => 'Estás en lista de espera — :course',
    'preinscription_waitlisted_body' => "Hola :name,\n\nLa convocatoria :edition del curso :course no tiene plazas disponibles en este momento. Te hemos añadido a la lista de espera y te avisaremos si se libera una plaza.",

    'enrollment_available_subject' => 'Matrícula disponible — :course',
    'enrollment_available_body' => "Hola :name,\n\nYa puedes formalizar tu matrícula del curso :course (:edition) y realizar el pago desde tu panel de estudiante.",
    'payment_confirmed_subject' => 'Pago confirmado — IEM',
    'payment_confirmed_body' => "Hola :name,\n\nHemos registrado correctamente tu pago de :amount €. Gracias. En breve recibirás la factura en tu panel.",
    'new_message_subject' => 'Nuevo mensaje — IEM',
    "new_message_body" => "Hola :name,\n\nTienes un mensaje nuevo en tu panel del IEM. Inicia sesión para leerlo y responder.",
    'reminder_payment_subject' => 'Recordatorio de pago — IEM',
    "reminder_payment_body" => "Hola :name,\n\nTe recordamos que tienes un pago pendiente de :amount €. Puedes abonarlo desde tu panel de estudiante.",
    'reminder_documents_subject' => 'Documentación pendiente — IEM',
    "reminder_documents_body" => "Hola :name,\n\nTu preinscripción tiene documentación pendiente o rechazada. Revísala y súbela desde tu panel.",
    'reminder_course_start_subject' => 'Tu curso comienza pronto — :course',
    "reminder_course_start_body" => "Hola :name,\n\nEl curso :course (:edition) comienza en breve. ¡Te esperamos!",
    'reminder_deadline_subject' => 'Cierre de plazo próximo — :course',
    "reminder_deadline_body" => "El plazo de preinscripción de :course (:edition) está a punto de cerrarse.",
];
