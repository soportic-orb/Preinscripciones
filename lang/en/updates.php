<?php

declare(strict_types=1);

return [
    'title' => 'Updates',
    'unavailable' => 'Automatic updates require Git and command-line access (VPS). On shared hosting, update by uploading the files manually.',
    'branch' => 'Branch',
    'current_commit' => 'Installed commit',
    'check' => 'Check for updates',
    'new_available' => 'There are :n update(s) available.',
    'up_to_date' => 'The platform is up to date.',
    'update_now' => 'Update now',
    'confirm' => 'Start the update? The site will enter maintenance mode.',
    'updated' => 'Platform updated successfully.',
    'failed' => 'The update failed (:error). It has been rolled back to the previous state.',
    'safeguards' => 'The update enables maintenance mode, backs up the database, applies the changes and migrations, and automatically rolls back on any failure.',
    'maintenance_title' => 'Under maintenance',
    'maintenance_body' => 'We are applying an update. Please come back in a few minutes.',
];
