export const API_PREFIX = '/xapi';

export const STORAGE_KEYS = {
    sessionToken: 'session_token',
    userDetails: 'user_details',
    legacyToken: 'fb_token',
    legacyUser: 'fb_user',
};

export const FIELD_TYPES = ['text', 'textarea', 'number', 'email', 'phone', 'url', 'date', 'dropdown', 'radio', 'checkbox', 'file', 'section', 'rating', 'boolean'];
export const OPTION_TYPES = ['dropdown', 'radio', 'checkbox'];

export const FIELD_PALETTE = [
    ['text', 'Text'],
    ['textarea', 'Textarea'],
    ['number', 'Number'],
    ['email', 'Email'],
    ['phone', 'Phone'],
    ['url', 'URL'],
    ['date', 'Date'],
    ['dropdown', 'Dropdown'],
    ['radio', 'Radio'],
    ['checkbox', 'Checkbox'],
    ['file', 'File upload'],
    ['section', 'Section heading'],
    ['rating', 'Rating'],
    ['boolean', 'Yes/No'],
];

export const DEFAULT_PROMPTS = {
    create: 'Create an event registration form for an AI workshop.',
    edit: 'Add an emergency contact section.',
};
