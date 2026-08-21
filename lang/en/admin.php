<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for admin module messages.
    |
    */

    // Authentication
    'login_success' => 'Login successful.',
    'logout_success' => 'Logout successful.',
    'blocked' => 'This account has been disabled.',

    // Administrator management
    'admin_create_success' => 'Administrator created successfully.',
    'admin_update_success' => 'Administrator updated successfully.',
    'admin_delete_success' => 'Administrator deleted successfully.',
    'admin_cannot_delete_self' => 'You cannot delete yourself.',
    'admin_cannot_delete_super' => 'Super admin cannot be deleted.',
    'admin_cannot_disable_self' => 'You cannot disable yourself.',
    'admin_assign_roles_success' => 'Roles assigned successfully.',
    'admin_toggle_status_success' => 'Status toggled successfully.',
    'admin_reset_password_success' => 'Password reset successfully.',

    // Role management
    'role_create_success' => 'Role created successfully.',
    'role_update_success' => 'Role updated successfully.',
    'role_delete_success' => 'Role deleted successfully.',
    'role_assign_permissions_success' => 'Permissions assigned successfully.',
    'role_cannot_modify_super' => 'Super admin role cannot be modified.',
    'role_cannot_delete_super' => 'Super admin role cannot be deleted.',
    'role_in_use' => 'This role is in use and cannot be deleted.',

    // Menu management
    'menu_create_success' => 'Menu created successfully.',
    'menu_update_success' => 'Menu updated successfully.',
    'menu_delete_success' => 'Menu deleted successfully.',
    'menu_has_children' => 'Cannot delete: menu has children.',
    'menu_invalid_parent' => 'Parent menu cannot be itself or its descendants.',

    // Area management
    'area_invalid_parent' => 'Parent area cannot be itself or its descendants.',
    'area_has_children' => 'Cannot delete: area has children.',

    // Notification management
    'notification_mark_all_read_success' => 'All marked as read.',
    'notification_mark_read_success' => 'Marked as read successfully.',

    // System settings
    'setting_save_success' => 'Settings saved successfully.',
    'setting_save_empty' => 'No settings to save, please check the submitted data.',

    // Attachment management
    'attachment_delete_success' => 'Attachment deleted successfully.',
    'attachment_batch_delete_success' => 'Attachments deleted successfully.',
    'attachment_rename_success' => 'Attachment renamed successfully.',
    'attachment_move_success' => 'Attachment moved successfully.',
    'attachment_move_failed' => 'Failed to move the attachment.',
    'attachment_register_success' => 'Attachment registered successfully.',
    'attachment_file_missing' => 'The physical file does not exist.',
    'attachment_target_exists' => 'A file already exists at the target path.',
    'attachment_invalid_target_path' => 'The target path is invalid.',
    'attachment_temporary_url_unsupported' => 'The current storage driver does not support temporary URLs.',

    // Debug panel (Telescope)
    'debug_entry_not_found' => 'The debug entry does not exist.',
    'debug_resolve_success' => 'The exception has been marked as resolved.',
    'debug_monitor_success' => 'Tag monitoring enabled.',
    'debug_unmonitor_success' => 'Tag monitoring disabled.',
    'debug_recording_paused' => 'Recording paused.',
    'debug_recording_resumed' => 'Recording resumed.',
    'debug_clear_success' => 'Debug entries cleared.',
];
