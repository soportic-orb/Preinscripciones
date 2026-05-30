<?php

declare(strict_types=1);

return [
    'preinscription_created_subject' => 'Pre-registration received — :course',
    'preinscription_created_body' => "Hello :name,\n\nWe have received your pre-registration for the course :course (:edition). Our team will review your documents and inform you of the next steps.\n\nThank you for trusting IEM.",

    'preinscription_created_staff_subject' => 'New pre-registration — :course',
    'preinscription_created_staff_body' => "A new pre-registration for the course :course (:edition) has been registered. Review it in the management panel.",

    'preinscription_accepted_subject' => 'Your pre-registration has been accepted — :course',
    'preinscription_accepted_body' => "Hello :name,\n\nGood news! Your pre-registration for the course :course (:edition) has been accepted. You will soon be able to complete enrolment and payment from your dashboard.",

    'preinscription_rejected_subject' => 'Update on your pre-registration — :course',
    'preinscription_rejected_body' => "Hello :name,\n\nYour pre-registration for the course :course (:edition) could not be accepted. Reason: :reason\n\nIf you have any questions, contact the management team.",

    'preinscription_waitlisted_subject' => 'You are on the waiting list — :course',
    'preinscription_waitlisted_body' => "Hello :name,\n\nThe intake :edition of the course :course has no seats available at the moment. We have added you to the waiting list and will notify you if a seat becomes free.",

    'enrollment_available_subject' => 'Enrolment available — :course',
    'enrollment_available_body' => "Hello :name,\n\nYou can now complete your enrolment for the course :course (:edition) and make the payment from your student dashboard.",
];
