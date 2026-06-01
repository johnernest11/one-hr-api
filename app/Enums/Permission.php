<?php

namespace App\Enums;

enum Permission: string
{
    case VIEW_PROFILE = 'view_profile';
    case UPDATE_PROFILE = 'update_profile';
    case CREATE_EMPLOYEE_PDS = 'create_employee_pds';
    case VIEW_EMPLOYEE_PDS = 'view_employee_pds';
    case IMPORT_EMPLOYEE_PDS = 'import_employee_pds';
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
    case VIEW_PROGRAMS = 'view_programs';
    case VIEW_LOCATOR_ACTIVITIES = 'view_locator_activities';
    case VIEW_ITEMS = 'view_items';
    case CREATE_ITEMS = 'create_items';
    case UPDATE_ITEMS = 'update_items';
    case GENERATE_READ_UPDATE_QR_CODE = 'generate_read_update_qr_code';
    case VERIFY_QR_CODE = 'verify_qr_code';
    case LOG_TIME = 'log_time';
    case VIEW_ALL_TIME_LOGS = 'view_all_time_logs';
    case VIEW_DTR = 'view_dtr';
    case VIEW_WARM_BODIES_TODAY = 'view_warm_bodies_today';
    case UPDATE_DTR = 'update_dtr';
    case SEARCH_TIME_LOGS = 'search_time_logs';
    case CRUD_LOCATOR_SLIP = 'crud_locator_slip';
}
