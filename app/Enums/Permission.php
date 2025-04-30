<?php

namespace App\Enums;

enum Permission: string
{
    case VIEW_PROFILE = 'view_profile';
    case UPDATE_PROFILE = 'update_profile';
    case CREATE_EMPLOYEE_PDS = 'create_employee_pds';
    case VIEW_EMPLOYEE_PDS = 'view_employee_pds';
    case UPDATE_EMPLOYEE_PDS = 'update_employee_pds';
    case DELETE_EMPLOYEE_PDS = 'delete_employee_pds';
    case CREATE_USERS = 'create_users';
    case VIEW_USERS = 'view_users';
    case UPDATE_USERS = 'update_users';
    case DELETE_USERS = 'delete_users';
    case RECEIVE_SYSTEM_ALERTS = 'receive_system_alerts';
    case VIEW_USER_ROLES = 'view_user_roles';
    case VIEW_PERMISSIONS = 'view_permissions';
    case UPDATE_APP_SETTINGS = 'update_app_settings';
    case VIEW_POSITIONS = 'view_positions';
    case VIEW_FUNDS = 'view_funds';
    case VIEW_OFFICES = 'view_offices';
    case VIEW_DIVISIONS = 'view_divisions';
    case VIEW_SECTION_OR_UNITS = 'view_section_or_units';
    case VIEW_COUNTRIES = 'view_countries';
    case VIEW_SALARY_GRADES = 'view_salary_grades';
    case VIEW_ITEMS = 'view_items';
    case CREATE_ITEMS = 'create_items';
    case UPDATE_ITEMS = 'update_items';
}
