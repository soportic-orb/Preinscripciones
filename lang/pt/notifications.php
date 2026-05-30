<?php

declare(strict_types=1);

return [
    'preinscription_created_subject' => 'Pré-inscrição recebida — :course',
    'preinscription_created_body' => "Olá :name,\n\nRecebemos a sua pré-inscrição no curso :course (:edition). A nossa equipa irá rever a sua documentação e informá-lo dos próximos passos.\n\nObrigado por confiar no IEM.",

    'preinscription_created_staff_subject' => 'Nova pré-inscrição — :course',
    'preinscription_created_staff_body' => "Foi registada uma nova pré-inscrição no curso :course (:edition). Reveja-a no painel de gestão.",

    'preinscription_accepted_subject' => 'A sua pré-inscrição foi aceite — :course',
    'preinscription_accepted_body' => "Olá :name,\n\nBoas notícias! A sua pré-inscrição no curso :course (:edition) foi aceite. Em breve poderá formalizar a matrícula e o pagamento a partir do seu painel.",

    'preinscription_rejected_subject' => 'Atualização da sua pré-inscrição — :course',
    'preinscription_rejected_body' => "Olá :name,\n\nA sua pré-inscrição no curso :course (:edition) não pôde ser aceite. Motivo: :reason\n\nSe tiver dúvidas, contacte a equipa de gestão.",

    'preinscription_waitlisted_subject' => 'Está em lista de espera — :course',
    'preinscription_waitlisted_body' => "Olá :name,\n\nA edição :edition do curso :course não tem vagas disponíveis neste momento. Adicionámo-lo à lista de espera e avisaremos se uma vaga ficar livre.",

    'enrollment_available_subject' => 'Matrícula disponível — :course',
    'enrollment_available_body' => "Olá :name,\n\nJá pode formalizar a sua matrícula do curso :course (:edition) e efetuar o pagamento a partir do seu painel de estudante.",
    'payment_confirmed_subject' => 'Pagamento confirmado — IEM',
    'payment_confirmed_body' => "Olá :name,\n\nRegistámos com sucesso o seu pagamento de :amount €. Obrigado. Em breve receberá a fatura no seu painel.",
    'new_message_subject' => 'Nova mensagem — IEM',
    "new_message_body" => "Olá :name,\n\nTem uma nova mensagem no seu painel do IEM. Inicie sessão para a ler e responder.",
    'reminder_payment_subject' => 'Lembrete de pagamento — IEM',
    "reminder_payment_body" => "Olá :name,\n\nLembramos que tem um pagamento pendente de :amount €. Pode efetuá-lo a partir do seu painel de estudante.",
    'reminder_documents_subject' => 'Documentação pendente — IEM',
    "reminder_documents_body" => "Olá :name,\n\nA sua pré-inscrição tem documentação pendente ou rejeitada. Reveja-a e carregue-a a partir do seu painel.",
    'reminder_course_start_subject' => 'O seu curso começa em breve — :course',
    "reminder_course_start_body" => "Olá :name,\n\nO curso :course (:edition) começa em breve. Contamos consigo!",
    'reminder_deadline_subject' => 'Prazo a terminar — :course',
    "reminder_deadline_body" => "O prazo de pré-inscrição de :course (:edition) está prestes a fechar.",
];
